<?php
// api/users.php
declare(strict_types=1);

session_start();
require_once __DIR__ . "/database.php";
require_once __DIR__ . "/helpers.php";

$method = $_SERVER["REQUEST_METHOD"];
$body = read_json_body();

if ($method === "GET") {
  // GET /users.php?me=1
  if (isset($_GET["me"]) && $_GET["me"] === "1") {
    $u = current_user($pdo);
    json_out(true, $u ? [$u] : [], "");
  }

  // GET /users.php?public=1
  if (isset($_GET["public"]) && $_GET["public"] === "1") {
    $stmt = $pdo->query("SELECT id,name,major,year,bio,avatar_url,created_at FROM sp_users WHERE role='student' ORDER BY name ASC, id ASC LIMIT 500");
    json_out(true, $stmt->fetchAll(), "");
  }

  // GET /users.php?id=1
  if (isset($_GET["id"])) {
    $id = intval($_GET["id"]);
    $stmt = $pdo->prepare("SELECT id,email,name,major,year,bio,avatar_url,role,created_at FROM sp_users WHERE id=?");
    $stmt->execute([$id]);
    $u = $stmt->fetch();
    json_out(true, $u ? [$u] : [], "");
  }

  // GET /users.php (admin list)
  require_admin($pdo);
  $stmt = $pdo->query("SELECT id,email,name,major,year,bio,avatar_url,role,created_at FROM sp_users ORDER BY id DESC LIMIT 500");
  json_out(true, $stmt->fetchAll(), "");
}

if ($method === "PUT") {
  // update profile (owner or admin)
  $u = require_login($pdo);
  $id = intval($body["id"] ?? 0);
  if ($id <= 0) json_out(false, null, "ต้องส่ง id", 400);
  if (!is_owner_or_admin($u, $id)) json_out(false, null, "ไม่มีสิทธิ์แก้ไข", 403);

  $isAdmin = (($u["role"] ?? "") === "admin");

  $stmt = $pdo->prepare("SELECT id FROM sp_users WHERE id=? LIMIT 1");
  $stmt->execute([$id]);
  if (!$stmt->fetch()) json_out(false, null, "ไม่พบผู้ใช้", 404);

  $fields = [];
  $params = [];

  if (array_key_exists("email", $body)) {
    if (!$isAdmin) json_out(false, null, "เฉพาะแอดมินเท่านั้นที่แก้ email ได้", 403);
    $email = trim(strval($body["email"] ?? ""));
    if ($email === "") json_out(false, null, "email ห้ามว่าง", 400);
    $fields[] = "email=?";
    $params[] = $email;
  }

  $map = ["name", "major", "year", "bio", "avatar_url"];
  foreach ($map as $key) {
    if (array_key_exists($key, $body)) {
      $fields[] = "{$key}=?";
      $params[] = trim(strval($body[$key]));
    }
  }

  if (count($fields) === 0) json_out(false, null, "ไม่มีฟิลด์ให้อัปเดต", 400);

  $params[] = $id;
  $sql = "UPDATE sp_users SET " . implode(", ", $fields) . " WHERE id=?";

  try {
    $upd = $pdo->prepare($sql);
    $upd->execute($params);
  } catch (Exception $e) {
    json_out(false, null, "อัปเดตไม่สำเร็จ: " . $e->getMessage(), 400);
  }

  json_out(true, null, "อัปเดตแล้ว");
}

if ($method === "DELETE") {
  // delete student (admin only)
  require_admin($pdo);

  $id = intval($_GET["id"] ?? 0);
  if ($id <= 0) json_out(false, null, "ต้องส่ง id", 400);

  $stmt = $pdo->prepare("SELECT id,role FROM sp_users WHERE id=? LIMIT 1");
  $stmt->execute([$id]);
  $target = $stmt->fetch();
  if (!$target) json_out(false, null, "ไม่พบผู้ใช้", 404);
  if (($target["role"] ?? "") !== "student") json_out(false, null, "ลบได้เฉพาะนักศึกษา", 400);

  try {
    $pdo->beginTransaction();

    $delWorks = $pdo->prepare("DELETE FROM sp_works WHERE user_id=?");
    $delWorks->execute([$id]);

    $delUser = $pdo->prepare("DELETE FROM sp_users WHERE id=?");
    $delUser->execute([$id]);

    $pdo->commit();
  } catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_out(false, null, "ลบไม่สำเร็จ: " . $e->getMessage(), 400);
  }

  json_out(true, null, "ลบแล้ว");
}

json_out(false, null, "Not found", 404);
