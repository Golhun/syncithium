USE syncithium;

-- 1) Add lockout tracking columns to users
ALTER TABLE users
  ADD COLUMN failed_attempts INT UNSIGNED NOT NULL DEFAULT 0 AFTER must_change_password,
  ADD COLUMN last_failed_at TIMESTAMP NULL DEFAULT NULL AFTER failed_attempts,
  ADD COLUMN lockout_until TIMESTAMP NULL DEFAULT NULL AFTER last_failed_at;

CREATE INDEX idx_users_lockout_until ON users(lockout_until);

-- 2) Minimal audit log table for admin actions
CREATE TABLE IF NOT EXISTS audit_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  actor_user_id BIGINT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  target_type VARCHAR(60) NULL,
  target_id BIGINT UNSIGNED NULL,
  meta_json JSON NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_actor (actor_user_id),
  KEY idx_audit_action (action),
  KEY idx_audit_target (target_type, target_id),
  CONSTRAINT fk_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users(id)
    ON DELETE SET NULL
) ENGINE=InnoDB;
