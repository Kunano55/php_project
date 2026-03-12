<?php
// 1. ดึงไฟล์เชื่อมต่อ database มาใช้งาน
require_once 'database.php'; 

// 2. กำหนดข้อมูลที่ต้องการเพิ่ม (ปรับแก้ได้ตามใจชอบ)
$email = 'admin@example.com';
$password = '123456'; // รหัสผ่านที่ต้องการ
$name = 'System Admin';
$major = 'IT';
$year = '2024';
$role = 'admin'; // กำหนดให้เป็น admin ตามที่ต้องการ

// 3. ทำการ Hash รหัสผ่านให้เป็นรหัสลับก่อนบันทึก
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

try {
    // 4. เตรียมคำสั่ง SQL (ใช้ Prepared Statement เพื่อความปลอดภัยจาก SQL Injection)
    $sql = "INSERT INTO sp_users (email, password_hash, name, major, year, role) 
            VALUES (:email, :password_hash, :name, :major, :year, :role)";
    
    $stmt = $conn->prepare($sql);
    
    // 5. ผูกค่าและประมวลผล
    $stmt->execute([
        ':email' => $email,
        ':password_hash' => $hashed_password,
        ':name' => $name,
        ':major' => $major,
        ':year' => $year,
        ':role' => $role
    ]);

    echo "### เพิ่มผู้ใช้ Admin เรียบร้อยแล้ว! ###<br>";
    echo "Email: " . $email . "<br>";
    echo "Password: " . $password;

} catch (PDOException $e) {
    // กรณี Email ซ้ำ หรือเกิดข้อผิดพลาดอื่นๆ
    echo "เกิดข้อผิดพลาด: " . $e->getMessage();
}
?>