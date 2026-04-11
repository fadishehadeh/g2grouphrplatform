<?php
/**
 * ╔══════════════════════════════════════════════════════════╗
 * ║        HR Management System — Installer v1.0             ║
 * ║        Full System Installation Wizard for cPanel        ║
 * ╚══════════════════════════════════════════════════════════╝
 *
 * ACCESS:  https://yourdomain.com/install_hr.php
 * REMOVE:  Delete this file after installation is complete!
 */

declare(strict_types=1);
define('HR_INSTALLER', true);
define('INSTALLER_VERSION', '1.0.0');
define('ROOT_PATH', dirname(__DIR__));
define('LOCK_FILE', ROOT_PATH . '/storage/installer_hr.lock');
define('ENV_FILE', ROOT_PATH . '/.env');
define('TOTAL_STEPS', 7);

// ── Bootstrap ────────────────────────────────────────────────────────────────
set_time_limit(300);
ini_set('display_errors', '0');
error_reporting(E_ALL);

session_name('hr_installer');
session_start();

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('X-Frame-Options: SAMEORIGIN');

// ── Lock check ───────────────────────────────────────────────────────────────
if (file_exists(LOCK_FILE) && !isset($_GET['force'])) {
    renderLocked(); exit;
}

// ── Helpers ──────────────────────────────────────────────────────────────────

function redirect(int $step): void {
    header('Location: install_hr.php?step=' . $step);
    exit;
}

function sessionSet(string $key, mixed $value): void {
    $_SESSION['hr_install'][$key] = $value;
}

function sessionGet(string $key, mixed $default = null): mixed {
    return $_SESSION['hr_install'][$key] ?? $default;
}

function e(mixed $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function fmtBytes(int $bytes): string {
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 1) . ' GB';
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    return round($bytes / 1024, 1) . ' KB';
}

function parseMemory(string $val): int {
    $val = trim($val);
    $last = strtolower($val[-1]);
    $num = (int)$val;
    return match($last) { 'g' => $num * 1073741824, 'm' => $num * 1048576, 'k' => $num * 1024, default => $num };
}

// ── SQL Execution ────────────────────────────────────────────────────────────

function splitSql(string $sql): array {
    // Strip block comments
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
    // Strip line comments
    $sql = preg_replace('/^--[^\n]*$/m', '', $sql);
    $sql = preg_replace('/^#[^\n]*$/m', '', $sql);

    $statements = [];
    $current = '';
    $len = strlen($sql);
    $inString = false;
    $stringChar = '';
    $escaped = false;

    for ($i = 0; $i < $len; $i++) {
        $c = $sql[$i];
        if ($escaped) { $current .= $c; $escaped = false; continue; }
        if ($c === '\\' && $inString) { $current .= $c; $escaped = true; continue; }
        if ($inString) {
            $current .= $c;
            if ($c === $stringChar) $inString = false;
            continue;
        }
        if ($c === "'" || $c === '"' || $c === '`') {
            $inString = true; $stringChar = $c; $current .= $c; continue;
        }
        if ($c === ';') {
            $s = trim($current);
            if ($s !== '') $statements[] = $s;
            $current = ''; continue;
        }
        $current .= $c;
    }
    $s = trim($current);
    if ($s !== '') $statements[] = $s;
    return $statements;
}

function runSqlFile(PDO $pdo, string $file, string $dbName): array {
    $results = ['ok' => 0, 'skip' => 0, 'errors' => []];
    if (!file_exists($file)) {
        $results['errors'][] = 'File not found: ' . basename($file);
        return $results;
    }
    $sql = file_get_contents($file);
    // Replace CREATE DATABASE / USE statements — we already selected the DB
    $sql = preg_replace('/^CREATE\s+DATABASE\s+.*?;\s*$/im', '', $sql);
    $sql = preg_replace('/^USE\s+\S+;\s*$/im', '', $sql);

    foreach (splitSql($sql) as $stmt) {
        if (trim($stmt) === '') continue;
        try {
            $pdo->exec($stmt);
            $results['ok']++;
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            // Ignore "already exists" / duplicate entry warnings
            if (str_contains($msg, 'already exists') || str_contains($msg, 'Duplicate entry')) {
                $results['skip']++;
            } else {
                $results['errors'][] = substr($msg, 0, 200);
            }
        }
    }
    return $results;
}

function createPdo(string $host, int $port, string $user, string $pass, string $db = ''): PDO {
    $dsn = "mysql:host={$host};port={$port};charset=utf8mb4" . ($db ? ";dbname={$db}" : '');
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 10,
    ]);
}

function testDbConnection(string $host, int $port, string $user, string $pass): array {
    try {
        $pdo = createPdo($host, $port, $user, $pass);
        $version = $pdo->query('SELECT VERSION()')->fetchColumn();
        return ['ok' => true, 'version' => $version];
    } catch (PDOException $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

// ── Requirements Check ───────────────────────────────────────────────────────

function checkRequirements(): array {
    $checks = [];

    // PHP Version
    $phpOk = version_compare(PHP_VERSION, '8.0.0', '>=');
    $checks[] = ['label' => 'PHP Version (≥ 8.0)', 'value' => PHP_VERSION, 'status' => $phpOk ? 'pass' : 'fail', 'required' => true];

    // Extensions
    $exts = [
        ['sodium',   true,  'libsodium — field-level PII encryption'],
        ['pdo',      true,  'PDO — database abstraction'],
        ['pdo_mysql',true,  'PDO MySQL driver'],
        ['mbstring', true,  'Multibyte string handling'],
        ['fileinfo', true,  'File upload validation'],
        ['openssl',  true,  'Password hashing & TLS'],
        ['json',     true,  'JSON encoding/decoding'],
        ['curl',     false, 'cURL — reCAPTCHA & external requests'],
        ['gd',       false, 'GD — image processing'],
        ['zip',      false, 'ZIP — archive support'],
    ];
    foreach ($exts as [$ext, $required, $desc]) {
        $loaded = extension_loaded($ext);
        $checks[] = [
            'label'    => "ext-{$ext}",
            'value'    => $desc,
            'status'   => $loaded ? 'pass' : ($required ? 'fail' : 'warn'),
            'required' => $required,
        ];
    }

    // PHP INI settings
    $memOk = parseMemory(ini_get('memory_limit')) >= 128 * 1048576;
    $checks[] = ['label' => 'memory_limit (≥ 128M)', 'value' => ini_get('memory_limit'), 'status' => $memOk ? 'pass' : 'warn', 'required' => false];

    $upOk = parseMemory(ini_get('upload_max_filesize')) >= 8 * 1048576;
    $checks[] = ['label' => 'upload_max_filesize (≥ 8M)', 'value' => ini_get('upload_max_filesize'), 'status' => $upOk ? 'pass' : 'warn', 'required' => false];

    $postOk = parseMemory(ini_get('post_max_size')) >= 10 * 1048576;
    $checks[] = ['label' => 'post_max_size (≥ 10M)', 'value' => ini_get('post_max_size'), 'status' => $postOk ? 'pass' : 'warn', 'required' => false];

    $execOk = (int)ini_get('max_execution_time') >= 60 || (int)ini_get('max_execution_time') === 0;
    $checks[] = ['label' => 'max_execution_time (≥ 60s)', 'value' => ini_get('max_execution_time') . 's', 'status' => $execOk ? 'pass' : 'warn', 'required' => false];

    // mod_rewrite
    $rewrite = function_exists('apache_get_modules') ? in_array('mod_rewrite', apache_get_modules()) : true;
    $checks[] = ['label' => 'mod_rewrite', 'value' => $rewrite ? 'Enabled' : 'Unknown — verify in cPanel', 'status' => $rewrite ? 'pass' : 'warn', 'required' => false];

    // Disk space
    $free = disk_free_space(ROOT_PATH);
    $diskOk = $free === false || $free >= 100 * 1048576;
    $checks[] = ['label' => 'Free Disk Space (≥ 100 MB)', 'value' => $free !== false ? fmtBytes((int)$free) : 'Unknown', 'status' => $diskOk ? 'pass' : 'warn', 'required' => false];

    // Write permissions
    $writePaths = [
        ROOT_PATH,
        ROOT_PATH . '/storage',
        ROOT_PATH . '/public/assets/uploads',
    ];
    foreach ($writePaths as $p) {
        $exists = file_exists($p);
        $writable = $exists && is_writable($p);
        $label = str_replace(ROOT_PATH, '', $p) ?: '/';
        $checks[] = ['label' => "Writable: {$label}", 'value' => $exists ? ($writable ? 'Writable' : 'Not writable') : 'Directory missing', 'status' => $writable ? 'pass' : ($exists ? 'fail' : 'warn'), 'required' => $p === ROOT_PATH];
    }

    return $checks;
}

function requirementsPass(array $checks): bool {
    foreach ($checks as $c) {
        if ($c['required'] && $c['status'] === 'fail') return false;
    }
    return true;
}

// ── .env Writer ──────────────────────────────────────────────────────────────

function writeEnv(array $cfg): bool {
    $encKey = $cfg['encryption_key'] ?? bin2hex(random_bytes(32));
    $lines = [
        '# ── Application ──────────────────────────────',
        'APP_NAME="' . addslashes($cfg['app_name']) . '"',
        'APP_ENV=production',
        'APP_DEBUG=false',
        'APP_URL=' . rtrim($cfg['app_url'], '/'),
        'APP_TIMEZONE=' . ($cfg['timezone'] ?? 'UTC'),
        '',
        '# ── Database ─────────────────────────────────',
        'DB_HOST=' . $cfg['db_host'],
        'DB_PORT=' . $cfg['db_port'],
        'DB_DATABASE=' . $cfg['db_name'],
        'DB_USERNAME=' . $cfg['db_user'],
        'DB_PASSWORD=' . $cfg['db_pass'],
        '',
        '# ── Careers Database ─────────────────────────',
        'CAREERS_DB_HOST=' . $cfg['cdb_host'],
        'CAREERS_DB_PORT=' . $cfg['cdb_port'],
        'CAREERS_DB_DATABASE=' . $cfg['cdb_name'],
        'CAREERS_DB_USERNAME=' . $cfg['cdb_user'],
        'CAREERS_DB_PASSWORD=' . $cfg['cdb_pass'],
        '',
        '# ── Encryption ───────────────────────────────',
        'ENCRYPTION_KEY=' . $encKey,
        '',
        '# ── Mail ─────────────────────────────────────',
        'MAIL_ENABLED=' . ($cfg['mail_enabled'] ? 'true' : 'false'),
        'MAIL_TRANSPORT=smtp',
        'MAIL_HOST=' . ($cfg['mail_host'] ?? ''),
        'MAIL_PORT=' . ($cfg['mail_port'] ?? '587'),
        'MAIL_ENCRYPTION=' . ($cfg['mail_enc'] ?? 'tls'),
        'MAIL_USERNAME=' . ($cfg['mail_user'] ?? ''),
        'MAIL_PASSWORD=' . ($cfg['mail_pass'] ?? ''),
        'MAIL_FROM_ADDRESS=' . ($cfg['mail_from'] ?? ''),
        'MAIL_FROM_NAME="' . addslashes($cfg['app_name']) . '"',
        '',
        '# ── Session ──────────────────────────────────',
        'SESSION_NAME=hr_system_session',
        'SESSION_IDLE_TIMEOUT=7200',
        '',
        '# ── Security ─────────────────────────────────',
        'LOGIN_LOCKOUT_ATTEMPTS=5',
        'LOGIN_LOCKOUT_MINUTES=15',
        'PASSWORD_RESET_EXPIRY_MINUTES=60',
        '',
        '# ── reCAPTCHA (optional) ─────────────────────',
        'RECAPTCHA_ENABLED=false',
        'RECAPTCHA_SITE_KEY=',
        'RECAPTCHA_SECRET_KEY=',
    ];
    return (bool) file_put_contents(ENV_FILE, implode("\n", $lines) . "\n");
}

// ── Install Runner ───────────────────────────────────────────────────────────

function runInstall(array $cfg): array {
    $log = [];
    $fatal = false;

    // 1. Ensure storage/ directory exists
    $storagePath = ROOT_PATH . '/storage';
    if (!is_dir($storagePath)) {
        @mkdir($storagePath, 0755, true);
    }
    $uploadsPath = ROOT_PATH . '/public/assets/uploads';
    if (!is_dir($uploadsPath)) {
        @mkdir($uploadsPath, 0755, true);
    }
    $log[] = ['msg' => 'Storage directories verified', 'type' => 'ok'];

    // 2. Write .env
    if (!writeEnv($cfg)) {
        $log[] = ['msg' => 'Failed to write .env file — check directory permissions', 'type' => 'error'];
        $fatal = true;
    } else {
        $log[] = ['msg' => '.env configuration file written', 'type' => 'ok'];
    }
    if ($fatal) return ['log' => $log, 'fatal' => true];

    // 3. Connect to HR database
    try {
        $hrPdo = createPdo($cfg['db_host'], (int)$cfg['db_port'], $cfg['db_user'], $cfg['db_pass']);
        $hrPdo->exec("CREATE DATABASE IF NOT EXISTS `{$cfg['db_name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $hrPdo->exec("USE `{$cfg['db_name']}`");
        $log[] = ['msg' => "Database `{$cfg['db_name']}` created/verified", 'type' => 'ok'];
    } catch (PDOException $e) {
        $log[] = ['msg' => 'HR database error: ' . $e->getMessage(), 'type' => 'error'];
        return ['log' => $log, 'fatal' => true];
    }

    // 4. Run HR SQL files in order
    $hrFiles = [
        'schema.sql'               => 'Core schema (tables & indexes)',
        'seed.sql'                 => 'Base data (roles, permissions)',
        'letters_migration.sql'    => 'Letters module',
        'photo_insurance_migration.sql' => 'Photo & insurance fields',
        'security_migration.sql'   => 'Security & audit tables',
        'encrypt_pii_migration.sql'=> 'PII encryption column widening',
    ];
    foreach ($hrFiles as $file => $desc) {
        $path = ROOT_PATH . '/database/' . $file;
        $result = runSqlFile($hrPdo, $path, $cfg['db_name']);
        $warn = !empty($result['errors']) ? ' (' . count($result['errors']) . ' warnings)' : '';
        $log[] = ['msg' => "{$desc}: {$result['ok']} statements executed{$warn}", 'type' => !empty($result['errors']) ? 'warn' : 'ok'];
        foreach (array_slice($result['errors'], 0, 3) as $err) {
            $log[] = ['msg' => '  ↳ ' . $err, 'type' => 'warn'];
        }
    }

    // 5. Create custom admin account (replaces seed default)
    if (!empty($cfg['admin_email']) && !empty($cfg['admin_pass'])) {
        try {
            $hash = password_hash($cfg['admin_pass'], PASSWORD_BCRYPT, ['cost' => 12]);
            $hrPdo->prepare("
                INSERT INTO users (role_id, username, email, password_hash, first_name, last_name, status, must_change_password)
                VALUES (1, ?, ?, ?, ?, ?, 'active', 1)
                ON DUPLICATE KEY UPDATE email=VALUES(email), password_hash=VALUES(password_hash),
                    first_name=VALUES(first_name), last_name=VALUES(last_name)
            ")->execute([
                $cfg['admin_username'],
                $cfg['admin_email'],
                $hash,
                $cfg['admin_fname'] ?: 'System',
                $cfg['admin_lname'] ?: 'Admin',
            ]);
            $log[] = ['msg' => "Admin account created: {$cfg['admin_email']}", 'type' => 'ok'];
        } catch (PDOException $e) {
            $log[] = ['msg' => 'Admin account warning: ' . $e->getMessage(), 'type' => 'warn'];
        }
    }

    // 6. Connect to Careers database
    try {
        $cdbHost = $cfg['cdb_host'];
        $cdbPdo  = createPdo($cdbHost, (int)$cfg['cdb_port'], $cfg['cdb_user'], $cfg['cdb_pass']);
        $cdbPdo->exec("CREATE DATABASE IF NOT EXISTS `{$cfg['cdb_name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $cdbPdo->exec("USE `{$cfg['cdb_name']}`");
        $log[] = ['msg' => "Careers database `{$cfg['cdb_name']}` created/verified", 'type' => 'ok'];

        $result = runSqlFile($cdbPdo, ROOT_PATH . '/database/careers_migration.sql', $cfg['cdb_name']);
        $warn = !empty($result['errors']) ? ' (' . count($result['errors']) . ' warnings)' : '';
        $log[] = ['msg' => "Careers schema: {$result['ok']} statements executed{$warn}", 'type' => !empty($result['errors']) ? 'warn' : 'ok'];
    } catch (PDOException $e) {
        $log[] = ['msg' => 'Careers database warning: ' . $e->getMessage(), 'type' => 'warn'];
    }

    // 7. Write lock file
    @mkdir(dirname(LOCK_FILE), 0755, true);
    file_put_contents(LOCK_FILE, json_encode([
        'installed_at' => date('Y-m-d H:i:s'),
        'version'      => INSTALLER_VERSION,
        'app_url'      => $cfg['app_url'],
    ]));
    $log[] = ['msg' => 'Install lock file written', 'type' => 'ok'];

    return ['log' => $log, 'fatal' => false];
}

// ── Step Handlers ─────────────────────────────────────────────────────────────

$step = max(1, min(TOTAL_STEPS + 1, (int)($_GET['step'] ?? 1)));
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post = $_POST;

    if ($step === 2) {
        // Requirements — just advance
        redirect(3);
    }

    if ($step === 3) {
        // Database config
        $host    = trim($post['db_host'] ?? '127.0.0.1');
        $port    = (int)($post['db_port'] ?? 3306);
        $user    = trim($post['db_user'] ?? '');
        $pass    = $post['db_pass'] ?? '';
        $dbName  = trim($post['db_name'] ?? '');
        $useMain = isset($post['cdb_same']) && $post['cdb_same'] === '1';
        $cHost   = $useMain ? $host : trim($post['cdb_host'] ?? $host);
        $cPort   = $useMain ? $port : (int)($post['cdb_port'] ?? $port);
        $cUser   = $useMain ? $user : trim($post['cdb_user'] ?? $user);
        $cPass   = $useMain ? $pass : ($post['cdb_pass'] ?? $pass);
        $cName   = trim($post['cdb_name'] ?? '');

        if (!$user)   $errors[] = 'Database username is required.';
        if (!$dbName) $errors[] = 'HR database name is required.';
        if (!$cName)  $errors[] = 'Careers database name is required.';

        if (empty($errors)) {
            $test = testDbConnection($host, $port, $user, $pass);
            if (!$test['ok']) $errors[] = 'Cannot connect to database: ' . $test['error'];
        }

        if (empty($errors)) {
            sessionSet('db', compact('host','port','user','pass','dbName','cHost','cPort','cUser','cPass','cName'));
            sessionSet('db_version', $test['version'] ?? '');
            redirect(4);
        }
    }

    if ($step === 4) {
        // App settings
        $appUrl  = rtrim(trim($post['app_url'] ?? ''), '/');
        $appName = trim($post['app_name'] ?? 'HR Management System');
        $tz      = trim($post['timezone'] ?? 'UTC');
        $encKey  = trim($post['enc_key'] ?? '') ?: bin2hex(random_bytes(32));

        if (!$appUrl) $errors[] = 'Application URL is required.';
        if (!filter_var($appUrl, FILTER_VALIDATE_URL)) $errors[] = 'Application URL must be a valid URL (include https://).';

        if (empty($errors)) {
            sessionSet('app', compact('appUrl','appName','tz','encKey'));
            redirect(5);
        }
    }

    if ($step === 5) {
        // Admin account
        $fname    = trim($post['fname'] ?? '');
        $lname    = trim($post['lname'] ?? '');
        $username = trim($post['username'] ?? 'admin');
        $email    = trim($post['email'] ?? '');
        $pass1    = $post['pass1'] ?? '';
        $pass2    = $post['pass2'] ?? '';

        if (!$fname)  $errors[] = 'First name is required.';
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
        if (strlen($pass1) < 8) $errors[] = 'Password must be at least 8 characters.';
        if ($pass1 !== $pass2)  $errors[] = 'Passwords do not match.';
        if (!preg_match('/[A-Z]/', $pass1)) $errors[] = 'Password must contain at least one uppercase letter.';
        if (!preg_match('/[0-9]/', $pass1)) $errors[] = 'Password must contain at least one number.';

        if (empty($errors)) {
            sessionSet('admin', compact('fname','lname','username','email','pass1'));
            redirect(6);
        }
    }

    if ($step === 6) {
        // Email config
        $enabled   = isset($post['mail_enabled']) && $post['mail_enabled'] === '1';
        $mailHost  = trim($post['mail_host'] ?? '');
        $mailPort  = (int)($post['mail_port'] ?? 587);
        $mailEnc   = trim($post['mail_enc'] ?? 'tls');
        $mailUser  = trim($post['mail_user'] ?? '');
        $mailPass  = $post['mail_pass'] ?? '';
        $mailFrom  = trim($post['mail_from'] ?? '');

        if ($enabled && !$mailHost)  $errors[] = 'SMTP host is required when mail is enabled.';
        if ($enabled && !$mailFrom)  $errors[] = 'From address is required when mail is enabled.';

        if (empty($errors)) {
            sessionSet('mail', compact('enabled','mailHost','mailPort','mailEnc','mailUser','mailPass','mailFrom'));
            redirect(7);
        }
    }

    if ($step === 7 && isset($post['do_install'])) {
        // Run installation
        $db    = sessionGet('db', []);
        $app   = sessionGet('app', []);
        $admin = sessionGet('admin', []);
        $mail  = sessionGet('mail', []);

        $cfg = [
            'db_host'          => $db['host'] ?? '127.0.0.1',
            'db_port'          => $db['port'] ?? 3306,
            'db_user'          => $db['user'] ?? '',
            'db_pass'          => $db['pass'] ?? '',
            'db_name'          => $db['dbName'] ?? '',
            'cdb_host'         => $db['cHost'] ?? '127.0.0.1',
            'cdb_port'         => $db['cPort'] ?? 3306,
            'cdb_user'         => $db['cUser'] ?? '',
            'cdb_pass'         => $db['cPass'] ?? '',
            'cdb_name'         => $db['cName'] ?? '',
            'app_url'          => $app['appUrl'] ?? '',
            'app_name'         => $app['appName'] ?? 'HR Management System',
            'timezone'         => $app['tz'] ?? 'UTC',
            'encryption_key'   => $app['encKey'] ?? bin2hex(random_bytes(32)),
            'admin_fname'      => $admin['fname'] ?? 'System',
            'admin_lname'      => $admin['lname'] ?? 'Admin',
            'admin_username'   => $admin['username'] ?? 'admin',
            'admin_email'      => $admin['email'] ?? '',
            'admin_pass'       => $admin['pass1'] ?? '',
            'mail_enabled'     => $mail['enabled'] ?? false,
            'mail_host'        => $mail['mailHost'] ?? '',
            'mail_port'        => $mail['mailPort'] ?? 587,
            'mail_enc'         => $mail['mailEnc'] ?? 'tls',
            'mail_user'        => $mail['mailUser'] ?? '',
            'mail_pass'        => $mail['mailPass'] ?? '',
            'mail_from'        => $mail['mailFrom'] ?? '',
        ];

        $result = runInstall($cfg);
        sessionSet('install_result', $result);
        sessionSet('install_cfg', $cfg);
        redirect(8);
    }
}

// ── Render ────────────────────────────────────────────────────────────────────

$stepLabels = [
    1 => 'Welcome',
    2 => 'Server Check',
    3 => 'Database',
    4 => 'App Settings',
    5 => 'Admin Account',
    6 => 'Email',
    7 => 'Install',
    8 => 'Complete',
];

function renderLocked(): void {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Already Installed</title>
    <style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;background:#f0f4f8}
    .box{background:#fff;padding:2rem 3rem;border-radius:8px;box-shadow:0 2px 20px rgba(0,0,0,.1);max-width:500px;text-align:center}
    h2{color:#1a56db}p{color:#555}code{background:#f3f4f6;padding:2px 6px;border-radius:4px;font-size:.9em}</style>
    </head><body><div class="box"><h2>&#9989; Already Installed</h2>
    <p>The HR System installer has already been run.</p>
    <p>To re-run: delete <code>storage/installer_hr.lock</code> or add <code>?force=1</code> to the URL.</p>
    <p><strong>Remember to delete this installer file from your server!</strong></p></div></body></html>';
}

ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>HR System Installer v<?= INSTALLER_VERSION ?></title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --blue: #1a56db; --blue-d: #1240a0; --blue-l: #e8f0fe;
  --green: #0e9f6e; --red: #f05252; --yellow: #e3a008;
  --gray: #6b7280; --gray-l: #f9fafb; --border: #e5e7eb;
  --text: #111827; --text-s: #374151;
}
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f3f4f6; color: var(--text); font-size: 15px; line-height: 1.5; }
a { color: var(--blue); }

/* Layout */
.installer-wrap { display: flex; min-height: 100vh; }
.sidebar { width: 260px; background: #1e2433; color: #c9d1e3; flex-shrink: 0; padding: 2rem 0; }
.sidebar-brand { padding: 0 1.5rem 1.5rem; border-bottom: 1px solid rgba(255,255,255,.08); }
.sidebar-brand h1 { font-size: 1.1rem; font-weight: 700; color: #fff; }
.sidebar-brand p { font-size: .78rem; color: #8892a0; margin-top: .2rem; }
.sidebar-steps { padding: 1.5rem 0; }
.step-item { display: flex; align-items: center; gap: .75rem; padding: .55rem 1.5rem; cursor: default; font-size: .88rem; }
.step-item.active { background: rgba(26,86,219,.3); color: #fff; }
.step-item.done { color: #6ee7b7; }
.step-item.pending { color: #6b7a9a; }
.step-num { width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: .75rem; font-weight: 700; flex-shrink: 0; }
.step-item.active  .step-num { background: var(--blue); color: #fff; }
.step-item.done    .step-num { background: var(--green); color: #fff; }
.step-item.pending .step-num { background: #2d3748; color: #6b7a9a; border: 1px solid #3d4759; }
.sidebar-footer { padding: 1.5rem; border-top: 1px solid rgba(255,255,255,.08); font-size: .78rem; color: #6b7a9a; }

/* Main */
.main { flex: 1; padding: 2.5rem; max-width: 860px; }
.step-header { margin-bottom: 1.75rem; }
.step-header h2 { font-size: 1.5rem; font-weight: 700; color: var(--text); }
.step-header p  { color: var(--gray); margin-top: .35rem; }
.badge-step { display: inline-block; font-size: .72rem; background: var(--blue-l); color: var(--blue); padding: 2px 8px; border-radius: 12px; font-weight: 600; margin-bottom: .6rem; }

/* Cards */
.card { background: #fff; border: 1px solid var(--border); border-radius: 10px; padding: 1.5rem; margin-bottom: 1.25rem; }
.card-title { font-size: .95rem; font-weight: 600; margin-bottom: 1rem; color: var(--text); display: flex; align-items: center; gap: .5rem; }
.card-title .icon { font-size: 1.1rem; }

/* Forms */
.form-group { margin-bottom: 1.1rem; }
.form-group label { display: block; font-size: .85rem; font-weight: 600; margin-bottom: .4rem; color: var(--text-s); }
.form-group input[type=text],
.form-group input[type=email],
.form-group input[type=password],
.form-group input[type=number],
.form-group select,
.form-group textarea {
  width: 100%; padding: .55rem .75rem; border: 1px solid var(--border); border-radius: 6px;
  font-size: .9rem; color: var(--text); background: #fff; transition: border .15s;
}
.form-group input:focus, .form-group select:focus { outline: none; border-color: var(--blue); box-shadow: 0 0 0 3px rgba(26,86,219,.1); }
.form-group .hint { font-size: .8rem; color: var(--gray); margin-top: .3rem; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.form-row-3 { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 1rem; }

/* Toggle */
.toggle-wrap { display: flex; align-items: center; gap: .75rem; }
.toggle-wrap input[type=checkbox] { width: 18px; height: 18px; cursor: pointer; }
.toggle-wrap label { font-size: .9rem; cursor: pointer; }

/* Requirements table */
.req-table { width: 100%; border-collapse: collapse; }
.req-table th { text-align: left; font-size: .8rem; font-weight: 600; color: var(--gray); padding: .5rem .75rem; border-bottom: 2px solid var(--border); }
.req-table td { padding: .55rem .75rem; border-bottom: 1px solid var(--border); font-size: .87rem; vertical-align: middle; }
.req-table tr:last-child td { border-bottom: none; }
.badge { display: inline-flex; align-items: center; gap: .3rem; padding: 2px 8px; border-radius: 12px; font-size: .78rem; font-weight: 600; }
.badge-pass   { background: #d1fae5; color: #065f46; }
.badge-fail   { background: #fee2e2; color: #991b1b; }
.badge-warn   { background: #fef3c7; color: #92400e; }
.req-label { font-weight: 500; }
.req-value { color: var(--gray); font-size: .83rem; }

/* Alerts */
.alert { padding: .75rem 1rem; border-radius: 6px; margin-bottom: 1.1rem; font-size: .88rem; display: flex; gap: .6rem; align-items: flex-start; }
.alert-error  { background: #fee2e2; border: 1px solid #fca5a5; color: #7f1d1d; }
.alert-warn   { background: #fef3c7; border: 1px solid #fcd34d; color: #78350f; }
.alert-success{ background: #d1fae5; border: 1px solid #6ee7b7; color: #065f46; }
.alert-info   { background: #dbeafe; border: 1px solid #93c5fd; color: #1e3a8a; }
.alert ul { margin: .4rem 0 0 1.2rem; }
.alert ul li { margin-top: .2rem; }

/* Buttons */
.btn-row { display: flex; gap: .75rem; align-items: center; margin-top: 1.75rem; flex-wrap: wrap; }
.btn { display: inline-flex; align-items: center; gap: .4rem; padding: .6rem 1.5rem; border-radius: 7px; font-size: .9rem; font-weight: 600; cursor: pointer; border: none; text-decoration: none; transition: background .15s, transform .1s; }
.btn:active { transform: translateY(1px); }
.btn-primary { background: var(--blue); color: #fff; }
.btn-primary:hover { background: var(--blue-d); }
.btn-secondary { background: #fff; color: var(--text-s); border: 1px solid var(--border); }
.btn-secondary:hover { background: var(--gray-l); }
.btn-success { background: var(--green); color: #fff; }
.btn-success:hover { background: #0b8a5e; }
.btn-danger { background: var(--red); color: #fff; }

/* Install log */
.install-log { background: #1e2433; color: #c9d1e3; border-radius: 8px; padding: 1.25rem; font-family: 'Courier New', monospace; font-size: .83rem; max-height: 400px; overflow-y: auto; }
.install-log .log-ok    { color: #6ee7b7; }
.install-log .log-warn  { color: #fcd34d; }
.install-log .log-error { color: #fca5a5; }
.install-log .log-line  { padding: .1rem 0; }

/* Spec grid */
.spec-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: .75rem; }
.spec-box { background: var(--gray-l); border: 1px solid var(--border); border-radius: 8px; padding: .9rem 1rem; }
.spec-box .spec-label { font-size: .78rem; font-weight: 600; color: var(--gray); text-transform: uppercase; letter-spacing: .04em; }
.spec-box .spec-val   { font-size: .95rem; font-weight: 600; color: var(--text); margin-top: .2rem; }

/* Welcome */
.feature-list { display: grid; grid-template-columns: 1fr 1fr; gap: .6rem; margin: .75rem 0; }
.feature-item { display: flex; align-items: center; gap: .5rem; font-size: .88rem; color: var(--text-s); }
.feature-item::before { content: '✓'; color: var(--green); font-weight: 700; }

/* Key display */
.key-box { font-family: 'Courier New', monospace; font-size: .82rem; background: var(--gray-l); border: 1px solid var(--border); border-radius: 6px; padding: .6rem .75rem; word-break: break-all; color: var(--text-s); }
.key-regen { font-size: .8rem; color: var(--blue); text-decoration: underline; cursor: pointer; }

/* Success step */
.success-hero { text-align: center; padding: 2rem; }
.success-hero .icon { font-size: 4rem; }
.success-hero h2 { font-size: 1.75rem; font-weight: 700; color: var(--green); margin: .75rem 0 .5rem; }
.credentials-box { background: #fff; border: 2px dashed var(--green); border-radius: 10px; padding: 1.25rem 1.5rem; margin: 1.25rem 0; }
.credentials-box table { width: 100%; border-collapse: collapse; }
.credentials-box td { padding: .4rem .25rem; font-size: .92rem; }
.credentials-box td:first-child { font-weight: 600; color: var(--gray); width: 140px; }

@media (max-width: 768px) {
  .installer-wrap { flex-direction: column; }
  .sidebar { width: 100%; padding: 1rem 0; }
  .main { padding: 1.25rem; }
  .form-row, .form-row-3, .spec-grid { grid-template-columns: 1fr; }
  .feature-list { grid-template-columns: 1fr; }
}
</style>
</head>
<body>
<div class="installer-wrap">

<!-- Sidebar -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <h1>&#128736; HR System</h1>
    <p>Installation Wizard v<?= INSTALLER_VERSION ?></p>
  </div>
  <nav class="sidebar-steps">
    <?php foreach ($stepLabels as $n => $label):
      if ($n > TOTAL_STEPS + 1) continue;
      $cls = $n === $step ? 'active' : ($n < $step ? 'done' : 'pending');
      $icon = $n < $step ? '✓' : $n;
    ?>
    <div class="step-item <?= $cls ?>">
      <span class="step-num"><?= $icon ?></span>
      <span><?= $label ?></span>
    </div>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-footer">
    <div>PHP <?= PHP_VERSION ?></div>
    <div><?= php_uname('s') ?> <?= php_uname('r') ?></div>
  </div>
</aside>

<!-- Main -->
<main class="main">

<?php if (!empty($errors)): ?>
<div class="alert alert-error">
  <span>⚠</span>
  <div><?php if (count($errors) === 1): echo e($errors[0]); else: ?>
    <strong>Please fix the following:</strong>
    <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
  <?php endif; ?></div>
</div>
<?php endif; ?>

<?php

// ── STEP 1: Welcome ───────────────────────────────────────────────────────────
if ($step === 1):
?>
<div class="step-header">
  <span class="badge-step">Step 1 of <?= TOTAL_STEPS ?></span>
  <h2>Welcome to the HR System Installer</h2>
  <p>This wizard will guide you through the complete installation of the HR Management System.</p>
</div>

<div class="card">
  <div class="card-title"><span class="icon">&#128203;</span> What will be installed</div>
  <div class="feature-list">
    <div class="feature-item">Employee Management</div>
    <div class="feature-item">Leave Management</div>
    <div class="feature-item">Role-Based Access Control</div>
    <div class="feature-item">Document Management</div>
    <div class="feature-item">Onboarding & Offboarding</div>
    <div class="feature-item">Announcements</div>
    <div class="feature-item">Letters & Certificates</div>
    <div class="feature-item">Reports & Analytics</div>
    <div class="feature-item">Careers Portal</div>
    <div class="feature-item">REST API (v1)</div>
    <div class="feature-item">Email Queue System</div>
    <div class="feature-item">PII Encryption (libsodium)</div>
  </div>
</div>

<div class="card">
  <div class="card-title"><span class="icon">&#9989;</span> Pre-installation checklist</div>
  <div class="alert alert-info" style="margin-bottom:.75rem">
    <span>&#8505;</span>
    <div>Before continuing, make sure you have:</div>
  </div>
  <ul style="list-style:none;display:grid;gap:.5rem">
    <li>&#9744; Created two MySQL databases in cPanel: one for the HR system, one for the Careers portal</li>
    <li>&#9744; Created database users and granted them full privileges on both databases</li>
    <li>&#9744; Noted your cPanel database credentials (host, name, user, password)</li>
    <li>&#9744; Set your domain's Document Root to the <code>public/</code> folder (or ensure .htaccess rewrites are working)</li>
    <li>&#9744; Verified that <code>mod_rewrite</code> is enabled on your hosting</li>
  </ul>
</div>

<div class="card">
  <div class="card-title"><span class="icon">&#128274;</span> Databases required</div>
  <table style="width:100%;border-collapse:collapse;font-size:.88rem">
    <tr><th style="text-align:left;padding:.4rem;border-bottom:2px solid var(--border)">Database</th><th style="text-align:left;padding:.4rem;border-bottom:2px solid var(--border)">Purpose</th></tr>
    <tr><td style="padding:.5rem .4rem;border-bottom:1px solid var(--border)"><strong>hr_system</strong> (configurable)</td><td style="padding:.5rem .4rem;border-bottom:1px solid var(--border);color:var(--gray)">Main HR platform — employees, leaves, roles, settings</td></tr>
    <tr><td style="padding:.5rem .4rem"><strong>hr_careers</strong> (configurable)</td><td style="padding:.5rem .4rem;color:var(--gray)">Careers portal — job seekers, applications, CV profiles</td></tr>
  </table>
</div>

<div class="btn-row">
  <a href="?step=2" class="btn btn-primary">Start Installation &rarr;</a>
</div>

<?php
// ── STEP 2: Server Requirements ───────────────────────────────────────────────
elseif ($step === 2):
  $checks = checkRequirements();
  $allPass = requirementsPass($checks);
  $passCount = count(array_filter($checks, fn($c) => $c['status'] === 'pass'));
  $failCount = count(array_filter($checks, fn($c) => $c['status'] === 'fail'));
  $warnCount = count(array_filter($checks, fn($c) => $c['status'] === 'warn'));
?>
<div class="step-header">
  <span class="badge-step">Step 2 of <?= TOTAL_STEPS ?></span>
  <h2>Server Requirements</h2>
  <p>Checking your server environment for compatibility.</p>
</div>

<!-- Server specs -->
<div class="card">
  <div class="card-title"><span class="icon">&#128187;</span> Server Environment</div>
  <div class="spec-grid">
    <div class="spec-box"><div class="spec-label">PHP Version</div><div class="spec-val"><?= PHP_VERSION ?></div></div>
    <div class="spec-box"><div class="spec-label">Server Software</div><div class="spec-val"><?= e(explode(' ', $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown')[0]) ?></div></div>
    <div class="spec-box"><div class="spec-label">Operating System</div><div class="spec-val"><?= e(PHP_OS_FAMILY) ?></div></div>
    <div class="spec-box"><div class="spec-label">Memory Limit</div><div class="spec-val"><?= ini_get('memory_limit') ?></div></div>
    <div class="spec-box"><div class="spec-label">Max Upload Size</div><div class="spec-val"><?= ini_get('upload_max_filesize') ?></div></div>
    <div class="spec-box"><div class="spec-label">Disk Free</div><div class="spec-val"><?= ($f = disk_free_space(ROOT_PATH)) !== false ? fmtBytes((int)$f) : 'N/A' ?></div></div>
  </div>
</div>

<!-- Results summary -->
<?php if (!$allPass): ?>
<div class="alert alert-error">
  <span>&#10060;</span>
  <div><strong><?= $failCount ?> critical requirement(s) failed.</strong> Please resolve these before proceeding. Contact your hosting provider if needed.</div>
</div>
<?php elseif ($warnCount > 0): ?>
<div class="alert alert-warn">
  <span>&#9888;</span>
  <div><strong><?= $warnCount ?> warning(s).</strong> These won't block installation but may affect functionality. You can proceed.</div>
</div>
<?php else: ?>
<div class="alert alert-success">
  <span>&#10003;</span>
  <div><strong>All requirements passed!</strong> Your server is ready for installation.</div>
</div>
<?php endif; ?>

<!-- Requirements table -->
<div class="card" style="padding:0;overflow:hidden">
  <table class="req-table">
    <thead><tr>
      <th style="width:220px">Requirement</th>
      <th>Details</th>
      <th style="width:100px">Status</th>
      <th style="width:80px">Required</th>
    </tr></thead>
    <tbody>
    <?php foreach ($checks as $c): ?>
    <tr>
      <td class="req-label"><?= e($c['label']) ?></td>
      <td class="req-value"><?= e($c['value']) ?></td>
      <td>
        <?php if ($c['status'] === 'pass'): ?>
          <span class="badge badge-pass">&#10003; Pass</span>
        <?php elseif ($c['status'] === 'fail'): ?>
          <span class="badge badge-fail">&#10007; Fail</span>
        <?php else: ?>
          <span class="badge badge-warn">&#9888; Warn</span>
        <?php endif; ?>
      </td>
      <td style="font-size:.82rem;color:var(--gray)"><?= $c['required'] ? 'Required' : 'Optional' ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<form method="post" action="?step=2">
  <div class="btn-row">
    <a href="?step=1" class="btn btn-secondary">&larr; Back</a>
    <?php if ($allPass): ?>
    <button type="submit" class="btn btn-primary">Continue &rarr;</button>
    <?php else: ?>
    <a href="?step=2" class="btn btn-secondary">&#8635; Re-check</a>
    <button type="submit" class="btn btn-danger" title="Proceed despite failures — not recommended">Force Continue</button>
    <?php endif; ?>
  </div>
</form>

<?php
// ── STEP 3: Database ──────────────────────────────────────────────────────────
elseif ($step === 3):
  $saved = sessionGet('db', []);
?>
<div class="step-header">
  <span class="badge-step">Step 3 of <?= TOTAL_STEPS ?></span>
  <h2>Database Configuration</h2>
  <p>Configure the MySQL databases for the HR system and Careers portal. Both databases can use the same MySQL user.</p>
</div>

<div class="alert alert-info">
  <span>&#8505;</span>
  <div>In cPanel, database names are usually prefixed with your account name (e.g. <code>username_hr_system</code>). Use the full prefixed name here.</div>
</div>

<form method="post" action="?step=3">
  <div class="card">
    <div class="card-title"><span class="icon">&#128196;</span> HR System Database</div>
    <div class="form-row-3">
      <div class="form-group">
        <label>Database Host</label>
        <input type="text" name="db_host" value="<?= e($saved['host'] ?? 'localhost') ?>" placeholder="localhost">
        <div class="hint">Usually <code>localhost</code> on cPanel</div>
      </div>
      <div class="form-group">
        <label>Port</label>
        <input type="number" name="db_port" value="<?= e($saved['port'] ?? '3306') ?>" placeholder="3306">
      </div>
      <div class="form-group" style=""></div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Database Name <span style="color:var(--red)">*</span></label>
        <input type="text" name="db_name" value="<?= e($saved['dbName'] ?? '') ?>" placeholder="cpanelusername_hr_system" required>
        <div class="hint">Must already exist in cPanel → MySQL Databases</div>
      </div>
      <div class="form-group">
        <label>Database User <span style="color:var(--red)">*</span></label>
        <input type="text" name="db_user" value="<?= e($saved['user'] ?? '') ?>" placeholder="cpanelusername_hruser" required>
      </div>
    </div>
    <div class="form-group">
      <label>Database Password</label>
      <input type="password" name="db_pass" value="<?= e($saved['pass'] ?? '') ?>" placeholder="Database user password">
    </div>
  </div>

  <div class="card">
    <div class="card-title"><span class="icon">&#127942;</span> Careers Portal Database</div>
    <div class="form-group">
      <div class="toggle-wrap">
        <input type="checkbox" name="cdb_same" id="cdb_same" value="1"
          <?= ($saved['cHost'] ?? '') === ($saved['host'] ?? 'localhost') && ($saved['cUser'] ?? '') === ($saved['user'] ?? '') ? 'checked' : '' ?>
          onchange="toggleCdb(this)">
        <label for="cdb_same">Use the same host/user/password as HR database</label>
      </div>
    </div>
    <div id="cdb_extra" style="display:none">
      <div class="form-row-3">
        <div class="form-group">
          <label>Careers DB Host</label>
          <input type="text" name="cdb_host" value="<?= e($saved['cHost'] ?? 'localhost') ?>" placeholder="localhost">
        </div>
        <div class="form-group">
          <label>Port</label>
          <input type="number" name="cdb_port" value="<?= e($saved['cPort'] ?? '3306') ?>" placeholder="3306">
        </div>
        <div class="form-group"></div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Careers DB User</label>
          <input type="text" name="cdb_user" value="<?= e($saved['cUser'] ?? '') ?>" placeholder="cpanelusername_hruser">
        </div>
        <div class="form-group">
          <label>Careers DB Password</label>
          <input type="password" name="cdb_pass" value="<?= e($saved['cPass'] ?? '') ?>">
        </div>
      </div>
    </div>
    <div class="form-group">
      <label>Careers Database Name <span style="color:var(--red)">*</span></label>
      <input type="text" name="cdb_name" value="<?= e($saved['cName'] ?? '') ?>" placeholder="cpanelusername_hr_careers" required>
      <div class="hint">Must be a separate database — do not reuse the HR database name</div>
    </div>
  </div>

  <div class="btn-row">
    <a href="?step=2" class="btn btn-secondary">&larr; Back</a>
    <button type="submit" class="btn btn-primary">Test &amp; Continue &rarr;</button>
  </div>
</form>
<script>
function toggleCdb(cb) {
  document.getElementById('cdb_extra').style.display = cb.checked ? 'none' : 'block';
}
// Init on load
document.addEventListener('DOMContentLoaded', function() {
  var cb = document.getElementById('cdb_same');
  if (cb) toggleCdb(cb);
});
</script>

<?php
// ── STEP 4: App Settings ──────────────────────────────────────────────────────
elseif ($step === 4):
  $saved = sessionGet('app', []);
  $defaultKey = $saved['encKey'] ?? bin2hex(random_bytes(32));
  $timezones = ['UTC','Asia/Riyadh','Asia/Qatar','Asia/Dubai','Asia/Kuwait','Asia/Bahrain','Asia/Muscat','Africa/Cairo','Europe/London','America/New_York','America/Los_Angeles','Asia/Karachi','Asia/Kolkata','Asia/Singapore'];
  $dbVer = sessionGet('db_version','');
?>
<div class="step-header">
  <span class="badge-step">Step 4 of <?= TOTAL_STEPS ?></span>
  <h2>Application Settings</h2>
  <p>Configure the core application parameters.</p>
</div>

<?php if ($dbVer): ?>
<div class="alert alert-success"><span>&#10003;</span> Database connection verified &mdash; MySQL <?= e($dbVer) ?></div>
<?php endif; ?>

<form method="post" action="?step=4">
  <div class="card">
    <div class="card-title"><span class="icon">&#127760;</span> Application</div>
    <div class="form-group">
      <label>Application URL <span style="color:var(--red)">*</span></label>
      <input type="text" name="app_url" value="<?= e($saved['appUrl'] ?? 'https://') ?>" placeholder="https://yourdomain.com" required>
      <div class="hint">Your full domain URL. No trailing slash. If installed in a subfolder: <code>https://domain.com/hr</code></div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Application Name</label>
        <input type="text" name="app_name" value="<?= e($saved['appName'] ?? 'HR Management System') ?>" placeholder="HR Management System">
      </div>
      <div class="form-group">
        <label>Timezone</label>
        <select name="timezone">
          <?php foreach ($timezones as $tz): ?>
          <option value="<?= $tz ?>" <?= ($saved['tz'] ?? 'UTC') === $tz ? 'selected' : '' ?>><?= $tz ?></option>
          <?php endforeach; ?>
        </select>
        <div class="hint">Used for date/time display and scheduling</div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-title"><span class="icon">&#128273;</span> Encryption Key</div>
    <div class="alert alert-warn" style="margin-bottom:.75rem">
      <span>&#9888;</span>
      <div><strong>Keep this key safe!</strong> It is used to encrypt sensitive employee data (PII). If lost, encrypted data cannot be recovered. Store it in a secure location.</div>
    </div>
    <div class="form-group">
      <label>Encryption Key (64-char hex)</label>
      <div class="key-box" id="key_display"><?= e($defaultKey) ?></div>
      <input type="hidden" name="enc_key" id="enc_key_input" value="<?= e($defaultKey) ?>">
      <div class="hint" style="margin-top:.4rem">Auto-generated using <code>random_bytes(32)</code> — cryptographically secure.</div>
    </div>
  </div>

  <div class="btn-row">
    <a href="?step=3" class="btn btn-secondary">&larr; Back</a>
    <button type="submit" class="btn btn-primary">Continue &rarr;</button>
  </div>
</form>

<?php
// ── STEP 5: Admin Account ─────────────────────────────────────────────────────
elseif ($step === 5):
  $saved = sessionGet('admin', []);
?>
<div class="step-header">
  <span class="badge-step">Step 5 of <?= TOTAL_STEPS ?></span>
  <h2>Administrator Account</h2>
  <p>Create the primary Super Admin account for the HR system.</p>
</div>

<form method="post" action="?step=5">
  <div class="card">
    <div class="card-title"><span class="icon">&#128100;</span> Admin Details</div>
    <div class="form-row">
      <div class="form-group">
        <label>First Name <span style="color:var(--red)">*</span></label>
        <input type="text" name="fname" value="<?= e($saved['fname'] ?? '') ?>" placeholder="John" required>
      </div>
      <div class="form-group">
        <label>Last Name</label>
        <input type="text" name="lname" value="<?= e($saved['lname'] ?? '') ?>" placeholder="Smith">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Username</label>
        <input type="text" name="username" value="<?= e($saved['username'] ?? 'admin') ?>" placeholder="admin">
        <div class="hint">Used for login — lowercase, no spaces</div>
      </div>
      <div class="form-group">
        <label>Email Address <span style="color:var(--red)">*</span></label>
        <input type="email" name="email" value="<?= e($saved['email'] ?? '') ?>" placeholder="admin@yourdomain.com" required>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Password <span style="color:var(--red)">*</span></label>
        <input type="password" name="pass1" placeholder="Minimum 8 characters" required>
        <div class="hint">Must include uppercase letter and number</div>
      </div>
      <div class="form-group">
        <label>Confirm Password <span style="color:var(--red)">*</span></label>
        <input type="password" name="pass2" placeholder="Repeat password" required>
      </div>
    </div>
  </div>
  <div class="alert alert-info">
    <span>&#8505;</span>
    <div>You will be prompted to change your password on first login (<code>must_change_password</code> = true).</div>
  </div>

  <div class="btn-row">
    <a href="?step=4" class="btn btn-secondary">&larr; Back</a>
    <button type="submit" class="btn btn-primary">Continue &rarr;</button>
  </div>
</form>

<?php
// ── STEP 6: Email Settings ────────────────────────────────────────────────────
elseif ($step === 6):
  $saved = sessionGet('mail', []);
?>
<div class="step-header">
  <span class="badge-step">Step 6 of <?= TOTAL_STEPS ?></span>
  <h2>Email Configuration</h2>
  <p>Configure SMTP for password resets, notifications and leave approvals. You can skip this and configure it later in Settings.</p>
</div>

<form method="post" action="?step=6" id="mail_form">
  <div class="card">
    <div class="card-title"><span class="icon">&#128231;</span> Mail Settings</div>
    <div class="form-group">
      <div class="toggle-wrap">
        <input type="checkbox" name="mail_enabled" id="mail_enabled" value="1"
          <?= ($saved['enabled'] ?? false) ? 'checked' : '' ?>
          onchange="toggleMail(this)">
        <label for="mail_enabled">Enable outbound email (SMTP)</label>
      </div>
    </div>
    <div id="mail_fields" style="<?= ($saved['enabled'] ?? false) ? '' : 'display:none' ?>">
      <div class="form-row-3">
        <div class="form-group">
          <label>SMTP Host</label>
          <input type="text" name="mail_host" value="<?= e($saved['mailHost'] ?? '') ?>" placeholder="smtp.mailjet.com">
        </div>
        <div class="form-group">
          <label>Port</label>
          <input type="number" name="mail_port" value="<?= e($saved['mailPort'] ?? '587') ?>" placeholder="587">
        </div>
        <div class="form-group">
          <label>Encryption</label>
          <select name="mail_enc">
            <option value="tls"  <?= ($saved['mailEnc'] ?? 'tls') === 'tls'  ? 'selected' : '' ?>>TLS (587)</option>
            <option value="ssl"  <?= ($saved['mailEnc'] ?? 'tls') === 'ssl'  ? 'selected' : '' ?>>SSL (465)</option>
            <option value=""     <?= ($saved['mailEnc'] ?? 'tls') === ''     ? 'selected' : '' ?>>None</option>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>SMTP Username</label>
          <input type="text" name="mail_user" value="<?= e($saved['mailUser'] ?? '') ?>" placeholder="your@email.com">
        </div>
        <div class="form-group">
          <label>SMTP Password</label>
          <input type="password" name="mail_pass" value="<?= e($saved['mailPass'] ?? '') ?>">
        </div>
      </div>
      <div class="form-group">
        <label>From Address</label>
        <input type="email" name="mail_from" value="<?= e($saved['mailFrom'] ?? '') ?>" placeholder="hr@yourdomain.com">
        <div class="hint">Emails will appear to come from this address</div>
      </div>
    </div>
  </div>

  <div class="btn-row">
    <a href="?step=5" class="btn btn-secondary">&larr; Back</a>
    <button type="submit" class="btn btn-primary">Continue &rarr;</button>
  </div>
</form>
<script>
function toggleMail(cb) {
  document.getElementById('mail_fields').style.display = cb.checked ? 'block' : 'none';
}
</script>

<?php
// ── STEP 7: Install ───────────────────────────────────────────────────────────
elseif ($step === 7):
  $db    = sessionGet('db', []);
  $app   = sessionGet('app', []);
  $admin = sessionGet('admin', []);
  $mail  = sessionGet('mail', []);
  if (!$db || !$app || !$admin) { redirect(1); }
?>
<div class="step-header">
  <span class="badge-step">Step 7 of <?= TOTAL_STEPS ?></span>
  <h2>Ready to Install</h2>
  <p>Review your configuration and click <strong>Install Now</strong> to begin.</p>
</div>

<div class="card">
  <div class="card-title"><span class="icon">&#128203;</span> Installation Summary</div>
  <table style="width:100%;border-collapse:collapse;font-size:.88rem">
    <tr><td style="padding:.4rem;font-weight:600;color:var(--gray);width:200px">Application URL</td><td><?= e($app['appUrl'] ?? '') ?></td></tr>
    <tr><td style="padding:.4rem;font-weight:600;color:var(--gray)">Application Name</td><td><?= e($app['appName'] ?? '') ?></td></tr>
    <tr><td style="padding:.4rem;font-weight:600;color:var(--gray)">Timezone</td><td><?= e($app['tz'] ?? '') ?></td></tr>
    <tr><td style="padding:.4rem;font-weight:600;color:var(--gray)">HR Database</td><td><?= e($db['dbName'] ?? '') ?> on <?= e($db['host'] ?? '') ?></td></tr>
    <tr><td style="padding:.4rem;font-weight:600;color:var(--gray)">Careers Database</td><td><?= e($db['cName'] ?? '') ?> on <?= e($db['cHost'] ?? '') ?></td></tr>
    <tr><td style="padding:.4rem;font-weight:600;color:var(--gray)">Admin Account</td><td><?= e($admin['email'] ?? '') ?> (<?= e($admin['username'] ?? '') ?>)</td></tr>
    <tr><td style="padding:.4rem;font-weight:600;color:var(--gray)">Email</td><td><?= ($mail['enabled'] ?? false) ? 'Enabled — ' . e($mail['mailHost'] ?? '') . ':' . e($mail['mailPort'] ?? '') : 'Disabled' ?></td></tr>
    <tr><td style="padding:.4rem;font-weight:600;color:var(--gray)">Encryption Key</td><td><span style="font-family:monospace;font-size:.8rem;word-break:break-all"><?= e(substr($app['encKey'] ?? '', 0, 16)) ?>...</span></td></tr>
  </table>
</div>

<div class="alert alert-warn">
  <span>&#9888;</span>
  <div><strong>Important:</strong> This will create databases, import SQL schemas, and write your <code>.env</code> file. Existing data in the named databases may be affected.</div>
</div>

<form method="post" action="?step=7">
  <div class="btn-row">
    <a href="?step=6" class="btn btn-secondary">&larr; Back</a>
    <button type="submit" name="do_install" value="1" class="btn btn-success">&#9654; Install Now</button>
  </div>
</form>

<?php
// ── STEP 8: Complete ──────────────────────────────────────────────────────────
elseif ($step === 8):
  $result = sessionGet('install_result', []);
  $cfg    = sessionGet('install_cfg', []);
  $fatal  = $result['fatal'] ?? true;
  $log    = $result['log'] ?? [];
  $appUrl = $cfg['app_url'] ?? '';
?>
<div class="step-header">
  <span class="badge-step">Step 8 of <?= TOTAL_STEPS ?></span>
  <h2><?= $fatal ? 'Installation Failed' : 'Installation Complete!' ?></h2>
</div>

<?php if ($fatal): ?>
<div class="alert alert-error"><span>&#10060;</span><div><strong>Installation encountered a fatal error.</strong> Check the log below and retry after fixing the issue.</div></div>
<?php else: ?>
<div class="success-hero">
  <div class="icon">&#127881;</div>
  <h2>HR System Installed Successfully!</h2>
  <p style="color:var(--gray)">Your HR Management System is ready to use.</p>
</div>

<div class="credentials-box">
  <div style="font-weight:700;margin-bottom:.75rem;font-size:.95rem">&#128273; Login Credentials</div>
  <table>
    <tr><td>URL:</td><td><a href="<?= e($appUrl) ?>/login" target="_blank"><?= e($appUrl) ?>/login</a></td></tr>
    <tr><td>Email:</td><td><?= e($cfg['admin_email'] ?? '') ?></td></tr>
    <tr><td>Password:</td><td><em>The password you set in Step 5</em></td></tr>
    <tr><td>Careers Portal:</td><td><a href="<?= e($appUrl) ?>/careers" target="_blank"><?= e($appUrl) ?>/careers</a></td></tr>
  </table>
</div>

<div class="card">
  <div class="card-title"><span class="icon">&#128221;</span> Post-Installation Checklist</div>
  <ul style="list-style:none;display:grid;gap:.5rem;font-size:.9rem">
    <li>&#9744; <strong>Delete this installer file:</strong> <code>public/install_hr.php</code></li>
    <li>&#9744; Set up cron job: <code>php <?= ROOT_PATH ?>/scripts/process-email-queue.php</code> — every 5 minutes</li>
    <li>&#9744; Set up cron job: <code>php <?= ROOT_PATH ?>/scripts/process-escalations.php</code> — every hour</li>
    <li>&#9744; Configure reCAPTCHA in <code>.env</code> (optional but recommended)</li>
    <li>&#9744; Set up SSL certificate if not already done</li>
    <li>&#9744; Save your encryption key from the <code>.env</code> file in a secure location</li>
    <li>&#9744; Test password reset email if SMTP is configured</li>
  </ul>
</div>

<div class="btn-row">
  <a href="<?= e($appUrl) ?>/login" class="btn btn-success" target="_blank">&#9654; Go to Login &rarr;</a>
  <a href="<?= e($appUrl) ?>/careers" class="btn btn-secondary" target="_blank">View Careers Portal</a>
</div>
<?php endif; ?>

<!-- Install Log -->
<div class="card" style="margin-top:1.5rem">
  <div class="card-title"><span class="icon">&#128196;</span> Installation Log</div>
  <div class="install-log">
    <?php foreach ($log as $entry): ?>
    <div class="log-line log-<?= e($entry['type']) ?>">
      <?= $entry['type'] === 'ok' ? '[OK]  ' : ($entry['type'] === 'warn' ? '[WARN]' : '[ERR] ') ?>
      <?= e($entry['msg']) ?>
    </div>
    <?php endforeach; ?>
    <?php if (empty($log)): ?><div class="log-line log-warn">No log entries found. Session may have expired.</div><?php endif; ?>
  </div>
</div>

<?php if (!$fatal): ?>
<div class="alert alert-error" style="margin-top:1rem">
  <span>&#128161;</span>
  <div><strong>Security reminder:</strong> Delete <code>public/install_hr.php</code> from your server immediately. Leaving it accessible is a security risk.</div>
</div>
<?php endif; ?>

<?php endif; // end step switch ?>

</main>
</div>
</body>
</html>
<?php
echo ob_get_clean();
