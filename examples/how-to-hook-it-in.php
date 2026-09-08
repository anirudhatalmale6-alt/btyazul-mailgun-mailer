<?php
/**
 * how-to-hook-it-in.php - examples only. This file is never run by the site.
 *
 * It shows the lines you add to your own code so the two emails go out at the
 * right moment. Copy the relevant block into your checkout / signup code.
 */

// Always this one line first. Adjust the path to wherever the mailer folder sits.
require_once __DIR__ . '/../bootstrap.php';


// ===========================================================================
// 1. ORDER CONFIRMATION
//    Put this immediately AFTER the order has been saved successfully.
//    Saving the order must come first - if the email fails, you still have
//    the order.
// ===========================================================================

/*
try {
    $result = Mailer::orderConfirmation([
        'email'        => $order['customer_email'],   // where to send it
        'customer'     => $order['customer_name'],
        'order_number' => $order['id'],
        'order_date'   => date('j F Y'),

        // One entry per line of the order.
        'items'        => $orderLines,   // [['name'=>..., 'qty'=>..., 'price'=>...], ...]

        'subtotal'     => $order['subtotal'],
        'shipping'     => $order['shipping'],
        'total'        => $order['total'],
        'currency'     => '$',
        'ship_to'      => $order['shipping_address'],
    ]);

    if (!$result['ok']) {
        // The order is fine, the email is not. Record it and move on - never
        // show the customer an error about email after they have paid.
        error_log('Order confirmation failed for ' . $order['id'] . ': ' . $result['message']);
    }
} catch (Throwable $e) {
    error_log('Order confirmation error: ' . $e->getMessage());
}
*/


// ===========================================================================
// 2a. WELCOME EMAIL
//     After a new account is created.
// ===========================================================================

/*
Mailer::accountNotification([
    'email'      => $user['email'],
    'customer'   => $user['name'],
    'type'       => 'welcome',
    'action_url' => 'https://btyazul.com/login',
]);
*/


// ===========================================================================
// 2b. PASSWORD RESET
//     After you have generated and stored the reset token.
// ===========================================================================

/*
Mailer::accountNotification([
    'email'      => $user['email'],
    'customer'   => $user['name'],
    'type'       => 'password_reset',
    'action_url' => 'https://btyazul.com/reset-password?token=' . urlencode($token),
    'expires_in' => '1 hour',
]);
*/


// ===========================================================================
// 2c. PASSWORD CHANGED CONFIRMATION
//     After the password has actually been updated.
// ===========================================================================

/*
Mailer::accountNotification([
    'email'      => $user['email'],
    'customer'   => $user['name'],
    'type'       => 'password_changed',
    'action_url' => 'https://btyazul.com/account',
]);
*/


// ===========================================================================
// 2d. ANY OTHER ACCOUNT MESSAGE
//     Use 'generic' and supply your own wording.
// ===========================================================================

/*
Mailer::accountNotification([
    'email'      => $user['email'],
    'customer'   => $user['name'],
    'type'       => 'generic',
    'message'    => 'Your delivery address has been updated on your account.',
    'action_url' => 'https://btyazul.com/account',
]);
*/


// ===========================================================================
// NOTES
//
// - Every call returns an array. $result['ok'] is true when Mailgun accepted
//   the message. $result['message'] explains it when it did not.
//
// - Wrap the calls in try/catch. A missing field throws, and you do not want
//   an email problem to take down your checkout page.
//
// - While MAILGUN_DRY_RUN=1 is set in the .env, nothing is really sent - the
//   send is written to the log file instead. That is the safe way to test.
// ===========================================================================
