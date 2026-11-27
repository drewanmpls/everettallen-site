<?php
// Admin > Portfolio > Galleries: every public gallery, from dist/client/galleries-index.json (written by build.py).
// Included by index.php after the admin login check. ?a=pgallery edits one gallery's details (T-25) through content.php.
declare(strict_types=1);
require_once __DIR__ . '/content.php';

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
<td><a href="?a=pgallery&slug=<?= h($g['slug']) ?>"><b><?= h($g['title']) ?></b></a><?= $needs($g) ? ' <span class="tag wait">Needs info</span>' : '' ?></td>
<td data-label="Date" class="nw"><?= $d ? h($d->format('M j, Y')) : h($g['date']) ?></td>
<td data-label="Section"><?= h($cat[$g['category']] ?? $g['category']) ?></td>
<td data-label="Series"><?= h($s) ?></td>
<td data-label="Photos"><?= (int)$g['count'] ?></td>
<td data-label="Status"><?= $g['hidden'] ? '<span class="tag wait">Hidden</span>' : '<span class="tag good">Shown</span>' ?></td>
<td><div class="acts"><a class="btn sm alt" href="?a=pgallery&slug=<?= h($g['slug']) ?>">Edit<span class="sr"> <?= h($g['title']) ?></span></a><?php if (!$g['hidden']): ?> <a class="btn sm alt" href="/gallery/<?= h($g['slug']) ?>/" target="_blank" rel="noopener">View<span class="sr"> <?= h($g['title']) ?> (opens a new tab)</span></a><?php endif; ?></div></td>
</tr><?php endforeach; ?>
</table>
<p class="hint" id="pgnone" hidden>No galleries match. Try fewer letters, or choose All above.</p>
<?php if (!$rows): ?><p>No galleries here.</p><?php endif; ?>
<?php endif; afoot(); exit;
}

// ---------- one gallery: details, cover, hide (T-25) ----------
function pg_series(): array { $o = []; foreach (pg_json('series-index.json') as $x) if (is_array($x) && isset($x['slug'])) $o[(string)$x['slug']] = (string)($x['name'] ?? $x['slug']); return $o; }

// The form's values applied on top of the gallery file as it is on GitHub. images and unknown fields are kept as they are.
function pg_apply(array $g, array $in): array {
    $g['title'] = trim(preg_replace('/\s+/', ' ', (string)($in['title'] ?? '')));
    $g['date'] = trim((string)($in['date'] ?? ''));
    $y = trim((string)($in['year'] ?? '')); $g['year'] = $y !== '' ? $y : substr($g['date'], 0, 4);
    $g['category'] = (string)($in['category'] ?? '');
    $g['description'] = trim(str_replace("\r\n", "\n", (string)($in['description'] ?? '')));
    $sr = (string)($in['series'] ?? ''); if ($sr !== '') $g['series'] = $sr; else unset($g['series']);
    $tags = []; foreach (preg_split('/\R/', (string)($in['tags'] ?? '')) as $t) { $t = trim($t); if ($t !== '' && !in_array($t, $tags, true)) $tags[] = $t; }
    if ($tags) $g['tags'] = $tags; else unset($g['tags']);
    $cv = (string)($in['cover'] ?? ''); if ($cv !== '') $g['cover'] = $cv;
    if (!empty($in['hidden'])) $g['hidden'] = true; else unset($g['hidden']);
    return $g;
}

if ($a === 'pgsave' || $a === 'pgallery') {
    $slug = (string)($_POST['slug'] ?? $_GET['slug'] ?? '');
    if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug) || strlen($slug) > 120) go('/client/admin/?a=pgalleries');
    $path = 'content/galleries/' . $slug . '.json';
    $problems = []; $form = null;
    if ($a === 'pgsave') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok()) { http_response_code(400); echo 'Session expired. Go back, reload the page and try again.'; exit; }
        [$cur, $sha, $err] = content_get($path);
        if ($cur === null) $problems[] = 'Could not read the gallery from GitHub: ' . $err;
        else {
            $new = pg_apply($cur, $_POST);
            $problems = validate_gallery($new, array_keys(pg_series()));
            if (!$problems) {
                [$ok, $err] = content_put($path, $new, $sha, 'Gallery details: ' . $slug);
                if ($ok) go('/client/admin/?a=pgallery&slug=' . $slug . '&saved=1');
                $problems[] = 'Could not save: ' . $err;
            }
        }
        $form = $_POST;   // show what was typed, with the problems above it
    }
    [$g, , $err] = content_get($path);
    ahead($g['title'] ?? 'Gallery'); $tok = csrf();
    if ($g === null) { ?>
<div class="ph"><div><p class="back"><a href="?a=pgalleries">&larr; All galleries</a></p><h1>Gallery</h1></div></div>
<p class="msg bad" role="alert">Could not open this gallery: <?= h($err) ?></p>
<?php afoot(); exit; }
    $v = $form !== null ? pg_apply($g, $form) : $g;
    $ser = pg_series(); $hidden = !empty($g['hidden']);
    $ids = array_map(fn($im) => (string)($im['id'] ?? ''), is_array($g['images'] ?? null) ? $g['images'] : []); ?>
<div class="ph"><div><p class="back"><a href="?a=pgalleries">&larr; All galleries</a></p><h1><?= h((string)$g['title']) ?></h1><?= $hidden ? '<span class="tag wait">Hidden</span>' : '<span class="tag good">Shown</span>' ?></div>
<?php if (!$hidden): ?><div class="act"><a class="btn alt" href="/gallery/<?= h($slug) ?>/" target="_blank" rel="noopener">View on site</a></div><?php endif; ?></div>
<?php if (!empty($_GET['saved'])): ?><p class="msg good" role="status">Saved. The site updates in a minute or two.</p><?php endif; ?>
<?php if ($problems): ?><div class="msg bad" role="alert"><p>Not saved yet. Please fix:</p><ul><?php foreach ($problems as $pr): ?><li><?= h($pr) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" class="form wide"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="pgsave"><input type="hidden" name="slug" value="<?= h($slug) ?>">
<h2>Details</h2>
<label>Title<input name="title" value="<?= h((string)($v['title'] ?? '')) ?>" required maxlength="200"></label>
<div class="cols"><label>Date<input name="date" type="date" value="<?= h((string)($v['date'] ?? '')) ?>" required data-year="#pgyear"></label>
<label>Year (groups the Events page)<input name="year" id="pgyear" value="<?= h((string)($v['year'] ?? '')) ?>" inputmode="numeric" maxlength="4" pattern="\d{4}"></label>
<label>Section<select name="category"><?php foreach (['portraits' => 'Portraits', 'events' => 'Events'] as $k => $lab): ?><option value="<?= $k ?>" <?= ($v['category'] ?? '') === $k ? 'selected' : '' ?>><?= $lab ?></option><?php endforeach; ?></select></label>
<label>Event series<select name="series"><option value="">None</option><?php foreach ($ser as $k => $lab): ?><option value="<?= h($k) ?>" <?= ($v['series'] ?? '') === $k ? 'selected' : '' ?>><?= h($lab) ?></option><?php endforeach; ?></select></label></div>
<label>Tags (one per line, up to 12; they become search chips and tag pages)<textarea name="tags" rows="4"><?= h(implode("\n", is_array($v['tags'] ?? null) ? $v['tags'] : [])) ?></textarea></label>
<label>Description<textarea name="description" rows="6" maxlength="2000"><?= h((string)($v['description'] ?? '')) ?></textarea></label>
<p class="hint">Plain text. A blank line starts a new paragraph.</p>
<h2 id="cover">Cover photo</h2>
<p class="hint">Shown on the Events or Portraits tile and in link previews. <?= count($ids) ?> photos.</p>
<fieldset class="pick"><legend class="sr">Cover photo</legend><?php foreach ($ids as $id): ?><label class="pk"><input type="radio" name="cover" value="<?= h($id) ?>" <?= (string)($v['cover'] ?? '') === $id ? 'checked' : '' ?>><img loading="lazy" src="/photos/<?= h($slug) ?>/<?= h($id) ?>-t.jpg" alt="Photo <?= h($id) ?>"><span><?= h($id) ?></span></label><?php endforeach; ?></fieldset>
<h2>Show or hide</h2>
<label class="check"><input type="checkbox" name="hidden" value="1" <?= !empty($v['hidden']) ? 'checked' : '' ?>> Hide this gallery from the site</label>
<p class="hint">Hiding removes the gallery's pages from the site (Events, Portraits, Search and its own address) after the next update. The photo files stay on the server. To take a photo down for good, use Remove on the photo instead.</p>
<div class="savebar"><button class="btn">Save gallery</button></div>
</form>
<?php afoot(); exit;
}
