<?php
// Admin > Library: browse every public gallery and download full-size originals (one photo, or a whole gallery as a zip).
// Originals live in the private masters folder, outside the web root. Included by index.php after the admin login check.
declare(strict_types=1);

function lib_slug_ok(string $s): bool { return strlen($s) <= 120 && (bool)preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $s); }
function lib_labels(string $slug): array {
    $f = masters_dir() . '/' . $slug . '/labels.json';
    $j = is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
    return is_array($j) ? $j : [];
}
function lib_files(string $slug): array {   // id => original file name (without .jpg), sorted by file name
    $dir = masters_dir() . '/' . $slug; $lab = lib_labels($slug)['files'] ?? []; $out = [];
    foreach (glob($dir . '/*.jpg') ?: [] as $p) { $id = basename($p, '.jpg'); $out[$id] = (string)($lab[$id] ?? $id); }
    asort($out, SORT_NATURAL | SORT_FLAG_CASE);
    return $out;
}
function lib_galleries(): array {   // slug => title, newest folder name first
    $out = [];
    foreach (glob(masters_dir() . '/*', GLOB_ONLYDIR) ?: [] as $d) {
        $slug = basename($d); if (!lib_slug_ok($slug)) continue;
        $t = lib_labels($slug)['title'] ?? $slug; $out[$slug] = (string)$t;
    }
    krsort($out, SORT_NATURAL);
    return $out;
}

if ($a === 'libfile') {
    $slug = (string)($_GET['g'] ?? ''); $id = (string)($_GET['id'] ?? '');
    $p = masters_dir() . '/' . $slug . '/' . $id . '.jpg';
    if (!lib_slug_ok($slug) || !preg_match('/^[A-Za-z0-9_-]+$/', $id) || !is_file($p)) { http_response_code(404); echo 'Not found'; exit; }
    $name = (lib_files($slug)[$id] ?? $id) . '.jpg';
    header('Content-Type: image/jpeg'); header('Content-Length: ' . filesize($p));
    header('Content-Disposition: attachment; filename="' . str_replace(['"', '\\', "\r", "\n"], '', $name) . '"'); header('Cache-Control: private, no-store');
    readfile($p); exit;
}

if ($a === 'libzip') {
    $slug = (string)($_GET['g'] ?? '');
    $files = lib_slug_ok($slug) ? lib_files($slug) : [];
    if (!$files || !class_exists('ZipArchive')) { http_response_code(404); echo 'Zip is not available for this gallery.'; exit; }
    @set_time_limit(0); @ini_set('memory_limit', '256M');
    $tmp = tempnam(sys_get_temp_dir(), 'eaz'); $z = new ZipArchive();
    if ($z->open($tmp, ZipArchive::OVERWRITE) !== true) { http_response_code(500); echo 'Could not build the zip.'; exit; }
    $seen = [];
    foreach ($files as $id => $nm) {
        $n = $nm . '.jpg'; if (isset($seen[$n])) $n = $nm . '-' . $id . '.jpg'; $seen[$n] = 1;
        $z->addFile(masters_dir() . '/' . $slug . '/' . $id . '.jpg', $n); $z->setCompressionName($n, ZipArchive::CM_STORE);
    }
    $z->close();
    header('Content-Type: application/zip'); header('Content-Length: ' . filesize($tmp));
    header('Content-Disposition: attachment; filename="' . $slug . '.zip"'); header('Cache-Control: private, no-store');
    readfile($tmp); @unlink($tmp); exit;
}

if ($a === 'library') {
    ahead('Library');
    $gals = lib_galleries(); $sel = (string)($_GET['g'] ?? ''); $base = '/client/admin/';
    if ($sel !== '' && !isset($gals[$sel])) $sel = '';
    if ($sel === ''): ?>
<div class="ph"><div><h1>Library</h1></div></div>
<p>Every gallery on the site. Open one to download full-size originals, one photo at a time or the whole gallery as a zip.</p>
<?php if (!$gals): ?><p>No originals found on the server yet.</p><?php endif; ?>
<ul class="list"><?php foreach ($gals as $s => $t): ?><li><a href="<?= $base ?>?a=library&g=<?= h($s) ?>"><?= h($t) ?></a> <small>(<?= count(lib_files($s)) ?> photos)</small></li><?php endforeach; ?></ul>
<?php else: $files = lib_files($sel); ?>
<div class="ph"><div><p class="back"><a href="<?= $base ?>?a=library">&larr; All galleries</a></p><h1><?= h($gals[$sel]) ?></h1></div><div class="act"><a class="btn" href="<?= $base ?>?a=libzip&g=<?= h($sel) ?>">Download all (zip)</a></div></div>
<p><?= count($files) ?> photos. <a href="/gallery/<?= h($sel) ?>/" target="_blank">View public gallery</a></p>
<div class="cards"><?php foreach ($files as $id => $nm): ?><div class="cd"><img loading="lazy" src="/photos/<?= h($sel) ?>/<?= h($id) ?>-t.jpg" alt=""><small><?= h($nm) ?></small><p><a href="<?= $base ?>?a=libfile&g=<?= h($sel) ?>&id=<?= h($id) ?>">Download original</a></p></div><?php endforeach; ?></div>
<?php endif; afoot(); exit;
}
