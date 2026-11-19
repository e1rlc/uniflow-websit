<?php
require "layout.php";
require "storage.php";
$msg = ""; $err = "";
$uid = (int) $_SESSION["user_id"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    requireAdmin();

    if (isset($_POST["delete_id"])) {
        $id = (int) $_POST["delete_id"];
        $s = $conn->prepare("SELECT stored_name FROM files WHERE id = ?");
        $s->bind_param("i", $id);
        $s->execute();
        $row = $s->get_result()->fetch_assoc();
        $s->close();
        if ($row) {
            $p = STORAGE_DIR . DIRECTORY_SEPARATOR . $row["stored_name"];
            if (is_file($p)) { unlink($p); }
            $d = $conn->prepare("DELETE FROM files WHERE id = ?");
            $d->bind_param("i", $id);
            $d->execute();
            $d->close();
            $msg = "File deleted.";
        }
    } else {
        $title    = trim($_POST["title"]);
        $courseId = (int) $_POST["course_id"];
        $courseId = $courseId > 0 ? $courseId : null;
        $kind     = $_POST["kind"];
        $up       = $_FILES["file"] ?? null;

        if ($title === "" || !in_array($kind, ["lecture", "hints", "assignment", "other"], true)) {
            $err = "Enter a title and choose a type.";
        } elseif (!$up || $up["error"] !== UPLOAD_ERR_OK) {
            $err = "Choose a file (upload failed or file too large for the server).";
        } elseif ($up["size"] > MAX_UPLOAD) {
            $err = "File is too large (max 20 MB).";
        } else {
            $ext = strtolower(pathinfo($up["name"], PATHINFO_EXTENSION));
            if (!in_array($ext, $ALLOWED_EXT, true)) {
                $err = "File type not allowed. Allowed: " . implode(", ", $ALLOWED_EXT) . ".";
            } else {
                if (!is_dir(STORAGE_DIR)) { mkdir(STORAGE_DIR, 0777, true); }
                $stored = bin2hex(random_bytes(16));
                if (move_uploaded_file($up["tmp_name"], STORAGE_DIR . DIRECTORY_SEPARATOR . $stored)) {
                    $orig = substr($up["name"], 0, 255);
                    $size = (int) $up["size"];
                    $s = $conn->prepare("INSERT INTO files (course_id, title, kind, stored_name, original_name, size_bytes) VALUES (?, ?, ?, ?, ?, ?)");
                    $s->bind_param("issssi", $courseId, $title, $kind, $stored, $orig, $size);
                    $s->execute();
                    $s->close();
                    $msg = "File uploaded.";
                } else {
                    $err = "Could not save the file on the server.";
                }
            }
        }
    }
}

$courses = $conn->query("SELECT id, code, name FROM courses ORDER BY code")->fetch_all(MYSQLI_ASSOC);

if ($isAdmin) {
    $files = $conn->query("SELECT f.*, c.code AS ccode, c.name AS cname FROM files f
        LEFT JOIN courses c ON c.id = f.course_id ORDER BY f.uploaded_at DESC")->fetch_all(MYSQLI_ASSOC);
} else {
    $s = $conn->prepare("SELECT f.*, c.code AS ccode, c.name AS cname FROM files f
        LEFT JOIN courses c ON c.id = f.course_id
        WHERE f.course_id IS NULL OR f.course_id IN (SELECT course_id FROM enrollments WHERE user_id = ?)
        ORDER BY f.uploaded_at DESC");
    $s->bind_param("i", $uid);
    $s->execute();
    $files = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();
}

$cf = (int) ($_GET["course"] ?? 0);
if ($cf > 0) {
    $files = array_values(array_filter($files, function ($f) use ($cf) { return (int) $f["course_id"] === $cf; }));
}
$cf = (int) ($_GET["course"] ?? 0);
if ($cf > 0) {
    $files = array_values(array_filter($files, function ($f) use ($cf) { return (int) $f["course_id"] === $cf; }));
}

portalHeader("Files", "Lectures, hints, and assignments from your instructors.", "files.php");
?>
<?php if ($msg): ?><p class="msg-ok"><?php echo e($msg); ?></p><?php endif; ?>
<?php if ($err): ?><p class="msg-err"><?php echo e($err); ?></p><?php endif; ?>

<section class="page-grid <?php echo $isAdmin ? "" : "single"; ?>">

<?php if ($isAdmin): ?>
    <div class="card">
        <h3><i data-lucide="upload-cloud"></i> Upload file</h3>
        <form method="post" enctype="multipart/form-data" class="form-stack">
            <label>Title <input type="text" name="title" maxlength="150" placeholder="Algorithms - Lecture 5" required></label>
            <label>Course
                <select name="course_id">
                    <option value="0">General (all students)</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?php echo (int) $c["id"]; ?>"><?php echo e($c["code"] . " - " . $c["name"]); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Type
                <select name="kind">
                    <option value="lecture">Lecture</option>
                    <option value="hints">Exam hints</option>
                    <option value="assignment">Assignment</option>
                    <option value="other">Other</option>
                </select>
            </label>
            <label>File (max 20 MB) <input type="file" name="file" required></label>
            <button type="submit" class="btn-main"><i data-lucide="upload"></i> Upload</button>
        </form>
    </div>
<?php endif; ?>

    <div class="card">
        <h3><i data-lucide="folder"></i> Library
            <span class="count-pill"><?php echo count($files); ?> files</span></h3>

        <input type="text" id="fsearch" class="search-box" placeholder="Search files or courses...">
        <div class="chips" id="fchips">
            <button class="chip on" data-k="all">All</button>
            <button class="chip" data-k="lecture">Lectures</button>
            <button class="chip" data-k="hints">Exam hints</button>
            <button class="chip" data-k="assignment">Assignments</button>
            <button class="chip" data-k="other">Other</button>
        </div>

        <?php if (!$files): ?>
            <div class="empty">No files yet.</div>
        <?php else: foreach ($files as $f): ?>
            <div class="file-row" data-kind="<?php echo e($f["kind"]); ?>">
                <span class="file-ico"><i data-lucide="<?php echo fileIcon($f["original_name"]); ?>"></i></span>
                <div class="file-info">
                    <b><?php echo e($f["title"]); ?></b>
                    <small>
                        <?php echo $f["ccode"] ? e($f["ccode"] . " · " . $f["cname"]) : "General"; ?>
                        · <?php echo e(formatSize((int) $f["size_bytes"])); ?>
                        · <?php echo e(date("M j, Y", strtotime($f["uploaded_at"]))); ?>
                    </small>
                </div>
                <span class="tag <?php echo kindClass($f["kind"]); ?>"><?php echo e(ucfirst($f["kind"])); ?></span>
                <a class="btn-dl" href="download.php?id=<?php echo (int) $f["id"]; ?>"><i data-lucide="download"></i> Download</a>
                <?php if ($isAdmin): ?>
                    <form method="post" onsubmit="return confirm('Delete this file permanently?');">
                        <input type="hidden" name="delete_id" value="<?php echo (int) $f["id"]; ?>">
                        <button type="submit" class="btn-del">Delete</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; endif; ?>
    </div>
</section>

<script>
(function () {
    var kind = "all", q = "";
    function apply() {
        document.querySelectorAll(".file-row").forEach(function (r) {
            var okK = kind === "all" || r.dataset.kind === kind;
            var okQ = r.textContent.toLowerCase().indexOf(q) > -1;
            r.style.display = (okK && okQ) ? "" : "none";
        });
    }
    document.querySelectorAll("#fchips .chip").forEach(function (c) {
        c.addEventListener("click", function () {
            document.querySelectorAll("#fchips .chip").forEach(function (x) { x.classList.remove("on"); });
            c.classList.add("on");
            kind = c.dataset.k;
            apply();
        });
    });
    document.getElementById("fsearch").addEventListener("input", function () {
        q = this.value.toLowerCase();
        apply();
    });
})();
</script>
<?php portalFooter(); ?>