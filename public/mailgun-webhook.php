<?php
/**
 * mailgun-webhook.php - receives bounce and spam-complaint reports from Mailgun.
 *
 * WHAT THIS IS FOR
 * When an email cannot be delivered (address does not exist, mailbox full) or
 * a customer marks it as spam, Mailgun sends a small notice to this page. We
 * record it so you can see WHY a customer says they never got their email.
 *
 * WHERE TO PUT IT
 * Inside your public web folder, so Mailgun can reach it. For example:
 *     public_html/mailgun-webhook.php   ->  https://btyazul.com/mailgun-webhook.php
 *
 * THEN, in the Mailgun dashboard:
 *     Sending -> Webhooks -> add that URL for the events:
 *     Permanent Failure, Temporary Failure, Spam Complaints, Unsubscribes, Delivered
 *
 * SECURITY
 * Anyone on the internet can POST to this URL, so every request is checked
 * against Mailgun's signature before we believe a word of it. Without that
 * check, someone could invent fake bounces for your customers.
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Mailgun only ever POSTs here.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo 'Method not allowed';
    exit;
}

$raw     = file_get_contents('php://input');
$payload = json_decode($raw, true);

// Modern Mailgun sends JSON. Very old webhook versions send form fields, so
// fall back to $_POST rather than silently dropping the event.
if (!is_array($payload)) {
    $payload = $_POST;
}

$signature = isset($payload['signature']) && is_array($payload['signature'])
    ? $payload['signature']
    : [
        'timestamp' => $payload['timestamp']  ?? '',
        'token'     => $payload['token']      ?? '',
        'signature' => $payload['signature']  ?? '',
    ];

$signingKey = Env::get('MAILGUN_WEBHOOK_SIGNING_KEY', '');

if ($signingKey === '') {
    // Refuse rather than accept unverified data. A missing key is a setup
    // mistake, and accepting anything would be worse than failing.
    http_response_code(500);
    echo 'Webhook signing key is not configured.';
    exit;
}

if (!mailgun_signature_is_valid($signature, $signingKey)) {
    http_response_code(406); // Mailgun stops retrying on 406.
    echo 'Invalid signature.';
    exit;
}

$event     = $payload['event-data'] ?? $payload;
$eventName = $event['event'] ?? 'unknown';
$recipient = $event['recipient'] ?? '';
$reason    = $event['reason'] ?? ($event['delivery-status']['message'] ?? '');
$code      = $event['delivery-status']['code'] ?? '';

// Our own variables, set when the message was sent, come back here. This is
// how a bounce is tied to a specific order.
$vars       = $event['user-variables'] ?? [];
$orderRef   = $vars['order_number'] ?? '';
$emailType  = $vars['email_type'] ?? '';

$line = sprintf(
    "[%s] %-18s to=%s code=%s type=%s order=%s reason=%s\n",
    gmdate('Y-m-d H:i:s') . ' UTC',
    $eventName,
    $recipient,
    $code,
    $emailType,
    $orderRef,
    str_replace(["\r", "\n"], ' ', (string) $reason)
);

$logPath = Env::get('MAIL_SUPPRESSION_LOG', '');
if ($logPath !== '') {
    $dir = dirname($logPath);
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    @file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX);
}

// ---------------------------------------------------------------------------
// OPTIONAL: mark the customer's record in your own database.
// Uncomment and point at your own table once we hook this into your site.
//
// if (in_array($eventName, ['failed', 'complained'], true) && $recipient !== '') {
//     $stmt = $pdo->prepare('UPDATE customers SET email_undeliverable = 1 WHERE email = ?');
//     $stmt->execute([$recipient]);
// }
// ---------------------------------------------------------------------------

// Mailgun retries anything that is not a 200, so always answer 200 once the
// event has been recorded.
http_response_code(200);
echo 'OK';

/**
 * Check that a webhook really came from Mailgun.
 *
 * Mailgun signs each call as HMAC-SHA256 of (timestamp + token) using your
 * webhook signing key. We recompute it and compare.
 *
 * @param array  $signature ['timestamp' => ..., 'token' => ..., 'signature' => ...]
 * @param string $key       The webhook signing key from the Mailgun dashboard.
 */
function mailgun_signature_is_valid(array $signature, $key)
{
    $timestamp = (string) ($signature['timestamp'] ?? '');
    $token     = (string) ($signature['token'] ?? '');
    $provided  = (string) ($signature['signature'] ?? '');

    if ($timestamp === '' || $token === '' || $provided === '') {
        return false;
    }

    // Reject anything older than 15 minutes, so a captured request cannot be
    // replayed back at us later.
    if (abs(time() - (int) $timestamp) > 900) {
        return false;
    }

    $expected = hash_hmac('sha256', $timestamp . $token, $key);

    // hash_equals compares in constant time, so an attacker cannot learn the
    // right signature one character at a time by measuring how long we take.
    return hash_equals($expected, $provided);
}
