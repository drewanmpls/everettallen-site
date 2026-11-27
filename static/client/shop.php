<?php
// Buying photos from the public galleries (one photo or a whole cart).
// Masters live privately at <private>/masters/<gallery>/<id>.jpg (uploaded by scripts/eap-publish.py).
// A purchase becomes a small private "order gallery" (kind = 'shop') so the normal Stripe + download code does the rest.
declare(strict_types=1);
require __DIR__ . '/lib.php';
require __DIR__ . '/payments.php';
noindex();
start_session();

function shop_prices(): array { return ['web' => (int)setting('shop_price_web_cents', '0'), 'full' => (int)setting('shop_price_full_cents', '0')]; }
// Multi-photo discount: {min, pct}. Applies to every photo once the order has at least `min` photos.
function shop_discount(): array { $m = (int)setting('shop_discount_min', '0'); $p = (int)setting('shop_discount_pct', '0'); return ($m >= 2 && $p > 0 && $p < 100) ? ['min' => $m, 'pct' => $p] : ['min' => 0, 'pct' => 0]; }
function shop_enabled(): bool { $p = shop_prices(); return setting('shop_enabled', '') === '1' && ($p['web'] > 0 || $p['full'] > 0); }

// Looks a code up (any case). Returns the row, or answers "did not work" (and counts the miss; 20 misses in 10 minutes locks guessing for everyone).
function shop_find_code(string $raw, bool $quiet = false): ?array {
    $code = strtolower(trim($raw));
    $fails = explode(':', (string)setting('shop_code_fails', '0:0'));
    $ft = (int)($fails[0] ?? 0); $fc = (int)($fails[1] ?? 0);
    if ($ft < time() - 600) $fc = 0;
    if ($fc >= 20) { if ($quiet) return null; json_out(['error' => 'Too many wrong codes. Please wait a few minutes.'], 429); }
    $cr = db()->prepare('SELECT * FROM codes WHERE code = ? AND active = 1'); $cr->execute([$code]); $cr = $cr->fetch();
    if ($cr && ($cr['expires'] === '' || $cr['expires'] === null || $cr['expires'] >= date('Y-m-d'))) return $cr;
    set_setting('shop_code_fails', time() . ':' . ($fc + 1));
    if ($quiet) return null;
    json_out(['error' => 'That code did not work.'], 400);
}

$a = (string)($_REQUEST['a'] ?? 'info');

if ($a === 'info') {
    $p = shop_prices(); $on = shop_enabled();
    $cn = ''; if ($on && trim((string)($_GET['code'] ?? '')) !== '') { $cr = shop_find_code((string)$_GET['code'], true); $cn = $cr ? (string)$cr['name'] : ''; }
    json_out(['codeName' => $cn, 'enabled' => $on, 'web' => $on ? $p['web'] : 0, 'full' => $on ? $p['full'] : 0, 'webText' => money($p['web']), 'fullText' => money($p['full']), 'discountMin' => shop_discount()['min'], 'discountPct' => shop_discount()['pct']]);
}

if ($a !== 'checkout' || $_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'Bad request'], 400);
if (!shop_enabled()) json_out(['error' => 'Photo sales are not open yet.'], 400);

// same-site only, and a gentle rate limit
$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
if ($origin !== '' && strtolower((string)parse_url($origin, PHP_URL_HOST)) !== strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')))) json_out(['error' => 'Bad request'], 400);
$_SESSION['shop_hits'] = array_values(array_filter($_SESSION['shop_hits'] ?? [], fn($t) => $t > time() - 600));
if (count($_SESSION['shop_hits']) >= 8) json_out(['error' => 'Too many tries. Please wait a few minutes.'], 429);
$_SESSION['shop_hits'][] = time();
$recent = db()->prepare("SELECT COUNT(*) FROM galleries WHERE kind = 'shop' AND created > ?"); $recent->execute([time() - 3600]);
if ((int)$recent->fetchColumn() > 40) json_out(['error' => 'Busy right now. Please try again in a little while.'], 429);

$email = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
if (!$email) json_out(['error' => 'Please enter a valid email so I can send your download link.'], 400);
$tier = ($_POST['tier'] ?? 'full') === 'web' ? 'web' : 'full';
$prices = shop_prices(); $price = $prices[$tier];
if ($price <= 0) json_out(['error' => 'That size is not available.'], 400);
$items = $_POST['items'] ?? [];   // items[] = "<gallery-slug>/<photo-id>"
if (!is_array($items) || !$items || count($items) > 30) json_out(['error' => 'Pick 1 to 30 photos.'], 400);

// find the masters
$picked = []; $seen = [];
foreach ($items as $it) {
    if (!is_string($it) || !preg_match('#^([a-z0-9-]{1,120})/(\d{3,6})$#', $it, $m) || isset($seen[$it])) continue;
    $seen[$it] = 1;
    $file = masters_dir() . '/' . $m[1] . '/' . $m[2] . '.jpg';
    if (!is_file($file)) json_out(['error' => 'One of those photos is not available for download yet.'], 400);
    $lab = is_file(masters_dir() . '/' . $m[1] . '/labels.json') ? (json_decode((string)file_get_contents(masters_dir() . '/' . $m[1] . '/labels.json'), true) ?: []) : [];
    $label = (string)($lab['files'][$m[2]] ?? ($m[1] . '-' . $m[2]));
    $picked[] = ['slug' => $m[1], 'id' => $m[2], 'file' => $file, 'label' => preg_replace('/\.jpe?g$/i', '', $label), 'title' => (string)($lab['title'] ?? '')];
}
if (!$picked) json_out(['error' => 'Pick at least one photo.'], 400);
$d = shop_discount();
if ($d['pct'] > 0 && count($picked) >= $d['min']) $price = (int)round($price * (100 - $d['pct']) / 100);

// Free-download code (made in Admin > Codes). A right code skips payment and unlocks the photos right away.
$free = false; $codeId = 0;
if (strtolower(trim((string)($_POST['code'] ?? ''))) !== '') { $cr = shop_find_code((string)$_POST['code']); $free = true; $codeId = (int)$cr['id']; }

// tidy: order galleries that were never paid for, older than 3 days
$old = db()->prepare("SELECT id FROM galleries WHERE kind = 'shop' AND created < ? AND NOT EXISTS (SELECT 1 FROM orders o WHERE o.gallery_id = galleries.id AND o.status = 'paid')");
$old->execute([time() - 3 * 86400]);
foreach ($old->fetchAll(PDO::FETCH_COLUMN) as $oid) {
    $dir = priv_dir() . '/galleries/' . (int)$oid;
    if (is_dir($dir)) foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) { $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
    if (is_dir($dir)) @rmdir($dir);
    db()->prepare('DELETE FROM galleries WHERE id = ?')->execute([(int)$oid]);
}

// the order gallery
$gt = is_file(__DIR__ . '/gallery-titles.json') ? (json_decode((string)file_get_contents(__DIR__ . '/gallery-titles.json'), true) ?: []) : [];
$titles = array_values(array_unique(array_filter(array_map(fn($p) => (string)($gt[$p['slug']] ?? $p['title']), $picked))));
$gtitle = 'Your photos' . ($titles ? ': ' . $titles[0] . (count($titles) > 1 ? ' and more' : '') : '');
db()->prepare("INSERT INTO galleries (token, title, client_email, default_price_cents, default_web_cents, kind, created) VALUES (?,?,?,?,?, 'shop', ?)")
    ->execute([bin2hex(random_bytes(12)), $gtitle, $email, $prices['full'], $prices['web'], time()]);
$gid = (int)db()->lastInsertId();
$g = db()->query('SELECT * FROM galleries WHERE id = ' . $gid)->fetch();

$orders = []; $n = 0;
foreach ($picked as $p) {
    $name = bin2hex(random_bytes(10)) . '.jpg';
    if (!copy($p['file'], gdir($gid, 'orig') . '/' . $name)) json_out(['error' => 'Could not prepare your photos. Please try again.'], 500);
    $sz = @getimagesize($p['file']) ?: [1600, 1067];
    $sc = min(1, 1600 / max($sz[0], $sz[1]));
    db()->prepare('INSERT INTO images (gallery_id, file, label, sort, w, h, created) VALUES (?,?,?,?,?,?,?)')
        ->execute([$gid, $name, $p['label'], ++$n, (int)round($sz[0] * $sc), (int)round($sz[1] * $sc), time()]);
    $iid = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO orders (gallery_id, image_id, email, status, tier, created) VALUES (?, ?, ?, 'requested', ?, ?)")->execute([$gid, $iid, $email, $tier, time()]);
    $orders[] = ['id' => (int)db()->lastInsertId(), 'email' => $email, 'tier' => $tier, 'price_cents' => $price, 'label' => $p['label']];
}

if ($free) {
    db()->prepare('UPDATE images SET price_cents = 0 WHERE gallery_id = ?')->execute([$gid]);
    db()->prepare('UPDATE galleries SET code_id = ? WHERE id = ?')->execute([$codeId, $gid]);   // free order: shows as $0 in stats
    mark_orders_paid(array_column($orders, 'id'), 'owner-code');
    json_out(['ok' => true, 'checkout' => 'https://' . ($_SERVER['HTTP_HOST'] ?? '') . '/client/g/' . $g['token'] . '/']);
}
$url = create_checkout_multi($orders, $g);
if ($url) json_out(['ok' => true, 'checkout' => $url]);

// Stripe off or unavailable: manual flow
$count = count($orders); $label = $tier === 'web' ? 'web size' : 'full resolution'; $total = $price * $count;
$list = implode("\n", array_map(fn($o) => '  - ' . $o['label'], $orders));
send_owner_mail('Photo request: ' . $count . ' photo' . ($count > 1 ? 's' : ''), "$email asked for $count photo" . ($count > 1 ? 's' : '') . " as $label (" . money($total) . "):\n$list\n\nMark each paid in the admin once payment is arranged:\nhttps://" . ($_SERVER['HTTP_HOST'] ?? '') . "/client/admin/?a=gallery&id=$gid\n", $email);
$how = trim((string)setting('manual_pay_text', ''));
if ($how !== '') send_client_mail($email, 'Your photo request: next steps', "Thanks! I got your request for $count photo" . ($count > 1 ? 's' : '') . " as $label (" . money($total) . "):\n$list\n\n$how\n\nOnce I see the payment, I will unlock your downloads and email you.\n\nAndrew\nEverett Allen Photography\n");
json_out(['ok' => true, 'message' => $how !== '' ? 'Request sent. Check your email for how to pay.' : 'Sent. I will email you about getting your photos.']);
