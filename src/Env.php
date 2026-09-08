<?php
/**
 * Env.php - loads settings from a .env file.
 *
 * Why this exists: the Mailgun API key must never sit inside the code. It lives
 * in a .env file that is kept OUTSIDE the public web folder, so a browser can
 * never download it. This class reads that file once and hands out the values.
 *
 * Usage:
 *   Env::load('/home/youruser/private/.env');
 *   $key = Env::get('MAILGUN_API_KEY');
 */

class Env
{
    /** @var array<string,string> */
    private static $vars = [];

    /** @var bool */
    private static $loaded = false;

    /**
     * Read a .env file into memory.
     *
     * @param string $path Full path to the .env file.
     * @throws RuntimeException if the file is missing or unreadable.
     */
    public static function load($path)
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException("Cannot read env file: {$path}");
        }

        // FILE_IGNORE_NEW_LINES strips the line breaks; SKIP_EMPTY_LINES drops blanks.
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            // Comments start with # and are ignored.
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            // Every real line looks like NAME=value. No '=' means it is not a setting.
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }

            $name  = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));

            // Allow the value to be wrapped in quotes, e.g. NAME="some value".
            $len = strlen($value);
            if ($len >= 2) {
                $first = $value[0];
                $last  = $value[$len - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            self::$vars[$name] = $value;
        }

        self::$loaded = true;
    }

    /**
     * Fetch one setting.
     *
     * @param string      $name    e.g. 'MAILGUN_API_KEY'
     * @param string|null $default Returned when the setting is absent.
     */
    public static function get($name, $default = null)
    {
        if (!self::$loaded) {
            throw new RuntimeException('Env::load() must be called before Env::get().');
        }

        return array_key_exists($name, self::$vars) ? self::$vars[$name] : $default;
    }

    /**
     * Fetch a setting that the code cannot run without.
     * Fails loudly rather than silently sending mail with a blank key.
     */
    public static function require_($name)
    {
        $value = self::get($name, '');
        if ($value === '' || $value === null) {
            throw new RuntimeException("Required setting missing from .env: {$name}");
        }

        return $value;
    }

    /**
     * Fetch a yes/no setting. "1", "true", "yes", "on" all mean true.
     */
    public static function bool($name, $default = false)
    {
        $value = self::get($name, null);
        if ($value === null || $value === '') {
            return $default;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    /** Wipe loaded values. Only used by the test scripts. */
    public static function reset()
    {
        self::$vars  = [];
        self::$loaded = false;
    }
}
