<?php
declare(strict_types=1);

return [

  'taxonomy_selector' => function (PDO $db, array $config): void {
    $u = require_login($db);

    $stmt = $db->prepare("SELECT * FROM levels ORDER BY CAST(code AS UNSIGNED), code");
    $stmt->execute();
    $levels = $stmt->fetchAll() ?: [];

    render('user/taxonomy_selector', [
      'title' => 'Choose Topics',
      'levels' => $levels,
      'user' => $u,
    ]);
  },

];
