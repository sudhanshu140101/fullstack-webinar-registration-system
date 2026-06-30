<?php

declare(strict_types=1);

final class PolicyAdvocacyRepository
{
    private const SECTION_ID = 1;
    private const MAX_CARDS = 12;

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    public static function maxCards(): int
    {
        return self::MAX_CARDS;
    }

    /** @return array<string, mixed>|null */
    public function getSection(): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, section_title, section_message, is_active, updated_at
             FROM policy_advocacy WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => self::SECTION_ID]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    /** @return list<array<string, mixed>> */
    public function listCards(bool $activeOnly = false): array
    {
        $sql = 'SELECT id, message, image_path, sort_order, is_active, created_at, updated_at
                FROM policy_advocacy_cards';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1 AND image_path IS NOT NULL AND image_path != \'\'';
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';

        $stmt = $this->db->query($sql);

        return $stmt !== false ? $stmt->fetchAll() : [];
    }

    /**
     * @return array{
     *   section_title: ?string,
     *   section_message: ?string,
     *   cards: list<array{id: int, message: ?string, image_url: string}>
     * }|null
     */
    public function getPublic(): ?array
    {
        $section = $this->getSection();
        if ($section === null || !(bool) $section['is_active']) {
            return null;
        }

        $cards = [];
        foreach ($this->listCards(true) as $card) {
            $imagePath = trim((string) ($card['image_path'] ?? ''));
            if ($imagePath === '') {
                continue;
            }

            $message = trim((string) ($card['message'] ?? ''));

            $cards[] = [
                'id' => (int) $card['id'],
                'message' => $message !== '' ? $message : null,
                'image_url' => $imagePath,
            ];
        }

        if ($cards === []) {
            return null;
        }

        $sectionTitle = trim((string) ($section['section_title'] ?? ''));
        $sectionMessage = trim((string) ($section['section_message'] ?? ''));

        return [
            'section_title' => $sectionTitle !== '' ? $sectionTitle : null,
            'section_message' => $sectionMessage !== '' ? $sectionMessage : null,
            'cards' => $cards,
        ];
    }

    public function updateSection(?string $sectionTitle, ?string $sectionMessage, bool $isActive): bool
    {
        $title = $sectionTitle !== null && $sectionTitle !== ''
            ? Security::sanitizeString($sectionTitle, 120)
            : null;
        $message = $sectionMessage !== null && $sectionMessage !== ''
            ? Security::sanitizeString($sectionMessage, 2000)
            : null;

        $stmt = $this->db->prepare(
            'UPDATE policy_advocacy
             SET section_title = :section_title, section_message = :section_message, is_active = :is_active
             WHERE id = :id'
        );

        return $stmt->execute([
            ':section_title' => $title,
            ':section_message' => $message,
            ':is_active' => $isActive ? 1 : 0,
            ':id' => self::SECTION_ID,
        ]);
    }

    public function countCards(): int
    {
        $count = $this->db->query('SELECT COUNT(*) FROM policy_advocacy_cards')->fetchColumn();

        return (int) $count;
    }

    public function createCard(?string $message, string $imagePath, int $sortOrder): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO policy_advocacy_cards (message, image_path, sort_order, is_active)
             VALUES (:message, :image_path, :sort_order, 1)'
        );
        $stmt->execute([
            ':message' => $this->normalizeCardMessage($message),
            ':image_path' => $imagePath,
            ':sort_order' => max(0, $sortOrder),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updateCard(int $id, ?string $message, ?string $imagePath, int $sortOrder, bool $isActive = true): bool
    {
        if ($imagePath !== null) {
            $stmt = $this->db->prepare(
                'UPDATE policy_advocacy_cards
                 SET message = :message, image_path = :image_path, sort_order = :sort_order, is_active = :is_active
                 WHERE id = :id'
            );

            return $stmt->execute([
                ':message' => $this->normalizeCardMessage($message),
                ':image_path' => $imagePath,
                ':sort_order' => max(0, $sortOrder),
                ':is_active' => $isActive ? 1 : 0,
                ':id' => $id,
            ]);
        }

        $stmt = $this->db->prepare(
            'UPDATE policy_advocacy_cards
             SET message = :message, sort_order = :sort_order, is_active = :is_active
             WHERE id = :id'
        );

        return $stmt->execute([
            ':message' => $this->normalizeCardMessage($message),
            ':sort_order' => max(0, $sortOrder),
            ':is_active' => $isActive ? 1 : 0,
            ':id' => $id,
        ]);
    }

    public function deleteCard(int $id): bool
    {
        $card = $this->getCardById($id);
        if ($card === null) {
            return false;
        }

        $stmt = $this->db->prepare('DELETE FROM policy_advocacy_cards WHERE id = :id');

        if (!$stmt->execute([':id' => $id])) {
            return false;
        }

        $this->deleteImageFile((string) ($card['image_path'] ?? ''));

        return true;
    }

    /** @return array<string, mixed>|null */
    public function getCardById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, message, image_path, sort_order, is_active
             FROM policy_advocacy_cards WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    public function deleteImageFile(?string $relativePath): void
    {
        if ($relativePath === null || $relativePath === '') {
            return;
        }

        if (!str_starts_with($relativePath, 'uploads/policy-advocacy/')) {
            return;
        }

        $fullPath = dirname(__DIR__) . '/' . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    private function normalizeCardMessage(?string $message): ?string
    {
        if ($message === null) {
            return null;
        }

        $value = Security::sanitizeString($message, 2000);

        return $value !== '' ? $value : null;
    }
}
