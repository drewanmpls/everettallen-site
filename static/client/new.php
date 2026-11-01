<?php
// Public new-client intake form: /client/new/
declare(strict_types=1);
require __DIR__ . '/lib.php';
noindex();
start_session();
$services = services_list();
$offer = share_offer();
$hasRelease = $offer['terms'] !== [];
$err = ''; $v = [];
$fields = ['name', 'email', 'phone', 'pronouns', 'service', 'timeframe', 'location', 'handle', 'referral', 'share_pref', 'release_name', 'release_stage', 'release_dob', 'release_gallery', 'message'];

function page_top(string $title): void { ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title><?= h($title) ?> | Everett Allen Photography</title>
<link rel="icon" href="/assets/icon.png"><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@500;800&family=Archivo+Narrow:wght@500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/client/client.css"><style>
.intake{max-width:720px;margin:0 auto;padding:0 24px 64px}.row2{display:grid;grid-template-columns:1fr 1fr;gap:0 16px}@media(max-width:600px){.row2{grid-template-columns:1fr}}
.hp{position:absolute;left:-9999px}select{width:100%;font:inherit;padding:10px 12px;border:1px solid var(--ink);background:#f6f3ec;color:var(--ink);margin-top:4px}
.opt{font-family:'Archivo',sans-serif;text-transform:none;letter-spacing:0;color:var(--mut);font-size:13px}
.rel{border:1px solid var(--ink);padding:14px 16px;margin:0 0 14px}.rel legend{font-family:'Archivo Narrow',sans-serif;text-transform:uppercase;letter-spacing:.06em;font-size:14px;padding:0 6px}
.relbox{background:#f6f3ec;border-left:4px solid var(--blue);padding:10px 14px;font-size:15px;margin:0 0 12px}
.radio{text-transform:none;letter-spacing:0;font-family:'Archivo',sans-serif;font-size:16px;margin:0 0 8px;display:flex;gap:8px;align-items:flex-start}.radio input{margin-top:5px;width:auto}
.terms{max-height:260px;overflow:auto;background:#f6f3ec;border:1px solid var(--line);padding:12px 14px;font-size:14px;line-height:1.5;margin:0 0 14px}.terms p{margin:0 0 10px}.terms dt{font-weight:800;display:inline}.terms dd{display:inline;margin:0}.terms dl>div{margin-bottom:6px}
#sigwrap{border:1px solid var(--ink);background:#fff;touch-action:none}#sig{display:block;width:100%;height:160px}
.sigbar{display:flex;justify-content:space-between;align-items:center;font-size:13px;color:var(--mut);margin:4px 0 14px}
</style></head><body>
<header class="top"><a href="/" class="brand">Everett Allen Photography</a><span class="kicker">New client</span></header>
<?php }

if (isset($_GET['done'])) { page_top('Thank you'); ?>
<main class="narrow"><p class="kicker">Intake received</p><h1>Thank you</h1>
<p>I got your details and I'll email you soon to talk about your session. If it's urgent, use the <a href="/contact/">contact page</a>.</p>
<?php if (!empty($_GET['rel'])): ?><p>Your signed photo release is on file. I'll check your ID at the session, as the release explains.</p><?php endif; ?>
<p><a class="btn" href="/">Back to the site</a></p></main></body></html>
<?php exit; }

function age_ok(string $dob): bool {
    $d = DateTime::createFromFormat('Y-m-d', $dob);
    if (!$d || $d->format('Y-m-d') !== $dob) return false;
    $now = new DateTime('today');
    return $d > new DateTime('1900-01-01') && $d <= $now && $d->diff($now)->y >= 18;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($fields as $f) $v[$f] = trim(strip_tags((string)($_POST[$f] ?? '')));
    $ipHash = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . 'ea-intake');
    $tooFast = (time() - (int)($_SESSION['intake_t'] ?? 0)) < 4;
    $recent = db()->prepare('SELECT COUNT(*) FROM clients WHERE ip_hash = ? AND created > ?'); $recent->execute([$ipHash, time() - 3600]);
    if (!empty($_POST['website']) || !csrf_ok()) { header('Location: /client/new/?done=1'); exit; }   // bots get a quiet thank-you
    $wantsRelease = $v['share_pref'] === 'Yes' && $hasRelease;
    $sigJpeg = '';
    if ($wantsRelease) {
        $raw = (string)($_POST['release_sig'] ?? '');
        if (preg_match('#^data:image/jpeg;base64,([A-Za-z0-9+/=]+)$#', $raw, $m) && strlen($m[1]) < 600000) {
            $bin = base64_decode($m[1], true);
            if ($bin !== false && (new finfo(FILEINFO_MIME_TYPE))->buffer($bin) === 'image/jpeg') $sigJpeg = $bin;
        }
    }
    if ($v['name'] === '') $err = 'Please enter your name.';
    elseif (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) $err = 'Please enter a valid email so I can reply.';
    elseif ($v['service'] === '') $err = 'Please choose a service (or "Not sure yet").';
    elseif ($wantsRelease && $v['release_name'] === '') $err = 'To sign the release, please enter your full legal name.';
    elseif ($wantsRelease && !age_ok($v['release_dob'])) $err = 'To sign the release you must be 18 or older. Please enter your date of birth, or choose a different sharing option.';
    elseif ($wantsRelease && empty($_POST['release_age'])) $err = 'Please confirm the release statements by ticking the box.';
    elseif ($wantsRelease && !in_array($v['release_gallery'], ['Yes', 'No'], true)) $err = 'Please answer the gallery display question.';
    elseif ($wantsRelease && $sigJpeg === '') $err = 'Please sign in the signature box with your finger or mouse.';
    elseif ($tooFast) $err = 'That was quick! Please check your details and send again.';
    elseif ((int)$recent->fetchColumn() >= 5) $err = 'Too many submissions from this connection. Please try again later or use the contact page.';
    elseif (mb_strlen($v['message']) > 3000) $err = 'Your note is a little long. Please shorten it.';
    if ($err === '') {
        foreach ($v as $k => $x) $v[$k] = mb_substr($x, 0, $k === 'message' ? 3000 : 200);
        $now = time();
        db()->prepare('INSERT INTO clients (name, email, phone, pronouns, service, timeframe, location, handle, referral, share_pref, message, status, source, ip_hash, created, release_text, release_name, release_at, discount_percent, release_stage, release_dob, release_gallery, release_version) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$v['name'], $v['email'], $v['phone'], $v['pronouns'], $v['service'], $v['timeframe'], $v['location'], $v['handle'], $v['referral'], $v['share_pref'], $v['message'], 'new', 'form', $ipHash, $now,
                $wantsRelease ? $offer['text'] : '', $wantsRelease ? $v['release_name'] : '', $wantsRelease ? $now : null, $wantsRelease ? $offer['percent'] : 0,
                $wantsRelease ? $v['release_stage'] : '', $wantsRelease ? $v['release_dob'] : '', $wantsRelease ? $v['release_gallery'] : '', $wantsRelease ? $offer['version'] : '']);
        $id = (int)db()->lastInsertId();
        $relNote = '';
        if ($wantsRelease) {
            $sp = release_dir() . '/' . $id . '-sig.jpg'; file_put_contents($sp, $sigJpeg);
            db()->prepare('UPDATE clients SET release_sig = ? WHERE id = ?')->execute([$sp, $id]);
            save_release_pdf($id);
            $dr = drive_upload($id);
            $relNote = ' (PHOTO RELEASE SIGNED by ' . $v['release_name'] . ', ' . $offer['percent'] . '% discount; Google Drive: ' . ($dr['ok'] ? 'saved' : 'NOT saved, ' . ($dr['error'] ?? '')) . ')';
        }
        send_owner_mail('New client intake: ' . $v['name'] . ' (' . $v['service'] . ')',
            "Name: {$v['name']}" . ($v['pronouns'] ? " ({$v['pronouns']})" : '') . "\nEmail: {$v['email']}\nPhone: {$v['phone']}\nService: {$v['service']}\nTimeframe: {$v['timeframe']}\nLocation ideas: {$v['location']}\nSocial: {$v['handle']}\nHeard about you: {$v['referral']}\nMay share photos: {$v['share_pref']}$relNote\n\nNote:\n{$v['message']}\n\nOpen in admin:\nhttps://" . ($_SERVER['HTTP_HOST'] ?? '') . "/client/admin/?a=client&id=$id\n", $v['email']);
        unset($_SESSION['intake_t']);
        header('Location: /client/new/?done=1' . ($wantsRelease ? '&rel=1' : '')); exit;
    }
} else {
    $_SESSION['intake_t'] = time();
    $v = array_fill_keys($fields, '');
}

page_top('New client intake'); ?>
<div class="intake">
<h1>New client</h1>
<p class="note">Tell me a little about what you have in mind and I'll follow up by email. Rates are on the <a href="/services/">Services page</a>. Nothing here is a booking until we confirm together.</p>
<?php if ($err): ?><p class="err"><b><?= h($err) ?></b></p><?php endif; ?>
<form method="post" action="" id="intake"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
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
<?php if ($hasRelease && $offer['percent'] > 0): ?><p class="relbox"><b>Save <?= (int)$offer['percent'] ?>% on your session</b> by signing a model release that lets me use your photos. You can read it below before you choose.</p><?php endif; ?>
<?php $opts = ['Yes' => ($hasRelease ? 'Yes, I will sign the model release' . ($offer['percent'] > 0 ? ' and get ' . (int)$offer['percent'] . '% off' : '') : 'Yes, sharing is fine'), 'Ask first' => 'Ask me first, each time (no discount)', 'Private' => 'No, keep my photos private (no discount)', '' => 'I will decide later'];
foreach ($opts as $k => $t): ?><label class="radio"><input type="radio" name="share_pref" value="<?= h((string)$k) ?>" <?= (string)$v['share_pref'] === (string)$k ? 'checked' : '' ?>> <?= h($t) ?></label><?php endforeach; ?>

<?php if ($hasRelease): ?>
<div id="release" style="display:none;margin-top:14px">
<h3 style="margin:0 0 8px;font-family:'Archivo Narrow',sans-serif;text-transform:uppercase;letter-spacing:.06em"><?= h($offer['title']) ?></h3>
<div class="terms" tabindex="0">
<?php foreach ($offer['terms'] as $t): ?><p><?= h($t) ?></p><?php endforeach; ?>
<?php if ($offer['definitions']): ?><p><b>Definitions</b></p><dl style="margin:0"><?php foreach ($offer['definitions'] as $df): ?><div><dt><?= h($df['term']) ?>:</dt> <dd><?= h($df['text']) ?></dd></div><?php endforeach; ?></dl><?php endif; ?>
<?php if ($offer['consideration'] !== ''): ?><p><b><?= h($offer['consideration']) ?></b></p><?php endif; ?>
</div>
<div class="row2"><label>Full legal name<input name="release_name" value="<?= h($v['release_name']) ?>" autocomplete="off"></label>
<label>Stage name <span class="opt">(optional)</span><input name="release_stage" value="<?= h($v['release_stage']) ?>"></label></div>
<div class="row2"><label>Date of birth<input type="date" name="release_dob" value="<?= h($v['release_dob']) ?>" max="<?= date('Y-m-d') ?>"></label>
<label><?= h($offer['gallery_question']) ?><select name="release_gallery"><option value="">Choose</option><option <?= $v['release_gallery'] === 'Yes' ? 'selected' : '' ?>>Yes</option><option <?= $v['release_gallery'] === 'No' ? 'selected' : '' ?>>No</option></select></label></div>
<label class="radio"><input type="checkbox" name="release_age" value="1"> I have read and agree to this release, I am at least 18 years old, and I will show my government-issued ID at the session.</label>
<p style="margin:10px 0 4px;font-family:'Archivo Narrow',sans-serif;text-transform:uppercase;letter-spacing:.06em;font-size:14px">Sign here (finger or mouse)</p>
<div id="sigwrap"><canvas id="sig" width="640" height="200"></canvas></div>
<div class="sigbar"><span>Your signature, your full legal name and today's date are saved with this release.</span><button type="button" id="sigclear" class="btn sm alt">Clear</button></div>
<input type="hidden" name="release_sig" id="release_sig">
</div>
<?php endif; ?>
</fieldset>

<label>Anything else I should know? <span class="opt">(ideas, mood, outfits, questions)</span><textarea name="message" rows="5"><?= h($v['message']) ?></textarea></label>
<input class="hp" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
<button class="btn" type="submit">Send</button></form></div>
<script>(function(){
var radios=document.querySelectorAll('input[name=share_pref]'),rel=document.getElementById('release');
if(!rel)return;
var cv=document.getElementById('sig'),ctx=cv.getContext('2d'),drawing=false,inked=false;
ctx.fillStyle='#fff';ctx.fillRect(0,0,cv.width,cv.height);ctx.lineWidth=3;ctx.lineCap='round';ctx.lineJoin='round';ctx.strokeStyle='#111';
function pos(e){var r=cv.getBoundingClientRect();return[(e.clientX-r.left)*cv.width/r.width,(e.clientY-r.top)*cv.height/r.height]}
cv.addEventListener('pointerdown',function(e){drawing=true;inked=true;var p=pos(e);ctx.beginPath();ctx.moveTo(p[0],p[1]);cv.setPointerCapture(e.pointerId);e.preventDefault()});
cv.addEventListener('pointermove',function(e){if(!drawing)return;var p=pos(e);ctx.lineTo(p[0],p[1]);ctx.stroke();e.preventDefault()});
['pointerup','pointercancel'].forEach(function(n){cv.addEventListener(n,function(){drawing=false})});
document.getElementById('sigclear').onclick=function(){ctx.fillStyle='#fff';ctx.fillRect(0,0,cv.width,cv.height);inked=false;document.getElementById('release_sig').value=''};
function show(){var y=false;radios.forEach(function(x){if(x.checked&&x.value==='Yes')y=true});rel.style.display=y?'block':'none'}
radios.forEach(function(x){x.addEventListener('change',show)});show();
document.getElementById('intake').addEventListener('submit',function(e){
  var yes=false;radios.forEach(function(x){if(x.checked&&x.value==='Yes')yes=true});
  if(yes){if(!inked){e.preventDefault();alert('Please sign in the signature box first.');return}
    document.getElementById('release_sig').value=cv.toDataURL('image/jpeg',0.85)}
});
})();</script></body></html>
