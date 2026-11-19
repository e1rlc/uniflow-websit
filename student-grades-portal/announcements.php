<?php
require "layout.php";
$msg = ""; $err = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    requireAdmin();
    if (isset($_POST["delete_id"])) {
        $id = (int) $_POST["delete_id"];
        $s = $conn->prepare("DELETE FROM announcements WHERE id = ?");
        $s->bind_param("i", $id);
        $s->execute();
        $s->close();
        $msg = "Announcement deleted.";
    } else {
        $title = trim($_POST["title"]);
        $body  = trim($_POST["body"]);
        $kind  = $_POST["kind"];
        if ($title === "" || $body === "" || !in_array($kind, ["info", "success", "alert"], true)) {
            $err = "Fill the title and message.";
        } else {
            $s = $conn->prepare("INSERT INTO announcements (title, body, kind) VALUES (?, ?, ?)");
            $s->bind_param("sss", $title, $body, $kind);
            $s->execute();
            $s->close();
            $msg = "Announcement posted.";
        }
    }
}

$rows = $conn->query("SELECT id, title, body, kind, created_at FROM announcements ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);

portalHeader("Announcements", "News and updates from your university.", "announcements.php");
?>
<?php if ($msg): ?><p class="msg-ok"><?php echo e($msg); ?></p><?php endif; ?>
<?php if ($err): ?><p class="msg-err"><?php echo e($err); ?></p><?php endif; ?>

<section class="page-grid <?php echo $isAdmin ? "" : "single"; ?>">

<?php if ($isAdmin): ?>
    <div class="card">
        <h3><i data-lucide="plus-circle"></i> Post announcement</h3>
        <form method="post" class="form-stack">
            <label>Title <input type="text" name="title" maxlength="150" placeholder="Final exam schedule released" required></label>
            <label>Message <textarea name="body" maxlength="255" placeholder="Check the updated schedule on the portal." required></textarea></label>
            <label>Type
                <select name="kind">
                    <option value="info">Info</option>
                    <option value="success">Good news</option>
                    <option value="alert">Important</option>
                </select>
            </label>
            <button type="submit" class="btn-main"><i data-lucide="send"></i> Post</button>
        </form>
    </div>
<?php endif; ?>

    <div class="card">
        <h3><i data-lucide="megaphone"></i> All announcements</h3>
        <div class="chips" id="chips">
            <button class="chip on" data-k="all">All</button>
            <button class="chip" data-k="alert">Important</button>
            <button class="chip" data-k="success">Good news</button>
            <button class="chip" data-k="info">Info</button>
        </div>
        <?php if (!$rows): ?>
            <div class="empty">No announcements yet.</div>
        <?php else: foreach ($rows as $a):
            $ic = $a["kind"] === "alert" ? "alert-circle" : ($a["kind"] === "success" ? "check-circle" : "info"); ?>
            <div class="an-full" data-kind="<?php echo e($a["kind"]); ?>">
                <span class="an-ico a-<?php echo e($a["kind"]); ?>"><i data-lucide="<?php echo $ic; ?>"></i></span>
                <div class="body"><b><?php echo e($a["title"]); ?></b><p><?php echo e($a["body"]); ?></p></div>
                <div class="meta">
                    <span><?php echo e(date("M j, Y · g:i A", strtotime($a["created_at"]))); ?></span>
                    <?php if ($isAdmin): ?>
                        <form method="post" onsubmit="return confirm('Delete this announcement?');">
                            <input type="hidden" name="delete_id" value="<?php echo (int) $a["id"]; ?>">
                            <button type="submit" class="btn-del">Delete</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>
</section>

<script>
document.querySelectorAll("#chips .chip").forEach(function (c) {
    c.addEventListener("click", function () {
        document.querySelectorAll("#chips .chip").forEach(function (x) { x.classList.remove("on"); });
        c.classList.add("on");
        var k = c.dataset.k;
        document.querySelectorAll(".an-full").forEach(function (r) {
            r.style.display = (k === "all" || r.dataset.kind === k) ? "" : "none";
        });
    });
});
</script>
<?php portalFooter(); ?>