-- 004_create_questions.sql
-- Question bank core table with hash-based dedupe per topic

CREATE TABLE IF NOT EXISTS questions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  topic_id BIGINT UNSIGNED NOT NULL,

  question_text TEXT NOT NULL,
  option_a TEXT NOT NULL,
  option_b TEXT NOT NULL,
  option_c TEXT NOT NULL,
  option_d TEXT NOT NULL,

  correct_option CHAR(1) NOT NULL, -- A/B/C/D
  explanation TEXT NULL,

  status ENUM('active','inactive') NOT NULL DEFAULT 'active',

  question_hash CHAR(64) NOT NULL,

  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  KEY idx_questions_topic (topic_id),
  KEY idx_questions_status (status),
  KEY idx_questions_hash (question_hash),

  UNIQUE KEY uq_questions_topic_hash (topic_id, question_hash),

  CONSTRAINT fk_questions_topic
    FOREIGN KEY (topic_id) REFERENCES topics(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
