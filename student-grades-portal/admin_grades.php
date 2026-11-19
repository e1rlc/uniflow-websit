<?php
require "admin_common.php";
$msg = ""; $err = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $eid = (int) $_POST["enrollment_id"];
    $int = (float) $_POST["internal_marks"];
    $fin = (float) $_POST["final_marks"];
    if ($int < 0 || $int > 40 || $fin < 0 || $fin > 60) {
        $err = "Internal must be 0-40 and final 0-60.";
    } else {
        $s = $conn->prepare("INSERT INTO grades (enrollment_id, internal_marks, final_marks) VALUES (?, ?, ?)
                             ON DUPLICATE KEY UPDATE internal_marks = VALUES(internal_marks), final_marks = VALUES(final_marks)");
        $s->bind_param("idd", $eid, $int, $fin);
        $s->execute();
        $s->close();
        $msg = "Grade saved.";
    }
}

$sel = (int) ($_GET["student"] ?? 0);
$students = $conn->query("SELECT id, full_name, student_id FROM users WHERE role='student' ORDER BY full_name");
$rows = [];
if ($sel > 0) {
    $s = $conn->prepare("SELECT e.id AS eid, e.semester, c.code, c.name, g.internal_marks, g.final_marks
        FROM enrollments e JOIN courses c ON c.id = e.course_id
        LEFT JOIN grades g ON g.enrollment_id = e.id
        WHERE e.user_id = ? ORDER BY e.semester, c.code");
    $s->bind_param("i", $sel);
    $s->execute();
    $rows = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();
}
adminHeader("Manage Grades", "admin_grades.php");
?>
<section class="card-box admin-card" style="opacity:1;transform:none">
    <?php if ($msg): ?><p class="result"><?php echo e($msg); ?></p><?php endif; ?>
    <?php if ($err): ?><p class="error"><?php echo e($err); ?></p><?php endif; ?>
    <h3><i data-lucide="user"></i> Select student</h3>
    <form method="get" class="inline-form">
        <select name="student" onchange="this.form.submit()">
            <option value="0">Select student</option>
            <?php while ($st = $students->fetch_assoc()): ?>
                <option value="<?php echo (int) $st["id"]; ?>" <?php echo $sel === (int) $st["id"] ? "selected" : ""; ?>>
                    <?php echo e($st["full_name"] . " (" . $st["student_id"] . ")"); ?>
                </option>
            <?php endwhile; ?>
        </select>
    </form>
</section>

<?php if ($sel > 0): ?>
<section class="card-box admin-card" style="opacity:1;transform:none">
    <h3><i data-lucide="table"></i> Grades</h3>
    <?php if (empty($rows)): ?>
        <p class="stat-sub">This student has no enrollments. Use the Enrollments page first.</p>
    <?php else: ?>
    <div class="table-scroll"><table class="grades-table">
        <thead><tr><th>Semester</th><th>Course</th><th>Internal /40</th><th>Final /60</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <form method="post" action="admin_grades.php?student=<?php echo $sel; ?>">
                    <input type="hidden" name="enrollment_id" value="<?php echo (int) $r["eid"]; ?>">
                    <td><?php echo e($r["semester"]); ?></td>
                    <td><?php echo e($r["code"] . " - " . $r["name"]); ?></td>
                    <td><input type="number" step="0.01" min="0" max="40" name="internal_marks" value="<?php echo e($r["internal_marks"] ?? 0); ?>" class="mark"></td>
                    <td><input type="number" step="0.01" min="0" max="60" name="final_marks" value="<?php echo e($r["final_marks"] ?? 0); ?>" class="mark"></td>
                    <td><button type="submit">Save</button></td>
                </form>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</section>
<?php endif; ?>
<?php adminFooter(); ?>