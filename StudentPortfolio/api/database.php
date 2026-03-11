<?php
// api/database.php (MySQLi) - ไฟล์เชื่อมต่อฐานข้อมูล
// ไฟล์นี้ตั้งค่าการเชื่อมต่อกับ MySQL ผ่าน MySQLi extension
declare(strict_types=1);

// ตั้งค่าการเชื่อมต่อฐานข้อมูล จากตัวแปร environment หรือใช้ค่าเริ่มต้น
$DB_HOST = getenv('DB_HOST') ?: 'localhost';
$DB_NAME = getenv('DB_NAME') ?: 'it67040233141';
$DB_USER = getenv('DB_USER') ?: 'it67040233141';
$DB_PASS = getenv('DB_PASS') ?: 'K3D2H5I4';
$DB_CHARSET = 'utf8mb4';

// เปิดใช้งาน MySQLi exception mode เพื่อให้ catch error ได้ (เหมือน PDO)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// สร้างการเชื่อมต่อ MySQLi object
$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
$mysqli->set_charset($DB_CHARSET); // ตั้ง encoding เป็น UTF-8

// ตรวจสอบว่าเชื่อมต่อสำเร็จหรือไม่
if ($mysqli->connect_errno) {
  http_response_code(500);
  header("Content-Type: application/json; charset=utf-8");
  echo json_encode(["ok"=>false,"data"=>null,"message"=>"DB connect failed: " . $mysqli->connect_error], JSON_UNESCAPED_UNICODE);
  exit;
}

// ฟังก์ชันช่วยเหลือสำหรับ prepare + execute โดยอัตโนมัติ detect type
// ใช้เมื่อต้อง bind parameter หลายตัว แต่ไม่อยากเขียน type string ด้วยตนเอง
function mquery(mysqli $mysqli, string $sql, array $params = []): mysqli_stmt {
  $stmt = $mysqli->prepare($sql); // เตรียม SQL statement
  if ($params) {
    $types = ''; // สตริง type: i=integer, d=double, s=string
    $refs = []; // อาร์เรย์ของค่า parameter
    foreach ($params as $p) {
      if (is_int($p)) {
        $types .= 'i'; // integer
      } elseif (is_float($p)) {
        $types .= 'd'; // double/float
      } else {
        $types .= 's'; // string
      }
      $refs[] = $p;
    }
    // bind parameter เป็นอัตโนมัติ
    $stmt->bind_param($types, ...$refs);
  }
  $stmt->execute(); // รันคำสั่ง
  return $stmt; // ส่ง statement object กลับ
}

// ฟังก์ชันส่งผลลัพธ์แบบ JSON กลับไปยัง client
// ใช้สำหรับส่งข้อมูล ข้อความสำเร็จ หรือข้อผิดพลาด
function json_out(bool $ok, $data=null, string $message='', int $code=200): void {
  http_response_code($code); // ตั้ง HTTP status code (200, 201, 400, 404, 500, etc.)
  header("Content-Type: application/json; charset=utf-8");
  // ส่ง JSON ที่มี ok, data, message 
  echo json_encode(["ok"=>$ok,"data"=>$data,"message"=>$message], JSON_UNESCAPED_UNICODE);
  exit; // จบการทำงาน script
}

// ฟังก์ชันอ่านข้อมูล JSON จาก request body
// ใช้เมื่อ client ส่ง POST/PUT data แบบ JSON
function read_json_body(): array {
  $raw = file_get_contents("php://input"); // อ่าน raw input
  if (!$raw) return []; // ถ้าไม่มีข้อมูล ส่งอาร์เรย์ว่าง
  $data = json_decode($raw, true); // แปลง JSON string เป็น PHP array
  return is_array($data) ? $data : []; // ถ้าแปลงได้ ส่ง array มิฉะนั้นส่ง array ว่าง
}

// ฟังก์ชันตั้งค่า CORS headers
// อนุญาตให้ frontend จากโดเมนต่างๆ เข้าถึง API นี้
function cors(): void {
  header("Access-Control-Allow-Origin: *"); // อนุญาตทุกโดเมน
  header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With"); // อนุญาต headers
  header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS"); // อนุญาต methods
  // ถ้าเป็น preflight request (OPTIONS) ให้จบการทำงาน
  if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") exit;
}

// เรียกใช้ CORS headers สำหรับทุก request
cors();
