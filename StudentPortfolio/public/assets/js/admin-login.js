const emailEl = document.getElementById("email");
const passEl = document.getElementById("password");
const lockCodeEl = document.getElementById("lockCode");
const msgEl = document.getElementById("msg");

document.getElementById("btnLogin").onclick = loginAdmin;
document.getElementById("btnLogout").onclick = logout;

(async function init() {
  const me = await apiGet("auth.php?action=me");
  const user = me && me.ok && me.data && me.data[0] ? me.data[0] : null;

  if (!user) {
    msgEl.textContent = "สถานะ: ยังไม่ล็อกอิน";
    return;
  }

  msgEl.textContent = `สถานะ: ล็อกอินอยู่ ${user.email} (${user.role})`;
})();

async function loginAdmin() {
  msgEl.textContent = "กำลังล็อกอิน...";

  const email = emailEl.value.trim();
  const password = passEl.value;
  const lock_code = lockCodeEl.value.trim();

  if (!email || !password || !lock_code) {
    msgEl.textContent = "กรอกอีเมล รหัสผ่าน และรหัสล็อคแอดมิน";
    return;
  }

  const res = await apiPost("auth.php?action=login", { email, password, lock_code });
  if (!res.ok) {
    msgEl.textContent = res.message || "ล็อกอินไม่สำเร็จ";
    return;
  }

  const user = res.data && res.data[0] ? res.data[0] : null;
  if (!user || (user.role || "") !== "admin") {
    await apiPost("auth.php?action=logout", {});
    msgEl.textContent = "หน้านี้ให้ล็อกอินเฉพาะแอดมินเท่านั้น";
    return;
  }

  msgEl.textContent = "ล็อกอินแอดมินสำเร็จ";
  setTimeout(() => {
    location.href = "admin.html";
  }, 250);
}

async function logout() {
  const res = await apiPost("auth.php?action=logout", {});
  msgEl.textContent = res.ok ? "ออกจากระบบแล้ว" : (res.message || "ออกจากระบบไม่สำเร็จ");
}