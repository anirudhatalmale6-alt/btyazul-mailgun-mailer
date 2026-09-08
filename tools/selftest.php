<?php
/**
 * selftest.php - checks the mailer without sending anything.
 *
 * Run it from the command line:
 *     php tools/selftest.php
 *
 * It renders both emails with sample data, checks the live values actually
 * appear in the output, checks bad input is rejected, and checks the webhook
 * signature verification accepts a genuine call and rejects a forged one.
 *
 * Nothing here touches the network or the Mailgun account.
 */

require_once __DIR__ . '/../src/Env.php';
require_once __DIR__ . '/../src/Mailgun.php';
require_once __DIR__ . '/../src/Mailer.php';

$passed = 0;
$failed = 0;

/** Assert that a condition holds. */
function check($label, $condition)
{
    global $passed, $failed;

    if ($condition) {
        $passed++;
        echo "  PASS  {$label}\n";
    } else {
        $failed++;
        echo "  FAIL  {$label}\n";
    }
}

/** Assert that calling $fn throws, and that the message mentions $needle. */
function check_throws($label, callable $fn, $needle = '')
{
    try {
        $fn();
        check($label . ' (expected an error, got none)', false);
    } catch (Throwable $e) {
        check($label, $needle === '' || stripos($e->getMessage(), $needle) !== false);
    }
}

// ---------------------------------------------------------------------------
// A stand-in for the real Mailgun client. It records the message instead of
// sending it, so we can inspect exactly what would have gone out.
// ---------------------------------------------------------------------------
class CapturingMailgun extends Mailgun
{
    /** @var array<int,array> Every message passed to send(). */
    public $sent = [];

    public function __construct()
    {
        parent::__construct('test-key', 'mg.example.com', 'us', true, null);
    }

    public function send(array $message)
    {
        // Run the real validation first so the test still catches bad input.
        foreach (['to', 'subject', 'html', 'text', 'from'] as $required) {
            if (empty($message[$required])) {
                throw new InvalidArgumentException("Mailgun::send() missing '{$required}'");
            }
        }
        if (!filter_var($message['to'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Not a valid email address: {$message['to']}");
        }

        $this->sent[] = $message;

        return ['ok' => true, 'status' => 0, 'id' => 'captured', 'message' => 'captured', 'dry_run' => true];
    }
}

// ---------------------------------------------------------------------------
// Load a throwaway .env so Env::require_() is satisfied.
// ---------------------------------------------------------------------------
$tmpEnv = sys_get_temp_dir() . '/mailer_selftest_' . getmypid() . '.env';
file_put_contents($tmpEnv, implode("\n", [
    'MAILGUN_API_KEY=test-key',
    'MAILGUN_DOMAIN=mg.example.com',
    'MAILGUN_REGION=us',
    'MAILGUN_WEBHOOK_SIGNING_KEY=selftest-signing-key',
    'MAIL_FROM_NAME=BT Yazul',
    'MAIL_FROM_ADDRESS=orders@mg.example.com',
    'MAIL_REPLY_TO=info@example.com',
    'MAILGUN_DRY_RUN=1',
    'SITE_URL=https://example.com',
    'SITE_NAME=BT Yazul',
]));
Env::reset();
Env::load($tmpEnv);

$fake = new CapturingMailgun();
Mailer::setClient($fake);

// ---------------------------------------------------------------------------
echo "\nORDER CONFIRMATION\n";
// ---------------------------------------------------------------------------

$order = [
    'email'        => 'customer@example.com',
    // The angle brackets are deliberate: they prove the templates escape input.
    'customer'     => 'Ana <Test> Ruiz',
    'order_number' => 'BT-1042',
    'order_date'   => '8 September 2026',
    'items'        => [
        ['name' => 'Blue Widget', 'qty' => 2, 'price' => 12.5],
        ['name' => 'Red Gadget',  'qty' => 1, 'price' => 40],
    ],
    'subtotal' => 65.00,
    'shipping' => 5.00,
    'total'    => 70.00,
    'currency' => '$',
    'ship_to'  => "12 Example Street\nSpringfield",
];

Mailer::orderConfirmation($order);
check('one message was produced', count($fake->sent) === 1);

$msg  = $fake->sent[0];
$html = $msg['html'];
$text = $msg['text'];

check('subject carries the order number',      strpos($msg['subject'], 'BT-1042') !== false);
check('from header is name + address',         $msg['from'] === 'BT Yazul <orders@mg.example.com>');
check('reply-to is the normal domain',         $msg['reply_to'] === 'info@example.com');
check('tagged as order-confirmation',          $msg['tag'] === 'order-confirmation');
check('order number stored as a variable',     ($msg['variables']['order_number'] ?? '') === 'BT-1042');

check('HTML shows the order number',           strpos($html, 'BT-1042') !== false);
check('HTML shows both item names',            strpos($html, 'Blue Widget') !== false && strpos($html, 'Red Gadget') !== false);
check('HTML shows the total as $70.00',        strpos($html, '$70.00') !== false);
check('HTML shows subtotal and shipping',      strpos($html, '$65.00') !== false && strpos($html, '$5.00') !== false);
check('HTML formats 12.5 as $12.50',           strpos($html, '$12.50') !== false);
check('HTML shows the delivery address',       strpos($html, '12 Example Street') !== false);
check('HTML breaks the address across lines',  strpos($html, 'Example Street<br') !== false);

// Escaping: the raw "<Test>" must never appear, only its escaped form.
check('customer name is escaped in HTML',      strpos($html, '&lt;Test&gt;') !== false);
check('raw <Test> is NOT in the HTML',         strpos($html, '<Test>') === false);

check('text version shows the order number',   strpos($text, 'BT-1042') !== false);
check('text version shows the total',          strpos($text, '$70.00') !== false);
check('text version lists an item',            strpos($text, 'Blue Widget') !== false);
check('text version has no HTML tags',         strpos($text, '<table') === false && strpos($text, '<html') === false);

// POSITIVE CONTROL: prove the checks above can actually fail. If this reports
// present, then "contains X" is matching everything and means nothing.
check('control - an absent string is absent',  strpos($html, 'ZZ-NOT-IN-TEMPLATE-ZZ') === false);

// ---------------------------------------------------------------------------
echo "\nORDER CONFIRMATION - BAD INPUT IS REFUSED\n";
// ---------------------------------------------------------------------------

check_throws('missing total is refused', function () use ($order) {
    $bad = $order;
    unset($bad['total']);
    Mailer::orderConfirmation($bad);
}, 'total');

check_throws('empty items list is refused', function () use ($order) {
    $bad = $order;
    $bad['items'] = [];
    Mailer::orderConfirmation($bad);
}, 'items');

check_throws('malformed address is refused', function () use ($order) {
    $bad = $order;
    $bad['email'] = 'not-an-address';
    Mailer::orderConfirmation($bad);
}, 'valid email');

// ---------------------------------------------------------------------------
echo "\nACCOUNT NOTIFICATION\n";
// ---------------------------------------------------------------------------

$fake->sent = [];

Mailer::accountNotification([
    'email'      => 'customer@example.com',
    'customer'   => 'Ana Ruiz',
    'type'       => 'password_reset',
    'action_url' => 'https://example.com/reset?token=abc123',
    'expires_in' => '1 hour',
]);

check('one message was produced', count($fake->sent) === 1);

$msg  = $fake->sent[0];
$html = $msg['html'];
$text = $msg['text'];

check('subject mentions the password reset', stripos($msg['subject'], 'reset your') !== false);
check('tagged as account-notification',      $msg['tag'] === 'account-notification');
check('event stored as a variable',          ($msg['variables']['account_event'] ?? '') === 'password_reset');

check('HTML contains the reset link',        strpos($html, 'https://example.com/reset?token=abc123') !== false);
check('HTML shows the expiry line',          stripos($html, 'expires in 1 hour') !== false);
check('HTML shows the button wording',       stripos($html, 'Choose a new password') !== false);
check('text version contains the link',      strpos($text, 'https://example.com/reset?token=abc123') !== false);
check('text version shows the expiry',       stripos($text, 'expires in 1 hour') !== false);

// The welcome email has no expiry, so that line must not leak into it.
$fake->sent = [];
Mailer::accountNotification([
    'email'    => 'customer@example.com',
    'customer' => 'Ana Ruiz',
    'type'     => 'welcome',
]);
$welcome = $fake->sent[0];
check('welcome email is produced',           stripos($welcome['subject'], 'welcome') !== false);
check('welcome email has no expiry line',    stripos($welcome['html'], 'expires in') === false);

check_throws('unknown account type is refused', function () {
    Mailer::accountNotification(['email' => 'a@example.com', 'customer' => 'A', 'type' => 'nonsense']);
}, 'Unknown account notification type');

check_throws('generic without wording is refused', function () {
    Mailer::accountNotification(['email' => 'a@example.com', 'customer' => 'A', 'type' => 'generic']);
}, 'requires');

// ---------------------------------------------------------------------------
echo "\nWEBHOOK SIGNATURE CHECK\n";
// ---------------------------------------------------------------------------

// Pull in just the verification function, without running the webhook page.
$webhookSource = file_get_contents(__DIR__ . '/../public/mailgun-webhook.php');
$start = strpos($webhookSource, 'function mailgun_signature_is_valid');
eval(substr($webhookSource, $start));

$key       = 'selftest-signing-key';
$timestamp = (string) time();
$token     = 'abcdef0123456789';
$genuine   = hash_hmac('sha256', $timestamp . $token, $key);

check('a genuine signature is accepted', mailgun_signature_is_valid(
    ['timestamp' => $timestamp, 'token' => $token, 'signature' => $genuine], $key
));

check('a forged signature is rejected', !mailgun_signature_is_valid(
    ['timestamp' => $timestamp, 'token' => $token, 'signature' => str_repeat('0', 64)], $key
));

check('a signature for another key is rejected', !mailgun_signature_is_valid(
    ['timestamp' => $timestamp, 'token' => $token, 'signature' => $genuine], 'a-different-key'
));

// An old-but-correctly-signed call must still be refused, or a captured
// request could be replayed at us days later.
$old       = (string) (time() - 3600);
$oldSigned = hash_hmac('sha256', $old . $token, $key);
check('an hour-old replay is rejected', !mailgun_signature_is_valid(
    ['timestamp' => $old, 'token' => $token, 'signature' => $oldSigned], $key
));

check('a blank signature is rejected', !mailgun_signature_is_valid(
    ['timestamp' => '', 'token' => '', 'signature' => ''], $key
));

// ---------------------------------------------------------------------------
echo "\nDRY RUN GUARD\n";
// ---------------------------------------------------------------------------

// With MAILGUN_DRY_RUN=1 the real client must not attempt any network call.
$real   = new Mailgun('test-key', 'mg.example.com', 'us', true, null);
$result = $real->send([
    'to' => 'customer@example.com', 'from' => 'a@mg.example.com',
    'subject' => 'x', 'html' => '<p>x</p>', 'text' => 'x',
]);
check('dry run reports itself as a dry run', $result['dry_run'] === true);
check('dry run returns no Mailgun id',       $result['id'] === null);

@unlink($tmpEnv);

// ---------------------------------------------------------------------------
$total = $passed + $failed;
echo "\n-----------------------------------------\n";
echo "{$passed} passed, {$failed} failed, {$total} checks run\n";
exit($failed === 0 ? 0 : 1);
