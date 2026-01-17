<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/migrations/migration_interface.php';

return new class implements MigrationInterface
{
    private function driver(PDO $pdo): string
    {
        return (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    public function up(PDO $pdo): void
    {
        $d = $this->driver($pdo);

        if ($d === 'mysql') {
            // Safe, if it already exists MySQL will error, so you may want try/catch in your runner.
            $pdo->exec("CREATE INDEX idx_pr_token_hash ON password_resets(token_hash)");
        } else {
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_pr_token_hash ON password_resets(token_hash)");
        }
    }

    public function down(PDO $pdo): void
    {
        $d = $this->driver($pdo);

        if ($d === 'mysql') {
            $pdo->exec("DROP INDEX idx_pr_token_hash ON password_resets");
        } else {
            $pdo->exec("DROP INDEX IF EXISTS idx_pr_token_hash");
        }
    }
};
