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

// Returns a Stripe Checkout URL for one order, or null (the caller then uses the manual flow).
function create_checkout(array $order, array $image, array $gallery): ?string {
    return create_checkout_multi([$order + ['label' => (string)($image['label'] ?? '')]], $gallery);
}

// One Stripe Checkout for several orders (a cart). Each order is a line item; all are marked paid together.
function create_checkout_multi(array $orders, array $gallery): ?string {
    $total = 0; foreach ($orders as $o) $total += (int)$o['price_cents'];
    if (!$orders || !payments_enabled() || $total < 50) return null;   // Stripe's minimum charge is $0.50
    $items = []; $ids = [];
    foreach ($orders as $o) {
        $name = $o['tier'] === 'web' ? 'Digital photo download (web size)' : 'Digital photo download (full resolution)';
        $items[] = ['quantity' => 1, 'price_data' => ['currency' => 'usd', 'unit_amount' => (int)$o['price_cents'], 'product_data' => ['name' => $name, 'description' => trim('Everett Allen Photography ' . ($o['label'] ?? ''))]]];
        $ids[] = (int)$o['id'];
    }
    $idList = implode(',', $ids);
    $s = stripe_api('POST', 'checkout/sessions', [
        'mode' => 'payment',
        'client_reference_id' => (string)$ids[0],
        'customer_email' => $orders[0]['email'],
        'success_url' => gallery_url($gallery) . '?sid={CHECKOUT_SESSION_ID}',
        'cancel_url' => gallery_url($gallery),
        'line_items' => $items,
        'metadata' => ['order_id' => (string)$ids[0], 'order_ids' => $idList],
        'payment_intent_data' => ['description' => 'Everett Allen Photography digital download', 'metadata' => ['order_id' => (string)$ids[0], 'order_ids' => $idList]],
    ]);
    if (!$s || empty($s['url'])) return null;
    $up = db()->prepare('UPDATE orders SET provider_ref = ? WHERE id = ?');
    foreach ($ids as $id) $up->execute([$s['id'], $id]);
    return (string)$s['url'];
}

// Which orders does a Checkout Session cover? (the cart's list, else the single order)
function session_order_ids(array $s): array {
    $raw = (string)(($s['metadata']['order_ids'] ?? '') ?: ($s['client_reference_id'] ?? ''));
    return array_values(array_filter(array_map('intval', explode(',', $raw))));
}

// After Stripe sends the buyer back: ask Stripe directly whether this session was paid. Returns true if it was.
function stripe_confirm_session(string $sid): bool {
    if (!preg_match('/^cs_[A-Za-z0-9_]+$/', $sid)) return false;
    $s = stripe_api('GET', 'checkout/sessions/' . $sid);
    if (!$s || ($s['payment_status'] ?? '') !== 'paid' || !($ids = session_order_ids($s))) return false;
    mark_orders_paid($ids, $sid);
    return true;
}

function send_client_mail(string $to, string $subject, string $body): void {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return;
    @mail($to, $subject, $body, "From: " . MAIL_FROM . "\r\nReply-To: " . OWNER_EMAIL . "\r\nContent-Type: text/plain; charset=UTF-8\r\n");
}

// Marks one order paid (once).
function mark_order_paid(int $orderId, string $providerRef = ''): void { mark_orders_paid([$orderId], $providerRef); }

// Marks orders paid (each once), then sends ONE email with a link back to the gallery and tells Andrew.
function mark_orders_paid(array $orderIds, string $providerRef = ''): void {
    $done = []; $gid = 0;
    foreach ($orderIds as $orderId) {
        $o = db()->prepare('SELECT * FROM orders WHERE id = ?'); $o->execute([(int)$orderId]); $o = $o->fetch();
        if (!$o || $o['status'] === 'paid') continue;
        db()->prepare("UPDATE orders SET status = 'paid', provider_ref = ? WHERE id = ?")->execute([$providerRef !== '' ? $providerRef : (string)$o['provider_ref'], (int)$o['id']]);
        $done[] = $o; $gid = (int)$o['gallery_id'];
    }
    if (!$done) return;
    $g = db()->prepare('SELECT * FROM galleries WHERE id = ?'); $g->execute([$gid]); $g = $g->fetch();
    if (!$g) return;
    $n = count($done); $email = (string)$done[0]['email'];
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $url = 'https://' . $host . '/client/g/' . $g['token'] . '/';
    send_client_mail($email, $n > 1 ? 'Your photo downloads are ready' : 'Your photo download is ready',
        ($n > 1 ? "Thank you! Your $n photos are unlocked." : 'Thank you! Your photo is unlocked.') . " Open your gallery and tap a photo, then Download:\n\n$url\n\nKeep this email: the link works any time you want to download again. If anything looks off, just reply.\n\nAndrew\nEverett Allen Photography\n");
    $ids = implode(', #', array_map(fn($o) => $o['id'], $done));
    send_owner_mail('Paid: "' . $g['title'] . '"', "Order" . ($n > 1 ? 's' : '') . " #$ids ($email, $n photo" . ($n > 1 ? 's' : '') . ") " . ($n > 1 ? 'are' : 'is') . " paid" . ($providerRef !== '' ? " via Stripe ($providerRef)" : '') . ". The client was emailed their download link.\n\nhttps://$host/client/admin/?a=gallery&id=" . $gid . "\n");
}
