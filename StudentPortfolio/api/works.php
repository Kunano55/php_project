<?php
// api/works.php - API สำหรับ CRUD ผลงาน (works)\n// GET: ดึงข้อมูลผลงาน (ทั้งหมด, ของฉัน, admin, ตาม id)\n// POST: สร้างผลงานใหม่ (ต้อง login)\n// PUT: อัปเดตผลงาน (เจ้าของหรือ admin)\n// DELETE: ลบผลงาน (เจ้าของหรือ admin)
declare(strict_types=1);

session_start();
require_once __DIR__ . "/database.php";
require_once __DIR__ . "/helpers.php";

$method = $_SERVER["REQUEST_METHOD"];
$body = read_json_body();

// ฟังก์ชันดึงผลงาน พร้อมตัวกรอง dynamic
// รับ options: only_visible, id, user_id, category_id, q (search query)
// คืนค่า array of works พร้อมข้อมูล category_name, owner_name, owner_avatar
function fetch_works(mysqli $mysqli, array $opts): array {
  $where = []; // เก็บ WHERE conditions
  $params = []; // เก็บ parameters สำหรับ bind

  // ตัวกรอง 1: เฉพาะผลงานที่เผยแพร่ (is_visible = 1)
  if (!empty($opts["only_visible"])) {
    $where[] = "w.is_visible = 1";
  }
  // ตัวกรอง 2: ตาม work ID
  if (!empty($opts["id"])) {
    $where[] = "w.id = ?";
    $params[] = intval($opts["id"]);
  }
  // ตัวกรอง 3: ตาม user ID (ผลงานขอของคนใดคนหนึ่ง)
  if (!empty($opts["user_id"])) {
    $where[] = "w.user_id = ?";
    $params[] = intval($opts["user_id"]);
  }
  // ตัวกรอง 4: ตาม category ID
  if (!empty($opts["category_id"])) {
    $where[] = "w.category_id = ?";
    $params[] = intval($opts["category_id"]);
  }
  // ตัวกรอง 5: ค้นหาจากชื่อผลงาน, คำอธิบาย, ชื่อ user (LIKE search)
  if (!empty($opts["q"])) {
    $where[] = "(w.title LIKE ? OR w.description LIKE ? OR u.name LIKE ?)"; // 3 fields ที่ search
    $q = "%" . $opts["q"] . "%";
    $params[] = $q;
    $params[] = $q;
    $params[] = $q;
  }

  // สร้าง SQL SELECT พร้อม JOIN กับ categories และ users เพื่อดึงข้อมูล category_name และ owner info
  $sql = "SELECT
            w.*,
            c.name AS category_name,
            u.name AS owner_name,
            u.avatar_url AS owner_avatar
          FROM sp_works w
          LEFT JOIN sp_categories c ON c.id = w.category_id
          LEFT JOIN sp_users u ON u.id = w.user_id";

  // ถ้ามีตัวกรอง ให้เพิ่ม WHERE clause
  if (count($where) > 0) {
    $sql .= " WHERE " . implode(" AND ", $where);
  }
  // เรียงจากใหม่สุดไปเก่าสุด (DESC) และ limit 500 rows
  $sql .= " ORDER BY w.created_at DESC LIMIT 500";

  // เตรียม statement
  $stmt = $mysqli->prepare($sql);
  // ถ้ามี parameters ก็ bind เข้าไป พร้อม auto-detect type
  if (count($params) > 0) {
    $types = '';
    foreach ($params as $p) {
      if (is_int($p)) $types .= 'i';           // integer
      elseif (is_float($p)) $types .= 'd';     // double
      else $types .= 's';                      // string
    }
    $stmt->bind_param($types, ...$params);
  }
  $stmt->execute();
  return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// ===== GET ENDPOINTS =====
if ($method === "GET") {
  // GET /works.php?summary=1 - ดึงสรุปจำนวน (admin only)
  // ส่งกลับ: total, visible, hidden count
  if (isset($_GET["summary"]) && $_GET["summary"] === "1") {
    require_admin($mysqli);
    $result = $mysqli->query("SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN is_visible=1 THEN 1 ELSE 0 END) AS visible,
        SUM(CASE WHEN is_visible=0 THEN 1 ELSE 0 END) AS hidden
      FROM sp_works");
    $row = $result->fetch_assoc();
    json_out(true, [$row ?: ["total" => 0, "visible" => 0, "hidden" => 0]], "");
  }

  // GET /works.php?admin=1 - ดึงผลงานทั้งหมด รวมที่อยู่ draft (admin only)
  // สามารถ filter ด้วย q, category_id, user_id
  if (isset($_GET["admin"]) && $_GET["admin"] === "1") {
    require_admin($mysqli);
    $data = fetch_works($mysqli, [
      "only_visible" => false, // ดึงทั้งหมด รวม draft
      "q" => ($_GET["q"] ?? ""),
      "category_id" => ($_GET["category_id"] ?? ""),
      "user_id" => ($_GET["user_id"] ?? ""),
    ]);
    json_out(true, $data, "");
  }

  // GET /works.php?mine=1 - ดึงผลงานของฉัน (ทั้ง draft และ published)
  // ต้อง login
  if (isset($_GET["mine"]) && $_GET["mine"] === "1") {
    $u = require_login($mysqli);
    $data = fetch_works($mysqli, [
      "only_visible" => false, // ดึงทั้งหมด รวม draft
      "user_id" => intval($u["id"]),
      "q" => ($_GET["q"] ?? ""),
      "category_id" => ($_GET["category_id"] ?? ""),
    ]);
    json_out(true, $data, "");
  }

  // GET /works.php?id=X - ดึงผลงานตาม ID เดี่ยว
  // ถ้า user session login:
  //   - แสดง draft ของตัวเองได้
  //   - admin เห็นทั้งหมด
  // ถ้าไม่ login:
  //   - เห็นแค่ published (is_visible=1)
  if (isset($_GET["id"]) && intval($_GET["id"]) > 0) {
    $id = intval($_GET["id"]);
    $only_visible = true; // default: เห็นแค่ published

    $u = current_user($mysqli);
    if ($u) {
      // ตรวจสอบเจ้าของ
      $stmt = $mysqli->prepare("SELECT user_id FROM sp_works WHERE id=?");
      $stmt->bind_param('i', $id);
      $stmt->execute();
      $row = $stmt->get_result()->fetch_assoc();

      // ถ้า user เป็นเจ้าของ ให้เห็น draft ด้วย
      if ($row && intval($u["id"] ?? 0) === intval($row["user_id"])) {
        $only_visible = false;
      }
      // ถ้า user เป็น admin ให้เห็นทั้งหมด
      if ($row && ($u["role"] ?? "") === "admin") {
        $only_visible = false;
      }
    }

    $data = fetch_works($mysqli, [
      "only_visible" => $only_visible,
      "id" => $id,
    ]);
    json_out(true, $data, "");
  }

  // GET /works.php - ดึงผลงานสาธารณะทั้งหมด (default)
  // สามารถ filter ด้วย q, category_id, user_id
  $opts = [
    "only_visible" => true, // เห็นแค่ published
    "q" => $_GET["q"] ?? "",
    "category_id" => $_GET["category_id"] ?? "",
    "user_id" => $_GET["user_id"] ?? "",
  ];
  $data = fetch_works($mysqli, $opts);
  json_out(true, $data, "");
}

// ===== POST ENDPOINT =====
if ($method === "POST") {
  // POST /works.php - สร้างผลงานใหม่
  // ต้อง login, title required
  // owner_id: ทำให้กับตัวเองหรือฝากสร้าง (admin only)
  // category_id: auto-select หมวดแรก ถ้าไม่ส่ง
  // description, cover_url, work_url: optional
  // is_visible: default 1 (public) สำหรับ students, admin สามารถเลือก
  
  $u = require_login($mysqli);

  // ตรวจสอบ title ไม่ว่างเปล่า (required)
  $title = trim(strval($body["title"] ?? ""));
  if ($title === "") json_out(false, null, "ต้องส่ง title", 400);

  // กำหนด owner_id: ผู้ใช้ในที่จะเป็นเจ้าของผลงาน
  // สำหรับ admin: สามารถระบุ user_id เพื่อสร้างให้คนอื่นได้
  $owner_id = intval($u["id"]);
  if (($u["role"] ?? "") === "admin" && array_key_exists("user_id", $body)) {
    $owner_id = intval($body["user_id"]);
    if ($owner_id <= 0) json_out(false, null, "user_id ไม่ถูกต้อง", 400);

    // ตรวจสอบว่า user_id นั้นมีจริง
    $stmt = $mysqli->prepare("SELECT id FROM sp_users WHERE id=? LIMIT 1");
    $stmt->bind_param('i', $owner_id);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) json_out(false, null, "ไม่พบผู้ใช้", 404);
  }

  // กำหนด category_id: ถ้าไม่ส่ง auto-select หมวดแรก
  $category_id = intval($body["category_id"] ?? 0);
  if ($category_id <= 0) {
    // ดึงหมวดแรก
    $res = $mysqli->query("SELECT id FROM sp_categories ORDER BY id ASC LIMIT 1");
    $firstCat = $res->fetch_assoc();
    if (!$firstCat) json_out(false, null, "ยังไม่มีหมวดหมู่ กรุณาให้แอดมินเพิ่มหมวดก่อน", 400);
    $category_id = intval($firstCat["id"]);
  } else {
    // ตรวจสอบว่า category_id ที่ส่งมามีจริง
    $catCheck = $mysqli->prepare("SELECT id FROM sp_categories WHERE id=? LIMIT 1");
    $catCheck->bind_param('i', $category_id);
    $catCheck->execute();
    if (!$catCheck->get_result()->fetch_assoc()) json_out(false, null, "ไม่พบหมวดหมู่ที่เลือก", 400);
  }

  // ดึงข้อมูลเพิ่มเติม (ค่า default = string เปล่า)
  $description = trim(strval($body["description"] ?? ""));
  $cover_url = trim(strval($body["cover_url"] ?? ""));
  $work_url = trim(strval($body["work_url"] ?? ""));

  // กำหนด is_visible: default 1 (public)
  // สำหรับ admin: สามารถเลือกบันทึก (draft) หรือ publish ได้
  $is_visible = 1;
  if (($u["role"] ?? "") === "admin" && array_key_exists("is_visible", $body)) {
    $is_visible = intval($body["is_visible"]) === 0 ? 0 : 1;
  }

  // เตรียม statement และ execute INSERT
  try {
    $stmt = $mysqli->prepare("INSERT INTO sp_works(user_id,category_id,title,description,cover_url,work_url,is_visible) VALUES(?,?,?,?,?,?,?)");
    $stmt->bind_param('iissssi', $owner_id, $category_id, $title, $description, $cover_url, $work_url, $is_visible);
    $stmt->execute();
  } catch (Exception $e) {
    json_out(false, null, "เพิ่มข้อมูลไม่สำเร็จ: " . $e->getMessage(), 400);
  }

  // คืนค่า ID ของผลงานที่เพิ่งสร้าง
  json_out(true, [["id" => $mysqli->insert_id]], "สร้างแล้ว", 201);
}

// ===== PUT ENDPOINT =====
if ($method === "PUT") {
  // PUT /works.php - อัปเดตผลงาน
  // ต้อง login, id required
  // สามารถอัปเดต: title, description, cover_url, work_url, category_id
  // is_visible: admin only ใช้ได้
  // ต้องเป็นเจ้าของงานหรือ admin
  
  $u = require_login($mysqli);

  // ตรวจสอบ ID ของผลงาน (required)
  $id = intval($body["id"] ?? 0);
  if ($id <= 0) json_out(false, null, "ต้องส่ง id", 400);

  // ดึงข้อมูลผลงาน เพื่อตรวจสอบความเป็นเจ้าของ
  $stmt = $mysqli->prepare("SELECT user_id FROM sp_works WHERE id=?");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  if (!$row) json_out(false, null, "ไม่พบผลงาน", 404);

  // ตรวจสอบสิทธิ์: user ต้องเป็นเจ้าของหรือ admin
  $isOwner = intval($u["id"] ?? 0) === intval($row["user_id"]);
  $isAdmin = ($u["role"] ?? "") === "admin";
  if (!$isOwner && !$isAdmin) {
    json_out(false, null, "ต้องเป็นเจ้าของงานหรือแอดมิน", 403);
  }

  // สร้างรายการฟิลด์ที่จะอัปเดต (dynamic fields)
  $fields = [];
  $params = [];

  // ฟิลด์ที่เป็น string: title, description, cover_url, work_url
  $map = ["title", "description", "cover_url", "work_url"];
  foreach ($map as $k) {
    if (array_key_exists($k, $body)) {
      $fields[] = "{$k} = ?";
      $params[] = trim(strval($body[$k]));
    }
  }

  // ฟิลด์ category_id: สามารถ null ได้
  if (array_key_exists("category_id", $body)) {
    $categoryVal = ($body["category_id"] === null || $body["category_id"] === "") ? null : intval($body["category_id"]);
    // ถ้ากำหนด category ให้ตรวจสอบว่ามีจริง
    if ($categoryVal !== null) {
      $catCheck = $mysqli->prepare("SELECT id FROM sp_categories WHERE id=? LIMIT 1");
      $catCheck->bind_param('i', $categoryVal);
      $catCheck->execute();
      if (!$catCheck->get_result()->fetch_assoc()) json_out(false, null, "ไม่พบหมวดหมู่ที่เลือก", 400);
    }
    $fields[] = "category_id = ?";
    $params[] = $categoryVal;
  }

  // ฟิลด์ is_visible: admin only
  if (array_key_exists("is_visible", $body)) {
    // เฉพาะ admin สามารถเปลี่ยน visibility ได้
    if ($isAdmin) {
      $fields[] = "is_visible = ?";
      $params[] = intval($body["is_visible"]) === 0 ? 0 : 1;
    }
  }

  // ตรวจสอบมีฟิลด์ที่อัปเดตอย่างน้อย 1 ฟิลด์
  if (count($fields) === 0) json_out(false, null, "ไม่มีฟิลด์ให้อัปเดต", 400);

  // เพิ่ม id ที่ท้าย params เพื่อใช้ใน WHERE clause
  $params[] = $id;
  // สร้าง dynamic SQL UPDATE statement
  $sql = "UPDATE sp_works SET " . implode(", ", $fields) . " WHERE id=?";
  $upd = $mysqli->prepare($sql);
  
  // auto-detect type string สำหรับ bind_param
  if ($params) {
    $types = '';
    foreach ($params as $p) {
      if (is_int($p)) $types .= 'i';
      elseif (is_float($p)) $types .= 'd';
      else $types .= 's';
    }
    $upd->bind_param($types, ...$params);
  }
  $upd->execute();

  json_out(true, null, "อัปเดตแล้ว");
}

// ===== DELETE ENDPOINT =====
if ($method === "DELETE") {
  // DELETE /works.php?id=X - ลบผลงาน
  // ต้อง login, id required (from query string)
  // ต้องเป็นเจ้าของงานหรือ admin
  
  $u = require_login($mysqli);

  // ตรวจสอบ ID ของผลงานจาก query string (required)
  $id = intval($_GET["id"] ?? 0);
  if ($id <= 0) json_out(false, null, "ต้องส่ง id", 400);

  // ดึงข้อมูลผลงาน เพื่อตรวจสอบความเป็นเจ้าของ
  $stmt = $mysqli->prepare("SELECT user_id FROM sp_works WHERE id=?");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  if (!$row) json_out(false, null, "ไม่พบผลงาน", 404);

  // ตรวจสอบสิทธิ์: user ต้องเป็นเจ้าของหรือ admin
  $isOwner = intval($u["id"] ?? 0) === intval($row["user_id"]);
  $isAdmin = ($u["role"] ?? "") === "admin";
  if (!$isOwner && !$isAdmin) {
    json_out(false, null, "ต้องเป็นเจ้าของงานหรือแอดมิน", 403);
  }

  // ลบไฟล์รูปภาพที่เกี่ยวข้องจากโฟลเดอร์ uploads
  $imgStmt = $mysqli->prepare("SELECT image_url FROM sp_work_images WHERE work_id=?");
  $imgStmt->bind_param('i', $id);
  $imgStmt->execute();
  $resImgs = $imgStmt->get_result();
  while ($rowImg = $resImgs->fetch_assoc()) {
    $url = $rowImg['image_url'];
    $filePath = realpath(__DIR__ . "/.." . substr($url, 2));
    if ($filePath && strpos($filePath, realpath(__DIR__ . "/../uploads")) === 0) {
      @unlink($filePath);
    }
  }

  // ลบผลงาน (จะ cascade ลบเรคคอร์ดใน sp_work_images ด้วย)
  $del = $mysqli->prepare("DELETE FROM sp_works WHERE id=?");
  $del->bind_param('i', $id);
  $del->execute();
  json_out(true, null, "ลบแล้ว");
}

json_out(false, null, "Not found", 404);
