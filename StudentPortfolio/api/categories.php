<?php
// api/categories.php - API สำหรับ quGetRead/Create/Update/Delete หมวดหมู่ของผลงาน (admin only for C/U/D)
declare(strict_types=1);
session_start();
require_once __DIR__ . "/database.php";
require_once __DIR__ . "/helpers.php";

$method = $_SERVER["REQUEST_METHOD"];
$body = read_json_body();

// GET /categories.php - ดึงรายการหมวดหมู่ทั้งหมด
if ($method === "GET") {
  $result = $mysqli->query("SELECT id,name FROM sp_categories ORDER BY name ASC");
  json_out(true, $result->fetch_all(MYSQLI_ASSOC), "");
}

// POST /categories.php - เพิ่มหมวดหมู่ใหม่ (admin only)
if ($method === "POST") {
  $u = require_admin($mysqli); // ตรวจสอบ admin
  $name = trim(strval($body["name"] ?? ""));
  if ($name === "") json_out(false, null, "ต้องส่ง name", 400);
  try {
    // เพิ่มหมวดใหม่
    $stmt = $mysqli->prepare("INSERT INTO sp_categories(name) VALUES(?)");
    $stmt->bind_param('s', $name);
    $stmt->execute();
    json_out(true, [["id"=>$mysqli->insert_id,"name"=>$name]], "เพิ่มแล้ว", 201);
  } catch (Exception $e) {
    json_out(false, null, "เพิ่มไม่สำเร็จ: ชื่ออาจซ้ำ", 400);
  }
}

// DELETE /categories.php?id=X - ลบหมวดหมู่ (admin only)
if ($method === "DELETE") {
  $u = require_admin($mysqli);
  $id = intval($_GET["id"] ?? 0);
  if ($id <= 0) json_out(false, null, "ต้องส่ง id", 400);
  // ลบหมวดตาม id
  $stmt = $mysqli->prepare("DELETE FROM sp_categories WHERE id=?");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  json_out(true, null, "ลบแล้ว");
}

// PUT /categories.php - แก้ไขชื่อหมวดหมู่ (admin only)
if ($method === "PUT") {
  $u = require_admin($mysqli);
  $id = intval($body["id"] ?? 0);
  $name = trim(strval($body["name"] ?? ""));
  
  if ($id <= 0) json_out(false, null, "ต้องส่ง id", 400);
  if ($name === "") json_out(false, null, "ต้องส่ง name", 400);
  
  try {
    // อัปเดตชื่อหมวด
    $stmt = $mysqli->prepare("UPDATE sp_categories SET name=? WHERE id=?");
    $stmt->bind_param('si', $name, $id);
    $stmt->execute();
    json_out(true, [["id" => $id, "name" => $name]], "แก้ไขแล้ว");
  } catch (Exception $e) {
    json_out(false, null, "แก้ไขไม่สำเร็จ: ชื่ออาจซ้ำ", 400);
  }
}

json_out(false, null, "Not found", 404);
