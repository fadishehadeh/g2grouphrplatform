<?php
try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306', 'root', '');
    $dbs = $pdo->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN);
    echo "Databases: " . implode(', ', $dbs);

    if (in_array('hr_system', $dbs)) {
        $pdo2 = new PDO('mysql:host=127.0.0.1;port=3306;dbname=hr_system', 'root', '');
        $tables = $pdo2->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        echo "\n\nhr_system tables (" . count($tables) . "): " . implode(', ', $tables);
    } else {
        echo "\n\nhr_system database does NOT exist!";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
