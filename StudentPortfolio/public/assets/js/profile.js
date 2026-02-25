const avatar = document.getElementById("avatar");
const nameEl = document.getElementById("name");
const bioEl = document.getElementById("bio");
const majorEl = document.getElementById("major");
const yearEl = document.getElementById("year");

const nameIn = document.getElementById("nameIn");
const majorIn = document.getElementById("majorIn");
const yearIn = document.getElementById("yearIn");
const bioIn = document.getElementById("bioIn");
const avatarIn = document.getElementById("avatarIn");
const msg = document.getElementById("msg");

document.getElementById("save").onclick = save;

(async function init(){
  const res = await apiGet("users.php?me=1"); // เดโม: ใช้ user id=1
  const u = (res.data && res.data[0]) ? res.data[0] : null;
  if(!u){ msg.textContent="ยังไม่มีโปรไฟล์"; return; }
  render(u);
  nameIn.value = u.name || "";
  majorIn.value = u.major || "";
  yearIn.value = u.year || "";
  bioIn.value = u.bio || "";
  avatarIn.value = u.avatar_url || "";
})();

function render(u){
  avatar.src = u.avatar_url || "https://via.placeholder.com/256?text=Profile";
  nameEl.textContent = u.name || "-";
  bioEl.textContent = u.bio || "-";
  majorEl.textContent = u.major || "-";
  yearEl.textContent = u.year || "-";
}

async function save(){
  msg.textContent = "กำลังบันทึก...";
  const payload = {
    id: 1,
    name: nameIn.value,
    major: majorIn.value,
    year: yearIn.value,
    bio: bioIn.value,
    avatar_url: avatarIn.value
  };
  const res = await apiPut("users.php", payload);
  msg.textContent = res.ok ? "บันทึกแล้ว" : (res.message || "บันทึกไม่สำเร็จ");
  if(res.ok) render(payload);
}