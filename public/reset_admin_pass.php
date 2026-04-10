<?php
// One-time admin password reset - DELETE AFTER USE
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=hr_system;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$newPassword = 'admin@123';
$hash = password_hash($newPassword, PASSWORD_BCRYPT);

$stmt = $pdo->prepare("UPDATE users SET password_hash = ?, must_change_password = 0, last_password_change_at = NOW() WHERE username = 'admin'");
$stmt->execute([$hash]);

echo "✓ Admin password reset to: <strong>admin@123</strong><br>";
echo "Rows updated: " . $stmt->rowCount() . "<br><br>";
echo "<strong>Delete this file after use!</strong>";
