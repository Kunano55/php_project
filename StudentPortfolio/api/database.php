<?php
// api/database.php (PDO)
declare(strict_types=1);

$DB_HOST = getenv('DB_HOST') ?: 'localhost';
$DB_NAME = getenv('DB_NAME') ?: 'it67040233114';
$DB_USER = getenv('DB_USER') ?: 'it67040233114';
$DB_PASS = getenv('DB_PASS') ?: 'X0A8T9V7';
$DB_CHARSET = 'utf8mb4';

$dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset={$DB_CHARSET}";
$options = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES => false,
];

try {
  $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (Exception $e) {
  http_response_code(500);
  header("Content-Type: application/json; charset=utf-8");
  echo json_encode(["ok"=>false,"data"=>null,"message"=>"DB connect failed: ".$e->getMessage()], JSON_UNESCAPED_UNICODE);
  exit;
}

function json_out(bool $ok, $data=null, string $message='', int $code=200): void {
  http_response_code($code);
  header("Content-Type: application/json; charset=utf-8");
  echo json_encode(["ok"=>$ok,"data"=>$data,"message"=>$message], JSON_UNESCAPED_UNICODE);
  exit;
}

function read_json_body(): array {
  $raw = file_get_contents("php://input");
  if (!$raw) return [];
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

function cors(): void {
  header("Access-Control-Allow-Origin: *");
  header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
  header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
  if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") exit;
}

cors();
