<?php
// Stripe webhook: marks orders paid when Stripe confirms the payment. Point Stripe at https://YOUR-SITE/client/stripe-webhook.php
// (event: checkout.session.completed, plus checkout.session.async_payment_succeeded). Signature is verified with the webhook secret from Admin > Settings.
declare(strict_types=1);
require __DIR__ . '/lib.php';
require __DIR__ . '/payments.php';
header('Cache-Control: no-store'); header('X-Robots-Tag: noindex, nofollow');
$secret = trim((string)setting('stripe_webhook_secret', ''));
$raw = (string)file_get_contents('php://input');
if ($secret === '' || $raw === '') { http_response_code(400); exit('not configured'); }
$sig = (string)($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? ''); $t = 0; $v1 = [];
foreach (explode(',', $sig) as $part) { $kv = explode('=', trim($part), 2); if (count($kv) !== 2) continue; if ($kv[0] === 't') $t = (int)$kv[1]; if ($kv[0] === 'v1') $v1[] = $kv[1]; }
$expected = hash_hmac('sha256', $t . '.' . $raw, $secret);
$ok = false; foreach ($v1 as $c) if (hash_equals($expected, $c)) $ok = true;
if (!$ok || abs(time() - $t) > 600) { http_response_code(400); exit('bad signature'); }
$ev = json_decode($raw, true);
if (is_array($ev) && in_array($ev['type'] ?? '', ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
    $s = $ev['data']['object'] ?? [];
    if (($s['payment_status'] ?? '') === 'paid' && ($ids = session_order_ids($s))) mark_orders_paid($ids, (string)($s['id'] ?? ''));
}
http_response_code(200); echo 'ok';
