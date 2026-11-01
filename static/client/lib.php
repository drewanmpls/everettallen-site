<?php
// Shared helpers for private client galleries (DreamHost PHP + SQLite).
// Everything private (database, original photos, watermarked copies) lives OUTSIDE the public web folder.
declare(strict_types=1);

const OWNER_EMAIL = 'andrew@everettallenphotography.com';
const OWNER_BCC   = 'thedrewan@gmail.com';
const MAIL_FROM   = 'email@everettallenphotography.com';

function host_key(): string {
    $h = strtolower($_SERVER['HTTP_HOST'] ?? 'local');
    $h = preg_replace('/^www\./', '', $h);
    return preg_replace('/[^a-z0-9.]/', '', $h) ?: 'local';
}

function priv_dir(): string {
    $env = getenv('EA_PRIVATE');
    $d = $env ?: rtrim($_SERVER['DOCUMENT_ROOT'] ?? '.', '/') . '/../everettallen-private/' . host_key();
    if (!is_dir($d)) { @mkdir($d, 0700, true); }
    return $d;
}

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $pdo = new PDO('sqlite:' . priv_dir() . '/data.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS settings (k TEXT PRIMARY KEY, v TEXT);
    CREATE TABLE IF NOT EXISTS galleries (
        id INTEGER PRIMARY KEY AUTOINCREMENT, token TEXT UNIQUE NOT NULL, title TEXT NOT NULL,
        client_name TEXT DEFAULT '', client_email TEXT DEFAULT '', password_hash TEXT DEFAULT '',
        default_price_cents INTEGER NOT NULL DEFAULT 0, note TEXT DEFAULT '',
        active INTEGER NOT NULL DEFAULT 1, created INTEGER NOT NULL);
    CREATE TABLE IF NOT EXISTS images (
        id INTEGER PRIMARY KEY AUTOINCREMENT, gallery_id INTEGER NOT NULL REFERENCES galleries(id) ON DELETE CASCADE,
        file TEXT NOT NULL, label TEXT DEFAULT '', sort INTEGER NOT NULL DEFAULT 0, w INTEGER, h INTEGER,
        price_cents INTEGER, created INTEGER NOT NULL);
    CREATE TABLE IF NOT EXISTS favorites (image_id INTEGER PRIMARY KEY REFERENCES images(id) ON DELETE CASCADE, created INTEGER NOT NULL);
    CREATE TABLE IF NOT EXISTS comments (
        id INTEGER PRIMARY KEY AUTOINCREMENT, image_id INTEGER NOT NULL REFERENCES images(id) ON DELETE CASCADE,
        author TEXT DEFAULT '', body TEXT NOT NULL, from_owner INTEGER NOT NULL DEFAULT 0, created INTEGER NOT NULL);
    CREATE TABLE IF NOT EXISTS orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT, gallery_id INTEGER NOT NULL, image_id INTEGER NOT NULL REFERENCES images(id) ON DELETE CASCADE,
        email TEXT DEFAULT '', status TEXT NOT NULL DEFAULT 'requested', provider_ref TEXT DEFAULT '', created INTEGER NOT NULL);
    CREATE TABLE IF NOT EXISTS downloads (id INTEGER PRIMARY KEY AUTOINCREMENT, image_id INTEGER NOT NULL, created INTEGER NOT NULL);
    CREATE TABLE IF NOT EXISTS claims (image_id INTEGER PRIMARY KEY REFERENCES images(id) ON DELETE CASCADE, created INTEGER NOT NULL);
    ");
    foreach ([['galleries', 'included_count', 'INTEGER NOT NULL DEFAULT 0'], ['galleries', 'default_web_cents', 'INTEGER NOT NULL DEFAULT 0'],
              ['images', 'price_web_cents', 'INTEGER'], ['orders', 'tier', "TEXT NOT NULL DEFAULT 'full'"]] as [$t, $c, $def]) {
        $has = false;
        foreach ($pdo->query("PRAGMA table_info($t)") as $col) if ($col['name'] === $c) $has = true;
        if (!$has) $pdo->exec("ALTER TABLE $t ADD COLUMN $c $def");
    }
    return $pdo;
}

function setting(string $k, ?string $default = null): ?string {
    $s = db()->prepare('SELECT v FROM settings WHERE k = ?'); $s->execute([$k]);
    $r = $s->fetchColumn();
    return $r === false ? $default : (string)$r;
}
function set_setting(string $k, string $v): void {
    db()->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON CONFLICT(k) DO UPDATE SET v = excluded.v')->execute([$k, $v]);
}

function start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('eapc');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/client', 'httponly' => true, 'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}
function csrf(): string {
    start_session();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_ok(): bool {
    start_session();
    $t = $_SERVER['HTTP_X_CSRF'] ?? ($_POST['csrf'] ?? '');
    return is_string($t) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
}

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function money(int $cents): string {
    if ($cents <= 0) return 'Free';
    return '$' . ($cents % 100 === 0 ? (string)($cents / 100) : number_format($cents / 100, 2));
}
function dollars_to_cents(string $s): int {
    $s = trim(str_replace(['$', ','], '', $s));
    if ($s === '' || strtolower($s) === 'free') return 0;
    return max(0, (int)round(((float)$s) * 100));
}

function gallery_by_token(string $t): ?array {
    if (!preg_match('/^[a-f0-9]{24}$/', $t)) return null;
    $s = db()->prepare('SELECT * FROM galleries WHERE token = ? AND active = 1'); $s->execute([$t]);
    return $s->fetch() ?: null;
}
// Full-resolution price (for printing). Per-photo override, else the gallery default.
function image_price(array $img, array $g): int {
    return $img['price_cents'] === null ? (int)$g['default_price_cents'] : (int)$img['price_cents'];
}
// Web-size price (for social sharing).
function image_price_web(array $img, array $g): int {
    return ($img['price_web_cents'] ?? null) === null ? (int)$g['default_web_cents'] : (int)$img['price_web_cents'];
}
function gallery_authorized(array $g): bool {
    if ($g['password_hash'] === '') return true;
    start_session();
    return !empty($_SESSION['g' . $g['id']]);
}
function image_is_claimed(int $imageId): bool {
    $s = db()->prepare('SELECT 1 FROM claims WHERE image_id = ?'); $s->execute([$imageId]);
    return (bool)$s->fetchColumn();
}
function claims_used(int $gid): int {
    $s = db()->prepare('SELECT COUNT(*) FROM claims c JOIN images i ON i.id = c.image_id WHERE i.gallery_id = ?'); $s->execute([$gid]);
    return (int)$s->fetchColumn();
}
function image_paid_tier(int $imageId): string {   // '', 'web' or 'full'
    $s = db()->prepare("SELECT tier FROM orders WHERE image_id = ? AND status = 'paid'"); $s->execute([$imageId]);
    $best = '';
    foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $t) { if ($t === 'full') return 'full'; $best = 'web'; }
    return $best;
}
// Can the client download this size ('web' or 'full') of this photo?
function can_download(array $img, array $g, string $tier = 'full'): bool {
    $id = (int)$img['id'];
    $full = image_price($img, $g) <= 0 || image_is_claimed($id) || image_paid_tier($id) === 'full';
    if ($tier === 'full') return $full;
    return $full || image_price_web($img, $g) <= 0 || image_paid_tier($id) !== '';
}
function was_downloaded(int $imageId): bool {
    $s = db()->prepare('SELECT 1 FROM downloads WHERE image_id = ? LIMIT 1'); $s->execute([$imageId]);
    return (bool)$s->fetchColumn();
}

function send_owner_mail(string $subject, string $body, string $replyTo = ''): void {
    $headers = "From: " . MAIL_FROM . "\r\nBcc: " . OWNER_BCC . "\r\nContent-Type: text/plain; charset=UTF-8\r\n";
    if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) $headers .= "Reply-To: $replyTo\r\n";
    @mail(OWNER_EMAIL, $subject, $body, $headers);
}

function gdir(int $gid, string $kind): string {
    $d = priv_dir() . "/galleries/$gid/$kind";
    if (!is_dir($d)) @mkdir($d, 0700, true);
    return $d;
}

// ---------- watermark + derivatives ----------

function wm_logo() {
    static $logo = false;
    if ($logo !== false) return $logo;
    $p = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '.', '/') . '/assets/logo-white.png';
    if (is_file($p) && ($im = @imagecreatefrompng($p))) { imagesavealpha($im, true); return $logo = $im; }
    // fallback: white text on a transparent tile
    $txt = 'EVERETT ALLEN PHOTOGRAPHY'; $fw = imagefontwidth(5); $fh = imagefontheight(5);
    $im = imagecreatetruecolor($fw * strlen($txt), $fh);
    imagealphablending($im, false); imagesavealpha($im, true);
    imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
    imagestring($im, 5, 0, 0, $txt, imagecolorallocate($im, 255, 255, 255));
    return $logo = $im;
}

function wm_apply($im, float $widthFrac = 0.5, float $opacity = 0.4): void {
    $logo = wm_logo(); if (!$logo) return;
    $w = imagesx($im); $h = imagesy($im);
    $lw = max(40, (int)($w * $widthFrac)); $lh = max(10, (int)(imagesy($logo) * $lw / imagesx($logo)));
    $s = imagecreatetruecolor($lw, $lh);
    imagealphablending($s, false); imagesavealpha($s, true);
    imagefill($s, 0, 0, imagecolorallocatealpha($s, 0, 0, 0, 127));
    imagecopyresampled($s, $logo, 0, 0, 0, 0, $lw, $lh, imagesx($logo), imagesy($logo));
    for ($y = 0; $y < $lh; $y++) for ($x = 0; $x < $lw; $x++) {
        $c = imagecolorat($s, $x, $y); $a = ($c >> 24) & 127;
        if ($a >= 127) continue;
        $na = 127 - (int)round((127 - $a) * $opacity);
        imagesetpixel($s, $x, $y, imagecolorallocatealpha($s, ($c >> 16) & 255, ($c >> 8) & 255, $c & 255, $na));
    }
    imagealphablending($im, true);
    imagecopy($im, $s, (int)(($w - $lw) / 2), (int)(($h - $lh) / 2), 0, 0, $lw, $lh);
    imagedestroy($s);
}

function resize_to($src, int $long) {
    $w = imagesx($src); $h = imagesy($src); $scale = min(1, $long / max($w, $h));
    $nw = max(1, (int)round($w * $scale)); $nh = max(1, (int)round($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    return $dst;
}

// Takes an uploaded JPEG on disk; stores the untouched original privately and builds watermarked display + thumb.
function ingest_image(int $gid, string $tmp, string $origName): array {
    @ini_set('memory_limit', '512M'); @set_time_limit(120);
    $f = new finfo(FILEINFO_MIME_TYPE);
    if ($f->file($tmp) !== 'image/jpeg') throw new RuntimeException('Only JPEG files are supported.');
    $data = file_get_contents($tmp);
    $src = @imagecreatefromstring($data);
    if (!$src) throw new RuntimeException('Could not read that image.');
    if (function_exists('exif_read_data')) {
        $ex = @exif_read_data($tmp);
        $o = $ex['Orientation'] ?? 1;
        if ($o == 3) $src = imagerotate($src, 180, 0);
        elseif ($o == 6) $src = imagerotate($src, -90, 0);
        elseif ($o == 8) $src = imagerotate($src, 90, 0);
    }
    $name = bin2hex(random_bytes(10)) . '.jpg';
    if (!copy($tmp, gdir($gid, 'orig') . '/' . $name)) throw new RuntimeException('Could not store the original.');
    $disp = resize_to($src, 1600); wm_apply($disp, 0.5, 0.4);
    imagejpeg($disp, gdir($gid, 'display') . '/' . $name, 84);
    $thumb = resize_to($src, 520); wm_apply($thumb, 0.6, 0.45);
    imagejpeg($thumb, gdir($gid, 'thumb') . '/' . $name, 80);
    $web = resize_to($src, 2048); imagejpeg($web, gdir($gid, 'web') . '/' . $name, 88);
    $w = imagesx($disp); $h = imagesy($disp);
    return ['file' => $name, 'w' => $w, 'h' => $h, 'label' => preg_replace('/\.jpe?g$/i', '', basename($origName))];
}

// Web-size copy (2048px, no watermark) for social sharing. Built on upload; rebuilt on demand if missing.
function ensure_web(int $gid, string $file): string {
    $p = gdir($gid, 'web') . '/' . basename($file);
    if (is_file($p)) return $p;
    @ini_set('memory_limit', '512M');
    $src = @imagecreatefromjpeg(gdir($gid, 'orig') . '/' . basename($file));
    if (!$src) return '';
    $web = resize_to($src, 2048);
    imagejpeg($web, $p, 88);
    return $p;
}

function json_out(array $d, int $code = 200): void {
    http_response_code($code); header('Content-Type: application/json'); header('Cache-Control: no-store');
    echo json_encode($d); exit;
}
function noindex(): void { header('X-Robots-Tag: noindex, nofollow, noarchive'); }
