-- ============================================
-- Quiz engine tables for Syncithium
-- attempts + attempt_questions
-- ============================================

USE `syncithium`;

-- ----------------------------
-- Table: attempts
-- ----------------------------
CREATE TABLE IF NOT EXISTS `attempts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `scoring_mode` ENUM('standard','negative') NOT NULL DEFAULT 'standard',
  `timer_seconds` INT UNSIGNED NOT NULL,
  `status` ENUM('in_progress','submitted','expired') NOT NULL DEFAULT 'in_progress',
  `started_at` DATETIME NOT NULL,
  `submitted_at` DATETIME NULL,
  `score` INT NOT NULL DEFAULT 0,
  `total_questions` INT UNSIGNED NOT NULL DEFAULT 0,
  `raw_correct` INT UNSIGNED NOT NULL DEFAULT 0,
  `raw_wrong` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_attempts_user` (`user_id`)
) ENGINE=InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- ----------------------------
-- Table: attempt_questions
-- ----------------------------
CREATE TABLE IF NOT EXISTS `attempt_questions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `attempt_id` INT UNSIGNED NOT NULL,
  `question_id` INT UNSIGNED NOT NULL,
  `position` INT UNSIGNED NOT NULL,
  `selected_option` CHAR(1) NULL,
  `is_correct` TINYINT(1) NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_attempt_questions_attempt` (`attempt_id`),
  KEY `idx_attempt_questions_question` (`question_id`)
) ENGINE=InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;
