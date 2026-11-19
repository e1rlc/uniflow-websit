<?php
require "admin_common.php";
$msg = ""; $err = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $semester = trim($_POST["semester"]);
    $mode = $_POST["mode"] ?? "";
    if ($semester === "") {
        $err = "Enter a semester name.";
    } elseif ($mode === "one") {
        $uid = (int) $_POST["user_id"];
        $cids = $_POST["courses"] ?? [];
        $added = 0;
        $s = $conn->prepare("INSERT IGNORE INTO enrollments (user_id, course_id, semester) VALUES (?, ?, ?)");
        foreach ($cids as $cid) {
            $cid = (int) $cid;
            $s->bind_param("iis", $uid, $cid, $semester);
            $s->execute();
            $added += $s->affected_rows > 0 ? 1 : 0;
        }
        $s->close();
        $msg = "$added enrollment(s) added.";
    } elseif ($mode === "all") {
        $conn->query("INSERT IGNORE INTO enrollments (user_id, course_id, semester)
            SELECT u.id, c.id, '" . $conn->real_escape_string($semester) . "'
            FROM users u CROSS JOIN courses c WHERE u.role = 'student'");
        $msg = "All students enrolled in all courses for " . $semester . " (existing ones skipped).";
    }
}
$students = $conn->query("SELECT id, full_name, student_id FROM users WHERE role='student' ORDER BY full_name");
$courses  = $conn->query("SELECT id, code, name FROM courses ORDER BY code");
$courseList = $courses->fetch_all(MYSQLI_ASSOC);
adminHeader("Enrollments", "admin_enrollments.php");
?>
<section class="card-box admin-card" style="opacity:1;transform:none">
    <?php if ($msg): ?><p class="result"><?php echo e($msg); ?></p><?php endif; ?>
    <?php if ($err): ?><p class="error"><?php echo e($err); ?></p><?php endif; ?>

    <h3><i data-lucide="user-plus"></i> Enroll one student</h3>
    <form method="post">
        <input type="hidden" name="mode" value="one">
        <div class="inline-form">
            <select name="user_id" required>
                <option value="">Select student</option>
                <?php while ($st = $students->fetch_assoc()): ?>
                    <option value="<?php echo (int) $st["id"]; ?>"><?php echo e($st["full_name"] . " (" . $st["student_id"] . ")"); ?></option>
                <?php endwhile; ?>
            </select>
            <input type="text" name="semester" placeholder="Semester (e.g. Semester 1)" required>
        </div>
        <div class="check-grid">
            <?php foreach ($courseList as $c): ?>
                <label><input type="checkbox" name="courses[]" value="<?php echo (int) $c["id"]; ?>"> <?php echo e($c["code"] . " - " . $c["name"]); ?></label>
            <?php endforeach; ?>
        </div>
        <button type="submit">Enroll</button>
    </form>
</section>

<section class="card-box admin-card" style="opacity:1;transform:none">
    <h3><i data-lucide="users"></i> Enroll all students in all courses</h3>
    <form method="post" class="inline-form" onsubmit="return confirm('Enroll ALL students in ALL courses for this semester?');">
        <input type="hidden" name="mode" value="all">
        <input type="text" name="semester" placeholder="Semester (e.g. Semester 2)" required>
        <button type="submit">Enroll all</button>
    </form>
</section>
<?php adminFooter(); ?>