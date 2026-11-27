<?php
// Admin > Portfolio > Galleries: every public gallery, from dist/client/galleries-index.json (written by build.py).
// Included by index.php after the admin login check. Read only; the editor (?a=pgallery) comes in T-25.
declare(strict_types=1);

function pg_json(string $name): array {
    $f = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/') . '/client/' . $name;
    $j = is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
    return is_array($j) ? $j : [];
}

if ($a === 'pgalleries') {
    ahead('Galleries');
    $all = pg_json('galleries-index.json');
    $ser = []; foreach (pg_json('series-index.json') as $s) if (is_array($s) && isset($s['slug'])) $ser[(string)$s['slug']] = (string)($s['short'] ?? $s['slug']);
    $needs = fn(array $g) => !$g['described'] || ($g['category'] === 'events' && $g['series'] === '');
    $chips = ['all' => ['All', fn($g) => true], 'portraits' => ['Portraits', fn($g) => $g['category'] === 'portraits'], 'events' => ['Events', fn($g) => $g['category'] === 'events'],
              'hidden' => ['Hidden', fn($g) => $g['hidden']], 'needs' => ['Needs info', $needs]];
    foreach ($ser as $slug => $short) $chips['s-' . $slug] = [$short, fn($g) => $g['series'] === $slug];
    $f = (string)($_GET['f'] ?? 'all'); if (!isset($chips[$f])) $f = 'all';
    foreach ($all as $k => $g) $all[$k] += ['slug' => '', 'title' => '', 'date' => '', 'year' => '', 'category' => '', 'series' => '', 'cover' => '', 'count' => 0, 'hidden' => false, 'described' => false];
    $rows = array_values(array_filter($all, $chips[$f][1]));
    $cat = ['portraits' => 'Portraits', 'events' => 'Events']; ?>
<div class="ph"><div><h1>Galleries</h1></div><div class="act"><a class="btn" href="?a=publish">Publish photos</a></div></div>
<p class="hint">Every gallery on the public site, newest first. Changes take a minute or two to appear here after saving.</p>
<?php if (!$all): ?><p class="msg wait" role="status">The gallery list is not there yet. It is made each time the site is published.</p><?php else: ?>
<nav class="chips" aria-label="Show galleries"><?php foreach ($chips as $k => [$lab, $fn]): $n = count(array_filter($all, $fn)); if ($n === 0 && str_starts_with($k, 's-')) continue; ?><a class="tab<?= $k === $f ? ' on" aria-current="page' : '' ?>" href="?a=pgalleries<?= $k === 'all' ? '' : '&f=' . h($k) ?>"><?= h($lab) ?> <?= $n ?></a><?php endforeach; ?></nav>
<div class="row narrow"><label class="grow">Find a gallery<input type="search" id="pgq" data-filter="#pglist" placeholder="Name, year, series..." autocomplete="off"></label></div>
<p class="hint" id="pgn" role="status" aria-live="polite"><?= count($rows) ?> galler<?= count($rows) === 1 ? 'y' : 'ies' ?></p>
<table class="stack pg" id="pglist"><tr><th>Cover</th><th>Gallery</th><th>Date</th><th>Section</th><th>Series</th><th>Photos</th><th>Status</th><th></th></tr>
<?php foreach ($rows as $g): $s = $ser[$g['series']] ?? ''; $d = DateTime::createFromFormat('!Y-m-d', (string)$g['date']); ?>
<tr data-q="<?= h(mb_strtolower($g['title'] . ' ' . $g['slug'] . ' ' . $g['year'] . ' ' . $s . ' ' . ($cat[$g['category']] ?? ''))) ?>">
<td><img class="cov" loading="lazy" src="/photos/<?= h($g['slug']) ?>/<?= h($g['cover']) ?>-t.jpg" alt=""></td>
<td><b><?= h($g['title']) ?></b><?= $needs($g) ? ' <span class="tag wait">Needs info</span>' : '' ?></td>
<td data-label="Date" class="nw"><?= $d ? h($d->format('M j, Y')) : h($g['date']) ?></td>
<td data-label="Section"><?= h($cat[$g['category']] ?? $g['category']) ?></td>
<td data-label="Series"><?= h($s) ?></td>
<td data-label="Photos"><?= (int)$g['count'] ?></td>
<td data-label="Status"><?= $g['hidden'] ? '<span class="tag wait">Hidden</span>' : '<span class="tag good">Shown</span>' ?></td>
<td><a href="/gallery/<?= h($g['slug']) ?>/" target="_blank" rel="noopener">View<span class="sr"> <?= h($g['title']) ?> (opens a new tab)</span></a></td>
</tr><?php endforeach; ?>
</table>
<p class="hint" id="pgnone" hidden>No galleries match. Try fewer letters, or choose All above.</p>
<?php if (!$rows): ?><p>No galleries here.</p><?php endif; ?>
<?php endif; afoot(); exit;
}
