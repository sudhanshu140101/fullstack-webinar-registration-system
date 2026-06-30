<?php

declare(strict_types=1);

final class SaveTheDateRepository
{
    private const SECTION_ID = 1;
    private const MAX_DETAILS = 8;

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    public static function maxDetails(): int
    {
        return self::MAX_DETAILS;
    }

    /** @return array<string, mixed>|null */
    public function getSection(): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, badge, tagline, headline, copy_text, is_active, updated_at
             FROM save_the_date_section WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => self::SECTION_ID]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    /** @return list<array<string, mixed>> */
    public function listDetails(): array
    {
        $stmt = $this->db->query(
            'SELECT id, text, sort_order
             FROM save_the_date_details
             ORDER BY sort_order ASC, id ASC'
        );

        return $stmt !== false ? $stmt->fetchAll() : [];
    }

    /**
     * @return array{
     *   badge: ?string,
     *   tagline: ?string,
     *   headline: string,
     *   details: list<string>,
     *   copy_text: ?string
     * }|null
     */
    public function getPublic(): ?array
    {
        $section = $this->getSection();
        if ($section === null || !(bool) $section['is_active']) {
            return null;
        }

        $headline = trim((string) ($section['headline'] ?? ''));
        if ($headline === '') {
            return null;
        }

        $details = [];
        foreach ($this->listDetails() as $item) {
            $text = trim((string) ($item['text'] ?? ''));
            if ($text !== '') {
                $details[] = $text;
            }
        }

        return [
            'badge' => $this->nullableTrim((string) ($section['badge'] ?? '')),
            'tagline' => $this->nullableTrim((string) ($section['tagline'] ?? '')),
            'headline' => $headline,
            'details' => $details,
            'copy_text' => $this->nullableTrim((string) ($section['copy_text'] ?? '')),
        ];
    }

    /**
     * @param array{
     *   badge: ?string,
     *   tagline: ?string,
     *   headline: string,
     *   copy_text: ?string,
     *   is_active: bool
     * } $data
     */
    public function updateSection(array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE save_the_date_section
             SET badge = :badge,
                 tagline = :tagline,
                 headline = :headline,
                 copy_text = :copy_text,
                 is_active = :is_active
             WHERE id = :id'
        );

        return $stmt->execute([
            ':badge' => $this->normalizeOptional($data['badge'] ?? null, 80),
            ':tagline' => $this->normalizeOptional($data['tagline'] ?? null, 120),
            ':headline' => Security::sanitizeString($data['headline'], 200),
            ':copy_text' => $this->normalizeOptional($data['copy_text'] ?? null, 2000),
            ':is_active' => !empty($data['is_active']) ? 1 : 0,
            ':id' => self::SECTION_ID,
        ]);
    }

    /**
     * @param list<array{text: string, sort_order: int}> $items
     */
    public function replaceDetails(array $items): void
    {
        $this->db->beginTransaction();

        try {
            $this->db->exec('DELETE FROM save_the_date_details');

            $stmt = $this->db->prepare(
                'INSERT INTO save_the_date_details (text, sort_order)
                 VALUES (:text, :sort_order)'
            );

            $count = 0;
            foreach ($items as $item) {
                if ($count >= self::MAX_DETAILS) {
                    break;
                }

                $text = Security::sanitizeString((string) ($item['text'] ?? ''), 120);
                if ($text === '') {
                    continue;
                }

                $stmt->execute([
                    ':text' => $text,
                    ':sort_order' => max(0, (int) ($item['sort_order'] ?? $count)),
                ]);
                $count++;
            }

            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    private function nullableTrim(string $value): ?string
    {
        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private function normalizeOptional(?string $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        $sanitized = Security::sanitizeString($value, $maxLength);

        return $sanitized !== '' ? $sanitized : null;
    }
}
