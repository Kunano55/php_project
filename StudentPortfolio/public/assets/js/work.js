(async function init(){
  const id = qs("id");
  const res = await apiGet(`works.php?id=${encodeURIComponent(id)}`);
  const w = (res.data && res.data[0]) ? res.data[0] : null;
  if(!w){ alert("ไม่พบผลงาน"); location.href="index.html"; return; }

  document.getElementById("cover").src = w.cover_url || "https://via.placeholder.com/800x450?text=No+Cover";
  document.getElementById("title").textContent = w.title || "-";
  document.getElementById("desc").textContent = w.description || "-";
  document.getElementById("cat").textContent = w.category_name || "-";
  document.getElementById("ownerName").textContent = w.owner_name || "-";
  document.getElementById("ownerAvatar").src = w.owner_avatar || "https://via.placeholder.com/48?text=User";
  document.getElementById("ownerLink").href = `view-profile.html?id=${w.user_id}`;
  const link = document.getElementById("link");
  link.href = w.work_url || "#";
  
  // Load gallery images
  loadGallery(w.id);
})();

async function loadGallery(workId) {
  const res = await apiGet(`work-images.php?work_id=${workId}`);
  const images = res.data || [];
  const gallery = document.getElementById("gallery");
  const empty = document.getElementById("emptyGallery");
  const section = document.getElementById("gallerySection");

  section.style.display = "block"; // Always show the section
  
  if (!images.length) {
    gallery.style.display = "none";
    empty.style.display = "block";
    return;
  }

  section.style.display = "block";
  empty.style.display = "none";
  gallery.style.display = "grid";
  
  gallery.innerHTML = images.map(img => `
    <div style="position:relative; overflow:hidden; border-radius:8px; aspect-ratio:1;">
      <img src="${escapeHtml(img.image_url)}" alt="gallery" style="width:100%; height:100%; object-fit:cover; cursor:pointer;" onclick="openLightbox('${escapeAttr(img.image_url)}')">
    </div>
  `).join("");
}

function openLightbox(url) {
  const modal = document.createElement("div");
  modal.style.cssText = "position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.8); z-index:10000; display:flex; align-items:center; justify-content:center; cursor:pointer;";
  modal.onclick = () => modal.remove();
  
  const img = document.createElement("img");
  img.src = url;
  img.style.cssText = "max-width:90%; max-height:90%; object-fit:contain;";
  
  modal.appendChild(img);
  document.body.appendChild(modal);
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
  return String(str || "").replaceAll("'", "\\'");
}