<?php
/**
 * preview.php - writes the two emails to HTML files so you can open them in a
 * browser and see exactly what a customer would receive. Sends nothing.
 *
 * Run:  php tools/preview.php [output-folder]
 * Then open the .html files it names.
 */

require_once __DIR__ . '/../src/Env.php';
require_once __DIR__ . '/../src/Mailgun.php';
require_once __DIR__ . '/../src/Mailer.php';

$outDir = $argv[1] ?? (__DIR__ . '/../preview');
if (!is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}

// Catches the message instead of sending it.
class PreviewMailgun extends Mailgun
{
    public $sent = [];

    public function __construct()
    {
        parent::__construct('preview', 'mg.example.com', 'us', true, null);
    }

    public function send(array $message)
    {
        $this->sent[] = $message;

        return ['ok' => true, 'status' => 0, 'id' => null, 'message' => 'preview', 'dry_run' => true];
    }
}

// Sample settings, used only for the preview.
$tmpEnv = sys_get_temp_dir() . '/mailer_preview_' . getmypid() . '.env';
file_put_contents($tmpEnv, implode("\n", [
    'MAILGUN_API_KEY=preview',
    'MAILGUN_DOMAIN=mg.btyazul.com',
    'MAILGUN_WEBHOOK_SIGNING_KEY=preview',
    'MAIL_FROM_NAME=BT Yazul',
    'MAIL_FROM_ADDRESS=orders@mg.btyazul.com',
    'MAIL_REPLY_TO=info@btyazul.com',
    'MAILGUN_DRY_RUN=1',
    'SITE_URL=https://btyazul.com',
    'SITE_NAME=BT Yazul',
]));
Env::reset();
Env::load($tmpEnv);

$capture = new PreviewMailgun();
Mailer::setClient($capture);

// --- Sample order -----------------------------------------------------------
Mailer::orderConfirmation([
    'email'        => 'customer@example.com',
    'customer'     => 'Ana Ruiz',
    'order_number' => 'BT-1042',
    'order_date'   => '8 September 2026',
    'items'        => [
        ['name' => 'Sample product one',   'qty' => 2, 'price' => 12.50],
        ['name' => 'Sample product two',   'qty' => 1, 'price' => 40.00],
        ['name' => 'Sample product three', 'qty' => 3, 'price' => 4.00],
    ],
    'subtotal' => 77.00,
    'shipping' => 5.00,
    'total'    => 82.00,
    'currency' => '$',
    'ship_to'  => "12 Example Street\nApartment 4\nSpringfield, 10101",
]);

// --- Sample password reset --------------------------------------------------
Mailer::accountNotification([
    'email'      => 'customer@example.com',
    'customer'   => 'Ana Ruiz',
    'type'       => 'password_reset',
    'action_url' => 'https://btyazul.com/reset-password?token=SAMPLE',
    'expires_in' => '1 hour',
]);

// --- Sample welcome ---------------------------------------------------------
Mailer::accountNotification([
    'email'      => 'customer@example.com',
    'customer'   => 'Ana Ruiz',
    'type'       => 'welcome',
    'action_url' => 'https://btyazul.com/login',
]);

$names = ['order-confirmation', 'account-password-reset', 'account-welcome'];

foreach ($capture->sent as $i => $message) {
    $base = $outDir . '/' . $names[$i];
    file_put_contents($base . '.html', $message['html']);
    file_put_contents($base . '.txt', $message['text']);
    echo "wrote {$base}.html\n";
    echo "wrote {$base}.txt\n";
    echo "  subject: {$message['subject']}\n";
    echo "  from:    {$message['from']}\n\n";
}

@unlink($tmpEnv);
