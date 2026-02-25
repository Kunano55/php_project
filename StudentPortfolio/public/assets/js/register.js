const regNameEl = document.getElementById("regName");
const regEmailEl = document.getElementById("regEmail");
const regPasswordEl = document.getElementById("regPassword");
const msgRegisterEl = document.getElementById("msgRegister");

document.getElementById("btnRegister").onclick = registerStudent;

(async function init() {
  const me = await apiGet("auth.php?action=me");
  const user = me && me.ok && me.data && me.data[0] ? me.data[0] : null;

  if (!user) return;

  if ((user.role || "") === "admin") {
    msgRegisterEl.textContent = "บัญชีที่ล็อกอินอยู่เป็นแอดมิน";
    return;
  }

  msgRegisterEl.textContent = `คุณล็อกอินอยู่แล้ว (${user.email})`;
})();

async function registerStudent() {
  msgRegisterEl.textContent = "กำลังสมัครสมาชิก...";

  const name = regNameEl.value.trim();
  const email = regEmailEl.value.trim();
  const password = regPasswordEl.value;

  if (!name || !email || !password) {
    msgRegisterEl.textContent = "กรอกชื่อ อีเมล และรหัสผ่าน";
    return;
  }
  if (password.length < 6) {
    msgRegisterEl.textContent = "รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร";
    return;
  }

  const res = await apiPost("auth.php?action=register", { name, email, password });
  if (!res.ok) {
    msgRegisterEl.textContent = res.message || "สมัครสมาชิกไม่สำเร็จ";
    return;
  }

  msgRegisterEl.textContent = "สมัครสำเร็จ กำลังพาไปหน้าเข้าสู่ระบบ...";
  setTimeout(() => {
    location.href = "login.html";
  }, 500);
}
