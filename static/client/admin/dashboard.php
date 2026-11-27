<?php
// Admin home, "Today" (T-44): a "Needs you" list first, then upcoming sessions, latest public galleries, recent sales and the visit tiles.
// Included by index.php after the admin login check. Read only.
declare(strict_types=1);

if ($a === 'dashboard') {
    ahead('Today');
    $db = db(); $today = date('Y-m-d');
    $sum = function (int $days) use ($db): array {
        $s = $db->prepare('SELECT COALESCE(SUM(visitors),0) v, COALESCE(SUM(hits),0) h FROM stat_days WHERE day >= ?');
        $s->execute([date('Y-m-d', strtotime('-' . ($days - 1) . ' days'))]); return $s->fetch() ?: ['v' => 0, 'h' => 0];
    };
    $d1 = $sum(1); $d7 = $sum(7); $d30 = $sum(30);
    $likes = (int)$db->query('SELECT COALESCE(SUM(n),0) FROM photo_likes')->fetchColumn();
    $since7 = strtotime('-7 days');
    $dl7 = $db->prepare('SELECT COUNT(*) FROM downloads WHERE created >= ?'); $dl7->execute([$since7]); $dl7 = (int)$dl7->fetchColumn();
    $cnt = function (string $sql, array $args = []) use ($db): int { $s = $db->prepare($sql); $s->execute($args); return (int)$s->fetchColumn(); };
    $pending = $cnt('SELECT COUNT(*) FROM orders WHERE status = ?', ['requested']);
    $newcN = $cnt('SELECT COUNT(*) FROM clients WHERE status = ?', ['new']);
    $noid = $cnt("SELECT COUNT(*) FROM clients WHERE status != ? AND COALESCE(release_at, '') NOT IN ('', 0) AND TRIM(COALESCE(id_verified, '')) = ''", ['archived']);
    $reply = $cnt("SELECT COUNT(DISTINCT i.gallery_id) FROM comments m JOIN images i ON i.id = m.image_id JOIN galleries g ON g.id = i.gallery_id
        WHERE g.kind <> ? AND m.from_owner = 0 AND NOT EXISTS (SELECT 1 FROM comments r WHERE r.image_id = m.image_id AND r.from_owner = 1 AND r.id > m.id)", ['shop']);
    // Sessions with a shoot date (their own, else the client's), the same way the Sessions page works it out
    $ses = $db->prepare("SELECT g.id, g.title, g.client_name, g.shoot_date, g.shoot_location, c.name AS cname, c.shoot_date AS cdate, c.shoot_location AS cloc
        FROM galleries g LEFT JOIN clients c ON c.id = g.client_id WHERE g.kind <> ?"); $ses->execute(['shop']);
    $up = [];
    foreach ($ses->fetchAll() as $r) {
        $r['when'] = ses_date((string)$r['shoot_date'], $r['cdate']); if ($r['when'] === '' || $r['when'] < $today) continue;
        $r['who'] = trim((string)$r['client_name']) !== '' ? (string)$r['client_name'] : (string)($r['cname'] ?? '');
        $r['where'] = trim((string)$r['shoot_location']) !== '' ? (string)$r['shoot_location'] : (string)($r['cloc'] ?? '');
        $up[] = $r;
    }
    usort($up, fn($x, $y) => [$x['when'], $x['id']] <=> [$y['when'], $y['id']]);   // soonest first
    $soon = count(array_filter($up, fn($r) => $r['when'] <= date('Y-m-d', strtotime('+14 days'))));
    // Public galleries, from the list build.py writes (same "Needs info" rule as the Galleries page)
    $gf = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/') . '/client/galleries-index.json';
    $pg = is_file($gf) ? json_decode((string)file_get_contents($gf), true) : null; $pg = is_array($pg) ? $pg : [];
    foreach ($pg as $k => $g) $pg[$k] = (is_array($g) ? $g : []) + ['slug' => '', 'title' => '', 'date' => '', 'category' => '', 'series' => '', 'cover' => '', 'hidden' => false, 'described' => false];
    $info = count(array_filter($pg, fn($g) => !$g['described'] || ($g['category'] === 'events' && $g['series'] === '')));
    $latest = array_slice(array_values(array_filter($pg, fn($g) => !$g['hidden'] && $g['cover'] !== '')), 0, 6);
    $ns = json_decode((string)setting('nas_status', ''), true);
    $nasFail = is_array($ns) && is_array($ns['folders'] ?? null) ? count(array_filter($ns['folders'], fn($f) => ($f['state'] ?? '') === 'failed')) : 0;
    $sales = $db->query("SELECT o.id, o.status, o.price_cents, o.created, o.gallery_id, g.title, g.kind, i.label FROM orders o JOIN galleries g ON g.id = o.gallery_id LEFT JOIN images i ON i.id = o.image_id ORDER BY o.created DESC, o.id DESC LIMIT 6")->fetchAll();
    $stat = ['requested' => ['Needs action', 'wait'], 'paid' => ['Paid', 'good'], 'declined' => ['Declined', 'bad']];
    $n = fn($v) => number_format((int)$v);
    $fmt = fn(string $d) => date('M j, Y', (int)strtotime($d));
    // Needs you: count, words (one, many), link. Rows with nothing in them are left out.
    $needs = array_filter([
        [$pending, ['photo request not paid yet', 'photo requests not paid yet'], '?a=orders&status=requested'],
        [$newcN, ['new inquiry', 'new inquiries'], '?a=clients&status=new'],
        [$reply, ['session with client comments to answer', 'sessions with client comments to answer'], '?a=sessions&f=reply'],
        [$noid, ['signed release with no ID check', 'signed releases with no ID check'], '?a=clients&rel=noid'],
        [$soon, ['session in the next 14 days', 'sessions in the next 14 days'], '?a=sessions&f=upcoming'],
        [$info, ['public gallery with no series or description', 'public galleries with no series or description'], '?a=pgalleries&f=needs'],
        [$nasFail, ['NAS folder failed', 'NAS folders failed'], '?a=publish#nash'],
    ], fn($r) => $r[0] > 0);
?>
<div class="ph"><div><h1>Today</h1></div></div>
<section class="panel" aria-labelledby="needh"><h2 id="needh" class="mt0">Needs you</h2>
<?php if (!$needs): ?><p>Nothing waiting. Nice.</p><?php else: ?><ul class="needs">
<?php foreach ($needs as [$c, $w, $href]): ?><li><a href="<?= $base . h($href) ?>"><b><?= $n($c) ?></b><span><?= h($w[$c === 1 ? 0 : 1]) ?></span><i aria-hidden="true">&rsaquo;</i></a></li>
<?php endforeach; ?></ul><?php endif; ?></section>

<h2>Upcoming sessions</h2>
<?php if (!$up): ?><p>No sessions with a shoot date ahead. <a href="<?= $base ?>?a=sessions#new">New session</a></p><?php else: ?><table class="stack"><tr><th>Date</th><th>Session</th><th>Client</th><th>Where</th></tr>
<?php foreach (array_slice($up, 0, 6) as $r): ?><tr><td data-label="Date" class="nw"><?= h($fmt($r['when'])) ?></td><td data-label="Session"><a href="<?= $base ?>?a=gallery&amp;id=<?= (int)$r['id'] ?>"><?= h((string)$r['title']) ?></a></td><td data-label="Client"><?= h($r['who']) ?></td><td data-label="Where"><?= h($r['where']) ?></td></tr>
<?php endforeach; ?></table><?php if (count($up) > 6): ?><p><a href="<?= $base ?>?a=sessions&amp;f=upcoming">All <?= count($up) ?> upcoming sessions</a></p><?php endif; ?><?php endif; ?>

<h2>Latest public galleries</h2>
<?php if (!$latest): ?><p>None yet. <a href="<?= $base ?>?a=publish">Publish photos</a></p><?php else: ?><ul class="latest">
<?php foreach ($latest as $g): $d = DateTime::createFromFormat('!Y-m-d', (string)$g['date']); ?><li><a href="<?= $base ?>?a=pgallery&amp;slug=<?= h(rawurlencode((string)$g['slug'])) ?>"><img loading="lazy" src="/photos/<?= h(rawurlencode((string)$g['slug'])) ?>/<?= h(rawurlencode((string)$g['cover'])) ?>-t.jpg" alt=""><b><?= h((string)$g['title']) ?></b><small><?= $d ? h($d->format('M j, Y')) : h((string)$g['date']) ?></small></a></li>
<?php endforeach; ?></ul><p><a href="<?= $base ?>?a=pgalleries">All galleries</a></p><?php endif; ?>

<h2>Recent sales</h2>
<?php if (!$sales): ?><p>No orders yet.</p><?php else: ?><table class="stack"><tr><th>Date</th><th>Gallery</th><th>Photo</th><th>Status</th><th>Price</th></tr>
<?php foreach ($sales as $o): [$sl, $sc] = $stat[$o['status']] ?? [$o['status'], '']; ?><tr><td data-label="Date" class="nw"><?= h(date('M j, g:ia', (int)$o['created'])) ?></td><td data-label="Gallery"><a href="<?= $base ?>?a=gallery&amp;id=<?= (int)$o['gallery_id'] ?>&amp;tab=activity#orders"><?= h((string)$o['title']) ?></a><?= $o['kind'] === 'shop' ? ' <small>(public shop)</small>' : '' ?></td><td data-label="Photo"><?= h((string)$o['label']) ?></td><td data-label="Status"><span class="tag <?= $sc ?>"><?= h($sl) ?></span></td><td data-label="Price"><?= $o['price_cents'] === null ? '<small>not recorded</small>' : h(money((int)$o['price_cents'])) ?></td></tr>
<?php endforeach; ?></table><p><a href="<?= $base ?>?a=orders">All sales</a></p><?php endif; ?>

<h2>Visits and likes</h2>
<div class="tiles">
<div class="tile"><b><?= $n($d1['v']) ?></b><span>Visitors today</span></div>
<div class="tile"><b><?= $n($d7['v']) ?></b><span>Visitors, 7 days</span></div>
<div class="tile"><b><?= $n($d30['v']) ?></b><span>Visitors, 30 days</span></div>
<div class="tile"><b><?= $n($dl7) ?></b><span>Downloads, 7 days</span></div>
<a class="tile" href="<?= $base ?>?a=stats"><b><?= $n($likes) ?></b><span>Photo likes</span></a>
</div>
<?php afoot(); exit;
}
