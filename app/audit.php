<?php
declare(strict_types=1);

function audit_log_event(
  PDO $db,
  ?int $actorUserId,
  string $action,
  ?string $targetType = null,
  ?int $targetId = null,
  array $meta = []
): void {
  $ip = $_SERVER['REMOTE_ADDR'] ?? null;
  $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

  $stmt = $db->prepare("
    INSERT INTO audit_log (actor_user_id, action, target_type, target_id, meta_json, ip_address, user_agent)
    VALUES (:actor, :action, :ttype, :tid, :meta, :ip, :ua)
  ");

  $metaJson = !empty($meta) ? json_encode($meta, JSON_UNESCAPED_SLASHES) : null;

  $stmt->execute([
    ':actor' => $actorUserId,
    ':action' => $action,
    ':ttype' => $targetType,
    ':tid' => $targetId,
    ':meta' => $metaJson,
    ':ip' => $ip ? substr($ip, 0, 45) : null,
    ':ua' => $ua ? substr($ua, 0, 255) : null,
  ]);
}
