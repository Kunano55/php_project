<?php
// api/users.php
declare(strict_types=1);
session_start();
require_once __DIR__ . "/database.php";
require_once __DIR__ . "/helpers.php";

$method = $_SERVER["REQUEST_METHOD"];
$body = read_json_body();

if ($method === "GET") {
  // GET /users.php?me=1
  if (isset($_GET["me"]) && $_GET["me"] === "1") {
    $u = current_user($pdo);
    json_out(true, $u ? [$u] : [], "");
  }

  // GET /users.php?public=1
  if (isset($_GET["public"]) && $_GET["public"] === "1") {
    $stmt = $pdo->query("SELECT id,name,major,year,bio,avatar_url,created_at FROM sp_users WHERE role='student' ORDER BY name ASC, id ASC LIMIT 500");
    json_out(true, $stmt->fetchAll(), "");
  }

  // GET /users.php?id=1
  if (isset($_GET["id"])) {
    $id = intval($_GET["id"]);
    $stmt = $pdo->prepare("SELECT id,email,name,major,year,bio,avatar_url,role,created_at FROM sp_users WHERE id=?");
    $stmt->execute([$id]);
    $u = $stmt->fetch();
    json_out(true, $u ? [$u] : [], "");
  }

  // GET /users.php (admin list)
  require_admin($pdo);
  $stmt = $pdo->query("SELECT id,email,name,major,year,role,created_at FROM sp_users ORDER BY id DESC LIMIT 200");
  json_out(true, $stmt->fetchAll(), "");
}

if ($method === "PUT") {
  // update profile (owner or admin)
  $u = require_login($pdo);
  $id = intval($body["id"] ?? 0);
  if ($id <= 0) json_out(false, null, "ต้องส่ง id", 400);
  if (!is_owner_or_admin($u, $id)) json_out(false, null, "ไม่มีสิทธิ์แก้ไข", 403);

  $name = trim(strval($body["name"] ?? ""));
  $major = trim(strval($body["major"] ?? ""));
  $year = trim(strval($body["year"] ?? ""));
  $bio = trim(strval($body["bio"] ?? ""));
  $avatar_url = trim(strval($body["avatar_url"] ?? ""));

  $stmt = $pdo->prepare("UPDATE sp_users SET name=?, major=?, year=?, bio=?, avatar_url=? WHERE id=?");
  $stmt->execute([$name, $major, $year, $bio, $avatar_url, $id]);

  json_out(true, null, "อัปเดตแล้ว");
}

if ($method === "DELETE") {
  // delete user (admin only)
  require_admin($pdo);
  $id = intval($_GET["id"] ?? 0);
  if ($id <= 0) json_out(false, null, "ต้องส่ง id", 400);
  $stmt = $pdo->prepare("DELETE FROM sp_users WHERE id=?");
  $stmt->execute([$id]);
  json_out(true, null, "ลบแล้ว");
}

json_out(false, null, "Not found", 404);
