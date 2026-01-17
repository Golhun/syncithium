<?php
declare(strict_types=1);

return [
  'up' => "
    CREATE TABLE IF NOT EXISTS questions (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      topic_id BIGINT UNSIGNED NOT NULL,
      question_hash CHAR(64) NOT NULL,
      question_text TEXT NOT NULL,
      option_a TEXT NOT NULL,
      option_b TEXT NOT NULL,
      option_c TEXT NOT NULL,
      option_d TEXT NOT NULL,
      correct_option CHAR(1) NOT NULL,
      explanation TEXT NULL,
      status VARCHAR(16) NOT NULL DEFAULT 'active',
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      CONSTRAINT fk_questions_topic_id
        FOREIGN KEY (topic_id) REFERENCES topics(id)
        ON DELETE CASCADE,
      UNIQUE KEY uq_questions_topic_hash (topic_id, question_hash),
      KEY idx_questions_topic_id (topic_id),
      KEY idx_questions_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ",
  'down' => "
    DROP TABLE IF EXISTS questions;
  ",
];
