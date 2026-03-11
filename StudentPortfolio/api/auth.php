<?php
// api/auth.php - API สำหรับ login, logout, register และตรวจสอบผู้ใช้ปัจจุบัน
// ใช้ session-based authentication
declare(strict_types=1);

session_start(); // เริ่ม PHP session
require_once __DIR__ . "/database.php"; // นำเข้า MySQLi connection
require_once __DIR__ . "/helpers.php"; // นำเข้าฟังก์ชันช่วยเหลือ

$method = $_SERVER["REQUEST_METHOD"]; // ดึง HTTP method (GET/POST/PUT/DELETE)
$action = $_GET["action"] ?? ""; // ดึง action จาก query string
$body = read_json_body(); // อ่าน JSON body จาก request (สำหรับ POST/PUT)

// GET /auth.php?action=me - ดึงข้อมูลผู้ใช้ปัจจุบันที่ login อยู่
if ($method === "GET" && $action === "me") {
  $u = current_user($mysqli); // ดึงข้อมูลจาก session
  json_out(true, $u ? [$u] : [], ""); // ส่งข้อมูลผู้ใช้ หรือ array ว่างถ้าไม่มี
}

// POST /auth.php?action=login - ตรวจสอบ email และ password แล้ว set session
if ($method === "POST" && $action === "login") {
  $email = trim(strval($body["email"] ?? ""));     // ดึงและทำความสะอาด email
  $password = strval($body["password"] ?? "");   // ดึง password

  // ตรวจสอบว่า email และ password ไม่ว่าง
  if ($email === "" || $password === "") {
    json_out(false, null, "กรอก email และ password", 400);
  }

  // ค้นหา user จาก email
  $stmt = $mysqli->prepare("SELECT * FROM sp_users WHERE email=? LIMIT 1");
  $stmt->bind_param('s', $email);
  $stmt->execute();
  $u = $stmt->get_result()->fetch_assoc();

  // ตรวจสอบว่า user มีอยู่ และ password ถูกต้อง
  if (!$u || !password_verify($password, $u["password_hash"])) {
    json_out(false, null, "อีเมลหรือรหัสผ่านไม่ถูกต้อง", 401);
  }

  // Set session ด้วย user ID
  $_SESSION["uid"] = intval($u["id"]);
  // ส่งข้อมูลผู้ใช้ที่ login สำเร็จ
  json_out(true, [["id" => $u["id"], "email" => $u["email"], "role" => $u["role"]]], "ล็อกอินสำเร็จ");
}

// POST /auth.php?action=logout - ลบ session เพื่อออกจากระบบ
if ($method === "POST" && $action === "logout") {
  $_SESSION = [];           // ลบข้อมูลทั้งหมด в session
  session_destroy();        // ทำลาย session
  json_out(true, null, "ออกจากระบบแล้ว");
}

// POST /auth.php?action=register - สมัครสมาชิกใหม่ (สร้าง user ใหม่เป็น student)
if ($method === "POST" && $action === "register") {
  $email = trim(strval($body["email"] ?? ""));     // ดึงและทำความสะอาด email
  $password = strval($body["password"] ?? "");   // ดึง password
  $name = trim(strval($body["name"] ?? ""));       // ดึงและทำความสะอาด name

  // ตรวจสอบว่าข้อมูลไม่ว่าง
  if ($email === "" || $password === "" || $name === "") {
    json_out(false, null, "กรอก email / password / name", 400);
  }

  // Hash password สำหรับเก็บในฐานข้อมูล
  $hash = password_hash($password, PASSWORD_DEFAULT);

  try {
    // INSERT user ใหม่เป็น 'student' role
    $stmt = $mysqli->prepare("INSERT INTO sp_users(email,password_hash,name,role) VALUES(?,?,?, 'student')");
    $stmt->bind_param('sss', $email, $hash, $name);
    $stmt->execute();
    // ส่งข้อมูลผู้ใช้ที่สมัครสำเร็จ (status 201 Created)
    json_out(true, [["id" => $mysqli->insert_id, "email" => $email, "role" => "student"]], "สมัครสมาชิกสำเร็จ", 201);
  } catch (Exception $e) {
    // error ถ้า email ซ้ำ หรืออื่นๆ
    json_out(false, null, "สมัครไม่สำเร็จ: อีเมลอาจซ้ำ", 400);
  }
}

// ถ้า action ไม่ถูกต้อง ส่ง 404 error
json_out(false, null, "Not found", 404);
