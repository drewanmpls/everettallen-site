<?php
// Nightly database backup (run by cron, never from the web).
// Usage: php backup.php /home/dh_dbvmkc/everettallen-private/everettallenphotography.com
// Writes <private>/backups/data-YYYY-MM-DD.sqlite with VACUUM INTO (a clean, consistent copy even while the site is in use).
// Keeps the newest 30 daily files plus the first file of each of the last 12 months; deletes only older data-YYYY-MM-DD.sqlite files.
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

function fail(string $msg): void { fwrite(STDERR, "Backup failed: $msg\n"); exit(1); }

$priv = rtrim((string)($argv[1] ?? ''), '/');
if ($priv === '') fail('give the private folder path as the first argument.');
$db = $priv . '/data.sqlite';
if (!is_file($db)) fail("no data.sqlite in $priv");

$dir = $priv . '/backups';
if (!is_dir($dir) && !@mkdir($dir, 0700, true)) fail("could not create $dir");
@chmod($dir, 0700);

$tz = new DateTimeZone('America/Chicago');
$name = 'data-' . (new DateTime('now', $tz))->format('Y-m-d') . '.sqlite';
$out = $dir . '/' . $name;
if (is_file($out) && !@unlink($out)) fail("could not replace today's file $name");

try {
    $pdo = new PDO('sqlite:' . $db, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('PRAGMA busy_timeout = 10000');
    $pdo->exec("VACUUM INTO '" . str_replace("'", "''", $out) . "'");
    $pdo = null;
    $chk = new PDO('sqlite:' . $out, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $ok = $chk->query('PRAGMA integrity_check')->fetchColumn();
    $chk = null;
    if ($ok !== 'ok') { @unlink($out); fail("the copy did not pass the integrity check ($ok)"); }
} catch (Throwable $e) { if (is_file($out)) @unlink($out); fail($e->getMessage()); }
@chmod($out, 0600);

// Retention
$files = [];
foreach (scandir($dir) ?: [] as $f) if (preg_match('/^data-(\d{4})-(\d{2})-(\d{2})\.sqlite$/', $f)) $files[] = $f;
rsort($files);                                   // newest first (names sort by date)
$keep = array_flip(array_slice($files, 0, 30));
$months = [];
foreach (array_reverse($files) as $f) {          // oldest first, so the first file seen in a month is that month's first
    $m = substr($f, 5, 7);
    if (!isset($months[$m])) $months[$m] = $f;
}
krsort($months);
foreach (array_slice($months, 0, 12, true) as $f) $keep[$f] = true;
$removed = 0;
foreach ($files as $f) if (!isset($keep[$f]) && @unlink($dir . '/' . $f)) $removed++;

$kb = (int)round(filesize($out) / 1024);
echo "$name {$kb} KB" . ($removed ? ", removed $removed old backup" . ($removed > 1 ? 's' : '') : '') . "\n";
