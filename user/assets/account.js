const panel = document.getElementById("accountPanel");
const navButtons = document.querySelectorAll("[data-panel]");
const pageLoader = document.getElementById("accountPageLoader");

function skeletonHTML() {
  return `
    <div class="panel-card">
      <div class="skeleton skel-line" style="width:40%"></div>
      <div class="skeleton skel-line" style="width:70%"></div>
      <div class="skeleton skel-card"></div>
      <div class="skeleton skel-card"></div>
    </div>
  `;
}

function setActiveNav(name) {
  navButtons.forEach(btn => {
    const isActive = btn.dataset.panel === name;
    btn.classList.toggle("active", isActive);
  });
  const url = new URL(window.location.href);
  url.searchParams.set("panel", name);
  window.history.replaceState({}, "", url);
}

function animatePanel() {
  if (!panel) return;
  panel.classList.add("panel-fade-enter");
  requestAnimationFrame(() => {
    panel.classList.add("panel-fade-enter-active");
    setTimeout(() => panel.classList.remove("panel-fade-enter", "panel-fade-enter-active"), 240);
  });
}

async function loadPanel(name) {
  if (!panel) return;
  setActiveNav(name);
  panel.innerHTML = skeletonHTML();
  try {
    const res = await fetch(`panels/${name}.php`, { cache: "no-store" });
    const html = await res.text();
    panel.innerHTML = html;
    animatePanel();
    initPanelScripts(name);
  } catch (err) {
    panel.innerHTML = `<div class="panel-card"><div class="notice error">Could not load this section. Please retry.</div></div>`;
  }
}

function initPanelScripts(name) {
  if (name === "profile") initProfileForm();
  if (name === "chat") initChatPanel();
  if (name === "security") initSecurityForm();
}

function initProfileForm() {
  const pf = document.getElementById("profileForm");
  if (!pf) return;
  pf.addEventListener("submit", async (e) => {
    e.preventDefault();
    const fd = new FormData(pf);
    fd.set("csrf", window.__CSRF__);
    try {
      const res = await fetch("api/profile_update.php", { method: "POST", body: fd });
      const data = await res.json();
      if (data.ok) {
        const img = document.querySelector(".profile-avatar img");
        if (img && data.user?.profile_image_url) {
          img.src = data.user.profile_image_url;
        }
        alert(data.message || "Profile updated.");
      } else {
        alert(data.error || "Profile update failed.");
      }
    } catch (err) {
      alert("Profile update failed. Please try again.");
    }
  });
}

function renderMessages(list) {
  const chatBox = document.getElementById("accountChatBox");
  if (!chatBox) return;
  chatBox.innerHTML = "";
  if (!list || !list.length) {
    chatBox.innerHTML = '<div class="muted">No messages yet. Say hello!</div>';
    return;
  }
  list.forEach(msg => {
    const wrap = document.createElement("div");
    wrap.className = "bubble " + ((msg.role || "") === "user" ? "me" : "support");
    const body = document.createElement("div");
    body.innerHTML = (msg.message || "").replace(/</g, "&lt;").replace(/\n/g, "<br>");
    const small = document.createElement("small");
    small.textContent = msg.created_at_human || "";
    wrap.appendChild(body);
    wrap.appendChild(small);
    chatBox.appendChild(wrap);
  });
  chatBox.scrollTop = chatBox.scrollHeight;
}

function showChatError(msg) {
  const chatError = document.getElementById("accountChatError");
  if (!chatError) return;
  chatError.textContent = msg || "";
  chatError.style.display = msg ? "block" : "none";
}

function initChatPanel() {
  const chatForm = document.getElementById("accountChatForm");
  const chatBox = document.getElementById("accountChatBox");
  const clearBtn = document.getElementById("accountChatClear");
  const chatInput = document.getElementById("accountChatInput");

  if (chatBox && chatBox.dataset.messages) {
    try {
      const parsed = JSON.parse(chatBox.dataset.messages);
      renderMessages(parsed);
    } catch (e) {
      // ignore parse errors
    }
  }

  if (chatForm && chatInput) {
    chatForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      showChatError("");
      const fd = new FormData(chatForm);
      fd.set("csrf", window.__CSRF__);
      const message = (fd.get("message") || "").toString().trim();
      if (!message) return;
      try {
        const res = await fetch("api/chat_send.php", { method: "POST", body: fd });
        const data = await res.json();
        if (data.ok) {
          renderMessages(data.messages || []);
          chatInput.value = "";
          chatInput.focus();
        } else {
          showChatError(data.error || "Chat failed");
        }
      } catch (err) {
        showChatError("Chat failed. Please try again.");
      }
    });
  }

  if (clearBtn) {
    clearBtn.addEventListener("click", async () => {
      showChatError("");
      const fd = new FormData();
      fd.set("csrf", window.__CSRF__);
      try {
        const res = await fetch("api/chat_clear.php", { method: "POST", body: fd });
        const data = await res.json();
        if (data.ok) {
          renderMessages([]);
        } else {
          showChatError(data.error || "Clear failed");
        }
      } catch (err) {
        showChatError("Clear failed. Please retry.");
      }
    });
  }
}

function initSecurityForm() {
  const form = document.getElementById("securityForm");
  if (!form) return;
  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    const fd = new FormData(form);
    fd.set("csrf", window.__CSRF__);
    try {
      const res = await fetch("api/security_update.php", { method: "POST", body: fd });
      const data = await res.json();
      if (data.ok) {
        alert(data.message || "Password updated.");
        form.reset();
      } else {
        alert(data.error || "Could not update password.");
      }
    } catch (err) {
      alert("Could not update password. Please try again.");
    }
  });
}

function initNav() {
  navButtons.forEach(btn => {
    btn.addEventListener("click", (e) => {
      e.preventDefault();
      const target = btn.dataset.panel;
      if (!target) return;
      loadPanel(target);
    });
  });
}

window.addEventListener("load", () => {
  document.body.classList.add("loaded");
  if (pageLoader) {
    setTimeout(() => pageLoader.remove(), 400);
  }
});

initNav();

const initialPanel = (window.__INITIAL_PANEL__ || new URLSearchParams(window.location.search).get("panel") || "profile");
loadPanel(initialPanel);
