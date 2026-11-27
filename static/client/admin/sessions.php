<?php
// Admin > Studio > Sessions (T-41): every client gallery (galleries where kind is not 'shop') as a session card,
// with filters, search and the New session form. Order galleries from the public shop are listed below, under #orders.
// Included by index.php after the admin login check.
declare(strict_types=1);

if ($a === 'sessions') {
    ahead('Sessions'); $tok = csrf();
    $rows = db()->query("SELECT g.*, c.name AS cname, c.shoot_date AS cdate, c.shoot_location AS cloc,
        (SELECT COUNT(*) FROM images i WHERE i.gallery_id = g.id) AS n,
        (SELECT i.id FROM images i WHERE i.gallery_id = g.id ORDER BY i.sort, i.id LIMIT 1) AS first_img,
        (SELECT COUNT(*) FROM favorites f JOIN images i ON i.id = f.image_id WHERE i.gallery_id = g.id) AS fav,
        (SELECT COUNT(*) FROM claims cl JOIN images i ON i.id = cl.image_id WHERE i.gallery_id = g.id) AS picks,
        (SELECT COUNT(*) FROM downloads d JOIN images i ON i.id = d.image_id WHERE i.gallery_id = g.id) AS dls,
        (SELECT COUNT(*) FROM orders o WHERE o.gallery_id = g.id AND o.status = 'requested') AS req,
        (SELECT COUNT(*) FROM comments m JOIN images i ON i.id = m.image_id WHERE i.gallery_id = g.id AND m.from_owner = 0
            AND NOT EXISTS (SELECT 1 FROM comments r WHERE r.image_id = m.image_id AND r.from_owner = 1 AND r.id > m.id)) AS needs
        FROM galleries g LEFT JOIN clients c ON c.id = g.client_id WHERE g.kind <> 'shop'")->fetchAll();
    $today = date('Y-m-d');
    foreach ($rows as &$r) {
        $r['when'] = ses_date((string)$r['shoot_date'], $r['cdate']);
        $r['where'] = trim((string)$r['shoot_location']) !== '' ? (string)$r['shoot_location'] : (string)($r['cloc'] ?? '');
        $r['status'] = !$r['active'] ? 'off' : ($r['dls'] > 0 ? 'delivered' : (($r['picks'] > 0 || $r['fav'] > 0) ? 'picks' : 'proofing'));
        $r['who'] = trim((string)$r['client_name']) !== '' ? (string)$r['client_name'] : (string)($r['cname'] ?? '');
    }
    unset($r);
    usort($rows, fn($x, $y) => [$y['when'] ?: date('Y-m-d', (int)$y['created']), $y['id']] <=> [$x['when'] ?: date('Y-m-d', (int)$x['created']), $x['id']]);   // newest shoot first
    $chips = ['all' => ['All', fn($r) => true], 'upcoming' => ['Upcoming', fn($r) => $r['when'] !== '' && $r['when'] >= $today],
              'proofing' => ['Proofing', fn($r) => $r['status'] === 'proofing'], 'reply' => ['Needs reply', fn($r) => $r['needs'] > 0], 'off' => ['Off', fn($r) => $r['status'] === 'off']];
    $f = (string)($_GET['f'] ?? 'all'); if (!isset($chips[$f])) $f = 'all';
    $q = trim((string)($_GET['q'] ?? ''));
    $show = array_values(array_filter($rows, function ($r) use ($chips, $f, $q) {
        if (!$chips[$f][1]($r)) return false;
        if ($q === '') return true;
        return mb_stripos($r['title'] . ' ' . $r['who'] . ' ' . $r['client_email'], $q) !== false;
    }));
    if ($chips[$f][0] === 'Upcoming') usort($show, fn($x, $y) => $x['when'] <=> $y['when']);   // soonest first
    $tags = ['off' => ['Off', 'wait'], 'proofing' => ['Proofing', ''], 'picks' => ['Picks made', 'good'], 'delivered' => ['Delivered', 'good']];
    $fmt = fn(string $d) => $d === '' ? '' : date('M j, Y', (int)strtotime($d)); ?>
<div class="ph"><div><h1>Sessions</h1></div><div class="act"><a class="btn" href="#new">New session</a></div></div>
<p class="hint">One card per shoot: its client gallery, picks, comments and requests. Order galleries from the public site are listed under <a href="#orders">Photo orders</a>.</p>
<nav class="chips" aria-label="Show sessions"><?php foreach ($chips as $k => [$lab, $fn]): ?><a class="tab<?= $k === $f ? ' on" aria-current="page' : '' ?>" href="?a=sessions<?= $k === 'all' ? '' : '&f=' . $k ?><?= $q !== '' ? '&q=' . rawurlencode($q) : '' ?>"><?= h($lab) ?> <?= count(array_filter($rows, $fn)) ?></a><?php endforeach; ?></nav>
<form method="get" class="row narrow" role="search"><input type="hidden" name="a" value="sessions"><?php if ($f !== 'all'): ?><input type="hidden" name="f" value="<?= h($f) ?>"><?php endif; ?>
<input name="q" value="<?= h($q) ?>" placeholder="Search title or client" aria-label="Search sessions"><button class="btn sm">Search</button><?php if ($q !== ''): ?> <a href="?a=sessions<?= $f !== 'all' ? '&f=' . $f : '' ?>">Clear</a><?php endif; ?></form>
<?php if (!$show): ?><p><?= $rows ? 'No sessions match.' : 'No sessions yet. Create the first one below.' ?></p><?php else: ?>
<ul class="sescards">
<?php foreach ($show as $r): [$tl, $tc] = $tags[$r['status']]; ?>
<li><a class="sesc" href="?a=gallery&amp;id=<?= (int)$r['id'] ?>">
<?php if ($r['first_img']): ?><img loading="lazy" src="?a=thumb&amp;id=<?= (int)$r['first_img'] ?>" alt=""><?php else: ?><span class="noimg">No photos yet</span><?php endif; ?>
<span class="sb"><b><?= h((string)$r['title']) ?></b> <span class="tag <?= $tc ?>"><?= $tl ?></span>
<small><?= h($r['who'] !== '' ? $r['who'] : 'No client set') ?><?= $r['when'] !== '' ? ' &middot; ' . h($fmt($r['when'])) : '' ?><?= $r['where'] !== '' ? ' &middot; ' . h($r['where']) : '' ?></small>
<small class="counts"><?= (int)$r['n'] ?> photos &middot; <?= (int)$r['fav'] ?> favorites<?= $r['req'] ? ' &middot; <span class="tag wait">' . (int)$r['req'] . ' open request' . ($r['req'] > 1 ? 's' : '') . '</span>' : '' ?><?= $r['needs'] ? ' &middot; <span class="tag wait">' . (int)$r['needs'] . ' need' . ($r['needs'] > 1 ? '' : 's') . ' reply</span>' : '' ?></small></span></a></li>
<?php endforeach; ?></ul>
<?php endif; ?>
<?php $shop = db()->query("SELECT g.*, (SELECT COUNT(*) FROM images i WHERE i.gallery_id=g.id) AS n, (SELECT COUNT(*) FROM orders o WHERE o.gallery_id=g.id AND o.status='paid') AS paid FROM galleries g WHERE g.kind = 'shop' ORDER BY g.id DESC LIMIT 100")->fetchAll(); ?>
<h2 id="orders">Photo orders from the public galleries</h2>
<?php if (!$shop): ?><p>No orders yet.</p><?php else: ?>
<table class="stack"><tr><th>Buyer</th><th>Photos</th><th>Paid</th><th>Placed</th><th></th></tr>
<?php foreach ($shop as $r): ?><tr><td><?= h($r['client_email']) ?></td><td data-label="Photos"><?= (int)$r['n'] ?></td><td data-label="Paid"><?= $r['paid'] ? '<span class="tag good">paid</span>' : '<span class="tag wait">not yet</span>' ?></td><td data-label="Placed"><?= date('M j, Y g:ia', (int)$r['created']) ?></td><td><a href="?a=gallery&id=<?= $r['id'] ?>">Manage</a></td></tr><?php endforeach; ?></table>
<?php endif; ?>
<h2 id="new">New session</h2>
<form method="post" class="cols"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="create">
<label>Title<input name="title" required placeholder="Jordan and Sam, spring session"></label>
<label>Client name<input name="client_name"></label>
<label>Client email<input name="client_email" type="email"></label>
<label>Shoot date<input name="shoot_date" type="date"></label>
<label>Shoot location<input name="shoot_location" maxlength="120" placeholder="Studio, Minneapolis"></label>
<label>Included photos (free picks)<input name="included" type="number" min="0" value="0"></label>
<label>Extra photo, web size ($, 0 = Free)<input name="price_web" value="0"></label>
<label>Extra photo, full resolution ($, 0 = Free)<input name="price" value="0"></label>
<label>Password (optional)<input name="password" autocomplete="off"></label>
<label class="full">Note shown to client<textarea name="note" rows="2"></textarea></label>
<div class="full"><button class="btn">Create session</button></div></form>
<?php afoot(); exit;
}
