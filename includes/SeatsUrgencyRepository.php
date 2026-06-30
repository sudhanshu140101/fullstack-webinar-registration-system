<?php

declare(strict_types=1);

final class SeatsUrgencyRepository
{
    private const SECTION_ID = 1;
    private const MAX_SPOTS = 9999;

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    /** @return array<string, mixed>|null */
    public function getSection(): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, message_text, spots_left, progress_percent, is_active, updated_at
             FROM seats_urgency_banner
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => self::SECTION_ID]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    /**
     * @return array{
     *   message_text: string,
     *   spots_left: int,
     *   progress_percent: int
     * }|null
     */
    public function getPublic(): ?array
    {
        $section = $this->getSection();
        if ($section === null || !(bool) $section['is_active']) {
            return null;
        }

        $message = trim((string) ($section['message_text'] ?? ''));
        if ($message === '') {
            return null;
        }

        $spotsLeft = max(0, min(self::MAX_SPOTS, (int) ($section['spots_left'] ?? 0)));
        $progress = max(0, min(100, (int) ($section['progress_percent'] ?? 0)));

        return [
            'message_text' => $message,
            'spots_left' => $spotsLeft,
            'progress_percent' => $progress,
        ];
    }

    /**
     * @param array{
     *   message_text: string,
     *   spots_left: int,
     *   progress_percent: int,
     *   is_active: bool
     * } $data
     */
    public function updateSection(array $data): bool
    {
        $message = Security::sanitizeString((string) ($data['message_text'] ?? ''), 255);
        $spotsLeft = max(0, min(self::MAX_SPOTS, (int) ($data['spots_left'] ?? 0)));
        $progress = max(0, min(100, (int) ($data['progress_percent'] ?? 0)));

        $stmt = $this->db->prepare(
            'UPDATE seats_urgency_banner
             SET message_text = :message_text,
                 spots_left = :spots_left,
                 progress_percent = :progress_percent,
                 is_active = :is_active
             WHERE id = :id'
        );

        return $stmt->execute([
            ':message_text' => $message,
            ':spots_left' => $spotsLeft,
            ':progress_percent' => $progress,
            ':is_active' => !empty($data['is_active']) ? 1 : 0,
            ':id' => self::SECTION_ID,
        ]);
    }
}
