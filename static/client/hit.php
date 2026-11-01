<?php
// Privacy-friendly visit counter. No cookies, no IP addresses or user agents stored.
// Counts page views per day, plus daily unique visitors using a one-way hash that is deleted the next day.
require __DIR__ . '/lib.php';
header('X-Robots-Tag: noindex, nofollow'); header('Cache-Control: no-store');
http_response_code(204);
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') exit;
$ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
if ($ua === '' || preg_match('/bot|crawl|spider|slurp|preview|fetch|curl|wget|python|java|http|facebookexternalhit|embedly|monitor|headless|lighthouse/i', $ua)) exit;
$j = json_decode((string)file_get_contents('php://input', false, null, 0, 4096), true);
if (!is_array($j)) exit;
$p = (string)($j['p'] ?? '');
if ($p === '' || $p[0] !== '/' || strlen($p) > 120 || !preg_match('#^/[A-Za-z0-9/_\-.]*$#', $p) || strpos($p, '/client') === 0) exit;
$host = strtolower((string)preg_replace('/^www\./', '', (string)parse_url((string)($j['r'] ?? ''), PHP_URL_HOST)));
$own = strtolower((string)preg_replace('/^www\./', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
$base = preg_replace('/^[^.]+\./', '', $own);   // new.example.com -> example.com
if ($host === '') $ref = 'direct';
elseif ($host === $own || $host === $base || substr($host, -strlen($base) - 1) === '.' . $base) $ref = '';   // internal navigation
else $ref = substr($host, 0, 80);
$w = (int)($j['w'] ?? 0);
$dev = $w <= 0 ? 'unknown' : ($w < 700 ? 'phone' : ($w < 1100 ? 'tablet' : 'desktop'));
$day = (new DateTime('now', new DateTimeZone('America/Chicago')))->format('Y-m-d');
$salt = (string)setting('stats_salt', '');
if ($salt === '') { $salt = bin2hex(random_bytes(16)); set_setting('stats_salt', $salt); }
$h = hash('sha256', $day . '|' . $salt . '|' . ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . $ua);
$pdo = db();
$pdo->prepare('INSERT OR IGNORE INTO stat_hits (day, path, ref, dev, hits) VALUES (?,?,?,?,0)')->execute([$day, $p, $ref, $dev]);
$pdo->prepare('UPDATE stat_hits SET hits = hits + 1 WHERE day = ? AND path = ? AND ref = ? AND dev = ?')->execute([$day, $p, $ref, $dev]);
$pdo->prepare('INSERT OR IGNORE INTO stat_days (day, hits, visitors) VALUES (?,0,0)')->execute([$day]);
$new = $pdo->prepare('INSERT OR IGNORE INTO stat_seen (day, h) VALUES (?,?)'); $new->execute([$day, $h]);
$pdo->prepare('UPDATE stat_days SET hits = hits + 1' . ($new->rowCount() ? ', visitors = visitors + 1' : '') . ' WHERE day = ?')->execute([$day]);
$pdo->prepare('DELETE FROM stat_seen WHERE day < ?')->execute([$day]);
