<?php
// Fills the LOCAL test database with fake clients, private galleries, photos, favorites, comments, orders and stats.
// Run by dev/run.sh the first time (or after "bash dev/run.sh reset"). Refuses to run unless EA_PRIVATE points inside dev/.
// Every name, email and number here is made up. Emails use example.test, which can never be delivered.
declare(strict_types=1);

$priv = (string)getenv('EA_PRIVATE');
if ($priv === '' || strpos(realpath($priv) ?: '', realpath(__DIR__) . '/') !== 0) { fwrite(STDERR, "Run this through dev/run.sh (EA_PRIVATE must be inside dev/).\n"); exit(1); }
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__) . '/dist';
$_SERVER['HTTP_HOST'] = '127.0.0.1';
require dirname(__DIR__) . '/dist/client/lib.php';

const DEV_PASSWORD = 'local-practice-only';   // local fake copy only; never used on the real site
mt_srand(42);
$now = time(); $day = 86400;
$pdo = db();
if ((int)$pdo->query('SELECT COUNT(*) FROM clients')->fetchColumn() > 0) { echo "Fake data already there. Use: bash dev/run.sh reset\n"; exit(0); }
set_setting('admin_hash', password_hash(DEV_PASSWORD, PASSWORD_DEFAULT));
@unlink(priv_dir() . '/setup-code.txt');

// ---------- sample photos (plain colored frames with a number, so they are obviously not real) ----------
function fake_jpeg(string $label, int $n, bool $tall): string {
    [$w, $h] = $tall ? [1600, 2400] : [2400, 1600];
    $im = imagecreatetruecolor($w, $h);
    $hue = ($n * 47) % 360; [$r, $g, $b] = hsl($hue, 0.35, 0.42); [$r2, $g2, $b2] = hsl(($hue + 40) % 360, 0.40, 0.22);
    for ($y = 0; $y < $h; $y += 8) { $t = $y / $h; imagefilledrectangle($im, 0, $y, $w, $y + 8, imagecolorallocate($im, (int)($r + ($r2 - $r) * $t), (int)($g + ($g2 - $g) * $t), (int)($b + ($b2 - $b) * $t))); }
    $white = imagecolorallocate($im, 255, 255, 255);
    $txt = strtoupper($label) . ' ' . $n; $s = 8;   // scale the built-in font up so it reads in thumbnails
    $tw = imagefontwidth(5) * strlen($txt); $th = imagefontheight(5);
    $tile = imagecreatetruecolor($tw, $th); imagefill($tile, 0, 0, imagecolorallocate($tile, 0, 0, 0)); imagecolortransparent($tile, imagecolorallocate($tile, 0, 0, 0));
    imagestring($tile, 5, 0, 0, $txt, imagecolorallocate($tile, 255, 255, 255));
    imagecopyresized($im, $tile, (int)(($w - $tw * $s) / 2), (int)(($h - $th * $s) / 2), 0, 0, $tw * $s, $th * $s, $tw, $th);
    imagerectangle($im, 40, 40, $w - 41, $h - 41, $white);
    $f = tempnam(sys_get_temp_dir(), 'eap') . '.jpg'; imagejpeg($im, $f, 82);
    return $f;
}
function hsl(int $h, float $s, float $l): array {
    $c = (1 - abs(2 * $l - 1)) * $s; $x = $c * (1 - abs(fmod($h / 60, 2) - 1)); $m = $l - $c / 2;
    [$r, $g, $b] = $h < 60 ? [$c, $x, 0] : ($h < 120 ? [$x, $c, 0] : ($h < 180 ? [0, $c, $x] : ($h < 240 ? [0, $x, $c] : ($h < 300 ? [$x, 0, $c] : [$c, 0, $x]))));
    return [(int)(($r + $m) * 255), (int)(($g + $m) * 255), (int)(($b + $m) * 255)];
}
function add_photos(int $gid, string $label, int $count, int $start = 1): array {
    $ids = [];
    for ($i = 0; $i < $count; $i++) {
        $n = $start + $i; $tmp = fake_jpeg($label, $n, $n % 3 === 0);
        $r = ingest_image($gid, $tmp, sprintf('EAP_%04d.jpg', 1000 + $n)); @unlink($tmp);
        db()->prepare('INSERT INTO images (gallery_id, file, label, sort, w, h, created) VALUES (?,?,?,?,?,?,?)')->execute([$gid, $r['file'], $r['label'], $i + 1, $r['w'], $r['h'], time()]);
        $ids[] = (int)db()->lastInsertId();
    }
    return $ids;
}
function add_client(array $c): int {
    $cols = array_keys($c);
    db()->prepare('INSERT INTO clients (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')')->execute(array_values($c));
    return (int)db()->lastInsertId();
}
function add_gallery(array $g): int {
    $g += ['token' => bin2hex(random_bytes(12)), 'created' => time()];
    $cols = array_keys($g);
    db()->prepare('INSERT INTO galleries (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')')->execute(array_values($g));
    return (int)db()->lastInsertId();
}

// ---------- clients, one or more in every status ----------
$svc = array_column(services_list(), 'title');
$sv = fn(int $i) => $svc[$i % max(1, count($svc))] ?? 'Portrait session';
$releaseText = implode("\n\n", share_offer()['terms'] ?: ['Sample release wording for local testing.']);
$people = [
    ['Riley Sample', 'new', 1, 'Next month, weekends', 'form'],
    ['Jordan Testperson', 'new', 0, 'Before Pride', 'form'],
    ['Casey Fakename', 'contacted', 4, 'Flexible', 'form'],
    ['Morgan Example', 'booked', 9, 'Dec 12, 2026', 'form'],
    ['Avery Placeholder', 'shot', 20, 'Done', 'admin'],
    ['Sam Demo', 'delivered', 45, 'Done', 'form'],
    ['Quinn Mockup', 'delivered', 80, 'Done', 'form'],
    ['Drew Archive', 'archived', 200, 'Last year', 'admin'],
];
$cid = [];
foreach ($people as $k => [$name, $status, $ago, $when, $src]) {
    $slug = strtolower(strtok($name, ' '));
    $cid[$name] = add_client(['name' => $name, 'email' => $slug . '@example.test', 'phone' => '555-01' . str_pad((string)(10 + $k), 2, '0', STR_PAD_LEFT),
        'pronouns' => ['they/them', 'he/him', 'she/her'][$k % 3], 'service' => $sv($k), 'timeframe' => $when, 'location' => ['Studio', 'Outdoors, Minneapolis', 'At an event'][$k % 3],
        'handle' => '@' . $slug . '.sample', 'referral' => ['Instagram', 'A friend', 'Saw you at an event'][$k % 3], 'share_pref' => ['Yes', 'Ask first', 'Private'][$k % 3],
        'message' => 'This is a made-up inquiry for testing the admin. Looking for a few portraits.', 'status' => $status,
        'notes' => $k % 2 ? 'Fake private note: prefers morning sessions.' : '', 'source' => $src, 'created' => $now - $ago * $day]);
}
// Signed releases: one with ID checked and saved to Drive, one still waiting on the ID check.
foreach ([['Morgan Example', '', 'not sent'], ['Sam Demo', date('m/d/Y', $now - 40 * $day), 'saved']] as [$name, $idv, $drive]) {
    db()->prepare("UPDATE clients SET release_at = ?, release_name = ?, release_stage = ?, release_dob = '1990-01-01', release_gallery = 'Yes, in the public portfolio', release_text = ?, release_version = 'local-test',
        id_verified = ?, drive_status = ?, discount_percent = ?, shoot_date = ?, shoot_location = 'Studio, Minneapolis MN' WHERE id = ?")
        ->execute([$now - 30 * $day, $name, $name === 'Sam Demo' ? 'Sammy D' : '', $releaseText, $idv, $drive === 'saved' ? 'saved' : '', 15, $idv, $cid[$name]]);
}

// ---------- private client galleries ----------
echo "Making sample photos (takes a few seconds)...\n";
$g1 = add_gallery(['title' => 'Sam Demo: Portrait session', 'client_name' => 'Sam Demo', 'client_email' => 'sam@example.test', 'client_id' => $cid['Sam Demo'],
    'included_count' => 5, 'default_web_cents' => 1000, 'default_price_cents' => 2500, 'note' => 'Heart your favorites. Five are included with your session.', 'created' => $now - 35 * $day]);
$p1 = add_photos($g1, 'Sam', 14);
$g2 = add_gallery(['title' => 'Quinn Mockup: Event coverage', 'client_name' => 'Quinn Mockup', 'client_email' => 'quinn@example.test', 'client_id' => $cid['Quinn Mockup'],
    'password_hash' => password_hash('fake-gallery-pass', PASSWORD_DEFAULT), 'included_count' => 0, 'default_web_cents' => 500, 'default_price_cents' => 2000, 'created' => $now - 70 * $day]);
$p2 = add_photos($g2, 'Quinn', 8, 20);
$g3 = add_gallery(['title' => 'Avery Placeholder: Headshots', 'client_name' => 'Avery Placeholder', 'client_email' => 'avery@example.test', 'client_id' => $cid['Avery Placeholder'],
    'included_count' => 3, 'default_web_cents' => 0, 'default_price_cents' => 1500, 'created' => $now - 15 * $day]);
$p3 = add_photos($g3, 'Avery', 6, 40);
$g4 = add_gallery(['title' => 'Old test gallery (turned off)', 'client_name' => 'Drew Archive', 'client_email' => 'drew@example.test', 'client_id' => $cid['Drew Archive'], 'active' => 0, 'created' => $now - 210 * $day]);
add_photos($g4, 'Old', 3, 60);
db()->prepare('UPDATE images SET price_cents = 0 WHERE id = ?')->execute([$p1[13]]);          // one Free photo
db()->prepare('UPDATE images SET price_web_cents = 1500 WHERE id = ?')->execute([$p1[12]]);    // one custom price

$t = fn(int $daysAgo) => $now - $daysAgo * $day + mt_rand(0, 20000);
foreach ([$p1[0], $p1[2], $p1[3], $p1[6], $p1[8], $p1[9], $p1[11], $p3[1], $p3[4]] as $i) db()->prepare('INSERT INTO favorites (image_id, created) VALUES (?,?)')->execute([$i, $t(10)]);
foreach ([$p1[0], $p1[2], $p1[3]] as $i) db()->prepare('INSERT INTO claims (image_id, created) VALUES (?,?)')->execute([$i, $t(9)]);
$com = db()->prepare('INSERT INTO comments (image_id, author, body, from_owner, created) VALUES (?,?,?,?,?)');
$com->execute([$p1[2], 'Sam Demo', 'Could this one be a little brighter?', 0, $t(8)]);
$com->execute([$p1[2], 'Andrew', 'Sure, I will send an edited version.', 1, $t(7)]);
$com->execute([$p1[6], 'Sam Demo', 'Love this one!', 0, $t(3)]);
$com->execute([$p3[4], 'Avery Placeholder', 'Is there a version without the background?', 0, $t(1)]);
$ord = db()->prepare('INSERT INTO orders (gallery_id, image_id, email, status, tier, provider_ref, created) VALUES (?,?,?,?,?,?,?)');
$ord->execute([$g1, $p1[8], 'sam@example.test', 'requested', 'full', '', $t(2)]);
$ord->execute([$g1, $p1[9], 'sam@example.test', 'paid', 'web', 'manual', $t(6)]);
$ord->execute([$g2, $p2[1], 'quinn@example.test', 'requested', 'web', '', $t(1)]);
$ord->execute([$g2, $p2[3], 'quinn@example.test', 'declined', 'full', '', $t(20)]);
db()->prepare('INSERT INTO downloads (image_id, created) VALUES (?,?)')->execute([$p1[9], $t(5)]);

// ---------- public shop orders (each one is a small 'shop' order gallery) ----------
foreach ([['buyer1@example.test', 2, 'paid', 4], ['buyer2@example.test', 1, 'requested', 1]] as $k => [$email, $n, $st, $ago]) {
    $gs = add_gallery(['title' => 'Photo order', 'client_email' => $email, 'default_web_cents' => 500, 'default_price_cents' => 2500, 'kind' => 'shop', 'created' => $t($ago)]);
    foreach (add_photos($gs, 'Shop', $n, 80 + $k * 5) as $i) $ord->execute([$gs, $i, $email, $st, 'full', $st === 'paid' ? 'cs_test_fake' . $k : '', $t($ago)]);
}

// ---------- 120 days of visitor stats ----------
$pages = ['/', '/events/', '/portraits/', '/services/', '/contact/', '/series/tclfw/', '/series/iml-imbb/', '/series/twin-cities-pride/', '/about/'];
$refs = ['', '', '', 'instagram.com', 'facebook.com', 'google.com', 'mnleatherpride.org'];
$sd = db()->prepare('INSERT INTO stat_days (day, hits, visitors) VALUES (?,?,?)');
$sh = db()->prepare('INSERT INTO stat_hits (day, path, ref, dev, hits) VALUES (?,?,?,?,?) ON CONFLICT(day, path, ref, dev) DO UPDATE SET hits = hits + excluded.hits');
$tz = new DateTimeZone('America/Chicago');
for ($d = 119; $d >= 0; $d--) {
    $date = (new DateTime('@' . ($now - $d * $day)))->setTimezone($tz)->format('Y-m-d');
    $base = 30 + (int)(20 * sin($d / 9)) + mt_rand(0, 25) + ($d % 7 < 2 ? 25 : 0); $hits = 0;
    foreach ($pages as $k => $p) { $n = max(0, (int)($base / ($k + 1.5)) + mt_rand(-2, 3)); if ($n) { $sh->execute([$date, $p, $refs[mt_rand(0, count($refs) - 1)], mt_rand(0, 2) ? 'phone' : 'desktop', $n]); $hits += $n; } }
    $sd->execute([$date, $hits, (int)($hits * 0.45)]);
}

echo "Fake data ready: 8 clients, 4 client galleries, 2 shop orders, 120 days of stats.\n";
