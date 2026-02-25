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
            u.name AS owner_name,
            u.avatar_url AS owner_avatar
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
  if (isset($_GET["summary"]) && $_GET["summary"] === "1") {
    require_admin($pdo);
    $row = $pdo->query("SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN is_visible=1 THEN 1 ELSE 0 END) AS visible,
        SUM(CASE WHEN is_visible=0 THEN 1 ELSE 0 END) AS hidden
      FROM sp_works")->fetch();
    json_out(true, [$row ?: ["total" => 0, "visible" => 0, "hidden" => 0]], "");
  }

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

  if (isset($_GET["id"]) && intval($_GET["id"]) > 0) {
    $id = intval($_GET["id"]);
    $only_visible = true;

    $u = current_user($pdo);
    if ($u) {
      $stmt = $pdo->prepare("SELECT user_id FROM sp_works WHERE id=?");
      $stmt->execute([$id]);
      $row = $stmt->fetch();

      if ($row && intval($u["id"] ?? 0) === intval($row["user_id"])) {
        $only_visible = false;
      }
      if ($row && ($u["role"] ?? "") === "admin") {
        $only_visible = false;
      }
    }

    $data = fetch_works($pdo, [
      "only_visible" => $only_visible,
      "id" => $id,
    ]);
    json_out(true, $data, "");
  }

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

  $category_id = intval($body["category_id"] ?? 0);
  if ($category_id <= 0) {
    $firstCat = $pdo->query("SELECT id FROM sp_categories ORDER BY id ASC LIMIT 1")->fetch();
    if (!$firstCat) json_out(false, null, "ยังไม่มีหมวดหมู่ กรุณาให้แอดมินเพิ่มหมวดก่อน", 400);
    $category_id = intval($firstCat["id"]);
  } else {
    $catCheck = $pdo->prepare("SELECT id FROM sp_categories WHERE id=? LIMIT 1");
    $catCheck->execute([$category_id]);
    if (!$catCheck->fetch()) json_out(false, null, "ไม่พบหมวดหมู่ที่เลือก", 400);
  }

  $description = trim(strval($body["description"] ?? ""));
  $cover_url = trim(strval($body["cover_url"] ?? ""));
  $work_url = trim(strval($body["work_url"] ?? ""));

  $is_visible = 1;
  if (($u["role"] ?? "") === "admin" && array_key_exists("is_visible", $body)) {
    $is_visible = intval($body["is_visible"]) === 0 ? 0 : 1;
  }

  try {
    $stmt = $pdo->prepare("INSERT INTO sp_works(user_id,category_id,title,description,cover_url,work_url,is_visible) VALUES(?,?,?,?,?,?,?)");
    $stmt->execute([$owner_id, $category_id, $title, $description, $cover_url, $work_url, $is_visible]);
  } catch (Exception $e) {
    json_out(false, null, "เพิ่มข้อมูลไม่สำเร็จ: " . $e->getMessage(), 400);
  }

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

  // Check ownership: user must be owner or admin
  $isOwner = intval($u["id"] ?? 0) === intval($row["user_id"]);
  $isAdmin = ($u["role"] ?? "") === "admin";
  if (!$isOwner && !$isAdmin) {
    json_out(false, null, "ต้องเป็นเจ้าของงานหรือแอดมิน", 403);
  }

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
    $categoryVal = ($body["category_id"] === null || $body["category_id"] === "") ? null : intval($body["category_id"]);
    if ($categoryVal !== null) {
      $catCheck = $pdo->prepare("SELECT id FROM sp_categories WHERE id=? LIMIT 1");
      $catCheck->execute([$categoryVal]);
      if (!$catCheck->fetch()) json_out(false, null, "ไม่พบหมวดหมู่ที่เลือก", 400);
    }
    $fields[] = "category_id = ?";
    $params[] = $categoryVal;
  }

  if (array_key_exists("is_visible", $body)) {
    // Only admins can change visibility
    if ($isAdmin) {
      $fields[] = "is_visible = ?";
      $params[] = intval($body["is_visible"]) === 0 ? 0 : 1;
    }
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

  // Check ownership: user must be owner or admin
  $isOwner = intval($u["id"] ?? 0) === intval($row["user_id"]);
  $isAdmin = ($u["role"] ?? "") === "admin";
  if (!$isOwner && !$isAdmin) {
    json_out(false, null, "ต้องเป็นเจ้าของงานหรือแอดมิน", 403);
  }

  $del = $pdo->prepare("DELETE FROM sp_works WHERE id=?");
  $del->execute([$id]);
  json_out(true, null, "ลบแล้ว");
}

json_out(false, null, "Not found", 404);
