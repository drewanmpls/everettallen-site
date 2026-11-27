<?php
// Admin > Publish photos: upload JPEGs from the browser to a public gallery.
// The server stores the full-size original privately (masters), makes the 400px and 1000px (corner logo) copies in photos/<gallery>/,
// then saves content/galleries/<gallery>.json to GitHub so the site rebuilds. Mirrors scripts/eap-publish.py.
// Included by index.php after the admin login check; handles its own CSRF.
declare(strict_types=1);

function pub_slug_ok(string $s): bool { return strlen($s) <= 120 && (bool)preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $s); }
function pub_photos_dir(string $slug): string { return rtrim($_SERVER['DOCUMENT_ROOT'] ?? '.', '/') . '/photos/' . $slug; }
function pub_masters_dir(string $slug): string { return masters_dir() . '/' . $slug; }
function pub_bytes(string $v): int { $v = trim($v); $n = (int)$v; return match (strtolower(substr($v, -1))) { 'g' => $n << 30, 'm' => $n << 20, 'k' => $n << 10, default => $n }; }
function pub_series(): array {   // slug => name, from dist/client/series-index.json (built from content/series.json)
    $f = dirname(__DIR__) . '/series-index.json';
    $j = is_file($f) ? json_decode((string)file_get_contents($f), true) : null; $o = [];
    if (is_array($j)) foreach ($j as $x) if (is_array($x) && isset($x['slug'])) $o[(string)$x['slug']] = (string)($x['name'] ?? $x['slug']);
    return $o;
}
function pub_titles(): array {
    $f = dirname(__DIR__) . '/gallery-titles.json';
    $j = is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
    return is_array($j) ? $j : [];
}

require_once __DIR__ . '/content.php';   // pub_gh() and the content helpers

// ---------- images ----------
function pub_logo_mark($im): void {   // small logo, bottom-right, soft shadow (same look as the Mac script)
    $logo = wm_logo(); if (!$logo) return;
    $w = imagesx($im); $h = imagesy($im);
    $lw = max(90, (int)($w * 0.2)); $lh = max(10, (int)(imagesy($logo) * $lw / imagesx($logo)));
    $s = imagecreatetruecolor($lw, $lh); imagealphablending($s, false); imagesavealpha($s, true);
    imagefill($s, 0, 0, imagecolorallocatealpha($s, 0, 0, 0, 127));
    imagecopyresampled($s, $logo, 0, 0, 0, 0, $lw, $lh, imagesx($logo), imagesy($logo));
    $m = (int)($w * 0.03); $x = $w - $lw - $m; $y = $h - $lh - $m;
    imagealphablending($im, true);
    foreach ([[0, 0, 0, 0.45, 2], [null, null, null, 0.7, 0]] as [$r0, $g0, $b0, $op, $off]) {
        $t = imagecreatetruecolor($lw, $lh); imagealphablending($t, false); imagesavealpha($t, true);
        imagefill($t, 0, 0, imagecolorallocatealpha($t, 0, 0, 0, 127));
        for ($yy = 0; $yy < $lh; $yy++) for ($xx = 0; $xx < $lw; $xx++) {
            $c = imagecolorat($s, $xx, $yy); $a = ($c >> 24) & 127; if ($a >= 127) continue;
            $na = 127 - (int)round((127 - $a) * $op);
            $r = $r0 ?? (($c >> 16) & 255); $g = $g0 ?? (($c >> 8) & 255); $b = $b0 ?? ($c & 255);
            imagesetpixel($t, $xx, $yy, imagecolorallocatealpha($t, $r, $g, $b, $na));
        }
        imagecopy($im, $t, $x + $off, $y + $off, 0, 0, $lw, $lh); imagedestroy($t);
    }
    imagedestroy($s);
}

// ---------- actions ----------
if ($a === 'pubfile') {   // one JPEG per request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !($apiAuth ?? false) && !csrf_ok()) json_out(['error' => 'Session expired. Reload the page and try again.'], 400);
    $slug = (string)($_POST['slug'] ?? '');
    if (!pub_slug_ok($slug)) json_out(['error' => 'Bad gallery name.'], 400);
    $f = $_FILES['photo'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK) json_out(['error' => $f && $f['error'] === UPLOAD_ERR_INI_SIZE ? 'File is bigger than the server upload limit.' : 'Upload failed.'], 400);
    @ini_set('memory_limit', '768M'); @set_time_limit(180);
    if ((new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) !== 'image/jpeg') json_out(['error' => 'Only JPEG files.'], 400);
    $src = @imagecreatefromjpeg($f['tmp_name']);
    if (!$src) json_out(['error' => 'Could not read that image.'], 400);
    if (function_exists('exif_read_data')) {
        $o = (@exif_read_data($f['tmp_name'])['Orientation'] ?? 1);
        if ($o == 3) $src = imagerotate($src, 180, 0); elseif ($o == 6) $src = imagerotate($src, -90, 0); elseif ($o == 8) $src = imagerotate($src, 90, 0);
    }
    $md = pub_masters_dir($slug); $pd = pub_photos_dir($slug);
    foreach ([$md => 0700, $pd => 0755] as $d => $mode) if (!is_dir($d) && !@mkdir($d, $mode, true)) json_out(['error' => 'Could not create the gallery folder on the server.'], 500);
    $lockf = fopen($md . '/.lock', 'c'); flock($lockf, LOCK_EX);
    try {
        $labFile = $md . '/labels.json';
        $lab = is_file($labFile) ? (json_decode((string)file_get_contents($labFile), true) ?: []) : [];
        $lab['files'] = $lab['files'] ?? [];
        if (!empty($_POST['title']) && empty($lab['title'])) $lab['title'] = mb_substr(trim(strip_tags((string)$_POST['title'])), 0, 200);
        $name = preg_replace('/\.jpe?g$/i', '', basename((string)$f['name']));
        $id = array_search($name, $lab['files'], true);          // same file name again: replace that photo
        if ($id === false) {
            $max = 0;
            foreach (array_merge(glob($md . '/*.jpg') ?: [], glob($pd . '/*-t.jpg') ?: []) as $p) if (preg_match('/^(\d+)/', basename($p), $m)) $max = max($max, (int)$m[1]);
            $id = sprintf('%03d', $max + 1);
        }
        $id = (string)$id;
        if (!copy($f['tmp_name'], $md . '/' . $id . '.jpg')) json_out(['error' => 'Could not save the original.'], 500);
        @chmod($md . '/' . $id . '.jpg', 0600);
        $t = resize_to($src, 400); imagejpeg($t, $pd . '/' . $id . '-t.jpg', 80); imagedestroy($t);
        $l = resize_to($src, 1000); pub_logo_mark($l); imagejpeg($l, $pd . '/' . $id . '-l.jpg', 84); imagedestroy($l);
        @chmod($pd . '/' . $id . '-t.jpg', 0644); @chmod($pd . '/' . $id . '-l.jpg', 0644);
        $ratio = round(imagesx($src) / imagesy($src), 4);
        $lab['files'][$id] = $name;
        file_put_contents($labFile, json_encode($lab, JSON_UNESCAPED_UNICODE));
    } finally { flock($lockf, LOCK_UN); fclose($lockf); }
    json_out(['ok' => true, 'id' => $id, 'ratio' => $ratio, 'name' => $name]);
}

if ($a === 'pubdone') {   // save the gallery file to GitHub
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !($apiAuth ?? false) && !csrf_ok()) json_out(['error' => 'Session expired. Reload the page and try again.'], 400);
    $d = json_decode((string)($_POST['payload'] ?? ''), true);
    $slug = (string)($d['slug'] ?? '');
    if (!is_array($d) || !pub_slug_ok($slug) || empty($d['images']) || !is_array($d['images'])) json_out(['error' => 'Nothing to publish.'], 400);
    $new = [];
    foreach ($d['images'] as $im) if (is_array($im) && preg_match('/^\d{3,6}$/', (string)($im['id'] ?? '')) && is_numeric($im['ratio'] ?? null) && $im['ratio'] > 0.1 && $im['ratio'] < 10) $new[(string)$im['id']] = round((float)$im['ratio'], 4);
    if (!$new) json_out(['error' => 'Nothing to publish.'], 400);
    $path = 'contents/content/galleries/' . $slug . '.json';
    for ($try = 0; $try < 2; $try++) {
        [$code, $cur] = pub_gh('GET', $path . '?ref=main');
        if ($code === 0) json_out(['error' => $cur['message']], 400);
        if ($code === 401 || $code === 403) json_out(['error' => 'GitHub did not accept the token. Make a new one and save it in Settings.'], 400);
        $sha = null;
        if ($code === 200) { $sha = $cur['sha'] ?? null; $g = json_decode((string)base64_decode((string)($cur['content'] ?? '')), true); if (!is_array($g)) json_out(['error' => 'Could not read the existing gallery file.'], 500); }
        else {
            $title = mb_substr(trim(strip_tags((string)($d['title'] ?? ''))), 0, 200);
            $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($d['date'] ?? '')) ? $d['date'] : date('Y-m-d');
            if ($title === '') json_out(['error' => 'A new gallery needs a title.'], 400);
            // Events keep the year in the title ("2026 TCLF Friday"), even if the sender left it off.
            if (!empty($apiAuth) && !preg_match('/^\d{4}\b/', $title) && (($d['category'] ?? '') !== 'portraits')) $title = substr($date, 0, 4) . ' ' . $title;
            $g = ['title' => $title, 'category' => (($d['category'] ?? '') === 'portraits') ? 'portraits' : 'events', 'year' => substr($date, 0, 4), 'date' => $date, 'cover' => (string)array_key_first($new), 'description' => '', 'images' => []];
            // optional series and tags (Publish page, T-39); only for a new gallery file. The NAS watcher sends neither.
            $sr = is_string($d['series'] ?? null) ? trim($d['series']) : ''; if ($sr !== '') $g['series'] = $sr;
            $tg = []; foreach ((is_array($d['tags'] ?? null) ? $d['tags'] : []) as $t) if (is_string($t) && ($t = trim($t)) !== '' && !in_array($t, $tg, true)) $tg[] = $t;
            if ($tg) $g['tags'] = $tg;
        }
        $have = []; foreach ($g['images'] as $k => $im) $have[(string)$im['id']] = $k;
        foreach ($new as $id => $ratio) { $id = (string)$id; if (isset($have[$id])) $g['images'][$have[$id]]['ratio'] = $ratio; else $g['images'][] = ['id' => $id, 'ratio' => $ratio]; }
        if (empty($g['cover']) || !in_array($g['cover'], array_map(fn($i) => (string)$i['id'], $g['images']), true)) $g['cover'] = (string)$g['images'][0]['id'];
        if ($code !== 200 && (isset($g['series']) || isset($g['tags']))) { $pv = validate_gallery($g, array_keys(pub_series())); if ($pv) json_out(['error' => implode(' ', $pv)], 400); }
        $body = ['message' => 'Gallery: ' . $slug . ' (' . count($g['images']) . ' photos)', 'branch' => 'main',
                 'content' => base64_encode(json_encode($g, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n")];
        if ($sha) $body['sha'] = $sha;
        [$pc, $pr] = pub_gh('PUT', $path, $body);
        if ($pc === 200 || $pc === 201) json_out(['ok' => true, 'url' => '/gallery/' . $slug . '/', 'count' => count($g['images']), 'created' => $code !== 200]);
        if ($pc !== 409 && $pc !== 422) json_out(['error' => 'GitHub said: ' . ($pr['message'] ?? ('error ' . $pc))], 400);
    }
    json_out(['error' => 'The gallery changed while saving. Please press Publish again.'], 409);
}

if ($a === 'nasstatus') {   // the NAS watcher's status report; only the NAS key may send it
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($apiAuth)) json_out(['error' => 'Only the folder watcher sends this.'], 403);
    $d = json_decode((string)($_POST['payload'] ?? ''), true);
    if (!is_array($d) || !is_array($d['folders'] ?? null)) json_out(['error' => 'Bad status.'], 400);
    $clean = fn($v) => mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', is_string($v) ? $v : '')), 0, 200);
    $out = [];
    foreach (array_slice($d['folders'], 0, 50) as $f) {
        if (!is_array($f) || !in_array($f['state'] ?? '', ['ready', 'working', 'published', 'failed'], true)) continue;
        $out[] = ['name' => $clean($f['name'] ?? ''), 'state' => $f['state'], 'photos' => max(0, min(100000, (int)($f['photos'] ?? 0))), 'error' => $clean($f['error'] ?? '')];
    }
    set_setting('nas_status', json_encode(['t' => time(), 'version' => mb_substr($clean($d['version'] ?? ''), 0, 40), 'folders' => $out], JSON_UNESCAPED_UNICODE));
    json_out(['ok' => true]);
}

// ---------- page ----------
ahead('Publish photos'); $tok = csrf();
$hasTok = trim((string)setting('github_token', '')) !== '';
$limit = min(pub_bytes((string)ini_get('upload_max_filesize')), pub_bytes((string)ini_get('post_max_size')));
$titles = pub_titles(); asort($titles, SORT_NATURAL | SORT_FLAG_CASE);
$series = pub_series();
?>
<div class="ph"><div><h1>Publish photos</h1></div></div>
<p>Upload JPEGs straight from your browser to a public gallery. The full-size originals are kept privately (for paid downloads), and the web copies get the small corner logo. Adding to a gallery you already published is fine: only the new photos are added.</p>
<?php if (!$hasTok): ?>
<p class="msg wait" role="alert"><b>One-time setup needed:</b> this page saves the gallery to GitHub, so it needs a GitHub token. Add it in <a href="/client/admin/?a=settings&tab=publishing#github">Settings</a> first.</p>
<?php endif; ?>
<form id="pf" class="form" data-csrf="<?= h($tok) ?>" data-limit="<?= (int)$limit ?>">
<label class="check"><input type="radio" name="mode" value="new" checked> New gallery</label>
<div id="newbox">
<label>Title<input id="ptitle" placeholder="2026 TCLF Friday Night" autocomplete="off"></label>
<div class="cols"><label>Date<input id="pdate" type="date"></label>
<label>Section<select id="pcat"><option value="events">Events</option><option value="portraits">Portraits</option></select></label></div>
<label>Web address (made from the date and title; you can edit it)<input id="pslug" autocomplete="off"></label>
<div class="cols"><label>Event series<select id="pser"><option value="">None</option><?php foreach ($series as $k => $n): ?><option value="<?= h($k) ?>"><?= h($n) ?></option><?php endforeach; ?></select></label>
<label>Tags (optional, one per line)<textarea id="ptags" rows="3"></textarea></label></div>
</div>
<?php if ($titles): ?>
<label class="check"><input type="radio" name="mode" value="old"> Add to an existing gallery</label>
<div id="oldbox" hidden><label>Gallery<select id="pold"><?php foreach ($titles as $s => $t): ?><option value="<?= h($s) ?>"><?= h($t) ?></option><?php endforeach; ?></select></label></div>
<?php endif; ?>
<label class="drop mt" id="pdrop"><input type="file" id="pfiles" class="sr" accept="image/jpeg" multiple>
<b>Drop JPEGs here, or tap to choose photos</b>
<span class="hint">JPEGs only. Largest file the server accepts: <?= h(number_format($limit / 1048576, 0)) ?> MB.</span>
<span id="pchosen" class="hint" aria-live="polite"></span></label>
<p class="mt"><button class="btn" id="pgo" <?= $hasTok ? '' : 'disabled' ?>>Upload and publish</button></p>
<p id="pstat" role="status" aria-live="polite"></p><ul id="plist" class="prog"></ul>
<p id="plive" class="msg wait" role="status" hidden></p>
</form>
<script src="<?= h(asset('/client/admin/publish.js')) ?>"></script>
<?php $ns = json_decode((string)setting('nas_status', ''), true); $hasKey = setting('nas_token_hash', '') !== ''; ?>
<section class="panel mt" aria-labelledby="nash"><h2 id="nash">NAS folder watcher</h2>
<?php if (!is_array($ns)): ?><p><?= $hasKey ? 'Not heard from yet. It reports in once a minute after the new watcher is installed on the NAS.' : 'No folder watcher key yet. Make one in <a href="?a=settings&amp;tab=publishing#nas">Settings</a>.' ?></p>
<?php else: $ago = time() - (int)$ns['t']; $when = (new DateTime('@' . (int)$ns['t']))->setTimezone(new DateTimeZone('America/Chicago'));
    $by = []; foreach ($ns['folders'] as $f) $by[$f['state']][] = $f; ?>
<p class="msg <?= $ago > 600 ? 'wait' : 'good' ?>" role="status">Last heard from <?= h($when->format('M j, Y g:i a')) ?><?= $ago > 600 ? ': not heard from in over 10 minutes. Check that the NAS and its eap-watcher container are on.' : '' ?></p>
<?php if (!empty($by['working'])): ?><h3>Working now</h3><ul><?php foreach ($by['working'] as $f): ?><li><?= h($f['name']) ?> (<?= (int)$f['photos'] ?> photos)</li><?php endforeach; ?></ul><?php endif; ?>
<?php if (!empty($by['ready'])): ?><h3>Waiting to start</h3><ul><?php foreach ($by['ready'] as $f): ?><li><?= h($f['name']) ?></li><?php endforeach; ?></ul><?php endif; ?>
<?php if (!empty($by['failed'])): ?><h3>Failed</h3><ul><?php foreach ($by['failed'] as $f): ?><li><span class="tag bad">failed</span> <?= h($f['name']) ?><?= $f['error'] !== '' ? ': ' . h($f['error']) : '' ?></li><?php endforeach; ?></ul><p class="hint">Fix the problem, then rename the folder back to end with -READY.</p><?php endif; ?>
<h3>Last published</h3><?php if (empty($by['published'])): ?><p>None yet.</p><?php else: ?><ul><?php foreach (array_slice($by['published'], 0, 5) as $f): ?><li><?= h($f['name']) ?><?= $f['photos'] ? ' (' . (int)$f['photos'] . ' photos)' : '' ?></li><?php endforeach; ?></ul><?php endif; ?>
<?php if (($ns['version'] ?? '') !== ''): ?><p class="hint">Watcher version <?= h($ns['version']) ?></p><?php endif; ?>
<?php endif; ?></section>
<?php afoot(); exit;
