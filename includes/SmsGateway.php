<?php

declare(strict_types=1);

final class SmsGateway
{
    /**
     * @return array{success: bool, message: string, response?: string, message_id?: string}
     */
    public function sendOtp(string $mobile, string $otp): array
    {
        $config = app_config()['sms'] ?? [];
        $validation = $this->validateConfig($config);
        if (!$validation['valid']) {
            log_app('SMS configuration invalid', ['errors' => $validation['errors']]);

            return [
                'success' => false,
                'message' => 'SMS service is not configured correctly. Please contact support.',
            ];
        }

        if (empty($config['enabled'])) {
            log_app('SMS disabled; OTP not sent', ['mobile' => $this->maskMobile($mobile)]);

            return [
                'success' => false,
                'message' => 'SMS delivery is disabled.',
            ];
        }

        if (!function_exists('curl_init')) {
            log_app('PHP cURL extension is required for SMS delivery');

            return [
                'success' => false,
                'message' => 'SMS service is unavailable (cURL missing on server).',
            ];
        }

        $destination = $this->formatDestination($mobile, (string) ($config['destination_prefix'] ?? ''));
        $message = $this->buildMessage($config, $otp);
        $params = $this->buildRequestParams($config, $destination, $message);
        $timeout = max(5, (int) ($config['timeout_seconds'] ?? 20));
        $apiUrl = trim((string) $config['api_url']);

        // POST is preferred for DLT payloads; fall back to GET if POST fails.
        $request = $this->submitPost($apiUrl, $params, $timeout);
        if ($request['error'] !== null) {
            $request = $this->submitGet($apiUrl, $params, $timeout);
        }

        if ($request['error'] !== null) {
            $this->logSendAttempt($mobile, $destination, $message, $otp, '', false, $request['error']);
            log_app('SMS gateway request failed', [
                'mobile' => $this->maskMobile($mobile),
                'destination' => $destination,
                'error' => $request['error'],
            ]);

            return [
                'success' => false,
                'message' => 'Unable to send OTP SMS. Please try again shortly.',
            ];
        }

        $response = trim($request['body']);
        $messageId = $this->extractMessageId($response);
        $success = $this->isSuccessResponse($response);

        $this->logSendAttempt($mobile, $destination, $message, $otp, $response, $success, null);

        log_app('SMS gateway response', [
            'mobile' => $this->maskMobile($mobile),
            'destination' => $destination,
            'message_preview' => $this->maskOtpInMessage($message, $otp),
            'response' => mb_substr($response, 0, 200),
            'message_id' => $messageId,
        ]);

        if ($success) {
            $this->logOtpForLocalTesting($mobile, $otp);

            return [
                'success' => true,
                'message' => 'OTP sent successfully.',
                'response' => $response,
                'message_id' => $messageId,
            ];
        }

        return [
            'success' => false,
            'message' => $this->humanizeError($response),
            'response' => $response,
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @return array{valid: bool, errors: list<string>}
     */
    public function validateConfig(array $config): array
    {
        $errors = [];

        if (trim((string) ($config['username'] ?? '')) === '') {
            $errors[] = 'SMS_USERNAME is missing';
        }
        if ((string) ($config['password'] ?? '') === '') {
            $errors[] = 'SMS_PASSWORD is missing';
        }
        if (trim((string) ($config['api_url'] ?? '')) === '') {
            $errors[] = 'SMS_API_URL is missing';
        }
        if (trim((string) ($config['sender_id'] ?? '')) === '') {
            $errors[] = 'SMS_SENDER_ID is missing';
        }

        $entityId = trim((string) ($config['entity_id'] ?? ''));
        $templateId = trim((string) ($config['template_id'] ?? ''));
        $tmid = trim((string) ($config['tmid'] ?? ''));
        if ($entityId === '' || !preg_match('/^\d{10,20}$/', $entityId)) {
            $errors[] = 'SMS_ENTITY_ID is missing or invalid';
        }
        if ($templateId === '' || !preg_match('/^\d{10,20}$/', $templateId)) {
            $errors[] = 'SMS_TEMPLATE_ID is missing or invalid';
        }
        if ($tmid === '') {
            $errors[] = 'SMS_TMID is missing (required by ConnectBind DLT)';
        }

        $template = (string) ($config['message_template'] ?? '');
        if ($template === '' || !str_contains($template, '{otp}')) {
            $errors[] = 'SMS_OTP_MESSAGE must contain {otp} placeholder';
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
        ];
    }

    /**
     * Build params exactly as ConnectBind bulk SMS API expects.
     *
     * @param array<string, mixed> $config
     * @return array<string, string>
     */
    private function buildRequestParams(array $config, string $destination, string $message): array
    {
        return [
            'username' => trim((string) $config['username']),
            'password' => (string) $config['password'],
            'type' => (string) ((int) ($config['type'] ?? 0)),
            'dlr' => (string) ((int) ($config['dlr'] ?? 1)),
            'destination' => $destination,
            'source' => trim((string) ($config['sender_id'] ?? 'MSMEAP')),
            'message' => $message,
            'entityid' => trim((string) $config['entity_id']),
            'tempid' => trim((string) $config['template_id']),
            'tmid' => trim((string) $config['tmid']),
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    private function buildMessage(array $config, string $otp): string
    {
        $template = trim((string) ($config['message_template'] ?? '{otp} is your OTP to verify to your Number. Please do not share this with anyone MSMEAP'));
        $message = str_replace('{otp}', $otp, $template);
        $message = preg_replace('/\s+/u', ' ', $message) ?? $message;

        return trim($message);
    }

    private function formatDestination(string $mobile, string $prefix): string
    {
        $digits = preg_replace('/\D/', '', $mobile) ?? '';
        if ($digits === '') {
            return $mobile;
        }

        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            return $digits;
        }

        if (strlen($digits) === 10) {
            $prefix = preg_replace('/\D/', '', $prefix) ?? '';
            if ($prefix === '') {
                $prefix = '91';
            }

            return $prefix . $digits;
        }

        return $digits;
    }

    /**
     * @param array<string, string> $params
     * @return array{body: string, error: ?string}
     */
    private function submitGet(string $apiUrl, array $params, int $timeout): array
    {
        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $url = $apiUrl . (str_contains($apiUrl, '?') ? '&' : '?') . $query;

        $ch = curl_init($url);
        if ($ch === false) {
            return ['body' => '', 'error' => 'curl_init_failed'];
        }

        curl_setopt_array($ch, [
            CURLOPT_HTTPGET => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => ['Accept: text/plain'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        return $this->executeCurl($ch);
    }

    /**
     * @param array<string, string> $params
     * @return array{body: string, error: ?string}
     */
    private function submitPost(string $apiUrl, array $params, int $timeout): array
    {
        $ch = curl_init($apiUrl);
        if ($ch === false) {
            return ['body' => '', 'error' => 'curl_init_failed'];
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params, '', '&', PHP_QUERY_RFC3986),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => ['Accept: text/plain', 'Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        return $this->executeCurl($ch);
    }

    /**
     * @param \CurlHandle $ch
     * @return array{body: string, error: ?string}
     */
    private function executeCurl($ch): array
    {
        $body = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false) {
            return ['body' => '', 'error' => $curlError !== '' ? $curlError : 'curl_exec_failed'];
        }

        if ($httpCode >= 400) {
            return ['body' => (string) $body, 'error' => 'http_' . $httpCode];
        }

        return ['body' => (string) $body, 'error' => null];
    }

    private function isSuccessResponse(string $response): bool
    {
        return $response !== '' && str_starts_with($response, '1701');
    }

    private function extractMessageId(string $response): ?string
    {
        if (preg_match('/1701\|[^:]+:([^\s,|]+)/', $response, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    private function humanizeError(string $response): string
    {
        $code = strtok($response, '|') ?: $response;

        return match ($code) {
            '1702' => 'SMS gateway rejected the request (invalid parameters).',
            '1703' => 'SMS gateway rejected the request (invalid credentials).',
            '1704' => 'SMS gateway rejected the request (invalid mobile number).',
            '1705' => 'SMS gateway rejected the request (invalid sender ID MSMEAP).',
            '1706' => 'SMS text does not match your approved DLT template. Check SMS_OTP_MESSAGE in .env matches DLT exactly.',
            '1707' => 'SMS gateway rejected the request (invalid DLT entity, template, or tmid).',
            '1709' => 'SMS gateway is temporarily unavailable. Please try again.',
            '1025' => 'SMS account balance may be insufficient. Contact ConnectBind support.',
            default => 'Unable to send OTP SMS. Please try again shortly.',
        };
    }

    private function logSendAttempt(
        string $mobile,
        string $destination,
        string $message,
        string $otp,
        string $response,
        bool $success,
        ?string $error
    ): void {
        try {
            $stmt = Database::getConnection()->prepare(
                'INSERT INTO sms_send_logs (mobile, destination, message_preview, gateway_response, message_id, success, error_message)
                 VALUES (:mobile, :destination, :message_preview, :gateway_response, :message_id, :success, :error_message)'
            );
            $stmt->execute([
                ':mobile' => $this->maskMobile($mobile),
                ':destination' => $destination,
                ':message_preview' => $this->maskOtpInMessage($message, $otp),
                ':gateway_response' => mb_substr($response, 0, 250),
                ':message_id' => $this->extractMessageId($response),
                ':success' => $success ? 1 : 0,
                ':error_message' => $error,
            ]);
        } catch (PDOException $exception) {
            log_app('SMS send log write failed', ['error' => $exception->getMessage()]);
        }
    }

    private function logOtpForLocalTesting(string $mobile, string $otp): void
    {
        $config = app_config();
        $enabled = (bool) ($config['otp']['log_for_testing'] ?? false);
        $debug = (bool) ($config['debug'] ?? false);
        $env = (string) ($config['app_env'] ?? 'production');

        if (!$enabled || !$debug || $env === 'production') {
            return;
        }

        log_app('[OTP_TESTING] OTP for local login testing only', [
            'mobile' => $this->maskMobile($mobile),
            'otp' => $otp,
            'note' => 'Disable OTP_LOG_FOR_TESTING in production',
        ]);
    }

    private function maskMobile(string $mobile): string
    {
        $digits = preg_replace('/\D/', '', $mobile) ?? '';
        if (strlen($digits) < 4) {
            return '****';
        }

        return substr($digits, 0, 2) . str_repeat('*', max(0, strlen($digits) - 4)) . substr($digits, -2);
    }

    private function maskOtpInMessage(string $message, string $otp): string
    {
        return str_replace($otp, '******', $message);
    }
}
