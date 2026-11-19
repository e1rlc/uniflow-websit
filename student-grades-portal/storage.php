<?php
// مجلد التخزين خارج htdocs (مثلاً C:\xampp\uniflow_storage)
define("STORAGE_DIR", dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . "uniflow_storage");
define("MAX_UPLOAD", 20 * 1024 * 1024);
$ALLOWED_EXT = ["pdf", "doc", "docx", "ppt", "pptx", "xls", "xlsx", "txt", "zip", "png", "jpg", "jpeg"];

function formatSize($b) {
    if ($b >= 1048576) return round($b / 1048576, 1) . " MB";
    if ($b >= 1024) return round($b / 1024) . " KB";
    return $b . " B";
}

function fileIcon($name) {
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (in_array($ext, ["ppt", "pptx"])) return "presentation";
    if (in_array($ext, ["xls", "xlsx"])) return "table";
    if ($ext === "zip") return "archive";
    if (in_array($ext, ["png", "jpg", "jpeg"])) return "image";
    return "file-text";
}

function kindClass($k) {
    return ["hints" => "t-quiz", "assignment" => "t-exam", "other" => "t-event"][$k] ?? "";
}