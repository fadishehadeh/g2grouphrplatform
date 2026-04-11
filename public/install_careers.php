<?php
/**
 * ╔══════════════════════════════════════════════════════════╗
 * ║        HR System — Careers Portal Installer v1.0         ║
 * ║        Standalone Careers Portal for cPanel              ║
 * ╚══════════════════════════════════════════════════════════╝
 *
 * Use this installer to set up only the Careers Portal,
 * connecting it to an already-installed HR System database.
 *
 * ACCESS:  https://yourdomain.com/install_careers.php
 * REMOVE:  Delete this file after installation is complete!
 */

declare(strict_types=1);
define('CAREERS_INSTALLER', true);
define('INSTALLER_VERSION', '1.0.0');
define('ROOT_PATH', dirname(__DIR__));
define('LOCK_FILE', ROOT_PATH . '/storage/installer_careers.lock');
define('ENV_FILE', ROOT_PATH . '/.env');
define('TOTAL_STEPS', 6);

// ── Bootstrap ────────────────────────────────────────────────────────────────
set_time_limit(300);
ini_set('display_errors', '0');
error_reporting(E_ALL);

session_name('careers_installer');
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
    header('Location: install_careers.php?step=' . $step);
    exit;
}

function sessionSet(string $key, mixed $value): void {
    $_SESSION['careers_install'][$key] = $value;
}

function sessionGet(string $key, mixed $default = null): mixed {
    return $_SESSION['careers_install'][$key] ?? $default;
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
    $last = strtolower($val[-1] ?? '');
    $num = (int)$val;
    return match($last) { 'g' => $num * 1073741824, 'm' => $num * 1048576, 'k' => $num * 1024, default => $num };
}

// ── SQL Execution ─────────────────────────────────────────────────────────────

function splitSql(string $sql): array {
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
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
        if ($inString) { $current .= $c; if ($c === $stringChar) $inString = false; continue; }
        if ($c === "'" || $c === '"' || $c === '`') { $inString = true; $stringChar = $c; $current .= $c; continue; }
        if ($c === ';') { $s = trim($current); if ($s !== '') $statements[] = $s; $current = ''; continue; }
        $current .= $c;
    }
    $s = trim($current); if ($s !== '') $statements[] = $s;
    return $statements;
}

function createPdo(string $host, int $port, string $user, string $pass, string $db = ''): PDO {
    $dsn = "mysql:host={$host};port={$port};charset=utf8mb4" . ($db ? ";dbname={$db}" : '');
    return new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 10]);
}

function testDbConnection(string $host, int $port, string $user, string $pass, string $db = ''): array {
    try {
        $pdo = createPdo($host, $port, $user, $pass, $db);
        $version = $pdo->query('SELECT VERSION()')->fetchColumn();
        return ['ok' => true, 'version' => $version];
    } catch (PDOException $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function runSqlFile(PDO $pdo, string $file): array {
    $results = ['ok' => 0, 'skip' => 0, 'errors' => []];
    if (!file_exists($file)) { $results['errors'][] = 'File not found: ' . basename($file); return $results; }
    $sql = file_get_contents($file);
    $sql = preg_replace('/^CREATE\s+DATABASE\s+.*?;\s*$/im', '', $sql);
    $sql = preg_replace('/^USE\s+\S+;\s*$/im', '', $sql);
    foreach (splitSql($sql) as $stmt) {
        if (trim($stmt) === '') continue;
        try {
            $pdo->exec($stmt);
            $results['ok']++;
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'already exists') || str_contains($msg, 'Duplicate entry')) {
                $results['skip']++;
            } else {
                $results['errors'][] = substr($msg, 0, 200);
            }
        }
    }
    return $results;
}

// ── Requirements Check ────────────────────────────────────────────────────────

function checkRequirements(): array {
    $checks = [];
    $phpOk = version_compare(PHP_VERSION, '8.0.0', '>=');
    $checks[] = ['label' => 'PHP Version (≥ 8.0)', 'value' => PHP_VERSION, 'status' => $phpOk ? 'pass' : 'fail', 'required' => true];
    $exts = [
        ['sodium',    true,  'libsodium — field-level PII encryption'],
        ['pdo',       true,  'PDO — database abstraction'],
        ['pdo_mysql', true,  'PDO MySQL driver'],
        ['mbstring',  true,  'Multibyte string handling'],
        ['fileinfo',  true,  'File upload validation'],
        ['openssl',   true,  'Password hashing & TLS'],
        ['json',      true,  'JSON encoding/decoding'],
        ['curl',      false, 'cURL — external requests & reCAPTCHA'],
    ];
    foreach ($exts as [$ext, $required, $desc]) {
        $loaded = extension_loaded($ext);
        $checks[] = ['label' => "ext-{$ext}", 'value' => $desc, 'status' => $loaded ? 'pass' : ($required ? 'fail' : 'warn'), 'required' => $required];
    }
    $memOk = parseMemory(ini_get('memory_limit')) >= 64 * 1048576;
    $checks[] = ['label' => 'memory_limit (≥ 64M)', 'value' => ini_get('memory_limit'), 'status' => $memOk ? 'pass' : 'warn', 'required' => false];
    $upOk = parseMemory(ini_get('upload_max_filesize')) >= 4 * 1048576;
    $checks[] = ['label' => 'upload_max_filesize (≥ 4M)', 'value' => ini_get('upload_max_filesize'), 'status' => $upOk ? 'pass' : 'warn', 'required' => false];
    $rewrite = function_exists('apache_get_modules') ? in_array('mod_rewrite', apache_get_modules()) : true;
    $checks[] = ['label' => 'mod_rewrite', 'value' => $rewrite ? 'Enabled' : 'Unknown — verify in cPanel', 'status' => $rewrite ? 'pass' : 'warn', 'required' => false];
    $free = disk_free_space(ROOT_PATH);
    $diskOk = $free === false || $free >= 50 * 1048576;
    $checks[] = ['label' => 'Free Disk Space (≥ 50 MB)', 'value' => $free !== false ? fmtBytes((int)$free) : 'Unknown', 'status' => $diskOk ? 'pass' : 'warn', 'required' => false];
    $writable = is_writable(ROOT_PATH);
    $checks[] = ['label' => 'Writable: / (project root)', 'value' => $writable ? 'Writable' : 'Not writable — .env cannot be written', 'status' => $writable ? 'pass' : 'fail', 'required' => true];
    return $checks;
}

function requirementsPass(array $checks): bool {
    foreach ($checks as $c) { if ($c['required'] && $c['status'] === 'fail') return false; }
    return true;
}

// ── .env Writer ───────────────────────────────────────────────────────────────

function writeEnv(array $cfg): bool {
    // If .env already exists, merge/update Careers-specific keys only
    $existing = [];
    if (file_exists(ENV_FILE)) {
        foreach (file(ENV_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
            [$k, $v] = explode('=', $line, 2);
            $existing[trim($k)] = trim($v);
        }
    }

    // Merge new values
    $encKey = $cfg['encryption_key'] ?: ($existing['ENCRYPTION_KEY'] ?? bin2hex(random_bytes(32)));
    $merge = [
        'APP_NAME'             => '"' . addslashes($cfg['app_name']) . '"',
        'APP_ENV'              => 'production',
        'APP_DEBUG'            => 'false',
        'APP_URL'              => rtrim($cfg['app_url'], '/'),
        'APP_TIMEZONE'         => $cfg['timezone'] ?? 'UTC',
        'DB_HOST'              => $cfg['hr_host'],
        'DB_PORT'              => (string)$cfg['hr_port'],
        'DB_DATABASE'          => $cfg['hr_db'],
        'DB_USERNAME'          => $cfg['hr_user'],
        'DB_PASSWORD'          => $cfg['hr_pass'],
        'CAREERS_DB_HOST'      => $cfg['cdb_host'],
        'CAREERS_DB_PORT'      => (string)$cfg['cdb_port'],
        'CAREERS_DB_DATABASE'  => $cfg['cdb_name'],
        'CAREERS_DB_USERNAME'  => $cfg['cdb_user'],
        'CAREERS_DB_PASSWORD'  => $cfg['cdb_pass'],
        'ENCRYPTION_KEY'       => $encKey,
        'MAIL_ENABLED'         => $cfg['mail_enabled'] ? 'true' : 'false',
        'MAIL_TRANSPORT'       => 'smtp',
        'MAIL_HOST'            => $cfg['mail_host'] ?? '',
        'MAIL_PORT'            => (string)($cfg['mail_port'] ?? '587'),
        'MAIL_ENCRYPTION'      => $cfg['mail_enc'] ?? 'tls',
        'MAIL_USERNAME'        => $cfg['mail_user'] ?? '',
        'MAIL_PASSWORD'        => $cfg['mail_pass'] ?? '',
        'MAIL_FROM_ADDRESS'    => $cfg['mail_from'] ?? '',
        'MAIL_FROM_NAME'       => '"' . addslashes($cfg['app_name']) . '"',
        'SESSION_NAME'         => 'hr_system_session',
        'SESSION_IDLE_TIMEOUT' => '7200',
        'LOGIN_LOCKOUT_ATTEMPTS' => '5',
        'LOGIN_LOCKOUT_MINUTES'  => '15',
        'RECAPTCHA_ENABLED'    => 'false',
        'RECAPTCHA_SITE_KEY'   => '',
        'RECAPTCHA_SECRET_KEY' => '',
    ];

    $lines = [
        '# ── Application ─────────────────────────────────',
        'APP_NAME=' . $merge['APP_NAME'],
        'APP_ENV=production',
        'APP_DEBUG=false',
        'APP_URL=' . $merge['APP_URL'],
        'APP_TIMEZONE=' . $merge['APP_TIMEZONE'],
        '',
        '# ── HR System Database ───────────────────────────',
        'DB_HOST=' . $merge['DB_HOST'],
        'DB_PORT=' . $merge['DB_PORT'],
        'DB_DATABASE=' . $merge['DB_DATABASE'],
        'DB_USERNAME=' . $merge['DB_USERNAME'],
        'DB_PASSWORD=' . $merge['DB_PASSWORD'],
        '',
        '# ── Careers Database ─────────────────────────────',
        'CAREERS_DB_HOST=' . $merge['CAREERS_DB_HOST'],
        'CAREERS_DB_PORT=' . $merge['CAREERS_DB_PORT'],
        'CAREERS_DB_DATABASE=' . $merge['CAREERS_DB_DATABASE'],
        'CAREERS_DB_USERNAME=' . $merge['CAREERS_DB_USERNAME'],
        'CAREERS_DB_PASSWORD=' . $merge['CAREERS_DB_PASSWORD'],
        '',
        '# ── Encryption ───────────────────────────────────',
        'ENCRYPTION_KEY=' . $encKey,
        '',
        '# ── Mail ─────────────────────────────────────────',
        'MAIL_ENABLED=' . $merge['MAIL_ENABLED'],
        'MAIL_TRANSPORT=smtp',
        'MAIL_HOST=' . $merge['MAIL_HOST'],
        'MAIL_PORT=' . $merge['MAIL_PORT'],
        'MAIL_ENCRYPTION=' . $merge['MAIL_ENCRYPTION'],
        'MAIL_USERNAME=' . $merge['MAIL_USERNAME'],
        'MAIL_PASSWORD=' . $merge['MAIL_PASSWORD'],
        'MAIL_FROM_ADDRESS=' . $merge['MAIL_FROM_ADDRESS'],
        'MAIL_FROM_NAME=' . $merge['MAIL_FROM_NAME'],
        '',
        '# ── Session ──────────────────────────────────────',
        'SESSION_NAME=hr_system_session',
        'SESSION_IDLE_TIMEOUT=7200',
        '',
        '# ── Security ─────────────────────────────────────',
        'LOGIN_LOCKOUT_ATTEMPTS=5',
        'LOGIN_LOCKOUT_MINUTES=15',
        'PASSWORD_RESET_EXPIRY_MINUTES=60',
        '',
        '# ── reCAPTCHA (optional) ──────────────────────────',
        'RECAPTCHA_ENABLED=false',
        'RECAPTCHA_SITE_KEY=',
        'RECAPTCHA_SECRET_KEY=',
    ];
    return (bool) file_put_contents(ENV_FILE, implode("\n", $lines) . "\n");
}

// ── Install Runner ────────────────────────────────────────────────────────────

function runInstall(array $cfg): array {
    $log = [];

    // 1. Ensure storage/ directory exists
    $storagePath = ROOT_PATH . '/storage';
    if (!is_dir($storagePath)) @mkdir($storagePath, 0755, true);
    $log[] = ['msg' => 'Storage directory verified', 'type' => 'ok'];

    // 2. Write .env
    if (!writeEnv($cfg)) {
        $log[] = ['msg' => 'Failed to write .env — check directory permissions', 'type' => 'error'];
        return ['log' => $log, 'fatal' => true];
    }
    $log[] = ['msg' => '.env configuration file written', 'type' => 'ok'];

    // 3. Connect to Careers database and run schema
    try {
        $pdo = createPdo($cfg['cdb_host'], (int)$cfg['cdb_port'], $cfg['cdb_user'], $cfg['cdb_pass']);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$cfg['cdb_name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$cfg['cdb_name']}`");
        $log[] = ['msg' => "Careers database `{$cfg['cdb_name']}` created/verified", 'type' => 'ok'];
    } catch (PDOException $e) {
        $log[] = ['msg' => 'Careers database connection failed: ' . $e->getMessage(), 'type' => 'error'];
        return ['log' => $log, 'fatal' => true];
    }

    // 4. Run careers_migration.sql
    $result = runSqlFile($pdo, ROOT_PATH . '/database/careers_migration.sql');
    $warn = !empty($result['errors']) ? ' (' . count($result['errors']) . ' warnings)' : '';
    $log[] = ['msg' => "Careers schema: {$result['ok']} statements executed{$warn}", 'type' => !empty($result['errors']) ? 'warn' : 'ok'];
    foreach (array_slice($result['errors'], 0, 5) as $err) {
        $log[] = ['msg' => '  ↳ ' . $err, 'type' => 'warn'];
    }

    // 5. Verify HR database connection (read-only check — jobs table)
    try {
        $hrPdo = createPdo($cfg['hr_host'], (int)$cfg['hr_port'], $cfg['hr_user'], $cfg['hr_pass'], $cfg['hr_db']);
        $count = $hrPdo->query("SELECT COUNT(*) FROM jobs")->fetchColumn();
        $log[] = ['msg' => "HR database connected — {$count} job(s) found in jobs table", 'type' => 'ok'];
    } catch (PDOException $e) {
        $log[] = ['msg' => 'HR database warning — careers portal needs this for job listings: ' . $e->getMessage(), 'type' => 'warn'];
    }

    // 6. Write lock file
    @mkdir(dirname(LOCK_FILE), 0755, true);
    file_put_contents(LOCK_FILE, json_encode([
        'installed_at' => date('Y-m-d H:i:s'),
        'version'      => INSTALLER_VERSION,
        'app_url'      => $cfg['app_url'],
        'careers_db'   => $cfg['cdb_name'],
    ]));
    $log[] = ['msg' => 'Install lock file written', 'type' => 'ok'];

    return ['log' => $log, 'fatal' => false];
}

// ── Step Handlers ─────────────────────────────────────────────────────────────

$step = max(1, min(TOTAL_STEPS + 1, (int)($_GET['step'] ?? 1)));
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post = $_POST;

    if ($step === 2) redirect(3);

    if ($step === 3) {
        // HR DB connection (existing)
        $host = trim($post['hr_host'] ?? 'localhost');
        $port = (int)($post['hr_port'] ?? 3306);
        $user = trim($post['hr_user'] ?? '');
        $pass = $post['hr_pass'] ?? '';
        $db   = trim($post['hr_db'] ?? '');

        if (!$user) $errors[] = 'Database username is required.';
        if (!$db)   $errors[] = 'HR database name is required.';

        if (empty($errors)) {
            $test = testDbConnection($host, $port, $user, $pass, $db);
            if (!$test['ok']) $errors[] = 'Cannot connect to HR database: ' . $test['error'];
            else {
                // Verify it's an HR system db (check for jobs table)
                try {
                    $pdo = createPdo($host, $port, $user, $pass, $db);
                    $tables = $pdo->query("SHOW TABLES LIKE 'jobs'")->fetchColumn();
                    if (!$tables) $errors[] = 'Connected but "jobs" table not found — ensure this is the HR System database.';
                } catch (PDOException) {}
            }
        }

        if (empty($errors)) {
            sessionSet('hr_db', compact('host','port','user','pass','db'));
            sessionSet('hr_db_version', $test['version'] ?? '');
            redirect(4);
        }
    }

    if ($step === 4) {
        // Careers DB
        $hr      = sessionGet('hr_db', []);
        $host    = trim($post['cdb_host'] ?? $hr['host'] ?? 'localhost');
        $port    = (int)($post['cdb_port'] ?? $hr['port'] ?? 3306);
        $user    = trim($post['cdb_user'] ?? $hr['user'] ?? '');
        $pass    = $post['cdb_pass'] ?? ($hr['pass'] ?? '');
        $dbName  = trim($post['cdb_name'] ?? '');

        if (!$user)   $errors[] = 'Database username is required.';
        if (!$dbName) $errors[] = 'Careers database name is required.';
        if ($dbName && $dbName === ($hr['db'] ?? '')) $errors[] = 'Careers database must be different from the HR database.';

        if (empty($errors)) {
            $test = testDbConnection($host, $port, $user, $pass);
            if (!$test['ok']) $errors[] = 'Cannot connect to database server: ' . $test['error'];
        }

        if (empty($errors)) {
            sessionSet('cdb', compact('host','port','user','pass','dbName'));
            redirect(5);
        }
    }

    if ($step === 5) {
        // App + Email settings
        $appUrl  = rtrim(trim($post['app_url'] ?? ''), '/');
        $appName = trim($post['app_name'] ?? 'HR Careers Portal');
        $tz      = trim($post['timezone'] ?? 'UTC');
        $encKey  = trim($post['enc_key'] ?? '');

        $mailEnabled = isset($post['mail_enabled']) && $post['mail_enabled'] === '1';
        $mailHost  = trim($post['mail_host'] ?? '');
        $mailPort  = (int)($post['mail_port'] ?? 587);
        $mailEnc   = trim($post['mail_enc'] ?? 'tls');
        $mailUser  = trim($post['mail_user'] ?? '');
        $mailPass  = $post['mail_pass'] ?? '';
        $mailFrom  = trim($post['mail_from'] ?? '');

        if (!$appUrl) $errors[] = 'Application URL is required.';
        if (!filter_var($appUrl, FILTER_VALIDATE_URL)) $errors[] = 'Application URL must be a valid URL.';
        if ($mailEnabled && !$mailHost)  $errors[] = 'SMTP host is required when mail is enabled.';
        if ($mailEnabled && !$mailFrom)  $errors[] = 'From address is required when mail is enabled.';

        if (empty($errors)) {
            sessionSet('app', compact('appUrl','appName','tz','encKey'));
            sessionSet('mail', compact('mailEnabled','mailHost','mailPort','mailEnc','mailUser','mailPass','mailFrom'));
            redirect(6);
        }
    }

    if ($step === 6 && isset($post['do_install'])) {
        $hrDb  = sessionGet('hr_db', []);
        $cdb   = sessionGet('cdb', []);
        $app   = sessionGet('app', []);
        $mail  = sessionGet('mail', []);

        $cfg = [
            'hr_host'         => $hrDb['host'] ?? 'localhost',
            'hr_port'         => $hrDb['port'] ?? 3306,
            'hr_user'         => $hrDb['user'] ?? '',
            'hr_pass'         => $hrDb['pass'] ?? '',
            'hr_db'           => $hrDb['db'] ?? '',
            'cdb_host'        => $cdb['host'] ?? 'localhost',
            'cdb_port'        => $cdb['port'] ?? 3306,
            'cdb_user'        => $cdb['user'] ?? '',
            'cdb_pass'        => $cdb['pass'] ?? '',
            'cdb_name'        => $cdb['dbName'] ?? '',
            'app_url'         => $app['appUrl'] ?? '',
            'app_name'        => $app['appName'] ?? 'HR Careers Portal',
            'timezone'        => $app['tz'] ?? 'UTC',
            'encryption_key'  => $app['encKey'] ?? '',
            'mail_enabled'    => $mail['mailEnabled'] ?? false,
            'mail_host'       => $mail['mailHost'] ?? '',
            'mail_port'       => $mail['mailPort'] ?? 587,
            'mail_enc'        => $mail['mailEnc'] ?? 'tls',
            'mail_user'       => $mail['mailUser'] ?? '',
            'mail_pass'       => $mail['mailPass'] ?? '',
            'mail_from'       => $mail['mailFrom'] ?? '',
        ];

        $result = runInstall($cfg);
        sessionSet('install_result', $result);
        sessionSet('install_cfg', $cfg);
        redirect(7);
    }
}

// ── Render ────────────────────────────────────────────────────────────────────

$stepLabels = [
    1 => 'Welcome',
    2 => 'Server Check',
    3 => 'HR Database',
    4 => 'Careers DB',
    5 => 'Settings',
    6 => 'Install',
    7 => 'Complete',
];

function renderLocked(): void {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Already Installed</title>
    <style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;background:#f0f4f8}
    .box{background:#fff;padding:2rem 3rem;border-radius:8px;box-shadow:0 2px 20px rgba(0,0,0,.1);max-width:500px;text-align:center}
    h2{color:#0e9f6e}code{background:#f3f4f6;padding:2px 6px;border-radius:4px;font-size:.9em}</style>
    </head><body><div class="box"><h2>&#9989; Careers Portal Already Installed</h2>
    <p>The Careers Portal installer has already been run.</p>
    <p>To re-run: delete <code>storage/installer_careers.lock</code> or add <code>?force=1</code>.</p>
    <p><strong>Delete this installer file from your server!</strong></p></div></body></html>';
}

ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Careers Portal Installer v<?= INSTALLER_VERSION ?></title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --teal: #0e9f6e; --teal-d: #0b7a55; --teal-l: #d1fae5;
  --blue: #1a56db; --blue-l: #e8f0fe;
  --green: #0e9f6e; --red: #f05252; --yellow: #e3a008;
  --gray: #6b7280; --gray-l: #f9fafb; --border: #e5e7eb;
  --text: #111827; --text-s: #374151;
}
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f0fdf4; color: var(--text); font-size: 15px; line-height: 1.5; }
a { color: var(--teal); }
.installer-wrap { display: flex; min-height: 100vh; }
.sidebar { width: 260px; background: #14532d; color: #bbf7d0; flex-shrink: 0; padding: 2rem 0; }
.sidebar-brand { padding: 0 1.5rem 1.5rem; border-bottom: 1px solid rgba(255,255,255,.1); }
.sidebar-brand h1 { font-size: 1.1rem; font-weight: 700; color: #fff; }
.sidebar-brand p { font-size: .78rem; color: #6ee7b7; margin-top: .2rem; }
.sidebar-steps { padding: 1.5rem 0; }
.step-item { display: flex; align-items: center; gap: .75rem; padding: .55rem 1.5rem; font-size: .88rem; }
.step-item.active { background: rgba(14,159,110,.3); color: #fff; }
.step-item.done { color: #6ee7b7; }
.step-item.pending { color: #4ade80; opacity: .5; }
.step-num { width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: .75rem; font-weight: 700; flex-shrink: 0; }
.step-item.active  .step-num { background: var(--teal); color: #fff; }
.step-item.done    .step-num { background: #16a34a; color: #fff; }
.step-item.pending .step-num { background: #166534; color: #4ade80; border: 1px solid #166534; }
.sidebar-footer { padding: 1.5rem; border-top: 1px solid rgba(255,255,255,.1); font-size: .78rem; color: #4ade80; }
.main { flex: 1; padding: 2.5rem; max-width: 820px; }
.step-header { margin-bottom: 1.75rem; }
.step-header h2 { font-size: 1.5rem; font-weight: 700; }
.step-header p { color: var(--gray); margin-top: .35rem; }
.badge-step { display: inline-block; font-size: .72rem; background: var(--teal-l); color: var(--teal); padding: 2px 8px; border-radius: 12px; font-weight: 600; margin-bottom: .6rem; }
.card { background: #fff; border: 1px solid var(--border); border-radius: 10px; padding: 1.5rem; margin-bottom: 1.25rem; }
.card-title { font-size: .95rem; font-weight: 600; margin-bottom: 1rem; display: flex; align-items: center; gap: .5rem; }
.form-group { margin-bottom: 1.1rem; }
.form-group label { display: block; font-size: .85rem; font-weight: 600; margin-bottom: .4rem; color: var(--text-s); }
.form-group input[type=text], .form-group input[type=email], .form-group input[type=password],
.form-group input[type=number], .form-group select {
  width: 100%; padding: .55rem .75rem; border: 1px solid var(--border); border-radius: 6px;
  font-size: .9rem; color: var(--text); background: #fff; transition: border .15s;
}
.form-group input:focus, .form-group select:focus { outline: none; border-color: var(--teal); box-shadow: 0 0 0 3px rgba(14,159,110,.12); }
.form-group .hint { font-size: .8rem; color: var(--gray); margin-top: .3rem; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.form-row-3 { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 1rem; }
.toggle-wrap { display: flex; align-items: center; gap: .75rem; }
.toggle-wrap input[type=checkbox] { width: 18px; height: 18px; }
.req-table { width: 100%; border-collapse: collapse; }
.req-table th { text-align: left; font-size: .8rem; font-weight: 600; color: var(--gray); padding: .5rem .75rem; border-bottom: 2px solid var(--border); }
.req-table td { padding: .55rem .75rem; border-bottom: 1px solid var(--border); font-size: .87rem; vertical-align: middle; }
.req-table tr:last-child td { border-bottom: none; }
.badge { display: inline-flex; align-items: center; gap: .3rem; padding: 2px 8px; border-radius: 12px; font-size: .78rem; font-weight: 600; }
.badge-pass { background: #d1fae5; color: #065f46; }
.badge-fail { background: #fee2e2; color: #991b1b; }
.badge-warn { background: #fef3c7; color: #92400e; }
.alert { padding: .75rem 1rem; border-radius: 6px; margin-bottom: 1.1rem; font-size: .88rem; display: flex; gap: .6rem; align-items: flex-start; }
.alert-error  { background: #fee2e2; border: 1px solid #fca5a5; color: #7f1d1d; }
.alert-warn   { background: #fef3c7; border: 1px solid #fcd34d; color: #78350f; }
.alert-success{ background: #d1fae5; border: 1px solid #6ee7b7; color: #065f46; }
.alert-info   { background: #dbeafe; border: 1px solid #93c5fd; color: #1e3a8a; }
.alert ul { margin: .4rem 0 0 1.2rem; }
.btn-row { display: flex; gap: .75rem; align-items: center; margin-top: 1.75rem; flex-wrap: wrap; }
.btn { display: inline-flex; align-items: center; gap: .4rem; padding: .6rem 1.5rem; border-radius: 7px; font-size: .9rem; font-weight: 600; cursor: pointer; border: none; text-decoration: none; transition: background .15s; }
.btn-primary { background: var(--teal); color: #fff; }
.btn-primary:hover { background: var(--teal-d); }
.btn-secondary { background: #fff; color: var(--text-s); border: 1px solid var(--border); }
.btn-secondary:hover { background: var(--gray-l); }
.btn-success { background: #16a34a; color: #fff; }
.btn-danger { background: var(--red); color: #fff; }
.spec-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: .75rem; }
.spec-box { background: var(--gray-l); border: 1px solid var(--border); border-radius: 8px; padding: .9rem 1rem; }
.spec-box .spec-label { font-size: .78rem; font-weight: 600; color: var(--gray); text-transform: uppercase; letter-spacing: .04em; }
.spec-box .spec-val { font-size: .95rem; font-weight: 600; margin-top: .2rem; }
.install-log { background: #1a2e1a; color: #bbf7d0; border-radius: 8px; padding: 1.25rem; font-family: 'Courier New', monospace; font-size: .83rem; max-height: 380px; overflow-y: auto; }
.install-log .log-ok    { color: #6ee7b7; }
.install-log .log-warn  { color: #fcd34d; }
.install-log .log-error { color: #fca5a5; }
.install-log .log-line  { padding: .1rem 0; }
.success-hero { text-align: center; padding: 2rem; }
.success-hero .icon { font-size: 4rem; }
.success-hero h2 { font-size: 1.75rem; font-weight: 700; color: var(--teal); margin: .75rem 0 .5rem; }
.credentials-box { background: #fff; border: 2px dashed var(--teal); border-radius: 10px; padding: 1.25rem 1.5rem; margin: 1.25rem 0; }
.arch-diagram { display: grid; grid-template-columns: 1fr auto 1fr; gap: 1rem; align-items: center; text-align: center; margin: .75rem 0; }
.arch-box { background: var(--gray-l); border: 1px solid var(--border); border-radius: 8px; padding: .75rem; }
.arch-box strong { display: block; font-size: .88rem; }
.arch-box span { font-size: .78rem; color: var(--gray); }
.arch-arrow { font-size: 1.4rem; color: var(--teal); }
@media (max-width: 768px) {
  .installer-wrap { flex-direction: column; }
  .sidebar { width: 100%; }
  .main { padding: 1.25rem; }
  .form-row, .form-row-3, .spec-grid { grid-template-columns: 1fr; }
  .arch-diagram { grid-template-columns: 1fr; }
}
</style>
</head>
<body>
<div class="installer-wrap">

<!-- Sidebar -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <h1>&#127942; Careers Portal</h1>
    <p>Installer v<?= INSTALLER_VERSION ?></p>
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
    <div><?= php_uname('s') ?></div>
  </div>
</aside>

<!-- Main -->
<main class="main">

<?php if (!empty($errors)): ?>
<div class="alert alert-error">
  <span>&#9888;</span>
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
  <h2>Careers Portal Installer</h2>
  <p>Set up the public-facing Careers Portal that lets job seekers browse and apply for positions.</p>
</div>

<div class="card">
  <div class="card-title"><span>&#127963;</span> Architecture Overview</div>
  <div class="arch-diagram">
    <div class="arch-box">
      <strong>HR System</strong>
      <span>hr_system database</span><br>
      <span style="font-size:.75rem;color:var(--teal)">Jobs posted here</span>
    </div>
    <div class="arch-arrow">&#8596;</div>
    <div class="arch-box">
      <strong>Careers Portal</strong>
      <span>hr_careers database</span><br>
      <span style="font-size:.75rem;color:var(--teal)">Seekers &amp; applications</span>
    </div>
  </div>
  <p style="font-size:.88rem;color:var(--gray);margin-top:.75rem">The Careers Portal reads job listings from the HR System's <code>jobs</code> table. Job seekers register, build profiles, and apply — all stored in a separate <code>hr_careers</code> database.</p>
</div>

<div class="card">
  <div class="card-title"><span>&#127942;</span> Careers Portal Features</div>
  <ul style="list-style:none;display:grid;grid-template-columns:1fr 1fr;gap:.5rem;font-size:.88rem">
    <li>&#9989; Public job board</li>
    <li>&#9989; Job seeker registration</li>
    <li>&#9989; OTP email verification</li>
    <li>&#9989; Applicant profiles &amp; CV</li>
    <li>&#9989; Online job applications</li>
    <li>&#9989; Application tracking</li>
    <li>&#9989; Job bank / saved jobs</li>
    <li>&#9989; Separate authentication</li>
  </ul>
</div>

<div class="card">
  <div class="card-title"><span>&#9989;</span> Before you begin</div>
  <ul style="list-style:none;display:grid;gap:.5rem;font-size:.9rem">
    <li>&#9744; The HR System is already installed and running</li>
    <li>&#9744; You have credentials for the existing <code>hr_system</code> database</li>
    <li>&#9744; A second MySQL database for careers has been created in cPanel</li>
    <li>&#9744; The database user has full privileges on both databases</li>
    <li>&#9744; This installer file is in <code>public/install_careers.php</code></li>
  </ul>
</div>

<div class="btn-row">
  <a href="?step=2" class="btn btn-primary">Start &rarr;</a>
</div>

<?php
// ── STEP 2: Server Requirements ───────────────────────────────────────────────
elseif ($step === 2):
  $checks = checkRequirements();
  $allPass = requirementsPass($checks);
  $warnCount = count(array_filter($checks, fn($c) => $c['status'] === 'warn'));
  $failCount = count(array_filter($checks, fn($c) => $c['status'] === 'fail'));
?>
<div class="step-header">
  <span class="badge-step">Step 2 of <?= TOTAL_STEPS ?></span>
  <h2>Server Requirements</h2>
  <p>Checking server compatibility for the Careers Portal.</p>
</div>

<div class="card">
  <div class="card-title"><span>&#128187;</span> Server Environment</div>
  <div class="spec-grid">
    <div class="spec-box"><div class="spec-label">PHP Version</div><div class="spec-val"><?= PHP_VERSION ?></div></div>
    <div class="spec-box"><div class="spec-label">Server</div><div class="spec-val"><?= e(explode(' ', $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown')[0]) ?></div></div>
    <div class="spec-box"><div class="spec-label">OS</div><div class="spec-val"><?= PHP_OS_FAMILY ?></div></div>
    <div class="spec-box"><div class="spec-label">Memory Limit</div><div class="spec-val"><?= ini_get('memory_limit') ?></div></div>
    <div class="spec-box"><div class="spec-label">Max Upload</div><div class="spec-val"><?= ini_get('upload_max_filesize') ?></div></div>
    <div class="spec-box"><div class="spec-label">Disk Free</div><div class="spec-val"><?= ($f = disk_free_space(ROOT_PATH)) !== false ? fmtBytes((int)$f) : 'N/A' ?></div></div>
  </div>
</div>

<?php if (!$allPass): ?>
<div class="alert alert-error"><span>&#10060;</span><div><strong><?= $failCount ?> critical requirement(s) failed.</strong> Please resolve before proceeding.</div></div>
<?php elseif ($warnCount > 0): ?>
<div class="alert alert-warn"><span>&#9888;</span><div><strong><?= $warnCount ?> warning(s).</strong> Non-blocking — you can proceed.</div></div>
<?php else: ?>
<div class="alert alert-success"><span>&#10003;</span><div><strong>All requirements passed!</strong></div></div>
<?php endif; ?>

<div class="card" style="padding:0;overflow:hidden">
  <table class="req-table">
    <thead><tr><th style="width:220px">Requirement</th><th>Details</th><th style="width:100px">Status</th><th style="width:80px">Required</th></tr></thead>
    <tbody>
    <?php foreach ($checks as $c): ?>
    <tr>
      <td style="font-weight:500"><?= e($c['label']) ?></td>
      <td style="font-size:.83rem;color:var(--gray)"><?= e($c['value']) ?></td>
      <td><?php if ($c['status'] === 'pass'): ?>
        <span class="badge badge-pass">&#10003; Pass</span>
      <?php elseif ($c['status'] === 'fail'): ?>
        <span class="badge badge-fail">&#10007; Fail</span>
      <?php else: ?>
        <span class="badge badge-warn">&#9888; Warn</span>
      <?php endif; ?></td>
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
    <button type="submit" class="btn btn-danger">Force Continue</button>
    <?php endif; ?>
  </div>
</form>

<?php
// ── STEP 3: HR Database ───────────────────────────────────────────────────────
elseif ($step === 3):
  $saved = sessionGet('hr_db', []);
?>
<div class="step-header">
  <span class="badge-step">Step 3 of <?= TOTAL_STEPS ?></span>
  <h2>HR System Database</h2>
  <p>Connect to the existing HR System database. This is needed so the Careers Portal can read job listings.</p>
</div>

<div class="alert alert-info">
  <span>&#8505;</span>
  <div>This is a <strong>read connection</strong> to the existing HR System. The installer will verify the connection and check that the <code>jobs</code> table exists.</div>
</div>

<form method="post" action="?step=3">
  <div class="card">
    <div class="card-title"><span>&#128196;</span> HR System Database Credentials</div>
    <div class="form-row-3">
      <div class="form-group">
        <label>Host</label>
        <input type="text" name="hr_host" value="<?= e($saved['host'] ?? 'localhost') ?>" placeholder="localhost">
      </div>
      <div class="form-group">
        <label>Port</label>
        <input type="number" name="hr_port" value="<?= e($saved['port'] ?? '3306') ?>">
      </div>
      <div class="form-group"></div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>HR Database Name <span style="color:var(--red)">*</span></label>
        <input type="text" name="hr_db" value="<?= e($saved['db'] ?? '') ?>" placeholder="cpanelusername_hr_system" required>
        <div class="hint">The existing HR System database</div>
      </div>
      <div class="form-group">
        <label>Database User <span style="color:var(--red)">*</span></label>
        <input type="text" name="hr_user" value="<?= e($saved['user'] ?? '') ?>" placeholder="cpanelusername_hruser" required>
      </div>
    </div>
    <div class="form-group">
      <label>Database Password</label>
      <input type="password" name="hr_pass" value="<?= e($saved['pass'] ?? '') ?>">
    </div>
  </div>
  <div class="btn-row">
    <a href="?step=2" class="btn btn-secondary">&larr; Back</a>
    <button type="submit" class="btn btn-primary">Verify Connection &rarr;</button>
  </div>
</form>

<?php
// ── STEP 4: Careers DB ────────────────────────────────────────────────────────
elseif ($step === 4):
  $saved = sessionGet('cdb', []);
  $hrDb  = sessionGet('hr_db', []);
  $ver   = sessionGet('hr_db_version', '');
?>
<div class="step-header">
  <span class="badge-step">Step 4 of <?= TOTAL_STEPS ?></span>
  <h2>Careers Database</h2>
  <p>Configure the separate database for job seeker accounts and applications.</p>
</div>

<?php if ($ver): ?>
<div class="alert alert-success"><span>&#10003;</span> HR database verified &mdash; MySQL <?= e($ver) ?></div>
<?php endif; ?>

<form method="post" action="?step=4">
  <div class="card">
    <div class="card-title"><span>&#127942;</span> Careers Database Credentials</div>
    <div class="form-group">
      <div class="toggle-wrap">
        <input type="checkbox" id="same_host" name="same_host" value="1" checked onchange="toggleSame(this)">
        <label for="same_host">Same host &amp; user as HR database (<?= e($hrDb['host'] ?? '') ?>)</label>
      </div>
    </div>
    <div id="diff_creds" style="display:none">
      <div class="form-row-3">
        <div class="form-group">
          <label>Host</label>
          <input type="text" name="cdb_host" value="<?= e($saved['host'] ?? $hrDb['host'] ?? 'localhost') ?>">
        </div>
        <div class="form-group">
          <label>Port</label>
          <input type="number" name="cdb_port" value="<?= e($saved['port'] ?? $hrDb['port'] ?? '3306') ?>">
        </div>
        <div class="form-group"></div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>DB User</label>
          <input type="text" name="cdb_user" value="<?= e($saved['user'] ?? $hrDb['user'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>DB Password</label>
          <input type="password" name="cdb_pass" value="<?= e($saved['pass'] ?? $hrDb['pass'] ?? '') ?>">
        </div>
      </div>
    </div>
    <div class="form-group">
      <label>Careers Database Name <span style="color:var(--red)">*</span></label>
      <input type="text" name="cdb_name" value="<?= e($saved['dbName'] ?? '') ?>" placeholder="cpanelusername_hr_careers" required>
      <div class="hint">Must be different from the HR database. Will be created if it doesn't exist.</div>
    </div>
  </div>
  <div class="btn-row">
    <a href="?step=3" class="btn btn-secondary">&larr; Back</a>
    <button type="submit" class="btn btn-primary">Continue &rarr;</button>
  </div>
</form>
<script>
function toggleSame(cb) {
  document.getElementById('diff_creds').style.display = cb.checked ? 'none' : 'block';
  if (cb.checked) {
    var host = <?= json_encode($hrDb['host'] ?? 'localhost') ?>;
    var port = <?= json_encode((string)($hrDb['port'] ?? '3306')) ?>;
    var user = <?= json_encode($hrDb['user'] ?? '') ?>;
    var pass = <?= json_encode($hrDb['pass'] ?? '') ?>;
    document.querySelectorAll('[name=cdb_host]')[0].value = host;
    document.querySelectorAll('[name=cdb_port]')[0].value = port;
    document.querySelectorAll('[name=cdb_user]')[0].value = user;
    document.querySelectorAll('[name=cdb_pass]')[0].value = pass;
  }
}
</script>

<?php
// ── STEP 5: App + Email Settings ──────────────────────────────────────────────
elseif ($step === 5):
  $savedApp  = sessionGet('app', []);
  $savedMail = sessionGet('mail', []);
  $defaultKey = $savedApp['encKey'] ?? '';
  // Try to read existing .env key
  if (!$defaultKey && file_exists(ENV_FILE)) {
      foreach (file(ENV_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
          if (str_starts_with($line, 'ENCRYPTION_KEY=')) {
              $defaultKey = trim(explode('=', $line, 2)[1]);
              break;
          }
      }
  }
  $defaultKey = $defaultKey ?: bin2hex(random_bytes(32));
  $timezones = ['UTC','Asia/Riyadh','Asia/Qatar','Asia/Dubai','Asia/Kuwait','Asia/Bahrain','Asia/Muscat','Africa/Cairo','Europe/London','America/New_York','Asia/Karachi','Asia/Kolkata','Asia/Singapore'];
  $envExists = file_exists(ENV_FILE);
?>
<div class="step-header">
  <span class="badge-step">Step 5 of <?= TOTAL_STEPS ?></span>
  <h2>Application &amp; Email Settings</h2>
  <p>Configure the application URL, timezone, and optional email settings for the Careers Portal.</p>
</div>

<?php if ($envExists): ?>
<div class="alert alert-warn">
  <span>&#9888;</span>
  <div>An existing <code>.env</code> file was found. This installer will overwrite it with the new configuration. Your encryption key has been pre-filled from the existing file.</div>
</div>
<?php endif; ?>

<form method="post" action="?step=5">
  <div class="card">
    <div class="card-title"><span>&#127760;</span> Application</div>
    <div class="form-group">
      <label>Application URL <span style="color:var(--red)">*</span></label>
      <input type="text" name="app_url" value="<?= e($savedApp['appUrl'] ?? 'https://') ?>" placeholder="https://yourdomain.com" required>
      <div class="hint">The URL where this application is hosted. No trailing slash.</div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Application Name</label>
        <input type="text" name="app_name" value="<?= e($savedApp['appName'] ?? 'HR Careers Portal') ?>">
      </div>
      <div class="form-group">
        <label>Timezone</label>
        <select name="timezone">
          <?php foreach ($timezones as $tz): ?>
          <option value="<?= $tz ?>" <?= ($savedApp['tz'] ?? 'UTC') === $tz ? 'selected' : '' ?>><?= $tz ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-group">
      <label>Encryption Key</label>
      <input type="text" name="enc_key" value="<?= e($defaultKey) ?>" style="font-family:monospace;font-size:.82rem">
      <div class="hint">
        <?= $envExists ? 'Pre-filled from existing .env. ' : '' ?>
        Must be 64-character hex. Used to encrypt applicant PII data.
        <?php if ($envExists): ?><strong>Keep this the same if upgrading.</strong><?php endif; ?>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-title"><span>&#128231;</span> Email (for OTP verification &amp; notifications)</div>
    <div class="form-group">
      <div class="toggle-wrap">
        <input type="checkbox" name="mail_enabled" id="mail_enabled" value="1"
          <?= ($savedMail['mailEnabled'] ?? false) ? 'checked' : '' ?>
          onchange="toggleMail(this)">
        <label for="mail_enabled">Enable outbound email</label>
      </div>
      <div class="hint" style="margin-top:.4rem">Required for OTP email verification on job seeker registration</div>
    </div>
    <div id="mail_fields" style="<?= ($savedMail['mailEnabled'] ?? false) ? '' : 'display:none' ?>">
      <div class="form-row-3">
        <div class="form-group">
          <label>SMTP Host</label>
          <input type="text" name="mail_host" value="<?= e($savedMail['mailHost'] ?? '') ?>" placeholder="smtp.example.com">
        </div>
        <div class="form-group">
          <label>Port</label>
          <input type="number" name="mail_port" value="<?= e($savedMail['mailPort'] ?? '587') ?>">
        </div>
        <div class="form-group">
          <label>Encryption</label>
          <select name="mail_enc">
            <option value="tls" <?= ($savedMail['mailEnc'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS (587)</option>
            <option value="ssl" <?= ($savedMail['mailEnc'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL (465)</option>
            <option value="" <?= ($savedMail['mailEnc'] ?? '') === '' ? 'selected' : '' ?>>None</option>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>SMTP Username</label>
          <input type="text" name="mail_user" value="<?= e($savedMail['mailUser'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>SMTP Password</label>
          <input type="password" name="mail_pass" value="<?= e($savedMail['mailPass'] ?? '') ?>">
        </div>
      </div>
      <div class="form-group">
        <label>From Address</label>
        <input type="email" name="mail_from" value="<?= e($savedMail['mailFrom'] ?? '') ?>" placeholder="careers@yourdomain.com">
      </div>
    </div>
  </div>

  <div class="btn-row">
    <a href="?step=4" class="btn btn-secondary">&larr; Back</a>
    <button type="submit" class="btn btn-primary">Continue &rarr;</button>
  </div>
</form>
<script>
function toggleMail(cb) {
  document.getElementById('mail_fields').style.display = cb.checked ? 'block' : 'none';
}
</script>

<?php
// ── STEP 6: Install ───────────────────────────────────────────────────────────
elseif ($step === 6):
  $hrDb = sessionGet('hr_db', []);
  $cdb  = sessionGet('cdb', []);
  $app  = sessionGet('app', []);
  $mail = sessionGet('mail', []);
  if (!$hrDb || !$cdb || !$app) { redirect(1); }
?>
<div class="step-header">
  <span class="badge-step">Step 6 of <?= TOTAL_STEPS ?></span>
  <h2>Ready to Install</h2>
  <p>Review and click <strong>Install Now</strong> to set up the Careers Portal.</p>
</div>

<div class="card">
  <div class="card-title"><span>&#128203;</span> Installation Summary</div>
  <table style="width:100%;border-collapse:collapse;font-size:.88rem">
    <tr><td style="padding:.4rem;font-weight:600;color:var(--gray);width:180px">Application URL</td><td><?= e($app['appUrl'] ?? '') ?></td></tr>
    <tr><td style="padding:.4rem;font-weight:600;color:var(--gray)">App Name</td><td><?= e($app['appName'] ?? '') ?></td></tr>
    <tr><td style="padding:.4rem;font-weight:600;color:var(--gray)">HR Database</td><td><?= e($hrDb['db'] ?? '') ?> on <?= e($hrDb['host'] ?? '') ?></td></tr>
    <tr><td style="padding:.4rem;font-weight:600;color:var(--gray)">Careers Database</td><td><?= e($cdb['dbName'] ?? '') ?> on <?= e($cdb['host'] ?? '') ?></td></tr>
    <tr><td style="padding:.4rem;font-weight:600;color:var(--gray)">Email</td><td><?= ($mail['mailEnabled'] ?? false) ? 'Enabled — ' . e($mail['mailHost'] ?? '') . ':' . e($mail['mailPort'] ?? '') : 'Disabled' ?></td></tr>
    <tr><td style="padding:.4rem;font-weight:600;color:var(--gray)">Timezone</td><td><?= e($app['tz'] ?? '') ?></td></tr>
  </table>
</div>

<div class="card" style="border-left:4px solid var(--teal)">
  <div class="card-title"><span>&#128196;</span> What will happen</div>
  <ul style="list-style:none;display:grid;gap:.4rem;font-size:.88rem">
    <li>&#9654; Create Careers database: <code><?= e($cdb['dbName'] ?? '') ?></code></li>
    <li>&#9654; Import careers portal schema (job seekers, applications, CV profiles)</li>
    <li>&#9654; Write <code>.env</code> configuration file</li>
    <li>&#9654; Verify HR database connection</li>
    <li>&#9654; Create installer lock file</li>
  </ul>
</div>

<form method="post" action="?step=6">
  <div class="btn-row">
    <a href="?step=5" class="btn btn-secondary">&larr; Back</a>
    <button type="submit" name="do_install" value="1" class="btn btn-success">&#9654; Install Careers Portal</button>
  </div>
</form>

<?php
// ── STEP 7: Complete ──────────────────────────────────────────────────────────
elseif ($step === 7):
  $result = sessionGet('install_result', []);
  $cfg    = sessionGet('install_cfg', []);
  $fatal  = $result['fatal'] ?? true;
  $log    = $result['log'] ?? [];
  $appUrl = $cfg['app_url'] ?? '';
?>
<div class="step-header">
  <span class="badge-step">Step 7 of <?= TOTAL_STEPS ?></span>
  <h2><?= $fatal ? 'Installation Failed' : 'Careers Portal Ready!' ?></h2>
</div>

<?php if ($fatal): ?>
<div class="alert alert-error"><span>&#10060;</span><div><strong>Installation failed.</strong> Check the log below.</div></div>
<?php else: ?>
<div class="success-hero">
  <div class="icon">&#127942;</div>
  <h2>Careers Portal Installed!</h2>
  <p style="color:var(--gray)">Job seekers can now browse and apply for positions.</p>
</div>

<div class="credentials-box">
  <div style="font-weight:700;margin-bottom:.75rem">&#128279; Portal URLs</div>
  <table style="width:100%;border-collapse:collapse;font-size:.9rem">
    <tr><td style="padding:.35rem;font-weight:600;color:var(--gray);width:140px">Careers Portal:</td><td><a href="<?= e($appUrl) ?>/careers" target="_blank"><?= e($appUrl) ?>/careers</a></td></tr>
    <tr><td style="padding:.35rem;font-weight:600;color:var(--gray)">Job Listings:</td><td><a href="<?= e($appUrl) ?>/careers/jobs" target="_blank"><?= e($appUrl) ?>/careers/jobs</a></td></tr>
    <tr><td style="padding:.35rem;font-weight:600;color:var(--gray)">Register:</td><td><a href="<?= e($appUrl) ?>/careers/register" target="_blank"><?= e($appUrl) ?>/careers/register</a></td></tr>
  </table>
</div>

<div class="card">
  <div class="card-title"><span>&#128221;</span> Post-Installation Checklist</div>
  <ul style="list-style:none;display:grid;gap:.5rem;font-size:.9rem">
    <li>&#9744; <strong>Delete this installer:</strong> <code>public/install_careers.php</code></li>
    <li>&#9744; Enable email (SMTP) for OTP job seeker verification if not done</li>
    <li>&#9744; Post at least one job from the HR System admin panel to test the portal</li>
    <li>&#9744; Test the full registration → apply flow as a job seeker</li>
    <li>&#9744; Set up cron job for email queue: <code>php <?= ROOT_PATH ?>/scripts/process-email-queue.php</code></li>
  </ul>
</div>

<div class="btn-row">
  <a href="<?= e($appUrl) ?>/careers" class="btn btn-primary" target="_blank">&#9654; Open Careers Portal &rarr;</a>
  <a href="<?= e($appUrl) ?>/login" class="btn btn-secondary" target="_blank">HR Admin Login</a>
</div>
<?php endif; ?>

<div class="card" style="margin-top:1.5rem">
  <div class="card-title"><span>&#128196;</span> Installation Log</div>
  <div class="install-log">
    <?php foreach ($log as $entry): ?>
    <div class="log-line log-<?= e($entry['type']) ?>">
      <?= $entry['type'] === 'ok' ? '[OK]  ' : ($entry['type'] === 'warn' ? '[WARN]' : '[ERR] ') ?>
      <?= e($entry['msg']) ?>
    </div>
    <?php endforeach; ?>
    <?php if (empty($log)): ?><div class="log-line log-warn">No log entries — session may have expired.</div><?php endif; ?>
  </div>
</div>

<?php if (!$fatal): ?>
<div class="alert alert-error" style="margin-top:1rem">
  <span>&#128161;</span>
  <div><strong>Security reminder:</strong> Delete <code>public/install_careers.php</code> from your server now.</div>
</div>
<?php endif; ?>

<?php endif; ?>

</main>
</div>
</body>
</html>
<?php
echo ob_get_clean();
