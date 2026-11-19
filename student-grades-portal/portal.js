if (window.lucide) { lucide.createIcons(); }

// عدّاد الأرقام
document.querySelectorAll("[data-count]").forEach(function (el) {
    var target = parseFloat(el.dataset.count);
    var decimals = el.dataset.count.indexOf(".") > -1 ? 2 : 0;
    var start = null;
    function step(ts) {
        if (!start) start = ts;
        var p = Math.min((ts - start) / 1100, 1);
        el.textContent = (target * p).toFixed(decimals);
        if (p < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
});

// ملء الأشرطة تدريجياً
setTimeout(function () {
    document.querySelectorAll("[data-w]").forEach(function (s) { s.style.width = s.dataset.w + "%"; });
}, 150);

// قائمة الإشعارات
var bell = document.getElementById("bell");
var notif = document.getElementById("notif");
if (bell && notif) {
    bell.addEventListener("click", function (e) { e.stopPropagation(); notif.classList.toggle("open"); });
    document.addEventListener("click", function () { notif.classList.remove("open"); });
}

// البحث في المواد
var q = document.getElementById("q");
if (q) {
    q.addEventListener("input", function () {
        var t = q.value.toLowerCase();
        document.querySelectorAll(".course-row").forEach(function (r) {
            r.style.display = r.textContent.toLowerCase().indexOf(t) > -1 ? "" : "none";
        });
    });
}

// حركة الانتقال بين الصفحات
document.querySelectorAll("a[href]").forEach(function (a) {
    a.addEventListener("click", function (e) {
        var h = a.getAttribute("href");
        if (!h || h.charAt(0) === "#" || a.target === "_blank" || e.ctrlKey || e.metaKey || e.shiftKey) return;
        if (a.hostname && a.hostname !== location.hostname) return;
        e.preventDefault();
        document.body.classList.add("leaving");
        setTimeout(function () { location.href = a.href; }, 230);
    });
});
window.addEventListener("pageshow", function (e) {
    if (e.persisted) document.body.classList.remove("leaving");
});