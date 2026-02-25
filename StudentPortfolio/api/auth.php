<?php
// api/auth.php (session-based)
declare(strict_types=1);

session_start();
require_once __DIR__ . "/database.php";
require_once __DIR__ . "/helpers.php";

$method = $_SERVER["REQUEST_METHOD"];
$action = $_GET["action"] ?? "";
$body = read_json_body();

$ADMIN_LOCK_CODE = getenv("ADMIN_LOCK_CODE") ?: "12344";

if ($method === "GET" && $action === "me") {
  $u = current_user($pdo);
  json_out(true, $u ? [$u] : [], "");
}

if ($method === "POST" && $action === "login") {
  $email = trim(strval($body["email"] ?? ""));
  $password = strval($body["password"] ?? "");
  $lockCode = trim(strval($body["lock_code"] ?? ""));

  if ($email === "" || $password === "") {
    json_out(false, null, "กรอก email และ password", 400);
  }

  $stmt = $pdo->prepare("SELECT * FROM sp_users WHERE email=? LIMIT 1");
  $stmt->execute([$email]);
  $u = $stmt->fetch();

  if (!$u || !password_verify($password, $u["password_hash"])) {
    json_out(false, null, "อีเมลหรือรหัสผ่านไม่ถูกต้อง", 401);
  }

  if (($u["role"] ?? "") === "admin") {
    if ($lockCode === "" || !hash_equals($ADMIN_LOCK_CODE, $lockCode)) {
      json_out(false, null, "รหัสล็อคแอดมินไม่ถูกต้อง", 401);
    }
    $_SESSION["admin_lock_ok"] = true;
  } else {
    $_SESSION["admin_lock_ok"] = false;
  }

  $_SESSION["uid"] = intval($u["id"]);
  json_out(true, [["id" => $u["id"], "email" => $u["email"], "role" => $u["role"]]], "ล็อกอินสำเร็จ");
}

if ($method === "POST" && $action === "logout") {
  $_SESSION = [];
  session_destroy();
  json_out(true, null, "ออกจากระบบแล้ว");
}

if ($method === "POST" && $action === "register") {
  $email = trim(strval($body["email"] ?? ""));
  $password = strval($body["password"] ?? "");
  $name = trim(strval($body["name"] ?? ""));

  if ($email === "" || $password === "" || $name === "") {
    json_out(false, null, "กรอก email / password / name", 400);
  }

  $hash = password_hash($password, PASSWORD_DEFAULT);

  try {
    $stmt = $pdo->prepare("INSERT INTO sp_users(email,password_hash,name,role) VALUES(?,?,?, 'student')");
    $stmt->execute([$email, $hash, $name]);
    json_out(true, [["id" => $pdo->lastInsertId(), "email" => $email, "role" => "student"]], "สมัครสมาชิกสำเร็จ", 201);
  } catch (Exception $e) {
    json_out(false, null, "สมัครไม่สำเร็จ: อีเมลอาจซ้ำ", 400);
  }
}

json_out(false, null, "Not found", 404);
