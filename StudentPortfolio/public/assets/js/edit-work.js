let workId = null;
let currentUrl = "";
let pendingFile = null;
let galleryImages = [];

const titleIn = document.getElementById("titleIn");
const categoryIn = document.getElementById("categoryIn");
const descIn = document.getElementById("descIn");
const workUrlIn = document.getElementById("workUrlIn");
const coverFileIn = document.getElementById("coverFileIn");
const coverPreview = document.getElementById("coverPreview");
const saveBtn = document.getElementById("saveBtn");
const deleteBtn = document.getElementById("deleteBtn");
const msg = document.getElementById("msg");
const galleryFileIn = document.getElementById("galleryFileIn");
const addImageBtn = document.getElementById("addImageBtn");

coverFileIn.onchange = previewCover;
document.getElementById("clearCoverBtn").onclick = clearCover;
document.getElementById("saveBtn").onclick = saveWork;
document.getElementById("deleteBtn").onclick = deleteWork;
addImageBtn.onclick = uploadGalleryImage;

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

  // Load gallery images
  await loadGallery();
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

async function loadGallery() {
  const res = await apiGet(`work-images.php?work_id=${workId}`);
  galleryImages = res.data || [];
  renderGallery();
}

function renderGallery() {
  const container = document.getElementById("galleryContainer");
  const emptyMsg = document.getElementById("emptyGalleryMsg");

  if (!galleryImages.length) {
    container.style.display = "none";
    emptyMsg.style.display = "block";
    return;
  }

  container.style.display = "grid";
  emptyMsg.style.display = "none";

  container.innerHTML = galleryImages.map(img => `
    <div style="position:relative; border-radius:8px; overflow:hidden;">
      <img src="${escapeHtml(img.image_url)}" alt="gallery" style="width:100%; height:150px; object-fit:cover;">
      <button onclick="deleteGalleryImage(${img.id})" style="position:absolute; top:4px; right:4px; background:#dc3545; color:white; border:none; border-radius:50%; width:28px; height:28px; cursor:pointer; font-size:18px; display:flex; align-items:center; justify-content:center; padding:0;">×</button>
    </div>
  `).join("");
}

async function uploadGalleryImage() {
  const file = galleryFileIn.files && galleryFileIn.files[0] ? galleryFileIn.files[0] : null;
  if (!file) {
    msg.textContent = "เลือกรูปภาพก่อน";
    return;
  }

  msg.textContent = "กำลังอัปโหลด...";
  
  const formData = new FormData();
  formData.append("file", file);
  formData.append("work_id", workId);

  try {
    const response = await fetch("../api/work-images.php", {
      method: "POST",
      body: formData
    });
    const result = await response.json();

    if (!result.ok) {
      msg.textContent = result.message || "อัปโหลดไม่สำเร็จ";
      return;
    }

    msg.textContent = "อัปโหลดสำเร็จ";
    galleryFileIn.value = "";
    await loadGallery();
  } catch (e) {
    msg.textContent = "เกิดข้อผิดพลาด: " + e.message;
  }
}

async function deleteGalleryImage(imageId) {
  if (!confirm("ลบรูปภาพนี้?")) return;

  const res = await apiDelete(`work-images.php?id=${imageId}`);
  if (!res.ok) {
    msg.textContent = res.message || "ลบไม่สำเร็จ";
    return;
  }

  await loadGallery();
}

function escapeHtml(str) {
  return String(str || "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll("\"", "&quot;")
    .replaceAll("'", "&#039;");
}
