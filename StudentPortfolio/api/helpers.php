<?php
// api/helpers.php - ฟังก์ชันช่วยเหลือสำหรับการตรวจสอบและดึงข้อมูลผู้ใช้
declare(strict_types=1);

// ฟังก์ชันดึงข้อมูลผู้ใช้ปัจจุบันจาก session
// ค้นหาผู้ใช้ที่ login อยู่จากฐานข้อมูล
function current_user(mysqli $mysqli): ?array {
  if (session_status() !== PHP_SESSION_ACTIVE) session_start(); // เริ่ม session ถ้ายังไม่เริ่ม
  if (!isset($_SESSION["uid"])) return null; // ถ้าไม่มี uid ใน session คืน null

  $id = intval($_SESSION["uid"]); // ดึง user ID จาก session
  // ค้นหาข้อมูลผู้ใช้จากฐานข้อมูล
  $stmt = $mysqli->prepare("SELECT id,email,name,major,year,bio,avatar_url,role,created_at FROM sp_users WHERE id=?");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $res = $stmt->get_result();
  $u = $res->fetch_assoc(); // ดึงแถวข้อมูลเป็น associative array

  return $u ?: null; // ส่งข้อมูลผู้ใช้หรือ null ถ้าไม่พบ
}

// ฟังก์ชันตรวจสอบว่า user ได้ login แล้วหรือไม่
// ถ้าไม่ได้ login แสดง error 401 Unauthorized
function require_login(mysqli $mysqli): array {
  $u = current_user($mysqli); // ดึงข้อมูลผู้ใช้ปัจจุบัน
  if (!$u) json_out(false, null, "ต้องล็อกอินก่อน", 401); // ถ้าไม่มี user ส่ง error
  return $u; // ส่งข้อมูลผู้ใช้กลับ
}

// ฟังก์ชันตรวจสอบว่า user เป็น admin หรือไม่
// ต้อง login ก่อน และมี role = 'admin'
function require_admin(mysqli $mysqli): array {
  $u = require_login($mysqli); // ตรวจสอบ login ก่อน
  if (($u["role"] ?? "") !== "admin") json_out(false, null, "ต้องเป็นแอดมิน", 403); // ถ้าไม่ใช่ admin ส่ง error 403
  return $u; // ส่งข้อมูล admin กลับ
}

// ฟังก์ชันตรวจสอบว่า user เป็นเจ้าของหรือ admin ของ item นี้
// ใช้สำหรับกำหนดสิทธิ์ในการแก้ไข/ลบของ user
function is_owner_or_admin(array $u, int $ownerId): bool {
  // ส่ง true ถ้า user เป็น admin หรือมี ID เท่ากับ owner ID
  return (($u["role"] ?? "") === "admin") || (intval($u["id"] ?? 0) === intval($ownerId));
}
