<?php
/**
 * Order confirmation - HTML version.
 *
 * Available variables: $customer, $order_number, $order_date, $items,
 * $subtotal, $shipping, $total, $currency, $ship_to, $site_name, $site_url
 *
 * Email clients (Outlook especially) ignore stylesheets, so the styling is
 * written inline on each element. That is normal for email and not a mistake.
 */
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($site_name) ?> order <?= e($order_number) ?></title>
</head>
<body style="margin:0; padding:0; background-color:#f4f5f7; font-family:Arial,Helvetica,sans-serif; color:#22262b;">

<!-- Preheader: the grey preview line shown in the inbox list. Hidden in the body. -->
<div style="display:none; max-height:0; overflow:hidden; opacity:0;">
  Order <?= e($order_number) ?> is confirmed. Total <?= e(money($total, $currency)) ?>.
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f5f7; padding:24px 12px;">
  <tr>
    <td align="center">

      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px; background-color:#ffffff; border-radius:6px; overflow:hidden;">

        <!-- Header -->
        <tr>
          <td style="background-color:#1f3a5f; padding:24px 28px;">
            <div style="font-size:20px; font-weight:bold; color:#ffffff;"><?= e($site_name) ?></div>
          </td>
        </tr>

        <!-- Greeting -->
        <tr>
          <td style="padding:28px 28px 8px 28px;">
            <h1 style="margin:0 0 12px 0; font-size:22px; color:#1f3a5f;">Thank you for your order</h1>
            <p style="margin:0 0 6px 0; font-size:15px; line-height:22px;">
              Hi <?= e($customer) ?>,
            </p>
            <p style="margin:0; font-size:15px; line-height:22px;">
              We have received your order and it is now confirmed. Here are the details.
            </p>
          </td>
        </tr>

        <!-- Order meta -->
        <tr>
          <td style="padding:20px 28px 0 28px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f7f9fc; border-radius:4px;">
              <tr>
                <td style="padding:14px 16px; font-size:14px;">
                  <strong>Order number:</strong> <?= e($order_number) ?><br>
                  <strong>Date:</strong> <?= e($order_date) ?>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Items -->
        <tr>
          <td style="padding:24px 28px 0 28px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;">
              <tr>
                <th align="left"  style="padding:0 0 8px 0; border-bottom:2px solid #e3e7ee; font-size:12px; text-transform:uppercase; color:#6b7480;">Item</th>
                <th align="center" style="padding:0 0 8px 0; border-bottom:2px solid #e3e7ee; font-size:12px; text-transform:uppercase; color:#6b7480;">Qty</th>
                <th align="right" style="padding:0 0 8px 0; border-bottom:2px solid #e3e7ee; font-size:12px; text-transform:uppercase; color:#6b7480;">Price</th>
              </tr>
              <?php foreach ($items as $item): ?>
                <tr>
                  <td align="left"   style="padding:10px 0; border-bottom:1px solid #eef1f5;"><?= e(isset($item['name']) ? $item['name'] : '') ?></td>
                  <td align="center" style="padding:10px 0; border-bottom:1px solid #eef1f5;"><?= e(isset($item['qty']) ? $item['qty'] : 1) ?></td>
                  <td align="right"  style="padding:10px 0; border-bottom:1px solid #eef1f5;"><?= e(money(isset($item['price']) ? $item['price'] : '', $currency)) ?></td>
                </tr>
              <?php endforeach; ?>

              <?php if ($subtotal !== null && $subtotal !== ''): ?>
                <tr>
                  <td colspan="2" align="right" style="padding:10px 12px 2px 0; color:#6b7480;">Subtotal</td>
                  <td align="right" style="padding:10px 0 2px 0;"><?= e(money($subtotal, $currency)) ?></td>
                </tr>
              <?php endif; ?>

              <?php if ($shipping !== null && $shipping !== ''): ?>
                <tr>
                  <td colspan="2" align="right" style="padding:2px 12px 2px 0; color:#6b7480;">Shipping</td>
                  <td align="right" style="padding:2px 0;"><?= e(money($shipping, $currency)) ?></td>
                </tr>
              <?php endif; ?>

              <tr>
                <td colspan="2" align="right" style="padding:12px 12px 0 0; font-size:16px; font-weight:bold; border-top:2px solid #e3e7ee;">Total</td>
                <td align="right" style="padding:12px 0 0 0; font-size:16px; font-weight:bold; border-top:2px solid #e3e7ee;"><?= e(money($total, $currency)) ?></td>
              </tr>
            </table>
          </td>
        </tr>

        <?php if (trim((string) $ship_to) !== ''): ?>
        <!-- Delivery address -->
        <tr>
          <td style="padding:24px 28px 0 28px;">
            <div style="font-size:12px; text-transform:uppercase; color:#6b7480; margin-bottom:6px;">Delivering to</div>
            <div style="font-size:14px; line-height:21px;"><?= nl2br(e($ship_to)) ?></div>
          </td>
        </tr>
        <?php endif; ?>

        <!-- Footer -->
        <tr>
          <td style="padding:28px;">
            <p style="margin:0 0 4px 0; font-size:14px; line-height:21px; color:#6b7480;">
              If anything looks wrong, just reply to this email and we will sort it out.
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
