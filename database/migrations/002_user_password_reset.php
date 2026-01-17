<?php
declare(strict_types=1);

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
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM information_schema.columns
                WHERE table_schema = DATABASE()
                  AND table_name = :t
                  AND column_name = :c
            ");
            $stmt->execute([':t' => $table, ':c' => $col]);
            return ((int)$stmt->fetchColumn() > 0);
        }

        // SQLite
        $stmt = $pdo->query("PRAGMA table_info({$table})");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($rows as $r) {
            if (($r['name'] ?? '') === $col) return true;
        }
        return false;
    }

    public function up(PDO $pdo): void
    {
        // must_change_password
        if (!$this->hasColumn($pdo, 'users', 'must_change_password')) {
            if ($this->driver($pdo) === 'mysql') {
                $pdo->exec("ALTER TABLE `users` ADD COLUMN `must_change_password` TINYINT(1) NOT NULL DEFAULT 0");
            } else {
                $pdo->exec("ALTER TABLE users ADD COLUMN must_change_password INTEGER NOT NULL DEFAULT 0");
            }
        }

        // updated_at
        if (!$this->hasColumn($pdo, 'users', 'updated_at')) {
            if ($this->driver($pdo) === 'mysql') {
                $pdo->exec("ALTER TABLE `users` ADD COLUMN `updated_at` DATETIME NULL");
            } else {
                $pdo->exec("ALTER TABLE users ADD COLUMN updated_at TEXT NULL");
            }
        }

        // disabled_at
        if (!$this->hasColumn($pdo, 'users', 'disabled_at')) {
            if ($this->driver($pdo) === 'mysql') {
                $pdo->exec("ALTER TABLE `users` ADD COLUMN `disabled_at` DATETIME NULL");
            } else {
                $pdo->exec("ALTER TABLE users ADD COLUMN disabled_at TEXT NULL");
            }
        }
    }

    public function down(PDO $pdo): void
    {
        // MySQL can drop columns, SQLite cannot safely without table rebuild.
        if ($this->driver($pdo) === 'mysql') {
            // Drop only if they exist (safe-ish on dev). If you prefer non-destructive rollbacks, make this a no-op.
            $pdo->exec("ALTER TABLE `users` DROP COLUMN `disabled_at`");
            $pdo->exec("ALTER TABLE `users` DROP COLUMN `updated_at`");
            $pdo->exec("ALTER TABLE `users` DROP COLUMN `must_change_password`");
        }
    }
};
