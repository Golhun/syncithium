<?php
require_admin();

$title = 'Admin: Import questions';
$base = rtrim(base_url(), '/');
$pdo  = db();

$stats = null;
$errors = [];


function normalize_correct_option(string $v): ?string {
    $v = strtoupper(trim($v));
    if (in_array($v, ['A','B','C','D'], true)) return $v;
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_abort();

    if (empty($_FILES['csv_file']) || ($_FILES['csv_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $errors[] = 'Please upload a valid CSV file.';
    } else {
        $tmp = $_FILES['csv_file']['tmp_name'];
        $fh = fopen($tmp, 'r');
        if (!$fh) {
            $errors[] = 'Unable to read the uploaded file.';
        } else {
            $header = fgetcsv($fh);
            if (!$header) {
                $errors[] = 'CSV appears to be empty.';
            } else {
                $expected = ['subject','topic','question','option_a','option_b','option_c','option_d','correct_option'];
                $header_norm = array_map(fn($h) => strtolower(trim((string)$h)), $header);

                // Allow extra columns, but we must find expected ones.
                $idx = [];
                foreach ($expected as $col) {
                    $pos = array_search($col, $header_norm, true);
                    if ($pos === false) {
                        $errors[] = "Missing required column: {$col}";
                    } else {
                        $idx[$col] = $pos;
                    }
                }

                if (!$errors) {
                    $inserted = 0;
                    $skipped = 0;
                    $line = 1;

                    $stmt = $pdo->prepare(
                        'INSERT INTO questions (subject, topic, question_text, option_a, option_b, option_c, option_d, correct_option)
                         VALUES (:subject, :topic, :question_text, :a, :b, :c, :d, :correct)'
                    );

                    while (($row = fgetcsv($fh)) !== false) {
                        $line++;
                        $subject = trim((string)($row[$idx['subject']] ?? ''));
                        $topic = trim((string)($row[$idx['topic']] ?? ''));
                        $question = trim((string)($row[$idx['question']] ?? ''));
                        $a = trim((string)($row[$idx['option_a']] ?? ''));
                        $b = trim((string)($row[$idx['option_b']] ?? ''));
                        $c = trim((string)($row[$idx['option_c']] ?? ''));
                        $d = trim((string)($row[$idx['option_d']] ?? ''));
                        $correct = normalize_correct_option((string)($row[$idx['correct_option']] ?? ''));

                        if ($subject === '' || $question === '' || $a === '' || $b === '' || $c === '' || $d === '' || !$correct) {
                            $skipped++;
                            continue;
                        }

                        try {
                            $stmt->execute([
                                ':subject' => $subject,
                                ':topic' => $topic !== '' ? $topic : null,
                                ':question_text' => $question,
                                ':a' => $a,
                                ':b' => $b,
                                ':c' => $c,
                                ':d' => $d,
                                ':correct' => $correct,
                            ]);
                            $inserted++;
                        } catch (Throwable $e) {
                            // Likely a duplicate (unique index) or invalid encoding. Skip.
                            $skipped++;
                        }
                    }

                    $stats = ['inserted' => $inserted, 'skipped' => $skipped];
                }
            }
            fclose($fh);
        }
    }
}

ob_start();
?>
  <h1>Import questions (CSV)</h1>
  <p class="muted">Only Admins can import. Use the template columns: <code>subject, topic, question, option_a, option_b, option_c, option_d, correct_option</code>.</p>

  <?php if ($stats): ?>
    <div class="card">
      <p><strong>Import completed.</strong></p>
      <p>Inserted: <?= (int)$stats['inserted'] ?>, Skipped: <?= (int)$stats['skipped'] ?></p>
    </div>
  <?php endif; ?>

  <?php if ($errors): ?>
    <div class="card" style="border-left:4px solid #ef4444;">
      <p><strong>Fix the following:</strong></p>
      <ul>
        <?php foreach ($errors as $err): ?>
          <li><?= e($err) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="card">
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <label>CSV file
        <input class="input" type="file" name="csv_file" accept=".csv" required>
      </label>
      <div style="margin-top:12px;">
        <button class="btn" type="submit">Import</button>
        <a class="btn secondary" href="<?= e($base) ?>/index.php?r=home">Back</a>
      </div>
    </form>

    <p class="muted" style="margin-top:12px;">Sample CSV: <code>storage/uploads/sample_questions.csv</code></p>
  </div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../views/layout.php';
