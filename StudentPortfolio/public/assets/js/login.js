const loginEmailEl = document.getElementById("loginEmail");
const loginPasswordEl = document.getElementById("loginPassword");
const msgLoginEl = document.getElementById("msgLogin");

document.getElementById("btnLogin").onclick = loginStudent;
document.getElementById("btnGoRegister").onclick = () => {
  location.href = "register.html";
};

(async function init() {
  const me = await apiGet("auth.php?action=me");
  const user = me && me.ok && me.data && me.data[0] ? me.data[0] : null;

  if (!user) {
    msgLoginEl.textContent = "สถานะ: ยังไม่ได้ล็อกอิน";
    return;
  }

  if ((user.role || "") === "student") {
    msgLoginEl.textContent = `สถานะ: ล็อกอินเป็นนักศึกษาอยู่ (${user.email})`;
    return;
  }

  if ((user.role || "") === "admin") {
    msgLoginEl.textContent = `สถานะ: ล็อกอินเป็นแอดมินอยู่ (${user.email})`;
    return;
  }

  msgLoginEl.textContent = "สถานะ: ยังไม่ได้ล็อกอิน";
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
  if (!user) {
    await apiPost("auth.php?action=logout", {});
    msgLoginEl.textContent = "ล็อกอินไม่สำเร็จ";
    return;
  }

  msgLoginEl.textContent = "ล็อกอินสำเร็จ";
  
  const role = user.role || "";
  setTimeout(() => {
    location.href = role === "admin" ? "admin.html" : "student.html";
  }, 250);
}
