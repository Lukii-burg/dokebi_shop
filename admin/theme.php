<?php
// theme.php  shared JS for dark/light + alert popup
?>
<script>
// Apply saved theme on every page load
document.addEventListener("DOMContentLoaded", () => {
    const saved = localStorage.getItem("dokebi_theme");
    if (saved === "light") {
        document.body.classList.add("light-mode");
    }
});

// Toggle between dark and light themes
function toggleMode() {
    const isLight = document.body.classList.toggle("light-mode");
    localStorage.setItem("dokebi_theme", isLight ? "light" : "dark");
}

// Reusable alert popup
function showAlert(msg) {
    const box = document.getElementById("alertBox");
    if (!box) return;
    box.innerHTML = msg;
    box.classList.add("show");
    setTimeout(() => box.classList.remove("show"), 2000);
}
</script>
