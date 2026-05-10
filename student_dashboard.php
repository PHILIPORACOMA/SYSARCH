<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    header("Location: Loginpage.php");
    exit();
}

include "db.php";
include "leaderboard_helper.php";

// ── CSRF Validation for all POST requests ──
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!verify_csrf_token($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'CSRF token validation failed.']);
        exit();
    }
}

$id = $_SESSION['user_id'];
$uploads_dir = 'uploads/student_photos';
if (!is_dir($uploads_dir)) mkdir($uploads_dir, 0755, true);

/* ── AJAX: Photo ── */
if (isset($_POST['upload_photo'])) {
    header('Content-Type: application/json');
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] != 0) { echo json_encode(['status' => 'error', 'message' => 'No file.']); exit(); }
    $file = $_FILES['photo'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif'])) { echo json_encode(['status' => 'error', 'message' => 'JPG, PNG, or GIF only.']); exit(); }
    $old_q = $conn->prepare("SELECT PhotoPath FROM students_info WHERE IdNumber=?");
    $old_q->bind_param("s", $id); $old_q->execute(); $old = $old_q->get_result()->fetch_assoc(); $old_q->close();
    if ($old && $old['PhotoPath'] && file_exists($old['PhotoPath'])) unlink($old['PhotoPath']);
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $path = $uploads_dir . '/' . $id . '_' . time() . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $path)) {
        $upd = $conn->prepare("UPDATE students_info SET PhotoPath=? WHERE IdNumber=?");
        $upd->bind_param("ss", $path, $id); $upd->execute(); $upd->close();
        echo json_encode(['status' => 'ok', 'photo_path' => $path]);
    } else echo json_encode(['status' => 'error']);
    exit();
}

if (isset($_POST['remove_photo'])) {
    header('Content-Type: application/json');
    $old_q = $conn->prepare("SELECT PhotoPath FROM students_info WHERE IdNumber=?");
    $old_q->bind_param("s", $id); $old_q->execute(); $old = $old_q->get_result()->fetch_assoc(); $old_q->close();
    if ($old && $old['PhotoPath'] && file_exists($old['PhotoPath'])) unlink($old['PhotoPath']);
    $upd = $conn->prepare("UPDATE students_info SET PhotoPath=NULL WHERE IdNumber=?");
    $upd->bind_param("s", $id); $upd->execute(); $upd->close();
    echo json_encode(['status' => 'ok']);
    exit();
}

if (isset($_GET['get_pcs'])) {
    $lab = trim($_GET['lab']); $date = trim($_GET['date']);
    $lab_q = $conn->prepare("SELECT PCCount FROM labs WHERE LabName=?");
    $lab_q->bind_param("s", $lab); $lab_q->execute(); $lrow = $lab_q->get_result()->fetch_assoc(); $lab_q->close();
    $pcc = $lrow ? (int)$lrow['PCCount'] : 40;
    $occ = $conn->prepare("SELECT PCNumber FROM sit_in_sessions WHERE Lab=? AND SessionDate=? AND Status IN ('Active','Pending') AND PCNumber IS NOT NULL");
    $occ->bind_param("ss", $lab, $date); $occ->execute(); $rows = $occ->get_result()->fetch_all(MYSQLI_ASSOC); $occ->close();
    header('Content-Type: application/json');
    echo json_encode(['occupied' => array_column($rows, 'PCNumber'), 'total' => $pcc]);
    exit();
}

if (isset($_POST['submit_feedback'])) {
    $sid = (int)$_POST['fb_session_id']; $rat = (int)$_POST['fb_rating']; $tc = floatval($_POST['fb_task_completed']); $msg = trim($_POST['fb_message']);
    $ins = $conn->prepare("INSERT INTO feedback (SessionID, StudentID, Rating, Message) VALUES (?,?,?,?)");
    $ins->bind_param("isis", $sid, $id, $rat, $msg); $ins->execute(); $ins->close();
    $upd = $conn->prepare("UPDATE sit_in_sessions SET TaskCompleted=? WHERE SessionID=?");
    $upd->bind_param("di", $tc, $sid); $upd->execute(); $upd->close();
    header("Location: student_dashboard.php?feedback=success"); exit();
}

if (isset($_POST['submit_reservation'])) {
    $pur = trim($_POST['res_purpose']); $lab = trim($_POST['res_lab']); $tin = trim($_POST['res_timein']); $dat = trim($_POST['res_date']); $pc = !empty($_POST['res_pc']) ? (int)$_POST['res_pc'] : null;
    $ins = $conn->prepare("INSERT INTO sit_in_sessions (StudentID, Purpose, Lab, TimeIn, SessionDate, Status, Type, PCNumber) VALUES (?,?,?,?,?,'Pending','Reservation',?)");
    $ins->bind_param("sssssi", $id, $pur, $lab, $tin, $dat, $pc); $ins->execute(); $ins->close();
    header("Location: student_dashboard.php?res=success"); exit();
}

if (isset($_POST['update_profile'])) {
    $fn = trim($_POST['first_name']); $ln = trim($_POST['last_name']); $em = trim($_POST['email']); $ad = trim($_POST['address']); $pw = $_POST['password'];
    if (!empty($pw)) {
        $hs = password_hash($pw, PASSWORD_DEFAULT);
        $s = $conn->prepare("UPDATE students_info SET FirstName=?,LastName=?,Email=?,Address=?,Password=? WHERE IdNumber=?");
        $s->bind_param("ssssss", $fn, $ln, $em, $ad, $hs, $id);
    } else {
        $s = $conn->prepare("UPDATE students_info SET FirstName=?,LastName=?,Email=?,Address=? WHERE IdNumber=?");
        $s->bind_param("sssss", $fn, $ln, $em, $ad, $id);
    }
    $s->execute(); $s->close();
    $_SESSION['user_name'] = $fn . ' ' . $ln;
    header("Location: student_dashboard.php?profile=success"); exit();
}

/* ── Fetch Data ── */
$student = $conn->query("SELECT * FROM students_info WHERE IdNumber='$id'")->fetch_assoc();
$unread_count = $conn->query("SELECT COUNT(*) as c FROM notifications WHERE StudentID='$id' AND IsRead=0")->fetch_assoc()['c'];
$used_sessions = $conn->query("SELECT COUNT(*) as c FROM sit_in_sessions WHERE StudentID='$id' AND (Type='Sit-in' OR Type IS NULL)")->fetch_assoc()['c'];
$max_credits = 30; $credits_left = max(0, 30 - $used_sessions); $credits_percent = round(($credits_left/30)*100); $credits_color = $credits_left > 15 ? '#198754' : ($credits_left > 5 ? '#c09412' : '#dc3545');

$summary = $conn->query("SELECT COUNT(*) as sessions, COALESCE(SUM(TIMESTAMPDIFF(MINUTE, TimeIn, TimeOut)), 0) as mins, COALESCE(MAX(TIMESTAMPDIFF(MINUTE, TimeIn, TimeOut)), 0) as max_m FROM sit_in_sessions WHERE StudentID='$id' AND Status='Completed'")->fetch_assoc();
$sum_hours = round($summary['mins']/60, 1); $max_fmt = $summary['max_m']>=60 ? floor($summary['max_m']/60).'h '.($summary['max_m']%60).'m' : $summary['max_m'].'m';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard | CCS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        :root { --primary-purple: #5c2b7a; --purple-light: #7b3da3; --purple-soft: #f3eaf9; --gold: #c09412; --bg-body: #f8f9fa; --bg-card: rgba(255, 255, 255, 0.7); --text-main: #2d3436; --text-dim: #636e72; --card-radius: 24px; --shadow: 0 8px 32px rgba(31, 38, 135, 0.07); }
        body { background-color: var(--bg-body); font-family: 'Inter', sans-serif; color: var(--text-main); margin: 0; min-height: 100vh; display: flex; }
        .dock-sidebar { width: 80px; background: var(--primary-purple); height: 100vh; position: fixed; left: 0; top: 0; display: flex; flex-direction: column; align-items: center; padding: 30px 0; z-index: 1000; box-shadow: 10px 0 30px rgba(0,0,0,0.05); transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .dock-sidebar:hover { width: 240px; }
        .dock-logo { margin-bottom: 50px; }
        .dock-logo img { width: 45px; }
        .dock-nav { flex: 1; width: 100%; display: flex; flex-direction: column; gap: 12px; padding: 0 12px; }
        .dock-link { width: 100%; height: 50px; display: flex; align-items: center; text-decoration: none; color: rgba(255,255,255,0.7); border-radius: 14px; transition: 0.2s; overflow: hidden; white-space: nowrap; }
        .dock-link i { min-width: 56px; text-align: center; font-size: 1.25rem; }
        .dock-link span { font-weight: 600; font-size: 0.9rem; opacity: 0; transform: translateX(-10px); transition: 0.3s; }
        .dock-sidebar:hover .dock-link span { opacity: 1; transform: translateX(0); }
        .dock-link:hover { background: rgba(255,255,255,0.1); color: white; }
        .dock-link.active { background: white; color: var(--primary-purple); box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .main-wrapper { margin-left: 80px; flex: 1; padding: 40px; }
        .header-section { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 40px; }
        .header-section h1 { font-weight: 800; font-size: 2.2rem; letter-spacing: -1px; margin: 0; color: var(--primary-purple); }
        .bento-grid { display: grid; grid-template-columns: repeat(4, 1fr); grid-auto-rows: minmax(180px, auto); gap: 24px; }
        .bento-card { background: var(--bg-card); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border: 1px solid rgba(0,0,0,0.05); border-radius: var(--card-radius); padding: 24px; transition: 0.3s; display: flex; flex-direction: column; box-shadow: var(--shadow); }
        .bento-card:hover { transform: translateY(-5px); box-shadow: 0 15px 40px rgba(92, 43, 122, 0.12); }
        .tile-large { grid-column: span 2; grid-row: span 2; }
        .tile-wide { grid-column: span 4; }
        .tile-medium { grid-column: span 2; }
        .tile-small { grid-column: span 1; }
        .card-title { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1.5px; color: var(--text-dim); font-weight: 800; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .card-title i { color: var(--gold); }
        .vital-sign { display: flex; flex-direction: column; justify-content: center; height: 100%; }
        .vital-value { font-size: 2.5rem; font-weight: 900; line-height: 1; color: var(--primary-purple); }
        .vital-label { color: var(--text-dim); font-weight: 700; font-size: 0.75rem; text-transform: uppercase; }
        .profile-avatar { width: 90px; height: 90px; border-radius: 28px; border: 3px solid white; object-fit: cover; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .avatar-def { width: 90px; height: 90px; border-radius: 28px; background: var(--primary-purple); color: white; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 800; }
        .credits-wrap { background: var(--purple-soft); border-radius: 20px; padding: 18px; margin-top: auto; }
        .credits-bar { height: 6px; background: rgba(0,0,0,0.05); border-radius: 10px; overflow: hidden; margin-top: 10px; }
        .credits-fill { height: 100%; transition: 1s ease; }
        .glass-table { width: 100%; border-collapse: separate; border-spacing: 0 8px; }
        .glass-table th { text-align: left; font-size: 0.65rem; text-transform: uppercase; color: var(--text-dim); padding: 0 15px; font-weight: 800; }
        .glass-table tr { background: rgba(255, 255, 255, 0.4); }
        .glass-table td { padding: 12px 15px; font-weight: 600; font-size: 0.85rem; border-top: 1px solid rgba(0,0,0,0.02); border-bottom: 1px solid rgba(0,0,0,0.02); }
        .glass-table td:first-child { border-radius: 12px 0 0 12px; }
        .glass-table td:last-child { border-radius: 0 12px 12px 0; }
        .status-badge { padding: 5px 12px; border-radius: 100px; font-size: 0.6rem; font-weight: 800; text-transform: uppercase; }
        .status-active { background: #e3fcef; color: #00a854; }
        .status-completed { background: #f4f5f7; color: #5e6c84; }
        .btn-action { background: var(--primary-purple); border: none; color: white; padding: 10px 20px; border-radius: 14px; font-weight: 700; font-size: 0.8rem; }
        .lab-btn { border: 1px solid #eee; border-radius: 12px; background: white; padding: 8px 15px; font-weight: 700; font-size: 0.8rem; }
        .lab-btn.active { background: var(--primary-purple); color: white; }
        .pc-item { width: 42px; height: 42px; border-radius: 10px; background: #e3fcef; color: #00a854; display: flex; align-items: center; justify-content: center; font-weight: 800; cursor: pointer; }
        .pc-item.occupied { background: #fde8e8; color: #dc3545; cursor: not-allowed; }
        .pc-item.selected { border: 2px solid var(--primary-purple); }
        .modal-content { border-radius: 30px; border: none; background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(20px); }
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-thumb { background: #e0e0e0; border-radius: 10px; }
    </style>
</head>
<body>

<aside class="dock-sidebar">
    <div class="dock-logo"><img src="UCLogo-removebg-preview.png" alt="UC"></div>
    <nav class="dock-nav">
        <a href="student_dashboard.php" class="dock-link active"><i class="fa-solid fa-house"></i><span>Dashboard</span></a>
        <a href="#" class="dock-link" onclick="openPhotoModal()"><i class="fa-solid fa-camera"></i><span>Photo</span></a>
        <a href="#" class="dock-link" data-bs-toggle="modal" data-bs-target="#resModal"><i class="fa-solid fa-calendar-check"></i><span>Reservation</span></a>
        <a href="#" class="dock-link" data-bs-toggle="modal" data-bs-target="#notifModal" onclick="markRead()"><i class="fa-solid fa-bell"></i><span>Alerts</span><?php if($unread_count>0): ?><div style="width:8px;height:8px;background:var(--gold);border-radius:50%;margin-left:auto;margin-right:15px;"></div><?php endif; ?></a>
        <a href="#" class="dock-link" data-bs-toggle="modal" data-bs-target="#histModal"><i class="fa-solid fa-history"></i><span>History</span></a>
        <a href="#" class="dock-link" data-bs-toggle="modal" data-bs-target="#editModal"><i class="fa-solid fa-user-gear"></i><span>Settings</span></a>
    </nav>
    <form method="POST" action="logout.php" class="w-100 px-2"><?php csrf_input(); ?><button class="dock-link border-0 bg-transparent w-100 text-danger"><i class="fa-solid fa-power-off"></i><span>Logout</span></button></form>
</aside>

<main class="main-wrapper">
    <div class="header-section">
        <div><div class="vital-label mb-1"><?= $student['Course'] ?> • Year <?= $student['CourseLevel'] ?></div><h1>My Dashboard</h1></div>
        <div class="text-end"><div class="fw-800 small"><?= date('l, M d') ?></div><div class="small text-dim"><?= date('h:i A') ?></div></div>
    </div>

    <div class="bento-grid">
        <div class="bento-card tile-large">
            <div class="card-title">My Profile</div>
            <div class="d-flex align-items-center gap-4 mb-4">
                <?php if($student['PhotoPath'] && file_exists($student['PhotoPath'])): ?><img src="<?= $student['PhotoPath'] ?>" class="profile-avatar"><?php else: ?><div class="avatar-def"><?= substr($student['FirstName'],0,1) ?></div><?php endif; ?>
                <div><h3 class="fw-900 mb-0"><?= $student['FirstName'].' '.$student['LastName'] ?></h3><div class="text-dim small fw-700"><?= $student['IdNumber'] ?></div><span class="badge bg-white shadow-sm border mt-2" style="color:var(--primary-purple)"><?= $student['Email'] ?></span></div>
            </div>
            <div class="credits-wrap">
                <div class="d-flex justify-content-between align-items-end mb-1"><div class="vital-label">Credits Left</div><div class="fw-800" style="color:<?= $credits_color ?>"><?= $credits_percent ?>%</div></div>
                <div class="vital-value" style="color:<?= $credits_color ?>; font-size:2rem;"><?= $credits_left ?> <span class="small opacity-50" style="font-size:0.8rem">Sessions</span></div>
                <div class="credits-bar"><div class="credits-fill" style="width:<?= $credits_percent ?>%; background:<?= $credits_color ?>; height:100%"></div></div>
            </div>
        </div>

        <div class="bento-card tile-small"><div class="card-title">Total Hours</div><div class="vital-sign"><div class="vital-value"><?= $sum_hours ?></div><div class="vital-label">Lifetime</div></div></div>
        <div class="bento-card tile-small"><div class="card-title">Peak Session</div><div class="vital-sign"><div class="vital-value text-gold"><?= $max_fmt ?></div><div class="vital-label">Longest</div></div></div>
        <div class="bento-card tile-small"><div class="card-title">History</div><div class="vital-sign"><div class="vital-value"><?= $summary['sessions'] ?></div><div class="vital-label">Total Logs</div></div></div>
        <div class="bento-card tile-small"><div class="card-title">Average</div><div class="vital-sign"><div class="vital-value text-gold"><?= $max_fmt ?></div><div class="vital-label">Per Session</div></div></div>

        <div class="bento-card tile-medium"><div class="card-title">Usage Analysis</div><div style="height:160px"><canvas id="usageChart"></canvas></div></div>
        
        <div class="bento-card tile-medium">
            <div class="card-title">Announcements</div>
            <div class="d-flex flex-column gap-3 overflow-auto" style="max-height: 180px;">
                <?php $ann=$conn->query("SELECT * FROM announcements ORDER BY DatePosted DESC LIMIT 3"); while($a=$ann->fetch_assoc()): ?>
                <div class="p-2 border-bottom"><div class="fw-800 small text-gold"><?= $a['Title'] ?></div><p class="small text-dim mb-0"><?= substr($a['Message'],0,60) ?>...</p></div>
                <?php endwhile; ?>
            </div>
        </div>

        <div class="bento-card tile-wide">
            <div class="card-title">Recent Sit-ins</div>
            <div class="glass-table-container">
                <table class="glass-table">
                    <thead><tr><th>Purpose</th><th>Lab & PC</th><th>Time</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                    <tbody>
                        <?php $rs=$conn->query("SELECT * FROM sit_in_sessions WHERE StudentID='$id' AND (Type='Sit-in' OR Type IS NULL) ORDER BY SessionDate DESC LIMIT 5");
                        while($s=$rs->fetch_assoc()): $st=strtolower($s['Status']); ?>
                        <tr><td><div class="fw-800"><?= $s['Purpose']?:'General' ?></div><div class="small text-dim"><?= $s['SessionDate'] ?></div></td><td>Lab <?= $s['Lab'] ?> • PC <?= $s['PCNumber']?:'Any' ?></td><td><?= substr($s['TimeIn'],0,5) ?> - <?= $s['TimeOut']?substr($s['TimeOut'],0,5):'LIVE' ?></td><td><span class="status-badge <?= $st==='active'?'status-active':'status-completed' ?>"><?= $s['Status'] ?></span></td><td class="text-end"><?php if($st==='completed'): ?><button class="btn-action py-1 px-3" onclick="openFeedback(<?= $s['SessionID'] ?>,'<?= $s['SessionDate'] ?>')">Rate</button><?php endif; ?></td></tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- MODALS -->
<div class="modal fade" id="photoModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="fw-800">Profile Photo</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body text-center"><div id="photoPreview" class="mb-4"></div><input type="file" id="photoUploadInput" class="d-none" onchange="uploadPhoto(this)"><button class="btn-action w-100 py-3 mb-2" onclick="document.getElementById('photoUploadInput').click()">Upload New Photo</button><button class="btn btn-link text-danger w-100" onclick="removePhoto()">Remove Photo</button></div></div></div></div>

<div class="modal fade" id="resModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="fw-800">Lab Reservation</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><form method="POST"><?php csrf_input(); ?><div class="row g-3"><div class="col-md-6"><label class="small fw-800">Purpose</label><select name="res_purpose" class="form-select"><option>C Programming</option><option>Java Programming</option><option>Research</option></select></div><div class="col-md-6"><label class="small fw-800">Date</label><input type="date" name="res_date" class="form-control" min="<?= date('Y-m-d') ?>"></div><div class="col-12"><label class="small fw-800">Laboratory</label><div class="d-flex gap-2 flex-wrap"><?php $lbq=$conn->query("SELECT * FROM labs"); while($lb=$lbq->fetch_assoc()): ?><button type="button" class="lab-btn" data-lab="<?= $lb['LabName'] ?>" data-total="<?= $lb['PCCount'] ?>">Lab <?= $lb['LabName'] ?></button><?php endwhile; ?></div><input type="hidden" name="res_lab" id="res_lab_input" required><input type="hidden" name="res_pc" id="res_pc_input"></div><div id="pcPickerWrap" class="col-12 d-none"><div class="small fw-800 mb-2">Select PC Number</div><div id="pcGrid" style="display:grid; grid-template-columns:repeat(auto-fill,minmax(50px,1fr)); gap:8px;"></div></div><div class="col-md-6"><label class="small fw-800">Time In</label><input type="time" name="res_timein" class="form-control"></div><button type="submit" name="submit_reservation" class="btn-action w-100 py-3 mt-3">REQUEST RESERVATION</button></div></form></div></div></div></div>

<div class="modal fade" id="feedbackModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="fw-800">Rate Session</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><form method="POST"><?php csrf_input(); ?><input type="hidden" name="fb_session_id" id="fb_session_id"><div class="mb-3"><label class="small fw-800 mb-2">Task Completed?</label><select name="fb_task_completed" class="form-select"><option value="1">Fully</option><option value="0.5">Partially</option><option value="0">No</option></select></div><div class="mb-3"><label class="small fw-800">PC Rating (1-5)</label><input type="number" name="fb_rating" class="form-control" min="1" max="5"></div><textarea name="fb_message" class="form-control mb-3" placeholder="Comments..."></textarea><button type="submit" name="submit_feedback" class="btn-action w-100 py-3">SUBMIT</button></form></div></div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openPhotoModal(){new bootstrap.Modal(document.getElementById('photoModal')).show();}
function uploadPhoto(i){const fd=new FormData(); fd.append('photo',i.files[0]); fd.append('upload_photo','1'); fd.append('csrf_token','<?=get_csrf_token()?>'); fetch('',{method:'POST',body:fd}).then(()=>location.reload());}
function removePhoto(){if(confirm('Delete?')) fetch('',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'remove_photo=1&csrf_token=<?=get_csrf_token()?>'}).then(()=>location.reload());}
document.querySelectorAll('.lab-btn').forEach(b=>b.onclick=function(){document.querySelectorAll('.lab-btn').forEach(x=>x.classList.remove('active')); this.classList.add('active'); document.getElementById('res_lab_input').value=this.dataset.lab; document.getElementById('pcPickerWrap').classList.remove('d-none'); loadPCs(this.dataset.lab, document.querySelector('[name="res_date"]').value);});
function loadPCs(l,d){const g=document.getElementById('pcGrid'); g.innerHTML='Loading...'; fetch(`?get_pcs=1&lab=${l}&date=${d}`).then(r=>r.json()).then(data=>{g.innerHTML=''; (data.occupied||[]).map(Number); for(let i=1;i<=data.total;i++){const div=document.createElement('div'); div.className='pc-item'+(data.occupied.includes(i)?' occupied':''); div.innerText=i; if(!data.occupied.includes(i)) div.onclick=function(){g.querySelectorAll('.pc-item').forEach(p=>p.classList.remove('selected')); this.classList.add('selected'); document.getElementById('res_pc_input').value=i;}; g.appendChild(div);}}); }
function openFeedback(id,date){document.getElementById('fb_session_id').value=id; new bootstrap.Modal(document.getElementById('feedbackModal')).show();}
function markRead(){fetch('',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'mark_notifications_read=1&csrf_token=<?=get_csrf_token()?>'});}
document.addEventListener('DOMContentLoaded',()=>{const ctx=document.getElementById('usageChart');if(ctx)new Chart(ctx,{type:'doughnut',data:{labels:['Used','Left'],datasets:[{data:[<?=$used_sessions?>,<?=$credits_left?>],backgroundColor:['#5c2b7a','rgba(0,0,0,0.05)'],borderWidth:0}]},options:{responsive:true,maintainAspectRatio:false,cutout:'80%',plugins:{legend:{display:false}}}});});
</script>
</body></html>