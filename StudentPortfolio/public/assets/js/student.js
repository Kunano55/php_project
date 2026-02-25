const studentInfoEl = document.getElementById("studentInfo");
const createSection = document.getElementById("createSection");
const openCreateBtn = document.getElementById("openCreateBtn");
const categoryIn = document.getElementById("categoryIn");
const titleIn = document.getElementById("titleIn");
const descIn = document.getElementById("descIn");
const workUrlIn = document.getElementById("workUrlIn");
const coverUrlIn = document.getElementById("coverUrlIn");
const coverFileIn = document.getElementById("coverFileIn");
const createMsg = document.getElementById("createMsg");
const myWorksEl = document.getElementById("myWorks");
const myWorksEmpty = document.getElementById("myWorksEmpty");
const createBtn = document.getElementById("createBtn");
let pendingCoverFile = null;

coverFileIn.onchange = previewCoverFile;
document.getElementById("clearCoverBtn").onclick = clearCoverFile;
createBtn.onclick = createWork;
if (openCreateBtn) {
  openCreateBtn.onclick = goToCreateForm;
}

(async function init() {
  const me = await apiGet("auth.php?action=me");
  const user = me && me.ok && me.data && me.data[0] ? me.data[0] : null;

  if (!user) {
    alert("กรุณาล็อกอินก่อน");
    location.href = "login.html";
    return;
  }

  if (user.role === "admin") {
    location.href = "admin.html";
    return;
  }

  studentInfoEl.textContent = `ผู้ใช้: ${user.email} (${user.role})`;

  await loadCategories();
  await loadMyWorks();
})();

async function loadCategories() {
  const res = await apiGet("categories.php");
  const cats = res.data || [];

  if (!cats.length) {
    categoryIn.innerHTML = `<option value="">ยังไม่มีหมวดหมู่</option>`;
    createBtn.disabled = true;
    createMsg.textContent = "ยังเพิ่มข้อมูลไม่ได้ เพราะยังไม่มีหมวดหมู่ (ให้แอดมินเพิ่มก่อน)";
    return;
  }

  createBtn.disabled = false;
  categoryIn.innerHTML = cats.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join("");
  categoryIn.value = String(cats[0].id);
}

async function previewCoverFile() {
  const file = coverFileIn.files && coverFileIn.files[0] ? coverFileIn.files[0] : null;
  if (!file) {
    createMsg.textContent = "";
    pendingCoverFile = null;
    return;
  }

  pendingCoverFile = file;
  createMsg.textContent = "เลือกรูป: " + file.name;
}

function clearCoverFile() {
  pendingCoverFile = null;
  coverUrlIn.value = "";
  coverFileIn.value = "";
  createMsg.textContent = "";
}

async function createWork() {
  if (createBtn.disabled) {
    createMsg.textContent = "ยังเพิ่มข้อมูลไม่ได้ เพราะยังไม่มีหมวดหมู่";
    return;
  }

  const title = titleIn.value.trim();
  if (!title) {
    createMsg.textContent = "กรุณากรอกชื่อผลงาน";
    return;
  }

  const categoryId = Number(categoryIn.value || 0);
  if (categoryId <= 0) {
    createMsg.textContent = "กรุณาเลือกหมวดหมู่";
    return;
  }

  // Upload pending file if exists
  if (pendingCoverFile) {
    createMsg.textContent = "กำลังอัปโหลดรูป...";
    const uploadRes = await apiUpload("upload.php", pendingCoverFile);
    if (!uploadRes.ok) {
      createMsg.textContent = uploadRes.message || "อัปโหลดรูปไม่สำเร็จ";
      return;
    }
    const url = uploadRes.data && uploadRes.data[0] ? uploadRes.data[0].url : "";
    coverUrlIn.value = url;
    pendingCoverFile = null;
    coverFileIn.value = "";
  }

  createMsg.textContent = "กำลังบันทึก...";
  const payload = {
    title,
    category_id: categoryId,
    description: descIn.value.trim(),
    work_url: workUrlIn.value.trim(),
    cover_url: coverUrlIn.value.trim()
  };

  const res = await apiPost("works.php", payload);
  if (!res.ok) {
    createMsg.textContent = res.message || "บันทึกไม่สำเร็จ";
    return;
  }

  titleIn.value = "";
  descIn.value = "";
  workUrlIn.value = "";
  coverUrlIn.value = "";
  coverFileIn.value = "";
  createMsg.textContent = "เพิ่มผลงานสำเร็จ เพิ่มชิ้นถัดไปได้เลย";
  titleIn.focus();

  await loadMyWorks();
}

async function loadMyWorks() {
  const res = await apiGet("works.php?mine=1");
  if (!res.ok) {
    alert(res.message || "โหลดผลงานไม่สำเร็จ");
    if ((res.message || "").includes("ล็อกอิน")) {
      location.href = "login.html";
    }
    return;
  }

  const works = res.data || [];
  myWorksEmpty.style.display = works.length ? "none" : "block";
  myWorksEl.innerHTML = works.map(w => {
    const cover = w.cover_url || "https://via.placeholder.com/160x90?text=No+Cover";
    const visible = String(w.is_visible) === "1";

    return `
      <div class="list-item">
        <div class="row gap">
          <img src="${escapeHtml(cover)}" alt="cover" class="work-cover" style="max-width:180px; height:100px;" />
          <div style="flex:1;">
            <b>${escapeHtml(w.title)}</b>
            <div class="muted">${escapeHtml(w.category_name || "-")}</div>
            <div class="muted">${escapeHtml((w.description || "").slice(0, 140))}</div>
            <div class="muted">สถานะ: ${visible ? "แสดง" : "ซ่อนโดยแอดมิน"}</div>
          </div>
        </div>
        <div class="row gap" style="justify-content:flex-end; margin-top:8px;">
          <a class="btn" href="work.html?id=${w.id}">ดู</a>
          <a class="btn" href="edit-work.html?id=${w.id}" style="background:#666;">แก้ไข</a>
        </div>
      </div>
    `;
  }).join("");
}

function goToCreateForm() {
  if (!createSection) return;
  createSection.scrollIntoView({ behavior: "smooth", block: "start" });
  setTimeout(() => {
    titleIn.focus();
  }, 180);
}

function escapeHtml(str) {
  return String(str || "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll("\"", "&quot;")
    .replaceAll("'", "&#039;");
}
