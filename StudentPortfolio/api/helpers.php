<?php
// api/helpers.php
declare(strict_types=1);

function current_user(PDO $pdo): ?array {
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  if (!isset($_SESSION["uid"])) return null;
  $stmt = $pdo->prepare("SELECT id,email,name,major,year,bio,avatar_url,role,created_at FROM sp_users WHERE id=?");
  $stmt->execute([intval($_SESSION["uid"])]);
  $u = $stmt->fetch();
  return $u ?: null;
}

function require_login(PDO $pdo): array {
  $u = current_user($pdo);
  if (!$u) json_out(false, null, "ต้องล็อกอินก่อน", 401);
  return $u;
}

function require_admin(PDO $pdo): array {
  $u = require_login($pdo);
  if (($u["role"] ?? "") !== "admin") json_out(false, null, "ต้องเป็นแอดมิน", 403);
  return $u;
}

function is_owner_or_admin(array $u, int $ownerId): bool {
  return (($u["role"] ?? "") === "admin") || (intval($u["id"] ?? 0) === intval($ownerId));
}
