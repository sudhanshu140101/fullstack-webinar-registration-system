<?php

declare(strict_types=1);

function install_lock_path(): string
{
    return dirname(__DIR__) . '/storage/install.lock';
}

function install_is_complete(): bool
{
    return is_file(install_lock_path());
}

function install_mark_complete(): void
{
    $dir = dirname(install_lock_path());
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    @file_put_contents(install_lock_path(), date('c') . ' — setup complete' . PHP_EOL);
}

/**
 * Applies incremental schema updates for existing databases (idempotent).
 */
function ensure_database_schema(PDO $db): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }
    $ensured = true;

    $stmt = $db->query("SHOW COLUMNS FROM `registrations` LIKE 'company_name'");
    if ($stmt !== false && $stmt->fetch() === false) {
        $db->exec(
            'ALTER TABLE `registrations`
             ADD COLUMN `company_name` VARCHAR(120) NULL DEFAULT NULL AFTER `name`'
        );
    }

    $paymentCol = $db->query("SHOW COLUMNS FROM `registrations` LIKE 'payment_status'")->fetch();
    if ($paymentCol !== false && ($paymentCol['Null'] ?? '') === 'NO') {
        $db->exec(
            'ALTER TABLE `registrations`
             MODIFY COLUMN `payment_status` ENUM(\'pending\', \'paid\', \'failed\') NULL DEFAULT NULL'
        );
    }

    $verifiedCol = $db->query("SHOW COLUMNS FROM `registrations` LIKE 'is_verified'")->fetch();
    if ($verifiedCol === false) {
        $db->exec(
            'ALTER TABLE `registrations`
             ADD COLUMN `is_verified` TINYINT(1) NOT NULL DEFAULT 0 AFTER `payment_status`'
        );
        $db->exec('ALTER TABLE `registrations` ADD KEY `idx_registrations_is_verified` (`is_verified`)');
    }

    $db->exec(
        'CREATE TABLE IF NOT EXISTS `policy_advocacy` (
          `id` TINYINT UNSIGNED NOT NULL,
          `section_title` VARCHAR(120) NULL DEFAULT NULL,
          `section_message` TEXT NULL,
          `is_active` TINYINT(1) NOT NULL DEFAULT 1,
          `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $db->exec(
        'CREATE TABLE IF NOT EXISTS `policy_advocacy_cards` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `message` TEXT NULL,
          `image_path` VARCHAR(255) NOT NULL,
          `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
          `is_active` TINYINT(1) NOT NULL DEFAULT 1,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_policy_advocacy_cards_sort` (`sort_order`, `is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    ensure_policy_advocacy_schema($db);
    ensure_hero_schema($db);
    ensure_save_the_date_schema($db);
    ensure_seats_urgency_schema($db);
    ensure_registration_payment_schema($db);
    ensure_user_login_otp_schema($db);
    ensure_registration_mobile_otp_schema($db);
    ensure_sms_send_log_schema($db);
    sync_legacy_event_dates($db);
}

function ensure_sms_send_log_schema(PDO $db): void
{
    $db->exec(
        'CREATE TABLE IF NOT EXISTS `sms_send_logs` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `mobile` VARCHAR(20) NOT NULL,
          `destination` VARCHAR(20) NOT NULL,
          `message_preview` VARCHAR(255) NOT NULL,
          `gateway_response` VARCHAR(255) NOT NULL,
          `message_id` VARCHAR(80) NULL DEFAULT NULL,
          `success` TINYINT(1) NOT NULL DEFAULT 0,
          `error_message` VARCHAR(255) NULL DEFAULT NULL,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_sms_send_logs_created` (`created_at`),
          KEY `idx_sms_send_logs_mobile` (`mobile`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function ensure_user_login_otp_schema(PDO $db): void
{
    $db->exec(
        'CREATE TABLE IF NOT EXISTS `user_login_otps` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `mobile` VARCHAR(15) NOT NULL,
          `otp_hash` VARCHAR(255) NOT NULL,
          `expires_at` DATETIME NOT NULL,
          `verify_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_user_login_otps_mobile_expires` (`mobile`, `expires_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function ensure_registration_mobile_otp_schema(PDO $db): void
{
    $db->exec(
        'CREATE TABLE IF NOT EXISTS `registration_mobile_otps` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `mobile` VARCHAR(15) NOT NULL,
          `otp_hash` VARCHAR(255) NOT NULL,
          `expires_at` DATETIME NOT NULL,
          `verify_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_registration_mobile_otps_mobile_expires` (`mobile`, `expires_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function ensure_registration_payment_schema(PDO $db): void
{
    $db->exec(
        'CREATE TABLE IF NOT EXISTS `registration_settings` (
          `id` TINYINT UNSIGNED NOT NULL,
          `amount_paise` INT UNSIGNED NOT NULL DEFAULT 0,
          `currency` CHAR(3) NOT NULL DEFAULT \'INR\',
          `fee_label` VARCHAR(120) NULL DEFAULT NULL,
          `payment_enabled` TINYINT(1) NOT NULL DEFAULT 1,
          `payment_url` VARCHAR(500) NULL DEFAULT NULL,
          `workshop_url` VARCHAR(500) NULL DEFAULT NULL,
          `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $db->exec(
        'CREATE TABLE IF NOT EXISTS `payment_orders` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `registration_id` BIGINT UNSIGNED NOT NULL,
          `cashfree_order_id` VARCHAR(80) NOT NULL,
          `cashfree_payment_id` VARCHAR(64) NULL DEFAULT NULL,
          `payment_session_id` VARCHAR(255) NOT NULL,
          `amount_paise` INT UNSIGNED NOT NULL,
          `currency` CHAR(3) NOT NULL DEFAULT \'INR\',
          `status` ENUM(\'created\', \'paid\', \'failed\') NOT NULL DEFAULT \'created\',
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_payment_orders_cashfree_order_id` (`cashfree_order_id`),
          KEY `idx_payment_orders_registration_id` (`registration_id`),
          CONSTRAINT `fk_payment_orders_registration`
            FOREIGN KEY (`registration_id`) REFERENCES `registrations` (`id`)
            ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $paymentUrlCol = $db->query("SHOW COLUMNS FROM `registration_settings` LIKE 'payment_url'")->fetch();
    if ($paymentUrlCol === false) {
        $db->exec(
            'ALTER TABLE `registration_settings`
             ADD COLUMN `payment_url` VARCHAR(500) NULL DEFAULT NULL AFTER `payment_enabled`'
        );
    }

    $settingsCount = (int) $db->query('SELECT COUNT(*) FROM registration_settings')->fetchColumn();
    if ($settingsCount === 0) {
        $stmt = $db->prepare(
            'INSERT INTO registration_settings (id, amount_paise, currency, fee_label, payment_enabled)
             VALUES (1, :amount_paise, :currency, :fee_label, 1)'
        );
        $stmt->execute([
            ':amount_paise' => 49900,
            ':currency' => 'INR',
            ':fee_label' => 'Summit Registration Fee',
        ]);
    }

    $workshopCol = $db->query("SHOW COLUMNS FROM `registration_settings` LIKE 'workshop_url'");
    if ($workshopCol !== false && $workshopCol->fetch() === false) {
        $db->exec(
            'ALTER TABLE `registration_settings`
             ADD COLUMN `workshop_url` VARCHAR(500) NULL DEFAULT NULL AFTER `payment_enabled`'
        );
    }
}

function ensure_policy_advocacy_schema(PDO $db): void
{
    $sectionCount = (int) $db->query('SELECT COUNT(*) FROM `policy_advocacy`')->fetchColumn();
    if ($sectionCount === 0) {
        $stmt = $db->prepare(
            'INSERT INTO `policy_advocacy` (`id`, `section_title`, `section_message`, `is_active`)
             VALUES (1, :title, NULL, 1)'
        );
        $stmt->execute([':title' => 'Policy Advocacy']);
    }

    $titleCol = $db->query("SHOW COLUMNS FROM `policy_advocacy` LIKE 'title'")->fetch();
    if ($titleCol !== false) {
        $legacy = $db->query('SELECT title, message, image_path FROM policy_advocacy WHERE id = 1')->fetch();
        $cardCount = (int) $db->query('SELECT COUNT(*) FROM policy_advocacy_cards')->fetchColumn();

        if ($cardCount === 0 && is_array($legacy)) {
            $legacyMessage = trim((string) ($legacy['message'] ?? ''));
            $legacyImage = trim((string) ($legacy['image_path'] ?? ''));

            if ($legacyImage !== '' || $legacyMessage !== '') {
                $stmt = $db->prepare(
                    'INSERT INTO policy_advocacy_cards (message, image_path, sort_order, is_active)
                     VALUES (:message, :image_path, 0, 1)'
                );
                $stmt->execute([
                    ':message' => $legacyMessage !== '' ? $legacyMessage : null,
                    ':image_path' => $legacyImage !== '' ? $legacyImage : 'images/Slider-1.png',
                ]);
            }
        }

        $db->exec('ALTER TABLE `policy_advocacy` CHANGE `title` `section_title` VARCHAR(120) NULL DEFAULT NULL');
        if ($db->query("SHOW COLUMNS FROM `policy_advocacy` LIKE 'message'")->fetch() !== false) {
            $db->exec('ALTER TABLE `policy_advocacy` CHANGE `message` `section_message` TEXT NULL');
        }
        if ($db->query("SHOW COLUMNS FROM `policy_advocacy` LIKE 'image_path'")->fetch() !== false) {
            $db->exec('ALTER TABLE `policy_advocacy` DROP COLUMN `image_path`');
        }
    }

    $sectionTitleCol = $db->query("SHOW COLUMNS FROM `policy_advocacy` LIKE 'section_title'")->fetch();
    if ($sectionTitleCol !== false && ($sectionTitleCol['Null'] ?? '') === 'NO') {
        $db->exec('ALTER TABLE `policy_advocacy` MODIFY `section_title` VARCHAR(120) NULL DEFAULT NULL');
    }

    $sectionMessageCol = $db->query("SHOW COLUMNS FROM `policy_advocacy` LIKE 'section_message'")->fetch();
    if ($sectionMessageCol !== false && ($sectionMessageCol['Null'] ?? '') === 'NO') {
        $db->exec('ALTER TABLE `policy_advocacy` MODIFY `section_message` TEXT NULL');
    }

    $cardsCount = (int) $db->query('SELECT COUNT(*) FROM policy_advocacy_cards')->fetchColumn();
    if ($cardsCount === 0) {
        $stmt = $db->prepare(
            'INSERT INTO policy_advocacy_cards (message, image_path, sort_order, is_active)
             VALUES (:message, :image_path, 0, 1)'
        );
        $stmt->execute([
            ':message' => 'CIMSME works with policymakers to strengthen MSME-friendly reforms, fair credit access, and practical support for entrepreneurs across India.',
            ':image_path' => 'images/Slider-1.png',
        ]);
    }
}

function ensure_hero_schema(PDO $db): void
{
    $db->exec(
        'CREATE TABLE IF NOT EXISTS `hero_section` (
          `id` TINYINT UNSIGNED NOT NULL,
          `badge` VARCHAR(80) NULL DEFAULT NULL,
          `title` VARCHAR(200) NOT NULL,
          `subtitle` TEXT NULL,
          `guest_label` VARCHAR(80) NULL DEFAULT NULL,
          `guest_name` VARCHAR(120) NULL DEFAULT NULL,
          `guest_role` TEXT NULL,
          `copy_text` TEXT NULL,
          `register_url` VARCHAR(255) NOT NULL DEFAULT \'register.html\',
          `chat_url` VARCHAR(500) NULL DEFAULT NULL,
          `fee_note` VARCHAR(255) NULL DEFAULT NULL,
          `is_active` TINYINT(1) NOT NULL DEFAULT 1,
          `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $db->exec(
        'CREATE TABLE IF NOT EXISTS `hero_meta_items` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `label` VARCHAR(40) NOT NULL,
          `value` VARCHAR(200) NOT NULL,
          `icon` VARCHAR(16) NOT NULL DEFAULT \'📅\',
          `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
          PRIMARY KEY (`id`),
          KEY `idx_hero_meta_sort` (`sort_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $db->exec(
        'CREATE TABLE IF NOT EXISTS `hero_highlights` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `text` VARCHAR(80) NOT NULL,
          `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
          PRIMARY KEY (`id`),
          KEY `idx_hero_highlights_sort` (`sort_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $sectionCount = (int) $db->query('SELECT COUNT(*) FROM `hero_section`')->fetchColumn();
    if ($sectionCount === 0) {
        $stmt = $db->prepare(
            'INSERT INTO `hero_section`
             (`id`, `badge`, `title`, `subtitle`, `guest_label`, `guest_name`, `guest_role`,
              `copy_text`, `register_url`, `chat_url`, `fee_note`, `is_active`)
             VALUES
             (1, :badge, :title, :subtitle, :guest_label, :guest_name, :guest_role,
              :copy_text, :register_url, :chat_url, :fee_note, 1)'
        );
        $stmt->execute([
            ':badge' => 'RECOGNITION OF MSMEs',
            ':title' => 'MSME CONNECT Summit 2026',
            ':subtitle' => 'India\'s premier platform connecting MSMEs with Government Schemes, Funding Opportunity, and Industry Experts.',
            ':guest_label' => 'Expert Host',
            ':guest_name' => 'Shri Mukesh Mohan Gupta',
            ':guest_role' => 'President of CIMSME, Chartered Accountant by Profession having more than 35 years of experience in Banking, Finance MSME, Economy, Law Accounting and Auditing',
            ':copy_text' => "Connect with Industry Leaders, Banks, NBFCs, and Policymakers.\nExplore Practical Strategies for Business Expansion, Funding Access, Digital Transformation, and Sustainable MSME Growth.",
            ':register_url' => 'register.html',
            ':chat_url' => 'https://wa.me/919582821431?text=Hello%2C%20I%20am%20interested%20in%20MSME%20CONNECT%20Summit%202026.%20Please%20share%20registration%20and%20event%20details%20with%20me.',
            ':fee_note' => 'Registration Closes 24th June 2026',
        ]);
    }

    $metaCount = (int) $db->query('SELECT COUNT(*) FROM hero_meta_items')->fetchColumn();
    if ($metaCount === 0) {
        $defaults = [
            ['Date', '25th July 2026', '📅', 0],
            ['Time', '12 PM – 3 PM Afternoon', '⏰', 1],
            ['Venue', 'Online, New Delhi House', '📍', 2],
            ['Language', 'Hindi & English', '🗣️', 3],
        ];
        $stmt = $db->prepare(
            'INSERT INTO hero_meta_items (label, value, icon, sort_order)
             VALUES (:label, :value, :icon, :sort_order)'
        );
        foreach ($defaults as [$label, $value, $icon, $sortOrder]) {
            $stmt->execute([
                ':label' => $label,
                ':value' => $value,
                ':icon' => $icon,
                ':sort_order' => $sortOrder,
            ]);
        }
    }

    $highlightCount = (int) $db->query('SELECT COUNT(*) FROM hero_highlights')->fetchColumn();
    if ($highlightCount === 0) {
        $defaults = [
            'Government Schemes',
            'Collateral-Free Loans',
            'GeM & TReDS',
            'Expert Networking',
        ];
        $stmt = $db->prepare(
            'INSERT INTO hero_highlights (text, sort_order)
             VALUES (:text, :sort_order)'
        );
        foreach ($defaults as $index => $text) {
            $stmt->execute([
                ':text' => $text,
                ':sort_order' => $index,
            ]);
        }
    }
}

function ensure_save_the_date_schema(PDO $db): void
{
    $db->exec(
        'CREATE TABLE IF NOT EXISTS `save_the_date_section` (
          `id` TINYINT UNSIGNED NOT NULL,
          `badge` VARCHAR(80) NULL DEFAULT NULL,
          `tagline` VARCHAR(120) NULL DEFAULT NULL,
          `headline` VARCHAR(200) NOT NULL,
          `copy_text` TEXT NULL,
          `is_active` TINYINT(1) NOT NULL DEFAULT 1,
          `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $db->exec(
        'CREATE TABLE IF NOT EXISTS `save_the_date_details` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `text` VARCHAR(120) NOT NULL,
          `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
          PRIMARY KEY (`id`),
          KEY `idx_save_the_date_details_sort` (`sort_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $sectionCount = (int) $db->query('SELECT COUNT(*) FROM `save_the_date_section`')->fetchColumn();
    if ($sectionCount === 0) {
        $stmt = $db->prepare(
            'INSERT INTO `save_the_date_section`
             (`id`, `badge`, `tagline`, `headline`, `copy_text`, `is_active`)
             VALUES
             (1, :badge, :tagline, :headline, :copy_text, 1)'
        );
        $stmt->execute([
            ':badge' => 'Save the Date',
            ':tagline' => 'Mark Your Calendar',
            ':headline' => '25th July 2026 · New Delhi',
            ':copy_text' => 'Join industry leaders, banks, NBFCs, policymakers, and MSME stakeholders for a day of practical strategies, funding access, and meaningful connections.',
        ]);
    }

    $detailCount = (int) $db->query('SELECT COUNT(*) FROM save_the_date_details')->fetchColumn();
    if ($detailCount === 0) {
        $defaults = [
            '12 PM – 3 PM',
            'New Delhi House',
            'Afternoon',
            'Hindi & English',
        ];
        $stmt = $db->prepare(
            'INSERT INTO save_the_date_details (text, sort_order)
             VALUES (:text, :sort_order)'
        );
        foreach ($defaults as $index => $text) {
            $stmt->execute([
                ':text' => $text,
                ':sort_order' => $index,
            ]);
        }
    }
}

function ensure_seats_urgency_schema(PDO $db): void
{
    $db->exec(
        'CREATE TABLE IF NOT EXISTS `seats_urgency_banner` (
          `id` TINYINT UNSIGNED NOT NULL,
          `message_text` VARCHAR(255) NOT NULL DEFAULT \'\',
          `spots_left` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
          `progress_percent` TINYINT UNSIGNED NOT NULL DEFAULT 0,
          `is_active` TINYINT(1) NOT NULL DEFAULT 1,
          `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $sectionCount = (int) $db->query('SELECT COUNT(*) FROM `seats_urgency_banner`')->fetchColumn();
    if ($sectionCount === 0) {
        $stmt = $db->prepare(
            'INSERT INTO `seats_urgency_banner`
             (`id`, `message_text`, `spots_left`, `progress_percent`, `is_active`)
             VALUES
             (1, :message_text, :spots_left, :progress_percent, 1)'
        );
        $stmt->execute([
            ':message_text' => 'Hurry, seats for Sunday are low......',
            ':spots_left' => 30,
            ':progress_percent' => 90,
        ]);
    }
}

/** Align seeded legacy event dates with Summit 2026 (idempotent). */
function sync_legacy_event_dates(PDO $db): void
{
    $db->exec(
        "UPDATE hero_meta_items
         SET value = '25th July 2026'
         WHERE value IN ('25th July 2025', '25th July 2025 ')"
    );

    $db->exec(
        "UPDATE save_the_date_section
         SET headline = '25th July 2026 · New Delhi'
         WHERE headline IN ('25th July 2025 · New Delhi', '25th July 2025 - New Delhi')"
    );
}
