<?php
/**
 * Email Queue Processor
 *
 * Run from CLI:   php scripts/process-email-queue.php
 * Or via cron:    * * * * * php /path/to/scripts/process-email-queue.php >> /path/to/logs/email.log 2>&1
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/Support/helpers.php';

// Minimal autoloader (same as public/index.php)
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relativeClass = substr($class, strlen($prefix));
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', $relativeClass) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// Boot application (loads config + DB)
$app = new App\Core\Application(BASE_PATH);

$mailConfig = (array) config('app.mail', []);

if (!($mailConfig['enabled'] ?? false)) {
    echo "[" . date('Y-m-d H:i:s') . "] Mail is disabled. Set MAIL_ENABLED=true to process the queue.\n";
    exit(0);
}

$db = $app->database();
$maxAttempts = (int) ($mailConfig['max_attempts'] ?? 3);
$batchSize = 50;

// Fetch pending emails that are due
$emails = $db->fetchAll(
    'SELECT id, user_id, to_email, subject, body_html, body_text, related_type, related_id, attempts
     FROM email_queue
     WHERE status = :status AND (scheduled_at IS NULL OR scheduled_at <= NOW()) AND attempts < :max_attempts
     ORDER BY created_at ASC
     LIMIT ' . $batchSize,
    ['status' => 'pending', 'max_attempts' => $maxAttempts]
);

if ($emails === []) {
    echo "[" . date('Y-m-d H:i:s') . "] No pending emails.\n";
    exit(0);
}

echo "[" . date('Y-m-d H:i:s') . "] Processing " . count($emails) . " email(s)...\n";

$transport = (string) ($mailConfig['transport'] ?? 'smtp');
$sent = 0;
$failed = 0;

foreach ($emails as $email) {
    $emailId = (int) $email['id'];

    try {
        $success = false;

        if ($transport === 'smtp') {
            $success = sendViaSMTP($mailConfig, $email);
        } else {
            $success = sendViaMail($mailConfig, $email);
        }

        if ($success) {
            $db->execute(
                'UPDATE email_queue SET status = :status, sent_at = NOW(), attempts = attempts + 1 WHERE id = :id',
                ['status' => 'sent', 'id' => $emailId]
            );
            $sent++;
            echo "  ✓ #{$emailId} → {$email['to_email']}\n";
        } else {
            throw new RuntimeException('Transport returned false');
        }
    } catch (Throwable $e) {
        $db->execute(
            'UPDATE email_queue SET attempts = attempts + 1, last_error = :error, status = CASE WHEN attempts + 1 >= :max THEN :failed ELSE :pending END WHERE id = :id',
            ['error' => substr($e->getMessage(), 0, 500), 'max' => $maxAttempts, 'failed' => 'failed', 'pending' => 'pending', 'id' => $emailId]
        );
        $failed++;
        echo "  ✗ #{$emailId} → {$email['to_email']}: {$e->getMessage()}\n";
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Done. Sent: {$sent}, Failed: {$failed}\n";

// ── Transport functions ─────────────────────────────────────────────────

function sendViaSMTP(array $config, array $email): bool
{
    $host = (string) ($config['host'] ?? '127.0.0.1');
    $port = (int) ($config['port'] ?? 587);
    $encryption = (string) ($config['encryption'] ?? 'tls');
    $username = (string) ($config['username'] ?? '');
    $password = (string) ($config['password'] ?? '');
    $fromAddress = (string) ($config['from_address'] ?? '');
    $fromName = (string) ($config['from_name'] ?? 'HR System');

    $prefix = $encryption === 'ssl' ? 'ssl://' : '';
    $socket = @fsockopen($prefix . $host, $port, $errno, $errstr, 10);

    if (!$socket) {
        throw new RuntimeException("SMTP connect failed: {$errstr} ({$errno})");
    }

    stream_set_timeout($socket, 30);

    smtpRead($socket, 220);
    smtpWrite($socket, "EHLO localhost\r\n", 250);

    if ($encryption === 'tls') {
        smtpWrite($socket, "STARTTLS\r\n", 220);
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('STARTTLS handshake failed');
        }
        smtpWrite($socket, "EHLO localhost\r\n", 250);
    }

    if ($username !== '') {
        smtpWrite($socket, "AUTH LOGIN\r\n", 334);
        smtpWrite($socket, base64_encode($username) . "\r\n", 334);
        smtpWrite($socket, base64_encode($password) . "\r\n", 235);
    }

    smtpWrite($socket, "MAIL FROM:<{$fromAddress}>\r\n", 250);
    smtpWrite($socket, "RCPT TO:<{$email['to_email']}>\r\n", 250);
    smtpWrite($socket, "DATA\r\n", 354);

    $headers = "From: {$fromName} <{$fromAddress}>\r\n"
        . "To: {$email['to_email']}\r\n"
        . "Subject: {$email['subject']}\r\n"
        . "MIME-Version: 1.0\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Date: " . date('r') . "\r\n"
        . "\r\n";

    $body = $email['body_html'] ?? $email['body_text'] ?? '';
    fwrite($socket, $headers . $body . "\r\n.\r\n");
    smtpRead($socket, 250);

    smtpWrite($socket, "QUIT\r\n", 221);
    fclose($socket);

    return true;
}

function sendViaMail(array $config, array $email): bool
{
    $fromAddress = (string) ($config['from_address'] ?? '');
    $fromName = (string) ($config['from_name'] ?? 'HR System');

    $headers = "From: {$fromName} <{$fromAddress}>\r\n"
        . "MIME-Version: 1.0\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n";

    return mail($email['to_email'], $email['subject'], $email['body_html'] ?? $email['body_text'] ?? '', $headers);
}

function smtpWrite($socket, string $data, int $expectedCode): void
{
    fwrite($socket, $data);
    smtpRead($socket, $expectedCode);
}

function smtpRead($socket, int $expectedCode): string
{
    $response = '';
    while ($line = fgets($socket, 515)) {
        $response .= $line;
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }
    $code = (int) substr($response, 0, 3);
    if ($code !== $expectedCode) {
        throw new RuntimeException("SMTP expected {$expectedCode}, got {$code}: " . trim($response));
    }
    return $response;
}

