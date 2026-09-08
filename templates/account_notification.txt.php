<?php
/**
 * Account notification - plain-text version.
 * Sent alongside the HTML version in the same email.
 */
?>
<?= $site_name ?>


<?= strtoupper($heading) ?>


Hi <?= $customer ?>,
<?php if ($intro !== ''): ?>

<?= $intro ?>

<?php endif; ?>
<?php if (trim((string) $message) !== ''): ?>

<?= $message ?>

<?php endif; ?>
<?php if (trim((string) $action_url) !== ''): ?>

<?= strtoupper($button) ?>:
<?= $action_url ?>

<?php if (trim((string) $expires_in) !== ''): ?>
This link expires in <?= $expires_in ?>.

<?php endif; ?>
<?php endif; ?>

Need help? Just reply to this email.

<?= $site_name ?><?= $site_url !== '' ? "\n" . $site_url : '' ?>
