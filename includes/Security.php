<?php

declare(strict_types=1);

final class Security
{
    public static function sendSecurityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        header('X-XSS-Protection: 0');

        $config = app_config();
        if (!empty($config['app_env']) && $config['app_env'] === 'production') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    public static function startSession(array $config): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $session = $config['session'];
        $secure = (bool) ($session['secure'] ?? false);

        if (PHP_SAPI !== 'cli') {
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
            $secure = $isHttps ? true : $secure;
        }

        session_name($session['name'] ?? 'MSME_CONNECT_SESSID');
        session_set_cookie_params([
            'lifetime' => (int) ($session['lifetime'] ?? 3600),
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => (bool) ($session['httponly'] ?? true),
            'samesite' => $session['samesite'] ?? 'Lax',
        ]);

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_start();

        self::enforceSessionTimeout($config);
        self::rotateSessionId();
    }

    private static function enforceSessionTimeout(array $config): void
    {
        $lifetime = (int) ($config['session']['lifetime'] ?? 3600);
        $now = time();

        if (!isset($_SESSION['_last_activity'])) {
            $_SESSION['_last_activity'] = $now;
            return;
        }

        if (($now - (int) $_SESSION['_last_activity']) > $lifetime) {
            $_SESSION = [];
            session_destroy();
            session_start();
            $_SESSION['_last_activity'] = $now;
            return;
        }

        $_SESSION['_last_activity'] = $now;
    }

    private static function rotateSessionId(): void
    {
        if (!isset($_SESSION['_created'])) {
            $_SESSION['_created'] = time();
            return;
        }

        if (time() - (int) $_SESSION['_created'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['_created'] = time();
        }
    }

    public static function getCsrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public static function validateCsrf(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        $sessionToken = $_SESSION['csrf_token'] ?? '';

        return $sessionToken !== '' && hash_equals($sessionToken, $token);
    }

    public static function sanitizeString(string $value, int $maxLength = 255): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        if (mb_strlen($value) > $maxLength) {
            $value = mb_substr($value, 0, $maxLength);
        }

        return $value;
    }
}
