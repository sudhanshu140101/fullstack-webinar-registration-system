<?php

declare(strict_types=1);



if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run this script from the command line only.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/config/database.php';

$username = $argv[1] ?? 'admin';
$email = $argv[2] ?? 'admin@indiansmechamber.com';
$password = $argv[3] ?? 'ChangeMe@2026!';
$fullName = $argv[4] ?? 'System Administrator';

if (strlen($password) < 10) {
    fwrite(STDERR, "Password must be at least 10 characters.\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$db = Database::getConnection();

$stmt = $db->prepare(
    'INSERT INTO admins (username, email, password_hash, full_name, is_active)
     VALUES (:username, :email, :password_hash, :full_name, 1)
     ON DUPLICATE KEY UPDATE
       email = VALUES(email),
       password_hash = VALUES(password_hash),
       full_name = VALUES(full_name),
       is_active = 1'
);

$stmt->execute([
    ':username' => $username,
    ':email' => $email,
    ':password_hash' => $hash,
    ':full_name' => $fullName,
]);

$db->exec('TRUNCATE TABLE login_attempts');
require_once dirname(__DIR__) . '/includes/install.php';
install_mark_complete();

echo "Admin user \"{$username}\" is ready.\n";
echo "Password has been set to your provided value.\n";
echo "Login lockouts cleared.\n";
echo "Sign in at: /admin/login.php\n";
