<?php
// Public photo likes: anonymous hearts with a count. GET ?g=<gallery> returns the counts; POST a=like g id on=1|0 adds or removes one.
require __DIR__ . '/lib.php';
header('X-Robots-Tag: noindex, nofollow'); header('Cache-Control: no-store');
$g = (string)($_REQUEST['g'] ?? '');
if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $g) || strlen($g) > 120) json_out(['error' => 'bad gallery'], 400);
$pdo = db();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $q = $pdo->prepare('SELECT id, n FROM photo_likes WHERE g = ? AND n > 0'); $q->execute([$g]);
    $o = []; foreach ($q as $r) $o[(string)$r['id']] = (int)$r['n'];
    json_out(['counts' => (object)$o]);
}
// ---- like / unlike
$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
if ($origin !== '' && strcasecmp((string)parse_url($origin, PHP_URL_HOST), preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? ''))) !== 0) json_out(['error' => 'no'], 403);
$ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
if ($ua === '' || preg_match('/bot|crawl|spider|curl|wget|python|java|headless/i', $ua)) json_out(['error' => 'no'], 403);
$id = (string)($_POST['id'] ?? ''); $on = !empty($_POST['on']);
if (!preg_match('/^\d{3,6}$/', $id)) json_out(['error' => 'bad photo'], 400);
if (!is_file(rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/') . '/photos/' . $g . '/' . $id . '-t.jpg')) json_out(['error' => 'no such photo'], 404);
// at most 60 likes or unlikes per visitor per hour (a visitor is a one-way hash, never stored with an address)
$salt = (string)setting('stats_salt', ''); if ($salt === '') { $salt = bin2hex(random_bytes(16)); set_setting('stats_salt', $salt); }
$h = hash('sha256', 'like|' . $salt . '|' . ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . $ua); $hr = date('YmdH');
$pdo->prepare('INSERT OR IGNORE INTO like_rate (h, hr, c) VALUES (?,?,0)')->execute([$h, $hr]);
$pdo->prepare('UPDATE like_rate SET c = c + 1 WHERE h = ? AND hr = ?')->execute([$h, $hr]);
$c = $pdo->prepare('SELECT c FROM like_rate WHERE h = ? AND hr = ?'); $c->execute([$h, $hr]);
$pdo->prepare('DELETE FROM like_rate WHERE hr < ?')->execute([$hr]);
if ((int)$c->fetchColumn() > 60) json_out(['error' => 'Slow down a little.'], 429);
$pdo->prepare('INSERT OR IGNORE INTO photo_likes (g, id, n) VALUES (?,?,0)')->execute([$g, $id]);
$pdo->prepare($on ? 'UPDATE photo_likes SET n = n + 1 WHERE g = ? AND id = ?' : 'UPDATE photo_likes SET n = MAX(0, n - 1) WHERE g = ? AND id = ?')->execute([$g, $id]);
$n = $pdo->prepare('SELECT n FROM photo_likes WHERE g = ? AND id = ?'); $n->execute([$g, $id]);
json_out(['ok' => true, 'n' => (int)$n->fetchColumn()]);
