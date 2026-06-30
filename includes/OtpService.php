<?php

declare(strict_types=1);

final class OtpService
{
    private const LOGIN_OTP_TABLE = 'user_login_otps';
    private const REGISTRATION_OTP_TABLE = 'registration_mobile_otps';

    private ?PDO $db = null;

    public function __construct(
        private readonly SmsGateway $smsGateway = new SmsGateway(),
        private readonly RegistrationRepository $registrations = new RegistrationRepository(),
    ) {
    }

    /**
     * @return array{success: bool, message: string, retry_after?: int}
     */
    public function sendLoginOtp(string $mobile, string $ip): array
    {
        $mobile = $this->normalizeMobile($mobile);
        if ($mobile === null) {
            return ['success' => false, 'message' => 'Please enter a valid 10-digit mobile number.'];
        }

        $config = app_config();
        $otpConfig = $config['otp'] ?? [];
        $attemptKey = 'user:' . $mobile;
        $maxAttempts = (int) ($config['security']['login_max_attempts'] ?? 20);
        $lockoutMinutes = (int) ($config['security']['login_lockout_minutes'] ?? 30);

        if ($this->isLockedOut($ip, $attemptKey, $maxAttempts, $lockoutMinutes)) {
            return [
                'success' => false,
                'message' => sprintf(
                    'Too many failed attempts. Please try again in %d minutes.',
                    $lockoutMinutes
                ),
            ];
        }

        $registration = $this->registrations->findLatestByMobile($mobile);
        if ($registration === null) {
            $this->recordAttempt($ip, $attemptKey, false);

            return [
                'success' => false,
                'message' => 'No registration found for this mobile number.',
            ];
        }

        if (!is_registration_verified($registration)) {
            return [
                'success' => false,
                'message' => 'Your registration is pending admin approval. OTP will be sent once your registration is verified.',
            ];
        }

        $cooldownSeconds = max(60, (int) ($otpConfig['resend_cooldown_seconds'] ?? 120));
        $retryAfter = $this->secondsUntilResendAllowed($mobile, $cooldownSeconds, self::LOGIN_OTP_TABLE);
        if ($retryAfter > 0) {
            return [
                'success' => false,
                'message' => $this->formatResendWaitMessage($retryAfter),
                'retry_after' => $retryAfter,
            ];
        }

        $maxSendPerHour = max(1, (int) ($otpConfig['max_send_per_hour'] ?? 5));
        if ($this->countSendsSince($mobile, 60, self::LOGIN_OTP_TABLE) >= $maxSendPerHour) {
            return [
                'success' => false,
                'message' => 'Too many OTP requests. Please try again after an hour.',
            ];
        }

        $otp = $this->generateOtp((int) ($otpConfig['length'] ?? 6));
        $validityMinutes = max(1, (int) ($otpConfig['validity_minutes'] ?? 5));
        $otpHash = $this->hashOtp($mobile, $otp);

        $this->purgeExpiredOtps(self::LOGIN_OTP_TABLE);
        $this->invalidateOtpsForMobile($mobile, self::LOGIN_OTP_TABLE);

        $smsResult = $this->smsGateway->sendOtp($mobile, $otp);
        if (!$smsResult['success']) {
            return [
                'success' => false,
                'message' => $smsResult['message'],
            ];
        }

        $stmt = $this->db()->prepare(
            'INSERT INTO user_login_otps (mobile, otp_hash, expires_at)
             VALUES (:mobile, :otp_hash, DATE_ADD(NOW(), INTERVAL :minutes MINUTE))'
        );
        $stmt->execute([
            ':mobile' => $mobile,
            ':otp_hash' => $otpHash,
            ':minutes' => $validityMinutes,
        ]);

        log_app('Login OTP sent', [
            'mobile' => substr($mobile, 0, 2) . '******' . substr($mobile, -2),
            'ip' => $ip,
            'sms_message_id' => $smsResult['message_id'] ?? null,
        ]);

        return [
            'success' => true,
            'message' => 'OTP sent to your mobile number.',
            'retry_after' => $cooldownSeconds,
        ];
    }

    /**
     * @return array{success: bool, message: string, registration?: array<string, mixed>}
     */
    public function verifyLoginOtp(string $mobile, string $otp, string $ip): array
    {
        $mobile = $this->normalizeMobile($mobile);
        if ($mobile === null) {
            return ['success' => false, 'message' => 'Please enter a valid 10-digit mobile number.'];
        }

        $otp = preg_replace('/\D/', '', $otp) ?? '';
        $otpLength = (int) (app_config()['otp']['length'] ?? 6);
        if (!preg_match('/^\d{' . $otpLength . '}$/', $otp)) {
            return [
                'success' => false,
                'message' => sprintf('Please enter the %d-digit OTP.', $otpLength),
            ];
        }

        $config = app_config();
        $attemptKey = 'user:' . $mobile;
        $maxAttempts = (int) ($config['security']['login_max_attempts'] ?? 20);
        $lockoutMinutes = (int) ($config['security']['login_lockout_minutes'] ?? 30);

        if ($this->isLockedOut($ip, $attemptKey, $maxAttempts, $lockoutMinutes)) {
            return [
                'success' => false,
                'message' => sprintf(
                    'Too many failed attempts. Please try again in %d minutes.',
                    $lockoutMinutes
                ),
            ];
        }

        $registration = $this->registrations->findLatestVerifiedByMobile($mobile);
        if ($registration === null) {
            $this->recordAttempt($ip, $attemptKey, false);

            return [
                'success' => false,
                'message' => 'No verified registration found for this mobile number.',
            ];
        }

        $otpRow = $this->findActiveOtp($mobile, self::LOGIN_OTP_TABLE);
        if ($otpRow === null) {
            $this->recordAttempt($ip, $attemptKey, false);

            return [
                'success' => false,
                'message' => 'OTP expired or not found. Please request a new OTP.',
            ];
        }

        $maxVerifyAttempts = max(1, (int) ($config['otp']['max_verify_attempts'] ?? 5));
        if ((int) $otpRow['verify_attempts'] >= $maxVerifyAttempts) {
            $this->invalidateOtpById((int) $otpRow['id'], self::LOGIN_OTP_TABLE);
            $this->recordAttempt($ip, $attemptKey, false);

            return [
                'success' => false,
                'message' => 'Too many incorrect OTP attempts. Please request a new OTP.',
            ];
        }

        $expectedHash = (string) $otpRow['otp_hash'];
        $providedHash = $this->hashOtp($mobile, $otp);

        if (!hash_equals($expectedHash, $providedHash)) {
            $this->incrementVerifyAttempts((int) $otpRow['id'], self::LOGIN_OTP_TABLE);
            $this->recordAttempt($ip, $attemptKey, false);

            return [
                'success' => false,
                'message' => 'Invalid OTP. Please check and try again.',
            ];
        }

        $this->invalidateOtpById((int) $otpRow['id'], self::LOGIN_OTP_TABLE);
        $this->clearLoginAttempts($ip, $attemptKey);
        $this->recordAttempt($ip, $attemptKey, true);

        return [
            'success' => true,
            'message' => 'OTP verified successfully.',
            'registration' => $registration,
        ];
    }

    /**
     * @return array{success: bool, message: string, retry_after?: int}
     */
    public function sendRegistrationOtp(string $mobile, string $ip): array
    {
        $mobile = $this->normalizeMobile($mobile);
        if ($mobile === null) {
            return ['success' => false, 'message' => 'Please enter a valid 10-digit mobile number.'];
        }

        $config = app_config();
        $otpConfig = $config['otp'] ?? [];
        $attemptKey = 'reg:' . $mobile;
        $maxAttempts = (int) ($config['security']['login_max_attempts'] ?? 20);
        $lockoutMinutes = (int) ($config['security']['login_lockout_minutes'] ?? 30);

        if ($this->isLockedOut($ip, $attemptKey, $maxAttempts, $lockoutMinutes)) {
            return [
                'success' => false,
                'message' => sprintf(
                    'Too many failed attempts. Please try again in %d minutes.',
                    $lockoutMinutes
                ),
            ];
        }

        $cooldownSeconds = max(60, (int) ($otpConfig['resend_cooldown_seconds'] ?? 120));
        $retryAfter = $this->secondsUntilResendAllowed($mobile, $cooldownSeconds, self::REGISTRATION_OTP_TABLE);
        if ($retryAfter > 0) {
            return [
                'success' => false,
                'message' => $this->formatResendWaitMessage($retryAfter),
                'retry_after' => $retryAfter,
            ];
        }

        $maxSendPerHour = max(1, (int) ($otpConfig['max_send_per_hour'] ?? 5));
        if ($this->countSendsSince($mobile, 60, self::REGISTRATION_OTP_TABLE) >= $maxSendPerHour) {
            return [
                'success' => false,
                'message' => 'Too many OTP requests. Please try again after an hour.',
            ];
        }

        $otp = $this->generateOtp((int) ($otpConfig['length'] ?? 6));
        $validityMinutes = max(1, (int) ($otpConfig['validity_minutes'] ?? 5));
        $otpHash = $this->hashOtp($mobile, $otp);

        $this->purgeExpiredOtps(self::REGISTRATION_OTP_TABLE);
        $this->invalidateOtpsForMobile($mobile, self::REGISTRATION_OTP_TABLE);
        $this->clearRegistrationMobileVerification($mobile);

        $smsResult = $this->smsGateway->sendOtp($mobile, $otp);
        if (!$smsResult['success']) {
            return [
                'success' => false,
                'message' => $smsResult['message'],
            ];
        }

        $table = self::REGISTRATION_OTP_TABLE;
        $stmt = $this->db()->prepare(
            "INSERT INTO {$table} (mobile, otp_hash, expires_at)
             VALUES (:mobile, :otp_hash, DATE_ADD(NOW(), INTERVAL :minutes MINUTE))"
        );
        $stmt->execute([
            ':mobile' => $mobile,
            ':otp_hash' => $otpHash,
            ':minutes' => $validityMinutes,
        ]);

        log_app('Registration OTP sent', [
            'mobile' => substr($mobile, 0, 2) . '******' . substr($mobile, -2),
            'ip' => $ip,
            'sms_message_id' => $smsResult['message_id'] ?? null,
        ]);

        return [
            'success' => true,
            'message' => 'OTP sent to your mobile number.',
            'retry_after' => $cooldownSeconds,
        ];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function verifyRegistrationOtp(string $mobile, string $otp, string $ip): array
    {
        $mobile = $this->normalizeMobile($mobile);
        if ($mobile === null) {
            return ['success' => false, 'message' => 'Please enter a valid 10-digit mobile number.'];
        }

        $otp = preg_replace('/\D/', '', $otp) ?? '';
        $otpLength = (int) (app_config()['otp']['length'] ?? 6);
        if (!preg_match('/^\d{' . $otpLength . '}$/', $otp)) {
            return [
                'success' => false,
                'message' => sprintf('Please enter the %d-digit OTP.', $otpLength),
            ];
        }

        $config = app_config();
        $attemptKey = 'reg:' . $mobile;
        $maxAttempts = (int) ($config['security']['login_max_attempts'] ?? 20);
        $lockoutMinutes = (int) ($config['security']['login_lockout_minutes'] ?? 30);

        if ($this->isLockedOut($ip, $attemptKey, $maxAttempts, $lockoutMinutes)) {
            return [
                'success' => false,
                'message' => sprintf(
                    'Too many failed attempts. Please try again in %d minutes.',
                    $lockoutMinutes
                ),
            ];
        }

        $otpRow = $this->findActiveOtp($mobile, self::REGISTRATION_OTP_TABLE);
        if ($otpRow === null) {
            $this->recordAttempt($ip, $attemptKey, false);

            return [
                'success' => false,
                'message' => 'OTP expired or not found. Please request a new OTP.',
            ];
        }

        $maxVerifyAttempts = max(1, (int) ($config['otp']['max_verify_attempts'] ?? 5));
        if ((int) $otpRow['verify_attempts'] >= $maxVerifyAttempts) {
            $this->invalidateOtpById((int) $otpRow['id'], self::REGISTRATION_OTP_TABLE);
            $this->recordAttempt($ip, $attemptKey, false);

            return [
                'success' => false,
                'message' => 'Too many incorrect OTP attempts. Please request a new OTP.',
            ];
        }

        $expectedHash = (string) $otpRow['otp_hash'];
        $providedHash = $this->hashOtp($mobile, $otp);

        if (!hash_equals($expectedHash, $providedHash)) {
            $this->incrementVerifyAttempts((int) $otpRow['id'], self::REGISTRATION_OTP_TABLE);
            $this->recordAttempt($ip, $attemptKey, false);

            return [
                'success' => false,
                'message' => 'Invalid OTP. Please check and try again.',
            ];
        }

        $this->invalidateOtpById((int) $otpRow['id'], self::REGISTRATION_OTP_TABLE);
        $this->clearLoginAttempts($ip, $attemptKey);
        $this->recordAttempt($ip, $attemptKey, true);
        $this->markRegistrationMobileVerified($mobile);

        return [
            'success' => true,
            'message' => 'Mobile number verified successfully.',
        ];
    }

    public function isRegistrationMobileVerified(string $mobile): bool
    {
        $mobile = $this->normalizeMobile($mobile);
        if ($mobile === null) {
            return false;
        }

        if (!isset($_SESSION['registration_mobile_verified'][$mobile])) {
            return false;
        }

        $entry = $_SESSION['registration_mobile_verified'][$mobile];
        if (!is_array($entry)) {
            return false;
        }

        $ttlMinutes = max(5, (int) (app_config()['otp']['registration_verified_ttl_minutes'] ?? 30));
        $verifiedAt = (int) ($entry['verified_at'] ?? 0);
        if ($verifiedAt <= 0 || (time() - $verifiedAt) > ($ttlMinutes * 60)) {
            unset($_SESSION['registration_mobile_verified'][$mobile]);

            return false;
        }

        $proof = (string) ($entry['proof'] ?? '');

        return $proof !== '' && hash_equals($this->registrationVerificationProof($mobile), $proof);
    }

    public function clearRegistrationMobileVerification(string $mobile): void
    {
        $mobile = $this->normalizeMobile($mobile);
        if ($mobile === null) {
            return;
        }

        unset($_SESSION['registration_mobile_verified'][$mobile]);
    }

    private function markRegistrationMobileVerified(string $mobile): void
    {
        if (!isset($_SESSION['registration_mobile_verified']) || !is_array($_SESSION['registration_mobile_verified'])) {
            $_SESSION['registration_mobile_verified'] = [];
        }

        $_SESSION['registration_mobile_verified'][$mobile] = [
            'verified_at' => time(),
            'proof' => $this->registrationVerificationProof($mobile),
        ];
    }

    private function registrationVerificationProof(string $mobile): string
    {
        $secret = (string) (app_config()['otp']['hmac_secret'] ?? '');

        return hash_hmac('sha256', 'registration:' . $mobile, $secret);
    }

    private function normalizeMobile(string $mobile): ?string
    {
        $mobile = preg_replace('/\D/', '', $mobile) ?? '';

        if (strlen($mobile) === 12 && str_starts_with($mobile, '91')) {
            $mobile = substr($mobile, 2);
        }

        if (!preg_match('/^[6-9][0-9]{9}$/', $mobile)) {
            return null;
        }

        return $mobile;
    }

    private function generateOtp(int $length): string
    {
        $length = max(4, min(8, $length));
        $max = (10 ** $length) - 1;
        $min = 10 ** ($length - 1);

        return str_pad((string) random_int($min, $max), $length, '0', STR_PAD_LEFT);
    }

    private function hashOtp(string $mobile, string $otp): string
    {
        $secret = (string) (app_config()['otp']['hmac_secret'] ?? '');

        return hash_hmac('sha256', $mobile . ':' . $otp, $secret);
    }

    private function db(): PDO
    {
        if ($this->db === null) {
            $this->db = Database::getConnection();
        }

        return $this->db;
    }

    private function purgeExpiredOtps(string $table): void
    {
        $table = $this->assertOtpTable($table);
        $this->db()->exec("DELETE FROM {$table} WHERE expires_at < NOW()");
    }

    private function invalidateOtpsForMobile(string $mobile, string $table): void
    {
        $table = $this->assertOtpTable($table);
        $stmt = $this->db()->prepare("DELETE FROM {$table} WHERE mobile = :mobile");
        $stmt->execute([':mobile' => $mobile]);
    }

    private function invalidateOtpById(int $id, string $table): void
    {
        $table = $this->assertOtpTable($table);
        $stmt = $this->db()->prepare("DELETE FROM {$table} WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findActiveOtp(string $mobile, string $table): ?array
    {
        $table = $this->assertOtpTable($table);
        $stmt = $this->db()->prepare(
            "SELECT * FROM {$table}
             WHERE mobile = :mobile AND expires_at >= NOW()
             ORDER BY created_at DESC
             LIMIT 1"
        );
        $stmt->execute([':mobile' => $mobile]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function incrementVerifyAttempts(int $id, string $table): void
    {
        $table = $this->assertOtpTable($table);
        $stmt = $this->db()->prepare(
            "UPDATE {$table} SET verify_attempts = verify_attempts + 1 WHERE id = :id"
        );
        $stmt->execute([':id' => $id]);
    }

    private function secondsUntilResendAllowed(string $mobile, int $cooldownSeconds, string $table): int
    {
        $table = $this->assertOtpTable($table);
        $stmt = $this->db()->prepare(
            "SELECT GREATEST(
                0,
                :cooldown - TIMESTAMPDIFF(SECOND, created_at, NOW())
             ) AS wait_seconds
             FROM {$table}
             WHERE mobile = :mobile
             ORDER BY created_at DESC
             LIMIT 1"
        );
        $stmt->execute([
            ':mobile' => $mobile,
            ':cooldown' => $cooldownSeconds,
        ]);
        $waitSeconds = $stmt->fetchColumn();

        if (!is_numeric($waitSeconds)) {
            return 0;
        }

        return min($cooldownSeconds, max(0, (int) $waitSeconds));
    }

    private function formatResendWaitMessage(int $seconds): string
    {
        $seconds = max(1, $seconds);
        $minutes = intdiv($seconds, 60);
        $remainingSeconds = $seconds % 60;

        if ($minutes > 0 && $remainingSeconds > 0) {
            return sprintf(
                'Please wait %d min %d sec before requesting another OTP.',
                $minutes,
                $remainingSeconds
            );
        }

        if ($minutes > 0) {
            return sprintf(
                'Please wait %d minute%s before requesting another OTP.',
                $minutes,
                $minutes === 1 ? '' : 's'
            );
        }

        return sprintf('Please wait %d seconds before requesting another OTP.', $seconds);
    }

    private function countSendsSince(string $mobile, int $minutes, string $table): int
    {
        $table = $this->assertOtpTable($table);
        $minutes = max(1, min(1440, $minutes));
        $stmt = $this->db()->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE mobile = :mobile
               AND created_at >= (NOW() - INTERVAL {$minutes} MINUTE)"
        );
        $stmt->execute([':mobile' => $mobile]);

        return (int) $stmt->fetchColumn();
    }

    private function assertOtpTable(string $table): string
    {
        if (!in_array($table, [self::LOGIN_OTP_TABLE, self::REGISTRATION_OTP_TABLE], true)) {
            throw new InvalidArgumentException('Invalid OTP table.');
        }

        return $table;
    }

    private function isLockedOut(string $ip, string $attemptKey, int $maxAttempts, int $lockoutMinutes): bool
    {
        $stmt = $this->db()->prepare(
            "SELECT COUNT(*) FROM login_attempts
             WHERE success = 0
               AND attempted_at >= (NOW() - INTERVAL :minutes MINUTE)
               AND ip_address = :ip"
        );
        $stmt->execute([
            ':minutes' => $lockoutMinutes,
            ':ip' => $ip,
        ]);

        if ((int) $stmt->fetchColumn() >= $maxAttempts) {
            return true;
        }

        $stmt = $this->db()->prepare(
            "SELECT COUNT(*) FROM login_attempts
             WHERE success = 0
               AND attempted_at >= (NOW() - INTERVAL :minutes MINUTE)
               AND username = :username"
        );
        $stmt->execute([
            ':minutes' => $lockoutMinutes,
            ':username' => $attemptKey,
        ]);

        return (int) $stmt->fetchColumn() >= $maxAttempts;
    }

    private function clearLoginAttempts(string $ip, string $attemptKey): void
    {
        $stmt = $this->db()->prepare(
            'DELETE FROM login_attempts WHERE ip_address = :ip OR username = :username'
        );
        $stmt->execute([':ip' => $ip, ':username' => $attemptKey]);
    }

    private function recordAttempt(string $ip, string $attemptKey, bool $success): void
    {
        $stmt = $this->db()->prepare(
            'INSERT INTO login_attempts (ip_address, username, success) VALUES (:ip, :username, :success)'
        );
        $stmt->execute([
            ':ip' => $ip,
            ':username' => $attemptKey,
            ':success' => $success ? 1 : 0,
        ]);
    }
}
