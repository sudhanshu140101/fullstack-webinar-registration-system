<?php

declare(strict_types=1);



if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
$issues = [];
$passed = [];

require_once $root . '/includes/bootstrap.php';

try {
    $db = Database::getConnection();
    $passed[] = 'Database connection';

    $repo = new RegistrationRepository();
    $repo->search('test', null, null, 1, 5);
    $passed[] = 'Search query (unique PDO placeholders)';

    $count = $repo->countSubmissionsSince('127.0.0.1', 1);
    $passed[] = "Rate limit query (count={$count})";

    $adminCount = (int) $db->query('SELECT COUNT(*) FROM admins WHERE is_active = 1')->fetchColumn();
    if ($adminCount < 1) {
        $issues[] = 'No active admin account — run seed_admin.php';
    } else {
        $passed[] = "Active admin accounts: {$adminCount}";
    }
} catch (Throwable $e) {
    $issues[] = 'Database/runtime: ' . $e->getMessage();
}

$config = app_config();
$warnings = [];
if ($config['app_env'] === 'production' && $config['debug']) {
    $issues[] = 'APP_DEBUG must be false when APP_ENV=production';
} elseif ($config['app_env'] === 'local') {
    $warnings[] = 'APP_ENV=local — use env.production.example on live server';
    $passed[] = 'Environment: local (development)';
} else {
    $passed[] = 'Environment: ' . $config['app_env'];
}

$writable = ['uploads', 'uploads/policy-advocacy', 'storage/logs'];
foreach ($writable as $dir) {
    $path = $root . '/' . $dir;
    if (!is_dir($path)) {
        $issues[] = "Missing directory: {$dir}";
    } elseif (!is_writable($path)) {
        $issues[] = "Not writable: {$dir}";
    } else {
        $passed[] = "Writable: {$dir}";
    }
}

$cliOnly = ['database/seed_admin.php', 'database/unlock_login.php', 'database/update_admin.php'];
foreach ($cliOnly as $file) {
    $content = (string) file_get_contents($root . '/' . $file);
    if (!str_contains($content, "PHP_SAPI !== 'cli'")) {
        $issues[] = "{$file} missing CLI-only guard";
    } else {
        $passed[] = "{$file} CLI-only";
    }
}

$required = [
    'index.html', 'script.js', 'styles.css', 'api/submit.php', 'api/csrf.php',
    'admin/login.php', 'admin/dashboard.php', '.htaccess', 'database/schema.sql',
];
foreach ($required as $file) {
    if (!is_file($root . '/' . $file)) {
        $issues[] = "Missing file: {$file}";
    }
}

if (install_is_complete()) {
    $passed[] = 'Install lock present';
} else {
    $issues[] = 'storage/install.lock missing — run seed_admin after setup';
}

echo "=== MSME Connect Production Audit ===\n\n";
echo 'PASSED (' . count($passed) . "):\n";
foreach ($passed as $p) {
    echo "  [OK] {$p}\n";
}
echo "\nWARNINGS (" . count($warnings) . "):\n";
if ($warnings === []) {
    echo "  (none)\n";
} else {
    foreach ($warnings as $w) {
        echo "  [~] {$w}\n";
    }
}

echo "\nISSUES (" . count($issues) . "):\n";
if ($issues === []) {
    echo "  (none)\n";
} else {
    foreach ($issues as $i) {
        echo "  [!] {$i}\n";
    }
}
echo "\n";
exit($issues === [] ? 0 : 1);
