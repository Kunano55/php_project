<?php
// api/upload.php - API สำหรับ upload รูปภาพหลักของ user
declare(strict_types=1);
session_start();
require_once __DIR__ . "/database.php";
require_once __DIR__ . "/helpers.php";

// ตรวจสอบว่า user login แล้ว
require_login($mysqli);

// ตรวจสอบว่า method เป็น POST เท่านั้น
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  json_out(false, null, "ใช้ POST เท่านั้น", 405);
}

// ===== Validation =====
// ตรวจสอบว่ามี file ในการ request
if (!isset($_FILES["file"])) {
  json_out(false, null, "ต้องส่งไฟล์ชื่อ field ว่า file", 400);
}

// ดึงข้อมูล $_FILES
$f = $_FILES["file"];
// ตรวจสอบ error code ระหว่างอัปโหลด (0 = ไม่มี error)
if ($f["error"] !== UPLOAD_ERR_OK) {
  json_out(false, null, "อัปโหลดไม่สำเร็จ (error=" . $f["error"] . ")", 400);
}

// ตรวจสอบ MIME type ว่าเป็น jpg/png/webp เท่านั้น
$allowed = ["image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp"];
$tmp = $f["tmp_name"]; // path ชั่วคราวของไฟล์
$mime = mime_content_type($tmp); // ตรวจสอบ MIME type จากไฟล์จริง (ไม่เชื่อ filename)
if (!isset($allowed[$mime])) {
  json_out(false, null, "รองรับเฉพาะ jpg/png/webp", 400);
}

// ตรวจสอบขนาดไฟล์ (สูงสุด 3MB)
$ext = $allowed[$mime]; // ให้ส่วนขยายตาม MIME type ไม่ใช่จากชื่อไฟล์
$max_bytes = 3 * 1024 * 1024; // 3MB = 3145728 bytes
if (intval($f["size"]) > $max_bytes) {
  json_out(false, null, "ไฟล์ใหญ่เกิน 3MB", 400);
}

// ===== File Upload Process =====
// สร้างโฟลเดอร์ uploads
$upload_dir = __DIR__ . "/../uploads";
if (!is_dir($upload_dir)) {
  // ถ้ายังไม่มีโฟลเดอร์ก็สร้างใหม่ (recursive)
  if (!mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
    json_out(false, null, "สร้างโฟลเดอร์ uploads ไม่สำเร็จ", 500);
  }
}

// ทำให้ path เป็น absolute path เพื่อป้องกัน directory traversal
$upload_dir_real = realpath($upload_dir);
if ($upload_dir_real === false) {
  json_out(false, null, "ไม่พบโฟลเดอร์ uploads", 500);
}

// สร้างชื่อไฟล์ใหม่ของ format: img_YYYYMMDD_HHMMSS_randomhex.ext
// ทำให้ไม่เกิด collision และแต่ละชื่อเป็น unique
$name = "img_" . date("Ymd_His") . "_" . bin2hex(random_bytes(4)) . "." . $ext;
$dest = $upload_dir_real . DIRECTORY_SEPARATOR . $name;

// ย้ายไฟล์จากชั่วคราว (tmp) ไปยังโฟลเดอร์ uploads
if (!move_uploaded_file($tmp, $dest)) {
  json_out(false, null, "ย้ายไฟล์ไม่สำเร็จ", 500);
}

// สร้าง URL ที่ relative (สำหรับเก็บใน database และให้ client ดาวน์โหลด)
// path นี้: ../uploads/filename (ไป 2 ระดับบนจาก public/ ไปยัง uploads/)
$url = "../uploads/" . $name;

// ส่งสำเร็จกับ status 201 Created
json_out(true, [["url" => $url]], "อัปโหลดแล้ว", 201);
