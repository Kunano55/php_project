<?php
// api/categories.php
declare(strict_types=1);
session_start();
require_once __DIR__ . "/database.php";
require_once __DIR__ . "/helpers.php";

$method = $_SERVER["REQUEST_METHOD"];
$body = read_json_body();

if ($method === "GET") {
  $stmt = $pdo->query("SELECT id,name FROM sp_categories ORDER BY name ASC");
  json_out(true, $stmt->fetchAll(), "");
}

if ($method === "POST") {
  $u = require_admin($pdo);
  $name = trim(strval($body["name"] ?? ""));
  if ($name === "") json_out(false, null, "ต้องส่ง name", 400);
  try {
    $stmt = $pdo->prepare("INSERT INTO sp_categories(name) VALUES(?)");
    $stmt->execute([$name]);
    json_out(true, [["id"=>$pdo->lastInsertId(),"name"=>$name]], "เพิ่มแล้ว", 201);
  } catch (Exception $e) {
    json_out(false, null, "เพิ่มไม่สำเร็จ: ชื่ออาจซ้ำ", 400);
  }
}

if ($method === "DELETE") {
  $u = require_admin($pdo);
  $id = intval($_GET["id"] ?? 0);
  if ($id <= 0) json_out(false, null, "ต้องส่ง id", 400);
  $stmt = $pdo->prepare("DELETE FROM sp_categories WHERE id=?");
  $stmt->execute([$id]);
  json_out(true, null, "ลบแล้ว");
}

json_out(false, null, "Not found", 404);
