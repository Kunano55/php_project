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

if ($method === "GET") {
  // GET /work-images.php?work_id=X -- fetch all images for a work
  if (isset($_GET["work_id"])) {
    $work_id = intval($_GET["work_id"]);
    if ($work_id <= 0) json_out(false, null, "ต้องส่ง work_id", 400);

    $stmt = $mysqli->prepare("SELECT id, work_id, image_url, created_at FROM sp_work_images WHERE work_id=? ORDER BY created_at ASC");
    $stmt->bind_param('i', $work_id);
    $stmt->execute();
    $images = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    json_out(true, $images, "");
  }

  json_out(false, null, "Not found", 404);
}

if ($method === "POST") {
  // POST /work-images.php -- add image to work
  $u = require_login($mysqli);

  $work_id = intval($_POST["work_id"] ?? 0);
  if ($work_id <= 0) json_out(false, null, "ต้องส่ง work_id", 400);

  // Verify user owns the work
  $stmt = $mysqli->prepare("SELECT user_id FROM sp_works WHERE id=?");
  $stmt->bind_param('i', $work_id);
  $stmt->execute();
  $work = $stmt->get_result()->fetch_assoc();
  if (!$work) json_out(false, null, "ไม่พบผลงาน", 404);

  $isOwner = intval($u["id"]) === intval($work["user_id"]);
  $isAdmin = ($u["role"] ?? "") === "admin";
  if (!$isOwner && !$isAdmin) {
    json_out(false, null, "ต้องเป็นเจ้าของงานหรือแอดมิน", 403);
  }

  if (!isset($_FILES["file"])) {
    json_out(false, null, "ต้องส่งไฟล์ชื่อ field ว่า file", 400);
  }

  $f = $_FILES["file"];
  if ($f["error"] !== UPLOAD_ERR_OK) {
    json_out(false, null, "อัปโหลดไม่สำเร็จ (error=" . $f["error"] . ")", 400);
  }

  $allowed = ["image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp"];
  $tmp = $f["tmp_name"];
  $mime = mime_content_type($tmp);
  if (!isset($allowed[$mime])) {
    json_out(false, null, "รองรับเฉพาะ jpg/png/webp", 400);
  }

  $ext = $allowed[$mime];
  $max_bytes = 3 * 1024 * 1024; // 3MB
  if (intval($f["size"]) > $max_bytes) {
    json_out(false, null, "ไฟล์ใหญ่เกิน 3MB", 400);
  }

  $upload_dir = __DIR__ . "/../uploads";
  if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
      json_out(false, null, "สร้างโฟลเดอร์ uploads ไม่สำเร็จ", 500);
    }
  }

  $upload_dir_real = realpath($upload_dir);
  if ($upload_dir_real === false) {
    json_out(false, null, "ไม่พบโฟลเดอร์ uploads", 500);
  }

  $name = "img_" . date("Ymd_His") . "_" . bin2hex(random_bytes(4)) . "." . $ext;
  $dest = $upload_dir_real . DIRECTORY_SEPARATOR . $name;

  if (!move_uploaded_file($tmp, $dest)) {
    json_out(false, null, "ย้ายไฟล์ไม่สำเร็จ", 500);
  }

  $url = "../uploads/" . $name;

  try {
    $ins = $mysqli->prepare("INSERT INTO sp_work_images(work_id, image_url) VALUES(?, ?)");
    $ins->bind_param('is', $work_id, $url);
    $ins->execute();
    $imageId = $mysqli->insert_id;
    json_out(true, [["id" => $imageId, "work_id" => $work_id, "image_url" => $url]], "อัปโหลดแล้ว", 201);
  } catch (Exception $e) {
    json_out(false, null, "บันทึกข้อมูลไม่สำเร็จ: " . $e->getMessage(), 400);
  }
}

if ($method === "DELETE") {
  // DELETE /work-images.php?id=X -- delete image by id
  $u = require_login($mysqli);

  $id = intval($_GET["id"] ?? 0);
  if ($id <= 0) json_out(false, null, "ต้องส่ง id", 400);

  $stmt = $mysqli->prepare("SELECT work_id FROM sp_work_images WHERE id=?");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $img = $stmt->get_result()->fetch_assoc();
  if (!$img) json_out(false, null, "ไม่พบรูปภาพ", 404);

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
