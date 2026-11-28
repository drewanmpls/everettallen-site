<?php
// Admin > Studio > Outbox (T-34): client email waiting to go out, sent, and failed, with "Send now" and "Retry".
// Emails are added by outbox_queue() in lib.php (T-35 adds "Email this client"). Cron runs cli/send-outbox.php every 5 minutes.
// Included by index.php after the admin login check.
declare(strict_types=1);

if ($post) {
    if (!csrf_ok()) { http_response_code(400); echo 'Session expired. Go back, reload the page and try again.'; exit; }
    if ($a === 'mailsend') { $r = outbox_send_due(); go($base . '?a=outbox&' . ($r['busy'] ? 'busy=1' : 'sent=' . $r['sent'] . '&failed=' . $r['failed'])); }
    if ($a === 'mailretry') { db()->prepare("UPDATE outbox SET status = 'queued', attempts = 0, last_error = '' WHERE id = ? AND status = 'failed'")->execute([(int)($_POST['id'] ?? 0)]); go($base . '?a=outbox&retry=1'); }
}
ahead('Outbox'); $tok = csrf();
$rows = db()->query('SELECT o.*, c.name FROM outbox o LEFT JOIN clients c ON c.id = o.client_id ORDER BY COALESCE(o.sent, o.created) DESC, o.id DESC LIMIT 500')->fetchAll();
$by = ['queued' => [], 'failed' => [], 'sent' => []];
foreach ($rows as $r) $by[$r['status']][] = $r;
$heads = ['queued' => 'Waiting to send', 'failed' => 'Failed', 'sent' => 'Sent']; ?>
<div class="ph"><div><h1>Outbox</h1></div><div class="act"><form method="post"><input type="hidden" name="csrf" value="<?= h($tok) ?>"><input type="hidden" name="a" value="mailsend"><button class="btn"<?= $by['queued'] ? '' : ' disabled' ?>>Send now</button></form></div></div>
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
