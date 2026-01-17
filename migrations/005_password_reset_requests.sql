USE syncithium;

CREATE TABLE IF NOT EXISTS password_reset_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NULL,
  email VARCHAR(190) NOT NULL,
  note TEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'open',   -- open, processed, rejected, invalid
  request_ip VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at TIMESTAMP NULL,
  processed_by BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY idx_prr_status_created (status, created_at),
  KEY idx_prr_user (user_id),
  KEY idx_prr_email (email),
  CONSTRAINT fk_prr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
