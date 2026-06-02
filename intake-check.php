<?php
/**
 * Intake Module File Checker
 * Upload to: /home/greykktq/public_html/platform/
 * Access at: https://hr.greydoha.com/intake-check.php
 */

define('BASE_PATH', dirname(__FILE__));

$requiredFiles = [
    // Routes
    'routes/intake.php' => 'Intake routes',

    // Controllers & Repositories
    'app/Modules/Intake/IntakeController.php' => 'Intake Controller',
    'app/Modules/Intake/IntakeRepository.php' => 'Intake Repository',

    // Views
    'app/Views/layouts/intake.php' => 'Intake layout',
    'app/Views/intake/form.php' => 'Registration form',
    'app/Views/intake/success.php' => 'Success page',
    'app/Views/intake/review-list.php' => 'Review list',
    'app/Views/intake/review-show.php' => 'Review detail',

    // Core
    'app/Core/Database.php' => 'Database (with transaction fix)',

    // Assets
    'public/assets/css/bootstrap.min.css' => 'Bootstrap CSS',
    'public/assets/css/bootstrap-icons.min.css' => 'Bootstrap Icons CSS',
    'public/assets/css/fonts/bootstrap-icons.woff' => 'Bootstrap Icons Font (WOFF)',
    'public/assets/css/fonts/bootstrap-icons.woff2' => 'Bootstrap Icons Font (WOFF2)',
    'public/assets/js/bootstrap.bundle.min.js' => 'Bootstrap JS',
];

$directories = [
    'routes',
    'app/Modules/Intake',
    'app/Views/intake',
    'app/Views/layouts',
    'app/Core',
    'public/assets/css/fonts',
    'public/assets/js',
    'storage/uploads/intake',
    'storage/uploads/documents',
    'storage/uploads/photos',
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Intake Module File Checker</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; }
        h1 { color: #333; margin-bottom: 10px; font-size: 28px; }
        .info { color: #666; margin-bottom: 30px; font-size: 14px; }
        .card { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .card h2 { font-size: 18px; margin-bottom: 15px; color: #333; }

        .file-item { display: flex; align-items: center; padding: 10px 0; border-bottom: 1px solid #eee; }
        .file-item:last-child { border-bottom: none; }
        .status-icon { width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 12px; color: white; font-weight: bold; font-size: 14px; }
        .status-ok { background: #4CAF50; }
        .status-missing { background: #f44336; }
        .file-path { flex: 1; font-family: monospace; font-size: 13px; color: #666; }
        .file-desc { color: #999; font-size: 12px; }

        .summary { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px; }
        .stat { padding: 15px; border-radius: 6px; text-align: center; }
        .stat-value { font-size: 32px; font-weight: bold; margin-bottom: 5px; }
        .stat-label { font-size: 14px; color: #666; }
        .stat-ok { background: #e8f5e9; color: #2e7d32; }
        .stat-error { background: #ffebee; color: #c62828; }

        .alert { padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }

        .database-check { margin-top: 20px; padding-top: 20px; border-top: 2px solid #eee; }
    </style>
</head>
<body>

<div class="container">
    <h1>Intake Module File Checker</h1>
    <p class="info">Upload this file to: <code>/public_html/platform/intake-check.php</code></p>

    <?php
    // Count files
    $found = 0;
    $missing = 0;

    foreach ($requiredFiles as $file => $desc) {
        if (file_exists(BASE_PATH . '/' . $file)) {
            $found++;
        } else {
            $missing++;
        }
    }

    // Display alert
    if ($missing === 0) {
        echo '<div class="alert alert-success"><strong>Success!</strong> All files are present. You can delete this checker.</div>';
    } else {
        echo '<div class="alert alert-error"><strong>Missing Files!</strong> ' . $missing . ' file(s) not found. Upload them to continue.</div>';
    }
    ?>

    <!-- Summary -->
    <div class="summary">
        <div class="stat stat-ok">
            <div class="stat-value"><?= $found; ?></div>
            <div class="stat-label">Files Found</div>
        </div>
        <div class="stat <?= $missing > 0 ? 'stat-error' : 'stat-ok'; ?>">
            <div class="stat-value"><?= $missing; ?></div>
            <div class="stat-label">Files Missing</div>
        </div>
    </div>

    <!-- File Check -->
    <div class="card">
        <h2>Required Files</h2>
        <?php foreach ($requiredFiles as $file => $desc): ?>
            <?php $exists = file_exists(BASE_PATH . '/' . $file); ?>
            <div class="file-item">
                <div class="status-icon <?= $exists ? 'status-ok' : 'status-missing'; ?>">
                    <?= $exists ? '✓' : '✗'; ?>
                </div>
                <div style="flex: 1;">
                    <div class="file-path"><?= htmlspecialchars($file); ?></div>
                    <div class="file-desc"><?= htmlspecialchars($desc); ?></div>
                </div>
                <div style="text-align: right; color: <?= $exists ? '#4CAF50' : '#f44336'; ?>; font-weight: bold;">
                    <?= $exists ? 'OK' : 'MISSING'; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Directory Check -->
    <div class="card">
        <h2>Required Directories</h2>
        <?php foreach ($directories as $dir): ?>
            <?php $exists = is_dir(BASE_PATH . '/' . $dir); ?>
            <div class="file-item">
                <div class="status-icon <?= $exists ? 'status-ok' : 'status-missing'; ?>">
                    <?= $exists ? '✓' : '✗'; ?>
                </div>
                <div>
                    <div class="file-path"><?= htmlspecialchars($dir); ?>/</div>
                </div>
                <div style="text-align: right; color: <?= $exists ? '#4CAF50' : '#f44336'; ?>; font-weight: bold;">
                    <?= $exists ? 'OK' : 'MISSING'; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Database Check -->
    <div class="card database-check">
        <h2>Database Tables</h2>
        <?php
        // Try to check database
        $dbTables = [
            'employee_intake_submissions' => 'Submissions table',
            'employee_intake_contacts' => 'Contacts table',
            'employee_intake_documents' => 'Documents table',
        ];

        try {
            // Check if we can load the app
            $appPath = BASE_PATH . '/app/Core/Application.php';
            if (file_exists($appPath)) {
                require $appPath;
                $app = new \App\Core\Application(BASE_PATH);
                $db = $app->database();

                foreach ($dbTables as $table => $desc) {
                    $result = $db->fetchValue("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?", [$table]);
                    $exists = !empty($result);
                    ?>
                    <div class="file-item">
                        <div class="status-icon <?= $exists ? 'status-ok' : 'status-missing'; ?>">
                            <?= $exists ? '✓' : '✗'; ?>
                        </div>
                        <div>
                            <div class="file-path"><?= htmlspecialchars($table); ?></div>
                            <div class="file-desc"><?= htmlspecialchars($desc); ?></div>
                        </div>
                        <div style="text-align: right; color: <?= $exists ? '#4CAF50' : '#f44336'; ?>; font-weight: bold;">
                            <?= $exists ? 'OK' : 'MISSING'; ?>
                        </div>
                    </div>
                    <?php
                }
            } else {
                echo '<div class="file-item"><div style="color: #999;">Skipped: Application file not found yet</div></div>';
            }
        } catch (\Throwable $e) {
            echo '<div class="file-item"><div style="color: #f44336;">Error: ' . htmlspecialchars($e->getMessage()) . '</div></div>';
        }
        ?>
    </div>

    <!-- Instructions -->
    <div class="card">
        <h2>Next Steps</h2>
        <ol style="margin-left: 20px; line-height: 1.8; color: #666;">
            <li>If all files are <strong>OK</strong>: Delete this file and test <a href="/employee-registration" target="_blank">/employee-registration</a></li>
            <li>If files are <strong>MISSING</strong>:
                <ul style="margin-top: 10px; margin-left: 20px;">
                    <li>Download <code>intake-deployment-2026-05-05.zip</code></li>
                    <li>Extract to your computer</li>
                    <li>Upload the <code>intake-deploy</code> folder contents to <code>/public_html/platform/</code></li>
                    <li>Reload this page to verify</li>
                </ul>
            </li>
            <li>If database tables are <strong>MISSING</strong>: Run the SQL migrations in your database</li>
        </ol>
    </div>

    <div style="text-align: center; margin-top: 30px; color: #999; font-size: 12px;">
        <p>This checker is safe to delete once deployment is verified.</p>
    </div>
</div>

</body>
</html>
