<?php

declare(strict_types=1);



require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/install.php';

$config = app_config();
ensure_app_upload_directories();

if (!($config['debug'] ?? false)) {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

$logDir = dirname(__DIR__) . '/storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0750, true);
}

ini_set('log_errors', '1');
ini_set('error_log', $config['log_path']);

require_once dirname(__DIR__) . '/config/database.php';
require_once __DIR__ . '/Security.php';
require_once __DIR__ . '/Validator.php';
require_once __DIR__ . '/RegistrationRepository.php';
require_once __DIR__ . '/PolicyAdvocacyRepository.php';
require_once __DIR__ . '/HeroRepository.php';
require_once __DIR__ . '/SaveTheDateRepository.php';
require_once __DIR__ . '/SeatsUrgencyRepository.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/SmsGateway.php';
require_once __DIR__ . '/OtpService.php';
require_once __DIR__ . '/UserAuth.php';
require_once __DIR__ . '/FileUpload.php';

Security::sendSecurityHeaders();
Security::startSession($config);

