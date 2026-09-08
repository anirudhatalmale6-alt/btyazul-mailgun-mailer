<?php
/**
 * send_live_test.php - the end-to-end test. This one REALLY sends.
 *
 * Run it once the .env has a real API key and the DNS records are in place:
 *
 *     php tools/send_live_test.php you@yourdomain.com
 *
 * What it does, in order:
 *   1. Checks the address is not already on a Mailgun suppression list
 *      (bounced / complained / unsubscribed before).
 *   2. Sends a real order confirmation to that address.
 *   3. Sends a real account notification to that address.
 *   4. Waits, then reads Mailgun's own event log to confirm each message was
 *      actually DELIVERED - not merely accepted for sending.
 *
 * Point it only at an address you own. Do not aim it at a customer.
 */

require_once __DIR__ . '/../bootstrap.php';

$recipient = $argv[1] ?? '';

if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php tools/send_live_test.php your-address@example.com\n");
    exit(1);
}

if (Env::bool('MAILGUN_DRY_RUN', false)) {
    fwrite(STDERR, "MAILGUN_DRY_RUN is still 1 in your .env - nothing would be sent.\n");
    fwrite(STDERR, "Set MAILGUN_DRY_RUN=0 to run the real test.\n");
    exit(1);
}

$client = new Mailgun(
    Env::require_('MAILGUN_API_KEY'),
    Env::require_('MAILGUN_DOMAIN'),
    Env::get('MAILGUN_REGION', 'us'),
    false,
    Env::get('MAIL_LOG_FILE', null)
);

echo "Testing against domain: " . Env::require_('MAILGUN_DOMAIN') . "\n";
echo "Recipient: {$recipient}\n\n";

// -- 1. Suppression check ----------------------------------------------------
echo "1. Checking Mailgun suppression lists\n";
$blocked = false;
foreach (['bounces', 'complaints', 'unsubscribes'] as $list) {
    $result = $client->checkSuppression($list, $recipient);

    if ($result['status'] === 200) {
        $blocked = true;
        $reason  = $result['data']['error'] ?? ($result['data']['reason'] ?? 'listed');
        echo "   ON THE {$list} LIST - {$reason}\n";
        echo "   Mailgun will refuse to deliver here until you remove it.\n";
    } elseif ($result['status'] === 404) {
        echo "   not on {$list} - good\n";
    } else {
        // Anything else is an error. Do NOT report it as a clean address.
        echo "   could not check {$list} (HTTP {$result['status']}) - treat as unknown\n";
    }
}

if ($blocked) {
    echo "\nStopping: this address is suppressed, so a send would prove nothing.\n";
    echo "Remove it in Mailgun (Sending -> Suppressions) and run this again.\n";
    exit(1);
}

// -- 2 and 3. Real sends -----------------------------------------------------
echo "\n2. Sending order confirmation\n";
$order = Mailer::orderConfirmation([
    'email'        => $recipient,
    'customer'     => 'End-to-end test',
    'order_number' => 'TEST-' . gmdate('YmdHis'),
    'items'        => [['name' => 'Test item', 'qty' => 1, 'price' => 1.00]],
    'subtotal'     => 1.00,
    'shipping'     => 0.00,
    'total'        => 1.00,
]);
printf("   ok=%s  http=%d  id=%s  %s\n",
    $order['ok'] ? 'yes' : 'NO', $order['status'], $order['id'] ?: '-', $order['message']);

echo "\n3. Sending account notification\n";
$account = Mailer::accountNotification([
    'email'      => $recipient,
    'customer'   => 'End-to-end test',
    'type'       => 'password_reset',
    'action_url' => Env::get('SITE_URL', 'https://example.com') . '/reset-password?token=TEST',
    'expires_in' => '1 hour',
]);
printf("   ok=%s  http=%d  id=%s  %s\n",
    $account['ok'] ? 'yes' : 'NO', $account['status'], $account['id'] ?: '-', $account['message']);

if (!$order['ok'] || !$account['ok']) {
    echo "\nAt least one send was rejected by Mailgun. Fix that before checking delivery.\n";
    exit(1);
}

// -- 4. Confirm real delivery ------------------------------------------------
// "Accepted" only means Mailgun took the message. Delivery is a separate event
// that arrives seconds later, so we poll for it rather than assume it.
echo "\n4. Waiting for Mailgun to report delivery (up to 60s)\n";

$wanted = [];
foreach ([$order, $account] as $result) {
    if ($result['id']) {
        // Mailgun reports ids wrapped in angle brackets in the event log.
        $wanted[trim($result['id'], '<>')] = false;
    }
}

$deadline = time() + 60;
while (time() < $deadline && in_array(false, $wanted, true)) {
    sleep(10);

    $events = $client->events(['recipient' => $recipient, 'limit' => 50]);
    if (!$events['ok']) {
        echo "   could not read the event log (HTTP {$events['status']})\n";
        continue;
    }

    foreach ($events['items'] as $event) {
        $id    = trim($event['message']['headers']['message-id'] ?? '', '<>');
        $name  = $event['event'] ?? '';

        if ($id !== '' && array_key_exists($id, $wanted) && $name === 'delivered') {
            $wanted[$id] = true;
        }

        // A failure is just as important to report as a success.
        if ($id !== '' && array_key_exists($id, $wanted) && in_array($name, ['failed', 'rejected'], true)) {
            $reason = $event['delivery-status']['message'] ?? ($event['reason'] ?? 'no reason given');
            echo "   FAILED {$id}: {$reason}\n";
            $wanted[$id] = 'failed';
        }
    }

    $done = count(array_filter($wanted, function ($v) { return $v !== false; }));
    echo "   " . $done . " of " . count($wanted) . " confirmed so far\n";
}

echo "\nRESULT\n";
$allDelivered = true;
foreach ($wanted as $id => $state) {
    if ($state === true) {
        echo "   DELIVERED  {$id}\n";
    } elseif ($state === 'failed') {
        echo "   FAILED     {$id}\n";
        $allDelivered = false;
    } else {
        // Not proof of failure - Mailgun's event log can lag. Say so honestly.
        echo "   NOT YET CONFIRMED  {$id} (still queued, or the event log is lagging)\n";
        $allDelivered = false;
    }
}

echo $allDelivered
    ? "\nBoth emails were confirmed delivered by Mailgun.\n"
    : "\nNot everything was confirmed. Check Mailgun -> Sending -> Logs.\n";

exit($allDelivered ? 0 : 1);
