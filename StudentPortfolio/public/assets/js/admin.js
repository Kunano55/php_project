function handleAuthError(res){
  if(res && (res.message === "ต้องล็อกอินก่อน" || res.message === "ต้องเป็นแอดมิน")){
    alert(res.message);
    location.href = "login.html";
    return true;
  }
  return false;
}

const summaryEl = document.getElementById("summary");
const catList = document.getElementById("catList");
const workList = document.getElementById("workList");

document.getElementById("addCat").onclick = addCategory;

(async function init(){
  await loadSummary();
  await loadCategories();
  await loadWorks();
})();

async function loadSummary(){
  const res = await apiGet("works.php?summary=1");
  if(handleAuthError(res)) return;
  const s = (res.data && res.data[0]) ? res.data[0] : { total:0, visible:0, hidden:0 };
  summaryEl.innerHTML = `
    <span class="pill">ทั้งหมด: ${s.total}</span>
    <span class="pill">แสดง: ${s.visible}</span>
    <span class="pill">ซ่อน: ${s.hidden}</span>
  `;
}

async function loadCategories(){
  const res = await apiGet("categories.php");
  if(handleAuthError(res)) return;
  const cats = res.data || [];
  catList.innerHTML = cats.map(c => `
    <div class="list-item row" style="justify-content:space-between;">
      <b>${c.name}</b>
      <button class="btn" onclick="delCategory(${c.id})">ลบ</button>
    </div>
  `).join("");
}

async function addCategory(){
  const name = document.getElementById("catName").value.trim();
  if(!name) return alert("ใส่ชื่อหมวดก่อน");
  const res = await apiPost("categories.php", { name });
  if(!res.ok){ handleAuthError(res); return alert(res.message || "เพิ่มไม่สำเร็จ"); }
  document.getElementById("catName").value = "";
  await loadCategories();
}

async function delCategory(id){
  if(!confirm("ลบหมวดนี้?")) return;
  const res = await apiDelete(`categories.php?id=${id}`);
  if(!res.ok){ handleAuthError(res); return alert(res.message || "ลบไม่สำเร็จ"); }
  await loadCategories();
}

async function loadWorks(){
  const res = await apiGet("works.php?admin=1");
  if(handleAuthError(res)) return;
  const works = res.data || [];
  workList.innerHTML = works.map(w => `
    <div class="list-item">
      <div class="row" style="justify-content:space-between;">
        <div>
          <b>${w.title}</b>
          <div class="muted">${w.category_name || "-"}</div>
        </div>
        <div class="row gap" style="justify-content:flex-end;">
          <span class="pill">${w.is_visible==1 ? "แสดง" : "ซ่อน"}</span>
          <button class="btn" onclick="toggleWork(${w.id}, ${w.is_visible==1 ? 0 : 1})">
            ${w.is_visible==1 ? "ซ่อน" : "แสดง"}
          </button>
        </div>
      </div>
      <p class="muted" style="margin:8px 0 0;">${(w.description||"").slice(0,140)}</p>
    </div>
  `).join("");
}

async function toggleWork(id, toVisible){
  const res = await apiPut("works.php", { id, is_visible: toVisible });
  if(!res.ok){ handleAuthError(res); return alert(res.message || "อัปเดตไม่สำเร็จ"); }
  await loadSummary();
  await loadWorks();
}
