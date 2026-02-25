const summaryEl = document.getElementById("summary");
const catList = document.getElementById("catList");
const studentList = document.getElementById("studentList");
const workList = document.getElementById("workList");

// student search elements
const stuSearchEl = document.getElementById("stuSearch");
const stuSearchBtnEl = document.getElementById("stuSearchBtn");

// new UI elements for filtering works
const filterCatEl = document.getElementById("filterCat");
const filterSearchEl = document.getElementById("filterSearch");
const filterBtnEl = document.getElementById("filterBtn");

// cache categories for dropdown
let categoriesCache = [];

const AUTH_MESSAGES = ["ต้องล็อกอินก่อน", "ต้องเป็นแอดมิน"];

let editingCatId = null;
let editingStudentId = null;

// wire up listeners for filter controls
if (filterBtnEl) filterBtnEl.onclick = loadWorks;
if (filterSearchEl) filterSearchEl.onkeypress = e => { if (e.key === 'Enter') loadWorks(); };
if (filterCatEl) filterCatEl.onchange = loadWorks;

// student search events
if (stuSearchBtnEl) stuSearchBtnEl.onclick = applyStudentSearch;
if (stuSearchEl) stuSearchEl.onkeypress = e => { if (e.key === 'Enter') applyStudentSearch(); };

document.getElementById("addCat").onclick = addCategory;
document.getElementById("catCloseBtn").onclick = () => closeCatModal();
document.getElementById("catSaveBtn").onclick = saveCategoryEdit;
document.getElementById("stuCloseBtn").onclick = () => closeStudentModal();
document.getElementById("stuSaveBtn").onclick = saveStudentEdit;

(async function init() {
  const me = await apiGet("auth.php?action=me");
  const user = me && me.ok && me.data && me.data[0] ? me.data[0] : null;

  if (!user) {
    alert("กรุณาล็อกอินแอดมินก่อน");
    location.href = "login.html";
    return;
  }

  if (user.role !== "admin") {
    alert("หน้านี้สำหรับแอดมินเท่านั้น");
    location.href = "student.html";
    return;
  }

  await loadSummary();
  await loadCategories();
  await loadStudents();
  await loadWorks();
})();

function handleAuthError(res) {
  if (res && AUTH_MESSAGES.includes(res.message)) {
    alert(res.message);
    location.href = "login.html";
    return true;
  }
  return false;
}

async function loadSummary() {
  const res = await apiGet("works.php?summary=1");
  if (handleAuthError(res)) return;

  const s = res.data && res.data[0] ? res.data[0] : { total: 0, visible: 0, hidden: 0 };
  summaryEl.innerHTML = `
    <span class="pill">ทั้งหมด: ${s.total}</span>
    <span class="pill">แสดง: ${s.visible}</span>
    <span class="pill">ซ่อน: ${s.hidden}</span>
  `;
}

async function loadCategories() {
  const res = await apiGet("categories.php");
  if (handleAuthError(res)) return;

  const cats = res.data || [];
  categoriesCache = cats; // store for filtering

  catList.innerHTML = cats.map(c => `
    <div class="list-item row" style="justify-content:space-between;">
      <b>${escapeHtml(c.name)}</b>
      <div class="row gap">
        <button class="btn" type="button" style="background:#666;" onclick="openCatModal(${c.id}, '${escapeAttr(c.name)}')">แก้ไข</button>
        <button class="btn" type="button" onclick="delCategory(${c.id})">ลบ</button>
      </div>
    </div>
  `).join("");

  populateFilterCats();
}

function populateFilterCats() {
  if (!filterCatEl) return;
  // reset options preserving the "all" entry
  filterCatEl.innerHTML = '<option value="">ทุกหมวดหมู่</option>' +
    categoriesCache.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join("");
}


async function addCategory() {
  const nameEl = document.getElementById("catName");
  const name = nameEl.value.trim();
  if (!name) {
    alert("กรอกชื่อหมวดหมู่ก่อน");
    return;
  }

  const res = await apiPost("categories.php", { name });
  if (!res.ok) {
    handleAuthError(res);
    alert(res.message || "เพิ่มหมวดหมู่ไม่สำเร็จ");
    return;
  }

  nameEl.value = "";
  await loadCategories();
}

async function delCategory(id) {
  if (!confirm("ยืนยันการลบหมวดหมู่นี้?")) return;

  const res = await apiDelete(`categories.php?id=${id}`);
  if (!res.ok) {
    handleAuthError(res);
    alert(res.message || "ลบหมวดหมู่ไม่สำเร็จ");
    return;
  }

  await loadCategories();
}

function openCatModal(id, name) {
  editingCatId = id;
  document.getElementById("catEditName").value = name;
  document.getElementById("catModal").style.display = "flex";
}

function closeCatModal() {
  document.getElementById("catModal").style.display = "none";
  editingCatId = null;
}

async function saveCategoryEdit() {
  if (!editingCatId) return;

  const newName = document.getElementById("catEditName").value.trim();
  if (!newName) {
    alert("กรุณากรอกชื่อหมวดหมู่");
    return;
  }

  const res = await apiPut("categories.php", { id: editingCatId, name: newName });
  if (!res.ok) {
    handleAuthError(res);
    alert(res.message || "แก้ไขหมวดหมู่ไม่สำเร็จ");
    return;
  }

  closeCatModal();
  await loadCategories();
}

let studentCache = [];

async function loadStudents() {
  const res = await apiGet("users.php");
  if (handleAuthError(res)) return;
  if (!res.ok) {
    alert(res.message || "โหลดข้อมูลนักศึกษาไม่สำเร็จ");
    return;
  }

  const users = res.data || [];
  const students = users.filter(u => (u.role || "") === "student");
  studentCache = students; // keep for filtering

  renderStudentList(students);
}

function renderStudentList(students) {
  if (!students.length) {
    studentList.innerHTML = `<div class="muted">ยังไม่มีข้อมูลนักศึกษา</div>`;
    return;
  }

  studentList.innerHTML = students.map(s => {
    return `
      <div class="list-item">
        <div class="row" style="justify-content:space-between; align-items:start;">
          <div style="flex:1;">
            <b>#${s.id} ${escapeHtml(s.name || "-")}</b>
            <div class="muted">${escapeHtml(s.email || "-")}</div>
            <div class="muted" style="font-size:12px;">${escapeHtml(s.major || "-")} ปี ${escapeHtml(s.year || "-")}</div>
          </div>
          <div class="row gap">
            <button class="btn" type="button" style="background:#666;" onclick="openStudentModal(${s.id})">ดูข้อมูล</button>
            <button class="btn" type="button" style="background:#dc3545;" onclick="deleteStudent(${s.id})">ลบ</button>
          </div>
        </div>
      </div>
    `;
  }).join("");
}

function applyStudentSearch() {
  if (!stuSearchEl) return;
  const q = stuSearchEl.value.trim().toLowerCase();
  if (!q) {
    renderStudentList(studentCache);
    return;
  }
  const filtered = studentCache.filter(s => {
    return (
      (s.name || "").toLowerCase().includes(q) ||
      (s.email || "").toLowerCase().includes(q) ||
      (s.major || "").toLowerCase().includes(q)
    );
  });
  renderStudentList(filtered);
}

async function saveStudent(id) {
  const payload = {
    id,
    email: getValue(`stuEmail_${id}`),
    name: getValue(`stuName_${id}`),
    major: getValue(`stuMajor_${id}`),
    year: getValue(`stuYear_${id}`),
    bio: getValue(`stuBio_${id}`),
    avatar_url: getValue(`stuAvatar_${id}`)
  };

  if (!payload.email) {
    alert("กรุณากรอกอีเมล");
    return;
  }

  const res = await apiPut("users.php", payload);
  if (!res.ok) {
    handleAuthError(res);
    alert(res.message || "บันทึกข้อมูลนักศึกษาไม่สำเร็จ");
    return;
  }

  alert("บันทึกข้อมูลนักศึกษาแล้ว");
  await loadStudents();
}

async function openStudentModal(id) {
  const res = await apiGet("users.php");
  if (!res.ok) {
    alert("โหลดข้อมูลไม่สำเร็จ");
    return;
  }

  const student = (res.data || []).find(u => u.id == id);
  if (!student) {
    alert("ไม่พบข้อมูลนักศึกษา");
    return;
  }

  editingStudentId = id;
  const content = `
    <div class="row gap" style="margin-bottom:8px;">
      <label class="muted">อีเมล</label>
      <input id="stuModalEmail" class="input" value="${escapeAttr(student.email || "")}" />
    </div>
    <div class="row gap" style="margin-bottom:8px;">
      <label class="muted">ชื่อ</label>
      <input id="stuModalName" class="input" value="${escapeAttr(student.name || "")}" />
    </div>
    <div class="row gap" style="margin-bottom:8px;">
      <label class="muted">สาขา</label>
      <input id="stuModalMajor" class="input" value="${escapeAttr(student.major || "")}" />
    </div>
    <div class="row gap" style="margin-bottom:8px;">
      <label class="muted">ชั้นปี</label>
      <input id="stuModalYear" class="input" value="${escapeAttr(student.year || "")}" />
    </div>
    <div style="margin-bottom:8px;">
      <label class="muted">แนะนำตัว</label>
      <textarea id="stuModalBio" class="input" rows="3" style="margin-top:4px;">${escapeHtml(student.bio || "")}</textarea>
    </div>
    <div style="margin-bottom:8px;">
      <label class="muted">รูปโปรไฟล์</label>
      <input id="stuModalAvatar" class="input" value="${escapeAttr(student.avatar_url || "")}" style="margin-top:4px;" />
    </div>
  `;
  
  document.getElementById("studentModalContent").innerHTML = content;
  document.getElementById("studentModal").style.display = "flex";
}

function closeStudentModal() {
  document.getElementById("studentModal").style.display = "none";
  editingStudentId = null;
}

async function saveStudentEdit() {
  if (!editingStudentId) return;

  const payload = {
    id: editingStudentId,
    email: document.getElementById("stuModalEmail").value.trim(),
    name: document.getElementById("stuModalName").value.trim(),
    major: document.getElementById("stuModalMajor").value.trim(),
    year: document.getElementById("stuModalYear").value.trim(),
    bio: document.getElementById("stuModalBio").value.trim(),
    avatar_url: document.getElementById("stuModalAvatar").value.trim()
  };

  if (!payload.email) {
    alert("กรุณากรอกอีเมล");
    return;
  }

  const res = await apiPut("users.php", payload);
  if (!res.ok) {
    alert(res.message || "บันทึกไม่สำเร็จ");
    return;
  }

  closeStudentModal();
  await loadStudents();
}

async function deleteStudent(id) {
  const name = getValue(`stuName_${id}`) || `ID ${id}`;
  if (!confirm(`ยืนยันการลบนักศึกษา ${name}?`)) return;

  const res = await apiDelete(`users.php?id=${id}`);
  if (!res.ok) {
    handleAuthError(res);
    alert(res.message || "ลบนักศึกษาไม่สำเร็จ");
    return;
  }

  alert("ลบนักศึกษาแล้ว");
  await loadStudents();
  await loadSummary();
  await loadWorks();
}

async function loadWorks() {
  // build URL with filters
  let url = "works.php?admin=1";
  if (filterSearchEl) {
    const q = filterSearchEl.value.trim();
    if (q) url += `&q=${encodeURIComponent(q)}`;
  }
  if (filterCatEl) {
    const cat = filterCatEl.value;
    if (cat) url += `&category_id=${encodeURIComponent(cat)}`;
  }

  const res = await apiGet(url);
  if (handleAuthError(res)) return;

  const works = res.data || [];
  if (works.length === 0) {
    workList.innerHTML = `<div class="muted">ไม่พบผลงาน</div>`;
    return;
  }

  workList.innerHTML = works.map(w => {
    const visible = String(w.is_visible) === "1";
    return `
      <div class="list-item">
        <div class="row" style="justify-content:space-between;">
          <div style="flex:1;">
            <b>${escapeHtml(w.title)}</b>
            <div class="muted">${escapeHtml(w.owner_name || "-")} / ${escapeHtml(w.category_name || "-")}</div>
            <p class="muted" style="margin:4px 0 8px;">${escapeHtml((w.description || "").slice(0, 140))}</p>
          </div>
          <div class="row gap" style="flex-direction:column; justify-content:flex-start;">
            <span class="pill">${visible ? "แสดง" : "ซ่อน"}</span>
          </div>
        </div>
        <div class="row gap" style="justify-content:flex-start;">
          <a class="btn" href="work.html?id=${w.id}" target="_blank" style="background:#666;">ชมผลงาน</a>
          <button class="btn" type="button" onclick="toggleWork(${w.id}, ${visible ? 0 : 1})">${visible ? "ซ่อน" : "แสดง"}</button>
        </div>
      </div>
    `;
  }).join("");
}

async function toggleWork(id, toVisible) {
  const res = await apiPut("works.php", { id, is_visible: toVisible });
  if (!res.ok) {
    handleAuthError(res);
    alert(res.message || "อัปเดตสถานะไม่สำเร็จ");
    return;
  }

  await loadSummary();
  await loadWorks();
}

function getValue(id) {
  const el = document.getElementById(id);
  return el ? el.value.trim() : "";
}

function escapeHtml(str) {
  return String(str || "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll("\"", "&quot;")
    .replaceAll("'", "&#039;");
}

function escapeAttr(str) {
  return escapeHtml(str).replaceAll("`", "&#096;");
}
