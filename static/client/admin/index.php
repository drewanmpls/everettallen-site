<?php
// Admin for private client galleries. Only Andrew uses this.
declare(strict_types=1);
require dirname(__DIR__) . '/lib.php';
noindex();
header('Cache-Control: private, no-store');
start_session();
$a = (string)($_REQUEST['a'] ?? 'list');
$post = $_SERVER['REQUEST_METHOD'] === 'POST';
$base = '/client/admin/';
$flash = '';

function go(string $to): void { header('Location: ' . $to); exit; }
function ahead(string $title): void { ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title><?= h($title) ?> | Gallery admin</title><link rel="icon" href="/assets/icon.png">
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@500;800&family=Archivo+Narrow:wght@500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/client/client.css">
<style>.adm{padding:0 28px 64px;max-width:1200px;margin:0 auto}table{border-collapse:collapse;width:100%;margin:12px 0 28px}th,td{text-align:left;padding:8px 10px;border-bottom:1px solid var(--line);vertical-align:top}th{font-family:'Archivo Narrow',sans-serif;text-transform:uppercase;letter-spacing:.06em;font-size:13px;color:var(--mut)}
.cols{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:0 20px}.cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:12px}
.cd{background:#f6f3ec;border:1px solid var(--line);padding:6px}.cd img{width:100%;aspect-ratio:3/2;object-fit:cover;display:block}.cd input{padding:5px 8px;font-size:14px}.cd small{display:block;color:var(--mut);font-size:12px;word-break:break-all}
.link{font-family:monospace;background:#f6f3ec;border:1px solid var(--line);padding:8px 10px;word-break:break-all;display:block}
h2{font-family:'Archivo Narrow',sans-serif;text-transform:uppercase;letter-spacing:.08em;font-size:20px;margin:32px 0 8px}
.drop{border:2px dashed var(--ink);padding:24px;text-align:center;background:#f6f3ec}.ok{color:#0a6b2d}.pill{display:inline-block;padding:1px 8px;border-radius:10px;background:var(--ink);color:var(--bg);font-size:12px}</style></head><body>
<header class="top"><a class="brand" href="/client/admin/">Gallery admin</a><span style="display:flex;gap:22px"><a href="/client/admin/">Galleries</a><a href="/client/admin/?a=clients">Clients</a><a href="/client/admin/?a=settings">Settings</a><a href="/client/admin/?a=logout">Log out</a></span></header><div class="adm">
<?php }
function afoot(): void { echo '</div></body></html>'; }

// ----- auth -----
$hash = setting('admin_hash', '');
if ($hash === '') {
    $codeFile = priv_dir() . '/setup-code.txt';
    if (!is_file($codeFile)) file_put_contents($codeFile, bin2hex(random_bytes(6)) . "\n");
    $err = '';
    if ($post && csrf_ok()) {
        $code = trim((string)file_get_contents($codeFile));
        if (!hash_equals($code, trim((string)($_POST['code'] ?? '')))) { $err = 'Setup code is wrong.'; usleep(700000); }
        elseif (strlen((string)($_POST['password'] ?? '')) < 10) $err = 'Use at least 10 characters.';
        else { set_setting('admin_hash', password_hash((string)$_POST['password'], PASSWORD_DEFAULT)); @unlink($codeFile); $_SESSION['admin'] = true; go($base); }
    }
    ahead('Set up'); ?>
<div class="narrow"><h1>Set up</h1><p>Choose an admin password. The setup code is in the file <code>setup-code.txt</code> inside this site's private folder on the server.</p>
<form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><label>Setup code<input name="code" required></label><label>New password (10+ characters)<input type="password" name="password" required></label>
<?php if ($err): ?><p class="err"><?= h($err) ?></p><?php endif; ?><button class="btn">Save password</button></form></div>
<?php afoot(); exit;
}
if ($a === 'logout') { $_SESSION = []; session_destroy(); go($base); }
if (empty($_SESSION['admin'])) {
    $err = '';
    if ($post && csrf_ok()) {
        if (password_verify((string)($_POST['password'] ?? ''), $hash)) { session_regenerate_id(true); $_SESSION['admin'] = true; go($base); }
        $err = 'Wrong password.'; usleep(1000000);
    }
    ahead('Log in'); ?>
<div class="narrow"><h1>Log in</h1><form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><label>Password<input type="password" name="password" autofocus required></label>
<?php if ($err): ?><p class="err"><?= h($err) ?></p><?php endif; ?><button class="btn">Log in</button></form></div>
<?php afoot(); exit;
}

// ----- helpers -----
function gal(int $id): array { $s = db()->prepare('SELECT * FROM galleries WHERE id = ?'); $s->execute([$id]); $g = $s->fetch(); if (!$g) { http_response_code(404); echo 'Not found'; exit; } return $g; }
function link_for(array $g): string { return 'https://' . ($_SERVER['HTTP_HOST'] ?? '') . '/client/g/' . $g['token'] . '/'; }

// thumbnail preview for admin (works even for inactive galleries)
if ($a === 'thumb') {
    $s = db()->prepare('SELECT * FROM images WHERE id = ?'); $s->execute([(int)($_GET['id'] ?? 0)]); $i = $s->fetch();
    if (!$i) { http_response_code(404); exit; }
    $p = gdir((int)$i['gallery_id'], 'thumb') . '/' . basename($i['file']);
    if (!is_file($p)) { http_response_code(404); exit; }
    header('Content-Type: image/jpeg'); header('Cache-Control: private, max-age=3600'); readfile($p); exit;
}

if ($a === 'releasephoto') {
    $cs = db()->prepare('SELECT photo_path, idphoto_path FROM clients WHERE id = ?'); $cs->execute([(int)($_GET['id'] ?? 0)]); $c = $cs->fetch();
    $f = $c ? (($_GET['k'] ?? '') === 'id' ? $c['idphoto_path'] : $c['photo_path']) : '';
    if (!$f || !is_file($f)) { http_response_code(404); echo 'No photo'; exit; }
    header('Content-Type: image/jpeg'); header('Cache-Control: private, no-store'); readfile($f); exit;
}
if ($a === 'releasepdf') {
    $cs = db()->prepare('SELECT * FROM clients WHERE id = ?'); $cs->execute([(int)($_GET['id'] ?? 0)]); $c = $cs->fetch();
    if (!$c || !$c['release_at']) { http_response_code(404); echo 'No release'; exit; }
    if (!$c['release_pdf'] || !is_file($c['release_pdf'])) save_release_pdf((int)$c['id']);
    $cs->execute([$c['id']]); $c = $cs->fetch();
    header('Content-Type: application/pdf'); header('Content-Disposition: inline; filename="' . release_filename($c) . '"'); header('Cache-Control: private, no-store');
    readfile($c['release_pdf']); exit;
}

// ----- POST actions -----
if ($post) {
    if (!csrf_ok()) { http_response_code(400); echo 'Session expired. Go back, reload the page and try again.'; exit; }
    $id = (int)($_POST['id'] ?? 0);

    if ($a === 'create') {
        $title = trim((string)($_POST['title'] ?? '')); if ($title === '') go($base);
        $pw = (string)($_POST['password'] ?? '');
        db()->prepare('INSERT INTO galleries (token, title, client_name, client_email, password_hash, default_price_cents, default_web_cents, included_count, note, created) VALUES (?,?,?,?,?,?,?,?,?,?)')
            ->execute([bin2hex(random_bytes(12)), $title, trim((string)$_POST['client_name']), trim((string)$_POST['client_email']), $pw !== '' ? password_hash($pw, PASSWORD_DEFAULT) : '', dollars_to_cents((string)($_POST['price'] ?? '0')), dollars_to_cents((string)($_POST['price_web'] ?? '0')), max(0, (int)($_POST['included'] ?? 0)), trim((string)$_POST['note']), time()]);
        go($base . '?a=gallery&id=' . db()->lastInsertId());
    }
    if ($a === 'update') {
        $g = gal($id);
        $pwHash = $g['password_hash'];
        if (!empty($_POST['clear_pw'])) $pwHash = ''; elseif ((string)($_POST['password'] ?? '') !== '') $pwHash = password_hash((string)$_POST['password'], PASSWORD_DEFAULT);
        db()->prepare('UPDATE galleries SET title=?, client_name=?, client_email=?, password_hash=?, default_price_cents=?, default_web_cents=?, included_count=?, note=?, active=?, client_id=? WHERE id=?')
            ->execute([trim((string)$_POST['title']) ?: $g['title'], trim((string)$_POST['client_name']), trim((string)$_POST['client_email']), $pwHash, dollars_to_cents((string)($_POST['price'] ?? '0')), dollars_to_cents((string)($_POST['price_web'] ?? '0')), max(0, (int)($_POST['included'] ?? 0)), trim((string)$_POST['note']), empty($_POST['active']) ? 0 : 1, (int)($_POST['client_id'] ?? 0) ?: null, $id]);
        go($base . '?a=gallery&id=' . $id . '&saved=1');
    }
    if ($a === 'upload') {
        $g = gal($id);
        try {
            $f = $_FILES['photo'] ?? null;
            if (!$f || $f['error'] !== UPLOAD_ERR_OK) throw new RuntimeException($f && $f['error'] === UPLOAD_ERR_INI_SIZE ? 'File is bigger than the server upload limit.' : 'Upload failed.');
            $r = ingest_image((int)$g['id'], $f['tmp_name'], $f['name']);
            $m = db()->prepare('SELECT COALESCE(MAX(sort),0)+1 FROM images WHERE gallery_id = ?'); $m->execute([$id]);
            db()->prepare('INSERT INTO images (gallery_id, file, label, sort, w, h, created) VALUES (?,?,?,?,?,?,?)')->execute([$id, $r['file'], $r['label'], (int)$m->fetchColumn(), $r['w'], $r['h'], time()]);
            json_out(['ok' => true]);
        } catch (Throwable $e) { json_out(['error' => $e->getMessage()], 400); }
    }
    if ($a === 'price') {
        $v = trim((string)($_POST['price'] ?? ''));
        $col = ($_POST['tier'] ?? 'full') === 'web' ? 'price_web_cents' : 'price_cents';
        db()->prepare("UPDATE images SET $col = ? WHERE id = ?")->execute([$v === '' ? null : dollars_to_cents($v), (int)($_POST['image'] ?? 0)]);
        json_out(['ok' => true]);
    }
    if ($a === 'priceall') {
        $v = trim((string)($_POST['price'] ?? ''));
        $col = ($_POST['tier'] ?? 'full') === 'web' ? 'price_web_cents' : 'price_cents';
        if (!empty($_POST['only_fav'])) db()->prepare("UPDATE images SET $col = ? WHERE gallery_id = ? AND id IN (SELECT image_id FROM favorites)")->execute([$v === '' ? null : dollars_to_cents($v), $id]);
        else db()->prepare("UPDATE images SET $col = ? WHERE gallery_id = ?")->execute([$v === '' ? null : dollars_to_cents($v), $id]);
        go($base . '?a=gallery&id=' . $id . '&saved=1');
    }
    if ($a === 'delimg') {
        $s = db()->prepare('SELECT * FROM images WHERE id = ?'); $s->execute([(int)($_POST['image'] ?? 0)]); $i = $s->fetch();
        if ($i) { foreach (['orig', 'display', 'thumb'] as $k) @unlink(gdir((int)$i['gallery_id'], $k) . '/' . basename($i['file'])); db()->prepare('DELETE FROM images WHERE id = ?')->execute([$i['id']]); }
        go($base . '?a=gallery&id=' . $id);
    }
    if ($a === 'reply') {
        $body = trim((string)($_POST['body'] ?? ''));
        if ($body !== '') db()->prepare('INSERT INTO comments (image_id, author, body, from_owner, created) VALUES (?,?,?,1,?)')->execute([(int)$_POST['image'], 'Andrew', $body, time()]);
        go($base . '?a=gallery&id=' . $id . '#comments');
    }
    if ($a === 'order') {
        $st = in_array($_POST['status'] ?? '', ['paid', 'requested', 'declined'], true) ? $_POST['status'] : 'requested';
        db()->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$st, (int)($_POST['order'] ?? 0)]);
        go($base . '?a=gallery&id=' . $id . '#orders');
    }
    if ($a === 'clientsave') {
        $f = []; foreach (['name', 'email', 'phone', 'pronouns', 'service', 'timeframe', 'location', 'handle', 'referral', 'share_pref', 'message', 'notes'] as $k) $f[$k] = trim((string)($_POST[$k] ?? ''));
        $st = array_key_exists($_POST['status'] ?? '', CLIENT_STATUSES) ? $_POST['status'] : 'new';
        if ($f['name'] === '') go($base . '?a=clients');
        if ($id > 0) {
            db()->prepare('UPDATE clients SET name=?, email=?, phone=?, pronouns=?, service=?, timeframe=?, location=?, handle=?, referral=?, share_pref=?, message=?, notes=?, status=?, discount_percent=? WHERE id=?')
                ->execute([$f['name'], $f['email'], $f['phone'], $f['pronouns'], $f['service'], $f['timeframe'], $f['location'], $f['handle'], $f['referral'], $f['share_pref'], $f['message'], $f['notes'], $st, max(0, min(100, (int)($_POST['discount_percent'] ?? 0))), $id]);
            go($base . '?a=client&id=' . $id . '&saved=1');
        }
        db()->prepare("INSERT INTO clients (name, email, phone, pronouns, service, timeframe, location, handle, referral, share_pref, message, notes, status, source, created) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?, 'admin', ?)")
            ->execute([$f['name'], $f['email'], $f['phone'], $f['pronouns'], $f['service'], $f['timeframe'], $f['location'], $f['handle'], $f['referral'], $f['share_pref'], $f['message'], $f['notes'], $st, time()]);
        go($base . '?a=client&id=' . db()->lastInsertId());
    }
    if ($a === 'releasesave') {
        $c0 = db()->prepare('SELECT release_at FROM clients WHERE id = ?'); $c0->execute([$id]);
        if ($c0->fetchColumn()) {
            db()->prepare('UPDATE clients SET shoot_date = ?, shoot_location = ?, id_verified = ? WHERE id = ?')
                ->execute([trim((string)($_POST['shoot_date'] ?? '')), trim((string)($_POST['shoot_location'] ?? '')), trim((string)($_POST['id_verified'] ?? '')), $id]);
            foreach (['photo' => ['model', 'photo_path'], 'idphoto' => ['id', 'idphoto_path']] as $in => [$kind, $col]) {
                $cur = db()->prepare("SELECT $col FROM clients WHERE id = ?"); $cur->execute([$id]); $old = (string)$cur->fetchColumn();
                if (!empty($_POST['remove_' . $in]) && $old !== '') { @unlink($old); db()->prepare("UPDATE clients SET $col = '' WHERE id = ?")->execute([$id]); }
                if (!empty($_FILES[$in]['name'])) { $sp = store_release_photo($id, $kind, $_FILES[$in]); if ($sp) db()->prepare("UPDATE clients SET $col = ? WHERE id = ?")->execute([$sp, $id]); }
            }
            save_release_pdf($id);
            if (!empty($_POST['to_drive'])) drive_upload($id);
        }
        go($base . '?a=client&id=' . $id . '&saved=1');
    }
    if ($a === 'driveretry') {
        $c0 = db()->prepare('SELECT release_at FROM clients WHERE id = ?'); $c0->execute([$id]);
        if ($c0->fetchColumn()) { save_release_pdf($id); drive_upload($id); }
        go($base . '?a=client&id=' . $id . '&saved=1');
    }
    if ($a === 'settingssave') {
        set_setting('drive_webhook_url', trim((string)($_POST['drive_webhook_url'] ?? '')));
        if (trim((string)($_POST['drive_webhook_secret'] ?? '')) !== '') set_setting('drive_webhook_secret', trim((string)$_POST['drive_webhook_secret']));
        go($base . '?a=settings&saved=1');
    }
    if ($a === 'makegallery') {
        $c = db()->prepare('SELECT * FROM clients WHERE id = ?'); $c->execute([$id]); $c = $c->fetch();
        if (!$c) go($base . '?a=clients');
        $inc = 0; foreach (services_list() as $sv) if ($sv['title'] === $c['service']) $inc = (int)$sv['included'];
        db()->prepare('INSERT INTO galleries (token, title, client_name, client_email, default_price_cents, default_web_cents, included_count, client_id, note, created) VALUES (?,?,?,?,0,0,?,?,?,?)')
            ->execute([bin2hex(random_bytes(12)), $c['name'] . ($c['service'] && $c['service'] !== 'Not sure yet' ? ': ' . $c['service'] : ''), $c['name'], $c['email'], $inc, $id, '', time()]);
        if ($c['status'] === 'new' || $c['status'] === 'contacted') db()->prepare("UPDATE clients SET status = 'booked' WHERE id = ?")->execute([$id]);
        go($base . '?a=gallery&id=' . db()->lastInsertId());
    }
    if ($a === 'linkgallery') {
        db()->prepare('UPDATE galleries SET client_id = ? WHERE id = ?')->execute([(int)($_POST['client_id'] ?? 0) ?: null, (int)($_POST['gallery'] ?? 0)]);
        go($base . '?a=client&id=' . (int)($_POST['client_id'] ?? 0) . '&saved=1');
    }
    if ($a === 'clientdel') {
        if (($_POST['confirm'] ?? '') === 'DELETE') { $pf = db()->prepare('SELECT photo_path, idphoto_path, release_pdf, release_sig FROM clients WHERE id = ?'); $pf->execute([$id]); foreach ((array)$pf->fetch() as $fp) { if (is_string($fp) && $fp !== '' && is_file($fp)) @unlink($fp); } db()->prepare('UPDATE galleries SET client_id = NULL WHERE client_id = ?')->execute([$id]); db()->prepare('DELETE FROM clients WHERE id = ?')->execute([$id]); go($base . '?a=clients'); }
        go($base . '?a=client&id=' . $id);
    }
    if ($a === 'resetclaims') {
        db()->prepare('DELETE FROM claims WHERE image_id IN (SELECT id FROM images WHERE gallery_id = ?)')->execute([$id]);
        go($base . '?a=gallery&id=' . $id . '&saved=1');
    }
    if ($a === 'delgallery') {
        $g = gal($id);
        if (($_POST['confirm'] ?? '') === 'DELETE') {
            $dir = priv_dir() . '/galleries/' . (int)$g['id'];
            if (is_dir($dir)) { $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); foreach ($it as $f) { $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); } @rmdir($dir); }
            db()->prepare('DELETE FROM galleries WHERE id = ?')->execute([$id]);
            go($base);
        }
        go($base . '?a=gallery&id=' . $id);
    }
    go($base);
}

// ----- views -----
if ($a === 'gallery') {
    $g = gal((int)($_GET['id'] ?? 0)); $gid = (int)$g['id'];
    $q = db()->prepare('SELECT i.*, (SELECT COUNT(*) FROM favorites f WHERE f.image_id = i.id) AS fav, (SELECT COUNT(*) FROM comments c WHERE c.image_id = i.id) AS ncom, (SELECT COUNT(*) FROM downloads d WHERE d.image_id = i.id) AS dls, (SELECT COUNT(*) FROM claims cl WHERE cl.image_id = i.id) AS claimed FROM images i WHERE i.gallery_id = ? ORDER BY i.sort, i.id'); $q->execute([$gid]);
    $imgs = $q->fetchAll();
    $nfav = count(array_filter($imgs, fn($i) => $i['fav'] > 0));
    ahead($g['title']); $tok = csrf(); ?>
<h1 style="font-size:clamp(32px,5vw,64px)"><?= h($g['title']) ?></h1>
<?php if (!empty($_GET['saved'])): ?><p class="ok">Saved.</p><?php endif; ?>
<?php if ($g['client_id']): ?><p><a href="?a=client&id=<?= (int)$g['client_id'] ?>">&larr; Back to this client's record</a></p><?php endif; ?>
<p class="kicker">Client link (send this to <?= h($g['client_name'] ?: 'your client') ?>)<?= $g['password_hash'] ? ', password protected' : '' ?><?= $g['active'] ? '' : ' / currently turned OFF' ?></p>
<code class="link" id="lnk"><?= h(link_for($g)) ?></code>
<p><button class="btn sm alt" onclick="navigator.clipboard.writeText(document.getElementById('lnk').textContent).then(()=>this.textContent='Copied')">Copy link</button> <a class="btn sm alt" target="_blank" href="/client/g/<?= h($g['token']) ?>/">Preview as client</a></p>

<h2>Upload photos</h2>
<div class="drop"><input type="file" id="files" accept="image/jpeg" multiple style="max-width:420px"><p id="prog" class="kicker">JPEGs only. Originals are stored privately; the client sees watermarked copies. Photos upload one at a time in filename order.</p></div>

<h2>Gallery settings</h2>
<form method="post" class="cols"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="update"><input type="hidden" name="id" value="<?= $gid ?>">
<label>Title<input name="title" value="<?= h($g['title']) ?>" required></label>
<label>Client name<input name="client_name" value="<?= h($g['client_name']) ?>"></label>
<label>Client email<input name="client_email" type="email" value="<?= h($g['client_email']) ?>"></label>
<label>Client record<select name="client_id" style="width:100%;padding:10px;margin-top:4px"><option value="">None</option><?php foreach (db()->query('SELECT id, name, service FROM clients ORDER BY name') as $cr): ?><option value="<?= $cr['id'] ?>" <?= (int)$g['client_id'] === (int)$cr['id'] ? 'selected' : '' ?>><?= h($cr['name'] . ($cr['service'] ? ' (' . $cr['service'] . ')' : '')) ?></option><?php endforeach; ?></select></label>
<label>Included photos (free picks, 0 = none)<input name="included" type="number" min="0" value="<?= (int)$g['included_count'] ?>"></label>
<label>Extra photo, web size ($, 0 = Free)<input name="price_web" value="<?= h(number_format($g['default_web_cents'] / 100, 2)) ?>"></label>
<label>Extra photo, full resolution ($, 0 = Free)<input name="price" value="<?= h(number_format($g['default_price_cents'] / 100, 2)) ?>"></label>
<label>New password (leave blank to keep)<input name="password" type="text" autocomplete="off"></label>
<label>Remove password<br><input type="checkbox" name="clear_pw" value="1" style="width:auto"> Anyone with the link can open it</label>
<label style="grid-column:1/-1">Note shown to client<textarea name="note" rows="3"><?= h($g['note']) ?></textarea></label>
<label>Status<br><input type="checkbox" name="active" value="1" style="width:auto" <?= $g['active'] ? 'checked' : '' ?>> Gallery is on (uncheck to hide it)</label>
<div style="grid-column:1/-1"><button class="btn">Save settings</button></div></form>

<h2>Prices</h2>
<p><b>Web size</b> is a 2048 px copy for social sharing. <b>Full resolution</b> is the original file for printing. Photos in the client's included package get both for free. Each photo uses the gallery default unless you type its own price (0 = Free, blank = default).</p>
<form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="priceall"><input type="hidden" name="id" value="<?= $gid ?>">
<label style="margin:0">Set all photos<br><select name="tier" style="padding:10px"><option value="web">web size</option><option value="full">full resolution</option></select></label>
<label style="margin:0">to ($)<input name="price" placeholder="blank = default" style="width:150px"></label>
<label style="margin:0"><input type="checkbox" name="only_fav" value="1" style="width:auto"> only favorites (<?= $nfav ?>)</label><button class="btn sm">Apply</button></form>
<?php $used = claims_used($gid); if ($g['included_count'] > 0): ?>
<p style="margin-top:16px"><b>Package:</b> <?= $used ?> of <?= (int)$g['included_count'] ?> included photos used.
<form method="post" onsubmit="return confirm('Clear all of this client\'s package picks?')" style="display:inline"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="resetclaims"><input type="hidden" name="id" value="<?= $gid ?>"><button class="btn sm alt">Reset picks</button></form></p>
<?php endif; ?>

<h2><?= count($imgs) ?> photos</h2>
<div class="cards">
<?php foreach ($imgs as $i): ?>
<div class="cd"><img loading="lazy" src="<?= $base ?>?a=thumb&id=<?= $i['id'] ?>" alt="">
<small><?= h($i['label']) ?></small>
<div style="display:grid;grid-template-columns:auto 1fr;gap:4px 6px;align-items:center;margin:4px 0;font-size:12px"><span>Web $</span><input value="<?= ($i['price_web_cents'] ?? null) === null ? '' : h(number_format($i['price_web_cents'] / 100, 2)) ?>" placeholder="<?= h(number_format($g['default_web_cents'] / 100, 2)) ?>" data-img="<?= $i['id'] ?>" data-tier="web" class="pr"><span>Full $</span><input value="<?= $i['price_cents'] === null ? '' : h(number_format($i['price_cents'] / 100, 2)) ?>" placeholder="<?= h(number_format($g['default_price_cents'] / 100, 2)) ?>" data-img="<?= $i['id'] ?>" data-tier="full" class="pr"></div>
<small><?= $i['fav'] ? '&#9829; favorite ' : '' ?><?= $i['claimed'] ? '[in package] ' : '' ?><?= $i['ncom'] ? $i['ncom'] . ' comments ' : '' ?><?= $i['dls'] ? $i['dls'] . ' downloads' : '' ?></small>
<form method="post" onsubmit="return confirm('Delete this photo and its original?')"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="delimg"><input type="hidden" name="id" value="<?= $gid ?>"><input type="hidden" name="image" value="<?= $i['id'] ?>"><button class="btn sm alt" style="padding:3px 8px;font-size:12px">Delete</button></form></div>
<?php endforeach; ?></div>

<?php
$o = db()->prepare('SELECT o.*, i.label FROM orders o JOIN images i ON i.id = o.image_id WHERE o.gallery_id = ? ORDER BY o.id DESC'); $o->execute([$gid]); $orders = $o->fetchAll();
$c = db()->prepare('SELECT c.*, i.label, i.id AS iid FROM comments c JOIN images i ON i.id = c.image_id WHERE i.gallery_id = ? ORDER BY c.id DESC'); $c->execute([$gid]); $coms = $c->fetchAll();
$fv = db()->prepare('SELECT i.label FROM favorites f JOIN images i ON i.id = f.image_id WHERE i.gallery_id = ? ORDER BY i.sort'); $fv->execute([$gid]); $favs = $fv->fetchAll(PDO::FETCH_COLUMN);
?>
<h2 id="orders">Photo requests</h2>
<?php if (!$orders): ?><p>None yet.</p><?php else: ?><table><tr><th>Photo</th><th>Size</th><th>Email</th><th>Status</th><th></th></tr>
<?php foreach ($orders as $r): ?><tr><td><?= h($r['label']) ?></td><td><?= $r['tier'] === 'web' ? 'Web' : 'Full' ?></td><td><?= h($r['email']) ?></td><td><span class="pill"><?= h($r['status']) ?></span></td><td>
<form method="post" style="display:flex;gap:6px"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="order"><input type="hidden" name="id" value="<?= $gid ?>"><input type="hidden" name="order" value="<?= $r['id'] ?>">
<button name="status" value="paid" class="btn sm">Mark paid (unlocks download)</button><button name="status" value="declined" class="btn sm alt">Decline</button></form></td></tr><?php endforeach; ?></table><?php endif; ?>

<h2>Favorites (<?= count($favs) ?>)</h2>
<p><?= $favs ? h(implode(', ', $favs)) : 'None yet.' ?></p>

<h2 id="comments">Comments</h2>
<?php if (!$coms): ?><p>None yet.</p><?php else: ?><table><tr><th>Photo</th><th>Who</th><th>Comment</th><th>Reply</th></tr>
<?php foreach ($coms as $r): ?><tr><td><?= h($r['label']) ?></td><td><?= h($r['from_owner'] ? 'You' : $r['author']) ?><br><small><?= date('M j, g:ia', (int)$r['created']) ?></small></td><td><?= nl2br(h($r['body'])) ?></td><td>
<?php if (!$r['from_owner']): ?><form method="post" style="display:flex;gap:6px"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="reply"><input type="hidden" name="id" value="<?= $gid ?>"><input type="hidden" name="image" value="<?= $r['iid'] ?>"><input name="body" placeholder="Reply" required><button class="btn sm">Send</button></form><?php endif; ?></td></tr><?php endforeach; ?></table><?php endif; ?>

<h2>Delete gallery</h2>
<form method="post" onsubmit="return confirm('This permanently deletes the gallery and all its photos. Continue?')" style="display:flex;gap:8px;max-width:420px"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="delgallery"><input type="hidden" name="id" value="<?= $gid ?>"><input name="confirm" placeholder="Type DELETE"><button class="btn sm alt">Delete</button></form>

<script>
var CSRF=<?= json_encode($tok) ?>,GID=<?= $gid ?>,BASE=<?= json_encode($base) ?>;
document.querySelectorAll('.pr').forEach(function(el){el.addEventListener('change',function(){
  var f=new URLSearchParams({a:'price',image:el.dataset.img,tier:el.dataset.tier,price:el.value,csrf:CSRF});
  fetch(BASE,{method:'POST',body:f}).then(function(r){el.style.outline=r.ok?'2px solid #0a6b2d':'2px solid #b00020'});
})});
document.getElementById('files').addEventListener('change',async function(e){
  var fs=Array.from(e.target.files).sort(function(a,b){return a.name.localeCompare(b.name,undefined,{numeric:true})});
  var p=document.getElementById('prog'),bad=[];
  for(var i=0;i<fs.length;i++){
    p.textContent='Uploading '+(i+1)+' of '+fs.length+': '+fs[i].name;
    var fd=new FormData();fd.append('a','upload');fd.append('id',GID);fd.append('csrf',CSRF);fd.append('photo',fs[i]);
    try{var r=await fetch(BASE,{method:'POST',body:fd});var j=await r.json();if(!r.ok)bad.push(fs[i].name+': '+(j.error||'failed'))}catch(_){bad.push(fs[i].name+': network error')}
  }
  p.textContent=bad.length?('Done with problems: '+bad.join(' | ')):'All uploaded.';
  if(!bad.length)location.reload();
});
</script>
<?php afoot(); exit;
}

if ($a === 'clients') {
    $stf = (string)($_GET['status'] ?? ''); $q = trim((string)($_GET['q'] ?? ''));
    $sql = 'SELECT c.*, (SELECT COUNT(*) FROM galleries g WHERE g.client_id = c.id) AS ng FROM clients c WHERE 1=1'; $args = [];
    if ($stf !== '' && isset(CLIENT_STATUSES[$stf])) { $sql .= ' AND c.status = ?'; $args[] = $stf; } elseif ($stf === '') { $sql .= " AND c.status != 'archived'"; }
    if ($q !== '') { $sql .= ' AND (c.name LIKE ? OR c.email LIKE ? OR c.service LIKE ?)'; array_push($args, "%$q%", "%$q%", "%$q%"); }
    $stmt = db()->prepare($sql . ' ORDER BY c.created DESC'); $stmt->execute($args); $rows = $stmt->fetchAll();
    $counts = db()->query('SELECT status, COUNT(*) n FROM clients GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
    ahead('Clients'); $tok = csrf(); $intake = 'https://' . ($_SERVER['HTTP_HOST'] ?? '') . '/client/new/'; ?>
<h1 style="font-size:clamp(32px,5vw,64px)">Clients</h1>
<p class="kicker">New-client form to send people</p>
<code class="link" id="ilnk"><?= h($intake) ?></code>
<p><button class="btn sm alt" onclick="navigator.clipboard.writeText(document.getElementById('ilnk').textContent).then(()=>this.textContent='Copied')">Copy link</button> <a class="btn sm alt" target="_blank" href="/client/new/">Open the form</a></p>
<div style="display:flex;gap:8px;flex-wrap:wrap;margin:20px 0"><a class="tab <?= $stf === '' ? 'on' : '' ?>" style="text-decoration:none" href="?a=clients">Active</a>
<?php foreach (CLIENT_STATUSES as $k => $lab): ?><a class="tab <?= $stf === $k ? 'on' : '' ?>" style="text-decoration:none" href="?a=clients&status=<?= $k ?>"><?= h($lab) ?> <?= (int)($counts[$k] ?? 0) ?></a><?php endforeach; ?></div>
<form method="get" style="display:flex;gap:8px;max-width:420px"><input type="hidden" name="a" value="clients"><input name="q" value="<?= h($q) ?>" placeholder="Search name, email or service"><button class="btn sm">Search</button></form>
<?php if (!$rows): ?><p style="margin-top:16px">No clients here yet.</p><?php else: ?>
<table><tr><th>Name</th><th>Service</th><th>Status</th><th>Timeframe</th><th>Galleries</th><th>Received</th></tr>
<?php foreach ($rows as $r): ?><tr><td><a href="?a=client&id=<?= $r['id'] ?>"><b><?= h($r['name']) ?></b></a><br><small><?= h($r['email']) ?></small></td><td><?= h($r['service']) ?></td><td><span class="pill"><?= h(CLIENT_STATUSES[$r['status']] ?? $r['status']) ?></span><?= $r['release_at'] ? ' <span class="pill" style="background:#0a6b2d">release</span>' : '' ?></td><td><?= h($r['timeframe']) ?></td><td><?= (int)$r['ng'] ?></td><td><?= date('M j, Y', (int)$r['created']) ?><?= $r['source'] === 'admin' ? ' (added by you)' : '' ?></td></tr><?php endforeach; ?></table><?php endif; ?>
<h2>Add a client yourself</h2>
<form method="post" action="?a=clientsave" class="cols"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="clientsave"><input type="hidden" name="id" value="0">
<label>Name<input name="name" required></label><label>Email<input name="email" type="email"></label><label>Phone<input name="phone"></label>
<label>Service<select name="service" style="width:100%;padding:10px;margin-top:4px"><option value="">Choose</option><?php foreach (services_list() as $sv): ?><option><?= h($sv['title']) ?></option><?php endforeach; ?><option>Not sure yet</option><option>Something else</option></select></label>
<label>Timeframe<input name="timeframe"></label><label>Notes (private)<input name="notes"></label>
<div style="grid-column:1/-1"><button class="btn">Add client</button></div></form>
<?php afoot(); exit;
}

if ($a === 'client') {
    $cs = db()->prepare('SELECT * FROM clients WHERE id = ?'); $cs->execute([(int)($_GET['id'] ?? 0)]); $c = $cs->fetch();
    if (!$c) { http_response_code(404); ahead('Not found'); echo '<p>Client not found. <a href="?a=clients">Back to clients</a></p>'; afoot(); exit; }
    $gs = db()->prepare('SELECT g.*, (SELECT COUNT(*) FROM images i WHERE i.gallery_id = g.id) AS n FROM galleries g WHERE g.client_id = ? ORDER BY g.id DESC'); $gs->execute([$c['id']]); $gals = $gs->fetchAll();
    $free = db()->query('SELECT id, title FROM galleries WHERE client_id IS NULL ORDER BY id DESC')->fetchAll();
    $inc = 0; foreach (services_list() as $sv) if ($sv['title'] === $c['service']) $inc = (int)$sv['included'];
    ahead($c['name']); $tok = csrf(); ?>
<p><a href="?a=clients">&larr; All clients</a></p>
<h1 style="font-size:clamp(32px,5vw,64px)"><?= h($c['name']) ?></h1>
<?php if (!empty($_GET['saved'])): ?><p class="ok">Saved.</p><?php endif; ?>
<p class="kicker"><?= h(CLIENT_STATUSES[$c['status']] ?? '') ?> / <?= $c['source'] === 'admin' ? 'added by you' : 'sent the form' ?> on <?= date('M j, Y', (int)$c['created']) ?></p>
<?php if ($c['email']): ?><p><a class="btn sm alt" href="mailto:<?= h($c['email']) ?>">Email <?= h($c['name']) ?></a></p><?php endif; ?>

<h2>Galleries</h2>
<?php if (!$gals): ?><p>No galleries yet.</p><?php else: ?><table><tr><th>Gallery</th><th>Photos</th><th>Package</th><th>Link</th></tr>
<?php foreach ($gals as $g): $url = link_for($g); ?><tr><td><a href="?a=gallery&id=<?= $g['id'] ?>"><b><?= h($g['title']) ?></b></a><?= $g['active'] ? '' : ' <span class="pill">off</span>' ?></td><td><?= (int)$g['n'] ?></td><td><?= (int)$g['included_count'] ?> included</td>
<td><a href="mailto:<?= h($c['email']) ?>?subject=<?= rawurlencode('Your photos from Everett Allen Photography') ?>&body=<?= rawurlencode("Hi " . $c['name'] . ",\n\nYour private gallery is ready:\n" . $url . ($g['password_hash'] ? "\n\n(I'll send the password separately.)" : '') . "\n\nTap the heart on your favorites, and leave a comment on any photo.\n\nAndrew") ?>">Email link to client</a></td></tr><?php endforeach; ?></table><?php endif; ?>
<form method="post" style="display:inline-block;margin-right:12px"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="makegallery"><input type="hidden" name="id" value="<?= $c['id'] ?>"><button class="btn sm">Create gallery for <?= h($c['name']) ?><?= $inc ? " ($inc photos included)" : '' ?></button></form>
<?php if ($free): ?><form method="post" style="display:inline-flex;gap:8px;align-items:center"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="linkgallery"><input type="hidden" name="client_id" value="<?= $c['id'] ?>">
<select name="gallery" style="padding:8px"><?php foreach ($free as $f): ?><option value="<?= $f['id'] ?>"><?= h($f['title']) ?></option><?php endforeach; ?></select><button class="btn sm alt">Link existing gallery</button></form><?php endif; ?>

<?php if ($c['release_at']): $ds = (string)$c['drive_status']; ?><h2>Photo release on file</h2>
<div style="background:#f6f3ec;border-left:4px solid <?= $c['id_verified'] !== '' ? '#0a6b2d' : '#b36b00' ?>;padding:12px 16px;max-width:75ch">
<b>Signed by <?= h($c['release_name']) ?></b><?= $c['release_stage'] ? ' (' . h($c['release_stage']) . ')' : '' ?> on <?= date('M j, Y g:ia', (int)$c['release_at']) ?><?= $c['discount_percent'] ? ', ' . (int)$c['discount_percent'] . '% session discount' : '' ?>. Gallery displays: <b><?= h($c['release_gallery'] ?: 'n/a') ?></b>. Born <?= h($c['release_dob']) ?>.<br>
<b>ID checked in person:</b> <?= $c['id_verified'] !== '' ? h($c['id_verified']) : '<span style="color:#b36b00">not yet. Check ID at the session, then record it below.</span>' ?><br>
<b>Google Drive:</b> <?= $ds === 'saved' ? '<span class="ok">saved</span>' . ($c['drive_url'] ? ' (<a href="' . h($c['drive_url']) . '" target="_blank" rel="noopener">open</a>)' : '') : ($ds === '' ? 'not sent' : '<span style="color:#b00020">' . h($ds) . '</span>') ?>
<p style="margin:10px 0 0"><a class="btn sm" href="?a=releasepdf&id=<?= $c['id'] ?>" target="_blank">View / download PDF</a></p>
<details style="margin-top:10px"><summary>Exact wording they agreed to</summary><p style="white-space:pre-line"><?= h($c['release_text']) ?></p></details></div>
<form method="post" enctype="multipart/form-data" class="cols" style="margin-top:12px"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="releasesave"><input type="hidden" name="id" value="<?= $c['id'] ?>">
<label>Shoot date<input name="shoot_date" value="<?= h($c['shoot_date']) ?>" placeholder="11/15/2026"></label>
<label>Shoot location<input name="shoot_location" value="<?= h($c['shoot_location']) ?>" placeholder="Studio, Minneapolis MN"></label>
<label>ID checked in person (date, or leave blank)<input name="id_verified" value="<?= h($c['id_verified']) ?>" placeholder="11/15/2026"></label>
<div><label>Model photo (adds to the PDF)<input type="file" name="photo" accept="image/*"></label>
<button type="button" class="btn sm alt" data-cam="photo" style="margin-top:6px">Use camera</button> <span class="camnote" data-note="photo" style="font-size:13px"></span>
<?php if ($c['photo_path'] !== '' && is_file($c['photo_path'])): ?><div style="margin-top:6px"><img src="?a=releasephoto&k=model&id=<?= $c['id'] ?>" alt="" style="max-width:140px;max-height:140px;display:block"><label style="font-size:13px"><input type="checkbox" name="remove_photo" value="1" style="width:auto"> Remove this photo</label></div><?php endif; ?></div>
<div><label>ID photo (adds to the PDF, then keep it private)<input type="file" name="idphoto" accept="image/*"></label>
<button type="button" class="btn sm alt" data-cam="idphoto" style="margin-top:6px">Use camera</button> <span class="camnote" data-note="idphoto" style="font-size:13px"></span>
<?php if ($c['idphoto_path'] !== '' && is_file($c['idphoto_path'])): ?><div style="margin-top:6px"><img src="?a=releasephoto&k=id&id=<?= $c['id'] ?>" alt="" style="max-width:200px;max-height:140px;display:block"><label style="font-size:13px"><input type="checkbox" name="remove_idphoto" value="1" style="width:auto"> Remove this photo</label></div><?php endif; ?></div>
<label style="align-self:end"><input type="checkbox" name="to_drive" value="1" style="width:auto" checked> Also save the updated PDF to Google Drive</label>
<div style="grid-column:1/-1"><button class="btn sm">Update release PDF</button></div></form>
<dialog id="camdlg" style="max-height:94vh;overflow:auto;border:2px solid #111;background:#f6f3ec;padding:14px;max-width:640px;width:92vw">
<p id="camtitle" style="margin:0 0 8px;font-weight:700"></p>
<video id="camvid" autoplay playsinline muted style="width:100%;background:#000;max-height:50vh;object-fit:contain"></video>
<p id="camerr" style="color:#b00020;margin:8px 0 0"></p>
<p style="margin:10px 0 0;display:flex;gap:8px"><button type="button" class="btn sm" id="camsnap">Take photo</button><button type="button" class="btn sm alt" id="camcancel">Cancel</button></p>
</dialog>
<script>
(function(){
  var dlg=document.getElementById('camdlg'),vid=document.getElementById('camvid'),err=document.getElementById('camerr'),title=document.getElementById('camtitle'),stream=null,field=null;
  function stop(){ if(stream){stream.getTracks().forEach(function(t){t.stop()});stream=null} vid.srcObject=null }
  function close(){ stop(); if(dlg.open) dlg.close() }
  document.querySelectorAll('[data-cam]').forEach(function(btn){
    btn.addEventListener('click',function(){
      field=btn.getAttribute('data-cam'); err.textContent='';
      title.textContent=field==='idphoto'?'Photo of the ID (fill the frame, check it is readable)':'Photo of the model';
      if(!navigator.mediaDevices||!navigator.mediaDevices.getUserMedia){ alert('This browser cannot use the camera here. Use the file box instead.'); return }
      dlg.showModal();
      navigator.mediaDevices.getUserMedia({video:{facingMode:field==='idphoto'?'environment':'user',width:{ideal:1920},height:{ideal:1080}},audio:false}).then(function(st){ stream=st; vid.srcObject=st }).catch(function(e){ err.textContent='Could not open the camera ('+(e&&e.name||'error')+'). Allow camera access for this site in the browser, then try again.' });
    });
  });
  document.getElementById('camcancel').addEventListener('click',close);
  dlg.addEventListener('cancel',function(){ stop() });
  document.getElementById('camsnap').addEventListener('click',function(){
    if(!stream||!vid.videoWidth) return;
    var cv=document.createElement('canvas'); cv.width=vid.videoWidth; cv.height=vid.videoHeight; cv.getContext('2d').drawImage(vid,0,0);
    cv.toBlob(function(b){
      if(!b){ err.textContent='Could not capture. Try again.'; return }
      var f=new File([b],field+'-camera.jpg',{type:'image/jpeg'}),dt=new DataTransfer(); dt.items.add(f);
      document.querySelector('input[name="'+field+'"]').files=dt.files;
      var n=document.querySelector('[data-note="'+field+'"]'); if(n) n.textContent='Photo ready. Click Update release PDF to save it.';
      close();
    },'image/jpeg',0.9);
  });
})();
</script>
<?php if ($ds !== 'saved'): ?><form method="post" style="margin-top:8px"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="driveretry"><input type="hidden" name="id" value="<?= $c['id'] ?>"><button class="btn sm alt">Save to Google Drive now</button></form><?php endif; ?>
<?php elseif ($c['share_pref']): ?><p class="kicker">Sharing preference: <?= h($c['share_pref']) ?> (no signed release on file)</p><?php endif; ?>
<h2>Details</h2>
<form method="post" class="cols"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="clientsave"><input type="hidden" name="id" value="<?= $c['id'] ?>">
<label>Name<input name="name" value="<?= h($c['name']) ?>" required></label><label>Pronouns<input name="pronouns" value="<?= h($c['pronouns']) ?>"></label>
<label>Email<input name="email" type="email" value="<?= h($c['email']) ?>"></label><label>Phone<input name="phone" value="<?= h($c['phone']) ?>"></label>
<label>Service<input name="service" value="<?= h($c['service']) ?>" list="svl"><datalist id="svl"><?php foreach (services_list() as $sv): ?><option value="<?= h($sv['title']) ?>"><?php endforeach; ?></datalist></label>
<label>Status<select name="status" style="width:100%;padding:10px;margin-top:4px"><?php foreach (CLIENT_STATUSES as $k => $lab): ?><option value="<?= $k ?>" <?= $c['status'] === $k ? 'selected' : '' ?>><?= h($lab) ?></option><?php endforeach; ?></select></label>
<label>Timeframe<input name="timeframe" value="<?= h($c['timeframe']) ?>"></label><label>Location ideas<input name="location" value="<?= h($c['location']) ?>"></label>
<label>Instagram or website<input name="handle" value="<?= h($c['handle']) ?>"></label><label>Heard about me from<input name="referral" value="<?= h($c['referral']) ?>"></label>
<label>May share photos publicly?<input name="share_pref" value="<?= h($c['share_pref']) ?>" list="spl"><datalist id="spl"><option value="Yes"><option value="Ask first"><option value="Private"></datalist></label>
<label>Session discount (%)<input name="discount_percent" type="number" min="0" max="100" value="<?= (int)$c['discount_percent'] ?>"></label>
<label style="grid-column:1/-1">What they wrote<textarea name="message" rows="4"><?= h($c['message']) ?></textarea></label>
<label style="grid-column:1/-1">Your private notes (clients never see these)<textarea name="notes" rows="4"><?= h($c['notes']) ?></textarea></label>
<div style="grid-column:1/-1"><button class="btn">Save</button></div></form>

<h2>Delete client</h2>
<p>Removes this record only. Their galleries stay and become unlinked.</p>
<form method="post" onsubmit="return confirm('Delete this client record?')" style="display:flex;gap:8px;max-width:420px"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="clientdel"><input type="hidden" name="id" value="<?= $c['id'] ?>"><input name="confirm" placeholder="Type DELETE"><button class="btn sm alt">Delete</button></form>
<?php afoot(); exit;
}

if ($a === 'settings') {
    ahead('Settings'); $tok = csrf(); ?>
<h1 style="font-size:clamp(32px,5vw,64px)">Settings</h1>
<?php if (!empty($_GET['saved'])): ?><p class="ok">Saved.</p><?php endif; ?>
<h2>Save signed releases to Google Drive</h2>
<p>Paste the web app address from your Google Apps Script, and the secret word you put in that script. Releases are then filed into your Drive folder automatically when a client signs.</p>
<form method="post" style="max-width:640px"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="settingssave">
<label>Web app address<input name="drive_webhook_url" value="<?= h((string)setting('drive_webhook_url', '')) ?>" placeholder="https://script.google.com/macros/s/.../exec"></label>
<label>Secret word <?= setting('drive_webhook_secret', '') ? '(already saved, type a new one to replace it)' : '' ?><input name="drive_webhook_secret" type="text" autocomplete="off"></label>
<button class="btn">Save</button></form>
<?php afoot(); exit;
}

// list
$rows = db()->query('SELECT g.*, (SELECT COUNT(*) FROM images i WHERE i.gallery_id=g.id) AS n, (SELECT COUNT(*) FROM favorites f JOIN images i ON i.id=f.image_id WHERE i.gallery_id=g.id) AS fav, (SELECT COUNT(*) FROM comments c JOIN images i ON i.id=c.image_id WHERE i.gallery_id=g.id AND c.from_owner=0) AS com, (SELECT COUNT(*) FROM orders o WHERE o.gallery_id=g.id AND o.status=\'requested\') AS req FROM galleries g ORDER BY g.id DESC')->fetchAll();
ahead('Galleries'); $tok = csrf(); ?>
<h1 style="font-size:clamp(32px,5vw,64px)">Client galleries</h1>
<?php if (!$rows): ?><p>No galleries yet. Create your first below.</p><?php else: ?>
<table><tr><th>Gallery</th><th>Client</th><th>Photos</th><th>Favorites</th><th>Comments</th><th>Open requests</th><th></th></tr>
<?php foreach ($rows as $r): ?><tr><td><a href="?a=gallery&id=<?= $r['id'] ?>"><b><?= h($r['title']) ?></b></a><?= $r['active'] ? '' : ' <span class="pill">off</span>' ?></td><td><?= h($r['client_name']) ?></td><td><?= $r['n'] ?></td><td><?= $r['fav'] ?></td><td><?= $r['com'] ?></td><td><?= $r['req'] ?></td><td><a href="?a=gallery&id=<?= $r['id'] ?>">Manage</a></td></tr><?php endforeach; ?></table><?php endif; ?>
<h2>New gallery</h2>
<form method="post" class="cols"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="create">
<label>Title<input name="title" required placeholder="Jordan and Sam, spring session"></label>
<label>Client name<input name="client_name"></label>
<label>Client email<input name="client_email" type="email"></label>
<label>Included photos (free picks)<input name="included" type="number" min="0" value="0"></label>
<label>Extra photo, web size ($, 0 = Free)<input name="price_web" value="0"></label>
<label>Extra photo, full resolution ($, 0 = Free)<input name="price" value="0"></label>
<label>Password (optional)<input name="password" autocomplete="off"></label>
<label style="grid-column:1/-1">Note shown to client<textarea name="note" rows="2"></textarea></label>
<div style="grid-column:1/-1"><button class="btn">Create gallery</button></div></form>
<?php afoot();
