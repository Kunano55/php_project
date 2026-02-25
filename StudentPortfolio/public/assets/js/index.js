const grid = document.getElementById("grid");
const empty = document.getElementById("empty");
const qEl = document.getElementById("q");
const catEl = document.getElementById("category");
document.getElementById("btnSearch").onclick = load;

(async function init() {
  const cats = await apiGet("categories.php");
  catEl.innerHTML = `<option value="">ทุกหมวด</option>` +
    (cats.data || []).map(c => `<option value="${c.id}">${c.name}</option>`).join("");
  await load();
})();

async function load() {
  const q = encodeURIComponent(qEl.value || "");
  const category_id = encodeURIComponent(catEl.value || "");
  const data = await apiGet(`works.php?q=${q}&category_id=${category_id}`);
  const works = data.data || [];
  grid.innerHTML = works.map(renderCard).join("");
  empty.style.display = works.length ? "none" : "block";
}

function renderCard(w) {
  const cover = w.cover_url || "https://via.placeholder.com/800x450?text=No+Cover";
  const avatar = w.owner_avatar || "https://via.placeholder.com/40?text=User";
  return `
    <article class="card work-card">
      <img class="work-cover" src="${cover}" alt="cover" />
      <h3 style="margin:10px 0 6px;">${escapeHtml(w.title)}</h3>
      <div class="row gap" style="justify-content:flex-start;">
        <span class="pill">${escapeHtml(w.category_name || "-")}</span>
      </div>
      <a class="row gap" href="view-profile.html?id=${w.user_id}" style="text-decoration:none; color:inherit; margin:10px 0;">
        <img src="${avatar}" alt="avatar" style="width:32px; height:32px; border-radius:50%; object-fit:cover;" />
        <span class="muted" style="font-size:14px; align-self:center;">${escapeHtml(w.owner_name || "-")}</span>
      </a>
      <p class="muted" style="margin:6px 0 12px; min-height:44px;">
        ${escapeHtml((w.description || "").slice(0,120))}
      </p>
      <a class="btn" href="work.html?id=${w.id}">ดูรายละเอียด</a>
    </article>
  `;
}

function escapeHtml(str) {
  return String(str || "")
    .replaceAll("&","&amp;").replaceAll("<","&lt;")
    .replaceAll(">","&gt;").replaceAll('"',"&quot;")
    .replaceAll("'","&#039;");
}