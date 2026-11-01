<?php
// Clean (no watermark) download. Allowed only for Free images or images marked paid.
declare(strict_types=1);
require __DIR__ . '/lib.php';
noindex();
$g = gallery_by_token((string)($_GET['t'] ?? ''));
if (!$g || !gallery_authorized($g)) { http_response_code(404); exit; }
$s = db()->prepare('SELECT * FROM images WHERE id = ? AND gallery_id = ?'); $s->execute([(int)($_GET['i'] ?? 0), $g['id']]);
$img = $s->fetch();
if (!$img) { http_response_code(404); exit; }
if (!can_download($img, $g)) { http_response_code(402); echo 'This photo is not unlocked yet.'; exit; }
$p = gdir((int)$g['id'], 'orig') . '/' . basename($img['file']);
if (!is_file($p)) { http_response_code(404); exit; }
db()->prepare('INSERT INTO downloads (image_id, created) VALUES (?, ?)')->execute([$img['id'], time()]);
$nice = preg_replace('/[^A-Za-z0-9._-]+/', '-', ($img['label'] ?: 'photo-' . $img['id'])) . '.jpg';
header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($p));
header('Content-Disposition: attachment; filename="' . $nice . '"');
header('Cache-Control: private, no-store');
readfile($p);
