<?php
require "layout.php";
require "storage.php";

$meId = (int) $_SESSION["user_id"];
$tid  = $meId;
if ($isAdmin && isset($_GET["id"])) { $tid = (int) $_GET["id"]; }

function loadUser($conn, $id) {
    $s = $conn->prepare("SELECT id, full_name, student_id, role, last_login, created_at, major, batch, faculty,
        program_type, enrollment_date, expected_graduation, student_type, status, photo, email, phone
        FROM users WHERE id = ?");
    $s->bind_param("i", $id);
    $s->execute();
    $r = $s->get_result()->fetch_assoc();
    $s->close();
    return $r;
}
function val($x) { return ($x !== null && $x !== "") ? e($x) : "—"; }
function mon($d) { return $d ? date("M Y", strtotime($d)) : "—"; }
function validDate($d) { $x = DateTime::createFromFormat("Y-m-d", $d); return $x && $x->format("Y-m-d") === $d; }
function academicYear() {
    $y = (int) date("Y"); $m = (int) date("n");
    return $m >= 9 ? $y . " - " . ($y + 1) : ($y - 1) . " - " . $y;
}
function barcodeSvg($text) {
    $h = hash("sha256", $text);
    $x = 0; $rects = "";
    for ($i = 0; $i < 64; $i++) {
        $w = 1 + (hexdec($h[$i]) % 3);
        if ($i % 2 === 0) { $rects .= '<rect x="' . $x . '" y="0" width="' . $w . '" height="40" fill="#14281f"/>'; }
        $x += $w;
    }
    return '<svg viewBox="0 0 ' . $x . ' 40" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">' . $rects . '</svg>';
}

$u = loadUser($conn, $tid);
if (!$u) { http_response_code(404); die("User not found."); }
$isSelf    = ($tid === $meId);
$isStudent = ($u["role"] === "student");
$msg = ""; $err = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if ($action === "details") {
        requireAdmin();
        $major   = trim($_POST["major"] ?? "");
        $batch   = trim($_POST["batch"] ?? "");
        $faculty = trim($_POST["faculty"] ?? "");
        $prog    = $_POST["program_type"] ?? "";
        $stype   = $_POST["student_type"] ?? "";
        $status  = $_POST["status"] ?? "";
        $enr     = trim($_POST["enrollment_date"] ?? "");
        $grad    = trim($_POST["expected_graduation"] ?? "");

        if (!$isStudent) {
            $err = "Academic details apply to student accounts only.";
        } elseif (strlen($major) > 100 || strlen($faculty) > 120) {
            $err = "Major or faculty is too long.";
        } elseif ($batch !== "" && !preg_match('/^\d{4}$/', $batch)) {
            $err = "Batch must be a 4-digit year, like 2026.";
        } elseif (!in_array($prog, ["Bachelor's Degree", "Master's Degree", "Diploma", "PhD"], true)
               || !in_array($stype, ["Local Student", "International Student"], true)
               || !in_array($status, ["active", "inactive", "graduated"], true)) {
            $err = "Invalid option selected.";
        } elseif (($enr !== "" && !validDate($enr)) || ($grad !== "" && !validDate($grad))) {
            $err = "Invalid date.";
        } else {
            $major = $major === "" ? null : $major;
            $batch = $batch === "" ? null : $batch;
            $faculty = $faculty === "" ? null : $faculty;
            $enr = $enr === "" ? null : $enr;
            $grad = $grad === "" ? null : $grad;
            $s = $conn->prepare("UPDATE users SET major=?, batch=?, faculty=?, program_type=?, enrollment_date=?,
                expected_graduation=?, student_type=?, status=? WHERE id=?");
            $s->bind_param("ssssssssi", $major, $batch, $faculty, $prog, $enr, $grad, $stype, $status, $tid);
            $s->execute();
            $s->close();
            $msg = "Profile details saved.";
        }

    } elseif ($action === "contact") {
        $email = trim($_POST["email"] ?? "");
        $phone = trim($_POST["phone"] ?? "");
        if ($email !== "" && (strlen($email) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            $err = "Enter a valid email address.";
        } elseif ($phone !== "" && !preg_match('/^[0-9+()\- ]{6,30}$/', $phone)) {
            $err = "Enter a valid phone number.";
        } else {
            $email = $email === "" ? null : $email;
            $phone = $phone === "" ? null : $phone;
            $s = $conn->prepare("UPDATE users SET email = ?, phone = ? WHERE id = ?");
            $s->bind_param("ssi", $email, $phone, $tid);
            $s->execute();
            $s->close();
            $msg = "Contact details saved.";
        }

    } elseif ($action === "photo") {
        $f = $_FILES["photo"] ?? null;
        if (!$f || $f["error"] !== UPLOAD_ERR_OK) {
            $err = "Choose an image (the upload failed or the file is too large).";
        } elseif ($f["size"] > 3 * 1024 * 1024) {
            $err = "Image is too large (max 3 MB).";
        } else {
            $info = @getimagesize($f["tmp_name"]);
            if (!$info || !in_array($info["mime"], ["image/jpeg", "image/png", "image/webp"], true)) {
                $err = "Only JPG, PNG, or WebP images are allowed.";
            } else {
                $dir = STORAGE_DIR . DIRECTORY_SEPARATOR . "photos";
                if (!is_dir($dir)) { mkdir($dir, 0777, true); }
                $name = bin2hex(random_bytes(16));
                if (move_uploaded_file($f["tmp_name"], $dir . DIRECTORY_SEPARATOR . $name)) {
                    if ($u["photo"] && is_file($dir . DIRECTORY_SEPARATOR . $u["photo"])) { unlink($dir . DIRECTORY_SEPARATOR . $u["photo"]); }
                    $s = $conn->prepare("UPDATE users SET photo = ? WHERE id = ?");
                    $s->bind_param("si", $name, $tid);
                    $s->execute();
                    $s->close();
                    $msg = "Photo updated.";
                } else {
                    $err = "Could not save the image on the server.";
                }
            }
        }

    } elseif ($action === "remove_photo") {
        if ($u["photo"]) {
            $p = STORAGE_DIR . DIRECTORY_SEPARATOR . "photos" . DIRECTORY_SEPARATOR . $u["photo"];
            if (is_file($p)) { unlink($p); }
            $s = $conn->prepare("UPDATE users SET photo = NULL WHERE id = ?");
            $s->bind_param("i", $tid);
            $s->execute();
            $s->close();
            $msg = "Photo removed.";
        }

    } elseif ($action === "password") {
        if (!$isSelf) {
            $err = "You can only change your own password.";
        } else {
            $cur = $_POST["current"] ?? "";
            $new = $_POST["new"] ?? "";
            $cnf = $_POST["confirm"] ?? "";
            $s = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
            $s->bind_param("i", $meId);
            $s->execute();
            $row = $s->get_result()->fetch_assoc();
            $s->close();
            if (!$row || !password_verify($cur, $row["password_hash"])) {
                $err = "Current password is incorrect.";
            } elseif (strlen($new) < 8) {
                $err = "New password must be at least 8 characters.";
            } elseif ($new !== $cnf) {
                $err = "New password and confirmation do not match.";
            } elseif ($new === $cur) {
                $err = "New password must be different from the current one.";
            } else {
                $hash = password_hash($new, PASSWORD_DEFAULT);
                $s = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $s->bind_param("si", $hash, $meId);
                $s->execute();
                $s->close();
                session_regenerate_id(true);
                $msg = "Password changed successfully.";
            }
        }
    }
    $u = loadUser($conn, $tid);
}

$photoUrl = $u["photo"] ? "photo.php?id=" . $tid . "&v=" . substr($u["photo"], 0, 8) : "";
$initial  = e(strtoupper(substr($u["full_name"], 0, 1)));
$formUrl  = "profile.php" . ($isSelf ? "" : "?id=" . $tid);
$canEditDetails = ($isAdmin && $isStudent);
$statusLabel = ["active" => "Active", "inactive" => "Inactive", "graduated" => "Graduated"][$u["status"]] ?? "Active";
$cardTitle = $isStudent ? "Student ID Card" : "Staff ID Card";
$roleLabel = $isStudent ? "Student" : "Administrator";
$acYear    = academicYear();
$adminMail = defined("ADMIN_EMAIL") ? ADMIN_EMAIL : "";

portalHeader($isSelf ? "My Profile" : "Student Profile", "Manage your personal and academic information", "profile.php", true);
?>
<link rel="stylesheet" href="profile.css">
<img class="hero-art" src="images/hero.png" alt="" aria-hidden="true">

<?php if ($msg): ?><p class="msg-ok"><?php echo e($msg); ?></p><?php endif; ?>
<?php if ($err): ?><p class="msg-err"><?php echo e($err); ?></p><?php endif; ?>

<section class="pr-top">

    <div class="card pr-personal">
        <h3><i data-lucide="user-cog"></i> Personal Information
            <button type="button" class="pr-btn" onclick="openDlg()"><i data-lucide="pencil"></i> Edit Profile</button></h3>
        <div class="pr-person">
            <div class="pr-avatar">
                <?php if ($photoUrl): ?><img src="<?php echo e($photoUrl); ?>" alt=""><?php else: ?><span><?php echo $initial; ?></span><?php endif; ?>
                <button type="button" class="pr-cam" onclick="openDlg('photo')" aria-label="Change photo"><i data-lucide="camera"></i></button>
            </div>
            <div class="pr-info">
                <h2><?php echo e($u["full_name"]); ?></h2>
                <p><?php echo $roleLabel; ?></p>
                <div class="pr-row"><i data-lucide="contact"></i><span>Login ID</span><b><?php echo e($u["student_id"]); ?></b></div>
                <?php if ($isStudent): ?>
                    <div class="pr-row"><i data-lucide="graduation-cap"></i><span>Major</span><b><?php echo val($u["major"]); ?></b></div>
                    <div class="pr-row"><i data-lucide="landmark"></i><span>Faculty</span><b class="sm"><?php echo val($u["faculty"]); ?></b></div>
                    <div class="pr-row"><i data-lucide="calendar-range"></i><span>Academic Year</span><b><?php echo e($acYear); ?></b></div>
                    <div class="pr-row"><i data-lucide="users"></i><span>Batch</span><b><?php echo val($u["batch"]); ?></b></div>
                <?php else: ?>
                    <div class="pr-row"><i data-lucide="calendar"></i><span>Member since</span><b><?php echo e(date("M j, Y", strtotime($u["created_at"]))); ?></b></div>
                    <div class="pr-row"><i data-lucide="clock"></i><span>Last login</span><b><?php echo $u["last_login"] ? e($u["last_login"]) : "First login"; ?></b></div>
                <?php endif; ?>
                <div class="pr-sep"></div>
                <div class="pr-row"><i data-lucide="mail"></i><b class="plain"><?php echo val($u["email"]); ?></b></div>
                <div class="pr-row"><i data-lucide="phone"></i><b class="plain"><?php echo val($u["phone"]); ?></b></div>
            </div>
        </div>
    </div>

    <div class="card pr-idpanel">
        <h3><i data-lucide="id-card"></i> Digital Student ID
            <span class="pr-tools">
                <button type="button" class="pr-btn" onclick="downloadPdf()"><i data-lucide="download"></i> Download PDF</button>
                <button type="button" class="pr-sq" onclick="expandCard()" aria-label="Enlarge"><i data-lucide="maximize-2"></i></button>
            </span></h3>

        <div class="id-wrap" id="idWrap">
            <div class="id-card" id="idCard">
                <div class="id-head">
                    <div class="id-logo">
                        <svg viewBox="0 0 24 24" fill="#fff"><path d="M12 3L1 9l11 6 9-4.91V17h2V9L12 3zM5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82z"/></svg>
                        <div><b>UniFlow</b><small>University Portal</small></div>
                    </div>
                    <em><?php echo $cardTitle; ?>
                        <svg viewBox="0 0 24 24" fill="none" stroke="#e8d9a8" stroke-width="1.6"><path d="M12 2l2.4 4.6 5.1-.7-2.3 4.6 3.6 3.7-5 1 .1 5.1-3.9-3.4-3.9 3.4.1-5.1-5-1 3.6-3.7-2.3-4.6 5.1.7z"/></svg></em>
                </div>
                <img class="id-corner" src="images/pattern-corner.png" alt="">
                <img class="id-pagoda" src="images/pattern-pagoda.png" alt="">
                <div class="id-photo">
                    <?php if ($photoUrl): ?><img src="<?php echo e($photoUrl); ?>" alt=""><?php else: ?>
                        <svg viewBox="0 0 24 24" fill="#9fcbb2"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                    <?php endif; ?>
                </div>
                <div class="id-barcode"><?php echo barcodeSvg($u["student_id"]); ?></div>
                <div class="id-data">
                    <h4><?php echo e($u["full_name"]); ?></h4>
                    <p><?php echo $isStudent ? val($u["major"]) : "UniFlow Administration"; ?></p>
                    <div><span>Login ID</span><b>: <?php echo e($u["student_id"]); ?></b></div>
                    <?php if ($isStudent): ?>
                        <div><span>Faculty</span><b>: <?php echo val($u["faculty"]); ?></b></div>
                        <div><span>Batch</span><b>: <?php echo val($u["batch"]); ?></b></div>
                        <div><span>Academic Year</span><b>: <?php echo e($acYear); ?></b></div>
                    <?php else: ?>
                        <div><span>Role</span><b>: Administrator</b></div>
                    <?php endif; ?>
                </div>
                <div class="id-qr"><div id="qr"></div></div>
                <div class="id-foot"><i></i><span>Learn · Grow · Achieve</span><i></i></div>
            </div>
        </div>
    </div>
</section>

<?php if ($isStudent): ?>
<section class="pr-bot">
    <div class="card">
        <h3><i data-lucide="graduation-cap"></i> Academic Details <a href="<?php echo $isAdmin ? "admin_grades.php?student=" . $tid : "grades.php"; ?>">View All →</a></h3>
        <div class="kv"><span><i data-lucide="folder"></i> Major</span><b><?php echo val($u["major"]); ?></b></div>
        <div class="kv"><span><i data-lucide="users"></i> Batch</span><b><?php echo val($u["batch"]); ?></b></div>
        <div class="kv"><span><i data-lucide="landmark"></i> School</span><b><?php echo val($u["faculty"]); ?></b></div>
        <div class="kv"><span><i data-lucide="award"></i> Program Type</span><b><?php echo val($u["program_type"]); ?></b></div>
    </div>

    <div class="card">
        <h3><i data-lucide="zap"></i> Quick Actions</h3>
        <div class="pr-qa">
            <button type="button" onclick="openDlg()"><i data-lucide="user-plus"></i> Update Profile</button>
            <a href="<?php echo $isAdmin ? "admin_grades.php?student=" . $tid : "grades.php"; ?>"><i data-lucide="file-text"></i> View Academic Record</a>
            <button type="button" onclick="downloadPdf()"><i data-lucide="download"></i> Download ID Card</button>
            <button type="button" onclick="contactAdmin()"><i data-lucide="headset"></i> Contact Admin</button>
        </div>
    </div>

    <div class="card pr-more">
        <h3><i data-lucide="info"></i> More Information</h3>
        <div class="kv"><span><i data-lucide="calendar"></i> Enrollment Date</span><b><?php echo mon($u["enrollment_date"]); ?></b></div>
        <div class="kv"><span><i data-lucide="calendar-check"></i> Expected Graduation</span><b><?php echo mon($u["expected_graduation"]); ?></b></div>
        <div class="kv"><span><i data-lucide="clock"></i> Student Status</span><b><span class="st-pill <?php echo e($u["status"]); ?>"><?php echo e($statusLabel); ?></span></b></div>
        <div class="kv"><span><i data-lucide="building-2"></i> Student Type</span><b><?php echo val($u["student_type"]); ?></b></div>
    </div>
</section>
<?php else: ?>
<section class="pr-bot one">
    <div class="card">
        <h3><i data-lucide="zap"></i> Quick Actions</h3>
        <div class="pr-qa">
            <button type="button" onclick="openDlg()"><i data-lucide="user-plus"></i> Update Profile</button>
            <button type="button" onclick="downloadPdf()"><i data-lucide="download"></i> Download ID Card</button>
        </div>
    </div>
</section>
<?php endif; ?>

<dialog id="bigDlg" class="dlg big"><div class="dlg-head"><b>Digital Student ID</b><button type="button" class="dlg-x" onclick="document.getElementById('bigDlg').close()" aria-label="Close">×</button></div>
    <div class="big-body" id="bigWrap"></div></dialog>

<dialog id="editDlg" class="dlg">
    <div class="dlg-head"><b>Edit profile</b><button type="button" class="dlg-x" onclick="closeDlg()" aria-label="Close">×</button></div>
    <div class="dlg-tabs">
        <button type="button" data-t="photo" class="on">Photo</button>
        <button type="button" data-t="contact">Contact</button>
        <?php if ($isSelf): ?><button type="button" data-t="password">Password</button><?php endif; ?>
        <?php if ($canEditDetails): ?><button type="button" data-t="details">Details</button><?php endif; ?>
    </div>

    <div class="dlg-pane" id="t-photo">
        <form method="post" action="<?php echo e($formUrl); ?>" enctype="multipart/form-data" class="form-stack">
            <input type="hidden" name="action" value="photo">
            <label>Choose a photo (JPG, PNG, WebP, max 3 MB)
                <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required></label>
            <button type="submit" class="btn-main"><i data-lucide="upload"></i> Upload photo</button>
        </form>
        <?php if ($u["photo"]): ?>
            <form method="post" action="<?php echo e($formUrl); ?>" style="margin-top:10px" onsubmit="return confirm('Remove the photo?');">
                <input type="hidden" name="action" value="remove_photo">
                <button type="submit" class="btn-del">Remove current photo</button>
            </form>
        <?php endif; ?>
    </div>

    <div class="dlg-pane" id="t-contact" hidden>
        <form method="post" action="<?php echo e($formUrl); ?>" class="form-stack">
            <input type="hidden" name="action" value="contact">
            <label>Email <input type="email" name="email" maxlength="120" value="<?php echo e($u["email"]); ?>" placeholder="name@example.com"></label>
            <label>Phone <input type="text" name="phone" maxlength="30" value="<?php echo e($u["phone"]); ?>" placeholder="+62 812 3456 7890"></label>
            <button type="submit" class="btn-main"><i data-lucide="save"></i> Save contact</button>
        </form>
    </div>

    <?php if ($isSelf): ?>
    <div class="dlg-pane" id="t-password" hidden>
        <form method="post" action="<?php echo e($formUrl); ?>" class="form-stack" autocomplete="off">
            <input type="hidden" name="action" value="password">
            <label>Current password <input type="password" name="current" required></label>
            <label>New password (min 8 characters) <input type="password" name="new" id="np" required minlength="8"></label>
            <div class="pw-meter"><span id="pwbar"></span></div>
            <small id="pwtxt" class="pw-note">Use letters, numbers, and symbols.</small>
            <label>Confirm new password <input type="password" name="confirm" required></label>
            <button type="submit" class="btn-main"><i data-lucide="shield-check"></i> Update password</button>
        </form>
    </div>
    <?php endif; ?>

    <?php if ($canEditDetails): ?>
    <div class="dlg-pane" id="t-details" hidden>
        <form method="post" action="<?php echo e($formUrl); ?>" class="form-stack">
            <input type="hidden" name="action" value="details">
            <label>Major <input type="text" name="major" maxlength="100" value="<?php echo e($u["major"]); ?>"></label>
            <label>Batch (year) <input type="text" name="batch" maxlength="4" value="<?php echo e($u["batch"]); ?>" placeholder="2026"></label>
            <label>Faculty <input type="text" name="faculty" maxlength="120" value="<?php echo e($u["faculty"]); ?>"></label>
            <label>Program type
                <select name="program_type">
                    <?php foreach (["Bachelor's Degree", "Master's Degree", "Diploma", "PhD"] as $o): ?>
                        <option <?php echo $u["program_type"] === $o ? "selected" : ""; ?>><?php echo e($o); ?></option>
                    <?php endforeach; ?>
                </select></label>
            <label>Enrollment date <input type="date" name="enrollment_date" value="<?php echo e($u["enrollment_date"]); ?>"></label>
            <label>Expected graduation <input type="date" name="expected_graduation" value="<?php echo e($u["expected_graduation"]); ?>"></label>
            <label>Student type
                <select name="student_type">
                    <?php foreach (["Local Student", "International Student"] as $o): ?>
                        <option <?php echo $u["student_type"] === $o ? "selected" : ""; ?>><?php echo e($o); ?></option>
                    <?php endforeach; ?>
                </select></label>
            <label>Status
                <select name="status">
                    <?php foreach (["active", "inactive", "graduated"] as $o): ?>
                        <option value="<?php echo $o; ?>" <?php echo $u["status"] === $o ? "selected" : ""; ?>><?php echo ucfirst($o); ?></option>
                    <?php endforeach; ?>
                </select></label>
            <button type="submit" class="btn-main"><i data-lucide="save"></i> Save details</button>
        </form>
    </div>
    <?php endif; ?>
</dialog>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
var ADMIN_MAIL = <?php echo json_encode($adminMail); ?>;
var FILE_ID = <?php echo json_encode(preg_replace('/[^A-Za-z0-9_-]/', '', $u["student_id"])); ?>;

if (window.QRCode) {
    new QRCode(document.getElementById("qr"), { text: <?php echo json_encode("UNIFLOW:" . $u["student_id"]); ?>, width: 92, height: 92, correctLevel: QRCode.CorrectLevel.M });
}

/* تصغير البطاقة لتناسب العرض */
function fit(wrapId, cardId) {
    var w = document.getElementById(wrapId), c = document.getElementById(cardId);
    if (!w || !c || !w.clientWidth) return;
    var k = w.clientWidth / 720;
    c.style.transform = "scale(" + k + ")";
    w.style.height = (400 * k) + "px";
}
fit("idWrap", "idCard");
window.addEventListener("resize", function () { fit("idWrap", "idCard"); });
window.addEventListener("load", function () { fit("idWrap", "idCard"); });

function expandCard() {
    var d = document.getElementById("bigDlg"), box = document.getElementById("bigWrap");
    box.innerHTML = '<div class="id-wrap" id="bigIdWrap"></div>';
    var clone = document.getElementById("idCard").cloneNode(true);
    clone.id = "bigCard";
    clone.style.transform = "";
    document.getElementById("bigIdWrap").appendChild(clone);
    d.showModal();
    fit("bigIdWrap", "bigCard");
}

function downloadPdf() {
    if (!window.html2canvas || !window.jspdf) { alert("Download needs an internet connection (libraries failed to load)."); return; }
    html2canvas(document.getElementById("idCard"), {
        scale: 3, backgroundColor: null, useCORS: true,
        onclone: function (doc) { doc.getElementById("idCard").style.transform = "none"; doc.getElementById("idWrap").style.height = "auto"; }
    }).then(function (c) {
        var pdf = new jspdf.jsPDF({ orientation: "landscape", unit: "mm", format: [108, 60] });
        pdf.addImage(c.toDataURL("image/png"), "PNG", 0, 0, 108, 60);
        pdf.save("uniflow-id-" + FILE_ID + ".pdf");
    }).catch(function () { alert("Could not create the card."); });
}

function contactAdmin() {
    if (ADMIN_MAIL) { location.href = "mailto:" + ADMIN_MAIL + "?subject=UniFlow%20support"; }
    else { alert("Admin contact email is not set yet. Add ADMIN_EMAIL in config.php."); }
}

/* نافذة التعديل */
var dlg = document.getElementById("editDlg");
function showTab(t) {
    dlg.querySelectorAll(".dlg-tabs button").forEach(function (b) { b.classList.toggle("on", b.dataset.t === t); });
    dlg.querySelectorAll(".dlg-pane").forEach(function (p) { p.hidden = (p.id !== "t-" + t); });
}
function openDlg(tab) {
    var ok = tab && dlg.querySelector('.dlg-tabs button[data-t="' + tab + '"]');
    showTab(ok ? tab : "photo");
    dlg.showModal();
}
function closeDlg() { dlg.close(); }
dlg.addEventListener("click", function (e) { if (e.target === dlg) dlg.close(); });
document.getElementById("bigDlg").addEventListener("click", function (e) { if (e.target === this) this.close(); });
dlg.querySelectorAll(".dlg-tabs button").forEach(function (b) { b.addEventListener("click", function () { showTab(b.dataset.t); }); });

var np = document.getElementById("np"), bar = document.getElementById("pwbar"), txt = document.getElementById("pwtxt");
if (np) {
    np.addEventListener("input", function () {
        var v = np.value, sc = 0;
        if (v.length >= 8) sc++;
        if (/[A-Z]/.test(v) && /[a-z]/.test(v)) sc++;
        if (/\d/.test(v)) sc++;
        if (/[^A-Za-z0-9]/.test(v)) sc++;
        var names = ["Too weak", "Weak", "Okay", "Good", "Strong"], cols = ["#b3261e", "#b3261e", "#c98a00", "#2f7d5c", "#0b4a35"];
        bar.style.width = (v ? (sc + 1) * 20 : 0) + "%";
        bar.style.background = cols[sc];
        txt.textContent = v ? names[sc] : "Use letters, numbers, and symbols.";
    });
}
</script>
<?php portalFooter(); ?>