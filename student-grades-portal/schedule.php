<?php
require "layout.php";
$msg = ""; $err = "";
$types = ["lecture", "exam", "quiz", "studio", "event"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    requireAdmin();
    if (isset($_POST["delete_id"])) {
        $id = (int) $_POST["delete_id"];
        $s = $conn->prepare("DELETE FROM events WHERE id = ?");
        $s->bind_param("i", $id);
        $s->execute();
        $s->close();
        $msg = "Event deleted.";
    } else {
        $title = trim($_POST["title"]);
        $type  = $_POST["type"];
        $loc   = trim($_POST["location"]);
        $when  = strtotime($_POST["starts_at"]);
        if ($title === "" || !in_array($type, $types, true) || !$when) {
            $err = "Fill the title and choose a valid date and time.";
        } else {
            $dt = date("Y-m-d H:i:s", $when);
            $s = $conn->prepare("INSERT INTO events (title, type, starts_at, location) VALUES (?, ?, ?, ?)");
            $s->bind_param("ssss", $title, $type, $dt, $loc);
            $s->execute();
            $s->close();
            $msg = "Event added.";
        }
    }
}

$upcoming = $conn->query("SELECT id, title, type, starts_at, location FROM events WHERE starts_at >= NOW() ORDER BY starts_at")->fetch_all(MYSQLI_ASSOC);
$past     = $conn->query("SELECT id, title, type, starts_at, location FROM events WHERE starts_at < NOW() ORDER BY starts_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

function evRow($ev, $isAdmin, $cls = "") {
    $ts = strtotime($ev["starts_at"]);
    echo '<div class="ev-full ' . $cls . '">
        <div class="ev-date"><small>' . date("M", $ts) . '</small><b>' . date("j", $ts) . '</b></div>
        <div class="ev-info"><b>' . e($ev["title"]) . '</b><small>' . date("g:i A", $ts) .
        ($ev["location"] ? " · " . e($ev["location"]) : "") . '</small></div>
        <span class="tag t-' . e($ev["type"]) . '">' . e(ucfirst($ev["type"])) . '</span>';
    if ($isAdmin) {
        echo '<form method="post" onsubmit="return confirm(\'Delete this event?\');">
            <input type="hidden" name="delete_id" value="' . (int) $ev["id"] . '">
            <button type="submit" class="btn-del">Delete</button></form>';
    }
    echo '</div>';
}

portalHeader("Schedule", "Lectures, exams, and campus events.", "schedule.php");
?>
<?php if ($msg): ?><p class="msg-ok"><?php echo e($msg); ?></p><?php endif; ?>
<?php if ($err): ?><p class="msg-err"><?php echo e($err); ?></p><?php endif; ?>

<section class="page-grid <?php echo $isAdmin ? "" : "single"; ?>">

<?php if ($isAdmin): ?>
    <div class="card">
        <h3><i data-lucide="calendar-plus"></i> Add event</h3>
        <form method="post" class="form-stack">
            <label>Title <input type="text" name="title" maxlength="150" placeholder="Algorithms - Final Exam" required></label>
            <label>Type
                <select name="type">
                    <option value="lecture">Lecture</option>
                    <option value="exam">Exam</option>
                    <option value="quiz">Quiz</option>
                    <option value="studio">Studio</option>
                    <option value="event">Event</option>
                </select>
            </label>
            <label>Date and time <input type="datetime-local" name="starts_at" required></label>
            <label>Location <input type="text" name="location" maxlength="100" placeholder="Hall A2"></label>
            <button type="submit" class="btn-main"><i data-lucide="plus"></i> Add event</button>
        </form>
    </div>
<?php endif; ?>

    <div class="card">
        <h3><i data-lucide="calendar"></i> Upcoming</h3>
        <?php if (!$upcoming): ?>
            <div class="empty">No upcoming events yet.</div>
        <?php else:
            $lastDay = "";
            foreach ($upcoming as $ev) {
                $day = date("l, F j", strtotime($ev["starts_at"]));
                if ($day !== $lastDay) { echo '<div class="day-title">' . e($day) . '</div>'; $lastDay = $day; }
                evRow($ev, $isAdmin);
            }
        endif; ?>

        <?php if ($past): ?>
            <h3 style="margin-top:26px"><i data-lucide="history"></i> Recently passed</h3>
            <?php foreach ($past as $ev) { evRow($ev, $isAdmin, "past"); } ?>
        <?php endif; ?>
    </div>
</section>
<?php portalFooter(); ?>