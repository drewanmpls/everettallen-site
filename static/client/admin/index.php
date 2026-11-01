<?php
// Admin for private client galleries. Only Everett uses this.
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
<header class="top"><a class="brand" href="/client/admin/">Gallery admin</a><span><a href="/client/admin/?a=logout" onclick="return true">Log out</a></span></header><div class="adm">
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

// ----- POST actions -----
if ($post) {
    if (!csrf_ok()) { http_response_code(400); echo 'Session expired. Go back, reload the page and try again.'; exit; }
    $id = (int)($_POST['id'] ?? 0);

    if ($a === 'create') {
        $title = trim((string)($_POST['title'] ?? '')); if ($title === '') go($base);
        $pw = (string)($_POST['password'] ?? '');
        db()->prepare('INSERT INTO galleries (token, title, client_name, client_email, password_hash, default_price_cents, note, created) VALUES (?,?,?,?,?,?,?,?)')
            ->execute([bin2hex(random_bytes(12)), $title, trim((string)$_POST['client_name']), trim((string)$_POST['client_email']), $pw !== '' ? password_hash($pw, PASSWORD_DEFAULT) : '', dollars_to_cents((string)($_POST['price'] ?? '0')), trim((string)$_POST['note']), time()]);
        go($base . '?a=gallery&id=' . db()->lastInsertId());
    }
    if ($a === 'update') {
        $g = gal($id);
        $pwHash = $g['password_hash'];
        if (!empty($_POST['clear_pw'])) $pwHash = ''; elseif ((string)($_POST['password'] ?? '') !== '') $pwHash = password_hash((string)$_POST['password'], PASSWORD_DEFAULT);
        db()->prepare('UPDATE galleries SET title=?, client_name=?, client_email=?, password_hash=?, default_price_cents=?, note=?, active=? WHERE id=?')
            ->execute([trim((string)$_POST['title']) ?: $g['title'], trim((string)$_POST['client_name']), trim((string)$_POST['client_email']), $pwHash, dollars_to_cents((string)($_POST['price'] ?? '0')), trim((string)$_POST['note']), empty($_POST['active']) ? 0 : 1, $id]);
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
        db()->prepare('UPDATE images SET price_cents = ? WHERE id = ?')->execute([$v === '' ? null : dollars_to_cents($v), (int)($_POST['image'] ?? 0)]);
        json_out(['ok' => true]);
    }
    if ($a === 'priceall') {
        $v = trim((string)($_POST['price'] ?? ''));
        if (!empty($_POST['only_fav'])) db()->prepare('UPDATE images SET price_cents = ? WHERE gallery_id = ? AND id IN (SELECT image_id FROM favorites)')->execute([$v === '' ? null : dollars_to_cents($v), $id]);
        else db()->prepare('UPDATE images SET price_cents = ? WHERE gallery_id = ?')->execute([$v === '' ? null : dollars_to_cents($v), $id]);
        go($base . '?a=gallery&id=' . $id . '&saved=1');
    }
    if ($a === 'delimg') {
        $s = db()->prepare('SELECT * FROM images WHERE id = ?'); $s->execute([(int)($_POST['image'] ?? 0)]); $i = $s->fetch();
        if ($i) { foreach (['orig', 'display', 'thumb'] as $k) @unlink(gdir((int)$i['gallery_id'], $k) . '/' . basename($i['file'])); db()->prepare('DELETE FROM images WHERE id = ?')->execute([$i['id']]); }
        go($base . '?a=gallery&id=' . $id);
    }
    if ($a === 'reply') {
        $body = trim((string)($_POST['body'] ?? ''));
        if ($body !== '') db()->prepare('INSERT INTO comments (image_id, author, body, from_owner, created) VALUES (?,?,?,1,?)')->execute([(int)$_POST['image'], 'Everett', $body, time()]);
        go($base . '?a=gallery&id=' . $id . '#comments');
    }
    if ($a === 'order') {
        $st = in_array($_POST['status'] ?? '', ['paid', 'requested', 'declined'], true) ? $_POST['status'] : 'requested';
        db()->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$st, (int)($_POST['order'] ?? 0)]);
        go($base . '?a=gallery&id=' . $id . '#orders');
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
    $q = db()->prepare('SELECT i.*, (SELECT COUNT(*) FROM favorites f WHERE f.image_id = i.id) AS fav, (SELECT COUNT(*) FROM comments c WHERE c.image_id = i.id) AS ncom, (SELECT COUNT(*) FROM downloads d WHERE d.image_id = i.id) AS dls FROM images i WHERE i.gallery_id = ? ORDER BY i.sort, i.id'); $q->execute([$gid]);
    $imgs = $q->fetchAll();
    $nfav = count(array_filter($imgs, fn($i) => $i['fav'] > 0));
    ahead($g['title']); $tok = csrf(); ?>
<h1 style="font-size:clamp(32px,5vw,64px)"><?= h($g['title']) ?></h1>
<?php if (!empty($_GET['saved'])): ?><p class="ok">Saved.</p><?php endif; ?>
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
<label>Default price per photo ($, 0 = Free)<input name="price" value="<?= h(number_format($g['default_price_cents'] / 100, 2)) ?>"></label>
<label>New password (leave blank to keep)<input name="password" type="text" autocomplete="off"></label>
<label>Remove password<br><input type="checkbox" name="clear_pw" value="1" style="width:auto"> Anyone with the link can open it</label>
<label style="grid-column:1/-1">Note shown to client<textarea name="note" rows="3"><?= h($g['note']) ?></textarea></label>
<label>Status<br><input type="checkbox" name="active" value="1" style="width:auto" <?= $g['active'] ? 'checked' : '' ?>> Gallery is on (uncheck to hide it)</label>
<div style="grid-column:1/-1"><button class="btn">Save settings</button></div></form>

<h2>Prices</h2>
<p>Each photo uses the default price unless you type its own. Type <b>0</b> for Free. Leave blank to use the default.</p>
<form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="priceall"><input type="hidden" name="id" value="<?= $gid ?>">
<label style="margin:0">Set all photos to ($)<input name="price" placeholder="blank = default" style="width:150px"></label>
<label style="margin:0"><input type="checkbox" name="only_fav" value="1" style="width:auto"> only favorites (<?= $nfav ?>)</label><button class="btn sm">Apply</button></form>

<h2><?= count($imgs) ?> photos</h2>
<div class="cards">
<?php foreach ($imgs as $i): ?>
<div class="cd"><img loading="lazy" src="<?= $base ?>?a=thumb&id=<?= $i['id'] ?>" alt="">
<small><?= h($i['label']) ?></small>
<div style="display:flex;gap:6px;align-items:center;margin:4px 0"><span>$</span><input value="<?= $i['price_cents'] === null ? '' : h(number_format($i['price_cents'] / 100, 2)) ?>" placeholder="<?= h(number_format($g['default_price_cents'] / 100, 2)) ?>" data-img="<?= $i['id'] ?>" class="pr"></div>
<small><?= $i['fav'] ? '&#9829; favorite ' : '' ?><?= $i['ncom'] ? $i['ncom'] . ' comments ' : '' ?><?= $i['dls'] ? $i['dls'] . ' downloads' : '' ?></small>
<form method="post" onsubmit="return confirm('Delete this photo and its original?')"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="delimg"><input type="hidden" name="id" value="<?= $gid ?>"><input type="hidden" name="image" value="<?= $i['id'] ?>"><button class="btn sm alt" style="padding:3px 8px;font-size:12px">Delete</button></form></div>
<?php endforeach; ?></div>

<?php
$o = db()->prepare('SELECT o.*, i.label FROM orders o JOIN images i ON i.id = o.image_id WHERE o.gallery_id = ? ORDER BY o.id DESC'); $o->execute([$gid]); $orders = $o->fetchAll();
$c = db()->prepare('SELECT c.*, i.label, i.id AS iid FROM comments c JOIN images i ON i.id = c.image_id WHERE i.gallery_id = ? ORDER BY c.id DESC'); $c->execute([$gid]); $coms = $c->fetchAll();
$fv = db()->prepare('SELECT i.label FROM favorites f JOIN images i ON i.id = f.image_id WHERE i.gallery_id = ? ORDER BY i.sort'); $fv->execute([$gid]); $favs = $fv->fetchAll(PDO::FETCH_COLUMN);
?>
<h2 id="orders">Photo requests</h2>
<?php if (!$orders): ?><p>None yet.</p><?php else: ?><table><tr><th>Photo</th><th>Email</th><th>Status</th><th></th></tr>
<?php foreach ($orders as $r): ?><tr><td><?= h($r['label']) ?></td><td><?= h($r['email']) ?></td><td><span class="pill"><?= h($r['status']) ?></span></td><td>
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
  var f=new URLSearchParams({a:'price',image:el.dataset.img,price:el.value,csrf:CSRF});
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
<label>Default price per photo ($, 0 = Free)<input name="price" value="0"></label>
<label>Password (optional)<input name="password" autocomplete="off"></label>
<label style="grid-column:1/-1">Note shown to client<textarea name="note" rows="2"></textarea></label>
<div style="grid-column:1/-1"><button class="btn">Create gallery</button></div></form>
<?php afoot();
