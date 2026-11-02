<?php
// Payments: Stripe Checkout (hosted page) with the manual "Request" flow as the automatic backup.
// - Stripe is used only when switched on in Admin > Settings and a secret key is saved.
// - If Stripe is off, missing, too small an amount, or errors out, the request falls back to the manual flow:
//   the request is emailed to Andrew and saved as an order with status 'requested'; marking it paid in the admin unlocks the download.
// - No Stripe library is needed: this talks to Stripe's HTTPS API directly.
declare(strict_types=1);

function payments_enabled(): bool {
    return setting('stripe_enabled', '') === '1' && trim((string)setting('stripe_secret_key', '')) !== '';
}

function stripe_api(string $method, string $path, array $fields = []): ?array {
    $key = trim((string)setting('stripe_secret_key', ''));
    if ($key === '') return null;
    $ch = curl_init((getenv('EA_STRIPE_BASE') ?: 'https://api.stripe.com/v1/') . ltrim($path, '/'));
    $opt = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_USERPWD => $key . ':', CURLOPT_HTTPHEADER => ['Stripe-Version: 2024-06-20']];
    if ($method === 'POST') { $opt[CURLOPT_POST] = true; $opt[CURLOPT_POSTFIELDS] = http_build_query($fields); }
    curl_setopt_array($ch, $opt);
    $res = curl_exec($ch); curl_close($ch);
    $j = is_string($res) ? json_decode($res, true) : null;
    return is_array($j) && !isset($j['error']) ? $j : null;
}

function gallery_url(array $g): string {
    return 'https://' . ($_SERVER['HTTP_HOST'] ?? '') . '/client/g/' . $g['token'] . '/';
}

// Returns a Stripe Checkout URL for the order, or null (the caller then uses the manual flow).
function create_checkout(array $order, array $image, array $gallery): ?string {
    if (!payments_enabled() || (int)$order['price_cents'] < 50) return null;   // Stripe's minimum charge is $0.50
    $name = $order['tier'] === 'web' ? 'Digital photo download (web size)' : 'Digital photo download (full resolution)';
    $s = stripe_api('POST', 'checkout/sessions', [
        'mode' => 'payment',
        'client_reference_id' => (string)$order['id'],
        'customer_email' => $order['email'],
        'success_url' => gallery_url($gallery) . '?sid={CHECKOUT_SESSION_ID}',
        'cancel_url' => gallery_url($gallery),
        'line_items' => [['quantity' => 1, 'price_data' => ['currency' => 'usd', 'unit_amount' => (int)$order['price_cents'], 'product_data' => ['name' => $name, 'description' => 'Everett Allen Photography']]]],
        'metadata' => ['order_id' => (string)$order['id']],
        'payment_intent_data' => ['description' => 'Everett Allen Photography digital download', 'metadata' => ['order_id' => (string)$order['id']]],
    ]);
    if (!$s || empty($s['url'])) return null;
    db()->prepare('UPDATE orders SET provider_ref = ? WHERE id = ?')->execute([$s['id'], (int)$order['id']]);
    return (string)$s['url'];
}

// After Stripe sends the buyer back: ask Stripe directly whether this session was paid. Returns true if it was.
function stripe_confirm_session(string $sid): bool {
    if (!preg_match('/^cs_[A-Za-z0-9_]+$/', $sid)) return false;
    $s = stripe_api('GET', 'checkout/sessions/' . $sid);
    if (!$s || ($s['payment_status'] ?? '') !== 'paid' || empty($s['client_reference_id'])) return false;
    mark_order_paid((int)$s['client_reference_id'], $sid);
    return true;
}

function send_client_mail(string $to, string $subject, string $body): void {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return;
    @mail($to, $subject, $body, "From: " . MAIL_FROM . "\r\nReply-To: " . OWNER_EMAIL . "\r\nContent-Type: text/plain; charset=UTF-8\r\n");
}

// Marks an order paid (once), then emails the client a link back to their gallery and tells Andrew.
function mark_order_paid(int $orderId, string $providerRef = ''): void {
    $o = db()->prepare('SELECT o.*, g.token, g.title AS gtitle FROM orders o JOIN galleries g ON g.id = o.gallery_id WHERE o.id = ?'); $o->execute([$orderId]); $o = $o->fetch();
    if (!$o || $o['status'] === 'paid') return;
    db()->prepare("UPDATE orders SET status = 'paid', provider_ref = ? WHERE id = ?")->execute([$providerRef !== '' ? $providerRef : (string)$o['provider_ref'], $orderId]);
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $url = 'https://' . $host . '/client/g/' . $o['token'] . '/';
    send_client_mail((string)$o['email'], 'Your photo download is ready',
        "Thank you! Your photo is unlocked. Open your gallery and tap Download on it:\n\n$url\n\nIf anything looks off, just reply to this email.\n\nAndrew\nEverett Allen Photography\n");
    send_owner_mail('Paid: "' . $o['gtitle'] . '"', "Order #$orderId (" . $o['email'] . ") is paid" . ($providerRef !== '' ? " via Stripe ($providerRef)" : '') . ". The client was emailed their download link.\n\nhttps://$host/client/admin/?a=gallery&id=" . $o['gallery_id'] . "\n");
}
