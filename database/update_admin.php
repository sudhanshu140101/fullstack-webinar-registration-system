<?php

declare(strict_types=1);



if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run this script from the command line only.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/config/database.php';

$username = $argv[1] ?? '';
$email = $argv[2] ?? $username;
$password = $argv[3] ?? '';
$fullName = $argv[4] ?? 'Administrator';

if ($username === '' || $password === '') {
    fwrite(STDERR, "Usage: php database/update_admin.php <username> <email> <password> [full_name]\n");
    exit(1);
}

if (strlen($password) < 10) {
    fwrite(STDERR, "Password must be at least 10 characters.\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$db = Database::getConnection();

$existing = $db->query('SELECT id FROM admins ORDER BY id ASC LIMIT 1')->fetch();

if ($existing) {
    $stmt = $db->prepare(
        'UPDATE admins SET username = :username, email = :email, password_hash = :password_hash,
         full_name = :full_name, is_active = 1 WHERE id = :id'
    );
    $stmt->execute([
        ':username' => $username,
        ':email' => $email,
        ':password_hash' => $hash,
        ':full_name' => $fullName,
        ':id' => $existing['id'],
    ]);
    $keepId = (int) $existing['id'];
    $delete = $db->prepare('DELETE FROM admins WHERE id != :id');
    $delete->execute([':id' => $keepId]);
} else {
    $stmt = $db->prepare(
        'INSERT INTO admins (username, email, password_hash, full_name, is_active)
         VALUES (:username, :email, :password_hash, :full_name, 1)'
    );
    $stmt->execute([
        ':username' => $username,
        ':email' => $email,
        ':password_hash' => $hash,
        ':full_name' => $fullName,
    ]);
}

$db->exec('TRUNCATE TABLE login_attempts');
require_once dirname(__DIR__) . '/includes/install.php';
install_mark_complete();

echo "Admin credentials updated.\n";
echo "Username: {$username}\n";
echo "Login lockouts cleared.\n";
