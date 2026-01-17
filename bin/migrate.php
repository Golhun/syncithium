<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/migrations/migration_interface.php';
require_once __DIR__ . '/../src/migrations/migrator.php';

$root = dirname(__DIR__);

$cmd = $argv[1] ?? 'help';

// Use the same PDO instance as the web app
$migrator = new Migrator(db(), $root . '/database/migrations');

switch ($cmd) {
    case 'status':
        $rows = $migrator->status();
        foreach ($rows as $r) {
            $flag = $r['ran'] ? 'YES' : 'NO ';
            $batch = $r['batch'] ?? '-';
            $ranAt = $r['ran_at'] ?? '-';
            echo "{$flag}  batch={$batch}  {$ranAt}  {$r['migration']}\n";
        }
        exit(0);

    case 'up':
        $n = $migrator->up();
        echo "Applied {$n} migration(s).\n";
        exit(0);

    case 'down':
        $n = $migrator->down();
        echo "Rolled back {$n} migration(s).\n";
        exit(0);

    case 'fresh':
        $migrator->fresh();
        echo "Fresh complete (rolled back all, then applied all).\n";
        exit(0);

    default:
        echo "Syncithium migrations\n";
        echo "Commands:\n";
        echo "  php bin/migrate.php status\n";
        echo "  php bin/migrate.php up\n";
        echo "  php bin/migrate.php down\n";
        echo "  php bin/migrate.php fresh\n";
        exit(0);
}
