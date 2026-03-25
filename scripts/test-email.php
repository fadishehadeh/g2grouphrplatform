<?php
/**
 * Quick SMTP email test
 *
 * Usage:  php scripts/test-email.php recipient@example.com
 *         php scripts/test-email.php              (sends to the FROM address itself)
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/Support/helpers.php';

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

$app = new App\Core\Application(BASE_PATH);

$mailConfig = (array) config('app.mail', []);

echo "=== HR System — Email Test ===\n\n";
echo "Host:       {$mailConfig['host']}\n";
echo "Port:       {$mailConfig['port']}\n";
echo "Encryption: {$mailConfig['encryption']}\n";
echo "Username:   {$mailConfig['username']}\n";
echo "From:       {$mailConfig['from_address']}\n";
echo "Enabled:    " . ($mailConfig['enabled'] ? 'YES' : 'NO') . "\n\n";

if (!$mailConfig['enabled']) {
    echo "ERROR: Mail is disabled. Set MAIL_ENABLED=true in .env\n";
    exit(1);
}

$recipient = $argv[1] ?? $mailConfig['from_address'];
echo "Sending test email to: {$recipient}\n\n";

$host       = (string) ($mailConfig['host'] ?? '127.0.0.1');
$port       = (int) ($mailConfig['port'] ?? 587);
$encryption = (string) ($mailConfig['encryption'] ?? 'tls');
$username   = (string) ($mailConfig['username'] ?? '');
$password   = (string) ($mailConfig['password'] ?? '');
$fromAddr   = (string) ($mailConfig['from_address'] ?? '');
$fromName   = (string) ($mailConfig['from_name'] ?? 'HR System');

try {
    // 1. Connect
    echo "[1] Connecting to {$host}:{$port}... ";
    $prefix = $encryption === 'ssl' ? 'ssl://' : '';
    $socket = @fsockopen($prefix . $host, $port, $errno, $errstr, 15);
    if (!$socket) {
        throw new RuntimeException("Connection failed: {$errstr} ({$errno})");
    }
    stream_set_timeout($socket, 30);
    $greeting = smtpReadLine($socket);
    echo "OK\n    Server: {$greeting}\n";

    // 2. EHLO
    echo "[2] EHLO... ";
    sendCmd($socket, "EHLO localhost\r\n");
    echo "OK\n";

    // 3. STARTTLS
    if ($encryption === 'tls') {
        echo "[3] STARTTLS... ";
        fwrite($socket, "STARTTLS\r\n");
        $resp = smtpReadLine($socket);
        if (!str_starts_with($resp, '220')) {
            throw new RuntimeException("STARTTLS rejected: {$resp}");
        }
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('TLS handshake failed');
        }
        sendCmd($socket, "EHLO localhost\r\n");
        echo "OK\n";
    }

    // 4. AUTH
    echo "[4] AUTH LOGIN... ";
    fwrite($socket, "AUTH LOGIN\r\n");
    smtpReadLine($socket);
    fwrite($socket, base64_encode($username) . "\r\n");
    smtpReadLine($socket);
    fwrite($socket, base64_encode($password) . "\r\n");
    $authResp = smtpReadLine($socket);
    if (!str_starts_with($authResp, '235')) {
        throw new RuntimeException("Auth failed: {$authResp}");
    }
    echo "OK — Authenticated!\n";

    // 5. Send
    echo "[5] Sending message... ";
    fwrite($socket, "MAIL FROM:<{$fromAddr}>\r\n");
    smtpReadLine($socket);
    fwrite($socket, "RCPT TO:<{$recipient}>\r\n");
    smtpReadLine($socket);
    fwrite($socket, "DATA\r\n");
    smtpReadLine($socket);

    $date = date('r');
    $body = "From: {$fromName} <{$fromAddr}>\r\n"
        . "To: {$recipient}\r\n"
        . "Subject: HR System — Email Test\r\n"
        . "MIME-Version: 1.0\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Date: {$date}\r\n"
        . "\r\n"
        . "<h2>Email test successful!</h2>"
        . "<p>This confirms that the HR System can send emails via <strong>{$host}:{$port}</strong>.</p>"
        . "<p>Sent at {$date}</p>"
        . "\r\n.\r\n";

    fwrite($socket, $body);
    $sendResp = smtpReadLine($socket);
    if (!str_starts_with($sendResp, '250')) {
        throw new RuntimeException("Send failed: {$sendResp}");
    }
    echo "OK\n";

    fwrite($socket, "QUIT\r\n");
    fclose($socket);

    echo "\n✅ SUCCESS — Test email sent to {$recipient}\n";
    echo "Check the inbox (and spam folder).\n";
} catch (Throwable $e) {
    echo "\n❌ FAILED: {$e->getMessage()}\n";
    exit(1);
}

function smtpReadLine($socket): string
{
    $response = '';
    while (($line = fgets($socket, 512)) !== false) {
        $response .= $line;
        if (isset($line[3]) && ($line[3] === ' ' || $line[3] === "\r")) {
            break;
        }
    }
    return trim($response);
}

function sendCmd($socket, string $cmd): string
{
    fwrite($socket, $cmd);
    $resp = '';
    while (($line = fgets($socket, 512)) !== false) {
        $resp .= $line;
        if (isset($line[3]) && ($line[3] === ' ' || $line[3] === "\r")) {
            break;
        }
    }
    return trim($resp);
}

