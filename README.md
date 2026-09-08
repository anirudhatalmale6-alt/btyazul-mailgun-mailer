# Mailgun transactional email for btyazul.com

Sends **order confirmations** and **account notifications** through Mailgun from
a plain PHP site. No Composer, no third-party libraries — just PHP's built-in
curl, so it runs on shared hosting as-is.

## Quick start

```bash
cp .env.example .env      # then fill it in and move it outside public_html
php tools/selftest.php    # 43 checks, sends nothing
php tools/preview.php     # writes the emails to HTML files you can open
```

From your own site:

```php
require_once '/path/to/mailer/bootstrap.php';

Mailer::orderConfirmation([
    'email'        => 'customer@example.com',
    'customer'     => 'Ana Ruiz',
    'order_number' => 'BT-1042',
    'items'        => [['name' => 'Blue Widget', 'qty' => 2, 'price' => 12.50]],
    'total'        => 25.00,
]);

Mailer::accountNotification([
    'email'      => 'customer@example.com',
    'customer'   => 'Ana Ruiz',
    'type'       => 'password_reset',
    'action_url' => 'https://btyazul.com/reset-password?token=...',
    'expires_in' => '1 hour',
]);
```

## What is here

| Path | Purpose |
|---|---|
| `bootstrap.php` | Single include; finds and loads the `.env`, pulls in the classes. |
| `src/Env.php` | Reads settings from the `.env`. The API key is never in code. |
| `src/Mailgun.php` | Mailgun API client — send, suppression lookup, event log. |
| `src/Mailer.php` | The two public functions and the account-email wording. |
| `templates/` | HTML **and** plain-text bodies for both emails. |
| `public/mailgun-webhook.php` | Bounce / complaint receiver, with signature verification. |
| `tools/selftest.php` | 43 offline checks. |
| `tools/preview.php` | Renders the emails to files. |
| `tools/send_live_test.php` | Real end-to-end send, then confirms delivery via Mailgun's event log. |
| `examples/how-to-hook-it-in.php` | Copy-paste snippets for the site code. |
| `HANDOFF.md` | Full maintenance guide in plain language. |

## Notes

- Sends from the subdomain `mg.btyazul.com` so the root domain's existing mail
  setup is untouched.
- `MAILGUN_DRY_RUN=1` logs instead of sending — the safe default while testing.
- Every email goes out with both an HTML and a plain-text body.
- Webhook calls are rejected unless they carry a valid Mailgun HMAC signature
  and a timestamp less than 15 minutes old.

See `HANDOFF.md` for the full guide.
