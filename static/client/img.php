<?php
// Serves watermarked copies only. Originals are never served from here.
declare(strict_types=1);
require __DIR__ . '/lib.php';
noindex();
$g = gallery_by_token((string)($_GET['t'] ?? ''));
if (!$g || !gallery_authorized($g)) { http_response_code(404); exit; }
$s = db()->prepare('SELECT * FROM images WHERE id = ? AND gallery_id = ?'); $s->execute([(int)($_GET['i'] ?? 0), $g['id']]);
$img = $s->fetch();
if (!$img) { http_response_code(404); exit; }
$kind = ($_GET['s'] ?? 'display') === 'thumb' ? 'thumb' : 'display';
$p = gdir((int)$g['id'], $kind) . '/' . basename($img['file']);
if (!is_file($p)) { http_response_code(404); exit; }
header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($p));
header('Cache-Control: private, max-age=86400');
readfile($p);
