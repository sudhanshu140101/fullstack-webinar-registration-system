<?php

declare(strict_types=1);

final class RegistrationRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * @param array<string, string> $data
     */
    public function create(array $data, ?string $ip, ?string $userAgent): int
    {
        $sql = <<<'SQL'
            INSERT INTO registrations
                (name, company_name, seat, mobile, email, pincode, state, district, ip_address, user_agent)
            VALUES
                (:name, :company_name, :seat, :mobile, :email, :pincode, :state, :district, :ip_address, :user_agent)
        SQL;

        $companyName = trim((string) ($data['company_name'] ?? ''));
        $seat = strtolower(trim((string) ($data['seat'] ?? 'other')));
        if ($seat === '') {
            $seat = 'other';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':name' => $data['name'],
            ':company_name' => $companyName === '' ? null : $companyName,
            ':seat' => $seat,
            ':mobile' => $data['mobile'],
            ':email' => $data['email'],
            ':pincode' => $data['pincode'],
            ':state' => $data['state'],
            ':district' => $data['district'],
            ':ip_address' => $ip,
            ':user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 512) : null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updatePaymentStatus(int $id, ?string $status): bool
    {
        if (!is_valid_payment_status($status)) {
            return false;
        }

        $stmt = $this->db->prepare(
            'UPDATE registrations SET payment_status = :payment_status WHERE id = :id'
        );
        $stmt->execute([
            ':payment_status' => $status,
            ':id' => $id,
        ]);

        if ($stmt->rowCount() > 0) {
            return true;
        }

        $existing = $this->findById($id);

        return $existing !== null && ($existing['payment_status'] ?? null) === $status;
    }

    public function updateVerificationStatus(int $id, bool $verified): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE registrations SET is_verified = :is_verified WHERE id = :id'
        );
        $stmt->execute([
            ':is_verified' => $verified ? 1 : 0,
            ':id' => $id,
        ]);

        if ($stmt->rowCount() > 0) {
            return true;
        }

        $existing = $this->findById($id);

        return $existing !== null && is_registration_verified($existing) === $verified;
    }

    public function attachFile(int $registrationId, string $storedName, string $originalName, string $mime, int $size): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO registration_files (registration_id, stored_name, original_name, mime_type, file_size)
             VALUES (:registration_id, :stored_name, :original_name, :mime_type, :file_size)'
        );
        $stmt->execute([
            ':registration_id' => $registrationId,
            ':stored_name' => $storedName,
            ':original_name' => $originalName,
            ':mime_type' => $mime,
            ':file_size' => $size,
        ]);
    }

    public function countAll(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM registrations')->fetchColumn();
    }

    public function countToday(): int
    {
        $stmt = $this->db->query(
            "SELECT COUNT(*) FROM registrations WHERE DATE(created_at) = CURDATE()"
        );

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getLatest(int $limit = 10): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM registrations ORDER BY created_at DESC LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM registrations WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findLatestByMobileAndEmail(string $mobile, string $email): ?array
    {
        $mobile = preg_replace('/\D/', '', $mobile) ?? '';
        $email = strtolower(trim($email));

        $stmt = $this->db->prepare(
            'SELECT * FROM registrations
             WHERE mobile = :mobile AND LOWER(email) = :email
             ORDER BY created_at DESC
             LIMIT 1'
        );
        $stmt->execute([
            ':mobile' => $mobile,
            ':email' => $email,
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findLatestByMobile(string $mobile): ?array
    {
        $mobile = preg_replace('/\D/', '', $mobile) ?? '';
        if ($mobile === '') {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT * FROM registrations
             WHERE mobile = :mobile
             ORDER BY created_at DESC
             LIMIT 1'
        );
        $stmt->execute([':mobile' => $mobile]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findLatestVerifiedByMobile(string $mobile): ?array
    {
        $mobile = preg_replace('/\D/', '', $mobile) ?? '';
        if ($mobile === '') {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT * FROM registrations
             WHERE mobile = :mobile AND is_verified = 1
             ORDER BY created_at DESC
             LIMIT 1'
        );
        $stmt->execute([':mobile' => $mobile]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getFilesForRegistration(int $registrationId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM registration_files WHERE registration_id = :id ORDER BY created_at ASC'
        );
        $stmt->execute([':id' => $registrationId]);

        return $stmt->fetchAll();
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public function search(
        string $query,
        ?string $seat,
        ?string $paymentStatus,
        int $page,
        int $perPage
    ): array {
        $where = ['1=1'];
        $params = [];

        if ($query !== '') {
            $like = '%' . $query . '%';
            $where[] = '(name LIKE :q_name OR company_name LIKE :q_company OR email LIKE :q_email OR mobile LIKE :q_mobile OR pincode LIKE :q_pincode OR state LIKE :q_state OR district LIKE :q_district)';
            $params[':q_name'] = $like;
            $params[':q_company'] = $like;
            $params[':q_email'] = $like;
            $params[':q_mobile'] = $like;
            $params[':q_pincode'] = $like;
            $params[':q_state'] = $like;
            $params[':q_district'] = $like;
        }

        if ($seat !== null && $seat !== '') {
            $where[] = 'seat = :seat';
            $params[':seat'] = $seat;
        }

        if ($paymentStatus !== null && $paymentStatus !== '') {
            $where[] = 'payment_status = :payment_status';
            $params[':payment_status'] = $paymentStatus;
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM registrations WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $perPage = max(1, min(100, $perPage));
        $sql = "SELECT * FROM registrations WHERE {$whereSql} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return [
            'rows' => $stmt->fetchAll(),
            'total' => $total,
        ];
    }

    public function delete(int $id): bool
    {
        $files = $this->getFilesForRegistration($id);
        $uploadPath = app_config()['upload']['path'];

        foreach ($files as $file) {
            $path = $uploadPath . DIRECTORY_SEPARATOR . $file['stored_name'];
            if (is_file($path)) {
                @unlink($path);
            }
        }

        $stmt = $this->db->prepare('DELETE FROM registrations WHERE id = :id');
        $stmt->execute([':id' => $id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function exportAll(?string $seat, ?string $paymentStatus): array
    {
        $where = ['1=1'];
        $params = [];

        if ($seat !== null && $seat !== '') {
            $where[] = 'seat = :seat';
            $params[':seat'] = $seat;
        }

        if ($paymentStatus !== null && $paymentStatus !== '') {
            $where[] = 'payment_status = :payment_status';
            $params[':payment_status'] = $paymentStatus;
        }

        $whereSql = implode(' AND ', $where);
        $stmt = $this->db->prepare(
            "SELECT * FROM registrations WHERE {$whereSql} ORDER BY created_at DESC"
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * @return list<array{name: string, registered_ago: string}>
     */
    public function getRecentRegistrantsPublic(int $limit = 10): array
    {
        $limit = max(1, min(10, $limit));

        $stmt = $this->db->prepare(
            'SELECT name, created_at
             FROM registrations
             ORDER BY created_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        if ($rows === false || $rows === []) {
            return [];
        }

        $registrants = [];
        foreach ($rows as $row) {
            $name = format_public_registrant_name((string) ($row['name'] ?? ''));
            $createdAt = (string) ($row['created_at'] ?? '');
            if ($name === 'Someone' && $createdAt === '') {
                continue;
            }

            $registrants[] = [
                'name' => $name,
                'registered_ago' => registration_time_ago_label($createdAt),
            ];
        }

        return $registrants;
    }

    public function countSubmissionsSince(string $ip, int $hours = 1): int
    {
        $hours = max(1, min(24, $hours));
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM registrations
             WHERE ip_address = :ip AND created_at >= (NOW() - INTERVAL {$hours} HOUR)"
        );
        $stmt->bindValue(':ip', $ip);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array<string, mixed>
     */
    public function getRegistrationSettings(): array
    {
        $stmt = $this->db->query('SELECT * FROM registration_settings WHERE id = 1 LIMIT 1');
        $row = $stmt->fetch();

        if (!is_array($row)) {
            return [
                'amount_paise' => 0,
                'currency' => 'INR',
                'fee_label' => 'Registration Fee',
                'payment_enabled' => 0,
                'payment_url' => '',
                'workshop_url' => '',
            ];
        }

        return [
            'amount_paise' => (int) ($row['amount_paise'] ?? 0),
            'currency' => (string) ($row['currency'] ?? 'INR'),
            'fee_label' => (string) ($row['fee_label'] ?? 'Registration Fee'),
            'payment_enabled' => (int) ($row['payment_enabled'] ?? 0),
            'payment_url' => (string) ($row['payment_url'] ?? ''),
            'workshop_url' => (string) ($row['workshop_url'] ?? ''),
        ];
    }

    /**
     * @param array{amount_paise: int, currency: string, fee_label: string, payment_enabled: bool|int, payment_url?: string, workshop_url?: string} $data
     */
    public function updateRegistrationSettings(array $data): void
    {
        $paymentUrl = sanitize_safe_external_url(
            Security::sanitizeString((string) ($data['payment_url'] ?? ''), 500)
        );
        $workshopUrl = sanitize_safe_external_url(
            Security::sanitizeString((string) ($data['workshop_url'] ?? ''), 500)
        );

        $stmt = $this->db->prepare(
            'UPDATE registration_settings
             SET amount_paise = :amount_paise,
                 currency = :currency,
                 fee_label = :fee_label,
                 payment_enabled = :payment_enabled,
                 payment_url = :payment_url,
                 workshop_url = :workshop_url
             WHERE id = 1'
        );
        $stmt->execute([
            ':amount_paise' => max(0, (int) ($data['amount_paise'] ?? 0)),
            ':currency' => strtoupper(substr((string) ($data['currency'] ?? 'INR'), 0, 3)),
            ':fee_label' => Security::sanitizeString((string) ($data['fee_label'] ?? ''), 120),
            ':payment_enabled' => !empty($data['payment_enabled']) ? 1 : 0,
            ':payment_url' => $paymentUrl,
            ':workshop_url' => $workshopUrl,
        ]);
    }
}
