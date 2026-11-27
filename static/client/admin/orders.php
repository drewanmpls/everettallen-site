<?php
// Admin > Studio > Sales (T-33): every photo order (client gallery requests and public shop orders), newest first,
// with status and date filters, a totals line for the filtered set and a CSV of the same rows.
// Included by index.php after the admin login check. Read only: paying and declining stay on the session page.
declare(strict_types=1);

$st = (string)($_GET['status'] ?? ''); $sts = ['requested' => 'Needs action', 'paid' => 'Paid', 'declined' => 'Declined'];
if (!isset($sts[$st])) $st = '';
$per = (string)($_GET['when'] ?? ''); $ms = strtotime(date('Y-m-01'));
$pers = ['' => ['All time', 0, 0], 'month' => ['This month', $ms, 0], 'last' => ['Last month', strtotime('-1 month', $ms), $ms], 'year' => ['This year', strtotime(date('Y-01-01')), 0]];
if (!isset($pers[$per])) $per = '';
$sql = 'SELECT o.*, g.title, g.kind, i.label FROM orders o JOIN galleries g ON g.id = o.gallery_id LEFT JOIN images i ON i.id = o.image_id WHERE 1=1'; $args = [];
if ($st !== '') { $sql .= ' AND o.status = ?'; $args[] = $st; }
if ($pers[$per][1]) { $sql .= ' AND o.created >= ?'; $args[] = $pers[$per][1]; }
if ($pers[$per][2]) { $sql .= ' AND o.created < ?'; $args[] = $pers[$per][2]; }
$q = db()->prepare($sql . ' ORDER BY o.created DESC, o.id DESC'); $q->execute($args); $rows = $q->fetchAll();
$paid = array_values(array_filter($rows, fn($r) => $r['status'] === 'paid'));
$sum = array_sum(array_map(fn($r) => (int)$r['price_cents'], $paid));   // null (not recorded) adds 0
$price = fn($r) => $r['price_cents'] === null ? 'not recorded' : money((int)$r['price_cents']);

if ($a === 'orderscsv') {
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="sales.csv"');
    $o = fopen('php://output', 'w'); fputcsv($o, ['Date', 'Buyer email', 'Gallery', 'Photo', 'Size', 'Status', 'Price']);
    foreach ($rows as $r) fputcsv($o, [date('Y-m-d H:i', (int)$r['created']), $r['email'], $r['title'], (string)$r['label'], $r['tier'] === 'web' ? 'Web' : 'Full', $r['status'], $r['price_cents'] === null ? 'not recorded' : number_format((int)$r['price_cents'] / 100, 2, '.', '')]);
    exit;
}

ahead('Sales');
$keep = fn(array $o) => '?' . h(http_build_query(array_filter(array_merge(['a' => 'orders', 'status' => $st, 'when' => $per], $o), fn($v) => $v !== ''))); ?>
<div class="ph"><div><h1>Sales</h1></div><div class="act"><a class="btn alt" href="<?= $keep(['a' => 'orderscsv']) ?>">Download CSV</a></div></div>
<p class="hint">Every photo order, from client galleries and from the public galleries. Mark orders paid or declined on the session page.</p>
<div class="chips mt"><a class="tab <?= $st === '' ? 'on' : '' ?>"<?= $st === '' ? ' aria-current="page"' : '' ?> href="<?= $keep(['status' => '']) ?>">All</a>
<?php foreach ($sts as $k => $lab): ?><a class="tab <?= $st === $k ? 'on' : '' ?>"<?= $st === $k ? ' aria-current="page"' : '' ?> href="<?= $keep(['status' => $k]) ?>"><?= h($lab) ?></a><?php endforeach; ?></div>
<div class="chips"><?php foreach ($pers as $k => [$lab]): ?><a class="tab <?= $per === $k ? 'on' : '' ?>"<?= $per === $k ? ' aria-current="page"' : '' ?> href="<?= $keep(['when' => $k]) ?>"><?= h($lab) ?></a><?php endforeach; ?></div>
<p class="hint"><?= count($rows) ?> <?= count($rows) === 1 ? 'order' : 'orders' ?> &middot; <b><?= count($paid) ?> paid, $<?= number_format($sum / 100, 2) ?></b>. Totals only include orders made after this page was added.</p>
<?php if (!$rows): ?><p>No orders match.</p><?php else: ?>
<table class="stack"><tr><th>Date</th><th>Buyer</th><th>Gallery</th><th>Photo</th><th>Size</th><th>Status</th><th>Price</th></tr>
<?php foreach ($rows as $r): ?><tr><td data-label="Date"><?= h(date('M j, Y g:ia', (int)$r['created'])) ?></td><td data-label="Buyer"><?= h($r['email']) ?></td><td data-label="Gallery"><a href="?a=gallery&amp;id=<?= (int)$r['gallery_id'] ?>&amp;tab=activity#orders"><?= h($r['title']) ?></a><?= $r['kind'] === 'shop' ? ' <small>(public shop)</small>' : '' ?></td><td data-label="Photo"><?= h((string)($r['label'] ?? '')) ?></td><td data-label="Size"><?= $r['tier'] === 'web' ? 'Web' : 'Full' ?></td><td data-label="Status"><span class="tag <?= ['requested' => 'wait', 'paid' => 'good', 'declined' => 'bad'][$r['status']] ?? '' ?>"><?= h($sts[$r['status']] ?? $r['status']) ?></span></td><td data-label="Price"><?= $r['price_cents'] === null ? '<small>not recorded</small>' : h($price($r)) ?></td></tr>
<?php endforeach; ?></table>
<?php endif; ?>
<?php afoot(); exit;
