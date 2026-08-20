function send(res, status, body) {
  res.statusCode = status;
  res.setHeader?.('Content-Type', 'application/json');
  res.setHeader?.('Cache-Control', 'no-store');
  res.end(JSON.stringify(body));
}

async function readBody(req) {
  if (req.body && typeof req.body === 'object') return req.body;
  const chunks = [];
  for await (const chunk of req) chunks.push(chunk);
  return JSON.parse(Buffer.concat(chunks).toString('utf8') || '{}');
}

const categoriesOf = (product) => Array.isArray(product.categories)
  ? product.categories
  : String(product.category || '').split(',').map((value) => value.trim());

export function rankCatalog(query, products) {
  const tokens = String(query || '').toLocaleLowerCase().trim().split(/\s+/).filter(Boolean).slice(0, 8);
  if (!tokens.length) return [];
  return (Array.isArray(products) ? products : []).map((product) => {
    const name = String(product.name || '').toLocaleLowerCase();
    const description = String(product.description || '').toLocaleLowerCase();
    const categories = categoriesOf(product).join(' ').toLocaleLowerCase();
    let score = 0;
    for (const token of tokens) {
      if (name === token) score += 30;
      else if (name.startsWith(token)) score += 18;
      else if (name.includes(token)) score += 12;
      if (categories.includes(token)) score += 7;
      if (description.includes(token)) score += 3;
    }
    return { id: product.id, score, name: String(product.name || '') };
  }).filter((result) => result.score > 0).sort((a, b) => b.score - a.score || a.name.localeCompare(b.name));
}

export default async function catalogSearch(req, res) {
  if (req.method !== 'POST') return send(res, 405, { error: 'Use POST for catalog search.' });
  try {
    const { query, products } = await readBody(req);
    // Product data is supplied by the tenant session; only IDs and score ordering return to the browser.
    const ranked = rankCatalog(query, products);
    return send(res, 200, { ids: ranked.map((result) => result.id), count: ranked.length });
  } catch {
    return send(res, 400, { error: 'Search payload could not be read.', ids: [] });
  }
}
