<?php
// Private client gallery page: /client/g/<token>/
declare(strict_types=1);
require __DIR__ . '/lib.php';
require __DIR__ . '/payments.php';
noindex();
header('Cache-Control: private, no-store');
start_session();
$t = (string)($_GET['t'] ?? '');
$g = gallery_by_token($t);

function page_head(string $title): void { ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title><?= h($title) ?> | Everett Allen Photography</title>
<link rel="icon" href="/assets/icon.png"><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@500;800&family=Archivo+Narrow:wght@500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/client/client.css"></head><body>
<?php }

if (!$g) { http_response_code(404); page_head('Gallery not found'); ?>
<main class="narrow"><h1>Gallery not found</h1><p>This link isn't working. It may have expired or been mistyped. Please contact Andrew for a new link.</p><p><a class="btn" href="/contact/">Contact</a></p></main></body></html>
<?php exit; }

// password gate
if (!gallery_authorized($g)) {
    $err = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
        if (password_verify((string)($_POST['password'] ?? ''), $g['password_hash'])) {
            session_regenerate_id(true); $_SESSION['g' . $g['id']] = true; header('Location: ' . $_SERVER['REQUEST_URI']); exit;
        }
        $err = 'That password didn\'t match.'; usleep(700000);
    }
    page_head($g['title']); ?>
<main class="narrow"><p class="kicker">Private gallery</p><h1><?= h($g['title']) ?></h1>
<form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
<label>Password<input type="password" name="password" autofocus required></label>
<?php if ($err): ?><p class="err"><?= h($err) ?></p><?php endif; ?>
<button class="btn" type="submit">Open gallery</button></form></main></body></html>
<?php exit; }

$s = db()->prepare('SELECT i.*, (SELECT COUNT(*) FROM favorites f WHERE f.image_id = i.id) AS fav, (SELECT COUNT(*) FROM comments c WHERE c.image_id = i.id) AS ncom FROM images i WHERE i.gallery_id = ? ORDER BY i.sort, i.id');
$s->execute([$g['id']]);
$imgs = $s->fetchAll();
$data = [];
foreach ($imgs as $i) {
    $pf = image_price($i, $g); $pw = image_price_web($i, $g); $cl = image_is_claimed((int)$i['id']);
    $data[] = ['id' => (int)$i['id'], 'label' => $i['label'], 'w' => (int)$i['w'], 'h' => (int)$i['h'], 'fav' => (int)$i['fav'] > 0, 'n' => (int)$i['ncom'],
        'pf' => $pf, 'pw' => $pw, 'pfText' => money($pf), 'pwText' => money($pw), 'claimed' => $cl, 'canUnclaim' => $cl && !was_downloaded((int)$i['id']),
        'dlFull' => can_download($i, $g, 'full'), 'dlWeb' => can_download($i, $g, 'web')];
}
$cfg = ['token' => $t, 'csrf' => csrf(), 'payments' => payments_enabled(), 'included' => (int)$g['included_count'], 'used' => claims_used((int)$g['id']), 'client' => (string)$g['client_name'], 'images' => $data];
page_head($g['title']); ?>
<header class="top"><a href="/" class="brand">Everett Allen Photography</a><span class="kicker">Private gallery</span></header>
<main class="wrap">
<div class="intro">
  <h1><?= h($g['title']) ?></h1>
  <?php if ($g['client_name']): ?><p class="who">For <?= h($g['client_name']) ?></p><?php endif; ?>
  <?php if (trim((string)$g['note'])): ?><p class="note"><?= nl2br(h($g['note'])) ?></p><?php endif; ?>
  <p class="help">Tap the heart to mark favorites. Tap a photo to see it larger, leave a comment, or get your files.<?php if ($g['included_count'] > 0): ?> Your package includes <b><?= (int)$g['included_count'] ?></b> photos: choose them with <b>Add to my package</b>.<?php endif; ?> Previews are watermarked; downloads are not.</p>
</div>
<div class="bar">
  <div class="tabs"><button class="tab on" data-f="all">All <span id="cAll"><?= count($data) ?></span></button><button class="tab" data-f="fav">Favorites <span id="cFav">0</span></button></div>
  <?php if ($g['included_count'] > 0): ?><span class="pkg" id="pkg"></span><?php endif; ?>
  <button class="btn" id="done">Send my picks to Andrew</button>
</div>
<div class="grid" id="grid">
<?php foreach ($data as $n => $d): ?>
  <figure class="ph" data-id="<?= $d['id'] ?>" style="--r:<?= $d['w'] && $d['h'] ? round($d['w'] / $d['h'], 4) : 1.5 ?>">
    <a href="#<?= $d['id'] ?>" data-i="<?= $n ?>"><img loading="lazy" src="/client/img.php?t=<?= h($t) ?>&i=<?= $d['id'] ?>&s=thumb" alt="<?= h($d['label']) ?>" draggable="false"></a>
    <button class="heart" data-id="<?= $d['id'] ?>" aria-label="Favorite" aria-pressed="false">&#9825;</button>
    <span class="tag"><?= $d['claimed'] ? 'Included' : h($d['pfText']) ?></span>
    <?php if ($d['n']): ?><span class="cm"><?= $d['n'] ?></span><?php endif; ?>
  </figure>
<?php endforeach; ?>
</div>
<?php if (!$data): ?><p class="narrow">Photos are on the way. Check back soon.</p><?php endif; ?>
</main>
<div class="lb" id="lb" hidden>
  <button class="x" id="lbx" aria-label="Close">&times;</button>
  <button class="nav p" id="lbp" aria-label="Previous">&lsaquo;</button><button class="nav n" id="lbn" aria-label="Next">&rsaquo;</button>
  <div class="stage"><img id="lbimg" alt="" draggable="false"></div>
  <aside class="side">
    <div class="row"><strong id="lbt"></strong><button class="heart big" id="lbh" aria-pressed="false">&#9825;</button></div>
    <div class="buy" id="lbbuy"></div>
    <h3>Comments</h3><div id="lbc" class="comments"></div>
    <form id="lbf"><input name="author" id="lba" placeholder="Your name" maxlength="60"><textarea name="body" id="lbb" rows="2" maxlength="1000" placeholder="Leave a note about this photo" required></textarea><button class="btn sm" type="submit">Post comment</button></form>
  </aside>
</div>
<div class="toast" id="toast" hidden></div>
<script>window.EA=<?= json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="/client/client.js"></script>
</body></html>
