<?php
// Admin > Portfolio > Event series (T-27): edit content/series.json through content.php. Later content editors (T-28, T-29) join here.
// Included by index.php after the admin login check; handles its own CSRF.
declare(strict_types=1);
require_once __DIR__ . '/content.php';

function ce_slugify(string $t): string { return trim((string)preg_replace('/-+/', '-', preg_replace('/[^a-z0-9]+/', '-', strtolower($t))), '-'); }
// Plain-sentence problems for one series (Section 3.3). $n is its position, for the message.
function ce_series_problems(array $s, int $n): array {
    $p = []; $lab = 'Series ' . $n . ($s['name'] !== '' ? ' (' . $s['name'] . ')' : '');
    if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $s['slug']) || strlen($s['slug']) > 40) $p[] = $lab . ': the web name must be 1 to 40 lowercase letters, numbers or single dashes.';
    if ($s['name'] === '' || mb_strlen($s['name']) > 120) $p[] = $lab . ': the name needs 1 to 120 characters.';
    if ($s['short'] === '' || mb_strlen($s['short']) > 40) $p[] = $lab . ': the short label needs 1 to 40 characters.';
    if ($s['desc'] === '' || mb_strlen($s['desc']) > 300) $p[] = $lab . ': the description needs 1 to 300 characters.';
    foreach (['name', 'short', 'desc'] as $k) if ($s[$k] !== strip_tags($s[$k])) { $p[] = $lab . ': use plain text, no HTML.'; break; }
    return $p;
}
function ce_galleries_using(string $slug): array {   // titles of galleries (shown or hidden) in that series
    $f = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/') . '/client/galleries-index.json';
    $j = is_file($f) ? json_decode((string)file_get_contents($f), true) : null; $o = [];
    if (is_array($j)) foreach ($j as $g) if (is_array($g) && ($g['series'] ?? '') === $slug) $o[] = (string)($g['title'] ?? $g['slug'] ?? '');
    return $o;
}
$ceClean = fn($v) => trim(preg_replace('/\s+/u', ' ', is_string($v) ? $v : ''));

if ($a === 'seriessave') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok()) { http_response_code(400); echo 'Session expired. Go back, reload the page and try again.'; exit; }
    $back = '/client/admin/?a=series';
    [$doc, $sha, $err] = content_get('content/series.json');
    if ($doc === null || !is_array($doc['series'] ?? null)) { $_SESSION['ce_err'] = ['Could not read the series list from GitHub: ' . ($err ?: 'it has no series list.')]; go($back); }
    $old = []; foreach ($doc['series'] as $s) $old[(string)$s['slug']] = $s;
    $problems = []; $list = []; $msg = 'Event series';
    if (($_POST['do'] ?? '') === 'add') {
        $s = ['slug' => ce_slugify((string)($_POST['name'] ?? '')), 'name' => $ceClean($_POST['name'] ?? ''), 'short' => $ceClean($_POST['short'] ?? ''), 'desc' => $ceClean($_POST['desc'] ?? '')];
        $base = substr($s['slug'], 0, 37); $i = 2; while (isset($old[$s['slug']])) $s['slug'] = rtrim($base, '-') . '-' . $i++;
        $problems = ce_series_problems($s, count($old) + 1);
        $list = array_values($old); $list[] = $s; $msg = 'Event series: add ' . $s['slug'];
        if ($problems) $_SESSION['ce_add'] = $s;
    } else {
        $rows = is_array($_POST['s'] ?? null) ? array_values($_POST['s']) : [];
        $seen = [];
        foreach ($rows as $n => $r) {
            $slug = is_array($r) ? (string)($r['slug'] ?? '') : '';
            if (!isset($old[$slug]) || isset($seen[$slug])) { $problems = ['The series list changed since this page was opened. Reload the page and try again.']; break; }
            $seen[$slug] = true;
            if (!empty($r['delete'])) {
                $used = ce_galleries_using($slug);
                if ($used) $problems[] = '"' . $old[$slug]['name'] . '" cannot be deleted while ' . count($used) . ' galler' . (count($used) === 1 ? 'y uses' : 'ies use') . ' it: ' . implode(', ', array_slice($used, 0, 8)) . (count($used) > 8 ? ', and ' . (count($used) - 8) . ' more' : '') . '. Change their series first (Galleries > Edit).';
                continue;
            }
            $s = $old[$slug]; foreach (['name', 'short', 'desc'] as $k) $s[$k] = $ceClean($r[$k] ?? '');   // the slug never changes
            $problems = array_merge($problems, ce_series_problems($s, $n + 1));
            $list[] = $s;
        }
        if (!$problems && count($seen) !== count($old)) $problems = ['The series list changed since this page was opened. Reload the page and try again.'];
    }
    if (!$problems) {
        $doc['series'] = array_values($list);
        [$ok, $err] = content_put('content/series.json', $doc, (string)$sha, $msg);
        if ($ok) go($back . '&saved=1');
        $problems[] = 'Could not save: ' . $err;
    }
    $_SESSION['ce_err'] = $problems;
    if (($_POST['do'] ?? '') !== 'add') $_SESSION['ce_rows'] = $_POST['s'] ?? [];
    go($back . (($_POST['do'] ?? '') === 'add' ? '#add' : ''));
}

if ($a === 'series') {
    ahead('Event series'); $tok = csrf();
    [$doc, , $err] = content_get('content/series.json');
    $errs = $_SESSION['ce_err'] ?? []; $typed = $_SESSION['ce_rows'] ?? null; $add = $_SESSION['ce_add'] ?? null;
    unset($_SESSION['ce_err'], $_SESSION['ce_rows'], $_SESSION['ce_add']); ?>
<div class="ph"><div><h1>Event series</h1></div><div class="act"><a class="btn" href="#add">Add a series</a></div></div>
<p class="hint">Recurring events, like TCLFW or Pride. Each has its own page on the site and a chip on the Events page. Changes take a minute or two to appear on the site.</p>
<?php if (!empty($_GET['saved'])): ?><p class="msg good" role="status">Saved. The site updates in a minute or two.</p><?php endif; ?>
<?php if ($errs): ?><div class="msg bad" role="alert"><p>Not saved. Please fix:</p><ul><?php foreach ($errs as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if ($doc === null || !is_array($doc['series'] ?? null)): ?><p class="msg bad" role="alert">Could not open the series list: <?= h($err ?: 'it has no series list.') ?></p><?php else:
    $rows = $doc['series']; $bySlug = []; foreach ($rows as $s) $bySlug[$s['slug']] = $s;
    if (is_array($typed)) { $t = []; foreach ($typed as $r) if (is_array($r) && isset($bySlug[$r['slug'] ?? ''])) $t[] = array_merge($bySlug[$r['slug']], array_intersect_key($r, ['name' => 1, 'short' => 1, 'desc' => 1, 'delete' => 1])); if (count($t) === count($rows)) $rows = $t; } ?>
<form method="post" class="form wide"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="seriessave"><input type="hidden" name="do" value="save">
<p class="sr" id="sermoved" role="status" aria-live="polite"></p>
<ol class="serlist" id="serlist">
<?php foreach ($rows as $i => $s): $n = count(ce_galleries_using((string)$s['slug'])); ?>
<li class="panel"><fieldset><legend><?= h((string)$s['name']) ?> <span class="hint">/series/<?= h((string)$s['slug']) ?>/ &middot; <?= $n ?> galler<?= $n === 1 ? 'y' : 'ies' ?></span></legend>
<input type="hidden" name="s[<?= $i ?>][slug]" value="<?= h((string)$s['slug']) ?>">
<div class="cols"><label>Name<input name="s[<?= $i ?>][name]" value="<?= h((string)$s['name']) ?>" maxlength="120" required></label>
<label>Short label (on chips)<input name="s[<?= $i ?>][short]" value="<?= h((string)$s['short']) ?>" maxlength="40" required></label></div>
<label>Description<textarea name="s[<?= $i ?>][desc]" rows="2" maxlength="300" required><?= h((string)$s['desc']) ?></textarea></label>
<div class="acts"><button type="button" class="btn sm alt" data-sermove="-1">Move up</button><button type="button" class="btn sm alt" data-sermove="1">Move down</button>
<?php if ($n === 0): ?><label class="check"><input type="checkbox" name="s[<?= $i ?>][delete]" value="1" <?= !empty($s['delete']) ? 'checked' : '' ?>> Delete this series</label><?php else: ?><span class="hint">Used by <?= $n ?> galler<?= $n === 1 ? 'y' : 'ies' ?>, so it cannot be deleted.</span><?php endif; ?></div>
</fieldset></li>
<?php endforeach; ?>
</ol>
<div class="savebar"><button class="btn">Save series</button></div>
</form>
<h2 id="add">Add a series</h2>
<form method="post" class="form wide panel"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="seriessave"><input type="hidden" name="do" value="add">
<div class="cols"><label>Name<input name="name" id="sername" value="<?= h((string)($add['name'] ?? '')) ?>" maxlength="120" required data-slugto="#serslug"></label>
<label>Short label (on chips)<input name="short" value="<?= h((string)($add['short'] ?? '')) ?>" maxlength="40" required></label></div>
<label>Description<textarea name="desc" rows="2" maxlength="300" required><?= h((string)($add['desc'] ?? '')) ?></textarea></label>
<p class="hint">Its page will be <b>/series/<span id="serslug"><?= h((string)($add['slug'] ?? '')) ?></span>/</b> (made from the name; it cannot be changed later).</p>
<button class="btn">Add series</button></form>
<?php endif; afoot(); exit;
}
