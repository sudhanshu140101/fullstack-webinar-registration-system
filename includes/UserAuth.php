<?php

declare(strict_types=1);

final class UserAuth
{
    private const ATTEMPT_PREFIX = 'user:';

    private ?PDO $db = null;

    public function __construct(
        private readonly OtpService $otpService = new OtpService(),
    ) {
    }

    public function isLoggedIn(): bool
    {
        return !empty($_SESSION['user_registration_id']) && !empty($_SESSION['user_mobile']);
    }

    public function requireLogin(): void
    {
        if (!$this->isLoggedIn()) {
            redirect('login.php');
        }
    }

    public function requireVerifiedAccess(): void
    {
        $this->requireLogin();

        $registrationId = $this->registrationId();
        if ($registrationId === null) {
            $this->denyDashboardAccess('Please sign in to access your dashboard.');
        }

        $repo = new RegistrationRepository();
        $registration = $repo->findById($registrationId);

        if ($registration === null || !is_registration_verified($registration)) {
            $this->denyDashboardAccess('Your registration is not verified yet. Please wait for admin approval.');
        }
    }

    private function denyDashboardAccess(string $message): never
    {
        $_SESSION['login_notice'] = $message;
        $this->logout();
        redirect('login.php');
    }

    public function registrationId(): ?int
    {
        $id = $_SESSION['user_registration_id'] ?? null;

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * @return array{success: bool, message: string, retry_after?: int}
     */
    public function requestLoginOtp(string $mobile): array
    {
        try {
            return $this->otpService->sendLoginOtp($mobile, client_ip());
        } catch (PDOException) {
            log_app('User OTP request database error');

            return [
                'success' => false,
                'message' => 'Service temporarily unavailable. Please try again shortly.',
            ];
        }
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function verifyLoginOtp(string $mobile, string $otp): array
    {
        try {
            $result = $this->otpService->verifyLoginOtp($mobile, $otp, client_ip());

            if (!$result['success']) {
                return [
                    'success' => false,
                    'message' => $result['message'],
                ];
            }

            $registration = $result['registration'] ?? null;
            if (!is_array($registration)) {
                return [
                    'success' => false,
                    'message' => 'Unable to complete login. Please try again.',
                ];
            }

            $this->establishSession($registration);

            return [
                'success' => true,
                'message' => 'Login successful.',
            ];
        } catch (PDOException) {
            log_app('User OTP verify database error');

            return [
                'success' => false,
                'message' => 'Service temporarily unavailable. Please try again shortly.',
            ];
        }
    }

    /**
     * @param array<string, mixed> $registration
     */
    private function establishSession(array $registration): void
    {
        session_regenerate_id(true);

        $_SESSION['user_registration_id'] = (int) $registration['id'];
        $_SESSION['user_mobile'] = (string) $registration['mobile'];
        $_SESSION['user_email'] = (string) $registration['email'];
        $_SESSION['user_name'] = (string) $registration['name'];
        $_SESSION['_last_activity'] = time();
    }

    public function logout(): void
    {
        unset(
            $_SESSION['user_registration_id'],
            $_SESSION['user_mobile'],
            $_SESSION['user_email'],
            $_SESSION['user_name']
        );
    }
}
