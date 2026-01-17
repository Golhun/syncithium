-- Syncithium (Quiz-first) schema
-- MySQL/MariaDB

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  is_admin TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS questions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  subject VARCHAR(120) NOT NULL,
  topic VARCHAR(190) NOT NULL,
  question TEXT NOT NULL,
  option_a TEXT NOT NULL,
  option_b TEXT NOT NULL,
  option_c TEXT NOT NULL,
  option_d TEXT NOT NULL,
  correct_option CHAR(1) NOT NULL,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_subject (subject),
  INDEX idx_topic (topic),
  CONSTRAINT fk_questions_created_by FOREIGN KEY (created_by) REFERENCES users(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  subject VARCHAR(120) NOT NULL,
  topic VARCHAR(190) NOT NULL,
  question_count INT NOT NULL,
  started_at DATETIME NOT NULL,
  submitted_at DATETIME NULL,
  score INT NULL,
  total INT NULL,
  duration_seconds INT NULL,
  INDEX idx_attempt_user (user_id),
  CONSTRAINT fk_attempts_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS attempt_answers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  attempt_id INT NOT NULL,
  question_id INT NOT NULL,
  chosen_option CHAR(1) NULL,
  is_correct TINYINT(1) NULL,
  CONSTRAINT fk_attempt_answers_attempt FOREIGN KEY (attempt_id) REFERENCES attempts(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_attempt_answers_question FOREIGN KEY (question_id) REFERENCES questions(id)
    ON DELETE CASCADE,
  UNIQUE KEY uniq_attempt_question (attempt_id, question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS attempt_questions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  attempt_id INT NOT NULL,
  question_id INT NOT NULL,
  display_order INT NOT NULL,
  CONSTRAINT fk_attempt_questions_attempt FOREIGN KEY (attempt_id) REFERENCES attempts(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_attempt_questions_question FOREIGN KEY (question_id) REFERENCES questions(id)
    ON DELETE CASCADE,
  UNIQUE KEY uniq_attempt_question_link (attempt_id, question_id),
  INDEX idx_attempt_order (attempt_id, display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
