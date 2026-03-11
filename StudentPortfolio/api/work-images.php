<?php
// api/work-images.php - API สำหรับจัดการรูปภาพของผลงาน (gallery images)\n// สามารถ GET รูปทั้งหมดของงาน, POST เพิ่มรูปใหม่ (ต้อง login), DELETE ลบรูป (ต้องเป็นเจ้าของหรือ admin)
declare(strict_types=1);

session_start();
require_once __DIR__ . "/database.php";
require_once __DIR__ . "/helpers.php";

$method = $_SERVER["REQUEST_METHOD"];

// Ensure table exists
try {
  $mysqli->query("CREATE TABLE IF NOT EXISTS sp_work_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    work_id INT NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (work_id) REFERENCES sp_works(id) ON DELETE CASCADE
  )");
} catch (Exception $e) {
  // Table might already exist, that's OK
}

// ===== GET endpoint =====
// GET /work-images.php?work_id=X - ดึงรูปภาพทั้งหมดของผลงาน
if ($method === "GET") {
  if (isset($_GET["work_id"])) {
    $work_id = intval($_GET["work_id"]); // ดึง work_id จาก query string
    if ($work_id <= 0) json_out(false, null, "ต้องส่ง work_id", 400);

    // คิวรี่ดึงรูปทั้งหมดของผลงาน
    $stmt = $mysqli->prepare("SELECT id, work_id, image_url, created_at FROM sp_work_images WHERE work_id=? ORDER BY created_at ASC");
    $stmt->bind_param('i', $work_id);
    $stmt->execute();
    $images = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); // ส่งรูปทั้งหมดเป็น array
    json_out(true, $images, "");
  }

  json_out(false, null, "Not found", 404);
}

// ===== POST endpoint =====
// POST /work-images.php - อัปโหลดรูปภาพให้กับผลงาน (ต้อง login และเป็นเจ้าของหรือ admin)
if ($method === "POST") {
  $u = require_login($mysqli); // ตรวจสอบ login

  $work_id = intval($_POST["work_id"] ?? 0); // ดึง work_id จาก POST
  if ($work_id <= 0) json_out(false, null, "ต้องส่ง work_id", 400);

  // ตรวจสอบ user เป็นเจ้าของผลงานหรือ admin
  $stmt = $mysqli->prepare("SELECT user_id FROM sp_works WHERE id=?");
  $stmt->bind_param('i', $work_id);
  $stmt->execute();
  $work = $stmt->get_result()->fetch_assoc();
  if (!$work) json_out(false, null, "ไม่พบผลงาน", 404);

  // ตรวจสอบสิทธิ์
  $isOwner = intval($u["id"]) === intval($work["user_id"]); // เป็นเจ้าของ
  $isAdmin = ($u["role"] ?? "") === "admin"; // เป็น admin
  if (!$isOwner && !$isAdmin) {
    json_out(false, null, "ต้องเป็นเจ้าของงานหรือแอดมิน", 403);
  }

  // ตรวจสอบการมี file
  if (!isset($_FILES["file"])) {
    json_out(false, null, "ต้องส่งไฟล์ชื่อ field ว่า file", 400);
  }

  $f = $_FILES["file"];
  // ตรวจสอบ error ระหว่างอัปโหลด
  if ($f["error"] !== UPLOAD_ERR_OK) {
    json_out(false, null, "อัปโหลดไม่สำเร็จ (error=" . $f["error"] . ")", 400);
  }

  // ตรวจสอบ MIME type (รองรับเฉพาะ jpg/png/webp)
  $allowed = ["image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp"];
  $tmp = $f["tmp_name"];
  $mime = mime_content_type($tmp);
  if (!isset($allowed[$mime])) {
    json_out(false, null, "รองรับเฉพาะ jpg/png/webp", 400);
  }

  // ตรวจสอบขนาดไฟล์ (สูงสุด 3MB)
  $ext = $allowed[$mime];
  $max_bytes = 3 * 1024 * 1024; // 3MB
  if (intval($f["size"]) > $max_bytes) {
    json_out(false, null, "ไฟล์ใหญ่เกิน 3MB", 400);
  }

  // สร้าง folder uploads (ถ้ายังไม่มี)
  $upload_dir = __DIR__ . "/../uploads";
  if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
      json_out(false, null, "สร้างโฟลเดอร์ uploads ไม่สำเร็จ", 500);
    }
  }

  // ทำความสะอาดตัวแปลงผู้เสื้อผู้ฉัย (security check)
  $upload_dir_real = realpath($upload_dir);
  if ($upload_dir_real === false) {
    json_out(false, null, "ไม่พบโฟลเดอร์ uploads", 500);
  }

  // สร้างชื่อไฟล์ใหม่ ของ format: img_YYYYMMDD_HHMMSS_randomhex.ext
  $name = "img_" . date("Ymd_His") . "_" . bin2hex(random_bytes(4)) . "." . $ext;
  $dest = $upload_dir_real . DIRECTORY_SEPARATOR . $name;

  // ย้ายไฟล์ไปยังปลายที่ถาวรงไป
  if (!move_uploaded_file($tmp, $dest)) {
    json_out(false, null, "ย้ายไฟล์ไม่สำเร็จ", 500);
  }

  // เก็บ path สำหรับเก็บใน database (relative)
  $url = "../uploads/" . $name;

  // บันทึก record ใหม่ลง database
  try {
    $ins = $mysqli->prepare("INSERT INTO sp_work_images(work_id, image_url) VALUES(?, ?)");
    $ins->bind_param('is', $work_id, $url); // i=integer, s=string
    $ins->execute();
    $imageId = $mysqli->insert_id; // ดึง ID ของรูปที่เปิดค้่า้อง
    // สำเร็จ (status 201 Created)
    json_out(true, [["id" => $imageId, "work_id" => $work_id, "image_url" => $url]], "อัปโหลดแล้ว", 201);
  } catch (Exception $e) {
    json_out(false, null, "บันทึกข้อมูลไม่สำเร็จ: " . $e->getMessage(), 400);
  }
}

// ===== DELETE endpoint =====
// DELETE /work-images.php?id=X - ลบรูปภาพ (id ที่ได้ access token ต้อง)
if ($method === "DELETE") {
  $u = require_login($mysqli); // ตรวจสอบ login

  $id = intval($_GET["id"] ?? 0); // ดึง image ID จาก query string
  if ($id <= 0) json_out(false, null, "ต้องส่ง id", 400);

  // หา record image
  $stmt = $mysqli->prepare("SELECT work_id FROM sp_work_images WHERE id=?");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $img = $stmt->get_result()->fetch_assoc();
  if (!$img) json_out(false, null, "ไม่พบรูปภาพ", 404);

  // ตรวจสอบ user เป็นเจ้าของงาน หรือ admin
  $work_id = intval($img["work_id"]);
  $workStmt = $mysqli->prepare("SELECT user_id FROM sp_works WHERE id=?");
  $workStmt->bind_param('i', $work_id);
  $workStmt->execute();
  $work = $workStmt->get_result()->fetch_assoc();
  if (!$work) json_out(false, null, "ไม่พบผลงาน", 404);

  $isOwner = intval($u["id"]) === intval($work["user_id"]);
  $isAdmin = ($u["role"] ?? "") === "admin";
  if (!$isOwner && !$isAdmin) {
    json_out(false, null, "ต้องเป็นเจ้าของงานหรือแอดมิน", 403);
  }

  // ลบ record จาก database
  try {
    $del = $mysqli->prepare("DELETE FROM sp_work_images WHERE id=?");
    $del->bind_param('i', $id);
    $del->execute();
    json_out(true, null, "ลบแล้ว");
  } catch (Exception $e) {
    json_out(false, null, "ลบไม่สำเร็จ: " . $e->getMessage(), 400);
  }
}

json_out(false, null, "Not found", 404);
