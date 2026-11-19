<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: index.html");
    exit;
}

$PLAN_CREDITS = 120;

$uid      = (int) $_SESSION["user_id"];
$isAdmin  = ($_SESSION["role"] === "admin");
$fullName = $_SESSION["username"];
$initial  = strtoupper(substr($fullName, 0, 1));

function e($s) { return htmlspecialchars((string) $s); }

function gradeInfo($total) {
    if ($total >= 90) return 4.0;
    if ($total >= 85) return 3.7;
    if ($total >= 80) return 3.3;
    if ($total >= 75) return 3.0;
    if ($total >= 70) return 2.7;
    if ($total >= 65) return 2.3;
    if ($total >= 60) return 2.0;
    if ($total >= 50) return 1.0;
    return 0.0;
}
function gpaOf($rows) {
    $pts = 0; $cr = 0;
    foreach ($rows as $r) { $pts += gradeInfo($r["total"]) * $r["credits"]; $cr += $r["credits"]; }
    return $cr > 0 ? $pts / $cr : 0;
}
function cover($code) {
    $pal = [["#0b4a35", "#e3f1e8"], ["#1d6b4f", "#e3f1e8"], ["#14584a", "#dff3ee"],
            ["#2f7d5c", "#eaf6ef"], ["#cfe8d9", "#0b4a35"], ["#a9d6bd", "#083828"], ["#e3f1e8", "#0b4a35"]];
    $icons = ["calculator", "git-branch", "cpu", "sigma", "languages", "code", "palette", "atom"];
    $h = crc32($code);
    return [$pal[$h % count($pal)], $icons[$h % count($icons)]];
}
function agoMin($m) {
    $m = (int) $m;
    if ($m < 60) return max(1, $m) . " min ago";
    if ($m < 1440) return floor($m / 60) . " h ago";
    return floor($m / 1440) . " d ago";
}

require_once "config.php";
$conn = db();

$stmt = $conn->prepare("SELECT last_login FROM users WHERE id = ?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$lr = $stmt->get_result()->fetch_assoc();
$lastLogin = ($lr && $lr["last_login"]) ? $lr["last_login"] : "First login";
$stmt->close();

$events = []; $ann = []; $examCount = 0; $nextExam = "";
try {
    $events = $conn->query("SELECT title, type, starts_at, location FROM events
        WHERE starts_at >= NOW() ORDER BY starts_at LIMIT 4")->fetch_all(MYSQLI_ASSOC);
    $ex = $conn->query("SELECT COUNT(*) n, MIN(starts_at) nxt FROM events
        WHERE type IN ('exam','quiz') AND starts_at >= NOW()")->fetch_assoc();
    $examCount = (int) $ex["n"];
    $nextExam = $ex["nxt"] ? date("M j, Y", strtotime($ex["nxt"])) : "";
    $ann = $conn->query("SELECT title, body, kind, created_at,
        TIMESTAMPDIFF(MINUTE, created_at, NOW()) AS mins
        FROM announcements ORDER BY created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
} catch (Throwable $t) { }
$latestAnnTs = $ann ? strtotime($ann[0]["created_at"]) : 0;

$bySem = []; $latestRows = []; $latestSem = "";
$cgpa = 0; $creditsEarned = 0; $delta = null; $semAvg = [];
$stats = ["students" => 0, "courses" => 0, "enrollments" => 0];
$adminCourses = [];

if ($isAdmin) {
    $stats["students"]    = $conn->query("SELECT COUNT(*) n FROM users WHERE role='student'")->fetch_assoc()["n"];
    $stats["courses"]     = $conn->query("SELECT COUNT(*) n FROM courses")->fetch_assoc()["n"];
    $stats["enrollments"] = $conn->query("SELECT COUNT(*) n FROM enrollments")->fetch_assoc()["n"];
    $adminCourses = $conn->query("SELECT code, name, credits FROM courses ORDER BY code LIMIT 5")->fetch_all(MYSQLI_ASSOC);
} else {
    $s = $conn->prepare("SELECT c.code, c.name, c.credits, e.semester, g.internal_marks, g.final_marks
        FROM enrollments e JOIN courses c ON c.id = e.course_id JOIN grades g ON g.enrollment_id = e.id
        WHERE e.user_id = ? ORDER BY e.semester, c.code");
    $s->bind_param("i", $uid);
    $s->execute();
    $all = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();

    foreach ($all as $r) {
        $r["total"] = $r["internal_marks"] + $r["final_marks"];
        $bySem[$r["semester"]][] = $r;
        if ($r["total"] >= 50) { $creditsEarned += $r["credits"]; }
    }
    $flat = [];
    foreach ($bySem as $sem => $list) {
        foreach ($list as $r) { $flat[] = $r; }
        $sum = 0; foreach ($list as $r) { $sum += $r["total"]; }
        $semAvg[$sem] = round($sum / count($list));
    }
    $cgpa = gpaOf($flat);
    if (!empty($bySem)) {
        $latestSem = array_key_last($bySem);
        $latestRows = $bySem[$latestSem];
        $keys = array_keys($bySem);
        if (count($keys) >= 2) {
            $delta = gpaOf($bySem[$keys[count($keys) - 1]]) - gpaOf($bySem[$keys[count($keys) - 2]]);
        }
    }
}
$conn->close();

$creditsPct = min(100, (int) round($creditsEarned / max(1, $PLAN_CREDITS) * 100));

if (count($semAvg) >= 2) {
    $chartLabels = array_keys($semAvg);
    $chartValues = array_values($semAvg);
    $chartTitle  = "Average score by semester";
} else {
    $chartLabels = []; $chartValues = [];
    foreach ($latestRows as $r) { $chartLabels[] = $r["code"]; $chartValues[] = (int) round($r["total"]); }
    $chartTitle = "Scores by course" . ($latestSem !== "" ? " (" . $latestSem . ")" : "");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UniFlow</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="portal.css">
</head>
<body class="pf">
<div class="pf-bg"></div>
<div class="shell">

    <aside class="sidebar">
        <div class="brand">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 3L1 9l11 6 9-4.91V17h2V9L12 3zM5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82z"/></svg>
            UniFlow
        </div>
        <div class="tagline">Study · Connect · Grow</div>

        <nav class="nav">
            <a href="dashboard.php" class="active"><i data-lucide="home"></i> Overview</a>
            <a href="grades.php"><i data-lucide="graduation-cap"></i> My Grades</a>
            <a href="courses.php"><i data-lucide="book-open"></i> Courses</a>
            <a href="schedule.php"><i data-lucide="calendar"></i> Schedule</a>
            <a href="files.php"><i data-lucide="folder"></i> Files</a>
            <a href="announcements.php"><i data-lucide="megaphone"></i> Announcements</a>
            <a href="profile.php"><i data-lucide="user"></i> Profile</a>
            <?php if ($isAdmin): ?>
                <div class="nav-title">Admin</div>
                <a href="admin_students.php"><i data-lucide="users"></i> Manage Students</a>
                <a href="admin_courses.php"><i data-lucide="library"></i> Manage Courses</a>
                <a href="admin_enrollments.php"><i data-lucide="clipboard-list"></i> Enrollments</a>
                <a href="admin_grades.php"><i data-lucide="pencil-line"></i> Manage Grades</a>
            <?php endif; ?>
        </nav>

        <div class="promo">
            <b>Stay on track!</b>
            <small>Your future is here</small>
            <a href="files.php">Explore Resources</a>
        </div>
        <a class="signout" href="logout.php"><i data-lucide="log-out"></i> Sign Out</a>
    </aside>

    <main class="content">

        <img class="hero-art" src="images/hero.png" alt="" aria-hidden="true">

        <header class="topbar">
            <div>
                <h1>Welcome, <?php echo e($fullName); ?>! 👋</h1>
                <p>Let's make this semester amazing together.</p>
            </div>
            <div class="top-actions">
                <label class="search"><i data-lucide="search"></i>
                    <input type="text" id="q" placeholder="Search courses..." autocomplete="off">
                </label>
                <div class="notif-wrap">
                    <button class="icon-btn" id="bell" data-latest="<?php echo (int) $latestAnnTs; ?>" aria-label="Notifications">
                        <i data-lucide="bell"></i>
                        <?php if ($ann): ?><span class="dot" id="dot"></span><?php endif; ?>
                    </button>
                    <div class="notif" id="notif">
                        <h4>Notifications</h4>
                        <?php if (!$ann): ?>
                            <p>No notifications yet.</p>
                        <?php else: foreach (array_slice($ann, 0, 4) as $a): ?>
                            <div class="nitem">
                                <b><?php echo e($a["title"]); ?></b>
                                <span><?php echo e($a["body"]); ?></span>
                                <time><?php echo e(agoMin($a["mins"])); ?></time>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
                <div class="avatar" title="<?php echo e($fullName); ?>"><?php echo e($initial); ?></div>
            </div>
        </header>

        <section class="grid-4">
            <?php if ($isAdmin): ?>
                <div class="card stat-card"><div class="stat-ico"><i data-lucide="users"></i></div>
                    <div><div class="stat-label">Students</div><div class="stat-num" data-count="<?php echo (int) $stats["students"]; ?>">0</div><div class="stat-note">Registered accounts</div></div></div>
                <div class="card stat-card"><div class="stat-ico"><i data-lucide="book-open"></i></div>
                    <div><div class="stat-label">Courses</div><div class="stat-num" data-count="<?php echo (int) $stats["courses"]; ?>">0</div><div class="stat-note">In the catalog</div></div></div>
                <div class="card stat-card"><div class="stat-ico"><i data-lucide="calendar-check"></i></div>
                    <div><div class="stat-label">Upcoming exams</div><div class="stat-num" data-count="<?php echo $examCount; ?>">0</div><div class="stat-note"><?php echo $nextExam !== "" ? "Next: " . e($nextExam) : "None scheduled"; ?></div></div></div>
                <div class="card stat-card"><div class="stat-ico"><i data-lucide="clock"></i></div>
                    <div><div class="stat-label">Last login</div><div class="stat-note" style="font-size:.85rem;color:var(--text)"><?php echo e($lastLogin); ?></div></div></div>
            <?php else: ?>
                <div class="card stat-card"><div class="stat-ico"><i data-lucide="bar-chart-3"></i></div>
                    <div><div class="stat-label">Current GPA</div>
                        <div class="stat-num" data-count="<?php echo number_format($cgpa, 2, ".", ""); ?>">0</div>
                        <?php if ($delta !== null): ?>
                            <span class="delta <?php echo $delta < 0 ? "down" : ""; ?>"><?php echo ($delta >= 0 ? "↑ +" : "↓ ") . number_format($delta, 2); ?></span>
                        <?php else: ?><div class="stat-note">Cumulative</div><?php endif; ?></div></div>
                <div class="card stat-card"><div class="stat-ico"><i data-lucide="book-open"></i></div>
                    <div><div class="stat-label">Courses</div><div class="stat-num" data-count="<?php echo count($latestRows); ?>">0</div><div class="stat-note"><?php echo $latestSem !== "" ? e($latestSem) : "Not enrolled yet"; ?></div></div></div>
                <div class="card stat-card"><div class="stat-ico"><i data-lucide="calendar-check"></i></div>
                    <div><div class="stat-label">Upcoming exams</div><div class="stat-num" data-count="<?php echo $examCount; ?>">0</div><div class="stat-note"><?php echo $nextExam !== "" ? "Next: " . e($nextExam) : "None scheduled"; ?></div></div></div>
                <div class="card stat-card"><div class="stat-ico"><i data-lucide="star"></i></div>
                    <div><div class="stat-label">Credits earned</div><div class="stat-num" data-count="<?php echo (int) $creditsEarned; ?>">0</div><div class="stat-note">of <?php echo (int) $PLAN_CREDITS; ?> in your plan</div></div></div>
            <?php endif; ?>
        </section>

        <section class="grid-mid">
            <div class="card">
                <h3><i data-lucide="bar-chart-2"></i> Your Academic Progress <a href="grades.php">View Details →</a></h3>
                <?php if ($isAdmin): ?>
                    <div class="empty">Progress is shown on student accounts.</div>
                <?php elseif (empty($chartValues)): ?>
                    <div class="empty">No grades yet.</div>
                <?php else: ?>
                <div class="prog">
                    <div class="ring-wrap">
                        <div class="ring" style="--p: <?php echo $creditsPct; ?>"><span><?php echo $creditsPct; ?>%</span></div>
                        <small>Credits toward degree</small>
                    </div>
                    <div class="chart-box">
                        <div class="chart-title"><?php echo e($chartTitle); ?></div>
                        <div class="chart-wrap"><canvas id="progressChart"></canvas></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <h3><i data-lucide="megaphone"></i> Announcements <a href="announcements.php">View All →</a></h3>
                <?php if (!$ann): ?>
                    <div class="empty">No announcements yet.</div>
                <?php else: foreach (array_slice($ann, 0, 3) as $a):
                    $ic = $a["kind"] === "alert" ? "alert-circle" : ($a["kind"] === "success" ? "check-circle" : "info"); ?>
                    <div class="an">
                        <span class="an-ico a-<?php echo e($a["kind"]); ?>"><i data-lucide="<?php echo $ic; ?>"></i></span>
                        <div><b><?php echo e($a["title"]); ?></b><small><?php echo e($a["body"]); ?></small></div>
                        <time><?php echo e(date("M j", strtotime($a["created_at"]))); ?></time>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </section>

        <section class="grid-bot">
            <div class="card">
                <h3><i data-lucide="calendar"></i> Upcoming Schedule <a href="schedule.php">View Calendar →</a></h3>
                <?php if (!$events): ?>
                    <div class="empty">No upcoming events yet.</div>
                <?php else: foreach ($events as $ev): $ts = strtotime($ev["starts_at"]); ?>
                    <div class="ev">
                        <div class="ev-date"><small><?php echo date("M", $ts); ?></small><b><?php echo date("j", $ts); ?></b></div>
                        <span class="ev-dot"></span>
                        <div class="ev-info"><b><?php echo e($ev["title"]); ?></b>
                            <small><?php echo date("g:i A", $ts); ?><?php echo $ev["location"] ? " · " . e($ev["location"]) : ""; ?></small></div>
                        <span class="tag t-<?php echo e($ev["type"]); ?>"><?php echo e(ucfirst($ev["type"])); ?></span>
                    </div>
                <?php endforeach; endif; ?>
            </div>

            <div class="card">
                <h3><i data-lucide="zap"></i> Quick Actions</h3>
                <div class="qa">
                    <?php if ($isAdmin): ?>
                        <a href="admin_students.php"><i data-lucide="user-plus"></i> Add Student</a>
                        <a href="announcements.php"><i data-lucide="megaphone"></i> Post Announcement</a>
                        <a href="schedule.php"><i data-lucide="calendar-plus"></i> Add Event</a>
                        <a href="files.php"><i data-lucide="upload-cloud"></i> Upload File</a>
                    <?php else: ?>
                        <a href="grades.php"><i data-lucide="book-open"></i> View Grades</a>
                        <a href="schedule.php"><i data-lucide="calendar"></i> Check Schedule</a>
                        <a href="announcements.php"><i data-lucide="megaphone"></i> Announcements</a>
                        <a href="files.php"><i data-lucide="folder"></i> Files</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <h3><i data-lucide="graduation-cap"></i> Your Courses <a href="grades.php">View All →</a></h3>
                <?php if ($isAdmin): ?>
                    <?php foreach ($adminCourses as $c): [$col, $ic] = cover($c["code"]); ?>
                        <a class="course-row" href="admin_courses.php">
                            <div class="cover" style="background:<?php echo $col[0]; ?>;color:<?php echo $col[1]; ?>"><i data-lucide="<?php echo $ic; ?>"></i></div>
                            <div class="course-info"><b><?php echo e($c["name"]); ?></b><small style="color:var(--muted);font-size:.74rem"><?php echo e($c["code"]); ?> · <?php echo (int) $c["credits"]; ?> credits</small></div>
                            <i data-lucide="chevron-right"></i>
                        </a>
                    <?php endforeach; ?>
                <?php elseif (empty($latestRows)): ?>
                    <div class="empty">You are not enrolled in any course yet.</div>
                <?php else: foreach ($latestRows as $c): [$col, $ic] = cover($c["code"]); $pct = (int) round($c["total"]); ?>
                    <a class="course-row" href="grades.php?semester=<?php echo urlencode($latestSem); ?>">
                        <div class="cover" style="background:<?php echo $col[0]; ?>;color:<?php echo $col[1]; ?>"><i data-lucide="<?php echo $ic; ?>"></i></div>
                        <div class="course-info"><b><?php echo e($c["name"]); ?></b><div class="track"><span data-w="<?php echo $pct; ?>"></span></div></div>
                        <span class="course-pct"><?php echo $pct; ?>%</span>
                        <i data-lucide="chevron-right"></i>
                    </a>
                <?php endforeach; endif; ?>
            </div>
        </section>
    </main>
</div>

<script src="https://unpkg.com/lucide@latest"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script src="portal.js"></script>
<script>
(function () {
    var L = <?php echo json_encode($chartLabels); ?>;
    var V = <?php echo json_encode($chartValues); ?>;
    var cv = document.getElementById("progressChart");
    if (cv && window.Chart && V.length) {
        var ctx = cv.getContext("2d");
        var g = ctx.createLinearGradient(0, 0, 0, 200);
        g.addColorStop(0, "rgba(11,74,53,.25)");
        g.addColorStop(1, "rgba(11,74,53,0)");
        var last = function (c) { return c.dataIndex === V.length - 1; };

        var bubble = {
            id: "bubble",
            afterDatasetsDraw: function (ch) {
                var pts = ch.getDatasetMeta(0).data;
                if (!pts.length) return;
                var p = pts[pts.length - 1], c = ch.ctx, t = V[V.length - 1] + "%";
                c.save();
                c.font = "600 12px Poppins, Arial";
                var w = c.measureText(t).width + 18, h = 24;
                var x = Math.min(p.x - w / 2, ch.chartArea.right + 10 - w), y = p.y - h - 12;
                c.fillStyle = "#0b4a35";
                c.beginPath();
                if (c.roundRect) { c.roundRect(x, y, w, h, 8); } else { c.rect(x, y, w, h); }
                c.fill();
                c.fillStyle = "#fff";
                c.textAlign = "center";
                c.textBaseline = "middle";
                c.fillText(t, x + w / 2, y + h / 2);
                c.restore();
            }
        };

        new Chart(ctx, {
            type: "line",
            data: { labels: L, datasets: [{
                data: V, borderColor: "#0b4a35", backgroundColor: g, fill: true, tension: 0.4, borderWidth: 2.5,
                pointRadius: function (c) { return last(c) ? 7 : 4; },
                pointBackgroundColor: function (c) { return last(c) ? "#fff" : "#0b4a35"; },
                pointBorderColor: "#0b4a35",
                pointBorderWidth: function (c) { return last(c) ? 3 : 0; },
                pointHoverRadius: 8
            }] },
            options: {
                responsive: true, maintainAspectRatio: false,
                layout: { padding: { top: 34, right: 14 } },
                animation: { duration: 1200 },
                scales: {
                    y: { min: 0, max: 100, ticks: { stepSize: 25, callback: function (v) { return v + "%"; } }, grid: { color: "#e6eee9" } },
                    x: { grid: { display: false } }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: { backgroundColor: "#0b4a35", padding: 10, displayColors: false,
                               callbacks: { label: function (c) { return c.parsed.y + "%"; } } }
                }
            },
            plugins: [bubble]
        });
    }

    var bell = document.getElementById("bell"), dot = document.getElementById("dot");
    if (bell && dot) {
        var latest = bell.dataset.latest;
        try { if (localStorage.getItem("uf_seen") === latest) dot.style.display = "none"; } catch (e) {}
        bell.addEventListener("click", function () {
            dot.style.display = "none";
            try { localStorage.setItem("uf_seen", latest); } catch (e) {}
        });
    }
})();
</script>
</body>
</html>