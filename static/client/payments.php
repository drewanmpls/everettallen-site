<?php
// Payment provider hook. Nothing is wired up yet: the galleries work without it.
// Until a provider is configured, "Buy" becomes "Request this photo": the request is emailed to Andrew and
// saved as an order with status 'requested'. In the admin page, marking it 'paid' unlocks the clean download.
//
// To add a processor later, set a provider in the private config (setting 'payment_provider') and implement
// create_checkout() to return a URL to send the buyer to. The provider's webhook should then call
// mark_order_paid($orderId, $providerRef).
declare(strict_types=1);

function payments_enabled(): bool {
    return (setting('payment_provider', '') ?? '') !== '';
}

// Returns a hosted-checkout URL for the order, or null if no provider is set up.
function create_checkout(array $order, array $image, array $gallery): ?string {
    return null;
}

function mark_order_paid(int $orderId, string $providerRef = ''): void {
    db()->prepare("UPDATE orders SET status = 'paid', provider_ref = ? WHERE id = ?")->execute([$providerRef, $orderId]);
}
