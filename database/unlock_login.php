<?php

declare(strict_types=1);



if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run this script from the command line only.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/config/database.php';

$db = Database::getConnection();
$db->exec('TRUNCATE TABLE login_attempts');

echo "Login lockouts cleared. You can sign in again.\n";
