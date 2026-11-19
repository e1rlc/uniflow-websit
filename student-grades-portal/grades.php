<?php
require "layout.php";
$uid = (int) $_SESSION["user_id"];

// الفصول المتاحة لهذا الطالب فقط
$sems = [];
$q = $conn->prepare("SELECT DISTINCT semester FROM enrollments WHERE user_id = ? ORDER BY semester");
$q->bind_param("i", $uid);
$q->execute();
$r = $q->get_result();
while ($x = $r->fetch_assoc()) { $sems[] = $x["semester"]; }
$q->close();

$selected = $_GET["semester"] ?? ($sems[count($sems) - 1] ?? "");
if (!in_array($selected, $sems, true)) { $selected = $sems[count($sems) - 1] ?? ""; }

function loadRows($conn, $uid, $sem = null) {
    $sql = "SELECT c.code, c.name, c.credits, g.internal_marks, g.final_marks, e.semester
            FROM enrollments e
            JOIN courses c ON c.id = e.course_id
            JOIN grades g ON g.enrollment_id = e.id
            WHERE e.user_id = ?" . ($sem !== null ? " AND e.semester = ?" : "") . " ORDER BY c.code";
    $s = $conn->prepare($sql);
    if ($sem !== null) { $s->bind_param("is", $uid, $sem); } else { $s->bind_param("i", $uid); }
    $s->execute();
    $res = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();
    return $res;
}

function gpaOfRows($rows) {
    $pts = 0; $cr = 0;
    foreach ($rows as $row) {
        $t = $row["internal_marks"] + $row["final_marks"];
        $pts += gradePoints($t) * $row["credits"];
        $cr  += $row["credits"];
    }
    return $cr > 0 ? $pts / $cr : 0;
}

$rows = $selected !== "" ? loadRows($conn, $uid, $selected) : [];
$all  = loadRows($conn, $uid);
$sgpa = gpaOfRows($rows);
$cgpa = gpaOfRows($all);

portalHeader("My Grades", "Your results by semester.", "grades.php");
?>

<?php if (empty($sems)): ?>
    <div class="card" style="opacity:1">
        <h3><i data-lucide="info"></i> No grades yet</h3>
        <div class="empty">
            <?php echo $isAdmin
                ? "Admin accounts have no grades. Use Manage Grades to enter student results."
                : "You are not enrolled in any course yet."; ?>
        </div>
    </div>
<?php else: ?>

<form method="get" class="sem-bar">
    <label for="semester">Semester</label>
    <select name="semester" id="semester" onchange="this.form.submit()">
        <?php foreach ($sems as $s): ?>
            <option value="<?php echo e($s); ?>" <?php echo $s === $selected ? "selected" : ""; ?>><?php echo e($s); ?></option>
        <?php endforeach; ?>
    </select>
</form>

<section class="gpa-row">
    <div class="card gpa-card">
        <div>
            <h3><i data-lucide="award"></i> SGPA</h3>
            <div class="stat-num" data-count="<?php echo number_format($sgpa, 2, ".", ""); ?>">0</div>
            <div class="stat-note">Current semester</div>
        </div>
        <div class="ring" style="--p: <?php echo round($sgpa / 4 * 100); ?>"><span><?php echo round($sgpa / 4 * 100); ?>%</span></div>
    </div>
    <div class="card gpa-card">
        <div>
            <h3><i data-lucide="trending-up"></i> CGPA</h3>
            <div class="stat-num" data-count="<?php echo number_format($cgpa, 2, ".", ""); ?>">0</div>
            <div class="stat-note">All semesters</div>
        </div>
        <div class="ring" style="--p: <?php echo round($cgpa / 4 * 100); ?>"><span><?php echo round($cgpa / 4 * 100); ?>%</span></div>
    </div>
</section>

<section class="card">
    <h3><i data-lucide="table"></i> Subject-wise marks (<?php echo e($selected); ?>)</h3>
    <div class="table-scroll">
        <table class="grades-table">
            <thead>
                <tr><th>Code</th><th>Subject</th><th>Credits</th><th>Internal /40</th><th>Final /60</th><th>Total</th><th>Grade</th></tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row):
                $total = $row["internal_marks"] + $row["final_marks"];
                $letter = gradeLetter($total); ?>
                <tr>
                    <td><?php echo e($row["code"]); ?></td>
                    <td><?php echo e($row["name"]); ?></td>
                    <td><?php echo (int) $row["credits"]; ?></td>
                    <td><?php echo rtrim(rtrim($row["internal_marks"], "0"), "."); ?></td>
                    <td><?php echo rtrim(rtrim($row["final_marks"], "0"), "."); ?></td>
                    <td><?php echo rtrim(rtrim(number_format($total, 2, ".", ""), "0"), "."); ?></td>
                    <td><span class="grade <?php echo gradeClass($letter); ?>"><?php echo $letter; ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php endif; ?>
<?php portalFooter(); ?>