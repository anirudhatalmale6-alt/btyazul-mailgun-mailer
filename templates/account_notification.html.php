<?php
/**
 * Account notification - HTML version.
 *
 * Available variables: $customer, $heading, $intro, $button, $action_url,
 * $message, $expires_in, $site_name, $site_url
 *
 * The wording comes from Mailer::accountNotification(), so this one file
 * covers welcome, password reset, password changed and email changed.
 */
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($heading) ?></title>
</head>
<body style="margin:0; padding:0; background-color:#f4f5f7; font-family:Arial,Helvetica,sans-serif; color:#22262b;">

<!-- Preheader: the grey preview line shown in the inbox list. -->
<div style="display:none; max-height:0; overflow:hidden; opacity:0;">
  <?= e($heading) ?> - <?= e($site_name) ?>
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f5f7; padding:24px 12px;">
  <tr>
    <td align="center">

      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px; background-color:#ffffff; border-radius:6px; overflow:hidden;">

        <tr>
          <td style="background-color:#1f3a5f; padding:24px 28px;">
            <div style="font-size:20px; font-weight:bold; color:#ffffff;"><?= e($site_name) ?></div>
          </td>
        </tr>

        <tr>
          <td style="padding:28px 28px 0 28px;">
            <h1 style="margin:0 0 14px 0; font-size:22px; color:#1f3a5f;"><?= e($heading) ?></h1>

            <p style="margin:0 0 14px 0; font-size:15px; line-height:22px;">
              Hi <?= e($customer) ?>,
            </p>

            <?php if ($intro !== ''): ?>
              <p style="margin:0 0 14px 0; font-size:15px; line-height:22px;"><?= e($intro) ?></p>
            <?php endif; ?>

            <?php if (trim((string) $message) !== ''): ?>
              <p style="margin:0 0 14px 0; font-size:15px; line-height:22px;"><?= nl2br(e($message)) ?></p>
            <?php endif; ?>
          </td>
        </tr>

        <?php if (trim((string) $action_url) !== ''): ?>
        <!-- Button. Built from a table because Outlook does not style <a> reliably. -->
        <tr>
          <td style="padding:10px 28px 4px 28px;">
            <table role="presentation" cellpadding="0" cellspacing="0">
              <tr>
                <td style="background-color:#1f3a5f; border-radius:4px;">
                  <a href="<?= e($action_url) ?>"
                     style="display:inline-block; padding:12px 24px; font-size:15px; font-weight:bold; color:#ffffff; text-decoration:none;">
                    <?= e($button) ?>
                  </a>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <?php if (trim((string) $expires_in) !== ''): ?>
        <tr>
          <td style="padding:10px 28px 0 28px;">
            <p style="margin:0; font-size:13px; line-height:20px; color:#6b7480;">
              This link expires in <?= e($expires_in) ?>.
            </p>
          </td>
        </tr>
        <?php endif; ?>

        <tr>
          <td style="padding:14px 28px 0 28px;">
            <p style="margin:0; font-size:13px; line-height:20px; color:#6b7480;">
              If the button does not work, copy this link into your browser:<br>
              <span style="color:#1f3a5f; word-break:break-all;"><?= e($action_url) ?></span>
            </p>
          </td>
        </tr>
        <?php endif; ?>

        <tr>
          <td style="padding:24px 28px 28px 28px;">
            <p style="margin:0; font-size:14px; line-height:21px; color:#6b7480;">
              Need help? Just reply to this email.
            </p>
          </td>
        </tr>

        <tr>
          <td style="background-color:#f7f9fc; padding:16px 28px; font-size:12px; color:#8a929c;" align="center">
            <?= e($site_name) ?><?php if ($site_url !== ''): ?> &middot; <a href="<?= e($site_url) ?>" style="color:#1f3a5f;"><?= e($site_url) ?></a><?php endif; ?>
          </td>
        </tr>

      </table>

    </td>
  </tr>
</table>

</body>
</html>
