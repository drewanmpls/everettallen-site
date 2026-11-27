<?php
// Saving site content (content/*.json) through the GitHub API. Every commit to main rebuilds the site.
// Used by Publish (pubdone) and the Portfolio editors (T-24 on). Included by admin pages after the login check.
declare(strict_types=1);

// One GitHub API call. Returns [http code, decoded JSON]; code 0 means no token is saved.
function pub_gh(string $method, string $path, ?array $body = null): array {
    $tok = trim((string)setting('github_token', ''));
    $repo = trim((string)setting('github_repo', '')) ?: 'drewanmpls/everettallen-site';
    if ($tok === '') return [0, ['message' => 'No GitHub token saved yet (Settings).']];
    $ch = curl_init((getenv('EA_GITHUB_API') ?: 'https://api.github.com') . '/repos/' . $repo . '/' . ltrim($path, '/'));
    $opt = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25, CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $tok, 'Accept: application/vnd.github+json', 'User-Agent: everettallen-site-admin', 'X-GitHub-Api-Version: 2022-11-28', 'Content-Type: application/json']];
    if ($body !== null) $opt[CURLOPT_POSTFIELDS] = json_encode($body);
    curl_setopt_array($ch, $opt);
    $res = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    $j = is_string($res) ? json_decode($res, true) : null;
    return [$code, is_array($j) ? $j : []];
}

function content_path_ok(string $path): bool { return (bool)preg_match('#^content/[a-z0-9/_-]+\.json$#', $path) && strpos($path, '//') === false; }

// Plain-sentence error for a GitHub reply that was not a success.
function content_gh_error(int $code, array $j): string {
    if ($code === 0) return (string)($j['message'] ?? 'No GitHub token saved yet (Settings).');
    if ($code === 401 || $code === 403) return 'GitHub did not accept the token. Make a new one and save it in Settings.';
    if ($code === 404) return 'That file is not in the repository.';
    return 'GitHub said: ' . ($j['message'] ?? ('error ' . $code));
}

// Reads a JSON file from main. Returns [data, sha, error]: data and sha are null when it failed (error says why).
function content_get(string $path): array {
    if (!content_path_ok($path)) return [null, null, 'That is not a content file.'];
    [$code, $j] = pub_gh('GET', 'contents/' . $path . '?ref=main');
    if ($code !== 200) return [null, null, content_gh_error($code, $j)];
    $data = json_decode((string)base64_decode((string)($j['content'] ?? '')), true);
    if (!is_array($data)) return [null, null, 'The file on GitHub is not valid JSON.'];
    return [$data, (string)($j['sha'] ?? ''), ''];
}

// Writes a JSON file to main: 4-space indent, unescaped unicode and slashes, trailing newline.
// $sha is the version that was read ('' for a new file). If GitHub says it changed meanwhile (409 or 422),
// it re-reads the current version once and saves again, so the last save wins. Returns [ok, errorSentence].
function content_put(string $path, array $data, string $sha, string $message): array {
    if (!content_path_ok($path)) return [false, 'That is not a content file.'];
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) return [false, 'Could not turn the data into JSON.'];
    $body = ['message' => $message, 'branch' => 'main', 'content' => base64_encode($json . "\n")];
    for ($try = 0; $try < 2; $try++) {
        $b = $body; if ($sha !== '') $b['sha'] = $sha;
        [$code, $j] = pub_gh('PUT', 'contents/' . $path, $b);
        if ($code === 200 || $code === 201) return [true, ''];
        if (($code !== 409 && $code !== 422) || $try === 1) return [false, ($code === 409 || $code === 422) ? 'The file changed while saving. Please save again.' : content_gh_error($code, $j)];
        [$rc, $rj] = pub_gh('GET', 'contents/' . $path . '?ref=main');
        if ($rc === 200) $sha = (string)($rj['sha'] ?? ''); elseif ($rc === 404) $sha = ''; else return [false, content_gh_error($rc, $rj)];
    }
    return [false, 'The file changed while saving. Please save again.'];
}

// Checks a gallery against Section 3.3 of IMPLEMENTATION-PLAN.md. Returns a list of plain sentences (empty = fine).
// $seriesSlugs: the slugs in content/series.json; pass null to skip the series check.
function validate_gallery(array $g, ?array $seriesSlugs = null): array {
    $p = []; $str = fn($k) => isset($g[$k]) && is_string($g[$k]);
    $t = $str('title') ? trim($g['title']) : '';
    if ($t === '' || mb_strlen($t) > 200) $p[] = 'The title needs 1 to 200 characters.';
    elseif ($t !== strip_tags($t)) $p[] = 'The title must be plain text.';
    if (!in_array($g['category'] ?? null, ['portraits', 'events'], true)) $p[] = 'The category must be Portraits or Events.';
    if (!$str('year') || !preg_match('/^\d{4}$/', $g['year'])) $p[] = 'The year needs 4 digits.';
    $d = $str('date') ? DateTime::createFromFormat('!Y-m-d', $g['date']) : false;
    if (!$d || $d->format('Y-m-d') !== $g['date']) $p[] = 'The date must be a real date (YYYY-MM-DD).';
    if (isset($g['description']) && (!is_string($g['description']) || mb_strlen($g['description']) > 2000)) $p[] = 'The description can be at most 2,000 characters.';
    if (isset($g['series']) && (!is_string($g['series']) || ($seriesSlugs !== null && !in_array($g['series'], $seriesSlugs, true)))) $p[] = 'The series is not in the series list.';
    if (isset($g['tags'])) {
        if (!is_array($g['tags']) || count($g['tags']) > 12) $p[] = 'A gallery can have at most 12 tags.';
        else foreach ($g['tags'] as $tg) if (!is_string($tg) || trim($tg) === '' || mb_strlen($tg) > 40) { $p[] = 'Each tag needs 1 to 40 characters.'; break; }
    }
    if (isset($g['hidden']) && !is_bool($g['hidden'])) $p[] = 'Hidden must be on or off.';
    $ids = [];
    if (empty($g['images']) || !is_array($g['images']) || !array_is_list($g['images'])) $p[] = 'The gallery needs at least one photo.';
    else foreach ($g['images'] as $im) {
        $id = is_array($im) ? ($im['id'] ?? null) : null;
        if (!is_string($id) || !preg_match('/^\d{3,6}$/', $id)) { $p[] = 'Every photo needs an id of 3 to 6 digits, saved as text.'; break; }
        if (isset($ids[$id])) { $p[] = 'Photo ' . $id . ' is listed twice.'; break; }
        $ids[$id] = true;
        $r = $im['ratio'] ?? null;
        if (!(is_int($r) || is_float($r)) || $r <= 0.1 || $r >= 10) { $p[] = 'Photo ' . $id . ' has a bad shape value (ratio).'; break; }
    }
    if (!$str('cover') || ($ids && !isset($ids[$g['cover']]))) $p[] = 'The cover must be one of the gallery\'s photos.';
    return $p;
}
