<?php
require_once "config.php";
session_start();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.html");
    exit;
}

$conn = db();

$student_id = trim($_POST["username"] ?? "");
$password   = $_POST["password"] ?? "";
$ip         = $_SERVER["REMOTE_ADDR"] ?? "0.0.0.0";

// تنظيف المحاولات القديمة
$conn->query("DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)");

// فحص القفل: 5 محاولات لنفس الاسم من نفس الجهاز، أو 20 من نفس الجهاز، خلال 10 دقائق
$c = $conn->prepare("SELECT
        SUM(student_id = ?) AS by_user,
        COUNT(*) AS by_ip
    FROM login_attempts
    WHERE ip = ? AND attempted_at > (NOW() - INTERVAL 10 MINUTE)");
$c->bind_param("ss", $student_id, $ip);
$c->execute();
$cnt = $c->get_result()->fetch_assoc();
$c->close();

if ((int) $cnt["by_user"] >= 5 || (int) $cnt["by_ip"] >= 20) {
    $conn->close();
    header("Location: index.html?error=locked");
    exit;
}

$stmt = $conn->prepare("SELECT id, full_name, password_hash, role FROM users WHERE student_id = ?");
$stmt->bind_param("s", $student_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($user && password_verify($password, $user["password_hash"])) {

    // نجح الدخول: نمسح محاولاته الفاشلة
    $d = $conn->prepare("DELETE FROM login_attempts WHERE student_id = ? AND ip = ?");
    $d->bind_param("ss", $student_id, $ip);
    $d->execute();
    $d->close();

    session_regenerate_id(true);
    $_SESSION["user_id"]  = $user["id"];
    $_SESSION["username"] = $user["full_name"];
    $_SESSION["role"]     = $user["role"];

    $u = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $u->bind_param("i", $user["id"]);
    $u->execute();
    $u->close();

    $conn->close();
    header("Location: dashboard.php");
    exit;
}

// فشل الدخول: نسجل المحاولة
$a = $conn->prepare("INSERT INTO login_attempts (student_id, ip) VALUES (?, ?)");
$a->bind_param("ss", $student_id, $ip);
$a->execute();
$a->close();
$conn->close();

header("Location: index.html?error=invalid");
exit;