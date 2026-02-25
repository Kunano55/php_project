const loginEmailEl = document.getElementById("loginEmail");
const loginPasswordEl = document.getElementById("loginPassword");
const regNameEl = document.getElementById("regName");
const regEmailEl = document.getElementById("regEmail");
const regPasswordEl = document.getElementById("regPassword");
const msgLoginEl = document.getElementById("msgLogin");
const msgRegisterEl = document.getElementById("msgRegister");

document.getElementById("btnLogin").onclick = loginStudent;
document.getElementById("btnLogout").onclick = logout;
document.getElementById("btnRegister").onclick = registerStudent;

(async function init() {
  const me = await apiGet("auth.php?action=me");
  const user = me && me.ok && me.data && me.data[0] ? me.data[0] : null;

  if (!user) {
    msgLoginEl.textContent = "สถานะ: ยังไม่ล็อกอิน";
    return;
  }

  if ((user.role || "") === "student") {
    msgLoginEl.textContent = `สถานะ: ล็อกอินนักศึกษาอยู่ (${user.email})`;
  } else {
    msgLoginEl.textContent = "สถานะ: เป็นแอดมิน กรุณาใช้หน้าเข้าสู่ระบบแอดมิน";
  }
})();

async function loginStudent() {
  msgLoginEl.textContent = "กำลังล็อกอิน...";

  const email = loginEmailEl.value.trim();
  const password = loginPasswordEl.value;
  if (!email || !password) {
    msgLoginEl.textContent = "กรอกอีเมลและรหัสผ่าน";
    return;
  }

  const res = await apiPost("auth.php?action=login", { email, password });
  if (!res.ok) {
    msgLoginEl.textContent = res.message || "ล็อกอินไม่สำเร็จ";
    return;
  }

  const user = res.data && res.data[0] ? res.data[0] : null;
  if (!user || (user.role || "") !== "student") {
    await apiPost("auth.php?action=logout", {});
    msgLoginEl.textContent = "หน้านี้ให้ล็อกอินเฉพาะนักศึกษาเท่านั้น";
    return;
  }

  msgLoginEl.textContent = "ล็อกอินนักศึกษาสำเร็จ";
  setTimeout(() => {
    location.href = "student.html";
  }, 250);
}

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

  msgRegisterEl.textContent = "สมัครสมาชิกสำเร็จ สามารถล็อกอินได้เลย";
  loginEmailEl.value = email;
  loginPasswordEl.value = "";
  regPasswordEl.value = "";
}

async function logout() {
  const res = await apiPost("auth.php?action=logout", {});
  msgLoginEl.textContent = res.ok ? "ออกจากระบบแล้ว" : (res.message || "ออกจากระบบไม่สำเร็จ");
}