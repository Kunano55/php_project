(async function init() {
  const params = new URLSearchParams(location.search);
  const userId = params.get("id");

  if (!userId) {
    alert("ต้องระบุ id ผู้ใช้");
    location.href = "index.html";
    return;
  }

  // Load user info
  const usersRes = await apiGet(`users.php?id=${userId}`);
  const user = usersRes.ok && usersRes.data && usersRes.data[0]
    ? usersRes.data[0]
    : null;

  if (!user) {
    alert("ไม่พบผู้ใช้");
    location.href = "index.html";
    return;
  }

  renderProfile(user);
  await loadUserWorks(userId);
})();

function renderProfile(user) {
  document.getElementById("avatar").src = user.avatar_url || "https://via.placeholder.com/256?text=Profile";
  document.getElementById("name").textContent = user.name || "-";
  document.getElementById("bio").textContent = user.bio || "-";
  document.getElementById("major").textContent = user.major || "-";
  document.getElementById("year").textContent = user.year ? "ปี " + user.year : "-";
}

async function loadUserWorks(userId) {
  const res = await apiGet(`works.php?user_id=${userId}`);
  const works = res.data || [];
  const worksEl = document.getElementById("works");
  const emptyEl = document.getElementById("emptyWorks");

  if (!works.length) {
    worksEl.style.display = "none";
    emptyEl.style.display = "block";
    return;
  }

  worksEl.innerHTML = works.map(w => {
    const cover = w.cover_url || "https://via.placeholder.com/800x450?text=No+Cover";
    return `
      <article class="card work-card">
        <img class="work-cover" src="${cover}" alt="cover" />
        <h3 style="margin:10px 0 6px;">${escapeHtml(w.title)}</h3>
        <div class="row gap" style="justify-content:flex-start;">
          <span class="pill">${escapeHtml(w.category_name || "-")}</span>
        </div>
        <p class="muted" style="margin:10px 0 12px; min-height:44px;">
          ${escapeHtml((w.description || "").slice(0,120))}
        </p>
        <a class="btn" href="work.html?id=${w.id}">ดูรายละเอียด</a>
      </article>
    `;
  }).join("");
}

function escapeHtml(str) {
  return String(str || "")
    .replaceAll("&", "&amp;").replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;").replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}
