(async function init(){
  const id = qs("id");
  const res = await apiGet(`works.php?id=${encodeURIComponent(id)}`);
  const w = (res.data && res.data[0]) ? res.data[0] : null;
  if(!w){ alert("ไม่พบผลงาน"); location.href="index.html"; return; }

  document.getElementById("cover").src = w.cover_url || "https://via.placeholder.com/800x450?text=No+Cover";
  document.getElementById("title").textContent = w.title || "-";
  document.getElementById("desc").textContent = w.description || "-";
  document.getElementById("cat").textContent = w.category_name || "-";
  document.getElementById("status").textContent = (w.is_visible == 1) ? "แสดงผล" : "ถูกซ่อน";
  const link = document.getElementById("link");
  link.href = w.work_url || "#";
})();