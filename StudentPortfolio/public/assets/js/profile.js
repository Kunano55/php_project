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
const avatarFileIn = document.getElementById("avatarFileIn");
const msg = document.getElementById("msg");

let currentUserId = 0;

document.getElementById("save").onclick = save;
document.getElementById("uploadAvatarBtn").onclick = uploadAvatar;

(async function init() {
  const me = await apiGet("auth.php?action=me");
  const user = me && me.ok && me.data && me.data[0] ? me.data[0] : null;

  if (!user) {
    msg.textContent = "กรุณาล็อกอินก่อน";
    setTimeout(() => {
      location.href = "login.html";
    }, 500);
    return;
  }

  currentUserId = Number(user.id) || 0;
  render(user);

  nameIn.value = user.name || "";
  majorIn.value = user.major || "";
  yearIn.value = user.year || "";
  bioIn.value = user.bio || "";
  avatarIn.value = user.avatar_url || "";
})();

function render(user) {
  avatar.src = user.avatar_url || "https://via.placeholder.com/256?text=Profile";
  nameEl.textContent = user.name || "-";
  bioEl.textContent = user.bio || "-";
  majorEl.textContent = user.major || "-";
  yearEl.textContent = user.year || "-";
}

async function uploadAvatar() {
  const file = avatarFileIn.files && avatarFileIn.files[0] ? avatarFileIn.files[0] : null;
  if (!file) {
    msg.textContent = "กรุณาเลือกไฟล์รูปก่อน";
    return;
  }

  msg.textContent = "กำลังอัปโหลดรูป...";
  const res = await apiUpload("upload.php", file);
  if (!res.ok) {
    msg.textContent = res.message || "อัปโหลดรูปไม่สำเร็จ";
    return;
  }

  const url = res.data && res.data[0] ? res.data[0].url : "";
  avatarIn.value = url;
  render({
    name: nameIn.value,
    major: majorIn.value,
    year: yearIn.value,
    bio: bioIn.value,
    avatar_url: url
  });

  msg.textContent = "อัปโหลดรูปสำเร็จ";
}

async function save() {
  if (!currentUserId) {
    msg.textContent = "ไม่พบข้อมูลผู้ใช้";
    return;
  }

  msg.textContent = "กำลังบันทึก...";
  const payload = {
    id: currentUserId,
    name: nameIn.value.trim(),
    major: majorIn.value.trim(),
    year: yearIn.value.trim(),
    bio: bioIn.value.trim(),
    avatar_url: avatarIn.value.trim()
  };

  const res = await apiPut("users.php", payload);
  msg.textContent = res.ok ? "บันทึกแล้ว" : (res.message || "บันทึกไม่สำเร็จ");

  if (res.ok) {
    render(payload);
  }
}
