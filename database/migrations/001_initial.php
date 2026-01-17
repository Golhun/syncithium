<?php

require_once __DIR__ . '/../../src/migrations/migration_interface.php';

return new class implements MigrationInterface
{
    public function up(PDO $pdo): void
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(120) NOT NULL,
                    email VARCHAR(190) NOT NULL UNIQUE,
                    password_hash VARCHAR(255) NOT NULL,
                    role VARCHAR(30) NOT NULL DEFAULT 'user',
                    created_at DATETIME NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS question_banks (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    owner_user_id INT NOT NULL,
                    name VARCHAR(140) NOT NULL,
                    description TEXT NULL,
                    created_at DATETIME NOT NULL,
                    FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS questions (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    bank_id INT NOT NULL,
                    subject VARCHAR(120) NOT NULL,
                    topic VARCHAR(255) NOT NULL,
                    stem TEXT NOT NULL,
                    option_a TEXT NOT NULL,
                    option_b TEXT NOT NULL,
                    option_c TEXT NOT NULL,
                    option_d TEXT NOT NULL,
                    correct_option CHAR(1) NOT NULL,
                    explanation TEXT NULL,
                    created_at DATETIME NOT NULL,
                    FOREIGN KEY (bank_id) REFERENCES question_banks(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS tests (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    bank_id INT NOT NULL,
                    mode VARCHAR(20) NOT NULL DEFAULT 'practice',
                    subject_filter VARCHAR(120) NULL,
                    topic_filter VARCHAR(255) NULL,
                    num_questions INT NOT NULL,
                    time_limit_minutes INT NULL,
                    created_at DATETIME NOT NULL,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (bank_id) REFERENCES question_banks(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS test_items (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    test_id INT NOT NULL,
                    question_id INT NOT NULL,
                    position INT NOT NULL,
                    FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE CASCADE,
                    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE RESTRICT,
                    UNIQUE KEY uq_test_position (test_id, position)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS attempts (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    test_id INT NOT NULL,
                    user_id INT NOT NULL,
                    started_at DATETIME NOT NULL,
                    submitted_at DATETIME NULL,
                    score INT NULL,
                    total INT NULL,
                    FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS attempt_answers (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    attempt_id INT NOT NULL,
                    question_id INT NOT NULL,
                    selected_option CHAR(1) NULL,
                    is_correct TINYINT(1) NULL,
                    answered_at DATETIME NULL,
                    FOREIGN KEY (attempt_id) REFERENCES attempts(id) ON DELETE CASCADE,
                    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE RESTRICT,
                    UNIQUE KEY uq_attempt_question (attempt_id, question_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            return;
        }

        // SQLite
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT 'user',
                created_at TEXT NOT NULL
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS question_banks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                owner_user_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                description TEXT NULL,
                created_at TEXT NOT NULL,
                FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS questions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                bank_id INTEGER NOT NULL,
                subject TEXT NOT NULL,
                topic TEXT NOT NULL,
                stem TEXT NOT NULL,
                option_a TEXT NOT NULL,
                option_b TEXT NOT NULL,
                option_c TEXT NOT NULL,
                option_d TEXT NOT NULL,
                correct_option TEXT NOT NULL,
                explanation TEXT NULL,
                created_at TEXT NOT NULL,
                FOREIGN KEY (bank_id) REFERENCES question_banks(id) ON DELETE CASCADE
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tests (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                bank_id INTEGER NOT NULL,
                mode TEXT NOT NULL DEFAULT 'practice',
                subject_filter TEXT NULL,
                topic_filter TEXT NULL,
                num_questions INTEGER NOT NULL,
                time_limit_minutes INTEGER NULL,
                created_at TEXT NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (bank_id) REFERENCES question_banks(id) ON DELETE CASCADE
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS test_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                test_id INTEGER NOT NULL,
                question_id INTEGER NOT NULL,
                position INTEGER NOT NULL,
                FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE CASCADE,
                FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE RESTRICT,
                UNIQUE (test_id, position)
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS attempts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                test_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                started_at TEXT NOT NULL,
                submitted_at TEXT NULL,
                score INTEGER NULL,
                total INTEGER NULL,
                FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS attempt_answers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                attempt_id INTEGER NOT NULL,
                question_id INTEGER NOT NULL,
                selected_option TEXT NULL,
                is_correct INTEGER NULL,
                answered_at TEXT NULL,
                FOREIGN KEY (attempt_id) REFERENCES attempts(id) ON DELETE CASCADE,
                FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE RESTRICT,
                UNIQUE (attempt_id, question_id)
            )
        ");
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS attempt_answers");
        $pdo->exec("DROP TABLE IF EXISTS attempts");
        $pdo->exec("DROP TABLE IF EXISTS test_items");
        $pdo->exec("DROP TABLE IF EXISTS tests");
        $pdo->exec("DROP TABLE IF EXISTS questions");
        $pdo->exec("DROP TABLE IF EXISTS question_banks");
        $pdo->exec("DROP TABLE IF EXISTS users");
    }
};
