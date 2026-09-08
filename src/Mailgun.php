<?php
/**
 * Mailgun.php - talks to the Mailgun API.
 *
 * Deliberately uses only PHP's built-in curl. There is no Composer and there
 * are no third-party libraries to install or keep updated, so this works on a
 * plain shared host.
 *
 * You normally do not call this class directly - use Mailer.php instead.
 */

class Mailgun
{
    /** @var string Private API key from the Mailgun dashboard. */
    private $apiKey;

    /** @var string Sending domain, e.g. mg.btyazul.com */
    private $domain;

    /** @var string API base URL, differs between the US and EU regions. */
    private $baseUrl;

    /** @var bool When true, nothing is actually sent. */
    private $dryRun;

    /** @var string|null Path to the log file, or null for no logging. */
    private $logFile;

    /**
     * @param string      $apiKey  Mailgun private API key.
     * @param string      $domain  Sending domain.
     * @param string      $region  'us' or 'eu'.
     * @param bool        $dryRun  True = log only, never send.
     * @param string|null $logFile Where to append a line per send.
     */
    public function __construct($apiKey, $domain, $region = 'us', $dryRun = false, $logFile = null)
    {
        $this->apiKey  = $apiKey;
        $this->domain  = $domain;
        $this->dryRun  = (bool) $dryRun;
        $this->logFile = $logFile;

        // Mailgun runs two separate stacks. An EU account will reject US calls
        // with a 401, which looks confusingly like a bad API key.
        $this->baseUrl = (strtolower($region) === 'eu')
            ? 'https://api.eu.mailgun.net'
            : 'https://api.mailgun.net';
    }

    /**
     * Send one email.
     *
     * @param array $message Keys:
     *   to        (string, required)  recipient address
     *   subject   (string, required)
     *   html      (string, required)  HTML body
     *   text      (string, required)  plain-text body, for clients that block HTML
     *   from      (string, required)  e.g. 'BT Yazul <orders@mg.btyazul.com>'
     *   reply_to  (string, optional)
     *   tag       (string, optional)  groups messages in Mailgun's dashboard
     *   variables (array,  optional)  stored with the message, visible in logs
     *
     * @return array {ok: bool, status: int, id: string|null, message: string, dry_run: bool}
     */
    public function send(array $message)
    {
        foreach (['to', 'subject', 'html', 'text', 'from'] as $required) {
            if (empty($message[$required])) {
                throw new InvalidArgumentException("Mailgun::send() missing '{$required}'");
            }
        }

        // A malformed address is worth catching here rather than burning a send.
        if (!filter_var($message['to'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Not a valid email address: {$message['to']}");
        }

        $fields = [
            'from'    => $message['from'],
            'to'      => $message['to'],
            'subject' => $message['subject'],
            'html'    => $message['html'],
            'text'    => $message['text'],
        ];

        if (!empty($message['reply_to'])) {
            $fields['h:Reply-To'] = $message['reply_to'];
        }

        // Tags let you filter in the Mailgun dashboard, e.g. show only order
        // confirmations. Mailgun allows up to 3 tags per message.
        if (!empty($message['tag'])) {
            $fields['o:tag'] = $message['tag'];
        }

        // Custom variables come back attached to bounce/complaint webhooks,
        // which is how we know WHICH order an eventual bounce belongs to.
        if (!empty($message['variables']) && is_array($message['variables'])) {
            foreach ($message['variables'] as $name => $value) {
                $fields['v:' . $name] = is_scalar($value) ? (string) $value : json_encode($value);
            }
        }

        if ($this->dryRun) {
            $this->log('DRY-RUN', $message['to'], $message['subject'], 'not sent (MAILGUN_DRY_RUN=1)');

            return [
                'ok'      => true,
                'status'  => 0,
                'id'      => null,
                'message' => 'Dry run - message was not sent.',
                'dry_run' => true,
            ];
        }

        $url = $this->baseUrl . '/v3/' . rawurlencode($this->domain) . '/messages';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($fields),
            // Mailgun authenticates with HTTP basic auth: user "api", password = key.
            CURLOPT_USERPWD        => 'api:' . $this->apiKey,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $body     = curl_exec($ch);
        $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        // Network-level failure: we never reached Mailgun at all.
        if ($body === false) {
            $this->log('ERROR', $message['to'], $message['subject'], 'curl: ' . $curlErr);

            return [
                'ok'      => false,
                'status'  => 0,
                'id'      => null,
                'message' => 'Could not reach Mailgun: ' . $curlErr,
                'dry_run' => false,
            ];
        }

        $decoded = json_decode($body, true);
        $ok      = ($status >= 200 && $status < 300);
        $id      = isset($decoded['id']) ? $decoded['id'] : null;

        // Mailgun puts the human-readable reason in "message" on both success
        // and failure, so surface it either way.
        $note = isset($decoded['message']) ? $decoded['message'] : substr($body, 0, 300);

        $this->log($ok ? 'SENT' : 'FAILED', $message['to'], $message['subject'], "HTTP {$status} {$note}");

        return [
            'ok'      => $ok,
            'status'  => $status,
            'id'      => $id,
            'message' => $note,
            'dry_run' => false,
        ];
    }

    /**
     * Ask Mailgun whether an address has previously bounced or complained.
     *
     * Mailgun keeps three suppression lists: bounces, complaints (spam reports)
     * and unsubscribes. Once an address is on one, Mailgun refuses to mail it.
     * Checking first tells you why a customer says "I never got the email".
     *
     * @param string $type    'bounces', 'complaints' or 'unsubscribes'
     * @param string $address The address to look up.
     * @return array {found: bool, status: int, data: array|null}
     */
    public function checkSuppression($type, $address)
    {
        $allowed = ['bounces', 'complaints', 'unsubscribes'];
        if (!in_array($type, $allowed, true)) {
            throw new InvalidArgumentException("Suppression type must be one of: " . implode(', ', $allowed));
        }

        $url = $this->baseUrl . '/v3/' . rawurlencode($this->domain)
             . '/' . $type . '/' . rawurlencode($address);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD        => 'api:' . $this->apiKey,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false) {
            return ['found' => false, 'status' => 0, 'data' => null];
        }

        // 200 = the address IS on that list. 404 = it is not. Anything else is
        // an error, and we must not report it as a clean address.
        return [
            'found'  => ($status === 200),
            'status' => $status,
            'data'   => ($status === 200) ? json_decode($body, true) : null,
        ];
    }

    /**
     * Read recent delivery events straight from Mailgun.
     * Used by the end-to-end test to prove a message really was delivered,
     * rather than just accepted for sending.
     *
     * @param array $filters e.g. ['event' => 'delivered', 'limit' => 25]
     * @return array {ok: bool, status: int, items: array}
     */
    public function events(array $filters = [])
    {
        $url = $this->baseUrl . '/v3/' . rawurlencode($this->domain) . '/events';
        if ($filters) {
            $url .= '?' . http_build_query($filters);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD        => 'api:' . $this->apiKey,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $status !== 200) {
            return ['ok' => false, 'status' => $status, 'items' => []];
        }

        $decoded = json_decode($body, true);

        return [
            'ok'     => true,
            'status' => $status,
            'items'  => isset($decoded['items']) ? $decoded['items'] : [],
        ];
    }

    /**
     * Append one line to the send log. Never throws - a logging problem must
     * not stop an order confirmation going out.
     */
    private function log($outcome, $to, $subject, $note)
    {
        if (!$this->logFile) {
            return;
        }

        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }

        $line = sprintf(
            "[%s] %-8s to=%s subject=%s %s\n",
            gmdate('Y-m-d H:i:s') . ' UTC',
            $outcome,
            $to,
            str_replace(["\r", "\n"], ' ', $subject),
            $note
        );

        @file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
    }
}
