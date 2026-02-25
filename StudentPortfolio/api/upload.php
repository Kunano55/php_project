<?php
// api/upload.php
declare(strict_types=1);
session_start();
require_once __DIR__ . "/database.php";
require_once __DIR__ . "/helpers.php";

require_login($pdo);

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  json_out(false, null, "Use POST", 405);
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

$upload_dir = __DIR__ . "/../public/uploads";
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

// Return a portable path that works for all pages under /public.
$url = "uploads/" . $name;

json_out(true, [["url" => $url]], "อัปโหลดแล้ว", 201);
