<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class Mailer
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function isEnabled(): bool
    {
        return !empty($this->config['enabled']);
    }

    /**
     * Send a pre-built raw MIME message (e.g. multipart with attachments).
     * The $rawBody must include all Content-Type headers but NOT the envelope headers
     * (From/To/Subject) — those are prepended here.
     */
    public function sendRaw(string $toEmail, string $subject, string $rawBody): void
    {
        if (!$this->isEnabled()) {
            throw new RuntimeException('Mail is not enabled.');
        }

        $transport = (string) ($this->config['transport'] ?? 'smtp');
        $fromAddr  = (string) ($this->config['from_address'] ?? '');
        $fromName  = (string) ($this->config['from_name'] ?? 'HR System');

        $envelope = "From: {$fromName} <{$fromAddr}>\r\n"
            . "To: {$toEmail}\r\n"
            . "Subject: {$subject}\r\n"
            . "Date: " . date('r') . "\r\n";

        if ($transport === 'smtp') {
            $this->sendRawViaSMTP($toEmail, $envelope . $rawBody);
        } else {
            $headers = "From: {$fromName} <{$fromAddr}>\r\n";
            mail($toEmail, $subject, $rawBody, $headers);
        }
    }

    private function sendRawViaSMTP(string $toEmail, string $fullMessage): void
    {
        $host       = (string) ($this->config['host'] ?? '127.0.0.1');
        $port       = (int)    ($this->config['port'] ?? 587);
        $encryption = (string) ($this->config['encryption'] ?? 'tls');
        $username   = (string) ($this->config['username'] ?? '');
        $password   = (string) ($this->config['password'] ?? '');
        $fromAddr   = (string) ($this->config['from_address'] ?? '');

        $prefix = $encryption === 'ssl' ? 'ssl://' : '';
        $socket = @fsockopen($prefix . $host, $port, $errno, $errstr, 10);

        if (!$socket) {
            throw new RuntimeException("SMTP connect failed: {$errstr} ({$errno})");
        }

        stream_set_timeout($socket, 30);
        $this->smtpRead($socket, 220);
        $this->smtpWrite($socket, "EHLO localhost\r\n", 250);

        if ($encryption === 'tls') {
            $this->smtpWrite($socket, "STARTTLS\r\n", 220);
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $this->smtpWrite($socket, "EHLO localhost\r\n", 250);
        }

        if ($username !== '') {
            $this->smtpWrite($socket, "AUTH LOGIN\r\n", 334);
            $this->smtpWrite($socket, base64_encode($username) . "\r\n", 334);
            $this->smtpWrite($socket, base64_encode($password) . "\r\n", 235);
        }

        $this->smtpWrite($socket, "MAIL FROM:<{$fromAddr}>\r\n", 250);
        $this->smtpWrite($socket, "RCPT TO:<{$toEmail}>\r\n", 250);
        $this->smtpWrite($socket, "DATA\r\n", 354);
        fwrite($socket, $fullMessage . "\r\n.\r\n");
        $this->smtpRead($socket, 250);
        $this->smtpWrite($socket, "QUIT\r\n", 221);
        fclose($socket);
    }

    public function send(string $toEmail, string $subject, string $bodyHtml, ?string $bodyText = null): void
    {
        if (!$this->isEnabled()) {
            throw new RuntimeException('Mail is not enabled. Check MAIL_ENABLED in your .env file.');
        }

        $transport = (string) ($this->config['transport'] ?? 'smtp');

        if ($transport === 'smtp') {
            $this->sendViaSMTP($toEmail, $subject, $bodyHtml);
        } else {
            $this->sendViaMail($toEmail, $subject, $bodyHtml, $bodyText);
        }
    }

    private function sendViaSMTP(string $toEmail, string $subject, string $bodyHtml): void
    {
        $host       = (string) ($this->config['host'] ?? '127.0.0.1');
        $port       = (int)    ($this->config['port'] ?? 587);
        $encryption = (string) ($this->config['encryption'] ?? 'tls');
        $username   = (string) ($this->config['username'] ?? '');
        $password   = (string) ($this->config['password'] ?? '');
        $fromAddr   = (string) ($this->config['from_address'] ?? '');
        $fromName   = (string) ($this->config['from_name'] ?? 'HR System');

        $prefix = $encryption === 'ssl' ? 'ssl://' : '';
        $socket = @fsockopen($prefix . $host, $port, $errno, $errstr, 10);

        if (!$socket) {
            throw new RuntimeException("SMTP connect failed: {$errstr} ({$errno})");
        }

        stream_set_timeout($socket, 30);

        $this->smtpRead($socket, 220);
        $this->smtpWrite($socket, "EHLO localhost\r\n", 250);

        if ($encryption === 'tls') {
            $this->smtpWrite($socket, "STARTTLS\r\n", 220);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('STARTTLS handshake failed');
            }
            $this->smtpWrite($socket, "EHLO localhost\r\n", 250);
        }

        if ($username !== '') {
            $this->smtpWrite($socket, "AUTH LOGIN\r\n", 334);
            $this->smtpWrite($socket, base64_encode($username) . "\r\n", 334);
            $this->smtpWrite($socket, base64_encode($password) . "\r\n", 235);
        }

        $this->smtpWrite($socket, "MAIL FROM:<{$fromAddr}>\r\n", 250);
        $this->smtpWrite($socket, "RCPT TO:<{$toEmail}>\r\n", 250);
        $this->smtpWrite($socket, "DATA\r\n", 354);

        $headers = "From: {$fromName} <{$fromAddr}>\r\n"
            . "To: {$toEmail}\r\n"
            . "Subject: {$subject}\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Date: " . date('r') . "\r\n"
            . "\r\n";

        fwrite($socket, $headers . $bodyHtml . "\r\n.\r\n");
        $this->smtpRead($socket, 250);
        $this->smtpWrite($socket, "QUIT\r\n", 221);
        fclose($socket);
    }

    private function sendViaMail(string $toEmail, string $subject, string $bodyHtml, ?string $bodyText): void
    {
        $fromAddr = (string) ($this->config['from_address'] ?? '');
        $fromName = (string) ($this->config['from_name'] ?? 'HR System');
        $headers  = "From: {$fromName} <{$fromAddr}>\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
        $body     = $bodyHtml !== '' ? $bodyHtml : (string) $bodyText;

        if (!mail($toEmail, $subject, $body, $headers)) {
            throw new RuntimeException('mail() returned false.');
        }
    }

    private function smtpWrite($socket, string $data, int $expectedCode): void
    {
        fwrite($socket, $data);
        $this->smtpRead($socket, $expectedCode);
    }

    private function smtpRead($socket, int $expectedCode): string
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
}
