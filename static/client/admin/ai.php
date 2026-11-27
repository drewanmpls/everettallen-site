<?php
// Admin > Galleries > Edit: "Suggest with Claude" drafts tags and a description from the gallery's title, date, series
// and up to 8 thumbnails. It only returns a draft for the editor's boxes; nothing is saved here.
// Calls the Claude Messages API over HTTP with curl (this repo adds no libraries). Key and model: Settings > Claude AI.
// Included by index.php after the admin login check; handles its own CSRF.
declare(strict_types=1);
require_once __DIR__ . '/content.php';

function ai_call(array $body): array {   // [http code, decoded JSON]; code 0 = network problem
    $ch = curl_init((getenv('EA_ANTHROPIC_BASE') ?: 'https://api.anthropic.com/') . 'v1/messages');
    $h = ['Content-Type: application/json', 'x-api-key: ' . trim((string)setting('claude_api_key', '')), 'anthropic-version: 2026-10-21'];
    if (isset($body['fallbacks'])) $h[] = 'anthropic-beta: server-side-fallback-2026-07-01';
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 150, CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => $h, CURLOPT_POSTFIELDS => json_encode($body)]);
    $res = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    $j = is_string($res) ? json_decode($res, true) : null;
    return [$code, is_array($j) ? $j : []];
}

if ($a === 'pgsuggest') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok()) json_out(['error' => 'Session expired. Reload the page and try again.'], 400);
    if (trim((string)setting('claude_api_key', '')) === '') json_out(['error' => 'Add a Claude API key in Settings > Claude AI first.'], 400);
    $slug = (string)($_POST['slug'] ?? '');
    if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug) || strlen($slug) > 120) json_out(['error' => 'Unknown gallery.'], 400);
    [$g, , $err] = content_get('content/galleries/' . $slug . '.json');
    if ($g === null) json_out(['error' => 'Could not read the gallery from GitHub: ' . $err], 400);
    @set_time_limit(180);

    // Up to 8 thumbnails, spread across the gallery, cover first. Read from this server's disk.
    $ids = array_values(array_filter(array_map(fn($im) => (string)($im['id'] ?? ''), is_array($g['images'] ?? null) ? $g['images'] : []), fn($i) => (bool)preg_match('/^\d{3,6}$/', $i)));
    $pick = []; $cover = (string)($g['cover'] ?? ''); if (in_array($cover, $ids, true)) $pick[] = $cover;
    $n = count($ids); for ($i = 0; $i < 8 && $n; $i++) { $id = $ids[(int)floor($i * $n / 8)]; if (!in_array($id, $pick, true)) $pick[] = $id; }
    $pick = array_slice($pick, 0, 8);
    $dir = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/') . '/photos/' . $slug;
    $content = [];
    foreach ($pick as $id) { $f = $dir . '/' . $id . '-t.jpg'; if (is_file($f) && filesize($f) < 2000000) $content[] = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => 'image/jpeg', 'data' => base64_encode((string)file_get_contents($f))]]; }
    if (!$content) json_out(['error' => 'No photos of this gallery were found on the server to show Claude.'], 400);

    // What the editor shows right now (the person may have typed a new title or series before asking)
    $title = trim((string)($_POST['title'] ?? $g['title'] ?? ''));
    $date = trim((string)($_POST['date'] ?? $g['date'] ?? ''));
    $cat = ((string)($_POST['category'] ?? $g['category'] ?? '')) === 'portraits' ? 'portrait session' : 'event';
    $sname = ''; $sr = (string)($_POST['series'] ?? $g['series'] ?? '');
    $sf = dirname(__DIR__) . '/series-index.json'; $sj = is_file($sf) ? json_decode((string)file_get_contents($sf), true) : [];
    foreach ((array)$sj as $x) if (is_array($x) && ($x['slug'] ?? '') === $sr) $sname = (string)($x['name'] ?? '');
    $have = trim((string)($_POST['description'] ?? ''));
    $venue = $cat === 'event' ? trim(mb_substr((string)($_POST['venue'] ?? $g['venue'] ?? ''), 0, 120)) : '';
    $notes = trim(mb_substr(str_replace("\r\n", "\n", (string)($_POST['notes'] ?? '')), 0, 1000));
    $facts = "Gallery title: $title\nDate: $date\nKind: $cat" . ($sname !== '' ? "\nEvent series: $sname" : '') . ($venue !== '' ? "\nVenue: $venue" : '')
        . ($notes !== '' ? "\nPhotographer's notes (facts you may use, including any names given here):\n$notes" : '')
        . ($have !== '' ? "\nCurrent description (improve it, keep its facts):\n$have" : '');

    // The site's own tags (prefer these) and up to 3 of Andrew's own descriptions (match his voice), from galleries-index.json
    $gi = json_decode((string)@file_get_contents(rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/') . '/client/galleries-index.json'), true);
    $tagCount = []; $examples = [];
    foreach ((array)$gi as $x) {
        if (!is_array($x)) continue;
        foreach ((array)($x['tags'] ?? []) as $t) if (is_string($t) && trim($t) !== '') { $k = trim($t); $tagCount[$k] = ($tagCount[$k] ?? 0) + 1; }
        $d0 = trim((string)($x['description'] ?? ''));
        if ($d0 !== '' && ($x['slug'] ?? '') !== $slug) $examples[] = [((($x['series'] ?? '') === $sr && $sr !== '') ? 2 : 0) + ((($x['category'] ?? '') === ($cat === 'event' ? 'events' : 'portraits')) ? 1 : 0), (string)($x['date'] ?? ''), $d0];
    }
    arsort($tagCount); $known = array_slice(array_keys($tagCount), 0, 150);
    usort($examples, fn($p, $q) => [$q[0], $q[1]] <=> [$p[0], $p[1]]);   // same series first, then same section, newest first
    $ex = array_map(fn($e) => $e[2], array_slice($examples, 0, 3));
    $guide = ($known ? "\n\nTags already used on the site (prefer these exact spellings when they fit; add a new tag only when none fits):\n" . implode(', ', $known) : '')
        . ($ex ? "\n\nDescriptions the photographer wrote for other galleries. Match this voice and length; do not copy their facts:\n- " . implode("\n- ", $ex) : '');
    $content[] = ['type' => 'text', 'text' => $facts . $guide . "\n\nThese are " . count($content) . ' photos from the gallery. Draft the tags and description.'];

    $system = 'You draft copy for Everett Allen Photography, a Minneapolis photographer whose public galleries cover portrait sessions and LGBTQ+ leather and kink community events. '
        . 'Write a short gallery description (2 or 3 sentences, plain text, warm and respectful, no hype) and 3 to 8 search tags. '
        . 'Use only facts given in the gallery details, the photographer\'s notes and what is plainly visible in the photos. Never guess names, places, titles, sponsors or numbers that are not given. '
        . 'Never identify a person from how they look; you may use a name only when the photographer\'s notes give it. Do not describe bodies in sexual or explicit terms; keep it suitable for a public portfolio. '
        . 'Tags are short (1 to 3 words, under 40 characters), describe the event or visible themes (for example a contest, a parade, harness, studio portrait), and do not repeat the year. '
        . 'Do not use em dashes.';
    $model = (string)setting('claude_model', 'claude-opus-5-5'); if (!isset(AI_MODELS[$model])) $model = 'claude-opus-5-5';
    $body = ['model' => $model, 'max_tokens' => 4000, 'system' => $system,
        'messages' => [['role' => 'user', 'content' => $content]],
        'output_config' => ['effort' => 'medium', 'format' => ['type' => 'json_schema', 'schema' => [
            'type' => 'object', 'additionalProperties' => false, 'required' => ['description', 'tags'],
            'properties' => ['description' => ['type' => 'string'], 'tags' => ['type' => 'array', 'items' => ['type' => 'string']]]]]]];
    if ($model !== 'claude-haiku-5-5') $body['fallbacks'] = 'default';   // another model tries if this one declines (not offered on Haiku)

    [$code, $r] = ai_call($body);
    if ($code === 0) json_out(['error' => 'Could not reach Claude. Check the connection and try again.'], 502);
    if ($code === 401 || $code === 403) json_out(['error' => 'Claude did not accept the API key. Check it in Settings > Claude AI.'], 400);
    if ($code === 429) json_out(['error' => 'Claude is busy or the account limit was reached. Try again in a minute.'], 429);
    if ($code !== 200) json_out(['error' => 'Claude said: ' . (string)($r['error']['message'] ?? ('error ' . $code))], 502);
    if (($r['stop_reason'] ?? '') === 'refusal') json_out(['error' => 'Claude declined to describe these photos. Write this one by hand.'], 422);
    $text = ''; foreach ((array)($r['content'] ?? []) as $b) if (($b['type'] ?? '') === 'text') $text .= (string)$b['text'];
    $out = json_decode($text, true);
    if (!is_array($out) || !is_string($out['description'] ?? null) || !is_array($out['tags'] ?? null)) json_out(['error' => 'Claude returned an answer this page could not read. Try again.'], 502);
    $desc = trim((string)preg_replace('/\s*\x{2014}\s*/u', ', ', strip_tags($out['description'])));   // site rule: no em dashes
    $tags = []; foreach ($out['tags'] as $t) { $t = trim(str_replace("\u{2014}", ' ', strip_tags((string)$t))); if ($t !== '' && mb_strlen($t) <= 40 && !in_array(mb_strtolower($t), array_map('mb_strtolower', $tags), true)) $tags[] = $t; }
    json_out(['ok' => true, 'description' => mb_substr($desc, 0, 2000), 'tags' => array_slice($tags, 0, 12), 'model' => (string)($r['model'] ?? $model), 'photos' => count($content) - 1]);
}
