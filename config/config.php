<?php

declare(strict_types=1);



if (!function_exists('msme_load_env_file')) {
    function msme_load_env_file(): void
    {
        $envPath = dirname(__DIR__) . '/.env';
        if (!is_readable($envPath)) {
            return;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\"'");

            if ($key !== '' && getenv($key) === false) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
            }
        }
    }
}

msme_load_env_file();

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return match (strtolower((string) $value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            default => $value,
        };
    }
}

if (!isset($GLOBALS['msme_app_config'])) {
    $appEnv = (string) env('APP_ENV', 'production');
    $debug = (bool) env('APP_DEBUG', false);
    if ($appEnv === 'production') {
        $debug = false;
    }

    $GLOBALS['msme_app_config'] = [
        'app_name' => (string) env('APP_NAME', 'MSME Connect Summit'),
        'app_url' => rtrim((string) env('APP_URL', ''), '/'),
        'app_env' => $appEnv,
        'debug' => $debug,

        'db' => [
            'host' => (string) env('DB_HOST', '127.0.0.1'),
            'port' => (int) env('DB_PORT', 3306),
            'name' => (string) env('DB_NAME', 'msme_connect'),
            'user' => (string) env('DB_USER', 'root'),
            'pass' => (string) env('DB_PASS', ''),
            'charset' => 'utf8mb4',
        ],

        'session' => [
            'name' => (string) env('SESSION_NAME', 'MSME_CONNECT_SESSID'),
            'lifetime' => (int) env('SESSION_LIFETIME', 3600),
            'secure' => (bool) env('SESSION_SECURE', false),
            'httponly' => true,
            'samesite' => (string) env('SESSION_SAMESITE', 'Lax'),
        ],

        'csrf' => [
            'token_name' => 'csrf_token',
        ],

        'upload' => [
            'max_size' => (int) env('UPLOAD_MAX_SIZE', 5_242_880),
            'allowed_mime' => [
                'application/pdf',
                'image/jpeg',
                'image/png',
                'image/webp',
            ],
            'allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'webp'],
            'path' => dirname(__DIR__) . '/uploads',
        ],

        'security' => [
            'login_max_attempts' => (int) env('LOGIN_MAX_ATTEMPTS', 20),
            'login_lockout_minutes' => (int) env('LOGIN_LOCKOUT_MINUTES', 30),
            'rate_limit_submissions_per_hour' => (int) env('RATE_LIMIT_SUBMISSIONS', 20),
        ],

        'otp' => [
            'validity_minutes' => (int) env('OTP_VALIDITY_MINUTES', 5),
            'length' => 6,
            'max_send_per_hour' => (int) env('OTP_MAX_SEND_PER_HOUR', 5),
            'resend_cooldown_seconds' => (int) env('OTP_RESEND_COOLDOWN_SECONDS', 120),
            'max_verify_attempts' => (int) env('OTP_MAX_VERIFY_ATTEMPTS', 5),
            'registration_verified_ttl_minutes' => (int) env('OTP_REGISTRATION_VERIFIED_TTL_MINUTES', 30),
            'hmac_secret' => (string) env('OTP_HMAC_SECRET', (string) env('SESSION_NAME', 'MSME_CONNECT_SESSID')),
            'log_for_testing' => (bool) env('OTP_LOG_FOR_TESTING', false),
        ],

        'sms' => [
            'enabled' => (bool) env('SMS_ENABLED', true),
            'api_url' => (string) env('SMS_API_URL', 'https://rslri.connectbind.com:8443/bulksms/bulksms'),
            'username' => (string) env('SMS_USERNAME', ''),
            'password' => (string) env('SMS_PASSWORD', ''),
            'sender_id' => (string) env('SMS_SENDER_ID', 'MSMEAP'),
            'entity_id' => (string) env('SMS_ENTITY_ID', ''),
            'template_id' => (string) env('SMS_TEMPLATE_ID', ''),
            'tmid' => (string) env('SMS_TMID', ''),
            'message_template' => (string) env(
                'SMS_OTP_MESSAGE',
                '{otp} is your OTP to verify to your Number. Please do not share this with anyone MSMEAP'
            ),
            'type' => (int) env('SMS_TYPE', 0),
            'dlr' => (int) env('SMS_DLR', 1),
            'timeout_seconds' => (int) env('SMS_TIMEOUT_SECONDS', 20),
            'destination_prefix' => (string) env('SMS_DESTINATION_PREFIX', '91'),
        ],

        'log_path' => dirname(__DIR__) . '/storage/logs/app.log',
    ];
}

return $GLOBALS['msme_app_config'];
