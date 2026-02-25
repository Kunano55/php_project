(function () {
  const loginLinks = document.querySelectorAll("[data-login='1']");
  const logoutLinks = document.querySelectorAll("[data-logout='1']");
  if (!loginLinks.length && !logoutLinks.length) return;

  function setVisible(nodes, visible) {
    nodes.forEach((el) => {
      el.style.display = visible ? "" : "none";
    });
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
      // ignore network errors and continue redirect
    }
    location.href = "login.html";
  }

  logoutLinks.forEach((el) => {
    el.addEventListener("click", async (e) => {
      e.preventDefault();
      await doLogout();
    });
  });

  (async function syncNavAuthButtons() {
    const user = await getCurrentUser();
    const isLoggedIn = !!user;
    setVisible(loginLinks, !isLoggedIn);
    setVisible(logoutLinks, isLoggedIn);
  })();
})();
