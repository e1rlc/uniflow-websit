// Lucide icons
if (window.lucide) {
    lucide.createIcons();
}

// Number counter: elements with data-count count up from 0
document.querySelectorAll("[data-count]").forEach(function (el) {
    var target = parseFloat(el.dataset.count);
    var decimals = el.dataset.count.indexOf(".") > -1 ? 2 : 0;
    var start = null;

    function step(ts) {
        if (!start) start = ts;
        var progress = Math.min((ts - start) / 1200, 1);
        el.textContent = (target * progress).toFixed(decimals);
        if (progress < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
});

// Notifications dropdown
var bell = document.getElementById("bell");
var notif = document.getElementById("notif");

if (bell && notif) {
    bell.addEventListener("click", function (e) {
        e.stopPropagation();
        notif.classList.toggle("open");
    });
    document.addEventListener("click", function () {
        notif.classList.remove("open");
    });
}