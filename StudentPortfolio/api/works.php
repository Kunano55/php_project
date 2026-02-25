<?php
// api/works.php
declare(strict_types=1);
session_start();
require_once __DIR__ . "/database.php";
require_once __DIR__ . "/helpers.php";

$method = $_SERVER["REQUEST_METHOD"];
$body = read_json_body();

function fetch_works(PDO $pdo, array $opts): array {
  $where = [];
  $params = [];

  if (!empty($opts["only_visible"])) {
    $where[] = "w.is_visible = 1";
  }
  if (!empty($opts["id"])) {
    $where[] = "w.id = ?";
    $params[] = intval($opts["id"]);
  }
  if (!empty($opts["user_id"])) {
    $where[] = "w.user_id = ?";
    $params[] = intval($opts["user_id"]);
  }
  if (!empty($opts["category_id"])) {
    $where[] = "w.category_id = ?";
    $params[] = intval($opts["category_id"]);
  }
  if (!empty($opts["q"])) {
    $where[] = "(w.title LIKE ? OR w.description LIKE ? OR u.name LIKE ?)";
    $q = "%" . $opts["q"] . "%";
    $params[] = $q;
    $params[] = $q;
    $params[] = $q;
  }

  $sql = "SELECT
            w.*,
            c.name AS category_name,
            u.name AS owner_name
          FROM sp_works w
          LEFT JOIN sp_categories c ON c.id = w.category_id
          LEFT JOIN sp_users u ON u.id = w.user_id";

  if (count($where) > 0) {
    $sql .= " WHERE " . implode(" AND ", $where);
  }
  $sql .= " ORDER BY w.created_at DESC LIMIT 500";

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  return $stmt->fetchAll();
}

if ($method === "GET") {
  // admin summary
  if (isset($_GET["summary"]) && $_GET["summary"] === "1") {
    require_admin($pdo);
    $row = $pdo->query("SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN is_visible=1 THEN 1 ELSE 0 END) AS visible,
        SUM(CASE WHEN is_visible=0 THEN 1 ELSE 0 END) AS hidden
      FROM sp_works")->fetch();
    json_out(true, [$row ?: ["total" => 0, "visible" => 0, "hidden" => 0]], "");
  }

  // admin list all
  if (isset($_GET["admin"]) && $_GET["admin"] === "1") {
    require_admin($pdo);
    $data = fetch_works($pdo, [
      "only_visible" => false,
      "q" => ($_GET["q"] ?? ""),
      "category_id" => ($_GET["category_id"] ?? ""),
      "user_id" => ($_GET["user_id"] ?? ""),
    ]);
    json_out(true, $data, "");
  }

  // logged in user's works
  if (isset($_GET["mine"]) && $_GET["mine"] === "1") {
    $u = require_login($pdo);
    $data = fetch_works($pdo, [
      "only_visible" => false,
      "user_id" => intval($u["id"]),
      "q" => ($_GET["q"] ?? ""),
      "category_id" => ($_GET["category_id"] ?? ""),
    ]);
    json_out(true, $data, "");
  }

  // single work detail
  if (isset($_GET["id"]) && intval($_GET["id"]) > 0) {
    $id = intval($_GET["id"]);
    $only_visible = true;

    $u = current_user($pdo);
    if ($u) {
      $stmt = $pdo->prepare("SELECT user_id FROM sp_works WHERE id=?");
      $stmt->execute([$id]);
      $row = $stmt->fetch();
      if ($row && is_owner_or_admin($u, intval($row["user_id"]))) {
        $only_visible = false;
      }
    }

    $data = fetch_works($pdo, [
      "only_visible" => $only_visible,
      "id" => $id,
    ]);
    json_out(true, $data, "");
  }

  // public list
  $opts = [
    "only_visible" => true,
    "q" => $_GET["q"] ?? "",
    "category_id" => $_GET["category_id"] ?? "",
    "user_id" => $_GET["user_id"] ?? "",
  ];
  $data = fetch_works($pdo, $opts);
  json_out(true, $data, "");
}

if ($method === "POST") {
  $u = require_login($pdo);

  $title = trim(strval($body["title"] ?? ""));
  if ($title === "") json_out(false, null, "ต้องส่ง title", 400);

  $owner_id = intval($u["id"]);
  if (($u["role"] ?? "") === "admin" && array_key_exists("user_id", $body)) {
    $owner_id = intval($body["user_id"]);
    if ($owner_id <= 0) json_out(false, null, "user_id ไม่ถูกต้อง", 400);
    $stmt = $pdo->prepare("SELECT id FROM sp_users WHERE id=? LIMIT 1");
    $stmt->execute([$owner_id]);
    if (!$stmt->fetch()) json_out(false, null, "ไม่พบผู้ใช้", 404);
  }

  $category_id = null;
  if (array_key_exists("category_id", $body) && $body["category_id"] !== null && $body["category_id"] !== "") {
    $category_id = intval($body["category_id"]);
  }
  $description = trim(strval($body["description"] ?? ""));
  $cover_url = trim(strval($body["cover_url"] ?? ""));
  $work_url = trim(strval($body["work_url"] ?? ""));
  $is_visible = array_key_exists("is_visible", $body) ? intval($body["is_visible"]) : 1;

  $stmt = $pdo->prepare("INSERT INTO sp_works(user_id,category_id,title,description,cover_url,work_url,is_visible) VALUES(?,?,?,?,?,?,?)");
  $stmt->execute([$owner_id, $category_id, $title, $description, $cover_url, $work_url, $is_visible]);

  json_out(true, [["id" => $pdo->lastInsertId()]], "สร้างแล้ว", 201);
}

if ($method === "PUT") {
  $u = require_login($pdo);
  $id = intval($body["id"] ?? 0);
  if ($id <= 0) json_out(false, null, "ต้องส่ง id", 400);

  $stmt = $pdo->prepare("SELECT user_id FROM sp_works WHERE id=?");
  $stmt->execute([$id]);
  $row = $stmt->fetch();
  if (!$row) json_out(false, null, "ไม่พบผลงาน", 404);
  if (!is_owner_or_admin($u, intval($row["user_id"]))) json_out(false, null, "ไม่มีสิทธิ์แก้ไข", 403);

  $fields = [];
  $params = [];

  $map = ["title", "description", "cover_url", "work_url"];
  foreach ($map as $k) {
    if (array_key_exists($k, $body)) {
      $fields[] = "{$k} = ?";
      $params[] = trim(strval($body[$k]));
    }
  }

  if (array_key_exists("category_id", $body)) {
    $fields[] = "category_id = ?";
    $params[] = ($body["category_id"] === null || $body["category_id"] === "") ? null : intval($body["category_id"]);
  }
  if (array_key_exists("is_visible", $body)) {
    $fields[] = "is_visible = ?";
    $params[] = intval($body["is_visible"]);
  }

  if (count($fields) === 0) json_out(false, null, "ไม่มีฟิลด์ให้อัปเดต", 400);

  $params[] = $id;
  $sql = "UPDATE sp_works SET " . implode(", ", $fields) . " WHERE id=?";
  $upd = $pdo->prepare($sql);
  $upd->execute($params);

  json_out(true, null, "อัปเดตแล้ว");
}

if ($method === "DELETE") {
  $u = require_login($pdo);
  $id = intval($_GET["id"] ?? 0);
  if ($id <= 0) json_out(false, null, "ต้องส่ง id", 400);

  $stmt = $pdo->prepare("SELECT user_id FROM sp_works WHERE id=?");
  $stmt->execute([$id]);
  $row = $stmt->fetch();
  if (!$row) json_out(false, null, "ไม่พบผลงาน", 404);
  if (!is_owner_or_admin($u, intval($row["user_id"]))) json_out(false, null, "ไม่มีสิทธิ์ลบ", 403);

  $del = $pdo->prepare("DELETE FROM sp_works WHERE id=?");
  $del->execute([$id]);
  json_out(true, null, "ลบแล้ว");
}

json_out(false, null, "Not found", 404);
