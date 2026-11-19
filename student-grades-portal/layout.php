<?php
require_once "config.php";
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: index.html");
    exit;
}
$isAdmin  = ($_SESSION["role"] === "admin");
$fullName = $_SESSION["username"];
$conn = db();

function e($s) { return htmlspecialchars((string) $s); }

function requireAdmin() {
    if ($_SESSION["role"] !== "admin") {
        http_response_code(403);
        die("Access denied.");
    }
}

function portalHeader($title, $subtitle, $active, $tools = false) {
    global $isAdmin, $fullName;
    $initial = e(strtoupper(substr($fullName, 0, 1)));
    $links = [
        "dashboard.php"     => ["home", "Overview"],
        "grades.php"        => ["graduation-cap", "My Grades"],
        "profile.php"       => ["user", "Profile"],
        "courses.php"       => ["book-open", "Courses"],
        "schedule.php"      => ["calendar", "Schedule"],
        "files.php"         => ["folder", "Files"],
        "announcements.php" => ["megaphone", "Announcements"],
    ];
    $admin = [
        "admin_students.php"    => ["users", "Manage Students"],
        "admin_courses.php"     => ["library", "Manage Courses"],
        "admin_enrollments.php" => ["clipboard-list", "Enrollments"],
        "admin_grades.php"      => ["pencil-line", "Manage Grades"],
    ];
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . e($title) . ' - UniFlow</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="portal.css">
    <link rel="stylesheet" href="pages.css">
    </head><body class="pf"><div class="pf-bg"></div><div class="shell">
    <aside class="sidebar"><div class="brand">
    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 3L1 9l11 6 9-4.91V17h2V9L12 3zM5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82z"/></svg>
    UniFlow</div><div class="tagline">Study · Connect · Grow</div><nav class="nav">';
    foreach ($links as $file => $i) {
        $cls = ($file === $active) ? ' class="active"' : '';
        echo '<a href="' . $file . '"' . $cls . '><i data-lucide="' . $i[0] . '"></i> ' . $i[1] . '</a>';
    }
    if ($isAdmin) {
        echo '<div class="nav-title">Admin</div>';
        foreach ($admin as $file => $i) {
            echo '<a href="' . $file . '"><i data-lucide="' . $i[0] . '"></i> ' . $i[1] . '</a>';
        }
    }
    echo '</nav><div class="promo"><b>Stay on track!</b><small>Your future is here</small>
    <a href="files.php">Explore Resources</a></div>
    <a class="signout" href="logout.php"><i data-lucide="log-out"></i> Sign Out</a></aside>
    <main class="content"><header class="topbar"><div><h1>' . e($title) . '</h1><p>' . e($subtitle) . '</p></div>
    <div class="top-actions">';
    if ($tools) {
        echo '<form class="search" action="courses.php" method="get"><i data-lucide="search"></i>
        <input type="text" name="q" placeholder="Search courses..." autocomplete="off"></form>
        <a class="icon-btn" href="announcements.php" aria-label="Announcements"><i data-lucide="bell"></i></a>';
    } else {
        echo '<a class="back-link" href="dashboard.php"><i data-lucide="arrow-left"></i> Dashboard</a>';
    }
    echo '<div class="avatar" title="' . e($fullName) . '">' . $initial . '</div></div></header>';
}

function portalFooter() {
    echo '</main></div><script src="https://unpkg.com/lucide@latest"></script>
    <script src="portal.js"></script></body></html>';
}

function gradePoints($t) {
    if ($t >= 90) return 4.0;
    if ($t >= 85) return 3.7;
    if ($t >= 80) return 3.3;
    if ($t >= 75) return 3.0;
    if ($t >= 70) return 2.7;
    if ($t >= 65) return 2.3;
    if ($t >= 60) return 2.0;
    if ($t >= 50) return 1.0;
    return 0.0;
}

function gradeLetter($t) {
    if ($t >= 90) return "A";
    if ($t >= 85) return "A-";
    if ($t >= 80) return "B+";
    if ($t >= 75) return "B";
    if ($t >= 70) return "B-";
    if ($t >= 65) return "C+";
    if ($t >= 60) return "C";
    if ($t >= 50) return "D";
    return "F";
}

function gradeClass($letter) {
    return $letter === "F" ? "bad" : ($letter[0] === "A" ? "good" : "mid");
}

function courseCover($code) {
    $pal = [["#0b4a35", "#e3f1e8"], ["#1d6b4f", "#e3f1e8"], ["#14584a", "#dff3ee"],
            ["#2f7d5c", "#eaf6ef"], ["#cfe8d9", "#0b4a35"], ["#a9d6bd", "#083828"], ["#e3f1e8", "#0b4a35"]];
    $icons = ["calculator", "git-branch", "cpu", "sigma", "languages", "code", "palette", "atom"];
    $h = crc32($code);
    return [$pal[$h % count($pal)], $icons[$h % count($icons)]];
}