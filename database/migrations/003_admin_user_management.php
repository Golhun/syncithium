<?php
declare(strict_types=1);

return new class implements MigrationInterface
{
    private function driver(PDO $pdo): string
    {
        return (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    private function hasTable(PDO $pdo, string $table): bool
    {
        $d = $this->driver($pdo);

        if ($d === 'mysql') {
            $stmt = $pdo->prepare("SHOW TABLES LIKE :t");
            $stmt->execute([':t' => $table]);
            return (bool)$stmt->fetchColumn();
        }

        // SQLite
        $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=:t");
        $stmt->execute([':t' => $table]);
        return (bool)$stmt->fetchColumn();
    }

    private function hasColumn(PDO $pdo, string $table, string $col): bool
    {
        $d = $this->driver($pdo);

        if ($d === 'mysql') {
            $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE :c");
            $stmt->execute([':c' => $col]);
            return (bool)$stmt->fetch();
        }

        // SQLite
        $stmt = $pdo->query("PRAGMA table_info({$table})");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($rows as $r) {
            if (($r['name'] ?? '') === $col) return true;
        }
        return false;
    }

    private function addColumn(PDO $pdo, string $table, string $sqlFragment): void
    {
        // sqlFragment example: "must_change_password TINYINT(1) NOT NULL DEFAULT 1"
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN {$sqlFragment}");
    }

    public function up(PDO $pdo): void
    {
        // We assume users table already exists (you have schema.sql creating it).
        if (!$this->hasTable($pdo, 'users')) {
            throw new RuntimeException("users table not found. Run your base schema/initial migration first.");
        }

        $d = $this->driver($pdo);

        // Add columns to users if missing
        if (!$this->hasColumn($pdo, 'users', 'must_change_password')) {
            if ($d === 'mysql') {
                $this->addColumn($pdo, 'users', "must_change_password TINYINT(1) NOT NULL DEFAULT 1");
            } else {
                $pdo->exec("ALTER TABLE users ADD COLUMN must_change_password INTEGER NOT NULL DEFAULT 1");
            }
        }

        if (!$this->hasColumn($pdo, 'users', 'disabled_at')) {
            if ($d === 'mysql') {
                $this->addColumn($pdo, 'users', "disabled_at DATETIME NULL");
            } else {
                $pdo->exec("ALTER TABLE users ADD COLUMN disabled_at TEXT NULL");
            }
        }

        if (!$this->hasColumn($pdo, 'users', 'updated_at')) {
            if ($d === 'mysql') {
                $this->addColumn($pdo, 'users', "updated_at DATETIME NULL");
            } else {
                $pdo->exec("ALTER TABLE users ADD COLUMN updated_at TEXT NULL");
            }
        }

        // Create password_resets table
        if ($d === 'mysql') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS password_resets (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    token_hash VARCHAR(255) NOT NULL,
                    expires_at DATETIME NOT NULL,
                    used_at DATETIME NULL,
                    created_at DATETIME NOT NULL,
                    INDEX idx_pr_user (user_id),
                    CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS password_resets (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    token_hash TEXT NOT NULL,
                    expires_at TEXT NOT NULL,
                    used_at TEXT NULL,
                    created_at TEXT NOT NULL,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )
            ");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_pr_user ON password_resets(user_id)");
        }
    }

    public function down(PDO $pdo): void
    {
        // Safe rollback: remove reset table (we do not drop columns to avoid data loss)
        $pdo->exec("DROP TABLE IF EXISTS password_resets");
    }
};
