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

// ---------- Pages and site settings (T-28) ----------
const CE_PAGES = ['about' => 'About', 'licensing' => 'Press and licensing', 'privacy' => 'Privacy', 'removal' => 'Photo removal'];
const CE_PROMISE = ['privacy', 'removal'];   // wording that is a promise to visitors
function ce_portraits(): array {   // slug => title of portrait galleries (shown or hidden), from galleries-index.json
    $f = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/') . '/client/galleries-index.json';
    $j = is_file($f) ? json_decode((string)file_get_contents($f), true) : null; $o = [];
    if (is_array($j)) foreach ($j as $g) if (is_array($g) && ($g['category'] ?? '') === 'portraits') $o[(string)$g['slug']] = (string)$g['title'] . (!empty($g['hidden']) ? ' (hidden)' : '');
    asort($o, SORT_NATURAL | SORT_FLAG_CASE);
    return $o;
}
$ceText = fn($v) => trim(str_replace("\r\n", "\n", is_string($v) ? $v : ''));

if ($a === 'pagesave' || $a === 'sitesave') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok()) { http_response_code(400); echo 'Session expired. Go back, reload the page and try again.'; exit; }
    $problems = [];
    if ($a === 'pagesave') {
        $p = (string)($_POST['page'] ?? ''); if (!isset(CE_PAGES[$p])) go('/client/admin/?a=pages');
        $back = '/client/admin/?a=pages&p=' . $p; $path = 'content/pages/' . $p . '.json';
        [$doc, $sha, $err] = content_get($path);
        if ($doc === null) $problems[] = 'Could not read the page from GitHub: ' . $err;
        else {
            $doc['title'] = $ceClean($_POST['title'] ?? ''); $doc['lede'] = $ceClean($_POST['lede'] ?? ''); $doc['body'] = $ceText($_POST['body'] ?? '');
            $d = $ceClean($_POST['desc'] ?? ''); if ($d !== '') $doc['desc'] = $d; else unset($doc['desc']);
            if ($doc['title'] === '' || mb_strlen($doc['title']) > 80) $problems[] = 'The title needs 1 to 80 characters.';
            if ($doc['lede'] === '' || mb_strlen($doc['lede']) > 200) $problems[] = 'The lede needs 1 to 200 characters.';
            if (mb_strlen($d) > 160) $problems[] = 'The search description can be at most 160 characters.';
            if ($doc['body'] === '' || mb_strlen($doc['body']) > 20000) $problems[] = 'The body needs 1 to 20,000 characters.';
            foreach (['title', 'lede', 'desc', 'body'] as $k) if (isset($doc[$k]) && $doc[$k] !== strip_tags($doc[$k])) { $problems[] = 'Use plain text and the formatting key, no HTML.'; break; }
            if (!$problems) { [$ok, $err] = content_put($path, $doc, (string)$sha, 'Page: ' . $p); if ($ok) go($back . '&saved=1'); $problems[] = 'Could not save: ' . $err; }
        }
        $_SESSION['ce_form'] = ['title' => $_POST['title'] ?? '', 'lede' => $_POST['lede'] ?? '', 'desc' => $_POST['desc'] ?? '', 'body' => $_POST['body'] ?? ''];
    } else {
        $back = '/client/admin/?a=pages#site'; $path = 'content/site.json';
        [$doc, $sha, $err] = content_get($path);
        if ($doc === null) $problems[] = 'Could not read the site settings from GitHub: ' . $err;
        else {
            foreach (['tagline' => 200, 'home_descriptor' => 120, 'contact_intro' => 600, 'usage_note' => 400] as $k => $max) {
                $v = $ceClean($_POST[$k] ?? '');
                if (mb_strlen($v) > $max) $problems[] = ucfirst(str_replace('_', ' ', $k)) . ' can be at most ' . $max . ' characters.';
                if ($v !== strip_tags($v)) $problems[] = ucfirst(str_replace('_', ' ', $k)) . ': use plain text, no HTML.';
                if ($v === '' && $k === 'home_descriptor') unset($doc[$k]); else $doc[$k] = $v;
            }
            if (($doc['tagline'] ?? '') === '') $problems[] = 'The tagline cannot be empty.';
            foreach (['facebook' => 'Facebook', 'instagram' => 'Instagram'] as $k => $lab) {
                $v = trim((string)($_POST[$k] ?? ''));
                if ($v !== '' && (!preg_match('#^https://[^\s"<>]+$#', $v) || strlen($v) > 300)) $problems[] = 'The ' . $lab . ' address must start with https://';
                $doc[$k] = $v;
            }
            $ok_slugs = ce_portraits(); $fp = [];
            foreach ((array)($_POST['featured'] ?? []) as $s) { $s = (string)$s; if (isset($ok_slugs[$s]) && !in_array($s, $fp, true)) $fp[] = $s; }
            if (count($fp) > 24) $problems[] = 'Choose at most 24 featured portraits.';
            $doc['featured_portraits'] = $fp;   // title, base_url, hero_count and any other fields are kept as they are
            if (!$problems) { [$ok, $err] = content_put($path, $doc, (string)$sha, 'Site settings'); if ($ok) go('/client/admin/?a=pages&sitesaved=1#site'); $problems[] = 'Could not save: ' . $err; }
        }
        $_SESSION['ce_site'] = $_POST;
    }
    $_SESSION['ce_err'] = $problems;
    go($back);
}

if ($a === 'pages') {
    $p = (string)($_GET['p'] ?? '');
    $tok = csrf(); $errs = $_SESSION['ce_err'] ?? []; unset($_SESSION['ce_err']);
    if (isset(CE_PAGES[$p])) {   // one page
        ahead(CE_PAGES[$p]);
        [$doc, , $err] = content_get('content/pages/' . $p . '.json');
        $v = $_SESSION['ce_form'] ?? $doc; unset($_SESSION['ce_form']); ?>
<div class="ph"><div><p class="back"><a href="?a=pages">&larr; Pages and site settings</a></p><h1><?= h(CE_PAGES[$p]) ?></h1></div><div class="act"><a class="btn alt" href="/<?= h($p) ?>/" target="_blank" rel="noopener">View on site</a></div></div>
<?php if (!empty($_GET['saved'])): ?><p class="msg good" role="status">Saved. The site updates in a minute or two.</p><?php endif; ?>
<?php if ($errs): ?><div class="msg bad" role="alert"><p>Not saved yet. Please fix:</p><ul><?php foreach ($errs as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if (in_array($p, CE_PROMISE, true)): ?><p class="msg wait" role="note">This wording is a promise to visitors. Have changes to it reviewed before saving.</p><?php endif; ?>
<?php if (!is_array($v)): ?><p class="msg bad" role="alert">Could not open this page: <?= h($err) ?></p><?php else: ?>
<form method="post" class="form wide"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="pagesave"><input type="hidden" name="page" value="<?= h($p) ?>">
<label>Title<input name="title" value="<?= h((string)($v['title'] ?? '')) ?>" maxlength="80" required></label>
<label>Lede (the line under the title)<input name="lede" value="<?= h((string)($v['lede'] ?? '')) ?>" maxlength="200" required></label>
<label>Search description (optional, shown by search engines)<input name="desc" value="<?= h((string)($v['desc'] ?? '')) ?>" maxlength="160"></label>
<label>Body<textarea name="body" rows="18" required><?= h((string)($v['body'] ?? '')) ?></textarea></label>
<div class="panel hint"><b>Formatting:</b> a blank line starts a new paragraph. <code>**bold**</code>, <code>*italic*</code>, <code>[link text](/contact/)</code>. Lines that start with <code>1. </code> become a numbered list.</div>
<div class="savebar"><button class="btn">Save page</button></div>
</form>
<?php endif; afoot(); exit;
    }
    ahead('Pages'); [$site, , $err] = content_get('content/site.json');
    $sv = $_SESSION['ce_site'] ?? null; unset($_SESSION['ce_site']);
    if (is_array($site) && is_array($sv)) { foreach (['tagline', 'home_descriptor', 'contact_intro', 'usage_note', 'facebook', 'instagram'] as $k) $site[$k] = (string)($sv[$k] ?? ''); $site['featured_portraits'] = array_values(array_filter((array)($sv['featured'] ?? []), 'is_string')); }
    $por = ce_portraits(); ?>
<div class="ph"><div><h1>Pages and site settings</h1></div></div>
<?php if (!empty($_GET['sitesaved'])): ?><p class="msg good" role="status">Site settings saved. The site updates in a minute or two.</p><?php endif; ?>
<h2>Pages</h2>
<p class="hint">These pages are linked from the site footer. Pages cannot be added or removed here.</p>
<ul class="go pagelist"><?php foreach (CE_PAGES as $k => $lab): ?><li><a href="?a=pages&amp;p=<?= $k ?>"><?= h($lab) ?><small>/<?= $k ?>/<?= in_array($k, CE_PROMISE, true) ? ' &middot; a promise to visitors' : '' ?></small></a></li><?php endforeach; ?></ul>
<h2 id="site">Site settings</h2>
<?php if ($errs): ?><div class="msg bad" role="alert"><p>Not saved yet. Please fix:</p><ul><?php foreach ($errs as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if (!is_array($site)): ?><p class="msg bad" role="alert">Could not open the site settings: <?= h($err) ?></p><?php else: ?>
<form method="post" class="form wide"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="sitesave">
<div class="cols"><label>Site name (fixed)<input value="<?= h((string)($site['title'] ?? '')) ?>" readonly></label><label>Web address (fixed)<input value="<?= h((string)($site['base_url'] ?? '')) ?>" readonly></label></div>
<label>Tagline (home page; split into lines at sentence ends)<input name="tagline" value="<?= h((string)($site['tagline'] ?? '')) ?>" maxlength="200" required></label>
<label>Home descriptor (optional, up to 120 characters)<input name="home_descriptor" value="<?= h((string)($site['home_descriptor'] ?? '')) ?>" maxlength="120"></label>
<label>Contact page intro<textarea name="contact_intro" rows="3" maxlength="600"><?= h((string)($site['contact_intro'] ?? '')) ?></textarea></label>
<label>Usage note (shown on galleries)<textarea name="usage_note" rows="2" maxlength="400"><?= h((string)($site['usage_note'] ?? '')) ?></textarea></label>
<div class="cols"><label>Facebook address<input name="facebook" type="url" value="<?= h((string)($site['facebook'] ?? '')) ?>" placeholder="https://"></label>
<label>Instagram address<input name="instagram" type="url" value="<?= h((string)($site['instagram'] ?? '')) ?>" placeholder="https://"></label></div>
<h3>Featured portraits (home page strip, in this order)</h3>
<p class="sr" id="fpmoved" role="status" aria-live="polite"></p>
<ol class="fplist" id="fplist"><?php foreach ((array)($site['featured_portraits'] ?? []) as $s): ?><li><input type="hidden" name="featured[]" value="<?= h((string)$s) ?>"><span><?= h($por[$s] ?? ($s . ' (not a portrait gallery; it will be dropped)')) ?></span>
<span class="acts"><button type="button" class="btn sm alt" data-fp="-1">Up</button><button type="button" class="btn sm alt" data-fp="1">Down</button><button type="button" class="btn sm alt" data-fp="0">Remove</button></span></li><?php endforeach; ?></ol>
<div class="row"><label>Add a portrait gallery<select id="fpadd"><option value="">Choose</option><?php foreach ($por as $s => $t): ?><option value="<?= h($s) ?>"><?= h($t) ?></option><?php endforeach; ?></select></label><button type="button" class="btn sm alt" id="fpaddbtn">Add</button></div>
<div class="savebar"><button class="btn">Save site settings</button></div>
</form>
<?php endif; afoot(); exit;
}
