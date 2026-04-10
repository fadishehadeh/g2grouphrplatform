<?php
// One-time DB setup script - DELETE AFTER USE
set_time_limit(300);

$host = '127.0.0.1';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;port=3306;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `hr_system` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✓ Database 'hr_system' created/verified<br>";

    // Use it
    $pdo->exec("USE `hr_system`");

    // Import SQL file
    $sqlFile = __DIR__ . '/../database/hr_system_full.sql';
    if (!file_exists($sqlFile)) {
        die("✗ SQL file not found at: $sqlFile");
    }

    $sql = file_get_contents($sqlFile);

    // Remove USE statements to avoid conflicts
    $sql = preg_replace('/^USE\s+\S+;\s*$/mi', '', $sql);

    // Split by semicolons and execute
    $statements = array_filter(array_map('trim', explode(";\n", $sql)));
    $count = 0;
    foreach ($statements as $stmt) {
        if (empty($stmt) || str_starts_with(ltrim($stmt), '--')) continue;
        try {
            $pdo->exec($stmt);
            $count++;
        } catch (PDOException $e) {
            // Skip errors for duplicate entries etc
            echo "⚠ Warning: " . htmlspecialchars($e->getMessage()) . "<br>";
        }
    }

    echo "✓ Imported $count SQL statements<br>";

    // Verify tables
    $tables = $pdo->query("SHOW TABLES FROM `hr_system`")->fetchAll(PDO::FETCH_COLUMN);
    echo "✓ Tables created (" . count($tables) . "): " . implode(', ', $tables) . "<br><br>";
    echo "<strong>✓ Setup complete! <a href='/HR%20System/public/login'>Go to app →</a></strong><br>";
    echo "<br><em>Delete this file after use: public/setup_db.php</em>";

} catch (Exception $e) {
    echo "✗ Error: " . htmlspecialchars($e->getMessage());
}
