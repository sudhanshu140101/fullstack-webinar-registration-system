<?php

declare(strict_types=1);

final class Auth
{
    private ?PDO $db = null;

    public function isLoggedIn(): bool
    {
        return !empty($_SESSION['admin_id']) && !empty($_SESSION['admin_username']);
    }

    public function requireLogin(): void
    {
        if (!$this->isLoggedIn()) {
            redirect('login.php');
        }
    }

    public function adminAccountExists(): bool
    {
        try {
            return $this->countAdmins() > 0;
        } catch (PDOException) {
            return false;
        }
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function attemptLogin(string $username, string $password): array
    {
        $username = trim($username);

        try {
            if ($this->countAdmins() === 0) {
                return [
                    'success' => false,
                    'message' => 'No admin account exists. Run: php database/seed_admin.php from the project root.',
                ];
            }

            return $this->performLogin($username, $password);
        } catch (PDOException) {
            log_app('Admin login database error');

            return [
                'success' => false,
                'message' => 'Database is unavailable. Check .env settings and ensure MySQL is running.',
            ];
        }
    }

    /**
     * @return array{success: bool, message: string}
     */
    private function performLogin(string $username, string $password): array
    {
        $config = app_config();
        $ip = client_ip();
        $maxAttempts = (int) $config['security']['login_max_attempts'];
        $lockoutMinutes = (int) $config['security']['login_lockout_minutes'];

        if ($this->isLockedOut($ip, $username, $maxAttempts, $lockoutMinutes)) {
            return [
                'success' => false,
                'message' => sprintf(
                    'Too many failed attempts. Please wait %d minutes or run: php database/unlock_login.php',
                    $lockoutMinutes
                ),
            ];
        }

        $stmt = $this->db()->prepare(
            'SELECT id, username, password_hash, full_name, is_active
             FROM admins WHERE LOWER(username) = LOWER(:username) LIMIT 1'
        );
        $stmt->execute([':username' => $username]);
        $admin = $stmt->fetch();

        if (!$admin || !(int) $admin['is_active']) {
            $this->recordAttempt($ip, $username, false);

            return [
                'success' => false,
                'message' => 'Invalid username or password.',
            ];
        }

        if (!password_verify($password, (string) $admin['password_hash'])) {
            $this->recordAttempt($ip, $username, false);

            return [
                'success' => false,
                'message' => 'Invalid username or password.',
            ];
        }

        $this->clearLoginAttempts($ip, $username);
        $this->recordAttempt($ip, $username, true);
        session_regenerate_id(true);

        $_SESSION['admin_id'] = (int) $admin['id'];
        $_SESSION['admin_username'] = (string) $admin['username'];
        $_SESSION['admin_name'] = (string) $admin['full_name'];
        $_SESSION['_last_activity'] = time();
        $_SESSION['_created'] = time();

        $update = $this->db()->prepare('UPDATE admins SET last_login_at = NOW() WHERE id = :id');
        $update->execute([':id' => $admin['id']]);

        return ['success' => true, 'message' => 'Login successful.'];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function createAdmin(string $username, string $email, string $password, string $fullName): array
    {
        $username = trim($username);
        $email = strtolower(trim($email));
        $fullName = Security::sanitizeString($fullName, 120);

        if (strlen($username) < 3) {
            return ['success' => false, 'message' => 'Username must be at least 3 characters.'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Please enter a valid email address.'];
        }

        if (strlen($password) < 10) {
            return ['success' => false, 'message' => 'Password must be at least 10 characters.'];
        }

        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $this->db()->prepare(
                'INSERT INTO admins (username, email, password_hash, full_name, is_active)
                 VALUES (:username, :email, :password_hash, :full_name, 1)'
            );
            $stmt->execute([
                ':username' => $username,
                ':email' => $email,
                ':password_hash' => $hash,
                ':full_name' => $fullName,
            ]);

            $this->db()->exec('TRUNCATE TABLE login_attempts');
            install_mark_complete();

            return ['success' => true, 'message' => 'Admin account created successfully.'];
        } catch (PDOException $exception) {
            if (str_contains($exception->getMessage(), 'Duplicate')) {
                return ['success' => false, 'message' => 'Username or email already exists.'];
            }

            log_app('Create admin failed', ['error' => $exception->getMessage()]);

            return ['success' => false, 'message' => 'Could not create admin account.'];
        }
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                (bool) $params['secure'],
                (bool) $params['httponly']
            );
        }
        session_destroy();
    }

    private function db(): PDO
    {
        if ($this->db === null) {
            $this->db = Database::getConnection();
        }

        return $this->db;
    }

    private function countAdmins(): int
    {
        return (int) $this->db()->query('SELECT COUNT(*) FROM admins WHERE is_active = 1')->fetchColumn();
    }

    private function isLockedOut(string $ip, string $username, int $maxAttempts, int $lockoutMinutes): bool
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

        if ($username === '') {
            return false;
        }

        $stmt = $this->db()->prepare(
            "SELECT COUNT(*) FROM login_attempts
             WHERE success = 0
               AND attempted_at >= (NOW() - INTERVAL :minutes MINUTE)
               AND username = :username"
        );
        $stmt->execute([
            ':minutes' => $lockoutMinutes,
            ':username' => $username,
        ]);

        return (int) $stmt->fetchColumn() >= $maxAttempts;
    }

    private function clearLoginAttempts(string $ip, string $username): void
    {
        $stmt = $this->db()->prepare(
            'DELETE FROM login_attempts WHERE ip_address = :ip OR username = :username'
        );
        $stmt->execute([':ip' => $ip, ':username' => $username]);
    }

    private function recordAttempt(string $ip, string $username, bool $success): void
    {
        $stmt = $this->db()->prepare(
            'INSERT INTO login_attempts (ip_address, username, success) VALUES (:ip, :username, :success)'
        );
        $stmt->execute([
            ':ip' => $ip,
            ':username' => $username,
            ':success' => $success ? 1 : 0,
        ]);
    }
}
