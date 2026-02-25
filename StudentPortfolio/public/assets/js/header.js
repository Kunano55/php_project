(function () {
  function currentPage() {
    const file = location.pathname.split("/").pop();
    return file || "index.html";
  }

  function escapeHtml(str) {
    return String(str || "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll("\"", "&quot;")
      .replaceAll("'", "&#039;");
  }

  async function getCurrentUser() {
    try {
      if (typeof apiGet === "function") {
        const me = await apiGet("auth.php?action=me");
        return me && me.ok && me.data && me.data[0] ? me.data[0] : null;
      }

      const res = await fetch("../api/auth.php?action=me", { credentials: "include" });
      const me = await res.json();
      return me && me.ok && me.data && me.data[0] ? me.data[0] : null;
    } catch (err) {
      return null;
    }
  }

  async function doLogout() {
    try {
      if (typeof apiPost === "function") {
        await apiPost("auth.php?action=logout", {});
      } else {
        await fetch("../api/auth.php?action=logout", {
          method: "POST",
          credentials: "include",
          headers: { "Content-Type": "application/json" },
          body: "{}"
        });
      }
    } catch (err) {
      // ignore and continue redirect
    }
    location.href = "login.html";
  }

  function linkHtml(link, page) {
    const isActive = link.href && link.href === page ? "active" : "";
    if (link.logout) {
      return `<a href="#" data-logout="1" class="${isActive}">${escapeHtml(link.label)}</a>`;
    }
    return `<a href="${escapeHtml(link.href)}" class="${isActive}">${escapeHtml(link.label)}</a>`;
  }

  function buildLinks(user) {
    const role = (user && user.role) || "";
    const isLoggedIn = !!user;
    const links = [{ href: "index.html", label: "ผลงาน" }];

    if (role === "student") {
      links.push({ href: "student.html", label: "นักศึกษา" });
      links.push({ href: "profile.html", label: "โปรไฟล์" });
    }

    if (role === "admin") {
      links.push({ href: "admin.html", label: "หน้าจัดการแอดมิน" });
    }

    if (!isLoggedIn) {
      links.push({ href: "login.html", label: "ล็อกอิน" });
      links.push({ href: "register.html", label: "สมัคร" });
      return links;
    }

    links.push({ logout: true, label: "ล็อกเอ้า" });
    return links;
  }

  class SiteHeader extends HTMLElement {
    async connectedCallback() {
      const user = await getCurrentUser();
      const links = buildLinks(user);
      const page = currentPage();

      this.innerHTML = `
        <header class="topbar">
          <div class="container row">
            <a class="brand" href="index.html">Student Portfolio</a>
            <nav class="nav">
              ${links.map((l) => linkHtml(l, page)).join("")}
            </nav>
          </div>
        </header>
      `;

      const logout = this.querySelector("[data-logout='1']");
      if (logout) {
        logout.addEventListener("click", async (e) => {
          e.preventDefault();
          await doLogout();
        });
      }
    }
  }

  if (!customElements.get("site-header")) {
    customElements.define("site-header", SiteHeader);
  }
})();
