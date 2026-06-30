<?php

declare(strict_types=1);

final class HeroRepository
{
    private const SECTION_ID = 1;
    private const MAX_META = 4;
    private const MAX_HIGHLIGHTS = 8;

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    public static function maxMeta(): int
    {
        return self::MAX_META;
    }

    public static function maxHighlights(): int
    {
        return self::MAX_HIGHLIGHTS;
    }

    /** @return array<string, mixed>|null */
    public function getSection(): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, badge, title, subtitle, guest_label, guest_name, guest_role,
                    copy_text, register_url, chat_url, fee_note, is_active, updated_at
             FROM hero_section WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => self::SECTION_ID]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    /** @return list<array<string, mixed>> */
    public function listMetaItems(): array
    {
        $stmt = $this->db->query(
            'SELECT id, label, value, icon, sort_order
             FROM hero_meta_items
             ORDER BY sort_order ASC, id ASC'
        );

        return $stmt !== false ? $stmt->fetchAll() : [];
    }

    /** @return list<array<string, mixed>> */
    public function listHighlights(): array
    {
        $stmt = $this->db->query(
            'SELECT id, text, sort_order
             FROM hero_highlights
             ORDER BY sort_order ASC, id ASC'
        );

        return $stmt !== false ? $stmt->fetchAll() : [];
    }

    /**
     * @return array{
     *   badge: ?string,
     *   title: string,
     *   subtitle: ?string,
     *   guest: array{label: ?string, name: ?string, role: ?string},
     *   meta: list<array{label: string, value: string, icon: string}>,
     *   highlights: list<string>,
     *   copy_text: ?string,
     *   register_url: string,
     *   chat_url: ?string,
     *   fee_note: ?string
     * }|null
     */
    public function getPublic(): ?array
    {
        $section = $this->getSection();
        if ($section === null || !(bool) $section['is_active']) {
            return null;
        }

        $title = trim((string) ($section['title'] ?? ''));
        if ($title === '') {
            return null;
        }

        $meta = [];
        foreach ($this->listMetaItems() as $item) {
            $label = trim((string) ($item['label'] ?? ''));
            $value = trim((string) ($item['value'] ?? ''));
            if ($label === '' || $value === '') {
                continue;
            }

            $icon = trim((string) ($item['icon'] ?? ''));
            $meta[] = [
                'label' => $label,
                'value' => $value,
                'icon' => $icon !== '' ? $icon : '📌',
            ];
        }

        $highlights = [];
        foreach ($this->listHighlights() as $item) {
            $text = trim((string) ($item['text'] ?? ''));
            if ($text !== '') {
                $highlights[] = $text;
            }
        }

        $registerUrl = trim((string) ($section['register_url'] ?? 'register.html'));
        if ($registerUrl === '') {
            $registerUrl = 'register.html';
        }
        $registerUrl = sanitize_safe_register_url($registerUrl);

        $chatUrl = sanitize_safe_external_url($this->nullableTrim((string) ($section['chat_url'] ?? '')));

        return [
            'badge' => $this->nullableTrim((string) ($section['badge'] ?? '')),
            'title' => $title,
            'subtitle' => $this->nullableTrim((string) ($section['subtitle'] ?? '')),
            'guest' => [
                'label' => $this->nullableTrim((string) ($section['guest_label'] ?? '')),
                'name' => $this->nullableTrim((string) ($section['guest_name'] ?? '')),
                'role' => $this->nullableTrim((string) ($section['guest_role'] ?? '')),
            ],
            'meta' => $meta,
            'highlights' => $highlights,
            'copy_text' => $this->nullableTrim((string) ($section['copy_text'] ?? '')),
            'register_url' => $registerUrl,
            'chat_url' => $chatUrl,
            'fee_note' => $this->nullableTrim((string) ($section['fee_note'] ?? '')),
        ];
    }

    /**
     * @param array{
     *   badge: ?string,
     *   title: string,
     *   subtitle: ?string,
     *   guest_label: ?string,
     *   guest_name: ?string,
     *   guest_role: ?string,
     *   copy_text: ?string,
     *   register_url: string,
     *   chat_url: ?string,
     *   fee_note: ?string,
     *   is_active: bool
     * } $data
     */
    public function updateSection(array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE hero_section
             SET badge = :badge,
                 title = :title,
                 subtitle = :subtitle,
                 guest_label = :guest_label,
                 guest_name = :guest_name,
                 guest_role = :guest_role,
                 copy_text = :copy_text,
                 register_url = :register_url,
                 chat_url = :chat_url,
                 fee_note = :fee_note,
                 is_active = :is_active
             WHERE id = :id'
        );

        return $stmt->execute([
            ':badge' => $this->normalizeOptional($data['badge'] ?? null, 80),
            ':title' => Security::sanitizeString($data['title'], 200),
            ':subtitle' => $this->normalizeOptional($data['subtitle'] ?? null, 2000),
            ':guest_label' => $this->normalizeOptional($data['guest_label'] ?? null, 80),
            ':guest_name' => $this->normalizeOptional($data['guest_name'] ?? null, 120),
            ':guest_role' => $this->normalizeOptional($data['guest_role'] ?? null, 2000),
            ':copy_text' => $this->normalizeOptional($data['copy_text'] ?? null, 2000),
            ':register_url' => sanitize_safe_register_url(
                Security::sanitizeString($data['register_url'] ?? 'register.html', 255)
            ),
            ':chat_url' => sanitize_safe_external_url($data['chat_url'] ?? null),
            ':fee_note' => $this->normalizeOptional($data['fee_note'] ?? null, 255),
            ':is_active' => !empty($data['is_active']) ? 1 : 0,
            ':id' => self::SECTION_ID,
        ]);
    }

    /**
     * @param list<array{id?: int, label: string, value: string, icon: string, sort_order: int}> $items
     */
    public function replaceMetaItems(array $items): void
    {
        $this->db->beginTransaction();

        try {
            $this->db->exec('DELETE FROM hero_meta_items');

            $stmt = $this->db->prepare(
                'INSERT INTO hero_meta_items (label, value, icon, sort_order)
                 VALUES (:label, :value, :icon, :sort_order)'
            );

            foreach (array_slice($items, 0, self::MAX_META) as $item) {
                $label = Security::sanitizeString((string) ($item['label'] ?? ''), 40);
                $value = Security::sanitizeString((string) ($item['value'] ?? ''), 200);
                if ($label === '' || $value === '') {
                    continue;
                }

                $icon = Security::sanitizeString((string) ($item['icon'] ?? '📌'), 16);
                $stmt->execute([
                    ':label' => $label,
                    ':value' => $value,
                    ':icon' => $icon !== '' ? $icon : '📌',
                    ':sort_order' => max(0, (int) ($item['sort_order'] ?? 0)),
                ]);
            }

            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    /**
     * @param list<array{text: string, sort_order: int}> $items
     */
    public function replaceHighlights(array $items): void
    {
        $this->db->beginTransaction();

        try {
            $this->db->exec('DELETE FROM hero_highlights');

            $stmt = $this->db->prepare(
                'INSERT INTO hero_highlights (text, sort_order)
                 VALUES (:text, :sort_order)'
            );

            $count = 0;
            foreach ($items as $item) {
                if ($count >= self::MAX_HIGHLIGHTS) {
                    break;
                }

                $text = Security::sanitizeString((string) ($item['text'] ?? ''), 80);
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
