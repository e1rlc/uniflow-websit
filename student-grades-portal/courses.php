<?php
require "layout.php";
require "storage.php";
$uid = (int) $_SESSION["user_id"];
$id  = (int) ($_GET["id"] ?? 0);

/* ========== صفحة تفاصيل مادة ========== */
if ($id > 0) {
    $s = $conn->prepare("SELECT id, code, name, credits FROM courses WHERE id = ?");
    $s->bind_param("i", $id);
    $s->execute();
    $course = $s->get_result()->fetch_assoc();
    $s->close();
    if (!$course) { http_response_code(404); die("Course not found."); }

    if (!$isAdmin) {
        $c = $conn->prepare("SELECT 1 FROM enrollments WHERE user_id = ? AND course_id = ? LIMIT 1");
        $c->bind_param("ii", $uid, $id);
        $c->execute();
        if (!$c->get_result()->fetch_row()) { http_response_code(403); die("Access denied."); }
        $c->close();
    }

    $files = $conn->prepare("SELECT id, title, kind, original_name, size_bytes, uploaded_at FROM files WHERE course_id = ? ORDER BY uploaded_at DESC");
    $files->bind_param("i", $id);
    $files->execute();
    $fileRows = $files->get_result()->fetch_all(MYSQLI_ASSOC);
    $files->close();

    $hist = []; $people = [];
    if ($isAdmin) {
        $p = $conn->prepare("SELECT u.full_name, u.student_id, e.semester FROM enrollments e
            JOIN users u ON u.id = e.user_id WHERE e.course_id = ? ORDER BY e.semester DESC, u.full_name");
        $p->bind_param("i", $id);
        $p->execute();
        $people = $p->get_result()->fetch_all(MYSQLI_ASSOC);
        $p->close();
    } else {
        $h = $conn->prepare("SELECT e.semester, g.internal_marks, g.final_marks FROM enrollments e
            LEFT JOIN grades g ON g.enrollment_id = e.id WHERE e.user_id = ? AND e.course_id = ? ORDER BY e.semester DESC");
        $h->bind_param("ii", $uid, $id);
        $h->execute();
        $hist = $h->get_result()->fetch_all(MYSQLI_ASSOC);
        $h->close();
    }

    [$col, $ic] = courseCover($course["code"]);
    portalHeader($course["name"], $course["code"] . " · " . (int) $course["credits"] . " credits", "courses.php");
    ?>
    <div class="detail-hero" style="background:<?php echo $col[0]; ?>;color:<?php echo $col[1]; ?>">
        <i data-lucide="<?php echo $ic; ?>"></i>
        <div><b><?php echo e($course["name"]); ?></b><small><?php echo e($course["code"]); ?></small></div>
        <a class="hero-back" href="courses.php">← All courses</a>
    </div>

    <section class="page-grid">
        <div class="card">
            <?php if ($isAdmin): ?>
                <h3><i data-lucide="users"></i> Enrolled students <span class="count-pill"><?php echo count($people); ?></span></h3>
                <?php if (!$people): ?><div class="empty">No students enrolled yet.</div>
                <?php else: foreach ($people as $p): ?>
                    <div class="file-row"><span class="file-ico"><i data-lucide="user"></i></span>
                        <div class="file-info"><b><?php echo e($p["full_name"]); ?></b><small><?php echo e($p["student_id"]); ?></small></div>
                        <span class="tag"><?php echo e($p["semester"]); ?></span></div>
                <?php endforeach; endif; ?>
            <?php else: ?>
                <h3><i data-lucide="award"></i> My results</h3>
                <?php foreach ($hist as $r):
                    $has = $r["internal_marks"] !== null;
                    $t = $has ? $r["internal_marks"] + $r["final_marks"] : 0;
                    $L = gradeLetter($t); ?>
                    <div class="result-row">
                        <div><b><?php echo e($r["semester"]); ?></b>
                            <small><?php echo $has ? "Internal " . rtrim(rtrim($r["internal_marks"], "0"), ".") . " + Final " . rtrim(rtrim($r["final_marks"], "0"), ".") : "Not graded yet"; ?></small></div>
                        <?php if ($has): ?>
                            <span class="big-total"><?php echo rtrim(rtrim(number_format($t, 2, ".", ""), "0"), "."); ?></span>
                            <span class="grade <?php echo gradeClass($L); ?>"><?php echo $L; ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3><i data-lucide="folder"></i> Course files <span class="count-pill"><?php echo count($fileRows); ?></span></h3>
            <?php if (!$fileRows): ?>
                <div class="empty">No files for this course yet.</div>
            <?php else: foreach ($fileRows as $f): ?>
                <div class="file-row">
                    <span class="file-ico"><i data-lucide="<?php echo fileIcon($f["original_name"]); ?>"></i></span>
                    <div class="file-info"><b><?php echo e($f["title"]); ?></b>
                        <small><?php echo e(formatSize((int) $f["size_bytes"])); ?> · <?php echo e(date("M j, Y", strtotime($f["uploaded_at"]))); ?></small></div>
                    <span class="tag <?php echo kindClass($f["kind"]); ?>"><?php echo e(ucfirst($f["kind"])); ?></span>
                    <a class="btn-dl" href="download.php?id=<?php echo (int) $f["id"]; ?>"><i data-lucide="download"></i> Download</a>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </section>
    <?php
    portalFooter();
    exit;
}

/* ========== قائمة المواد ========== */
$cards = [];
if ($isAdmin) {
    $cards = $conn->query("SELECT c.id, c.code, c.name, c.credits,
        (SELECT COUNT(DISTINCT user_id) FROM enrollments WHERE course_id = c.id) AS students_n,
        (SELECT COUNT(*) FROM files WHERE course_id = c.id) AS files_n
        FROM courses c ORDER BY c.code")->fetch_all(MYSQLI_ASSOC);
} else {
    $s = $conn->prepare("SELECT c.id, c.code, c.name, c.credits, e.semester, g.internal_marks, g.final_marks,
        (SELECT COUNT(*) FROM files WHERE course_id = c.id) AS files_n
        FROM enrollments e JOIN courses c ON c.id = e.course_id
        LEFT JOIN grades g ON g.enrollment_id = e.id
        WHERE e.user_id = ? ORDER BY e.semester DESC, c.code");
    $s->bind_param("i", $uid);
    $s->execute();
    $seen = [];
    foreach ($s->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        if (isset($seen[$r["id"]])) continue;
        $seen[$r["id"]] = true;
        $cards[] = $r;
    }
    $s->close();
}

portalHeader("Courses", $isAdmin ? "All courses in the catalog." : "Your enrolled courses.", "courses.php");
?>
<input type="text" id="csearch" class="search-box" placeholder="Search courses..." style="max-width:360px">
<input type="text" id="csearch" class="search-box" placeholder="Search courses..." style="max-width:360px" value="<?php echo e($_GET["q"] ?? ""); ?>">

<?php if (!$cards): ?>
    <div class="card"><div class="empty">No courses yet.</div></div>
<?php else: ?>
<section class="course-grid" id="cgrid">
<?php foreach ($cards as $c): [$col, $ic] = courseCover($c["code"]);
    $has = !$isAdmin && $c["internal_marks"] !== null;
    $t = $has ? $c["internal_marks"] + $c["final_marks"] : 0;
    $L = $has ? gradeLetter($t) : ""; ?>
    <a class="card course-card" href="courses.php?id=<?php echo (int) $c["id"]; ?>">
        <div class="cc-cover" style="background:<?php echo $col[0]; ?>;color:<?php echo $col[1]; ?>"><i data-lucide="<?php echo $ic; ?>"></i></div>
        <div class="cc-body">
            <small><?php echo e($c["code"]); ?> · <?php echo (int) $c["credits"]; ?> credits</small>
            <b><?php echo e($c["name"]); ?></b>
            <?php if ($isAdmin): ?>
                <div class="cc-meta"><span><i data-lucide="users"></i> <?php echo (int) $c["students_n"]; ?> students</span>
                    <span><i data-lucide="folder"></i> <?php echo (int) $c["files_n"]; ?> files</span></div>
            <?php else: ?>
                <?php if ($has): ?>
                    <div class="track"><span data-w="<?php echo (int) round($t); ?>"></span></div>
                    <div class="cc-meta"><span><?php echo e($c["semester"]); ?></span>
                        <span class="grade <?php echo gradeClass($L); ?>"><?php echo $L; ?> · <?php echo (int) round($t); ?>%</span></div>
                <?php else: ?>
                    <div class="cc-meta"><span><?php echo e($c["semester"]); ?></span><span>Not graded yet</span></div>
                <?php endif; ?>
                <div class="cc-meta"><span><i data-lucide="folder"></i> <?php echo (int) $c["files_n"]; ?> files</span></div>
            <?php endif; ?>
        </div>
    </a>
<?php endforeach; ?>
</section>
<?php endif; ?>

<script>
document.getElementById("csearch").addEventListener("input", function () {
    var q = this.value.toLowerCase();
    document.querySelectorAll("#cgrid .course-card").forEach(function (c) {
        c.style.display = c.textContent.toLowerCase().indexOf(q) > -1 ? "" : "none";
    });
});
var qv = document.getElementById("csearch");
if (qv.value) { qv.dispatchEvent(new Event("input")); }
</script>
<?php portalFooter(); ?>