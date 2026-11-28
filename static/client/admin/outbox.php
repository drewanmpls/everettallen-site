<?php
// Admin > Studio > Outbox (T-34): client email waiting to go out, sent, and failed, with "Send now" and "Retry".
// Emails are added by outbox_queue() in lib.php, from "Email this client" on a client page (T-35). Cron runs cli/send-outbox.php every 5 minutes.
// Also Templates (T-35): ?a=templates edits the email templates, POST templatesave saves one. Included by index.php after the admin login check.
declare(strict_types=1);

if ($post) {
    if (!csrf_ok()) { http_response_code(400); echo 'Session expired. Go back, reload the page and try again.'; exit; }
    if ($a === 'mailsend') { $r = outbox_send_due(); go($base . '?a=outbox&' . ($r['busy'] ? 'busy=1' : 'sent=' . $r['sent'] . '&failed=' . $r['failed'])); }
    if ($a === 'mailqueue') {   // from "Email this client": queue the previewed text. On a problem, return to index.php, which shows the client page with the text kept.
        $cs = db()->prepare('SELECT id, email FROM clients WHERE id = ?'); $cs->execute([(int)($_POST['id'] ?? 0)]); $mc = $cs->fetch();
        if (!$mc) { http_response_code(404); echo 'Client not found.'; exit; }
        $mailDraft = ['subject' => (string)($_POST['subject'] ?? ''), 'body' => (string)($_POST['body'] ?? ''), 'tpl' => (string)($_POST['tpl'] ?? ''), 'g' => (int)($_POST['g'] ?? 0), 'err' => ''];
        if (str_contains($mailDraft['subject'] . $mailDraft['body'], '[Draft wording needed')) $mailDraft['err'] = 'This email still contains "' . EMAIL_DRAFT . '", so it was not queued. Write the real wording in Templates, or replace the marker here.';
        else try { outbox_queue((int)$mc['id'], (string)$mc['email'], $mailDraft['subject'], $mailDraft['body']); go($base . '?a=client&id=' . (int)$mc['id'] . '&queued=1#email'); }
        catch (InvalidArgumentException $e) { $mailDraft['err'] = $e->getMessage() . ' It was not queued.'; }
        $a = 'client'; $_GET['id'] = (string)$mc['id']; $_GET['tab'] = 'overview'; $post = false;   // skip index.php's POST block (it ends in a redirect)
        return;
    }
    if ($a === 'templatesave') {
        $k = (string)($_POST['k'] ?? ''); $name = trim(preg_replace('/\s+/', ' ', (string)($_POST['name'] ?? ''))); $subj = trim((string)($_POST['subject'] ?? '')); $body = str_replace("\r\n", "\n", (string)($_POST['body'] ?? ''));
        if (!isset(email_templates()[$k])) go($base . '?a=templates');
        $err = $name === '' || mb_strlen($name) > 80 ? 'The name needs to be 1 to 80 characters.' : ($subj === '' || preg_match('/[\r\n]/', $subj) || mb_strlen($subj) > 200 ? 'The subject needs to be one line of 200 characters or fewer.' : (trim($body) === '' || mb_strlen($body) > 10000 ? 'The message needs to be between 1 and 10,000 characters.' : ''));
        if ($err === '') { db()->prepare('UPDATE email_templates SET name = ?, subject = ?, body = ? WHERE k = ?')->execute([$name, $subj, $body, $k]); go($base . '?a=templates&saved=' . rawurlencode($k) . '#t-' . rawurlencode($k)); }
        $tplErr = [$k, $err, $name, $subj, $body];
    }
    if ($a === 'mailretry') { db()->prepare("UPDATE outbox SET status = 'queued', attempts = 0, last_error = '' WHERE id = ? AND status = 'failed'")->execute([(int)($_POST['id'] ?? 0)]); go($base . '?a=outbox&retry=1'); }
}
if ($a === 'templates' || $a === 'templatesave') {
    $tpls = email_templates(); ahead('Email templates'); $tok = csrf(); ?>
<div class="ph"><div><p class="back"><a href="?a=outbox">&larr; Outbox</a></p><h1>Email templates</h1></div></div>
<p class="hint">Used by "Email this client" on a client page. You can edit the filled-in email before it is queued. Placeholders that are filled in for you:
<?php foreach (EMAIL_PLACEHOLDERS as $ph => $what): ?><code><?= h($ph) ?></code> <?= h($what) ?><?= $ph === array_key_last(EMAIL_PLACEHOLDERS) ? '.' : ',' ?> <?php endforeach; ?>
An email that still contains <?= h(EMAIL_DRAFT) ?> cannot be queued.</p>
<?php foreach ($tpls as $k => $t): $bad = isset($tplErr) && $tplErr[0] === $k; if ($bad) [, , $t['name'], $t['subject'], $t['body']] = $tplErr; ?>
<section class="panel mt" id="t-<?= h($k) ?>"><h2 class="mt0"><?= h($t['name']) ?><?= str_contains($t['subject'] . $t['body'], '[Draft wording needed') ? ' <span class="tag wait">needs wording</span>' : '' ?></h2>
<?php if ($bad): ?><p class="msg bad" role="alert"><?= h($tplErr[1]) ?> Not saved.</p><?php elseif (($_GET['saved'] ?? '') === $k): ?><p class="msg good" role="status">Saved.</p><?php endif; ?>
<form method="post" class="cols"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="templatesave"><input type="hidden" name="k" value="<?= h($k) ?>">
<label>Name<input name="name" value="<?= h($t['name']) ?>" maxlength="80" required></label>
<label>Subject<input name="subject" value="<?= h($t['subject']) ?>" maxlength="200" required></label>
<label class="full">Message (plain text)<textarea name="body" rows="10" maxlength="10000" required><?= h($t['body']) ?></textarea></label>
<div class="full"><button class="btn sm">Save <?= h($t['name']) ?></button></div></form></section>
<?php endforeach; ?>
<?php afoot(); exit;
}
ahead('Outbox'); $tok = csrf();
$rows = db()->query('SELECT o.*, c.name FROM outbox o LEFT JOIN clients c ON c.id = o.client_id ORDER BY COALESCE(o.sent, o.created) DESC, o.id DESC LIMIT 500')->fetchAll();
$by = ['queued' => [], 'failed' => [], 'sent' => []];
foreach ($rows as $r) $by[$r['status']][] = $r;
$heads = ['queued' => 'Waiting to send', 'failed' => 'Failed', 'sent' => 'Sent']; ?>
<div class="ph"><div><h1>Outbox</h1></div><div class="act"><a class="btn alt" href="?a=templates">Templates</a><form method="post"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="mailsend"><button class="btn"<?= $by['queued'] ? '' : ' disabled' ?>>Send now</button></form></div></div>
<p class="hint">Email to clients waits here and goes out every 5 minutes, or right away with Send now. If sending fails 5 times the email moves to Failed, where Retry puts it back in line.</p>
<?php if (isset($_GET['sent'])): $s = (int)$_GET['sent']; $f = (int)($_GET['failed'] ?? 0); ?><p class="msg <?= $f ? 'bad' : 'good' ?>" role="status"><?= $s ?> <?= $s === 1 ? 'email' : 'emails' ?> sent.<?= $f ? ' ' . $f . ' could not be sent; the error is shown below.' : '' ?></p><?php endif; ?>
<?php if (!empty($_GET['busy'])): ?><p class="msg bad" role="status">The outbox is already sending. Wait a minute and reload.</p><?php endif; ?>
<?php if (!empty($_GET['retry'])): ?><p class="msg good" role="status">Back in line. It goes out with the next send.</p><?php endif; ?>
<?php if (!$rows): ?><p>Nothing in the outbox yet.</p><?php endif; ?>
<?php foreach ($heads as $st => $title): if (!$by[$st]) continue; ?>
<h2><?= h($title) ?> <span class="tag <?= ['queued' => 'wait', 'failed' => 'bad', 'sent' => 'good'][$st] ?>"><?= count($by[$st]) ?></span></h2>
<table class="stack"><tr><th>To</th><th>Subject</th><th><?= $st === 'sent' ? 'Sent' : 'Queued' ?></th><?php if ($st !== 'sent'): ?><th>Error</th><?php endif; ?><?php if ($st === 'failed'): ?><th></th><?php endif; ?></tr>
<?php foreach ($by[$st] as $r): ?><tr><td data-label="To" class="brk"><?= $r['client_id'] && $r['name'] !== null ? '<a href="?a=client&amp;id=' . (int)$r['client_id'] . '">' . h($r['name']) . '</a><br><small>' . h($r['to_email']) . '</small>' : h($r['to_email']) ?></td>
<td data-label="Subject"><?= h($r['subject']) ?></td>
<td data-label="<?= $st === 'sent' ? 'Sent' : 'Queued' ?>"><?= h(date('M j, Y g:ia', (int)($st === 'sent' ? $r['sent'] : $r['created']))) ?></td>
<?php if ($st !== 'sent'): ?><td data-label="Error"><?= $r['last_error'] !== '' ? h($r['last_error']) . ' <small>(' . (int)$r['attempts'] . ' of 5 tries)</small>' : '<small>None</small>' ?></td><?php endif; ?>
<?php if ($st === 'failed'): ?><td><form method="post"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="mailretry"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn sm alt">Retry</button></form></td><?php endif; ?></tr>
<?php endforeach; ?></table>
<?php endforeach; ?>
<?php afoot(); exit;
