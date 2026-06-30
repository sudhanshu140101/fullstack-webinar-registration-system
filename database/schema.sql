

SET NAMES utf8mb4;
SET time_zone = '+00:00';


CREATE TABLE IF NOT EXISTS `registrations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(80) NOT NULL,
  `company_name` VARCHAR(120) NULL DEFAULT NULL,
  `seat` VARCHAR(32) NOT NULL COMMENT 'micro|small|medium|startup|professionals|other',
  `mobile` CHAR(10) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `pincode` CHAR(6) NOT NULL,
  `state` VARCHAR(60) NOT NULL,
  `district` VARCHAR(60) NOT NULL,
  `payment_status` ENUM('pending', 'paid', 'failed') NULL DEFAULT NULL,
  `is_verified` TINYINT(1) NOT NULL DEFAULT 0,
  `ip_address` VARCHAR(45) NULL DEFAULT NULL,
  `user_agent` VARCHAR(512) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_registrations_created_at` (`created_at`),
  KEY `idx_registrations_mobile` (`mobile`),
  KEY `idx_registrations_email` (`email`),
  KEY `idx_registrations_seat` (`seat`),
  KEY `idx_registrations_state` (`state`),
  KEY `idx_registrations_payment_status` (`payment_status`),
  KEY `idx_registrations_is_verified` (`is_verified`),
  KEY `idx_registrations_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `registration_files` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `registration_id` BIGINT UNSIGNED NOT NULL,
  `stored_name` VARCHAR(255) NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `mime_type` VARCHAR(128) NOT NULL,
  `file_size` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_registration_files_registration_id` (`registration_id`),
  CONSTRAINT `fk_registration_files_registration`
    FOREIGN KEY (`registration_id`) REFERENCES `registrations` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `policy_advocacy` (
  `id` TINYINT UNSIGNED NOT NULL,
  `section_title` VARCHAR(120) NULL DEFAULT NULL,
  `section_message` TEXT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `policy_advocacy_cards` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `message` TEXT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_policy_advocacy_cards_sort` (`sort_order`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO `policy_advocacy` (`id`, `section_title`, `section_message`, `is_active`)
VALUES (1, 'Policy Advocacy', NULL, 1)
ON DUPLICATE KEY UPDATE `id` = `id`;


INSERT INTO `policy_advocacy_cards` (`message`, `image_path`, `sort_order`, `is_active`)
SELECT
  'CIMSME works with policymakers to strengthen MSME-friendly reforms, fair credit access, and practical support for entrepreneurs across India.',
  'images/Slider-1.png',
  0,
  1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `policy_advocacy_cards` LIMIT 1);


CREATE TABLE IF NOT EXISTS `hero_section` (
  `id` TINYINT UNSIGNED NOT NULL,
  `badge` VARCHAR(80) NULL DEFAULT NULL,
  `title` VARCHAR(200) NOT NULL,
  `subtitle` TEXT NULL,
  `guest_label` VARCHAR(80) NULL DEFAULT NULL,
  `guest_name` VARCHAR(120) NULL DEFAULT NULL,
  `guest_role` TEXT NULL,
  `copy_text` TEXT NULL,
  `register_url` VARCHAR(255) NOT NULL DEFAULT 'register.html',
  `chat_url` VARCHAR(500) NULL DEFAULT NULL,
  `fee_note` VARCHAR(255) NULL DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `hero_meta_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `label` VARCHAR(40) NOT NULL,
  `value` VARCHAR(200) NOT NULL,
  `icon` VARCHAR(16) NOT NULL DEFAULT '📅',
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_hero_meta_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `hero_highlights` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `text` VARCHAR(80) NOT NULL,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_hero_highlights_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO `hero_section`
  (`id`, `badge`, `title`, `subtitle`, `guest_label`, `guest_name`, `guest_role`,
   `copy_text`, `register_url`, `chat_url`, `fee_note`, `is_active`)
VALUES (
  1,
  'RECOGNITION OF MSMEs',
  'MSME CONNECT Summit 2026',
  'India''s premier platform connecting MSMEs with Government Schemes, Funding Opportunity, and Industry Experts.',
  'Expert Host',
  'Shri Mukesh Mohan Gupta',
  'President of CIMSME, Chartered Accountant by Profession having more than 35 years of experience in Banking, Finance MSME, Economy, Law Accounting and Auditing',
  'Connect with Industry Leaders, Banks, NBFCs, and Policymakers.\nExplore Practical Strategies for Business Expansion, Funding Access, Digital Transformation, and Sustainable MSME Growth.',
  'register.html',
  'https://wa.me/919582821431?text=Hello%2C%20I%20am%20interested%20in%20MSME%20CONNECT%20Summit%202026.%20Please%20share%20registration%20and%20event%20details%20with%20me.',
  'Registration Closes 24th June 2026',
  1
)
ON DUPLICATE KEY UPDATE `id` = `id`;


INSERT INTO `hero_meta_items` (`label`, `value`, `icon`, `sort_order`)
SELECT 'Date', '25th July 2026', '📅', 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `hero_meta_items` LIMIT 1);

INSERT INTO `hero_meta_items` (`label`, `value`, `icon`, `sort_order`)
SELECT 'Time', '12 PM – 3 PM Afternoon', '⏰', 1 FROM DUAL
WHERE (SELECT COUNT(*) FROM `hero_meta_items`) < 2;

INSERT INTO `hero_meta_items` (`label`, `value`, `icon`, `sort_order`)
SELECT 'Venue', 'Online, New Delhi House', '📍', 2 FROM DUAL
WHERE (SELECT COUNT(*) FROM `hero_meta_items`) < 3;

INSERT INTO `hero_meta_items` (`label`, `value`, `icon`, `sort_order`)
SELECT 'Language', 'Hindi & English', '🗣️', 3 FROM DUAL
WHERE (SELECT COUNT(*) FROM `hero_meta_items`) < 4;


INSERT INTO `hero_highlights` (`text`, `sort_order`)
SELECT 'Government Schemes', 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `hero_highlights` LIMIT 1);

INSERT INTO `hero_highlights` (`text`, `sort_order`)
SELECT 'Collateral-Free Loans', 1 FROM DUAL
WHERE (SELECT COUNT(*) FROM `hero_highlights`) < 2;

INSERT INTO `hero_highlights` (`text`, `sort_order`)
SELECT 'GeM & TReDS', 2 FROM DUAL
WHERE (SELECT COUNT(*) FROM `hero_highlights`) < 3;

INSERT INTO `hero_highlights` (`text`, `sort_order`)
SELECT 'Expert Networking', 3 FROM DUAL
WHERE (SELECT COUNT(*) FROM `hero_highlights`) < 4;


CREATE TABLE IF NOT EXISTS `save_the_date_section` (
  `id` TINYINT UNSIGNED NOT NULL,
  `badge` VARCHAR(80) NULL DEFAULT NULL,
  `tagline` VARCHAR(120) NULL DEFAULT NULL,
  `headline` VARCHAR(200) NOT NULL,
  `copy_text` TEXT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `save_the_date_details` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `text` VARCHAR(120) NOT NULL,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_save_the_date_details_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO `save_the_date_section`
  (`id`, `badge`, `tagline`, `headline`, `copy_text`, `is_active`)
VALUES (
  1,
  'Save the Date',
  'Mark Your Calendar',
  '25th July 2026 · New Delhi',
  'Join industry leaders, banks, NBFCs, policymakers, and MSME stakeholders for a day of practical strategies, funding access, and meaningful connections.',
  1
)
ON DUPLICATE KEY UPDATE `id` = `id`;


INSERT INTO `save_the_date_details` (`text`, `sort_order`)
SELECT '12 PM – 3 PM', 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `save_the_date_details` LIMIT 1);

INSERT INTO `save_the_date_details` (`text`, `sort_order`)
SELECT 'New Delhi House', 1 FROM DUAL
WHERE (SELECT COUNT(*) FROM `save_the_date_details`) < 2;

INSERT INTO `save_the_date_details` (`text`, `sort_order`)
SELECT 'Afternoon', 2 FROM DUAL
WHERE (SELECT COUNT(*) FROM `save_the_date_details`) < 3;

INSERT INTO `save_the_date_details` (`text`, `sort_order`)
SELECT 'Hindi & English', 3 FROM DUAL
WHERE (SELECT COUNT(*) FROM `save_the_date_details`) < 4;


CREATE TABLE IF NOT EXISTS `seats_urgency_banner` (
  `id` TINYINT UNSIGNED NOT NULL,
  `message_text` VARCHAR(255) NOT NULL DEFAULT '',
  `spots_left` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `progress_percent` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO `seats_urgency_banner` (`id`, `message_text`, `spots_left`, `progress_percent`, `is_active`)
VALUES (1, 'Hurry, seats for Sunday are low......', 30, 90, 1)
ON DUPLICATE KEY UPDATE `id` = `id`;


CREATE TABLE IF NOT EXISTS `registration_settings` (
  `id` TINYINT UNSIGNED NOT NULL,
  `amount_paise` INT UNSIGNED NOT NULL DEFAULT 0,
  `currency` CHAR(3) NOT NULL DEFAULT 'INR',
  `fee_label` VARCHAR(120) NULL DEFAULT NULL,
  `payment_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `payment_url` VARCHAR(500) NULL DEFAULT NULL,
  `workshop_url` VARCHAR(500) NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO `registration_settings` (`id`, `amount_paise`, `currency`, `fee_label`, `payment_enabled`)
VALUES (1, 49900, 'INR', 'Summit Registration Fee', 1)
ON DUPLICATE KEY UPDATE `id` = `id`;


CREATE TABLE IF NOT EXISTS `payment_orders` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `registration_id` BIGINT UNSIGNED NOT NULL,
  `cashfree_order_id` VARCHAR(80) NOT NULL,
  `cashfree_payment_id` VARCHAR(64) NULL DEFAULT NULL,
  `payment_session_id` VARCHAR(255) NOT NULL,
  `amount_paise` INT UNSIGNED NOT NULL,
  `currency` CHAR(3) NOT NULL DEFAULT 'INR',
  `status` ENUM('created', 'paid', 'failed') NOT NULL DEFAULT 'created',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_payment_orders_cashfree_order_id` (`cashfree_order_id`),
  KEY `idx_payment_orders_registration_id` (`registration_id`),
  CONSTRAINT `fk_payment_orders_registration`
    FOREIGN KEY (`registration_id`) REFERENCES `registrations` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(64) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(120) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `last_login_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_admins_username` (`username`),
  UNIQUE KEY `uk_admins_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip_address` VARCHAR(45) NOT NULL,
  `username` VARCHAR(64) NULL DEFAULT NULL,
  `success` TINYINT(1) NOT NULL DEFAULT 0,
  `attempted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_login_attempts_ip_time` (`ip_address`, `attempted_at`),
  KEY `idx_login_attempts_username_time` (`username`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO `admins` (`username`, `email`, `password_hash`, `full_name`, `is_active`)
VALUES (
  'info@indiansmechamber.com',
  'info@indiansmechamber.com',
  '$2y$10$Nm5/Set9/6t8N6joVAvc4.YQQWf5uq5Ef2eVM1CRZYnqL5/0pUff2',
  'Administrator',
  1
)
ON DUPLICATE KEY UPDATE
  password_hash = VALUES(password_hash),
  full_name = VALUES(full_name),
  is_active = 1;
