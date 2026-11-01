<?php
// Public new-client intake form: /client/new/
declare(strict_types=1);
require __DIR__ . '/lib.php';
noindex();
start_session();
$services = services_list();
$err = ''; $v = [];
$fields = ['name', 'email', 'phone', 'pronouns', 'service', 'timeframe', 'location', 'handle', 'referral', 'share_pref', 'release_name', 'message'];
$offer = share_offer();

if (isset($_GET['done'])) { page_top('Thank you'); ?>
<main class="narrow"><p class="kicker">Intake received</p><h1>Thank you</h1>
<p>I got your details and I'll email you soon to talk about your session. If it's urgent, use the <a href="/contact/">contact page</a>.</p>
<p><a class="btn" href="/">Back to the site</a></p></main></body></html>
<?php exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($fields as $f) $v[$f] = trim(strip_tags((string)($_POST[$f] ?? '')));
    $ipHash = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . 'ea-intake');
    $tooFast = (time() - (int)($_SESSION['intake_t'] ?? 0)) < 4;
    $recent = db()->prepare('SELECT COUNT(*) FROM clients WHERE ip_hash = ? AND created > ?'); $recent->execute([$ipHash, time() - 3600]);
    if (!empty($_POST['website']) || !csrf_ok()) { header('Location: /client/new/?done=1'); exit; }   // bots get a quiet thank-you
    if ($v['name'] === '') $err = 'Please enter your name.';
    elseif (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) $err = 'Please enter a valid email so I can reply.';
    elseif ($v['service'] === '') $err = 'Please choose a service (or "Not sure yet").';
    elseif ($v['share_pref'] === 'Yes' && $offer['text'] !== '' && strcasecmp(trim($v['release_name']), $v['name']) !== 0) $err = 'To grant the photo release, please type your full name exactly as entered above, or choose a different sharing option.';
    elseif ($tooFast) $err = 'That was quick! Please check your details and send again.';
    elseif ((int)$recent->fetchColumn() >= 5) $err = 'Too many submissions from this connection. Please try again later or use the contact page.';
    elseif (mb_strlen($v['message']) > 3000) $err = 'Your note is a little long. Please shorten it.';
    if ($err === '') {
        foreach ($v as $k => $x) $v[$k] = mb_substr($x, 0, $k === 'message' ? 3000 : 200);
        $rel = $v['share_pref'] === 'Yes' && $offer['text'] !== '';
        $relNote = $rel ? ' (PHOTO RELEASE SIGNED by ' . $v['release_name'] . ', ' . $offer['percent'] . '% discount)' : '';
        db()->prepare('INSERT INTO clients (name, email, phone, pronouns, service, timeframe, location, handle, referral, share_pref, message, status, source, ip_hash, created, release_text, release_name, release_at, discount_percent) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$v['name'], $v['email'], $v['phone'], $v['pronouns'], $v['service'], $v['timeframe'], $v['location'], $v['handle'], $v['referral'], $v['share_pref'], $v['message'], 'new', 'form', $ipHash, time(), $rel ? $offer['text'] : '', $rel ? $v['release_name'] : '', $rel ? time() : null, $rel ? $offer['percent'] : 0]);
        $id = (int)db()->lastInsertId();
        send_owner_mail('New client intake: ' . $v['name'] . ' (' . $v['service'] . ')',
            "Name: {$v['name']}" . ($v['pronouns'] ? " ({$v['pronouns']})" : '') . "\nEmail: {$v['email']}\nPhone: {$v['phone']}\nService: {$v['service']}\nTimeframe: {$v['timeframe']}\nLocation ideas: {$v['location']}\nSocial: {$v['handle']}\nHeard about you: {$v['referral']}\nMay share photos: {$v['share_pref']}$relNote\n\nNote:\n{$v['message']}\n\nOpen in admin:\nhttps://" . ($_SERVER['HTTP_HOST'] ?? '') . "/client/admin/?a=client&id=$id\n", $v['email']);
        unset($_SESSION['intake_t']);
        header('Location: /client/new/?done=1'); exit;
    }
} else {
    $_SESSION['intake_t'] = time();
    $v = array_fill_keys($fields, '');
}

function page_top(string $title): void { ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title><?= h($title) ?> | Everett Allen Photography</title>
<link rel="icon" href="/assets/icon.png"><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@500;800&family=Archivo+Narrow:wght@500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/client/client.css"><style>.rel{border:1px solid var(--ink);padding:14px 16px;margin:0 0 14px}.rel legend{font-family:'Archivo Narrow',sans-serif;text-transform:uppercase;letter-spacing:.06em;font-size:14px;padding:0 6px}.relbox{background:#f6f3ec;border-left:4px solid var(--blue);padding:10px 14px;font-size:15px;margin:0 0 12px}.radio{text-transform:none;letter-spacing:0;font-family:'Archivo',sans-serif;font-size:16px;margin:0 0 8px;display:flex;gap:8px;align-items:flex-start}.radio input{margin-top:5px}.intake{max-width:720px;margin:0 auto;padding:0 24px 64px}.row2{display:grid;grid-template-columns:1fr 1fr;gap:0 16px}@media(max-width:600px){.row2{grid-template-columns:1fr}}.hp{position:absolute;left:-9999px}select{width:100%;font:inherit;padding:10px 12px;border:1px solid var(--ink);background:#f6f3ec;color:var(--ink);margin-top:4px}.opt{font-family:'Archivo',sans-serif;text-transform:none;letter-spacing:0;color:var(--mut);font-size:13px}</style></head><body>
<header class="top"><a href="/" class="brand">Everett Allen Photography</a><span class="kicker">New client</span></header>
<?php }

page_top('New client intake'); ?>
<div class="intake">
<h1>New client</h1>
<p class="note">Tell me a little about what you have in mind and I'll follow up by email. Rates are on the <a href="/services/">Services page</a>. Nothing here is a booking until we confirm together.</p>
<?php if ($err): ?><p class="err"><b><?= h($err) ?></b></p><?php endif; ?>
<form method="post" action=""><input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
<div class="row2"><label>Name<input name="name" value="<?= h($v['name']) ?>" required autocomplete="name"></label>
<label>Pronouns <span class="opt">(optional)</span><input name="pronouns" value="<?= h($v['pronouns']) ?>"></label></div>
<div class="row2"><label>Email<input type="email" name="email" value="<?= h($v['email']) ?>" required autocomplete="email"></label>
<label>Phone <span class="opt">(optional)</span><input name="phone" value="<?= h($v['phone']) ?>" autocomplete="tel"></label></div>
<label>Which service are you interested in?
<select name="service" required><option value="">Choose one</option>
<?php foreach ($services as $s): $t = $s['title'] . ' (' . $s['price'] . ($s['unit'] ? ' ' . $s['unit'] : '') . ')'; ?>
<option value="<?= h($s['title']) ?>" <?= $v['service'] === $s['title'] ? 'selected' : '' ?>><?= h($t) ?></option>
<?php endforeach; ?>
<option value="Not sure yet" <?= $v['service'] === 'Not sure yet' ? 'selected' : '' ?>>Not sure yet</option>
<option value="Something else" <?= $v['service'] === 'Something else' ? 'selected' : '' ?>>Something else</option></select></label>
<label>When are you thinking? <span class="opt">(dates, season, or a deadline)</span><input name="timeframe" value="<?= h($v['timeframe']) ?>"></label>
<label>Location ideas <span class="opt">(studio, outdoors, your place, an event venue)</span><input name="location" value="<?= h($v['location']) ?>"></label>
<div class="row2"><label>Instagram or website <span class="opt">(optional)</span><input name="handle" value="<?= h($v['handle']) ?>"></label>
<label>How did you hear about me? <span class="opt">(optional)</span><input name="referral" value="<?= h($v['referral']) ?>"></label></div>
<fieldset class="rel"><legend>Sharing photos from your session</legend>
<?php if ($offer['text'] !== '' && $offer['percent'] > 0): ?><p class="relbox"><b>Save <?= (int)$offer['percent'] ?>% on your session</b> by allowing me to share your photos.<br><span id="reltext"><?= h($offer['text']) ?></span></p><?php endif; ?>
<?php $opts = ['Yes' => ($offer['percent'] > 0 ? 'Yes, I agree to the release above and get ' . (int)$offer['percent'] . '% off' : 'Yes, sharing is fine'), 'Ask first' => 'Ask me first, each time (no discount)', 'Private' => 'No, keep my photos private (no discount)', '' => 'I will decide later']; foreach ($opts as $k => $t): ?>
<label class="radio"><input type="radio" name="share_pref" value="<?= h((string)$k) ?>" <?= (string)$v['share_pref'] === (string)$k ? 'checked' : '' ?> style="width:auto"> <?= h($t) ?></label>
<?php endforeach; ?>
<label id="signrow" style="display:none">To sign the release, type your full name <span class="opt">(same as your name above)</span><input name="release_name" value="<?= h($v['release_name']) ?>" autocomplete="off"></label></fieldset>
<label>Anything else I should know? <span class="opt">(ideas, mood, outfits, questions)</span><textarea name="message" rows="5"><?= h($v['message']) ?></textarea></label>
<input class="hp" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
<button class="btn" type="submit">Send</button></form></div>
<script>(function(){var r=document.querySelectorAll('input[name=share_pref]'),s=document.getElementById('signrow');function u(){var y=false;r.forEach(function(x){if(x.checked&&x.value==='Yes')y=true});s.style.display=y?'block':'none'}r.forEach(function(x){x.addEventListener('change',u)});u()})();</script></body></html>
