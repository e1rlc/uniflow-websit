<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: index.html");
    exit;
}
if ($_SESSION["role"] !== "admin") {
    http_response_code(403);
    die("Access denied.");
}
require_once "config.php";
$conn = db();

function e($s) { return htmlspecialchars((string) $s); }

function adminHeader($title, $active) {
    $name = e($_SESSION["username"]);
    $initial = e(strtoupper(substr($_SESSION["username"], 0, 1)));
    $links = [
        "dashboard.php"          => ["layout-grid", "Overview"],
        "admin_students.php"     => ["users", "Manage Students"],
        "admin_courses.php"      => ["book-open", "Manage Courses"],
        "admin_enrollments.php"  => ["clipboard-list", "Enrollments"],
        "admin_grades.php"       => ["graduation-cap", "Manage Grades"],
    ];
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . e($title) . ' - UniFlow</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="dashboard.css">
    <link rel="stylesheet" href="grades.css">
    <link rel="stylesheet" href="admin.css">
    </head><body class="app"><div class="blob blob-1"></div><div class="blob blob-2"></div>
    <div class="shell"><aside class="sidebar"><div class="brand">
    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 3L1 9l11 6 9-4.91V17h2V9L12 3zM5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82z"/></svg>
    UniFlow</div><nav class="nav">';
    foreach ($links as $file => $info) {
        $cls = ($file === $active) ? ' class="active"' : '';
        echo '<a href="' . $file . '"' . $cls . '><i data-lucide="' . $info[0] . '"></i> ' . $info[1] . '</a>';
    }
    echo '</nav><a class="signout" href="logout.php">Sign Out</a></aside>
    <main class="content"><header class="topbar"><div><h1>' . e($title) . '</h1>
    <span class="badge">admin</span><span class="last-login">' . $name . '</span></div>
    <div class="top-actions"><div class="avatar">' . $initial . '</div></div></header>';
}

function adminFooter() {
    echo '</main></div><script src="https://unpkg.com/lucide@latest"></script>
    <script src="app.js"></script></body></html>';
}