<?php
/**
 * bootstrap.php - include this one file and Mailer is ready to use.
 *
 * From anywhere in your site:
 *
 *   require_once __DIR__ . '/mailer/bootstrap.php';
 *   Mailer::orderConfirmation([...]);
 *
 * It finds your .env file, loads it, and pulls in the classes.
 */

require_once __DIR__ . '/src/Env.php';
require_once __DIR__ . '/src/Mailgun.php';
require_once __DIR__ . '/src/Mailer.php';

// Where to look for the .env file, in order. The first one found wins.
//
// The first two are OUTSIDE the public web folder, which is where the real
// file should live so a browser can never download it. The last one (next to
// this script) is a convenience for local testing only.
$candidates = [
    // Set MAILER_ENV_PATH in your web server config to override everything else.
    getenv('MAILER_ENV_PATH') ?: null,
    dirname(dirname(__DIR__)) . '/private/.env',
    dirname(__DIR__) . '/private/.env',
    __DIR__ . '/.env',
];

$envPath = null;
foreach ($candidates as $candidate) {
    if ($candidate && is_file($candidate)) {
        $envPath = $candidate;
        break;
    }
}

if ($envPath === null) {
    throw new RuntimeException(
        'No .env file found. Copy .env.example to .env, fill it in, and place it '
        . 'outside your public web folder (see HANDOFF.md).'
    );
}

Env::load($envPath);
