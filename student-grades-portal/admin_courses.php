<?php
require "admin_common.php";
$msg = ""; $err = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["delete_id"])) {
        $id = (int) $_POST["delete_id"];
        $s = $conn->prepare("DELETE FROM courses WHERE id = ?");
        $s->bind_param("i", $id);
        $s->execute();
        $msg = "Course deleted.";
        $s->close();
    } else {
        $code = trim($_POST["code"]);
        $cname = trim($_POST["name"]);
        $credits = (int) $_POST["credits"];
        if ($code === "" || $cname === "" || $credits < 1 || $credits > 10) {
            $err = "Fill all fields (credits 1-10).";
        } else {
            $s = $conn->prepare("INSERT INTO courses (code, name, credits) VALUES (?, ?, ?)");
            $s->bind_param("ssi", $code, $cname, $credits);
            if ($s->execute()) { $msg = "Course added."; } else { $err = "This course code already exists."; }
            $s->close();
        }
    }
}
$courses = $conn->query("SELECT id, code, name, credits FROM courses ORDER BY code");
adminHeader("Manage Courses", "admin_courses.php");
?>
<section class="card-box admin-card" style="opacity:1;transform:none">
    <?php if ($msg): ?><p class="result"><?php echo e($msg); ?></p><?php endif; ?>
    <?php if ($err): ?><p class="error"><?php echo e($err); ?></p><?php endif; ?>
    <h3><i data-lucide="plus"></i> Add course</h3>
    <form method="post" class="inline-form">
        <input type="text" name="code" placeholder="Code (e.g. CS301)" required>
        <input type="text" name="name" placeholder="Course name" required>
        <input type="number" name="credits" placeholder="Credits" min="1" max="10" required>
        <button type="submit">Add</button>
    </form>
</section>

<section class="card-box admin-card" style="opacity:1;transform:none">
    <h3><i data-lucide="table"></i> Courses</h3>
    <div class="table-scroll"><table class="grades-table">
        <thead><tr><th>Code</th><th>Name</th><th>Credits</th><th></th></tr></thead>
        <tbody>
        <?php while ($c = $courses->fetch_assoc()): ?>
            <tr>
                <td><?php echo e($c["code"]); ?></td>
                <td><?php echo e($c["name"]); ?></td>
                <td><?php echo (int) $c["credits"]; ?></td>
                <td>
                    <form method="post" onsubmit="return confirm('Delete this course and all its grades?');">
                        <input type="hidden" name="delete_id" value="<?php echo (int) $c["id"]; ?>">
                        <button type="submit" class="danger">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table></div>
</section>
<?php adminFooter(); ?>