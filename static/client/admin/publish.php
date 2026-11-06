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
function pub_titles(): array {
    $f = dirname(__DIR__) . '/gallery-titles.json';
    $j = is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
    return is_array($j) ? $j : [];
}

// ---------- GitHub (saves the gallery file; the site rebuilds on every commit) ----------
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
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok()) json_out(['error' => 'Session expired. Reload the page and try again.'], 400);
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
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok()) json_out(['error' => 'Session expired. Reload the page and try again.'], 400);
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
            $g = ['title' => $title, 'category' => (($d['category'] ?? '') === 'portraits') ? 'portraits' : 'events', 'year' => substr($date, 0, 4), 'date' => $date, 'cover' => (string)array_key_first($new), 'description' => '', 'images' => []];
        }
        $have = []; foreach ($g['images'] as $k => $im) $have[(string)$im['id']] = $k;
        foreach ($new as $id => $ratio) { if (isset($have[$id])) $g['images'][$have[$id]]['ratio'] = $ratio; else $g['images'][] = ['id' => $id, 'ratio' => $ratio]; }
        if (empty($g['cover']) || !in_array($g['cover'], array_map(fn($i) => (string)$i['id'], $g['images']), true)) $g['cover'] = (string)$g['images'][0]['id'];
        $body = ['message' => 'Gallery: ' . $slug . ' (' . count($g['images']) . ' photos)', 'branch' => 'main',
                 'content' => base64_encode(json_encode($g, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n")];
        if ($sha) $body['sha'] = $sha;
        [$pc, $pr] = pub_gh('PUT', $path, $body);
        if ($pc === 200 || $pc === 201) json_out(['ok' => true, 'url' => '/gallery/' . $slug . '/', 'count' => count($g['images']), 'created' => $code !== 200]);
        if ($pc !== 409 && $pc !== 422) json_out(['error' => 'GitHub said: ' . ($pr['message'] ?? ('error ' . $pc))], 400);
    }
    json_out(['error' => 'The gallery changed while saving. Please press Publish again.'], 409);
}

// ---------- page ----------
ahead('Publish photos'); $tok = csrf();
$hasTok = trim((string)setting('github_token', '')) !== '';
$limit = min(pub_bytes((string)ini_get('upload_max_filesize')), pub_bytes((string)ini_get('post_max_size')));
$titles = pub_titles(); asort($titles, SORT_NATURAL | SORT_FLAG_CASE);
?>
<h1 style="font-size:clamp(32px,5vw,64px)">Publish photos</h1>
<p>Upload JPEGs straight from your browser to a public gallery. The full-size originals are kept privately (for paid downloads), and the web copies get the small corner logo. Adding to a gallery you already published is fine: only the new photos are added.</p>
<?php if (!$hasTok): ?>
<p class="err"><b>One-time setup needed:</b> this page saves the gallery to GitHub, so it needs a GitHub token. Add it in <a href="/client/admin/?a=settings#github">Settings</a> first.</p>
<?php endif; ?>
<form id="pf" style="max-width:720px" onsubmit="return false">
<label style="display:flex;gap:10px;align-items:center"><input type="radio" name="mode" value="new" style="width:auto" checked> New gallery</label>
<div id="newbox">
<label>Title<input id="ptitle" placeholder="2026 TCLF Friday Night" autocomplete="off"></label>
<div class="cols"><label>Date<input id="pdate" type="date"></label>
<label>Section<select id="pcat"><option value="events">Events</option><option value="portraits">Portraits</option></select></label></div>
<label>Web address (made from the date and title; you can edit it)<input id="pslug" autocomplete="off"></label>
</div>
<?php if ($titles): ?>
<label style="display:flex;gap:10px;align-items:center"><input type="radio" name="mode" value="old" style="width:auto"> Add to an existing gallery</label>
<div id="oldbox" hidden><label>Gallery<select id="pold"><?php foreach ($titles as $s => $t): ?><option value="<?= h($s) ?>"><?= h($t) ?></option><?php endforeach; ?></select></label></div>
<?php endif; ?>
<div class="drop" style="margin-top:16px"><input type="file" id="pfiles" accept="image/jpeg" multiple style="max-width:420px">
<p class="kicker">JPEGs only. Largest file the server accepts: <b><?= h(number_format($limit / 1048576, 0)) ?> MB</b>.</p></div>
<p><button class="btn" id="pgo" <?= $hasTok ? '' : 'disabled' ?>>Upload and publish</button></p>
<p id="pstat" class="kicker"></p><ul id="plist" style="list-style:none;padding:0;font-size:14px"></ul>
</form>
<script>
(function(){
var CSRF=<?= json_encode($tok) ?>,LIMIT=<?= (int)$limit ?>,$=function(i){return document.getElementById(i)};
$('pdate').value=new Date().toISOString().slice(0,10);
function slugify(t){return t.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'')}
var slugEdited=false;function auto(){if(!slugEdited)$('pslug').value=slugify($('pdate').value+' '+$('ptitle').value)}
$('ptitle').oninput=auto;$('pdate').oninput=auto;$('pslug').oninput=function(){slugEdited=true};
document.querySelectorAll('input[name=mode]').forEach(function(r){r.onchange=function(){var o=document.querySelector('input[name=mode]:checked').value==='old';$('newbox').hidden=o;if($('oldbox'))$('oldbox').hidden=!o}});
function post(fd){return fetch('/client/admin/',{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json().catch(function(){return{error:'The server returned an unexpected answer (the file may be too big).'}})})}
$('pgo').onclick=async function(){
 var old=document.querySelector('input[name=mode]:checked').value==='old';
 var slug=old?$('pold').value:$('pslug').value.trim(),title=old?$('pold').options[$('pold').selectedIndex].text:$('ptitle').value.trim();
 if(!slug||(!old&&!title)){alert('Fill in the title first.');return}
 var files=[].slice.call($('pfiles').files).filter(function(f){return /\.jpe?g$/i.test(f.name)}).sort(function(a,b){return a.name.localeCompare(b.name,undefined,{numeric:true})});
 if(!files.length){alert('Choose some JPEGs first.');return}
 $('pgo').disabled=true;var list=$('plist');list.innerHTML='';var done=[],bad=0;
 for(var i=0;i<files.length;i++){
  var f=files[i],li=document.createElement('li');li.textContent=f.name+': ';list.appendChild(li);
  $('pstat').textContent='Uploading '+(i+1)+' of '+files.length+'... keep this page open.';
  if(f.size>LIMIT){li.textContent+='too big for the server ('+Math.round(f.size/1048576)+' MB), skipped';bad++;continue}
  var res=null;
  for(var t=0;t<2&&!(res&&res.ok);t++){var fd=new FormData();fd.append('a','pubfile');fd.append('csrf',CSRF);fd.append('slug',slug);fd.append('title',title);fd.append('photo',f);res=await post(fd).catch(function(){return{error:'network error'}})}
  if(res&&res.ok){done.push({id:res.id,ratio:res.ratio});li.textContent+='ok'}else{li.textContent+=(res&&res.error)||'failed';bad++}
 }
 if(!done.length){$('pstat').textContent='Nothing was uploaded.';$('pgo').disabled=false;return}
 $('pstat').textContent='Saving the gallery...';
 var fd=new FormData();fd.append('a','pubdone');fd.append('csrf',CSRF);fd.append('payload',JSON.stringify({slug:slug,title:title,date:$('pdate').value,category:$('pcat').value,images:done}));
 var r=await post(fd);
 if(r&&r.ok){$('pstat').innerHTML='Published '+done.length+' photo'+(done.length>1?'s':'')+(bad?' ('+bad+' skipped).':'.')+' The site rebuilds in a minute or two: <a href="'+r.url+'" target="_blank">open the gallery</a>.'+(r.created?' Fill in the description and series in Pages CMS.':'')}
 else{$('pstat').textContent='Photos are uploaded but the gallery was not saved: '+((r&&r.error)||'unknown error')+' You can press the button again (nothing is duplicated).';$('pgo').disabled=false}
};
})();
</script>
<?php afoot(); exit;
