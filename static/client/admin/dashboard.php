<?php
// Admin home: today's numbers, things that need attention, recent orders and shortcuts.
// Included by index.php after the admin login check.
declare(strict_types=1);

if ($a === 'dashboard') {
    ahead('Dashboard');
    $db = db(); $today = date('Y-m-d');
    $sum = function (int $days) use ($db): array {
        $s = $db->prepare('SELECT COALESCE(SUM(visitors),0) v, COALESCE(SUM(hits),0) h FROM stat_days WHERE day >= ?');
        $s->execute([date('Y-m-d', strtotime('-' . ($days - 1) . ' days'))]); return $s->fetch() ?: ['v' => 0, 'h' => 0];
    };
    $d1 = $sum(1); $d7 = $sum(7); $d30 = $sum(30);
    $likes = (int)$db->query('SELECT COALESCE(SUM(n),0) FROM photo_likes')->fetchColumn();
    $since7 = strtotime('-7 days');
    $dl7 = $db->prepare('SELECT COUNT(*) FROM downloads WHERE created >= ?'); $dl7->execute([$since7]); $dl7 = (int)$dl7->fetchColumn();
    $pending = (int)$db->query("SELECT COUNT(*) FROM orders WHERE status = 'requested'")->fetchColumn();
    $newc = $db->query("SELECT id, name, service, timeframe, created FROM clients WHERE status = 'new' ORDER BY created DESC LIMIT 8")->fetchAll();
    $newcN = (int)$db->query("SELECT COUNT(*) FROM clients WHERE status = 'new'")->fetchColumn();
    $orders = $db->query("SELECT o.id, o.status, o.email, o.created, g.id gid, g.title, i.label FROM orders o JOIN galleries g ON g.id = o.gallery_id JOIN images i ON i.id = o.image_id ORDER BY o.created DESC LIMIT 8")->fetchAll();
    $top = $db->prepare("SELECT path, SUM(hits) h FROM stat_hits WHERE day >= ? GROUP BY path ORDER BY h DESC LIMIT 6");
    $top->execute([date('Y-m-d', strtotime('-6 days'))]); $top = $top->fetchAll();
    $stat = ['requested' => 'Needs action', 'paid' => 'Paid', 'declined' => 'Declined'];
    $n = fn($v) => number_format((int)$v);
?>
<style>.tiles{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin:14px 0 8px}.tile{background:#f6f3ec;border:1px solid var(--line);padding:12px 14px}.tile b{display:block;font-size:30px;font-family:'Archivo',sans-serif;line-height:1.1}.tile span{font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:var(--mut)}.tile.hot{border-color:var(--ink);background:var(--ink);color:var(--bg)}.tile.hot span{color:inherit;opacity:.8}.tile a{color:inherit}.go{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px}.go a{display:block;padding:14px;border:1px solid var(--ink);text-decoration:none;font-family:'Archivo Narrow',sans-serif;text-transform:uppercase;letter-spacing:.08em}.go a small{display:block;text-transform:none;letter-spacing:0;font-family:inherit;color:var(--mut);margin-top:2px}.go a:hover{background:var(--ink);color:var(--bg)}.go a:hover small{color:inherit}</style>
<h1 style="font-size:clamp(32px,5vw,64px)">Dashboard</h1>
<div class="tiles">
<a class="tile <?= $pending ? 'hot' : '' ?>" href="<?= $base ?>?a=list#orders" style="text-decoration:none"><b><?= $n($pending) ?></b><span>Orders needing action</span></a>
<a class="tile <?= $newcN ? 'hot' : '' ?>" href="<?= $base ?>?a=clients" style="text-decoration:none"><b><?= $n($newcN) ?></b><span>New inquiries</span></a>
<div class="tile"><b><?= $n($d1['v']) ?></b><span>Visitors today</span></div>
<div class="tile"><b><?= $n($d7['v']) ?></b><span>Visitors, 7 days</span></div>
<div class="tile"><b><?= $n($d30['v']) ?></b><span>Visitors, 30 days</span></div>
<div class="tile"><b><?= $n($dl7) ?></b><span>Downloads, 7 days</span></div>
<div class="tile"><b><?= $n($likes) ?></b><span>Photo likes</span></div>
</div>

<h2>Jump to</h2>
<div class="go">
<a href="<?= $base ?>?a=publish">Publish photos<small>Upload to a gallery</small></a>
<a href="<?= $base ?>?a=library">Library<small>Download originals</small></a>
<a href="<?= $base ?>?a=list">Client galleries<small>Orders, prices, links</small></a>
<a href="<?= $base ?>?a=clients">Clients<small>Inquiries and records</small></a>
<a href="<?= $base ?>?a=codes">Codes<small>Free download links</small></a>
<a href="<?= $base ?>?a=links">Short links<small>Short addresses for QR codes</small></a>
<a href="<?= $base ?>?a=stats">Stats<small>Pages, sources, likes</small></a>
<a href="https://app.pagescms.org" target="_blank" rel="noopener">Site content<small>Pages CMS (galleries, text)</small></a>
<a href="<?= $base ?>?a=settings">Settings<small>Payments, keys</small></a>
</div>

<div class="cols">
<div><h2>New inquiries</h2>
<?php if (!$newc): ?><p>No new inquiries.</p><?php else: ?><table><?php foreach ($newc as $c): ?><tr><td><a href="<?= $base ?>?a=client&id=<?= (int)$c['id'] ?>"><?= h($c['name']) ?></a><br><small style="color:var(--mut)"><?= h(trim($c['service'] . ' ' . ($c['timeframe'] ? '(' . $c['timeframe'] . ')' : ''))) ?></small></td><td><?= h(date('M j', (int)$c['created'])) ?></td></tr><?php endforeach; ?></table><?php endif; ?></div>
<div><h2>Top pages, 7 days</h2>
<?php if (!$top): ?><p>No visits recorded yet.</p><?php else: ?><table><?php foreach ($top as $t): ?><tr><td><a href="<?= h($t['path']) ?>" target="_blank"><?= h($t['path']) ?></a></td><td><?= $n($t['h']) ?></td></tr><?php endforeach; ?></table><?php endif; ?></div>
</div>

<h2 id="orders">Recent orders</h2>
<?php if (!$orders): ?><p>No orders yet.</p><?php else: ?><table><tr><th>When</th><th>Photo</th><th>Gallery</th><th>Buyer</th><th>Status</th></tr>
<?php foreach ($orders as $o): ?><tr><td><?= h(date('M j, g:ia', (int)$o['created'])) ?></td><td><?= h($o['label']) ?></td><td><a href="<?= $base ?>?a=gallery&id=<?= (int)$o['gid'] ?>#orders"><?= h($o['title']) ?></a></td><td><?= h($o['email']) ?></td><td><span class="pill"><?= h($stat[$o['status']] ?? $o['status']) ?></span></td></tr><?php endforeach; ?></table><?php endif; ?>
<?php afoot(); exit;
}
