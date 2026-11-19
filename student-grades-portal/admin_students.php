<?php
require "admin_common.php";
$msg = ""; $err = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // حذف طالب
    if (isset($_POST["delete_id"])) {
        $id = (int) $_POST["delete_id"];
        if ($id === (int) $_SESSION["user_id"]) {
            $err = "You cannot delete your own account.";
        } else {
            $s = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'student'");
            $s->bind_param("i", $id);
            $s->execute();
            $msg = $s->affected_rows > 0 ? "Student deleted." : "Nothing deleted.";
            $s->close();
        }

    // إعادة تعيين كلمة المرور
    } elseif (isset($_POST["reset_id"])) {
        $id = (int) $_POST["reset_id"];
        $pw = $_POST["new_password"];
        if (strlen($pw) < 6) {
            $err = "Password must be at least 6 characters.";
        } else {
            $hash = password_hash($pw, PASSWORD_DEFAULT);
            $s = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ? AND role = 'student'");
            $s->bind_param("si", $hash, $id);
            $s->execute();
            $msg = "Password updated.";
            $s->close();
        }

    // إضافة طالب
    } else {
        $full_name  = trim($_POST["full_name"]);
        $student_id = trim($_POST["student_id"]);
        $password   = $_POST["password"];

        if ($full_name === "" || $student_id === "" || strlen($password) < 6) {
            $err = "Fill all fields. Password must be at least 6 characters.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $s = $conn->prepare("INSERT INTO users (full_name, student_id, password_hash, role) VALUES (?, ?, ?, 'student')");
            $s->bind_param("sss", $full_name, $student_id, $hash);
            if ($s->execute()) { $msg = "Student added successfully."; }
            else { $err = "This student ID already exists."; }
            $s->close();
        }
    }
}

$users = $conn->query("SELECT id, full_name, student_id, role, last_login FROM users ORDER BY id DESC");
$total = $conn->query("SELECT COUNT(*) AS n FROM users WHERE role='student'")->fetch_assoc()["n"];

adminHeader("Manage Students", "admin_students.php");
?>
<?php if ($msg): ?><p class="result"><?php echo e($msg); ?></p><?php endif; ?>
<?php if ($err): ?><p class="error"><?php echo e($err); ?></p><?php endif; ?>

<section class="stu-layout">

    <div class="card-box admin-card" style="opacity:1;transform:none">
        <h3><i data-lucide="user-plus"></i> Add new student</h3>
        <form method="post" class="stack-form">
            <label>Full name
                <input type="text" name="full_name" placeholder="e.g. Ashraf Ahmed" required>
            </label>
            <label>Student ID (login name)
                <input type="text" name="student_id" placeholder="e.g. s1002" required>
            </label>
            <label>Temporary password
                <div class="pw-wrap">
                    <input type="password" name="password" id="pw" placeholder="Min 6 characters" required>
                    <button type="button" class="eye" onclick="togglePw()" aria-label="Show password">
                        <i data-lucide="eye"></i>
                    </button>
                </div>
            </label>
            <button type="submit" class="wide-btn"><i data-lucide="plus"></i> Add student</button>
        </form>
    </div>

    <div class="card-box admin-card" style="opacity:1;transform:none">
        <h3><i data-lucide="users"></i> Registered users
            <span class="count-pill"><?php echo (int) $total; ?> students</span>
        </h3>
        <input type="text" id="search" class="search-box" placeholder="Search by name or ID...">
        <div class="table-scroll">
            <table class="grades-table" id="usersTable">
                <thead><tr><th>Name</th><th>ID</th><th>Role</th><th>Last login</th><th></th></tr></thead>
                <tbody>
                <?php while ($u = $users->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo e($u["full_name"]); ?></td>
                        <td><?php echo e($u["student_id"]); ?></td>
                        <td><span class="grade <?php echo $u["role"] === "admin" ? "mid" : "good"; ?>"><?php echo e($u["role"]); ?></span></td>
                        <td><?php echo $u["last_login"] ? e($u["last_login"]) : "Never"; ?></td>
                        <td class="actions">
                            <?php if ($u["role"] === "student"): ?>
                            <form method="post" class="mini-form" onsubmit="return askReset(this);">
                                <input type="hidden" name="reset_id" value="<?php echo (int) $u["id"]; ?>">
                                <input type="hidden" name="new_password" value="">
                                <a class="soft-link" href="profile.php?id=<?php echo (int) $u["id"]; ?>">Profile</a>
                                <button type="submit" class="soft">Reset password</button>
                            </form>
                            <form method="post" class="mini-form" onsubmit="return confirm('Delete this student and all their grades?');">
                                <input type="hidden" name="delete_id" value="<?php echo (int) $u["id"]; ?>">
                                <button type="submit" class="danger">Delete</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

</section>

<script>
function togglePw() {
    var f = document.getElementById("pw");
    f.type = f.type === "password" ? "text" : "password";
}
function askReset(form) {
    var p = prompt("Enter the new password (min 6 characters):");
    if (!p) return false;
    form.new_password.value = p;
    return true;
}
document.getElementById("search").addEventListener("input", function () {
    var q = this.value.toLowerCase();
    document.querySelectorAll("#usersTable tbody tr").forEach(function (tr) {
        tr.style.display = tr.textContent.toLowerCase().indexOf(q) > -1 ? "" : "none";
    });
});
</script>
<?php adminFooter(); ?>