<?php
// Short links: /go/<name> sends visitors to the saved address and counts the click.
declare(strict_types=1);
require __DIR__ . '/lib.php';
noindex();
$s = strtolower((string)($_GET['s'] ?? ''));
$r = null;
if (preg_match('/^[a-z0-9][a-z0-9-]{1,39}$/', $s)) {
    $q = db()->prepare('SELECT * FROM shortlinks WHERE slug = ? AND active = 1'); $q->execute([$s]); $r = $q->fetch();
}
if (!$r) { header('Location: /', true, 302); exit; }
db()->prepare('UPDATE shortlinks SET clicks = clicks + 1 WHERE slug = ?')->execute([$s]);
header('Cache-Control: no-store');
header('Location: ' . $r['target'], true, 302);
