<?php
// Admin > Links: short, memorable addresses (everettallenphotography.com/go/name) that redirect anywhere. Good for QR codes.
// Included by index.php after the admin login check.
declare(strict_types=1);

if ($a === 'links' || in_array($a, ['linknew', 'linktoggle', 'linkdel'], true)) {
    $err = '';
    if ($post) {
        if (!csrf_ok()) { http_response_code(400); echo 'Session expired. Go back, reload the page and try again.'; exit; }
        if ($a === 'linknew') {
            $slug = strtolower(trim((string)($_POST['slug'] ?? ''))); $target = trim((string)($_POST['target'] ?? ''));
            if ($target !== '' && $target[0] === '/' && ($target[1] ?? '') !== '/') $target = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'www.everettallenphotography.com') . $target;
            if (!preg_match('/^[a-z0-9][a-z0-9-]{1,39}$/', $slug)) $err = 'slug';
            elseif (!preg_match('#^https://[^\s]+$#i', $target) || strlen($target) > 1000) $err = 'target';
            else {
                try { db()->prepare('INSERT INTO shortlinks (slug, target, note, created) VALUES (?,?,?,?)')->execute([$slug, $target, mb_substr(trim((string)($_POST['note'] ?? '')), 0, 120), time()]); go($base . '?a=links&saved=1'); }
                catch (Throwable $e) { $err = 'dup'; }
            }
        } elseif ($a === 'linktoggle') {
            db()->prepare('UPDATE shortlinks SET active = 1 - active WHERE slug = ?')->execute([(string)($_POST['slug'] ?? '')]); go($base . '?a=links');
        } elseif ($a === 'linkdel') {
            db()->prepare('DELETE FROM shortlinks WHERE slug = ?')->execute([(string)($_POST['slug'] ?? '')]); go($base . '?a=links');
        }
    }
    ahead('Links'); $tok = csrf();
    $host = 'https://www.everettallenphotography.com';
    $rows = db()->query('SELECT * FROM shortlinks ORDER BY created DESC')->fetchAll();
    $errs = ['slug' => 'The short name needs 2 to 40 letters, numbers or dashes.', 'target' => 'Paste a full address that starts with https://', 'dup' => 'That short name is already used.'];
?>
<h1 style="font-size:clamp(32px,5vw,64px)">Short links</h1>
<p>Make a short address like <code><?= h($host) ?>/go/leather-family</code> that sends people to any page, such as a private gallery. Shorter addresses make cleaner QR codes. You can pause a link or delete it any time, and the QR code keeps working as long as the link stays on.</p>
<?php if (!empty($_GET['saved'])): ?><p class="ok">Saved.</p><?php endif; ?>
<?php if ($err): ?><p class="err"><?= h($errs[$err]) ?></p><?php endif; ?>
<form method="post" class="cols" style="max-width:760px"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="linknew">
<label>Short name (after /go/)<input name="slug" required placeholder="leather-family" autocomplete="off" value="<?= h($err ? (string)($_POST['slug'] ?? '') : '') ?>"></label>
<label>Sends people to (full address)<input name="target" required placeholder="https://www.everettallenphotography.com/client/g/..." autocomplete="off" value="<?= h($err ? (string)($_POST['target'] ?? '') : '') ?>"></label>
<label style="grid-column:1/-1">Note to yourself (optional)<input name="note" maxlength="120" placeholder="Holiday Fundraiser QR code"></label>
<div style="grid-column:1/-1"><button class="btn">Create short link</button></div></form>
<h2>Your links</h2>
<?php if (!$rows): ?><p>No short links yet.</p><?php else: ?><table><tr><th>Short link</th><th>Goes to</th><th>Clicks</th><th></th></tr>
<?php foreach ($rows as $r): $u = $host . '/go/' . $r['slug']; ?><tr>
<td><span class="link" style="display:inline-block"><?= h($u) ?></span><?= $r['active'] ? '' : ' <span class="pill">paused</span>' ?><?php if ($r['note']): ?><br><small style="color:var(--mut)"><?= h($r['note']) ?></small><?php endif; ?></td>
<td style="word-break:break-all"><a href="<?= h($r['target']) ?>" target="_blank" rel="noopener"><?= h(mb_strimwidth($r['target'], 0, 70, '...')) ?></a></td>
<td><?= (int)$r['clicks'] ?></td>
<td style="white-space:nowrap"><form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="linktoggle"><input type="hidden" name="slug" value="<?= h($r['slug']) ?>"><button class="btn sm alt"><?= $r['active'] ? 'Pause' : 'Turn on' ?></button></form>
<form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="linkdel"><input type="hidden" name="slug" value="<?= h($r['slug']) ?>"><button class="btn sm alt">Delete</button></form></td></tr><?php endforeach; ?></table><?php endif; ?>
<?php afoot(); exit;
}
