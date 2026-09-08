<?php
/**
 * Mailer.php - the only file the website needs to talk to.
 *
 * Everything else is plumbing. From your own code you write two lines:
 *
 *   require_once '/path/to/mailer/bootstrap.php';
 *   Mailer::orderConfirmation([...order data...]);
 *
 * The two supported emails are:
 *   - orderConfirmation()     sent after a customer places an order
 *   - accountNotification()   sent for account events (welcome, password reset...)
 */

require_once __DIR__ . '/Env.php';
require_once __DIR__ . '/Mailgun.php';

class Mailer
{
    /** @var Mailgun|null Built once and reused. */
    private static $client = null;

    /**
     * Build (or reuse) the Mailgun client from the .env settings.
     */
    private static function client()
    {
        if (self::$client === null) {
            self::$client = new Mailgun(
                Env::require_('MAILGUN_API_KEY'),
                Env::require_('MAILGUN_DOMAIN'),
                Env::get('MAILGUN_REGION', 'us'),
                Env::bool('MAILGUN_DRY_RUN', false),
                Env::get('MAIL_LOG_FILE', null)
            );
        }

        return self::$client;
    }

    /** Lets the test scripts swap in a different client. */
    public static function setClient(Mailgun $client = null)
    {
        self::$client = $client;
    }

    /**
     * ORDER CONFIRMATION
     *
     * Call this immediately after an order is successfully saved.
     *
     * @param array $order Keys:
     *   email        (string, required) customer's address
     *   customer     (string, required) customer's name
     *   order_number (string, required) e.g. "BT-1042"
     *   order_date   (string, optional) e.g. "8 September 2026"
     *   items        (array,  required) each: ['name'=>, 'qty'=>, 'price'=>]
     *   subtotal     (string|float, optional)
     *   shipping     (string|float, optional)
     *   total        (string|float, required)
     *   currency     (string, optional) defaults to '$'
     *   ship_to      (string, optional) multi-line delivery address
     *
     * @return array The result from Mailgun::send()
     */
    public static function orderConfirmation(array $order)
    {
        foreach (['email', 'customer', 'order_number', 'items', 'total'] as $required) {
            if (!isset($order[$required]) || $order[$required] === '' || $order[$required] === []) {
                throw new InvalidArgumentException("orderConfirmation() missing '{$required}'");
            }
        }

        // Fill in the optional pieces so the templates never hit an undefined key.
        $data = $order + [
            'order_date' => gmdate('j F Y'),
            'subtotal'   => null,
            'shipping'   => null,
            'currency'   => '$',
            'ship_to'    => '',
            'site_name'  => Env::get('SITE_NAME', 'Our shop'),
            'site_url'   => Env::get('SITE_URL', ''),
        ];

        $subject = sprintf('Order %s confirmed - %s', $data['order_number'], $data['site_name']);

        return self::client()->send([
            'from'      => self::from(),
            'to'        => $data['email'],
            'reply_to'  => Env::get('MAIL_REPLY_TO', ''),
            'subject'   => $subject,
            'html'      => self::render('order_confirmation.html.php', $data),
            'text'      => self::render('order_confirmation.txt.php', $data),
            'tag'       => 'order-confirmation',
            // Stored with the message, so a later bounce tells us which order broke.
            'variables' => [
                'order_number' => $data['order_number'],
                'email_type'   => 'order_confirmation',
            ],
        ]);
    }

    /**
     * ACCOUNT NOTIFICATION
     *
     * One email covering account events. The wording changes with $type.
     *
     * @param array $account Keys:
     *   email      (string, required)
     *   customer   (string, required)
     *   type       (string, required) 'welcome' | 'password_reset' | 'password_changed'
     *                                 | 'email_changed' | 'generic'
     *   action_url (string, optional) button link, e.g. the reset link
     *   message    (string, optional) extra sentence, required when type = 'generic'
     *   expires_in (string, optional) e.g. "1 hour", shown for password resets
     *
     * @return array The result from Mailgun::send()
     */
    public static function accountNotification(array $account)
    {
        foreach (['email', 'customer', 'type'] as $required) {
            if (empty($account[$required])) {
                throw new InvalidArgumentException("accountNotification() missing '{$required}'");
            }
        }

        $siteName = Env::get('SITE_NAME', 'Our site');

        // Subject line and headline per event type. Add new types here.
        $copy = [
            'welcome' => [
                'subject'  => 'Welcome to ' . $siteName,
                'heading'  => 'Your account is ready',
                'body'     => 'Thanks for creating an account with us. You can sign in any time using the address this email was sent to.',
                'button'   => 'Sign in',
            ],
            'password_reset' => [
                'subject'  => 'Reset your ' . $siteName . ' password',
                'heading'  => 'Password reset requested',
                'body'     => 'We received a request to reset your password. Use the button below to choose a new one. If you did not ask for this, you can safely ignore this email and your password will stay as it is.',
                'button'   => 'Choose a new password',
            ],
            'password_changed' => [
                'subject'  => 'Your ' . $siteName . ' password was changed',
                'heading'  => 'Your password has been changed',
                'body'     => 'Your password was changed successfully. If this was not you, please contact us straight away.',
                'button'   => 'Go to your account',
            ],
            'email_changed' => [
                'subject'  => 'Your ' . $siteName . ' email address was updated',
                'heading'  => 'Email address updated',
                'body'     => 'The email address on your account has been updated. If this was not you, please contact us straight away.',
                'button'   => 'Go to your account',
            ],
            'generic' => [
                'subject'  => 'A message about your ' . $siteName . ' account',
                'heading'  => 'Account notice',
                'body'     => '',
                'button'   => 'Go to your account',
            ],
        ];

        $type = $account['type'];
        if (!isset($copy[$type])) {
            throw new InvalidArgumentException(
                "Unknown account notification type '{$type}'. Known types: " . implode(', ', array_keys($copy))
            );
        }

        // 'generic' has no built-in wording, so the caller must supply it.
        if ($type === 'generic' && empty($account['message'])) {
            throw new InvalidArgumentException("accountNotification() type 'generic' requires 'message'");
        }

        $data = $account + [
            'action_url' => Env::get('SITE_URL', ''),
            'message'    => '',
            'expires_in' => '',
        ];

        $data['heading']   = $copy[$type]['heading'];
        $data['intro']     = $copy[$type]['body'];
        $data['button']    = $copy[$type]['button'];
        $data['site_name'] = $siteName;
        $data['site_url']  = Env::get('SITE_URL', '');

        return self::client()->send([
            'from'      => self::from(),
            'to'        => $data['email'],
            'reply_to'  => Env::get('MAIL_REPLY_TO', ''),
            'subject'   => $copy[$type]['subject'],
            'html'      => self::render('account_notification.html.php', $data),
            'text'      => self::render('account_notification.txt.php', $data),
            'tag'       => 'account-notification',
            'variables' => [
                'account_event' => $type,
                'email_type'    => 'account_notification',
            ],
        ]);
    }

    /**
     * PLAIN-TEXT EMAIL
     *
     * A direct replacement for PHP's own mail() function, for the short
     * internal notices that do not need a designed layout - the "nice sale"
     * note and the low-stock warning.
     *
     * @param string $to      Recipient address.
     * @param string $subject
     * @param string $body    Plain text. Line breaks are kept.
     * @param string $tag     Optional Mailgun tag for the dashboard.
     *
     * @return array The result from Mailgun::send()
     */
    public static function plain($to, $subject, $body, $tag = 'notice')
    {
        // Mailgun requires an HTML part as well, so wrap the text in minimal
        // markup. <pre> keeps the line breaks exactly as written.
        $html = '<pre style="font-family:Arial,Helvetica,sans-serif; font-size:15px; white-space:pre-wrap;">'
              . htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
              . '</pre>';

        return self::client()->send([
            'from'      => self::from(),
            'to'        => $to,
            'reply_to'  => Env::get('MAIL_REPLY_TO', ''),
            'subject'   => $subject,
            'html'      => $html,
            'text'      => $body,
            'tag'       => $tag,
            'variables' => ['email_type' => 'plain_notice'],
        ]);
    }

    /**
     * Build the "From" header, e.g.  BT Yazul <orders@mg.btyazul.com>
     */
    private static function from()
    {
        $name    = Env::get('MAIL_FROM_NAME', '');
        $address = Env::require_('MAIL_FROM_ADDRESS');

        return $name !== '' ? sprintf('%s <%s>', $name, $address) : $address;
    }

    /**
     * Render a template file with the given data.
     *
     * The template is a normal PHP file. Every value in $data becomes a
     * variable inside it, so ['customer' => 'Ana'] gives you $customer.
     *
     * @param string $template Filename inside the templates/ folder.
     * @param array  $data     Values to expose to the template.
     */
    private static function render($template, array $data)
    {
        $path = dirname(__DIR__) . '/templates/' . $template;

        if (!is_file($path)) {
            throw new RuntimeException("Template not found: {$path}");
        }

        // e() is the escaping helper the templates use. Defined here so every
        // template has it without a separate include.
        if (!function_exists('e')) {
            /**
             * Escape a value for safe placement inside HTML.
             * Stops a customer name like  <b>  from breaking the layout.
             */
            function e($value)
            {
                return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
        }

        if (!function_exists('money')) {
            /**
             * Format an amount for display.
             * A number becomes "12.50"; an already-formatted string is left as
             * it is, so the site can pass "1.234,56" if that is its convention.
             */
            function money($value, $symbol = '')
            {
                if ($value === null || $value === '') {
                    return '';
                }

                $formatted = is_numeric($value) ? number_format((float) $value, 2, '.', ',') : (string) $value;

                return $symbol . $formatted;
            }
        }

        extract($data, EXTR_SKIP);

        ob_start();
        include $path;

        return ob_get_clean();
    }
}
