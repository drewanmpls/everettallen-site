<?php
// JSON endpoints used by the client gallery page.
declare(strict_types=1);
require __DIR__ . '/lib.php';
require __DIR__ . '/payments.php';
noindex();
start_session();
$g = gallery_by_token((string)($_REQUEST['t'] ?? ''));
if (!$g || !gallery_authorized($g)) json_out(['error' => 'Not found'], 404);
$a = (string)($_REQUEST['a'] ?? '');

function load_image(array $g): array {
    $s = db()->prepare('SELECT * FROM images WHERE id = ? AND gallery_id = ?'); $s->execute([(int)($_REQUEST['i'] ?? 0), $g['id']]);
    $img = $s->fetch();
    if (!$img) json_out(['error' => 'Photo not found'], 404);
    return $img;
}

if ($a === 'comments') {
    $img = load_image($g);
    $s = db()->prepare('SELECT author, body, from_owner, created FROM comments WHERE image_id = ? ORDER BY id'); $s->execute([$img['id']]);
    json_out(['comments' => $s->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok()) json_out(['error' => 'Bad request'], 400);

// light rate limit per session
$_SESSION['hits'] = array_values(array_filter($_SESSION['hits'] ?? [], fn($t) => $t > time() - 60));
if (count($_SESSION['hits']) > 60) json_out(['error' => 'Slow down a little'], 429);
$_SESSION['hits'][] = time();

if ($a === 'fav') {
    $img = load_image($g);
    if (!empty($_POST['on'])) db()->prepare('INSERT OR IGNORE INTO favorites (image_id, created) VALUES (?, ?)')->execute([$img['id'], time()]);
    else db()->prepare('DELETE FROM favorites WHERE image_id = ?')->execute([$img['id']]);
    $c = db()->prepare('SELECT COUNT(*) FROM favorites f JOIN images i ON i.id = f.image_id WHERE i.gallery_id = ?'); $c->execute([$g['id']]);
    json_out(['ok' => true, 'favorites' => (int)$c->fetchColumn()]);
}

if ($a === 'comment') {
    $img = load_image($g);
    $body = trim((string)($_POST['body'] ?? '')); $author = trim(strip_tags((string)($_POST['author'] ?? '')));
    if ($body === '' || mb_strlen($body) > 1000) json_out(['error' => 'Write a comment (up to 1000 characters).'], 400);
    $author = mb_substr($author !== '' ? $author : ($g['client_name'] ?: 'Guest'), 0, 60);
    db()->prepare('INSERT INTO comments (image_id, author, body, from_owner, created) VALUES (?, ?, ?, 0, ?)')->execute([$img['id'], $author, $body, time()]);
    $n = db()->prepare('SELECT COUNT(*) FROM comments c JOIN images i ON i.id = c.image_id WHERE i.gallery_id = ? AND c.created > ?'); $n->execute([$g['id'], time() - 3600]);
    if ((int)$n->fetchColumn() <= 15) {
        send_owner_mail('New comment on "' . $g['title'] . '"', "$author commented on " . ($img['label'] ?: 'photo ' . $img['id']) . ":\n\n$body\n\nOpen the gallery admin to reply:\nhttps://" . ($_SERVER['HTTP_HOST'] ?? '') . "/client/admin/?a=gallery&id=" . $g['id'] . "\n");
    }
    json_out(['ok' => true, 'comment' => ['author' => $author, 'body' => $body, 'from_owner' => 0, 'created' => time()]]);
}

if ($a === 'claim' || $a === 'unclaim') {
    $img = load_image($g);
    if ($a === 'claim') {
        $included = (int)$g['included_count'];
        if (image_is_claimed((int)$img['id'])) json_out(['ok' => true]);
        if (claims_used((int)$g['id']) >= $included) json_out(['error' => 'You have used all ' . $included . ' included photos.'], 400);
        db()->prepare('INSERT OR IGNORE INTO claims (image_id, created) VALUES (?, ?)')->execute([$img['id'], time()]);
    } else {
        if (was_downloaded((int)$img['id'])) json_out(['error' => 'This photo was already downloaded, so it can\'t be swapped.'], 400);
        db()->prepare('DELETE FROM claims WHERE image_id = ?')->execute([$img['id']]);
    }
    json_out(['ok' => true, 'used' => claims_used((int)$g['id']), 'included' => (int)$g['included_count']]);
}

if ($a === 'request') {
    $img = load_image($g);
    $tier = ($_POST['tier'] ?? 'full') === 'web' ? 'web' : 'full';
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    if (!$email) json_out(['error' => 'Please enter a valid email so Everett can reply.'], 400);
    if (can_download($img, $g, $tier)) json_out(['error' => 'This size is already available: just download it.'], 400);
    $price = $tier === 'web' ? image_price_web($img, $g) : image_price($img, $g);
    $label = $tier === 'web' ? 'web size (social)' : 'full resolution (print)';
    db()->prepare("INSERT INTO orders (gallery_id, image_id, email, status, tier, created) VALUES (?, ?, ?, 'requested', ?, ?)")->execute([$g['id'], $img['id'], $email, $tier, time()]);
    $orderId = (int)db()->lastInsertId();
    if (payments_enabled()) {
        $url = create_checkout(['id' => $orderId, 'email' => $email, 'tier' => $tier, 'price_cents' => $price], $img, $g);
        if ($url) json_out(['ok' => true, 'checkout' => $url]);
    }
    send_owner_mail('Photo request: "' . $g['title'] . '"', "$email asked for " . ($img['label'] ?: 'photo ' . $img['id']) . " as $label (" . money($price) . ").\n\nMark it paid in the admin once payment is arranged:\nhttps://" . ($_SERVER['HTTP_HOST'] ?? '') . "/client/admin/?a=gallery&id=" . $g['id'] . "\n", $email);
    json_out(['ok' => true, 'message' => 'Sent. Everett will email you about getting this photo.']);
}

if ($a === 'done') {
    $s = db()->prepare('SELECT i.id, i.label, i.price_cents FROM favorites f JOIN images i ON i.id = f.image_id WHERE i.gallery_id = ? ORDER BY i.sort, i.id'); $s->execute([$g['id']]);
    $rows = $s->fetchAll();
    $lines = []; $total = 0;
    foreach ($rows as $r) { $p = $r['price_cents'] === null ? (int)$g['default_price_cents'] : (int)$r['price_cents']; $total += $p; $lines[] = '  - ' . ($r['label'] ?: 'photo ' . $r['id']) . ' (' . money($p) . ')'; }
    $who = trim(strip_tags((string)($_POST['author'] ?? ''))) ?: ($g['client_name'] ?: 'Your client');
    $cl = db()->prepare('SELECT i.label, i.id FROM claims c JOIN images i ON i.id = c.image_id WHERE i.gallery_id = ? ORDER BY i.sort, i.id'); $cl->execute([$g['id']]);
    $claimed = array_map(fn($r) => '  - ' . ($r['label'] ?: 'photo ' . $r['id']), $cl->fetchAll());
    $extra = $claimed ? "\n\nIncluded-package picks (" . count($claimed) . " of " . (int)$g['included_count'] . "):\n" . implode("\n", $claimed) : '';
    send_owner_mail('Picks submitted: "' . $g['title'] . '"', "$who marked " . count($rows) . " favorites (" . money($total) . " total):\n\n" . implode("\n", $lines) . $extra . "\n", (string)$g['client_email']);
    json_out(['ok' => true, 'count' => count($rows)]);
}

json_out(['error' => 'Unknown action'], 400);
