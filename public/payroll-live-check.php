<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/Support/helpers.php';

(static function (): void {
    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
    $dir = implode('/', array_map('rawurlencode', explode('/', $dir)));
    $url = $scheme . '://' . $host . $dir;
    $_ENV['APP_URL'] = $url;
    $_SERVER['APP_URL'] = $url;
    putenv('APP_URL=' . $url);
})();

require BASE_PATH . '/vendor/autoload.php';

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

/**
 * @return array{label:string,status:string,detail:string}
 */
function result_row(string $label, bool $ok, string $detail): array
{
    return [
        'label' => $label,
        'status' => $ok ? 'OK' : 'FAIL',
        'detail' => $detail,
    ];
}

function esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$results = [];

$criticalFiles = [
    'public/index.php',
    'routes/payroll.php',
    'app/Middleware/PayrollEnabledMiddleware.php',
    'app/Modules/Payroll/PayrollController.php',
    'app/Modules/Payroll/PayrollRepository.php',
    'app/Views/payroll/salary-structures.php',
    'app/Views/payroll/runs.php',
    'app/Views/settings/system.php',
];

foreach ($criticalFiles as $file) {
    $exists = is_file(BASE_PATH . '/' . $file);
    $results[] = result_row(
        'File: ' . $file,
        $exists,
        $exists ? 'Present' : 'Missing'
    );
}

$publicIndexPath = BASE_PATH . '/public/index.php';
$publicIndex = is_file($publicIndexPath) ? (string) file_get_contents($publicIndexPath) : '';
$results[] = result_row(
    'Bootstrap includes payroll routes',
    str_contains($publicIndex, "require BASE_PATH . '/routes/payroll.php';"),
    str_contains($publicIndex, "require BASE_PATH . '/routes/payroll.php';")
        ? 'public/index.php loads routes/payroll.php'
        : 'public/index.php does not load routes/payroll.php'
);

$routeFilePath = BASE_PATH . '/routes/payroll.php';
$routeFile = is_file($routeFilePath) ? (string) file_get_contents($routeFilePath) : '';
$expectedRouteChecks = [
    '/payroll/salary-structures',
    '/payroll/runs',
    '/payroll/runs/create',
];

foreach ($expectedRouteChecks as $path) {
    $results[] = result_row(
        'Route definition: ' . $path,
        str_contains($routeFile, "'" . $path . "'") || str_contains($routeFile, '"' . $path . '"'),
        str_contains($routeFile, "'" . $path . "'") || str_contains($routeFile, '"' . $path . '"')
            ? 'Found in routes/payroll.php'
            : 'Missing from routes/payroll.php'
    );
}

$classChecks = [
    'App\\Middleware\\PayrollEnabledMiddleware',
    'App\\Modules\\Payroll\\PayrollController',
    'App\\Modules\\Payroll\\PayrollRepository',
    'App\\Support\\Branding',
];

foreach ($classChecks as $className) {
    $results[] = result_row(
        'Class load: ' . $className,
        class_exists($className),
        class_exists($className) ? 'Autoload OK' : 'Autoload failed'
    );
}

$databaseError = null;
$db = null;

try {
    $db = $app->database();
    $results[] = result_row('Database connection', true, 'Connection OK');
} catch (Throwable $throwable) {
    $databaseError = $throwable->getMessage();
    $results[] = result_row('Database connection', false, $databaseError);
}

if ($db !== null) {
    $tableChecks = ['settings', 'companies', 'salary_structures', 'payroll_runs', 'payroll_run_items'];

    foreach ($tableChecks as $table) {
        try {
            $exists = (int) $db->fetchValue(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table_name',
                ['table_name' => $table]
            ) > 0;
            $results[] = result_row(
                'Table: ' . $table,
                $exists,
                $exists ? 'Present' : 'Missing'
            );
        } catch (Throwable $throwable) {
            $results[] = result_row('Table: ' . $table, false, $throwable->getMessage());
        }
    }

    $brandingColumns = ['is_main_tenant', 'parent_company_id', 'brand_color', 'tagline', 'logo_path_white'];
    foreach ($brandingColumns as $column) {
        try {
            $exists = (int) $db->fetchValue(
                'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table_name AND column_name = :column_name',
                ['table_name' => 'companies', 'column_name' => $column]
            ) > 0;
            $results[] = result_row(
                'Companies column: ' . $column,
                $exists,
                $exists ? 'Present' : 'Missing'
            );
        } catch (Throwable $throwable) {
            $results[] = result_row('Companies column: ' . $column, false, $throwable->getMessage());
        }
    }

    try {
        $mainTenant = $db->fetch(
            'SELECT id, name, code, brand_color, tagline FROM companies WHERE is_main_tenant = 1 LIMIT 1'
        );

        $results[] = result_row(
            'Main tenant row',
            is_array($mainTenant) && $mainTenant !== [],
            is_array($mainTenant) && $mainTenant !== []
                ? 'Found: ' . ($mainTenant['name'] ?? 'unknown') . ' (' . ($mainTenant['code'] ?? '-') . ')'
                : 'No companies.is_main_tenant = 1 row found'
        );
    } catch (Throwable $throwable) {
        $results[] = result_row('Main tenant row', false, $throwable->getMessage());
    }

    try {
        $payrollSetting = $db->fetch(
            "SELECT id, setting_value FROM settings WHERE category_name = 'modules' AND setting_key = 'payroll_enabled' LIMIT 1"
        );
        $enabledValue = is_array($payrollSetting) ? (string) ($payrollSetting['setting_value'] ?? '') : '';
        $results[] = result_row(
            'Payroll setting row',
            is_array($payrollSetting) && $payrollSetting !== [],
            is_array($payrollSetting) && $payrollSetting !== []
                ? 'Found with value: ' . $enabledValue
                : 'Missing settings row for modules/payroll_enabled'
        );
        $results[] = result_row(
            'Payroll enabled value',
            $enabledValue === 'true',
            $enabledValue === '' ? 'No value found' : 'Current value: ' . $enabledValue
        );
    } catch (Throwable $throwable) {
        $results[] = result_row('Payroll setting row', false, $throwable->getMessage());
    }
}

$okCount = count(array_filter($results, static fn (array $row): bool => $row['status'] === 'OK'));
$failCount = count($results) - $okCount;
$testUrls = [
    '/settings/system',
    '/payroll/salary-structures',
    '/payroll/runs',
    '/payroll/runs/create',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payroll Live Check</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; background: #f6f8fb; color: #1c2434; }
        .wrap { max-width: 1100px; margin: 0 auto; }
        .card { background: #fff; border: 1px solid #dbe2ec; border-radius: 14px; padding: 20px; margin-bottom: 20px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
        .stat { border: 1px solid #e5ebf2; border-radius: 12px; padding: 16px; background: #fcfdff; }
        .ok { color: #157347; font-weight: 700; }
        .fail { color: #b42318; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; border-bottom: 1px solid #e9eef5; vertical-align: top; text-align: left; }
        th { background: #f8fafc; }
        code { background: #f2f4f7; padding: 2px 6px; border-radius: 6px; }
        .note { font-size: 14px; color: #4b5565; }
        a { color: #0b63ce; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h1 style="margin-top:0;">Payroll Live Check</h1>
            <p class="note">Upload this file to <code>public/payroll-live-check.php</code>, open it in the browser, review the results, then delete it after testing.</p>
        </div>

        <div class="card">
            <div class="grid">
                <div class="stat">
                    <div class="note">Server Time</div>
                    <div><strong><?= esc(date('Y-m-d H:i:s')); ?></strong></div>
                </div>
                <div class="stat">
                    <div class="note">PHP Version</div>
                    <div><strong><?= esc(PHP_VERSION); ?></strong></div>
                </div>
                <div class="stat">
                    <div class="note">Checks Passed</div>
                    <div class="ok"><?= esc((string) $okCount); ?></div>
                </div>
                <div class="stat">
                    <div class="note">Checks Failed</div>
                    <div class="<?= $failCount === 0 ? 'ok' : 'fail'; ?>"><?= esc((string) $failCount); ?></div>
                </div>
            </div>
        </div>

        <div class="card">
            <h2 style="margin-top:0;">Results</h2>
            <table>
                <thead>
                    <tr>
                        <th style="width: 32%;">Check</th>
                        <th style="width: 10%;">Status</th>
                        <th>Detail</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $row): ?>
                        <tr>
                            <td><?= esc($row['label']); ?></td>
                            <td class="<?= $row['status'] === 'OK' ? 'ok' : 'fail'; ?>"><?= esc($row['status']); ?></td>
                            <td><?= esc($row['detail']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h2 style="margin-top:0;">Manual URL Tests</h2>
            <ul>
                <?php foreach ($testUrls as $path): ?>
                    <li><a href="<?= esc(url($path)); ?>" target="_blank" rel="noopener"><?= esc(url($path)); ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</body>
</html>
