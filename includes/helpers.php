<?php

declare(strict_types=1);

function app_config(): array
{
    /** @var array $config */
    $config = require dirname(__DIR__) . '/config/config.php';

    return $config;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function client_ip(): string
{
    $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $value = (string) $_SERVER[$header];
            if (str_contains($value, ',')) {
                $value = trim(explode(',', $value)[0]);
            }
            if (filter_var($value, FILTER_VALIDATE_IP)) {
                return $value;
            }
        }
    }

    return '0.0.0.0';
}

function log_app(string $message, array $context = []): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    if ($context !== []) {
        $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
    }
    error_log($line);
}

function format_datetime(?string $datetime): string
{
    if ($datetime === null || $datetime === '') {
        return '—';
    }

    $ts = strtotime($datetime);
    return $ts ? date('d M Y, h:i A', $ts) : e($datetime);
}

function seat_label(string $seat): string
{
    return match ($seat) {
        'micro' => 'Micro',
        'small' => 'Small',
        'medium' => 'Medium',
        'startup' => 'Startup',
        'professionals' => 'Professionals',
        'other' => 'Other',
        default => ucfirst($seat),
    };
}

/** @return list<string> */
function payment_status_values(): array
{
    return ['pending', 'paid', 'failed'];
}

/** @return array<string, string> */
function payment_status_options(): array
{
    return [
        '' => 'Select status',
        'pending' => 'Pending',
        'paid' => 'Payment Done',
        'failed' => 'Not Done',
    ];
}

function payment_status_label(?string $status): string
{
    return match ($status) {
        'paid' => 'Payment Done',
        'failed' => 'Not Done',
        'pending' => 'Pending',
        default => 'Select status',
    };
}

function is_valid_payment_status(?string $status): bool
{
    return $status === null || in_array($status, payment_status_values(), true);
}

function is_registration_verified(?array $registration): bool
{
    return !empty($registration['is_verified']);
}

/** @return array<string, string> */
function verification_status_options(): array
{
    return [
        '0' => 'Not Verified',
        '1' => 'Verified',
    ];
}

function verification_status_label(?array $registration): string
{
    return is_registration_verified($registration) ? 'Verified' : 'Not Verified';
}

function format_inr_paise(int $amountPaise): string
{
    if ($amountPaise <= 0) {
        return '₹0';
    }

    $rupees = $amountPaise / 100;
    if ($amountPaise % 100 === 0) {
        return '₹' . number_format($rupees, 0);
    }

    return '₹' . number_format($rupees, 2);
}

function rupees_to_paise(float $rupees): int
{
    return max(0, (int) round($rupees * 100));
}

/**
 * @param array<string, mixed> $settings
 */
function registration_payment_url(array $settings): string
{
    $url = sanitize_safe_external_url((string) ($settings['payment_url'] ?? ''));

    return $url ?? '';
}

function policy_advocacy_preview_src(?string $path): string
{
    $path = trim((string) $path);
    if ($path === '') {
        return '../images/Slider-1.png';
    }

    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '../')) {
        return $path;
    }

    return '../' . ltrim($path, '/');
}

function policy_advocacy_upload_dir(): string
{
    return ensure_upload_subdirectory('policy-advocacy', true);
}

/**
 * @throws RuntimeException
 */
function ensure_upload_subdirectory(string $relative, bool $publicReadable = false): string
{
    $relative = trim(str_replace('\\', '/', $relative), '/');
    if ($relative === '' || str_contains($relative, '..')) {
        throw new RuntimeException('Invalid upload path.');
    }

    $base = app_config()['upload']['path'];
    $full = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $dirMode = $publicReadable ? 0755 : 0750;

    if (!is_dir($full)) {
        if (!@mkdir($full, $dirMode, true) && !is_dir($full)) {
            throw new RuntimeException(
                'Could not create upload folder (' . $relative . '). Ensure uploads/ is writable by the web server.'
            );
        }
    }

    if (!is_writable($full)) {
        throw new RuntimeException(
            'Upload folder is not writable (' . $relative . '). Set permissions on uploads/ for the web server user.'
        );
    }

    return $full;
}

function ensure_app_upload_directories(): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }
    $ensured = true;

    $base = app_config()['upload']['path'];
    if (!is_dir($base)) {
        @mkdir($base, 0755, true);
    }

    try {
        policy_advocacy_upload_dir();
    } catch (RuntimeException $exception) {
        log_app('Upload directory setup warning', ['error' => $exception->getMessage()]);
    }
}

function sanitize_safe_register_url(string $url, string $default = 'register.html'): string
{
    $url = Security::sanitizeString($url, 255);
    if ($url === '') {
        return $default;
    }

    $lower = strtolower($url);
    if (str_starts_with($lower, 'javascript:') || str_starts_with($lower, 'data:') || str_starts_with($lower, 'vbscript:')) {
        return $default;
    }

    if (preg_match('#^https?://#i', $url) === 1) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false ? $url : $default;
    }

    if (preg_match('~^(?:\./|/)?[a-zA-Z0-9_./?=&%+-]+$~', $url) === 1) {
        return $url;
    }

    return $default;
}

function sanitize_safe_external_url(?string $url): ?string
{
    if ($url === null || trim($url) === '') {
        return null;
    }

    $url = Security::sanitizeString($url, 500);
    $lower = strtolower($url);
    if (str_starts_with($lower, 'javascript:') || str_starts_with($lower, 'data:') || str_starts_with($lower, 'vbscript:')) {
        return null;
    }

    if (preg_match('#^https?://#i', $url) !== 1) {
        return null;
    }

    return filter_var($url, FILTER_VALIDATE_URL) !== false ? $url : null;
}

function is_valid_register_url(string $url): bool
{
    $sanitized = Security::sanitizeString($url, 255);
    if ($sanitized === '') {
        return false;
    }

    return sanitize_safe_register_url($sanitized, '') !== '';
}

function is_valid_external_url(string $url): bool
{
    return sanitize_safe_external_url($url) !== null;
}

function format_public_registrant_name(string $name): string
{
    $name = Security::sanitizeString(trim($name), 80);
    if ($name === '') {
        return 'Someone';
    }

    $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
    if ($parts === false || $parts === []) {
        return 'Someone';
    }

    if (count($parts) === 1) {
        return $parts[0];
    }

    $first = $parts[0];
    $lastInitial = mb_strtoupper(mb_substr($parts[count($parts) - 1], 0, 1));

    return $first . ' ' . $lastInitial . '.';
}

function registration_time_ago_label(string $datetime): string
{
    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return 'recently';
    }

    $seconds = max(0, time() - $timestamp);
    if ($seconds < 60) {
        return 'just now';
    }

    $minutes = (int) floor($seconds / 60);
    if ($minutes < 60) {
        return $minutes === 1 ? '1 min ago' : $minutes . ' min ago';
    }

    $hours = (int) floor($seconds / 3600);
    if ($hours < 24) {
        return $hours === 1 ? '1 hr ago' : $hours . ' hr ago';
    }

    $days = (int) floor($seconds / 86400);
    if ($days < 7) {
        return $days === 1 ? '1 day ago' : $days . ' days ago';
    }

    return 'recently';
}
