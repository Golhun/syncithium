<?php
declare(strict_types=1);

return new class implements MigrationInterface
{
    public function up(PDO $pdo): void
    {
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            // MySQL / MariaDB
            $pdo->exec("
                ALTER TABLE users
                    ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0,
                    ADD COLUMN reset_token_hash VARCHAR(255) NULL,
                    ADD COLUMN reset_token_expires_at DATETIME NULL,
                    ADD COLUMN reset_token_created_at DATETIME NULL
            ");
            return;
        }

        // SQLite (ADD COLUMN only; no multi-add in one statement reliably across versions)
        $pdo->exec("ALTER TABLE users ADD COLUMN must_change_password INTEGER NOT NULL DEFAULT 0");
        $pdo->exec("ALTER TABLE users ADD COLUMN reset_token_hash TEXT NULL");
        $pdo->exec("ALTER TABLE users ADD COLUMN reset_token_expires_at TEXT NULL");
        $pdo->exec("ALTER TABLE users ADD COLUMN reset_token_created_at TEXT NULL");
    }

    public function down(PDO $pdo): void
    {
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("
                ALTER TABLE users
                    DROP COLUMN reset_token_created_at,
                    DROP COLUMN reset_token_expires_at,
                    DROP COLUMN reset_token_hash,
                    DROP COLUMN must_change_password
            ");
            return;
        }

        // SQLite cannot DROP COLUMN without table rebuild.
        // Intentionally a no-op to keep rollbacks safe.
    }
};
