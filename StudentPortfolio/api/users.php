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
  $id = intval($body["id"] ?? 0); // ดึง user ID ที่จะแก้ไข
  if ($id <= 0) json_out(false, null, "ต้องส่ง id", 400); // ตรวจว่า id ถูกต้อง
  if (!is_owner_or_admin($u, $id)) json_out(false, null, "ไม่มีสิทธิ์แก้ไข", 403); // ตรวจว่าเป็นเจ้าของหรือ admin

  $isAdmin = (($u["role"] ?? "") === "admin"); // ตรวจว่า user ปัจจุบันเป็น admin

  // ตรวจว่า user นี้มีอยู่ในฐานข้อมูล
  $stmt = $mysqli->prepare("SELECT id FROM sp_users WHERE id=? LIMIT 1");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  if (!$stmt->get_result()->fetch_assoc()) json_out(false, null, "ไม่พบผู้ใช้", 404);

  // เตรียมตัวแปรสำหรับสร้าง UPDATE query แบบ dynamic
  $fields = []; // เก็บชื่อ field ที่จะแก้ไข
  $params = []; // เก็บค่า parameter ที่จะแก้ไข

  // แก้ไข email (admin only)
  if (array_key_exists("email", $body)) {
    if (!$isAdmin) json_out(false, null, "เฉพาะแอดมินเท่านั้นที่แก้ email ได้", 403); // เฉพาะ admin ถึงแก้ได้
    $email = trim(strval($body["email"] ?? ""));
    if ($email === "") json_out(false, null, "email ห้ามว่าง", 400);
    $fields[] = "email=?"; // เพิ่ม field ลงใน UPDATE
    $params[] = $email;    // เพิ่มค่าลงใน params
  }

  // แก้ไข profile fields (name, major, year, bio, avatar_url)
  $map = ["name", "major", "year", "bio", "avatar_url"];
  foreach ($map as $key) {
    if (array_key_exists($key, $body)) { // ถ้า body มี field นี้ก็เพิ่มลงใน UPDATE
      $fields[] = "{$key}=?"; // เพิ่ม field=?
      $params[] = trim(strval($body[$key])); // เพิ่มค่าลงใน params
    }
  }

  // ตรวจว่ามี field ให้อัปเดตอย่างน้อย 1 field
  if (count($fields) === 0) json_out(false, null, "ไม่มีฟิลด์ให้อัปเดต", 400);

  // เตรียม params สำหรับ WHERE clause (id ต้องมาสุดท้าย)
  $params[] = $id;
  // สร้าง SQL dynamically จากจำนวน fields ที่จะแก้ไข
  $sql = "UPDATE sp_users SET " . implode(", ", $fields) . " WHERE id=?";

  try {
    // เตรียม statement ด้วย dynamic SQL
    $upd = $mysqli->prepare($sql);
    // bind dynamic parameters โดยใช้ automatic type detection
    if ($params) {
      $types = ''; // สตริงเก็บ type ของแต่ละ parameter
      foreach ($params as $p) {
        if (is_int($p)) $types .= 'i';      // integer
        elseif (is_float($p)) $types .= 'd'; // double
        else $types .= 's';                 // string
      }
      $upd->bind_param($types, ...$params); // bind ทั้งหมดตามลำดับ
    }
    $upd->execute(); // รัน UPDATE
  } catch (Exception $e) {
    // catch error เช่น duplicate email เป็นต้น
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

  // สำเร็จ
  json_out(true, null, "ลบแล้ว");
}

// ถ้า method หรือ endpoint ไม่ใช่ของไฟล์นี้ให้ส่ง 404
json_out(false, null, "Not found", 404);
