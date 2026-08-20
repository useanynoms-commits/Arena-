const SESSION_LIMIT = 6;
const sessionUsage = new Map();

function send(res, status, body) {
  res.statusCode = status;
  res.setHeader?.('Content-Type', 'application/json');
  res.setHeader?.('Cache-Control', 'no-store');
  res.end(JSON.stringify(body));
}

async function readJson(req) {
  if (req.body && typeof req.body === 'object') return req.body;
  if (typeof req.body === 'string') return JSON.parse(req.body);
  const chunks = [];
  for await (const chunk of req) chunks.push(chunk);
  return JSON.parse(Buffer.concat(chunks).toString('utf8') || '{}');
}

function compact(value, limit) {
  return String(value || '').trim().replace(/\s+/g, ' ').slice(0, limit);
}

function normalize(result, products) {
  const descriptions = Array.isArray(result.productDescriptions) ? result.productDescriptions : [];
  const validIds = new Set(products.map((product) => String(product.id)));
  return {
    tagline: compact(result.tagline, 90),
    description: compact(result.description, 280),
    productDescriptions: descriptions
      .filter((item) => item && validIds.has(String(item.id)))
      .slice(0, 8)
      .map((item) => ({ id: String(item.id), description: compact(item.description, 180) })),
  };
}

function availableModels() {
  const preferred = process.env.GEMINI_MODEL || 'gemini-2.0-flash-lite';
  const configuredFallbacks = (process.env.GEMINI_FALLBACK_MODELS || 'gemini-2.0-flash,gemini-1.5-flash')
    .split(',').map((model) => model.trim()).filter(Boolean);
  return [...new Set([preferred, ...configuredFallbacks])];
}

async function generateWithGemini(store, apiKey) {
  const products = Array.isArray(store.products) ? store.products.slice(0, 8) : [];
  const prompt = `Create concise ecommerce copy. Return JSON only with this exact shape: {"tagline":"","description":"","productDescriptions":[{"id":"","description":""}]}.\nRules: tagline max 9 words. Brand description max 38 words. Each product description max 18 words. Do not invent claims, materials, quantities, prices, shipping, or certifications. Write only for provided details.\nBrand=${compact(store.brandName, 80)}; category=${compact(store.category, 70)}; existing tagline=${compact(store.tagline, 90)}; brand notes=${compact(store.description, 220)}; products=${JSON.stringify(products.map((product) => ({ id: String(product.id), name: compact(product.name, 80), category: compact(product.category, 55), notes: compact(product.description, 120) })))}.`;
  const body = JSON.stringify({
    contents: [{ role: 'user', parts: [{ text: prompt }] }],
    generationConfig: { temperature: 0.25, maxOutputTokens: 220, responseMimeType: 'application/json' },
  });
  const failures = [];
  for (const model of availableModels()) {
    try {
      const response = await fetch(`https://generativelanguage.googleapis.com/v1beta/models/${model}:generateContent?key=${encodeURIComponent(apiKey)}`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body, signal: AbortSignal.timeout(15000),
      });
      if (!response.ok) { failures.push(`${model}: HTTP ${response.status}`); continue; }
      const responseData = await response.json();
      const text = responseData.candidates?.[0]?.content?.parts?.map((part) => part.text || '').join('') || '{}';
      return { content: normalize(JSON.parse(text), products), model };
    } catch (error) { failures.push(`${model}: ${error.name || 'request failed'}`); }
  }
  throw new Error(`No configured Gemini model succeeded. ${failures.join('; ')}`);
}

function remainingFor(sessionId) {
  const record = sessionUsage.get(sessionId) || { count: 0, touched: Date.now() };
  record.touched = Date.now();
  sessionUsage.set(sessionId, record);
  if (sessionUsage.size > 1000) {
    for (const [id, candidate] of sessionUsage) if (Date.now() - candidate.touched > 1000 * 60 * 60 * 12) sessionUsage.delete(id);
  }
  return record;
}

export default async function handler(req, res, suppliedKey) {
  if (req.method !== 'POST') return send(res, 405, { error: 'Use POST for content generation.' });
  const apiKey = suppliedKey || process.env.GEMINI_API_KEY;
  if (!apiKey) return send(res, 503, { error: 'AI content generation is not configured on this deployment.' });
  const sessionId = String(req.headers?.['x-ecom-session'] || 'anonymous').slice(0, 120);
  const record = remainingFor(sessionId);
  if (record.count >= SESSION_LIMIT) return send(res, 429, { error: `Session limit reached. You can generate up to ${SESSION_LIMIT} drafts per browser session.`, limit: SESSION_LIMIT, remaining: 0 });
  try {
    const store = await readJson(req);
    if (!compact(store.brandName, 80)) return send(res, 400, { error: 'Add a brand name before generating content.' });
    const result = await generateWithGemini(store, apiKey);
    record.count += 1;
    return send(res, 200, { content: result.content, model: result.model, limit: SESSION_LIMIT, remaining: Math.max(0, SESSION_LIMIT - record.count) });
  } catch (error) {
    return send(res, 502, { error: 'Gemini could not generate a draft right now. Please try again.', detail: error.message.slice(0, 200) });
  }
}
