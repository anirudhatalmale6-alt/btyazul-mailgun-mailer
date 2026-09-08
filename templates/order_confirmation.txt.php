<?php
/**
 * Order confirmation - plain-text version.
 *
 * Every email is sent with BOTH an HTML and a plain-text body. Some people
 * block HTML, and spam filters treat a missing text part as a bad sign, so
 * this file is not optional.
 *
 * No escaping here - it is plain text, so e() is not needed.
 */
?>
<?= $site_name ?>


THANK YOU FOR YOUR ORDER

Hi <?= $customer ?>,

We have received your order and it is now confirmed.

Order number: <?= $order_number ?>

Date: <?= $order_date ?>


ITEMS
<?php foreach ($items as $item): ?>
- <?= isset($item['name']) ? $item['name'] : '' ?> x<?= isset($item['qty']) ? $item['qty'] : 1 ?>  <?= money(isset($item['price']) ? $item['price'] : '', $currency) ?>

<?php endforeach; ?>
<?php if ($subtotal !== null && $subtotal !== ''): ?>
Subtotal: <?= money($subtotal, $currency) ?>

<?php endif; ?>
<?php if ($shipping !== null && $shipping !== ''): ?>
Shipping: <?= money($shipping, $currency) ?>

<?php endif; ?>
TOTAL: <?= money($total, $currency) ?>

<?php if (trim((string) $ship_to) !== ''): ?>

DELIVERING TO
<?= $ship_to ?>

<?php endif; ?>

If anything looks wrong, just reply to this email and we will sort it out.

<?= $site_name ?><?= $site_url !== '' ? "\n" . $site_url : '' ?>
