<?php

final class Migrator
{
    private PDO $pdo;
    private string $migrationsDir;

    public function __construct(PDO $pdo, string $migrationsDir)
    {
        $this->pdo = $pdo;
        $this->migrationsDir = rtrim($migrationsDir, '/');
        $this->ensureMigrationsTable();
    }

    private function ensureMigrationsTable(): void
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS migrations (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    migration VARCHAR(255) NOT NULL UNIQUE,
                    batch INT NOT NULL,
                    ran_at DATETIME NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            return;
        }

        // SQLite
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS migrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration TEXT NOT NULL UNIQUE,
                batch INTEGER NOT NULL,
                ran_at TEXT NOT NULL
            )
        ");
    }

    private function rollback_if_active(): void
    {
        // Some DDL statements may implicitly commit/close a transaction (common in SQLite)
        // so we MUST only rollback when a transaction is actually active.
        try {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
        } catch (Throwable $ignored) {
            // Never allow rollback failure to mask the original error.
        }
    }

    public function status(): array
    {
        $all = $this->listMigrationFiles();
        $ran = $this->ranMigrations();

        $rows = [];
        foreach ($all as $file) {
            $name = basename($file);
            $rows[] = [
                'migration' => $name,
                'ran' => isset($ran[$name]),
                'batch' => $ran[$name]['batch'] ?? null,
                'ran_at' => $ran[$name]['ran_at'] ?? null,
            ];
        }
        return $rows;
    }

    public function up(): int
    {
        $all = $this->listMigrationFiles();
        $ran = $this->ranMigrations();

        $pending = [];
        foreach ($all as $file) {
            $name = basename($file);
            if (!isset($ran[$name])) {
                $pending[] = $file;
            }
        }

        if (!$pending) {
            return 0;
        }

        $batch = $this->nextBatchNumber();
        $applied = 0;

        foreach ($pending as $file) {
            $name = basename($file);
            $migration = $this->loadMigration($file);

            try {
                $this->pdo->beginTransaction();

                $migration->up($this->pdo);

                $stmt = $this->pdo->prepare("
                    INSERT INTO migrations (migration, batch, ran_at)
                    VALUES (:migration, :batch, :ran_at)
                ");
                $stmt->execute([
                    ':migration' => $name,
                    ':batch' => $batch,
                    ':ran_at' => date('Y-m-d H:i:s'),
                ]);

                // If the driver implicitly committed, commit() might fail, so guard it.
                if ($this->pdo->inTransaction()) {
                    $this->pdo->commit();
                }

                $applied++;
            } catch (Throwable $e) {
                $this->rollback_if_active();
                throw $e; // let the real error surface
            }
        }

        return $applied;
    }

    public function down(): int
    {
        $lastBatch = $this->lastBatchNumber();
        if ($lastBatch === null) {
            return 0;
        }

        $stmt = $this->pdo->prepare("
            SELECT migration FROM migrations
            WHERE batch = :batch
            ORDER BY id DESC
        ");
        $stmt->execute([':batch' => $lastBatch]);
        $toRollback = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!$toRollback) {
            return 0;
        }

        $rolledBack = 0;

        foreach ($toRollback as $migrationName) {
            $file = $this->migrationsDir . '/' . $migrationName;
            if (!is_file($file)) {
                throw new RuntimeException("Missing migration file for rollback: {$migrationName}");
            }

            $migration = $this->loadMigration($file);

            try {
                $this->pdo->beginTransaction();

                $migration->down($this->pdo);

                $del = $this->pdo->prepare("DELETE FROM migrations WHERE migration = :m");
                $del->execute([':m' => $migrationName]);

                if ($this->pdo->inTransaction()) {
                    $this->pdo->commit();
                }

                $rolledBack++;
            } catch (Throwable $e) {
                $this->rollback_if_active();
                throw $e;
            }
        }

        return $rolledBack;
    }

    public function fresh(): void
    {
        while ($this->lastBatchNumber() !== null) {
            $this->down();
        }
        $this->up();
    }

    private function listMigrationFiles(): array
    {
        if (!is_dir($this->migrationsDir)) {
            throw new RuntimeException("Migrations directory not found: {$this->migrationsDir}");
        }

        $files = glob($this->migrationsDir . '/*.php') ?: [];
        sort($files, SORT_NATURAL);
        return $files;
    }

    private function ranMigrations(): array
    {
        $rows = $this->pdo->query("SELECT migration, batch, ran_at FROM migrations")->fetchAll();
        $map = [];
        foreach ($rows as $r) {
            $map[$r['migration']] = $r;
        }
        return $map;
    }

    private function nextBatchNumber(): int
    {
        $row = $this->pdo->query("SELECT MAX(batch) AS b FROM migrations")->fetch();
        $max = ($row && $row['b'] !== null) ? (int)$row['b'] : 0;
        return $max + 1;
    }

    private function lastBatchNumber(): ?int
    {
        $row = $this->pdo->query("SELECT MAX(batch) AS b FROM migrations")->fetch();
        if (!$row || $row['b'] === null) {
            return null;
        }
        return (int)$row['b'];
    }

    private function loadMigration(string $file): MigrationInterface
    {
        $migration = require $file;

        if (!$migration instanceof MigrationInterface) {
            throw new RuntimeException("Migration file must return MigrationInterface: " . basename($file));
        }

        return $migration;
    }
}
