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
$mailer = new App\Support\Mailer($mailConfig);

if (!($mailConfig['enabled'] ?? false)) {
    echo '[' . date('Y-m-d H:i:s') . "] Mail is disabled. Set MAIL_ENABLED=true to process the queue.\n";
    exit(0);
}

$db = $app->database();
$maxAttempts = (int) ($mailConfig['max_attempts'] ?? 3);
$batchSize = 50;

$emails = $db->fetchAll(
    'SELECT id, user_id, to_email, subject, body_html, body_text, related_type, related_id, attempts
     FROM email_queue
     WHERE status = :status
       AND (scheduled_at IS NULL OR scheduled_at <= NOW())
       AND attempts < :max_attempts
     ORDER BY created_at ASC
     LIMIT ' . $batchSize,
    ['status' => 'pending', 'max_attempts' => $maxAttempts]
);

if ($emails === []) {
    echo '[' . date('Y-m-d H:i:s') . "] No pending emails.\n";
    exit(0);
}

echo '[' . date('Y-m-d H:i:s') . '] Processing ' . count($emails) . " email(s)...\n";

$sent = 0;
$failed = 0;

foreach ($emails as $email) {
    $emailId = (int) $email['id'];

    try {
        $mailer->send(
            (string) $email['to_email'],
            (string) $email['subject'],
            (string) ($email['body_html'] ?? ''),
            isset($email['body_text']) ? (string) $email['body_text'] : null
        );

        $db->execute(
            'UPDATE email_queue
             SET status = :status, sent_at = NOW(), attempts = attempts + 1
             WHERE id = :id',
            ['status' => 'sent', 'id' => $emailId]
        );

        $sent++;
        echo '  OK #' . $emailId . ' -> ' . $email['to_email'] . "\n";
    } catch (Throwable $e) {
        $db->execute(
            'UPDATE email_queue
             SET attempts = attempts + 1,
                 last_error = :error,
                 status = CASE WHEN attempts + 1 >= :max THEN :failed ELSE :pending END
             WHERE id = :id',
            [
                'error' => substr($e->getMessage(), 0, 500),
                'max' => $maxAttempts,
                'failed' => 'failed',
                'pending' => 'pending',
                'id' => $emailId,
            ]
        );

        $failed++;
        echo '  FAIL #' . $emailId . ' -> ' . $email['to_email'] . ': ' . $e->getMessage() . "\n";
    }
}

echo '[' . date('Y-m-d H:i:s') . "] Done. Sent: {$sent}, Failed: {$failed}\n";
