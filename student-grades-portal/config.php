<?php
const DB_HOST = "localhost";
const DB_USER = "root";
const DB_PASS = "";
const DB_NAME = "grades_portal";
const ADMIN_EMAIL = "e1rlc.100iq@gmail.com";   // اكتب إيميل الإدارة هنا ليشتغل زر Contact Admin

// أثناء التطوير true، وعند النشر غيّرها إلى false
const DEBUG = true;
ini_set("display_errors", DEBUG ? "1" : "0");

function db() {
    $c = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($c->connect_error) { die("Database connection failed."); }
    $c->set_charset("utf8mb4");
    return $c;
}