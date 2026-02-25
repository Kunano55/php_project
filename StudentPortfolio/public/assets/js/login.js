const emailEl = document.getElementById("email");
const passEl = document.getElementById("password");
const msgEl = document.getElementById("msg");

document.getElementById("btnLogin").onclick = login;
document.getElementById("btnLogout").onclick = logout;

(async function init(){
  const me = await apiGet("auth.php?action=me");
  if (me && me.ok && me.data && me.data[0]) {
    msgEl.textContent = `ล็อกอินอยู่: ${me.data[0].email} (${me.data[0].role})`;
  }
})();

async function login(){
  msgEl.textContent = "กำลังล็อกอิน...";
  const email = emailEl.value.trim();
  const password = passEl.value;

  if(!email || !password){
    msgEl.textContent = "กรอกอีเมลและรหัสผ่าน";
    return;
  }

  const res = await apiPost("auth.php?action=login", { email, password });

  if(res.ok){
    msgEl.textContent = "ล็อกอินสำเร็จ กำลังพาไปหน้าแอดมิน...";
    setTimeout(() => location.href = "admin.html", 400);
  }else{
    msgEl.textContent = res.message || "ล็อกอินไม่สำเร็จ";
    alert(res.message || "ล็อกอินไม่สำเร็จ");
  }
}

async function logout(){
  const res = await apiPost("auth.php?action=logout", {});
  msgEl.textContent = res.ok ? "ออกจากระบบแล้ว" : (res.message || "ออกจากระบบไม่สำเร็จ");
}
