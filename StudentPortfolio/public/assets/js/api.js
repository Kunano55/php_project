const API_BASE = "../api";

async function apiGet(path) {
  const res = await fetch(`${API_BASE}/${path}`, { credentials: "include" });
  return res.json();
}
async function apiPost(path, body) {
  const res = await fetch(`${API_BASE}/${path}`, {
    credentials: "include",
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body || {})
  });
  return res.json();
}
async function apiPut(path, body) {
  const res = await fetch(`${API_BASE}/${path}`, {
    credentials: "include",
    method: "PUT",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body || {})
  });
  return res.json();
}
async function apiDelete(path) {
  const res = await fetch(`${API_BASE}/${path}`, { method: "DELETE", credentials: "include" });
  return res.json();
}
function qs(name) {
  return new URLSearchParams(location.search).get(name);
}
