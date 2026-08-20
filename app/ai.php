<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

function ai_env(string $key): string {
    $value = getenv($key);
    if ($value !== false && $value !== '') return (string)$value;
    $local = ROOT_PATH . '/.env.local';
    if (is_file($local)) {
        foreach (file($local, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
            [$name, $candidate] = explode('=', $line, 2);
            if (trim($name) === $key) return trim($candidate, " \t\n\r\0\x0B\"");
        }
    }
    return '';
}
function ai_models(): array {
    $preferred = ai_env('GEMINI_MODEL') ?: 'gemini-2.0-flash-lite';
    $fallbacks = ai_env('GEMINI_FALLBACK_MODELS') ?: 'gemini-2.0-flash,gemini-1.5-flash';
    return array_values(array_unique(array_filter(array_map('trim', array_merge([$preferred], explode(',', $fallbacks))))));
}
function ai_compact(string $value, int $limit): string { return substr(trim(preg_replace('/\s+/', ' ', $value)), 0, $limit); }
function ai_request(string $model, string $key, array $body): array {
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($key);
    $payload = json_encode($body, JSON_UNESCAPED_SLASHES);
    if (function_exists('curl_init')) {
        $handle = curl_init($url); curl_setopt_array($handle, [CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_POSTFIELDS=>$payload,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15]);
        $response = curl_exec($handle); $status=(int)curl_getinfo($handle,CURLINFO_RESPONSE_CODE); $error=curl_error($handle); curl_close($handle);
        if($response===false || $status<200 || $status>=300) throw new RuntimeException($error ?: 'HTTP '.$status); return json_decode($response,true,512,JSON_THROW_ON_ERROR);
    }
    throw new RuntimeException('PHP cURL is required for AI content generation on this host.');
}
function ai_generate_store_copy(PDO $db, array $store): array {
    $limit = 6; $used=(int)($_SESSION['php_ai_generations'] ?? 0); if($used >= $limit) throw new RuntimeException('AI session limit reached. Start a new browser session to generate up to '.$limit.' more drafts.');
    $key=ai_env('GEMINI_API_KEY'); if(!$key) throw new RuntimeException('Set GEMINI_API_KEY in your hosting environment before using AI generation.');
    $query=$db->prepare('SELECT id,name,description FROM products WHERE store_id=? ORDER BY id DESC LIMIT 8');$query->execute([$store['id']]);$products=array_map(fn($p)=>['id'=>(string)$p['id'],'name'=>ai_compact($p['name'],80),'notes'=>ai_compact($p['description'],110)],$query->fetchAll());
    $prompt='Return compact JSON only: {"tagline":"","description":"","products":[{"id":"","description":""}]}. Tagline max 9 words. Brand description max 38 words. Product descriptions max 18 words. Never invent claims, materials, certifications, prices or shipping. Brand='.ai_compact($store['name'],80).'; current tagline='.ai_compact($store['tagline'],90).'; brand notes='.ai_compact($store['description'],220).'; products='.json_encode($products).'.';
    $body=['contents'=>[['role'=>'user','parts'=>[['text'=>$prompt]]]],'generationConfig'=>['temperature'=>0.25,'maxOutputTokens'=>220,'responseMimeType'=>'application/json']];$errors=[];
    foreach(ai_models() as $model){try{$data=ai_request($model,$key,$body);$text='';foreach(($data['candidates'][0]['content']['parts']??[]) as $part)$text.=$part['text']??'';$result=json_decode($text,true,512,JSON_THROW_ON_ERROR);$_SESSION['php_ai_generations']=$used+1;return ['model'=>$model,'remaining'=>$limit-$used-1,'tagline'=>ai_compact((string)($result['tagline']??''),90),'description'=>ai_compact((string)($result['description']??''),280),'products'=>array_slice(array_filter($result['products']??[],fn($item)=>is_array($item)&&isset($item['id'])),0,8)];}catch(Throwable $e){$errors[]=$model.': '.$e->getMessage();}}
    throw new RuntimeException('No configured AI model was available. '.implode('; ',array_slice($errors,0,2)));
}
