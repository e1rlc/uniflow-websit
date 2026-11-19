<?php
require "layout.php";
require "storage.php";

$id = (int) ($_GET["id"] ?? 0);
if (!$isAdmin && $id !== (int) $_SESSION["user_id"]) { http_response_code(403); exit; }

$s = $conn->prepare("SELECT photo FROM users WHERE id = ?");
$s->bind_param("i", $id);
$s->execute();
$row = $s->get_result()->fetch_assoc();
$s->close();
if (!$row || !$row["photo"]) { http_response_code(404); exit; }

$path = STORAGE_DIR . DIRECTORY_SEPARATOR . "photos" . DIRECTORY_SEPARATOR . $row["photo"];
$info = is_file($path) ? @getimagesize($path) : false;
if (!$info) { http_response_code(404); exit; }

header("Content-Type: " . $info["mime"]);
header("Content-Length: " . filesize($path));
header("X-Content-Type-Options: nosniff");
header("Cache-Control: private, max-age=3600");
readfile($path);
exit;