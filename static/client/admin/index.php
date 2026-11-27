<?php
// Admin for private client galleries. Only Andrew uses this.
declare(strict_types=1);
require dirname(__DIR__) . '/lib.php';
noindex();
header('Cache-Control: private, no-store');
start_session();
$a = (string)($_REQUEST['a'] ?? 'dashboard');
$post = $_SERVER['REQUEST_METHOD'] === 'POST';
$base = '/client/admin/';
$flash = '';

function go(string $to): void { header('Location: ' . $to); exit; }
// Admin menu (IMPLEMENTATION-PLAN.md Section 3.7). Each row: label, action, the actions that also mark it as current.
// Later tickets add one line here when their page exists (Sessions, Sales, Galleries, Event series, Pages, Services).
const ADMIN_MENU = [
    '' => [['Dashboard', 'dashboard', []]],
    'Studio' => [['Clients', 'clients', ['client']], ['Client galleries', 'list', ['gallery']], ['Codes', 'codes', []]],
    'Portfolio' => [['Galleries', 'pgalleries', ['pgallery']], ['Publish', 'publish', []], ['Event series', 'series', []], ['Library', 'library', []]],
    'Website' => [['Pages', 'pages', []], ['Short links', 'links', []], ['Stats', 'stats', []], ['Settings', 'settings', []]],
];
// Phone tab bar: label, action, icon path.
const ADMIN_TABS = [
    ['Dashboard', 'dashboard', 'M3 11l9-7 9 7v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z'],
    ['Clients', 'clients', 'M16 20v-1.5a3.5 3.5 0 0 0-3.5-3.5h-5A3.5 3.5 0 0 0 4 18.5V20M10 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7M20 20v-1.5a3.5 3.5 0 0 0-2.5-3.35M15.5 4.15a3.5 3.5 0 0 1 0 6.7'],
    ['Galleries', 'list', 'M4 5h16v14H4zM4 15l4.5-4.5 4 4 3-3L20 15'],
    ['Publish', 'publish', 'M12 16V4M7 9l5-5 5 5M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3'],
];
function admin_current(): string {
    $a = (string)($GLOBALS['a'] ?? 'dashboard');
    foreach (ADMIN_MENU as $rows) foreach ($rows as [$lab, $act, $also]) if ($a === $act || in_array($a, $also, true)) return $act;
    return '';
}
function ahead(string $title): void {
    $in = !empty($_SESSION['admin']); $cur = admin_current(); $base = '/client/admin/';
    $href = fn(string $act) => $act === 'dashboard' ? $base : $base . '?a=' . $act; ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="robots" content="noindex,nofollow">
<title><?= h($title) ?> | Gallery admin</title><link rel="icon" href="/assets/icon.png">
<link rel="stylesheet" href="<?= h(asset('/client/admin/admin.css')) ?>"><script src="<?= h(asset('/client/admin/admin.js')) ?>"></script></head>
<body<?= $in ? '' : ' class="solo"' ?>><a class="skip" href="#main">Skip to content</a>
<?php if ($in): ?><nav class="side" id="side" aria-label="Admin menu"><button type="button" class="mnuclose">Close menu</button>
<a class="logo" href="<?= $base ?>">Everett Allen<small>Photography admin</small></a>
<?php foreach (ADMIN_MENU as $sec => $rows): ?><?php if ($sec !== ''): ?><h2><?= h($sec) ?></h2><?php endif; ?><ul><?php foreach ($rows as [$lab, $act]): ?><li><a href="<?= h($href($act)) ?>"<?= $cur === $act ? ' aria-current="page"' : '' ?>><?= h($lab) ?></a></li><?php endforeach; ?></ul>
<?php endforeach; ?><div class="tools"><label for="eaTheme">Appearance</label><select id="eaTheme"><option value="auto">Auto (match device)</option><option value="light">Light</option><option value="dark">Dark</option></select>
<ul><li><a href="/" target="_blank" rel="noopener">View site</a></li><li><a href="<?= $base ?>?a=logout">Log out</a></li></ul></div></nav><?php endif; ?>
<div class="main"><header class="top"><?php if ($in): ?><button type="button" class="mnubtn" data-menu-open aria-controls="side" aria-expanded="false"><i></i>Menu</button><?php endif; ?><span class="grp">Everett Allen</span><a class="site" href="/" target="_blank" rel="noopener">View site</a></header>
<main id="main">
<?php }
function afoot(): void {
    if (!empty($_SESSION['admin'])) {
        $cur = admin_current();
        echo '</main><nav class="tabbar" aria-label="Quick menu">';
        foreach (ADMIN_TABS as [$lab, $act, $d]) echo '<a href="/client/admin/' . ($act === 'dashboard' ? '' : '?a=' . $act) . '"' . ($cur === $act ? ' aria-current="page"' : '') . '><svg viewBox="0 0 24 24" aria-hidden="true"><path d="' . $d . '"/></svg>' . h($lab) . '</a>';
        echo '<button type="button" data-menu-open aria-controls="side" aria-expanded="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>More</button></nav></div></body></html>';
        return;
    }
    echo '</main></div></body></html>';
}

// ----- auth -----
$hash = setting('admin_hash', '');
if ($hash === '') {
    $codeFile = priv_dir() . '/setup-code.txt';
    if (!is_file($codeFile)) file_put_contents($codeFile, bin2hex(random_bytes(6)) . "\n");
    $err = '';
    if ($post && csrf_ok()) {
        $code = trim((string)file_get_contents($codeFile));
        if (throttle_blocked('setup')) $err = 'Too many tries. Wait 15 minutes and try again.';
        elseif (!hash_equals($code, trim((string)($_POST['code'] ?? '')))) { throttle_fail('setup'); $err = 'Setup code is wrong.'; usleep(700000); }
        elseif (strlen((string)($_POST['password'] ?? '')) < 10) $err = 'Use at least 10 characters.';
        else { throttle_clear('setup'); set_setting('admin_hash', password_hash((string)$_POST['password'], PASSWORD_DEFAULT)); @unlink($codeFile); $_SESSION['admin'] = true; go($base); }
    }
    ahead('Set up'); ?>
<div class="narrow"><h1>Set up</h1><p>Choose an admin password. The setup code is in the file <code>setup-code.txt</code> inside this site's private folder on the server.</p>
<form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><label>Setup code<input name="code" required></label><label>New password (10+ characters)<input type="password" name="password" required></label>
<?php if ($err): ?><p class="msg bad" role="alert"><?= h($err) ?></p><?php endif; ?><button class="btn">Save password</button></form></div>
<?php afoot(); exit;
}
if ($a === 'logout') { $_SESSION = []; session_destroy(); go($base); }
// The NAS watcher signs in with a secret key (header X-EA-Token) instead of a password; it can only use the two Publish actions
// and nasstatus (its status report, T-40).
$apiAuth = false;
if ($post && in_array($a, ['pubfile', 'pubdone', 'nasstatus'], true) && ($tk = (string)($_SERVER['HTTP_X_EA_TOKEN'] ?? '')) !== '') {
    $th = (string)setting('nas_token_hash', '');
    if ($th !== '' && hash_equals($th, hash('sha256', $tk))) $apiAuth = true; else { usleep(1000000); json_out(['error' => 'Bad key.'], 403); }
}
if (empty($_SESSION['admin']) && !$apiAuth) {
    $err = '';
    if ($post && csrf_ok()) {
        if (throttle_blocked('admin')) $err = 'Too many tries. Wait 15 minutes and try again.';
        elseif (password_verify((string)($_POST['password'] ?? ''), $hash)) { throttle_clear('admin'); session_regenerate_id(true); $_SESSION['admin'] = true; go($base); }
        else { throttle_fail('admin'); $err = 'Wrong password.'; usleep(1000000); }
    }
    ahead('Log in'); ?>
<div class="narrow"><h1>Log in</h1><form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><label>Password<input type="password" name="password" autofocus required></label>
<?php if ($err): ?><p class="msg bad" role="alert"><?= h($err) ?></p><?php endif; ?><button class="btn">Log in</button></form></div>
<?php afoot(); exit;
}

// ----- helpers -----
function gal(int $id): array { $s = db()->prepare('SELECT * FROM galleries WHERE id = ?'); $s->execute([$id]); $g = $s->fetch(); if (!$g) { http_response_code(404); echo 'Not found'; exit; } return $g; }
function link_for(array $g): string { return 'https://' . ($_SERVER['HTTP_HOST'] ?? '') . '/client/g/' . $g['token'] . '/'; }

// thumbnail preview for admin (works even for inactive galleries)
if ($a === 'thumb') {
    $s = db()->prepare('SELECT * FROM images WHERE id = ?'); $s->execute([(int)($_GET['id'] ?? 0)]); $i = $s->fetch();
    if (!$i) { http_response_code(404); exit; }
    // Order galleries (kind 'shop') keep unwatermarked copies in 'sthumb' and build them on first view.
    $gk = db()->prepare('SELECT kind FROM galleries WHERE id = ?'); $gk->execute([(int)$i['gallery_id']]);
    $wm = ($gk->fetchColumn() ?: '') !== 'shop';
    $p = gdir((int)$i['gallery_id'], ($wm ? '' : 's') . 'thumb') . '/' . basename($i['file']);
    if (!is_file($p)) $p = ensure_derivative((int)$i['gallery_id'], $i['file'], 'thumb', $wm);
    if ($p === '' || !is_file($p)) { http_response_code(404); exit; }
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

// ----- Publish photos (browser upload to the public galleries) -----
if (in_array($a, ['publish', 'pubfile', 'pubdone', 'nasstatus'], true)) require __DIR__ . '/publish.php';
if (in_array($a, ['library', 'libfile', 'libzip'], true)) require __DIR__ . '/library.php';
if ($a === 'dashboard') require __DIR__ . '/dashboard.php';
if (in_array($a, ['pgalleries', 'pgallery', 'pgsave', 'pgorder', 'pgremove'], true)) require __DIR__ . '/pgalleries.php';
if (in_array($a, ['series', 'seriessave', 'pages', 'pagesave', 'sitesave'], true)) require __DIR__ . '/content-editors.php';
if (in_array($a, ['links', 'linknew', 'linktoggle', 'linkdel'], true)) require __DIR__ . '/links.php';

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
        if ($st === 'paid') { require_once __DIR__ . '/../payments.php'; mark_order_paid((int)($_POST['order'] ?? 0), ''); }
        else db()->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$st, (int)($_POST['order'] ?? 0)]);
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
        go($base . '?a=settings&tab=drive&saved=1');
    }
    if ($a === 'nastoken') {
        if (!empty($_POST['revoke'])) set_setting('nas_token_hash', '');
        else { $k = bin2hex(random_bytes(24)); set_setting('nas_token_hash', hash('sha256', $k)); $_SESSION['new_nas_token'] = $k; }
        go($base . '?a=settings&tab=publishing#nas');
    }
    if ($a === 'codenew') {
        $name = trim(strip_tags((string)($_POST['name'] ?? '')));
        if ($name === '') go($base . '?a=codes&err=name');
        $c = strtolower(trim((string)($_POST['code'] ?? '')));
        if ($c === '') { $al = 'abcdefghjkmnpqrstuvwxyz23456789'; $c = ''; for ($k = 0; $k < 10; $k++) $c .= $al[random_int(0, strlen($al) - 1)]; $c = substr($c, 0, 5) . '-' . substr($c, 5); }
        if (strlen($c) < 6 || !preg_match('/^[a-z0-9-]+$/', $c)) go($base . '?a=codes&err=code');
        $ex = trim((string)($_POST['expires'] ?? '')); if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ex)) $ex = '';
        try { db()->prepare('INSERT INTO codes (name, code, expires, created) VALUES (?,?,?,?)')->execute([mb_substr($name, 0, 80), $c, $ex, time()]); }
        catch (Throwable $e) { go($base . '?a=codes&err=dup'); }
        go($base . '?a=codes&saved=1');
    }
    if ($a === 'codeedit') {
        $cid = (int)($_POST['cid'] ?? 0);
        $c = strtolower(trim((string)($_POST['code'] ?? '')));
        $name = trim(strip_tags((string)($_POST['name'] ?? '')));
        if ($name === '') go($base . '?a=codes&err=name');
        if (strlen($c) < 6 || !preg_match('/^[a-z0-9-]+$/', $c)) go($base . '?a=codes&err=code');
        $ex = trim((string)($_POST['expires'] ?? '')); if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ex)) $ex = '';
        try { db()->prepare('UPDATE codes SET name = ?, code = ?, expires = ? WHERE id = ?')->execute([mb_substr($name, 0, 80), $c, $ex, $cid]); }
        catch (Throwable $e) { go($base . '?a=codes&err=dup'); }
        go($base . '?a=codes&saved=1');
    }
    if ($a === 'codetoggle') {
        db()->prepare('UPDATE codes SET active = 1 - active WHERE id = ?')->execute([(int)($_POST['cid'] ?? 0)]);
        go($base . '?a=codes');
    }
    if ($a === 'githubsave') {
        if (trim((string)($_POST['github_token'] ?? '')) !== '') set_setting('github_token', trim((string)$_POST['github_token']));
        if (!empty($_POST['github_clear'])) set_setting('github_token', '');
        $r = trim((string)($_POST['github_repo'] ?? '')); set_setting('github_repo', preg_match('#^[\w.-]+/[\w.-]+$#', $r) ? $r : '');
        go($base . '?a=settings&tab=publishing&saved=1#github');
    }
    if ($a === 'paysave') {
        set_setting('stripe_enabled', !empty($_POST['stripe_enabled']) ? '1' : '0');
        foreach (['stripe_secret_key', 'stripe_webhook_secret'] as $k) if (trim((string)($_POST[$k] ?? '')) !== '') set_setting($k, trim((string)$_POST[$k]));
        set_setting('manual_pay_text', trim((string)($_POST['manual_pay_text'] ?? '')));
        set_setting('shop_enabled', !empty($_POST['shop_enabled']) ? '1' : '0');
        set_setting('shop_price_web_cents', (string)dollars_to_cents((string)($_POST['shop_price_web'] ?? '')));
        set_setting('shop_price_full_cents', (string)dollars_to_cents((string)($_POST['shop_price_full'] ?? '')));
        set_setting('shop_discount_min', (string)max(0, min(30, (int)($_POST['shop_discount_min'] ?? 0))));
        set_setting('shop_discount_pct', (string)max(0, min(90, (int)($_POST['shop_discount_pct'] ?? 0))));
        go($base . '?a=settings&tab=' . (($_POST['tab'] ?? '') === 'selling' ? 'selling' : 'payments') . '&saved=1');
    }
    if ($a === 'pwchange') {
        $to = $base . '?a=settings&tab=account';
        if (throttle_blocked('admin')) go($to . '&err=many');
        if (!password_verify((string)($_POST['current'] ?? ''), (string)setting('admin_hash', ''))) { throttle_fail('admin'); usleep(1000000); go($to . '&err=current'); }
        $new = (string)($_POST['new'] ?? '');
        if (mb_strlen($new) < 10) go($to . '&err=short');
        if (!hash_equals($new, (string)($_POST['new2'] ?? ''))) go($to . '&err=match');
        throttle_clear('admin'); set_setting('admin_hash', password_hash($new, PASSWORD_DEFAULT)); session_regenerate_id(true);
        go($to . '&pw=1');
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
<div class="ph"><div><p class="back"><?php if ($g['client_id']): ?><a href="?a=client&id=<?= (int)$g['client_id'] ?>">&larr; Back to this client's record</a><?php else: ?><a href="?a=list">&larr; All client galleries</a><?php endif; ?></p>
<h1><?= h($g['title']) ?></h1><?= $g['active'] ? '<span class="tag good">On</span>' : '<span class="tag wait">Off</span>' ?><?= $g['password_hash'] ? ' <span class="tag">Password</span>' : '' ?></div>
<div class="act"><a class="btn alt" target="_blank" href="/client/g/<?= h($g['token']) ?>/">Preview as client</a></div></div>
<?php if (!empty($_GET['saved'])): ?><p class="msg good" role="status">Saved.</p><?php endif; ?>
<p class="kicker">Client link (send this to <?= h($g['client_name'] ?: 'your client') ?>)<?= $g['password_hash'] ? ', password protected' : '' ?><?= $g['active'] ? '' : ' / currently turned OFF' ?></p>
<code class="link" id="lnk"><?= h(link_for($g)) ?></code>
<p><button type="button" class="btn sm alt" data-copy="#lnk">Copy link</button></p>

<h2>Upload photos</h2>
<div class="drop"><input type="file" id="files" accept="image/jpeg" multiple data-gid="<?= $gid ?>" data-csrf="<?= h($tok) ?>"><p id="prog" class="kicker">JPEGs only. Originals are stored privately; the client sees watermarked copies. Photos upload one at a time in filename order.</p></div>

<h2>Gallery settings</h2>
<form method="post" class="cols"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="update"><input type="hidden" name="id" value="<?= $gid ?>">
<label>Title<input name="title" value="<?= h($g['title']) ?>" required></label>
<label>Client name<input name="client_name" value="<?= h($g['client_name']) ?>"></label>
<label>Client email<input name="client_email" type="email" value="<?= h($g['client_email']) ?>"></label>
<label>Client record<select name="client_id"><option value="">None</option><?php foreach (db()->query('SELECT id, name, service FROM clients ORDER BY name') as $cr): ?><option value="<?= $cr['id'] ?>" <?= (int)$g['client_id'] === (int)$cr['id'] ? 'selected' : '' ?>><?= h($cr['name'] . ($cr['service'] ? ' (' . $cr['service'] . ')' : '')) ?></option><?php endforeach; ?></select></label>
<label>Included photos (free picks, 0 = none)<input name="included" type="number" min="0" value="<?= (int)$g['included_count'] ?>"></label>
<label>Extra photo, web size ($, 0 = Free)<input name="price_web" value="<?= h(number_format($g['default_web_cents'] / 100, 2)) ?>"></label>
<label>Extra photo, full resolution ($, 0 = Free)<input name="price" value="<?= h(number_format($g['default_price_cents'] / 100, 2)) ?>"></label>
<label>New password (leave blank to keep)<input name="password" type="text" autocomplete="off"></label>
<label class="check"><input type="checkbox" name="clear_pw" value="1"> Remove password: anyone with the link can open it</label>
<label class="full">Note shown to client<textarea name="note" rows="3"><?= h($g['note']) ?></textarea></label>
<label class="check"><input type="checkbox" name="active" value="1" <?= $g['active'] ? 'checked' : '' ?>> Gallery is on (uncheck to hide it)</label>
<div class="full"><button class="btn">Save settings</button></div></form>

<h2>Prices</h2>
<p><b>Web size</b> is a 2048 px copy for social sharing. <b>Full resolution</b> is the original file for printing. Photos in the client's included package get both for free. Each photo uses the gallery default unless you type its own price (0 = Free, blank = default).</p>
<form method="post" class="row"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="priceall"><input type="hidden" name="id" value="<?= $gid ?>">
<label>Set all photos<select name="tier"><option value="web">web size</option><option value="full">full resolution</option></select></label>
<label>to ($)<input name="price" placeholder="blank = default"></label>
<label class="check"><input type="checkbox" name="only_fav" value="1"> only favorites (<?= $nfav ?>)</label><button class="btn sm">Apply</button></form>
<?php $used = claims_used($gid); if ($g['included_count'] > 0): ?>
<p class="mt"><b>Package:</b> <?= $used ?> of <?= (int)$g['included_count'] ?> included photos used.
<form method="post" data-confirm="Clear all of this client&#39;s package picks?" class="acts"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="resetclaims"><input type="hidden" name="id" value="<?= $gid ?>"><button class="btn sm alt">Reset picks</button></form></p>
<?php endif; ?>

<h2><?= count($imgs) ?> photos</h2>
<div class="cards">
<?php foreach ($imgs as $i): ?>
<div class="cd"><img loading="lazy" src="<?= $base ?>?a=thumb&id=<?= $i['id'] ?>" alt="">
<small><?= h($i['label']) ?></small>
<div class="prices"><span>Web $</span><input value="<?= ($i['price_web_cents'] ?? null) === null ? '' : h(number_format($i['price_web_cents'] / 100, 2)) ?>" placeholder="<?= h(number_format($g['default_web_cents'] / 100, 2)) ?>" data-img="<?= $i['id'] ?>" data-tier="web" class="pr"><span>Full $</span><input value="<?= $i['price_cents'] === null ? '' : h(number_format($i['price_cents'] / 100, 2)) ?>" placeholder="<?= h(number_format($g['default_price_cents'] / 100, 2)) ?>" data-img="<?= $i['id'] ?>" data-tier="full" class="pr"></div>
<small><?= $i['fav'] ? '&#9829; favorite ' : '' ?><?= $i['claimed'] ? '[in package] ' : '' ?><?= $i['ncom'] ? $i['ncom'] . ' comments ' : '' ?><?= $i['dls'] ? $i['dls'] . ' downloads' : '' ?></small>
<form method="post" data-confirm="Delete this photo and its original?"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="delimg"><input type="hidden" name="id" value="<?= $gid ?>"><input type="hidden" name="image" value="<?= $i['id'] ?>"><button class="btn xs alt">Delete</button></form></div>
<?php endforeach; ?></div>

<?php
$o = db()->prepare('SELECT o.*, i.label FROM orders o JOIN images i ON i.id = o.image_id WHERE o.gallery_id = ? ORDER BY o.id DESC'); $o->execute([$gid]); $orders = $o->fetchAll();
$c = db()->prepare('SELECT c.*, i.label, i.id AS iid FROM comments c JOIN images i ON i.id = c.image_id WHERE i.gallery_id = ? ORDER BY c.id DESC'); $c->execute([$gid]); $coms = $c->fetchAll();
$fv = db()->prepare('SELECT i.label FROM favorites f JOIN images i ON i.id = f.image_id WHERE i.gallery_id = ? ORDER BY i.sort'); $fv->execute([$gid]); $favs = $fv->fetchAll(PDO::FETCH_COLUMN);
?>
<h2 id="orders">Photo requests</h2>
<?php if (!$orders): ?><p>None yet.</p><?php else: ?><table class="stack"><tr><th>Photo</th><th>Size</th><th>Email</th><th>Status</th><th></th></tr>
<?php foreach ($orders as $r): ?><tr><td data-label="Photo"><?= h($r['label']) ?></td><td data-label="Size"><?= $r['tier'] === 'web' ? 'Web' : 'Full' ?></td><td data-label="Email"><?= h($r['email']) ?></td><td data-label="Status"><span class="tag <?= ['requested' => 'wait', 'paid' => 'good', 'declined' => 'bad'][$r['status']] ?? '' ?>"><?= h($r['status']) ?></span></td><td><?php if ($r['status'] === 'requested'): ?>
<form method="post" class="acts"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="order"><input type="hidden" name="id" value="<?= $gid ?>"><input type="hidden" name="order" value="<?= $r['id'] ?>">
<button name="status" value="paid" class="btn sm">Mark paid (unlocks download)</button><button name="status" value="declined" class="btn sm alt">Decline</button></form><?php endif; ?></td></tr><?php endforeach; ?></table><?php endif; ?>

<h2>Favorites (<?= count($favs) ?>)</h2>
<p><?= $favs ? h(implode(', ', $favs)) : 'None yet.' ?></p>

<h2 id="comments">Comments</h2>
<?php if (!$coms): ?><p>None yet.</p><?php else: ?><table class="stack"><tr><th>Photo</th><th>Who</th><th>Comment</th><th>Reply</th></tr>
<?php foreach ($coms as $r): ?><tr><td data-label="Photo"><?= h($r['label']) ?></td><td data-label="Who"><?= h($r['from_owner'] ? 'You' : $r['author']) ?><br><small><?= date('M j, g:ia', (int)$r['created']) ?></small></td><td data-label="Comment"><?= nl2br(h($r['body'])) ?></td><td>
<?php if (!$r['from_owner']): ?><form method="post" class="row"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="reply"><input type="hidden" name="id" value="<?= $gid ?>"><input type="hidden" name="image" value="<?= $r['iid'] ?>"><input name="body" placeholder="Reply" required><button class="btn sm">Send</button></form><?php endif; ?></td></tr><?php endforeach; ?></table><?php endif; ?>

<h2>Delete gallery</h2>
<form method="post" data-confirm="This permanently deletes the gallery and all its photos. Continue?" class="row narrow"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="delgallery"><input type="hidden" name="id" value="<?= $gid ?>"><input name="confirm" placeholder="Type DELETE"><button class="btn sm alt">Delete</button></form>

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
<div class="ph"><div><h1>Clients</h1></div><div class="act"><a class="btn" href="#add">Add a client</a></div></div>
<p class="kicker">New-client form to send people</p>
<code class="link" id="ilnk"><?= h($intake) ?></code>
<p><button type="button" class="btn sm alt" data-copy="#ilnk">Copy link</button> <a class="btn sm alt" target="_blank" href="/client/new/">Open the form</a></p>
<div class="chips mt"><a class="tab <?= $stf === '' ? 'on' : '' ?>"<?= $stf === '' ? ' aria-current="page"' : '' ?> href="?a=clients">Active</a>
<?php foreach (CLIENT_STATUSES as $k => $lab): ?><a class="tab <?= $stf === $k ? 'on' : '' ?>"<?= $stf === $k ? ' aria-current="page"' : '' ?> href="?a=clients&status=<?= $k ?>"><?= h($lab) ?> <?= (int)($counts[$k] ?? 0) ?></a><?php endforeach; ?></div>
<form method="get" class="row narrow" role="search"><input type="hidden" name="a" value="clients"><input name="q" value="<?= h($q) ?>" placeholder="Search name, email or service" aria-label="Search clients"><button class="btn sm">Search</button></form>
<?php if (!$rows): ?><p>No clients here yet.</p><?php else: ?>
<table class="stack"><tr><th>Name</th><th>Service</th><th>Status</th><th>Timeframe</th><th>Galleries</th><th>Received</th></tr>
<?php foreach ($rows as $r): ?><tr><td><a href="?a=client&id=<?= $r['id'] ?>"><b><?= h($r['name']) ?></b></a><br><small><?= h($r['email']) ?></small></td><td data-label="Service"><?= h($r['service']) ?></td><td data-label="Status"><span class="tag"><?= h(CLIENT_STATUSES[$r['status']] ?? $r['status']) ?></span><?= $r['release_at'] ? ' <span class="tag good">release</span>' : '' ?></td><td data-label="Timeframe"><?= h($r['timeframe']) ?></td><td data-label="Galleries"><?= (int)$r['ng'] ?></td><td data-label="Received"><?= date('M j, Y', (int)$r['created']) ?><?= $r['source'] === 'admin' ? ' (added by you)' : '' ?></td></tr><?php endforeach; ?></table><?php endif; ?>
<h2 id="add">Add a client yourself</h2>
<form method="post" action="?a=clientsave" class="cols"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="clientsave"><input type="hidden" name="id" value="0">
<label>Name<input name="name" required></label><label>Email<input name="email" type="email"></label><label>Phone<input name="phone"></label>
<label>Service<select name="service"><option value="">Choose</option><?php foreach (services_list() as $sv): ?><option><?= h($sv['title']) ?></option><?php endforeach; ?><option>Not sure yet</option><option>Something else</option></select></label>
<label>Timeframe<input name="timeframe"></label><label>Notes (private)<input name="notes"></label>
<div class="full"><button class="btn">Add client</button></div></form>
<?php afoot(); exit;
}

if ($a === 'client') {
    $cs = db()->prepare('SELECT * FROM clients WHERE id = ?'); $cs->execute([(int)($_GET['id'] ?? 0)]); $c = $cs->fetch();
    if (!$c) { http_response_code(404); ahead('Not found'); echo '<p>Client not found. <a href="?a=clients">Back to clients</a></p>'; afoot(); exit; }
    $gs = db()->prepare('SELECT g.*, (SELECT COUNT(*) FROM images i WHERE i.gallery_id = g.id) AS n FROM galleries g WHERE g.client_id = ? ORDER BY g.id DESC'); $gs->execute([$c['id']]); $gals = $gs->fetchAll();
    $free = db()->query('SELECT id, title FROM galleries WHERE client_id IS NULL ORDER BY id DESC')->fetchAll();
    $inc = 0; foreach (services_list() as $sv) if ($sv['title'] === $c['service']) $inc = (int)$sv['included'];
    ahead($c['name']); $tok = csrf(); ?>
<div class="ph"><div><p class="back"><a href="?a=clients">&larr; All clients</a></p><h1><?= h($c['name']) ?></h1><span class="tag"><?= h(CLIENT_STATUSES[$c['status']] ?? $c['status']) ?></span></div>
<?php if ($c['email']): ?><div class="act"><a class="btn alt" href="mailto:<?= h($c['email']) ?>">Email <?= h($c['name']) ?></a></div><?php endif; ?></div>
<?php if (!empty($_GET['saved'])): ?><p class="msg good" role="status">Saved.</p><?php endif; ?>
<p class="kicker"><?= h(CLIENT_STATUSES[$c['status']] ?? '') ?> / <?= $c['source'] === 'admin' ? 'added by you' : 'sent the form' ?> on <?= date('M j, Y', (int)$c['created']) ?></p>

<h2>Galleries</h2>
<?php if (!$gals): ?><p>No galleries yet.</p><?php else: ?><table class="stack"><tr><th>Gallery</th><th>Photos</th><th>Package</th><th>Link</th></tr>
<?php foreach ($gals as $g): $url = link_for($g); ?><tr><td><a href="?a=gallery&id=<?= $g['id'] ?>"><b><?= h($g['title']) ?></b></a><?= $g['active'] ? '' : ' <span class="tag wait">off</span>' ?></td><td data-label="Photos"><?= (int)$g['n'] ?></td><td data-label="Package"><?= (int)$g['included_count'] ?> included</td>
<td><a href="mailto:<?= h($c['email']) ?>?subject=<?= rawurlencode('Your photos from Everett Allen Photography') ?>&body=<?= rawurlencode("Hi " . $c['name'] . ",\n\nYour private gallery is ready:\n" . $url . ($g['password_hash'] ? "\n\n(I'll send the password separately.)" : '') . "\n\nTap the heart on your favorites, and leave a comment on any photo.\n\nAndrew") ?>">Email link to client</a></td></tr><?php endforeach; ?></table><?php endif; ?>
<div class="row"><form method="post"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="makegallery"><input type="hidden" name="id" value="<?= $c['id'] ?>"><button class="btn sm">Create gallery for <?= h($c['name']) ?><?= $inc ? " ($inc photos included)" : '' ?></button></form>
<?php if ($free): ?><form method="post" class="row"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="linkgallery"><input type="hidden" name="client_id" value="<?= $c['id'] ?>">
<select name="gallery" aria-label="Gallery to link"><?php foreach ($free as $f): ?><option value="<?= $f['id'] ?>"><?= h($f['title']) ?></option><?php endforeach; ?></select><button class="btn sm alt">Link existing gallery</button></form><?php endif; ?></div>

<?php if ($c['release_at']): $ds = (string)$c['drive_status']; ?><h2>Photo release on file</h2>
<div class="panel <?= $c['id_verified'] !== '' ? 'good' : 'wait' ?>">
<b>Signed by <?= h($c['release_name']) ?></b><?= $c['release_stage'] ? ' (' . h($c['release_stage']) . ')' : '' ?> on <?= date('M j, Y g:ia', (int)$c['release_at']) ?><?= $c['discount_percent'] ? ', ' . (int)$c['discount_percent'] . '% session discount' : '' ?>. Gallery displays: <b><?= h($c['release_gallery'] ?: 'n/a') ?></b>. Born <?= h($c['release_dob']) ?>.<br>
<b>ID checked in person:</b> <?= $c['id_verified'] !== '' ? h($c['id_verified']) : '<span class="tag wait">not yet</span> Check ID at the session, then record it below.' ?><br>
<b>Google Drive:</b> <?= $ds === 'saved' ? '<span class="tag good">saved</span>' . ($c['drive_url'] ? ' (<a href="' . h($c['drive_url']) . '" target="_blank" rel="noopener">open</a>)' : '') : ($ds === '' ? 'not sent' : '<span class="tag bad">' . h($ds) . '</span>') ?>
<p class="mt"><a class="btn sm" href="?a=releasepdf&id=<?= $c['id'] ?>" target="_blank">View / download PDF</a></p>
<details class="mt"><summary>Exact wording they agreed to</summary><p class="pre"><?= h($c['release_text']) ?></p></details></div>
<form method="post" enctype="multipart/form-data" class="cols mt"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="releasesave"><input type="hidden" name="id" value="<?= $c['id'] ?>">
<label>Shoot date<input name="shoot_date" value="<?= h($c['shoot_date']) ?>" placeholder="11/15/2026"></label>
<label>Shoot location<input name="shoot_location" value="<?= h($c['shoot_location']) ?>" placeholder="Studio, Minneapolis MN"></label>
<label>ID checked in person (date, or leave blank)<input name="id_verified" value="<?= h($c['id_verified']) ?>" placeholder="11/15/2026"></label>
<div><label>Model photo (adds to the PDF)<input type="file" name="photo" accept="image/*"></label>
<button type="button" class="btn sm alt" data-cam="photo">Use camera</button> <span class="camnote" data-note="photo" role="status"></span>
<?php if ($c['photo_path'] !== '' && is_file($c['photo_path'])): ?><div><img class="prev" src="?a=releasephoto&k=model&id=<?= $c['id'] ?>" alt="Model photo on file"><label class="check"><input type="checkbox" name="remove_photo" value="1"> Remove this photo</label></div><?php endif; ?></div>
<div><label>ID photo (adds to the PDF, then keep it private)<input type="file" name="idphoto" accept="image/*"></label>
<button type="button" class="btn sm alt" data-cam="idphoto">Use camera</button> <span class="camnote" data-note="idphoto" role="status"></span>
<?php if ($c['idphoto_path'] !== '' && is_file($c['idphoto_path'])): ?><div><img class="prev" src="?a=releasephoto&k=id&id=<?= $c['id'] ?>" alt="ID photo on file"><label class="check"><input type="checkbox" name="remove_idphoto" value="1"> Remove this photo</label></div><?php endif; ?></div>
<label class="check"><input type="checkbox" name="to_drive" value="1" checked> Also save the updated PDF to Google Drive</label>
<div class="full"><button class="btn sm">Update release PDF</button></div></form>
<dialog id="camdlg" class="cam" aria-labelledby="camtitle">
<p id="camtitle" class="t"></p>
<video id="camvid" autoplay playsinline muted></video>
<p id="camerr" class="err" role="alert"></p>
<p class="row"><button type="button" class="btn sm" id="camsnap">Take photo</button><button type="button" class="btn sm alt" id="camcancel">Cancel</button></p>
</dialog>
<?php if ($ds !== 'saved'): ?><form method="post" class="mt"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="driveretry"><input type="hidden" name="id" value="<?= $c['id'] ?>"><button class="btn sm alt">Save to Google Drive now</button></form><?php endif; ?>
<?php elseif ($c['share_pref']): ?><p class="kicker">Sharing preference: <?= h($c['share_pref']) ?> (no signed release on file)</p><?php endif; ?>
<h2>Details</h2>
<form method="post" class="cols"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="clientsave"><input type="hidden" name="id" value="<?= $c['id'] ?>">
<label>Name<input name="name" value="<?= h($c['name']) ?>" required></label><label>Pronouns<input name="pronouns" value="<?= h($c['pronouns']) ?>"></label>
<label>Email<input name="email" type="email" value="<?= h($c['email']) ?>"></label><label>Phone<input name="phone" value="<?= h($c['phone']) ?>"></label>
<label>Service<input name="service" value="<?= h($c['service']) ?>" list="svl"><datalist id="svl"><?php foreach (services_list() as $sv): ?><option value="<?= h($sv['title']) ?>"><?php endforeach; ?></datalist></label>
<label>Status<select name="status"><?php foreach (CLIENT_STATUSES as $k => $lab): ?><option value="<?= $k ?>" <?= $c['status'] === $k ? 'selected' : '' ?>><?= h($lab) ?></option><?php endforeach; ?></select></label>
<label>Timeframe<input name="timeframe" value="<?= h($c['timeframe']) ?>"></label><label>Location ideas<input name="location" value="<?= h($c['location']) ?>"></label>
<label>Instagram or website<input name="handle" value="<?= h($c['handle']) ?>"></label><label>Heard about me from<input name="referral" value="<?= h($c['referral']) ?>"></label>
<label>May share photos publicly?<input name="share_pref" value="<?= h($c['share_pref']) ?>" list="spl"><datalist id="spl"><option value="Yes"><option value="Ask first"><option value="Private"></datalist></label>
<label>Session discount (%)<input name="discount_percent" type="number" min="0" max="100" value="<?= (int)$c['discount_percent'] ?>"></label>
<label class="full">What they wrote<textarea name="message" rows="4"><?= h($c['message']) ?></textarea></label>
<label class="full">Your private notes (clients never see these)<textarea name="notes" rows="4"><?= h($c['notes']) ?></textarea></label>
<div class="full"><button class="btn">Save</button></div></form>

<h2>Delete client</h2>
<p>Removes this record only. Their galleries stay and become unlinked.</p>
<form method="post" data-confirm="Delete this client record?" class="row narrow"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="clientdel"><input type="hidden" name="id" value="<?= $c['id'] ?>"><input name="confirm" placeholder="Type DELETE"><button class="btn sm alt">Delete</button></form>
<?php afoot(); exit;
}

if ($a === 'stats') {
    ahead('Stats');
    $days = in_array((int)($_GET['days'] ?? 30), [7, 30, 90, 365], true) ? (int)($_GET['days'] ?? 30) : 30;
    $tz = new DateTimeZone('America/Chicago');
    $from = (new DateTime('now', $tz))->modify('-' . ($days - 1) . ' days')->format('Y-m-d');
    $q = db()->prepare('SELECT day, hits, visitors FROM stat_days WHERE day >= ?'); $q->execute([$from]);
    $byday = []; foreach ($q->fetchAll() as $r) $byday[$r['day']] = $r;
    $series = []; $dt = new DateTime($from, $tz); $maxv = 1; $tv = 0; $th = 0;
    for ($i = 0; $i < $days; $i++) { $d = $dt->format('Y-m-d'); $r = $byday[$d] ?? ['hits' => 0, 'visitors' => 0]; $series[] = [$d, (int)$r['hits'], (int)$r['visitors']]; $maxv = max($maxv, (int)$r['hits']); $th += (int)$r['hits']; $tv += (int)$r['visitors']; $dt->modify('+1 day'); }
    $top = function (string $col, string $where = '', int $lim = 15) use ($from) { $s = db()->prepare("SELECT $col AS k, SUM(hits) AS n FROM stat_hits WHERE day >= ? $where GROUP BY $col ORDER BY n DESC LIMIT $lim"); $s->execute([$from]); return $s->fetchAll(); };
    $pages = $top('path', '', 20); $refs = $top('ref', "AND ref <> ''", 15); $devs = $top('dev', '', 5);
    $tbl = function (array $rows, string $head) { echo '<table class="form"><tr><th>' . h($head) . '</th><th class="num">Views</th></tr>'; foreach ($rows as $r) echo '<tr><td class="brk">' . h($r['k']) . '</td><td class="num">' . (int)$r['n'] . '</td></tr>'; if (!$rows) echo '<tr><td colspan="2">Nothing yet.</td></tr>'; echo '</table>'; };
    ?>
<div class="ph"><div><h1>Stats</h1></div></div>
<p>Page views and daily visitors for the public site. No cookies, no IP addresses or browser details are stored, and your own visits are not counted in this browser.</p>
<div class="chips"><?php foreach ([7, 30, 90, 365] as $d): ?><a class="tab<?= $d === $days ? ' on" aria-current="page' : '' ?>" href="?a=stats&days=<?= $d ?>"><?= $d ?> days</a><?php endforeach; ?></div>
<p class="lead"><b><?= $th ?></b> page views, <b><?= $tv ?></b> visitor-days (a person who visits on 3 different days counts 3 times)</p>
<svg class="chart" viewBox="0 0 <?= $days * 10 ?> 140" preserveAspectRatio="none" role="img" aria-label="Page views per day">
<?php foreach ($series as $k => [$d, $hv, $vv]): $bh = max(1, (int)round(130 * $hv / $maxv)); ?><rect x="<?= $k * 10 + 1 ?>" y="<?= 140 - $bh ?>" width="8" height="<?= $bh ?>"<?= $hv ? '' : ' class="z"' ?>><title><?= h($d) ?>: <?= $hv ?> views, <?= $vv ?> visitors</title></rect><?php endforeach; ?>
</svg>
<p class="axis"><span><?= h($series[0][0]) ?></span><span><?= h(end($series)[0]) ?></span></p>
<h2>Top pages</h2><?php $tbl($pages, 'Page'); ?>
<h2>Where visitors came from</h2><p>Sites that sent people here. "direct" means typed the address, used a bookmark, or came from an app that hides it.</p><?php $tbl($refs, 'Source'); ?>
<h2>Devices</h2><?php $tbl($devs, 'Device'); ?>
<?php     $tl = (int)db()->query('SELECT COALESCE(SUM(n),0) FROM photo_likes')->fetchColumn();
    $top = db()->query('SELECT g, id, n FROM photo_likes WHERE n > 0 ORDER BY n DESC, g, id LIMIT 12')->fetchAll();
    $gt = is_file(dirname(__DIR__) . '/gallery-titles.json') ? (json_decode((string)file_get_contents(dirname(__DIR__) . '/gallery-titles.json'), true) ?: []) : []; ?>
<h2>Photo likes</h2>
<p><?= $tl ?> like<?= $tl === 1 ? '' : 's' ?> in total.</p>
<?php if ($top): ?><table class="stack"><tr><th>Photo</th><th>Gallery</th><th>Likes</th></tr>
<?php foreach ($top as $r): ?><tr><td><a href="/photo/<?= h($r['g']) ?>/<?= h($r['id']) ?>/" target="_blank"><img class="thumb" src="/photos/<?= h($r['g']) ?>/<?= h($r['id']) ?>-t.jpg" alt=""> <?= h($r['id']) ?></a></td><td data-label="Gallery"><?= h($gt[$r['g']] ?? $r['g']) ?></td><td data-label="Likes"><?= (int)$r['n'] ?></td></tr><?php endforeach; ?></table><?php endif; ?>
<?php
afoot(); exit;
}

if ($a === 'codescsv') {
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="code-uses.csv"');
    $o = fopen('php://output', 'w'); fputcsv($o, ['Code name', 'Date', 'Email', 'Gallery', 'Photo', 'Downloaded']);
    $q = db()->query("SELECT c.name cname, g.created, g.client_email, g.title, i.label, i.id iid, (SELECT COUNT(*) FROM downloads d WHERE d.image_id = i.id) dl FROM galleries g JOIN codes c ON c.id = g.code_id JOIN images i ON i.gallery_id = g.id ORDER BY g.created DESC, i.sort");
    foreach ($q as $r) fputcsv($o, [$r['cname'], date('Y-m-d H:i', (int)$r['created']), $r['client_email'], $r['title'], $r['label'], $r['dl'] > 0 ? 'yes' : 'no']);
    exit;
}
if ($a === 'codes') {
    ahead('Codes'); $tok = csrf();
    $rows = db()->query('SELECT * FROM codes ORDER BY id DESC')->fetchAll(); ?>
<div class="ph"><div><h1>Free download codes</h1></div></div>
<p>Give someone a code and they can get photos from the public galleries with no payment: add photos to the order, open the cart, click "Have a code?". Each code is logged with who used it, which photos, and whether they downloaded them. Turn a code off any time without affecting the others.</p>
<?php if (!empty($_GET['saved'])): ?><p class="msg good" role="status">Saved.</p><?php endif; ?>
<?php $errs = ['name' => 'Give the code a name.', 'code' => 'A code needs 6 or more letters, numbers or dashes.', 'dup' => 'That code already exists.']; if (!empty($errs[$_GET['err'] ?? ''])): ?><p class="msg bad" role="alert"><?= h($errs[$_GET['err']]) ?></p><?php endif; ?>
<form method="post" class="form"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="codenew">
<div class="cols"><label>Who it is for (example: Q Minnesota)<input name="name" required></label>
<label>Code (leave blank to make one)<input name="code" autocomplete="off" placeholder="made for you"></label>
<label>Stops working after (optional)<input name="expires" type="date"></label></div>
<button class="btn">Add code</button></form>
<?php if ($rows): ?><p class="hint mt">The link opens the Events page with the code applied. To land on a specific gallery instead, put <code>?code=...</code> on the end of that gallery's address.</p><h2>Codes</h2><p><a href="?a=codescsv">Download everything as a spreadsheet (CSV)</a></p><?php endif; ?>
<?php foreach ($rows as $c):
    $u = db()->prepare("SELECT g.id, g.created, g.client_email, g.title, (SELECT COUNT(*) FROM images i WHERE i.gallery_id = g.id) n, (SELECT COUNT(DISTINCT d.image_id) FROM downloads d JOIN images i ON i.id = d.image_id WHERE i.gallery_id = g.id) dl, (SELECT GROUP_CONCAT(i.label, ', ') FROM images i WHERE i.gallery_id = g.id) labels FROM galleries g WHERE g.code_id = ? ORDER BY g.created DESC");
    $u->execute([$c['id']]); $uses = $u->fetchAll();
    $np = array_sum(array_column($uses, 'n')); $nd = array_sum(array_column($uses, 'dl'));
    $exp = ($c['expires'] ?? '') !== '' && $c['expires'] < date('Y-m-d'); ?>
<section class="panel"><h3><?= h($c['name']) ?> <span class="tag <?= $c['active'] ? ($exp ? 'wait' : 'good') : '' ?>"><?= $c['active'] ? ($exp ? 'expired' : 'on') : 'off' ?></span></h3>
<p>Code: <span class="link inl"><?= h($c['code']) ?></span><?= ($c['expires'] ?? '') !== '' ? ' &nbsp; until ' . h($c['expires']) : '' ?> &nbsp;
<?= count($uses) ?> use<?= count($uses) === 1 ? '' : 's' ?>, <?= (int)$np ?> photo<?= $np === 1 ? '' : 's' ?>, <?= (int)$nd ?> downloaded</p>
<?php $lh = preg_replace('/^new\./', 'www.', (string)($_SERVER['HTTP_HOST'] ?? '')); $link = 'https://' . $lh . '/events/?code=' . rawurlencode($c['code']); ?>
<p>Link to send: <span class="link inl"><?= h($link) ?></span> <button type="button" class="btn sm" data-copy-text="<?= h($link) ?>">Copy link</button></p>
<form method="post"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="codetoggle"><input type="hidden" name="cid" value="<?= (int)$c['id'] ?>"><button class="btn sm alt"><?= $c['active'] ? 'Turn off' : 'Turn on' ?></button></form>
<details class="mt"><summary>Change name, code or end date</summary>
<form method="post" class="form mt"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="codeedit"><input type="hidden" name="cid" value="<?= (int)$c['id'] ?>">
<div class="cols"><label>Name<input name="name" value="<?= h($c['name']) ?>" required></label><label>Code<input name="code" value="<?= h($c['code']) ?>" autocomplete="off" required></label><label>Stops working after<input name="expires" type="date" value="<?= h((string)$c['expires']) ?>"></label></div>
<button class="btn">Save changes</button></form></details>
<?php if ($uses): ?><table class="stack mt"><tr><th>When</th><th>Email</th><th>Photos</th><th>Downloaded</th></tr>
<?php foreach ($uses as $r): ?><tr><td data-label="When"><?= h(date('M j, Y g:ia', (int)$r['created'])) ?></td><td data-label="Email"><?= h($r['client_email']) ?></td><td data-label="Photos"><?= (int)$r['n'] ?>: <?= h(mb_strimwidth((string)$r['labels'], 0, 90, '...')) ?></td><td data-label="Downloaded"><?= (int)$r['dl'] ?> of <?= (int)$r['n'] ?></td></tr><?php endforeach; ?></table><?php endif; ?></section>
<?php endforeach; ?>
<?php afoot(); exit;
}

if ($a === 'settings') {
    ahead('Settings'); $tok = csrf();
    $tabs = ['payments' => 'Payments', 'selling' => 'Selling', 'publishing' => 'Publishing', 'drive' => 'Google Drive', 'account' => 'Account'];
    $tab = (string)($_GET['tab'] ?? ''); if (!isset($tabs[$tab])) $tab = 'payments';
    $pwerrs = ['current' => 'The current password is wrong.', 'short' => 'The new password needs 10 or more characters.', 'match' => 'The two new passwords do not match.', 'many' => 'Too many tries. Wait 15 minutes and try again.'];
    $sk = (string)setting('stripe_secret_key', '');
    $money = fn(string $k) => ((int)setting($k, '0')) > 0 ? number_format(((int)setting($k, '0')) / 100, 2, '.', '') : '';
    $secret = fn(string $name, string $hint = '') => '<span class="secret"><input name="' . $name . '" type="password" autocomplete="off" spellcheck="false"' . ($hint ? ' placeholder="' . h($hint) . '"' : '') . '><button type="button" class="btn sm alt" data-show aria-pressed="false">Show</button></span>'; ?>
<div class="ph"><div><h1>Settings</h1></div></div>
<nav class="chips" aria-label="Settings sections"><?php foreach ($tabs as $k => $lab): ?><a class="tab<?= $k === $tab ? ' on" aria-current="page' : '' ?>" href="?a=settings&tab=<?= $k ?>"><?= h($lab) ?></a><?php endforeach; ?></nav>
<?php if (!empty($_GET['saved'])): ?><p class="msg good" role="status">Saved.</p><?php endif; ?>
<?php if ($tab === 'payments' || $tab === 'selling'): ?>
<form method="post" class="form"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="paysave"><input type="hidden" name="tab" value="<?= $tab ?>">
<?php if ($tab === 'payments'): ?>
<h2>Payments</h2>
<p>Clients pay with Stripe on a secure Stripe page. If Stripe is switched off, not set up, or has a problem, the site automatically uses the manual flow instead: you get an email, the client gets your payment instructions, and you click "Mark paid" in the gallery.</p>
<label class="check"><input type="checkbox" name="stripe_enabled" value="1" <?= setting('stripe_enabled', '') === '1' ? 'checked' : '' ?>> Use Stripe checkout (uncheck to pause it and use the manual flow)</label>
<label>Stripe secret key <?= $sk !== '' ? '(saved: ' . h(substr($sk, 0, 8)) . '..., ' . (strpos($sk, '_test_') !== false ? 'TEST mode' : 'live mode') . '; type a new one to replace it)' : '(starts with sk_live_ or sk_test_, or rk_ for a restricted key)' ?><?= $secret('stripe_secret_key') ?></label>
<label>Stripe webhook signing secret <?= setting('stripe_webhook_secret', '') ? '(saved; type a new one to replace it)' : '(starts with whsec_)' ?><?= $secret('stripe_webhook_secret') ?></label>
<p class="hint">In Stripe, add a webhook endpoint with this address, listening for <b>checkout.session.completed</b>:<br><code>https://<?= h($_SERVER['HTTP_HOST'] ?? '') ?>/client/stripe-webhook.php</code></p>
<label>How to pay manually (emailed to clients when they send a request without Stripe)<textarea name="manual_pay_text" rows="4" placeholder="Venmo @yourname, or Zelle to you@example.com. Please put your gallery name in the note."><?= h((string)setting('manual_pay_text', '')) ?></textarea></label>
<?php /* this form also saves the Selling fields, so they ride along unchanged */ ?>
<?php if (setting('shop_enabled', '') === '1'): ?><input type="hidden" name="shop_enabled" value="1"><?php endif; ?>
<input type="hidden" name="shop_price_web" value="<?= h($money('shop_price_web_cents')) ?>"><input type="hidden" name="shop_price_full" value="<?= h($money('shop_price_full_cents')) ?>">
<input type="hidden" name="shop_discount_min" value="<?= (int)setting('shop_discount_min', '0') ?>"><input type="hidden" name="shop_discount_pct" value="<?= (int)setting('shop_discount_pct', '0') ?>">
<?php else: ?>
<h2>Selling from public galleries</h2>
<p>Lets visitors buy photos from any public gallery (single photos or a cart). Needs the full-size originals uploaded with the publish script. Prices are per photo.</p>
<label class="check"><input type="checkbox" name="shop_enabled" value="1" <?= setting('shop_enabled', '') === '1' ? 'checked' : '' ?>> Show "Buy" on public gallery photos</label>
<label>Web size price (2048px, for social media)<input name="shop_price_web" value="<?= h($money('shop_price_web_cents')) ?>" placeholder="5.00" inputmode="decimal"></label>
<label>Full size price (original, for printing)<input name="shop_price_full" value="<?= h($money('shop_price_full_cents')) ?>" placeholder="25.00" inputmode="decimal"></label>
<label>Multi-photo discount: starts at this many photos (leave 0 for no discount)<input name="shop_discount_min" value="<?= h((string)(int)setting('shop_discount_min', '0')) ?>" inputmode="numeric" placeholder="5"></label>
<label>Discount percent off every photo in the order<input name="shop_discount_pct" value="<?= h((string)(int)setting('shop_discount_pct', '0')) ?>" inputmode="numeric" placeholder="20"></label>
<?php /* this form also saves the Payments fields, so they ride along unchanged (blank secret fields keep the saved keys) */ ?>
<?php if (setting('stripe_enabled', '') === '1'): ?><input type="hidden" name="stripe_enabled" value="1"><?php endif; ?>
<input type="hidden" name="manual_pay_text" value="<?= h((string)setting('manual_pay_text', '')) ?>">
<?php endif; ?>
<button class="btn"><?= $tab === 'payments' ? 'Save payment settings' : 'Save selling settings' ?></button></form>
<?php elseif ($tab === 'publishing'): ?>
<h2 id="github">Publishing from the browser (GitHub)</h2>
<p>The <a href="?a=publish">Publish</a> page saves each new gallery to your GitHub repository so the site rebuilds. Paste a fine-grained GitHub token here that can only edit the contents of that one repository.</p>
<form method="post" class="form"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="githubsave">
<label>GitHub token <?= setting('github_token', '') ? '(saved; type a new one to replace it)' : '(starts with github_pat_)' ?><?= $secret('github_token') ?></label>
<label>Repository<input name="github_repo" value="<?= h((string)setting('github_repo', '') ?: 'drewanmpls/everettallen-site') ?>"></label>
<?php if (setting('github_token', '')): ?><label class="check"><input type="checkbox" name="github_clear" value="1"> Remove the saved token</label><?php endif; ?>
<button class="btn">Save GitHub settings</button></form>
<h2 id="nas">Folder watcher key (NAS)</h2>
<p>A small program on your NAS uses this key to publish photos to the site. Making a new key replaces the old one. Copy it right away, it is only shown once.</p>
<?php if (!empty($_SESSION['new_nas_token'])): ?><p class="msg good" role="status">Your new key (copy it now):</p><span class="link"><?= h($_SESSION['new_nas_token']) ?></span><?php unset($_SESSION['new_nas_token']); endif; ?>
<form method="post" class="form"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="nastoken">
<p><?= setting('nas_token_hash', '') ? 'A key is active.' : 'No key yet.' ?></p>
<div class="acts"><button class="btn"><?= setting('nas_token_hash', '') ? 'Make a new key' : 'Make a key' ?></button>
<?php if (setting('nas_token_hash', '')): ?><button class="btn alt" name="revoke" value="1">Turn the key off</button><?php endif; ?></div></form>
<?php elseif ($tab === 'drive'): ?>
<h2>Save signed releases to Google Drive</h2>
<p>Paste the web app address from your Google Apps Script, and the secret word you put in that script. Releases are then filed into your Drive folder automatically when a client signs.</p>
<form method="post" class="form"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="settingssave">
<label>Web app address<input name="drive_webhook_url" value="<?= h((string)setting('drive_webhook_url', '')) ?>" placeholder="https://script.google.com/macros/s/.../exec"></label>
<label>Secret word <?= setting('drive_webhook_secret', '') ? '(already saved, type a new one to replace it)' : '' ?><?= $secret('drive_webhook_secret') ?></label>
<button class="btn">Save Google Drive settings</button></form>
<?php else: ?>
<h2>Change password</h2>
<?php if (!empty($_GET['pw'])): ?><p class="msg good" role="status">Password changed.</p><?php endif; ?>
<?php if (isset($pwerrs[$_GET['err'] ?? ''])): ?><p class="msg bad" role="alert"><?= h($pwerrs[$_GET['err']]) ?></p><?php endif; ?>
<form method="post" class="form"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="pwchange">
<input type="text" name="username" value="admin" autocomplete="username" hidden>
<label>Current password<input name="current" type="password" autocomplete="current-password" required></label>
<label>New password (10 or more characters)<input name="new" type="password" autocomplete="new-password" minlength="10" required></label>
<label>New password again<input name="new2" type="password" autocomplete="new-password" minlength="10" required></label>
<button class="btn">Change password</button></form>
<h2>Appearance</h2>
<form class="form"><label for="eaTheme2">Light or dark (saved in this browser only)<select id="eaTheme2" data-theme><option value="auto">Auto (match device)</option><option value="light">Light</option><option value="dark">Dark</option></select></label></form>
<?php endif; ?>
<?php afoot(); exit;
}

// list
$rows = db()->query('SELECT g.*, (SELECT COUNT(*) FROM images i WHERE i.gallery_id=g.id) AS n, (SELECT COUNT(*) FROM favorites f JOIN images i ON i.id=f.image_id WHERE i.gallery_id=g.id) AS fav, (SELECT COUNT(*) FROM comments c JOIN images i ON i.id=c.image_id WHERE i.gallery_id=g.id AND c.from_owner=0) AS com, (SELECT COUNT(*) FROM orders o WHERE o.gallery_id=g.id AND o.status=\'requested\') AS req FROM galleries g WHERE g.kind <> \'shop\' ORDER BY g.id DESC')->fetchAll();
ahead('Galleries'); $tok = csrf(); ?>
<div class="ph"><div><h1>Client galleries</h1></div><div class="act"><a class="btn" href="#new">New gallery</a></div></div>
<?php if (!$rows): ?><p>No galleries yet. Create your first below.</p><?php else: ?>
<table class="stack"><tr><th>Gallery</th><th>Client</th><th>Photos</th><th>Favorites</th><th>Comments</th><th>Open requests</th><th></th></tr>
<?php foreach ($rows as $r): ?><tr><td><a href="?a=gallery&id=<?= $r['id'] ?>"><b><?= h($r['title']) ?></b></a><?= $r['active'] ? '' : ' <span class="tag wait">off</span>' ?></td><td data-label="Client"><?= h($r['client_name']) ?></td><td data-label="Photos"><?= $r['n'] ?></td><td data-label="Favorites"><?= $r['fav'] ?></td><td data-label="Comments"><?= $r['com'] ?></td><td data-label="Open requests"><?= $r['req'] ? '<span class="tag wait">' . $r['req'] . '</span>' : '0' ?></td><td><a href="?a=gallery&id=<?= $r['id'] ?>">Manage</a></td></tr><?php endforeach; ?></table><?php endif; ?>
<?php $shop = db()->query("SELECT g.*, (SELECT COUNT(*) FROM images i WHERE i.gallery_id=g.id) AS n, (SELECT COUNT(*) FROM orders o WHERE o.gallery_id=g.id AND o.status='paid') AS paid FROM galleries g WHERE g.kind = 'shop' ORDER BY g.id DESC LIMIT 100")->fetchAll(); if ($shop): ?>
<h2>Photo orders from the public galleries</h2>
<table class="stack"><tr><th>Buyer</th><th>Photos</th><th>Paid</th><th>Placed</th><th></th></tr>
<?php foreach ($shop as $r): ?><tr><td><?= h($r['client_email']) ?></td><td data-label="Photos"><?= (int)$r['n'] ?></td><td data-label="Paid"><?= $r['paid'] ? '<span class="tag good">paid</span>' : '<span class="tag wait">not yet</span>' ?></td><td data-label="Placed"><?= date('M j, Y g:ia', (int)$r['created']) ?></td><td><a href="?a=gallery&id=<?= $r['id'] ?>">Manage</a></td></tr><?php endforeach; ?></table>
<?php endif; ?>
<h2 id="new">New gallery</h2>
<form method="post" class="cols"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="create">
<label>Title<input name="title" required placeholder="Jordan and Sam, spring session"></label>
<label>Client name<input name="client_name"></label>
<label>Client email<input name="client_email" type="email"></label>
<label>Included photos (free picks)<input name="included" type="number" min="0" value="0"></label>
<label>Extra photo, web size ($, 0 = Free)<input name="price_web" value="0"></label>
<label>Extra photo, full resolution ($, 0 = Free)<input name="price" value="0"></label>
<label>Password (optional)<input name="password" autocomplete="off"></label>
<label class="full">Note shown to client<textarea name="note" rows="2"></textarea></label>
<div class="full"><button class="btn">Create gallery</button></div></form>
<?php afoot();
