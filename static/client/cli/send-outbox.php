<?php
// Sends queued client email from the outbox (T-34). Run by cron every 5 minutes, never from the web.
// Usage: php send-outbox.php /home/dh_dbvmkc/everettallen-private/everettallenphotography.com
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$priv = rtrim((string)($argv[1] ?? ''), '/');
if ($priv === '' || !is_file($priv . '/data.sqlite')) { fwrite(STDERR, "Outbox: give the private folder (the one with data.sqlite) as the first argument.\n"); exit(1); }
putenv('EA_PRIVATE=' . $priv);
require dirname(__DIR__) . '/lib.php';

$r = outbox_send_due();
if ($r['failed']) { fwrite(STDERR, "Outbox: {$r['sent']} sent, {$r['failed']} could not be sent (see Admin > Outbox).\n"); exit(1); }
