<?php
require "layout.php";
require "storage.php";

$id = (int) ($_GET["id"] ?? 0);
$s = $conn->prepare("SELECT course_id, stored_name, original_name FROM files WHERE id = ?");
$s->bind_param("i", $id);
$s->execute();
$f = $s->get_result()->fetch_assoc();
$s->close();

if (!$f) { http_response_code(404); die("File not found."); }

// الطالب: لازم الملف عام أو لمادة هو مسجل فيها
if (!$isAdmin && $f["course_id"] !== null) {
    $uid = (int) $_SESSION["user_id"];
    $c = $conn->prepare("SELECT 1 FROM enrollments WHERE user_id = ? AND course_id = ? LIMIT 1");
    $cid = (int) $f["course_id"];
    $c->bind_param("ii", $uid, $cid);
    $c->execute();
    if (!$c->get_result()->fetch_row()) { http_response_code(403); die("Access denied."); }
    $c->close();
}

$path = STORAGE_DIR . DIRECTORY_SEPARATOR . $f["stored_name"];
if (!is_file($path)) { http_response_code(404); die("File missing on the server."); }

$safe = preg_replace('/[^A-Za-z0-9._ -]/', "_", $f["original_name"]);
header("Content-Type: application/octet-stream");
header('Content-Disposition: attachment; filename="' . $safe . '"');
header("Content-Length: " . filesize($path));
header("X-Content-Type-Options: nosniff");
readfile($path);
exit;