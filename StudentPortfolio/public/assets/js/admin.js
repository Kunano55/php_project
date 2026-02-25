const summaryEl = document.getElementById("summary");
const catList = document.getElementById("catList");
const studentList = document.getElementById("studentList");
const workList = document.getElementById("workList");

const AUTH_MESSAGES = ["ต้องล็อกอินก่อน", "ต้องเป็นแอดมิน"];

document.getElementById("addCat").onclick = addCategory;

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
  catList.innerHTML = cats.map(c => `
    <div class="list-item row" style="justify-content:space-between;">
      <b>${escapeHtml(c.name)}</b>
      <button class="btn" type="button" onclick="delCategory(${c.id})">ลบ</button>
    </div>
  `).join("");
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

async function loadStudents() {
  const res = await apiGet("users.php");
  if (handleAuthError(res)) return;
  if (!res.ok) {
    alert(res.message || "โหลดข้อมูลนักศึกษาไม่สำเร็จ");
    return;
  }

  const users = res.data || [];
  const students = users.filter(u => (u.role || "") === "student");

  if (!students.length) {
    studentList.innerHTML = `<div class="muted">ยังไม่มีข้อมูลนักศึกษา</div>`;
    return;
  }

  studentList.innerHTML = students.map(s => {
    return `
      <div class="list-item">
        <div class="row gap">
          <b>#${s.id} ${escapeHtml(s.name || "-")}</b>
          <span class="pill">student</span>
        </div>

        <div class="row gap" style="margin-top:8px;">
          <input id="stuEmail_${s.id}" class="input" placeholder="email" value="${escapeAttr(s.email || "")}" />
          <input id="stuName_${s.id}" class="input" placeholder="ชื่อ" value="${escapeAttr(s.name || "")}" />
        </div>

        <div class="row gap" style="margin-top:8px;">
          <input id="stuMajor_${s.id}" class="input" placeholder="สาขา" value="${escapeAttr(s.major || "")}" />
          <input id="stuYear_${s.id}" class="input" placeholder="ชั้นปี" value="${escapeAttr(s.year || "")}" />
        </div>

        <textarea id="stuBio_${s.id}" class="input" rows="3" placeholder="bio" style="margin-top:8px;">${escapeHtml(s.bio || "")}</textarea>
        <input id="stuAvatar_${s.id}" class="input" placeholder="avatar url" value="${escapeAttr(s.avatar_url || "")}" style="margin-top:8px;" />

        <div class="row gap" style="justify-content:flex-start; margin-top:10px;">
          <button class="btn" type="button" onclick="saveStudent(${s.id})">บันทึกข้อมูล</button>
          <button class="btn" type="button" style="background:#fff;color:#111;" onclick="deleteStudent(${s.id})">ลบนักศึกษา</button>
        </div>
      </div>
    `;
  }).join("");
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
  const res = await apiGet("works.php?admin=1");
  if (handleAuthError(res)) return;

  const works = res.data || [];
  workList.innerHTML = works.map(w => {
    const visible = String(w.is_visible) === "1";
    return `
      <div class="list-item">
        <div class="row" style="justify-content:space-between;">
          <div>
            <b>${escapeHtml(w.title)}</b>
            <div class="muted">${escapeHtml(w.category_name || "-")}</div>
          </div>
          <div class="row gap" style="justify-content:flex-end;">
            <span class="pill">${visible ? "แสดง" : "ซ่อน"}</span>
            <button class="btn" type="button" onclick="toggleWork(${w.id}, ${visible ? 0 : 1})">${visible ? "ซ่อน" : "แสดง"}</button>
          </div>
        </div>
        <p class="muted" style="margin:8px 0 0;">${escapeHtml((w.description || "").slice(0, 140))}</p>
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
