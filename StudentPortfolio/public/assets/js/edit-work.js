let workId = null;
let currentUrl = "";
let pendingFile = null;

const titleIn = document.getElementById("titleIn");
const categoryIn = document.getElementById("categoryIn");
const descIn = document.getElementById("descIn");
const workUrlIn = document.getElementById("workUrlIn");
const coverFileIn = document.getElementById("coverFileIn");
const coverPreview = document.getElementById("coverPreview");
const saveBtn = document.getElementById("saveBtn");
const deleteBtn = document.getElementById("deleteBtn");
const msg = document.getElementById("msg");

coverFileIn.onchange = previewCover;
document.getElementById("clearCoverBtn").onclick = clearCover;
document.getElementById("saveBtn").onclick = saveWork;
document.getElementById("deleteBtn").onclick = deleteWork;

(async function init() {
  const me = await apiGet("auth.php?action=me");
  const user = me && me.ok && me.data && me.data[0] ? me.data[0] : null;

  if (!user || user.role !== "student") {
    alert("เฉพาะนักศึกษาเท่านั้น");
    location.href = "student.html";
    return;
  }

  const params = new URLSearchParams(location.search);
  workId = params.get("id");

  if (!workId) {
    alert("ต้องระบุ id ผลงาน");
    location.href = "student.html";
    return;
  }

  await loadCategories();
  await loadWork();
})();

async function loadCategories() {
  const res = await apiGet("categories.php");
  const cats = res.data || [];

  if (!cats.length) {
    msg.textContent = "ยังไม่มีหมวดหมู่";
    saveBtn.disabled = true;
    return;
  }

  categoryIn.innerHTML = cats
    .map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`)
    .join("");
}

async function loadWork() {
  const res = await apiGet(`works.php?id=${encodeURIComponent(workId)}`);
  const work = res.data && res.data[0] ? res.data[0] : null;

  if (!work) {
    alert("ไม่พบผลงาน หรือคุณไม่มีสิทธิ์แก้ไข");
    location.href = "student.html";
    return;
  }

  titleIn.value = work.title || "";
  descIn.value = work.description || "";
  workUrlIn.value = work.work_url || "";
  categoryIn.value = work.category_id || "";
  currentUrl = work.cover_url || "";

  if (currentUrl) {
    coverPreview.src = currentUrl;
    coverPreview.style.display = "block";
  }
}

async function previewCover() {
  const file = coverFileIn.files && coverFileIn.files[0] ? coverFileIn.files[0] : null;
  if (!file) {
    msg.textContent = "";
    pendingFile = null;
    return;
  }

  pendingFile = file;
  msg.textContent = "เลือกรูป: " + file.name;

  // Show preview
  const reader = new FileReader();
  reader.onload = (e) => {
    coverPreview.src = e.target.result;
    coverPreview.style.display = "block";
  };
  reader.readAsDataURL(file);
}

function clearCover() {
  pendingFile = null;
  currentUrl = "";
  msg.textContent = "";
  coverPreview.src = "";
  coverPreview.style.display = "none";
  coverFileIn.value = "";
}

async function saveWork() {
  const title = titleIn.value.trim();
  if (!title) {
    msg.textContent = "กรุณากรอกชื่อผลงาน";
    return;
  }

  const categoryId = Number(categoryIn.value || 0);
  if (categoryId <= 0) {
    msg.textContent = "กรุณาเลือกหมวดหมู่";
    return;
  }

  // If there's a pending file, upload it first
  if (pendingFile) {
    msg.textContent = "กำลังอัปโหลดรูป...";
    const uploadRes = await apiUpload("upload.php", pendingFile);
    if (!uploadRes.ok) {
      msg.textContent = uploadRes.message || "อัปโหลดรูปไม่สำเร็จ";
      return;
    }
    const url = uploadRes.data && uploadRes.data[0] ? uploadRes.data[0].url : "";
    currentUrl = url;
    pendingFile = null;
    coverFileIn.value = "";
  }

  msg.textContent = "กำลังบันทึก...";
  const payload = {
    id: workId,
    title,
    category_id: categoryId,
    description: descIn.value.trim(),
    work_url: workUrlIn.value.trim(),
    cover_url: currentUrl
  };

  const res = await apiPut("works.php", payload);
  if (!res.ok) {
    msg.textContent = res.message || "บันทึกไม่สำเร็จ";
    return;
  }

  msg.textContent = "บันทึกสำเร็จ";
  setTimeout(() => {
    location.href = "student.html";
  }, 500);
}

async function deleteWork() {
  if (!confirm("ยืนยันการลบผลงานนี้?")) return;

  msg.textContent = "กำลังลบ...";
  const res = await apiDelete(`works.php?id=${workId}`);
  if (!res.ok) {
    msg.textContent = res.message || "ลบไม่สำเร็จ";
    return;
  }

  msg.textContent = "ลบสำเร็จ";
  setTimeout(() => {
    location.href = "student.html";
  }, 500);
}

function escapeHtml(str) {
  return String(str || "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll("\"", "&quot;")
    .replaceAll("'", "&#039;");
}
