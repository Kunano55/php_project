<?php
// api/users.php - API สำหรับ CRUD user profile
// ได้ข้อมูล user, leaf update profile, delete student (admin only)
declare(strict_types=1);

session_start();
require_once __DIR__ . "/database.php";
require_once __DIR__ . "/helpers.php";

$method = $_SERVER["REQUEST_METHOD"];
$body = read_json_body();

// ===== GET endpoints =====
if ($method === "GET") {
  // GET /users.php?me=1 - ดึงข้อมูลผู้ใช้ปัจจุบัน
  if (isset($_GET["me"]) && $_GET["me"] === "1") {
    $u = current_user($mysqli);
    json_out(true, $u ? [$u] : [], "");
  }

  // GET /users.php?public=1 - ดึงรายชื่อ student ทั้งหมด (สาธารณะ)
  if (isset($_GET["public"]) && $_GET["public"] === "1") {
    $result = $mysqli->query("SELECT id,name,major,year,bio,avatar_url,created_at FROM sp_users WHERE role='student' ORDER BY name ASC, id ASC LIMIT 500");
    json_out(true, $result->fetch_all(MYSQLI_ASSOC), "");
  }

  // GET /users.php?id=1 - ดึงข้อมูล user ตาม id
  if (isset($_GET["id"])) {
    $id = intval($_GET["id"]);
    $stmt = $mysqli->prepare("SELECT id,email,name,major,year,bio,avatar_url,role,created_at FROM sp_users WHERE id=?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    json_out(true, $u ? [$u] : [], "");
  }

  // GET /users.php - ดึงรายชื่อ user ทั้งหมด (admin only)
  require_admin($mysqli);
  $result = $mysqli->query("SELECT id,email,name,major,year,bio,avatar_url,role,created_at FROM sp_users ORDER BY id DESC LIMIT 500");
  json_out(true, $result->fetch_all(MYSQLI_ASSOC), "");
}

// ===== PUT endpoint =====
// PUT /users.php - อัปเดต user profile (เจ้าของหรือ admin)
if ($method === "PUT") {
  $u = require_login($mysqli);
  $id = intval($body["id"] ?? 0);
  if ($id <= 0) json_out(false, null, "ต้องส่ง id", 400);
  if (!is_owner_or_admin($u, $id)) json_out(false, null, "ไม่มีสิทธิ์แก้ไข", 403);

  $isAdmin = (($u["role"] ?? "") === "admin");

  $stmt = $mysqli->prepare("SELECT id FROM sp_users WHERE id=? LIMIT 1");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  if (!$stmt->get_result()->fetch_assoc()) json_out(false, null, "ไม่พบผู้ใช้", 404);

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
    $upd = $mysqli->prepare($sql);
    // bind dynamic parameters
    if ($params) {
      $types = '';
      foreach ($params as $p) {
        if (is_int($p)) $types .= 'i';
        elseif (is_float($p)) $types .= 'd';
        else $types .= 's';
      }
      $upd->bind_param($types, ...$params);
    }
    $upd->execute();
  } catch (Exception $e) {
    json_out(false, null, "อัปเดตไม่สำเร็จ: " . $e->getMessage(), 400);
  }

  json_out(true, null, "อัปเดตแล้ว");
}

if ($method === "DELETE") {
  // delete student (admin only)
  require_admin($mysqli);

  $id = intval($_GET["id"] ?? 0);
  if ($id <= 0) json_out(false, null, "ต้องส่ง id", 400);

  $stmt = $mysqli->prepare("SELECT id,role FROM sp_users WHERE id=? LIMIT 1");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $target = $stmt->get_result()->fetch_assoc();
  if (!$target) json_out(false, null, "ไม่พบผู้ใช้", 404);
  if (($target["role"] ?? "") !== "student") json_out(false, null, "ลบได้เฉพาะนักศึกษา", 400);

  try {
    $mysqli->begin_transaction();

    $delWorks = $mysqli->prepare("DELETE FROM sp_works WHERE user_id=?");
    $delWorks->bind_param('i', $id);
    $delWorks->execute();

    $delUser = $mysqli->prepare("DELETE FROM sp_users WHERE id=?");
    $delUser->bind_param('i', $id);
    $delUser->execute();

    $mysqli->commit();
  } catch (Exception $e) {
    if ($mysqli->in_transaction) $mysqli->rollback();
    json_out(false, null, "ลบไม่สำเร็จ: " . $e->getMessage(), 400);
  }

  json_out(true, null, "ลบแล้ว");
}

json_out(false, null, "Not found", 404);
