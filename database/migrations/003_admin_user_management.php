<?php

return new class implements MigrationInterface
{
    private function driver(PDO $pdo): string
    {
        return (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    private function hasColumn(PDO $pdo, string $table, string $col): bool
    {
        $d = $this->driver($pdo);

        if ($d === 'mysql') {
            $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE :c");
            $stmt->execute([':c' => $col]);
            return (bool)$stmt->fetch();
        }

        // sqlite
        $stmt = $pdo->query("PRAGMA table_info({$table})");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($rows as $r) {
            if (($r['name'] ?? '') === $col) return true;
        }
        return false;
    }

    private function addColumn(PDO $pdo, string $table, string $sqlFragment): void
    {
        // SQL fragment must be: "<col> <type> ..."
        $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$sqlFragment}");
    }

    public function up(PDO $pdo): void
    {
        // Ensure users table exists (minimal guard)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                created_at TEXT NOT NULL
            )
        ");

        // Add admin-related columns (safe if already present)
        if (!$this->hasColumn($pdo, 'users', 'is_admin')) {
            $this->addColumn($pdo, 'users', "is_admin INTEGER NOT NULL DEFAULT 0");
        }
        if (!$this->hasColumn($pdo, 'users', 'must_change_password')) {
            $this->addColumn($pdo, 'users', "must_change_password INTEGER NOT NULL DEFAULT 1");
        }
        if (!$this->hasColumn($pdo, 'users', 'disabled_at')) {
            $this->addColumn($pdo, 'users', "disabled_at TEXT NULL");
        }
        if (!$this->hasColumn($pdo, 'users', 'updated_at')) {
            $this->addColumn($pdo, 'users', "updated_at TEXT NULL");
        }

        // Password reset tokens table (admin generated)
        $d = $this->driver($pdo);
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
        // Safe rollback for new table only (columns are left as-is)
        $pdo->exec("DROP TABLE IF EXISTS password_resets");
    }
};
