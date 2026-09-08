# Mailgun email for btyazul.com — hand-off guide

This is the guide for maintaining the email side of the site. It is written to
be readable without a technical background.

---

## What this does

Two emails are sent automatically:

1. **Order confirmation** — goes out after a customer places an order.
2. **Account notification** — welcome, password reset, password changed, email
   changed, or a custom message.

Both go out through Mailgun, so they land in the inbox rather than the spam
folder, and they look like they came from your domain.

---

## The folders

```
mailer/
  bootstrap.php        Include this one file and everything is ready.
  .env.example         Copy of the settings file, with nothing filled in.

  src/
    Env.php            Reads the settings file.
    Mailgun.php        Talks to Mailgun.
    Mailer.php         The two email functions you actually call.

  templates/
    order_confirmation.html.php     What the order email looks like.
    order_confirmation.txt.php      Plain-text copy of the same email.
    account_notification.html.php   What the account email looks like.
    account_notification.txt.php    Plain-text copy of the same email.

  public/
    mailgun-webhook.php  Receives bounce and spam reports from Mailgun.
                         This is the ONLY file that goes in the public folder.

  tools/
    selftest.php         Checks everything works. Sends nothing.
    preview.php          Writes the emails to HTML files so you can look at them.
    send_live_test.php   The real end-to-end test. This one does send.

  examples/
    how-to-hook-it-in.php   The lines to paste into your own code.
```

Everything except `public/mailgun-webhook.php` should live **outside** your
public web folder, so nobody can download it.

---

## The settings file

Copy `.env.example` to `.env`, fill it in, and put it somewhere private such as
`/home/YOURUSER/private/.env`.

**The API key lives only in this file. It is never written into the code.**

Set the file permissions to `600` so only your account can read it.

The important settings:

| Setting | What it is |
|---|---|
| `MAILGUN_API_KEY` | Your private API key from the Mailgun dashboard. |
| `MAILGUN_DOMAIN` | The sending domain, `mg.btyazul.com`. |
| `MAILGUN_REGION` | `us` or `eu` — whichever region your Mailgun account is in. |
| `MAILGUN_WEBHOOK_SIGNING_KEY` | Proves bounce reports really came from Mailgun. |
| `MAIL_FROM_ADDRESS` | The address customers see, e.g. `orders@mg.btyazul.com`. |
| `MAIL_REPLY_TO` | Where replies go. This can be your normal `@btyazul.com` address. |
| `MAILGUN_DRY_RUN` | `1` = do not really send, just log it. `0` = send for real. |

> **If emails suddenly stop**, check `MAILGUN_DRY_RUN` first. If it is set
> to `1`, nothing is being sent — it is only being written to the log.

---

## Why a subdomain

Emails are sent from **`mg.btyazul.com`**, not from `btyazul.com` directly.

`btyazul.com` already has its own mail set up on the HostPapa server. Pointing
Mailgun at the same domain risks breaking the mail you already receive. Giving
Mailgun its own subdomain keeps the two completely separate.

Customers still see `btyazul.com` in the sender name, and replies still go to
your normal `@btyazul.com` address, so nothing looks different to them.

---

## The DNS records

Mailgun generates the exact values when the domain is added — the list below is
what to expect, not values to copy.

| Type | Name | Purpose |
|---|---|---|
| TXT | `mg.btyazul.com` | SPF — says Mailgun is allowed to send for this subdomain. |
| TXT | `<selector>._domainkey.mg.btyazul.com` | DKIM — signs each message so it cannot be forged. |
| MX | `mg.btyazul.com` | Lets Mailgun handle bounces for the subdomain. |
| CNAME | `email.mg.btyazul.com` | Open and click tracking. |
| TXT | `_dmarc.btyazul.com` | Tells other mail servers what to do with fakes. |

Two things that are easy to get wrong:

- **These all go on the subdomain**, not the root. The existing records on
  `btyazul.com` must be left alone.
- **DNS is not instant.** Allow up to a few hours before Mailgun shows the
  domain as verified.

---

## Sending an email from your own code

One line to load it, one line to send:

```php
require_once '/path/to/mailer/bootstrap.php';

Mailer::orderConfirmation([
    'email'        => 'customer@example.com',
    'customer'     => 'Ana Ruiz',
    'order_number' => 'BT-1042',
    'items'        => [['name' => 'Blue Widget', 'qty' => 2, 'price' => 12.50]],
    'total'        => 25.00,
]);
```

Full examples for every case are in `examples/how-to-hook-it-in.php`.

---

## Changing the wording or the look

- **Wording of the account emails** — `src/Mailer.php`, the `$copy` block near
  the top of `accountNotification()`. Each event has a subject, a heading, a
  paragraph and a button label, all in plain English in one place.
- **Look of the emails** — the four files in `templates/`.

After any change, run:

```
php tools/preview.php
```

and open the HTML files it writes. Nothing is sent.

**When editing the templates:** if you add a customer-supplied value, wrap it
in `e()` — as in `<?= e($customer) ?>`. That stops odd characters in a name or
address from breaking the layout.

**If you change the HTML template, change the matching `.txt` one too.** Every
email is sent as both. Spam filters treat a missing plain-text part as a bad
sign.

---

## Testing

**Safe test, sends nothing:**

```
php tools/selftest.php
```

43 checks covering the templates, the data, the escaping, the rejection of bad
input, and the webhook security. All should pass.

**Real test, actually sends:**

```
php tools/send_live_test.php you@yourdomain.com
```

This checks the address is not blocked, sends both emails, then reads Mailgun's
own log to confirm they were genuinely **delivered** — not merely accepted.

Point it only at an address you own.

---

## Bounces and spam complaints

`public/mailgun-webhook.php` goes in the public folder, and its address is
registered in Mailgun under **Sending → Webhooks** for these events:

- Permanent Failure
- Temporary Failure
- Spam Complaints
- Unsubscribes
- Delivered

When an email cannot be delivered, or a customer marks it as spam, Mailgun
notifies that page and it is recorded in the file named by
`MAIL_SUPPRESSION_LOG`. That log is how you answer "the customer says they
never got their order confirmation".

Every notification is verified against Mailgun's signature first, so nobody can
send you fake bounce reports.

---

## When something goes wrong

| Symptom | First thing to check |
|---|---|
| No emails at all | Is `MAILGUN_DRY_RUN` still `1` in the `.env`? |
| `Required setting missing` | A blank line in the `.env`, or the wrong `.env` file was found. |
| Mailgun returns 401 | Wrong API key, **or** the account is in the EU region and `MAILGUN_REGION` still says `us`. |
| Emails go to spam | Check the domain shows as verified in Mailgun and all DNS records are green. |
| One customer never receives anything | Their address is probably on a suppression list. Check `MAIL_SUPPRESSION_LOG`, then Mailgun → Sending → Suppressions. |
| Webhook returns 500 | `MAILGUN_WEBHOOK_SIGNING_KEY` is missing from the `.env`. |

The send log named by `MAIL_LOG_FILE` records one line per email — when it was
sent, to whom, and what Mailgun said.
