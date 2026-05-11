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
    
    // Get occupied PCs from sessions
    $occ_res = $conn->prepare("SELECT PCNumber FROM sit_in_sessions WHERE Lab=? AND SessionDate=? AND Status IN ('Active','Pending','Approved') AND PCNumber IS NOT NULL");
    $occ_res->bind_param("ss", $lab, $date); $occ_res->execute(); $rows = $occ_res->get_result()->fetch_all(MYSQLI_ASSOC); $occ_res->close();
    $occupied = array_column($rows, 'PCNumber');
    
    // Get disabled PCs with reasons
    $dis_res = $conn->prepare("SELECT PCNumber, Reason FROM disabled_pcs WHERE LabName=?");
    $dis_res->bind_param("s", $lab); $dis_res->execute(); $dis_rows = $dis_res->get_result()->fetch_all(MYSQLI_ASSOC); $dis_res->close();
    $disabled = [];
    foreach($dis_rows as $dr) $disabled[$dr['PCNumber']] = $dr['Reason'];
    
    header('Content-Type: application/json');
    echo json_encode([
        'occupied' => $occupied, 
        'disabled' => $disabled, 
        'total' => $pcc
    ]);
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

if (isset($_POST['mark_notifications_read'])) {
    $upd = $conn->prepare("UPDATE notifications SET IsRead=1 WHERE StudentID=?");
    $upd->bind_param("s", $id);
    $upd->execute();
    $upd->close();
    exit();
}

/* ── Fetch Data ── */
$student = $conn->query("SELECT * FROM students_info WHERE IdNumber='$id'")->fetch_assoc();
$unread_count = $conn->query("SELECT COUNT(*) as c FROM notifications WHERE StudentID='$id' AND IsRead=0")->fetch_assoc()['c'];
$used_sessions = $conn->query("SELECT COUNT(*) as c FROM sit_in_sessions WHERE StudentID='$id' AND (Type='Sit-in' OR Type IS NULL)")->fetch_assoc()['c'];
$max_credits = 30; $credits_left = max(0, 30 - $used_sessions); $credits_percent = round(($credits_left/30)*100); $credits_color = $credits_left > 15 ? '#198754' : ($credits_left > 5 ? '#c09412' : '#dc3545');

$summary = $conn->query("SELECT COUNT(*) as sessions, COALESCE(SUM(TIMESTAMPDIFF(MINUTE, TimeIn, TimeOut)), 0) as mins, COALESCE(MAX(TIMESTAMPDIFF(MINUTE, TimeIn, TimeOut)), 0) as max_m FROM sit_in_sessions WHERE StudentID='$id' AND Status='Completed'")->fetch_assoc();
$sum_hours = round($summary['mins']/60, 1); $max_fmt = $summary['max_m']>=60 ? floor($summary['max_m']/60).'h '.($summary['max_m']%60).'m' : $summary['max_m'].'m';

// Fetch Purpose distribution for chart
$purp_q = $conn->query("SELECT Purpose, COUNT(*) as count FROM sit_in_sessions WHERE StudentID='$id' GROUP BY Purpose");
$purp_labels = []; $purp_counts = [];
while($p_row = $purp_q->fetch_assoc()) {
    $purp_labels[] = $p_row['Purpose'] ?: 'General';
    $purp_counts[] = (int)$p_row['count'];
}
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
        :root { --primary-purple: #4a1d64; --purple-light: #632d8a; --purple-soft: #f3eaf9; --gold: #a67c0d; --bg-body: #f8f9fa; --bg-card: rgba(255, 255, 255, 0.8); --text-main: #1d2122; --text-dim: #454d4f; --card-radius: 24px; --shadow: 0 8px 32px rgba(31, 38, 135, 0.1); }
        body { background-color: var(--bg-body); font-family: 'Inter', sans-serif; color: var(--text-main); margin: 0; min-height: 100vh; display: flex; font-size: 16px; line-height: 1.6; }
        .dock-sidebar { width: 80px; background: var(--primary-purple); height: 100vh; position: fixed; left: 0; top: 0; display: flex; flex-direction: column; align-items: center; padding: 30px 0; z-index: 1000; box-shadow: 10px 0 30px rgba(0,0,0,0.1); transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .dock-sidebar:hover { width: 240px; }
        .dock-logo { margin-bottom: 50px; }
        .dock-logo img { width: 45px; }
        .dock-nav { flex: 1; width: 100%; display: flex; flex-direction: column; gap: 12px; padding: 0 12px; }
        .dock-link { width: 100%; height: 55px; display: flex; align-items: center; text-decoration: none; color: rgba(255,255,255,0.85); border-radius: 14px; transition: 0.2s; overflow: hidden; white-space: nowrap; }
        .dock-link i { min-width: 56px; text-align: center; font-size: 1.4rem; }
        .dock-link span { font-weight: 700; font-size: 1rem; opacity: 0; transform: translateX(-10px); transition: 0.3s; }
        .dock-sidebar:hover .dock-link span { opacity: 1; transform: translateX(0); }
        .dock-link:hover { background: rgba(255,255,255,0.15); color: white; }
        .dock-link.active { background: white; color: var(--primary-purple); box-shadow: 0 4px 15px rgba(0,0,0,0.15); }
        .main-wrapper { margin-left: 80px; flex: 1; padding: 40px; }
        .header-section { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 40px; }
        .header-section h1 { font-weight: 800; font-size: 2.5rem; letter-spacing: -1.2px; margin: 0; color: var(--primary-purple); }
        .bento-grid { display: grid; grid-template-columns: repeat(4, 1fr); grid-auto-rows: minmax(180px, auto); gap: 24px; }
        .bento-card { background: var(--bg-card); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border: 1px solid rgba(0,0,0,0.08); border-radius: var(--card-radius); padding: 28px; transition: 0.3s; display: flex; flex-direction: column; box-shadow: var(--shadow); }
        .bento-card:hover { transform: translateY(-5px); box-shadow: 0 15px 40px rgba(92, 43, 122, 0.15); }
        .tile-large { grid-column: span 2; grid-row: span 2; }
        .tile-wide { grid-column: span 4; }
        .tile-medium { grid-column: span 2; }
        .tile-small { grid-column: span 1; }
        .card-title { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1.8px; color: var(--text-dim); font-weight: 800; margin-bottom: 24px; display: flex; align-items: center; gap: 10px; }
        .card-title i { color: var(--gold); font-size: 1rem; }
        .vital-sign { display: flex; flex-direction: column; justify-content: center; height: 100%; }
        .vital-value { font-size: 2.8rem; font-weight: 900; line-height: 1; color: var(--primary-purple); }
        .vital-label { color: var(--text-dim); font-weight: 800; font-size: 0.85rem; text-transform: uppercase; }
        .profile-avatar { width: 100px; height: 100px; border-radius: 28px; border: 4px solid white; object-fit: cover; box-shadow: 0 10px 25px rgba(0,0,0,0.15); }
        .avatar-def { width: 100px; height: 100px; border-radius: 28px; background: var(--primary-purple); color: white; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: 800; }
        .profile-photo-container { position: relative; width: 100px; height: 100px; }
        .edit-photo-btn { position: absolute; bottom: -5px; right: -5px; width: 35px; height: 35px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--primary-purple); box-shadow: 0 4px 10px rgba(0,0,0,0.2); cursor: pointer; transition: 0.2s; border: 2px solid var(--primary-purple); z-index: 10; }
        .edit-photo-btn:hover { transform: scale(1.1); background: var(--purple-soft); }
        .credits-wrap { background: var(--purple-soft); border-radius: 20px; padding: 20px; margin-top: auto; }
        .credits-bar { height: 8px; background: rgba(0,0,0,0.1); border-radius: 10px; overflow: hidden; margin-top: 12px; }
        .credits-fill { height: 100%; transition: 1s ease; }
        .glass-table { width: 100%; border-collapse: separate; border-spacing: 0 10px; }
        .glass-table th { text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--text-dim); padding: 0 15px; font-weight: 900; }
        .glass-table tr { background: rgba(255, 255, 255, 0.5); }
        .glass-table td { padding: 15px; font-weight: 700; font-size: 1rem; border-top: 1px solid rgba(0,0,0,0.03); border-bottom: 1px solid rgba(0,0,0,0.03); color: var(--text-main); }
        .glass-table td:first-child { border-radius: 14px 0 0 14px; }
        .glass-table td:last-child { border-radius: 0 14px 14px 0; }
        .status-badge { padding: 6px 14px; border-radius: 100px; font-size: 0.7rem; font-weight: 900; text-transform: uppercase; }
        .status-active { background: #d1f7e3; color: #008a46; }
        .status-completed { background: #eaecf0; color: #344054; }
        .btn-action { background: var(--primary-purple); border: none; color: white; padding: 12px 24px; border-radius: 14px; font-weight: 800; font-size: 0.9rem; }
        .lab-btn { border: 2px solid #eee; border-radius: 12px; background: white; padding: 10px 18px; font-weight: 800; font-size: 0.9rem; color: var(--text-main); }
        .lab-btn.active { background: var(--primary-purple); color: white; border-color: var(--primary-purple); }
        .pc-item { width: 45px; height: 45px; border-radius: 12px; background: #d1f7e3; color: #008a46; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 1rem; cursor: pointer; }
        .pc-item.occupied { background: #fee2e2; color: #b91c1c; cursor: not-allowed; }
        .pc-item.selected { border: 3px solid var(--primary-purple); }
        .modal-content { border-radius: 32px; border: none; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(25px); box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); }
        .modal-header { border-bottom: 1px solid rgba(0,0,0,0.05); padding: 25px 30px; }
        .modal-body { padding: 30px; }
        .form-label, label { font-weight: 800; color: var(--text-main); font-size: 0.95rem; margin-bottom: 8px; }
        .form-control, .form-select { border: 2px solid #eee; border-radius: 14px; padding: 12px 16px; font-weight: 600; font-size: 1rem; color: var(--text-main); }
        .form-control:focus { border-color: var(--primary-purple); box-shadow: 0 0 0 4px rgba(92, 43, 122, 0.1); }
        .text-dim { color: var(--text-dim) !important; }
        .fw-800 { font-weight: 800 !important; }
        .fw-900 { font-weight: 900 !important; }
    </style>
</head>
<body>

<aside class="dock-sidebar">
    <div class="dock-logo"><img src="UCLogo-removebg-preview.png" alt="UC"></div>
    <nav class="dock-nav">
        <a href="student_dashboard.php" class="dock-link active"><i class="fa-solid fa-house"></i><span>Dashboard</span></a>
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
                <div class="profile-photo-container">
                    <?php if($student['PhotoPath'] && file_exists($student['PhotoPath'])): ?>
                        <img src="<?= $student['PhotoPath'] ?>" class="profile-avatar">
                    <?php else: ?>
                        <div class="avatar-def"><?= substr($student['FirstName'],0,1) ?></div>
                    <?php endif; ?>
                    <div class="edit-photo-btn" onclick="openPhotoModal()">
                        <i class="fa-solid fa-camera"></i>
                    </div>
                </div>
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

        <div class="bento-card tile-wide">
            <div class="card-title">Available Lab Resources</div>
            <div class="glass-table-container">
                <div class="row g-3">
                    <?php 
                    $labs = $conn->query("SELECT DISTINCT LabName FROM lab_resources ORDER BY LabName");
                    if($labs && $labs->num_rows > 0): 
                        while($l = $labs->fetch_assoc()): 
                            $lname = $l['LabName'];
                    ?>
                        <div class="col-md-4">
                            <div class="p-3 rounded-4 bg-white border h-100 shadow-sm">
                                <div class="fw-800 text-gold mb-2 border-bottom pb-1">Lab <?= $lname ?></div>
                                <div class="d-flex flex-column gap-2">
                                    <?php 
                                    $apps = $conn->query("SELECT * FROM lab_resources WHERE LabName='$lname' ORDER BY Category, AppName");
                                    while($app = $apps->fetch_assoc()): 
                                    ?>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="fw-700" style="font-size: 0.9rem;"><?= $app['AppName'] ?></div>
                                                <div class="text-dim" style="font-size: 0.75rem;"><?= $app['Category'] ?></div>
                                            </div>
                                            <?php if($app['Description']): ?>
                                                <span class="badge bg-light text-dim fw-600" style="font-size: 0.65rem;"><?= $app['Description'] ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; else: ?>
                        <div class="col-12 text-center py-4 text-dim">No lab resources listed yet.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- MODALS -->
<div class="modal fade" id="photoModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="fw-800">Profile Photo</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body text-center"><div id="photoPreview" class="mb-4"></div><input type="file" id="photoUploadInput" class="d-none" onchange="uploadPhoto(this)"><button class="btn-action w-100 py-3 mb-2" onclick="document.getElementById('photoUploadInput').click()">Upload New Photo</button><button class="btn btn-link text-danger w-100" onclick="removePhoto()">Remove Photo</button></div></div></div></div>

<div class="modal fade" id="resModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="fw-800">Lab Reservation</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><form method="POST"><?php csrf_input(); ?><div class="row g-3"><div class="col-md-6"><label class="small fw-800">Purpose</label><select name="res_purpose" class="form-select"><option>C Programming</option><option>Java Programming</option><option>Research</option></select></div><div class="col-md-6"><label class="small fw-800">Date</label><input type="date" name="res_date" class="form-control" min="<?= date('Y-m-d') ?>"></div><div class="col-12"><label class="small fw-800">Laboratory</label><div class="d-flex gap-2 flex-wrap"><?php $lbq=$conn->query("SELECT * FROM labs"); while($lb=$lbq->fetch_assoc()): ?><button type="button" class="lab-btn" data-lab="<?= $lb['LabName'] ?>" data-total="<?= $lb['PCCount'] ?>">Lab <?= $lb['LabName'] ?></button><?php endwhile; ?></div><input type="hidden" name="res_lab" id="res_lab_input" required><input type="hidden" name="res_pc" id="res_pc_input"></div><div id="pcPickerWrap" class="col-12 d-none"><div class="small fw-800 mb-2">Select PC Number</div><div id="pcGrid" style="display:grid; grid-template-columns:repeat(auto-fill,minmax(50px,1fr)); gap:8px;"></div></div><div class="col-md-6"><label class="small fw-800">Time In</label><input type="time" name="res_timein" class="form-control"></div><button type="submit" name="submit_reservation" class="btn-action w-100 py-3 mt-3">REQUEST RESERVATION</button></div></form></div></div></div></div>

<div class="modal fade" id="feedbackModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="fw-800">Rate Session</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><form method="POST"><?php csrf_input(); ?><input type="hidden" name="fb_session_id" id="fb_session_id"><div class="mb-3"><label class="small fw-800 mb-2">Task Completed?</label><select name="fb_task_completed" class="form-select"><option value="1">Fully</option><option value="0.5">Partially</option><option value="0">No</option></select></div><div class="mb-3"><label class="small fw-800">PC Rating (1-5)</label><input type="number" name="fb_rating" class="form-control" min="1" max="5"></div><textarea name="fb_message" class="form-control mb-3" placeholder="Comments..."></textarea><button type="submit" name="submit_feedback" class="btn-action w-100 py-3">SUBMIT</button></form></div></div></div></div>

<!-- Alerts Modal -->
<div class="modal fade" id="notifModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="fw-800">Notifications</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex flex-column gap-2">
                    <?php 
                    $notifs = $conn->query("SELECT * FROM notifications WHERE StudentID='$id' ORDER BY (SELECT NULL) DESC LIMIT 10");
                    if($notifs && $notifs->num_rows > 0): 
                        while($n = $notifs->fetch_assoc()): 
                    ?>
                        <div class="p-3 border-bottom <?= (isset($n['IsRead']) && $n['IsRead']) ? 'opacity-50' : '' ?>">
                            <div class="fw-800 text-gold mb-1" style="font-size: 1.1rem;">System Alert</div>
                            <div class="fw-600" style="color: var(--text-main); font-size: 1rem;"><?= htmlspecialchars($n['Message']) ?></div>
                        </div>
                    <?php endwhile; else: ?>
                        <div class="text-center py-4 text-dim">No notifications</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- History Modal -->
<div class="modal fade" id="histModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="fw-800">My Session History</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                <table class="glass-table">
                    <thead>
                        <tr><th>Purpose</th><th>Lab & PC</th><th>Time In</th><th>Time Out</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php 
                        $ah = $conn->query("SELECT * FROM sit_in_sessions WHERE StudentID='$id' ORDER BY SessionDate DESC, TimeIn DESC");
                        while($h = $ah->fetch_assoc()): 
                            $hst = strtolower($h['Status']); 
                        ?>
                        <tr>
                            <td>
                                <div class="fw-800"><?= $h['Purpose'] ?: 'General' ?></div>
                                <div class="small text-dim"><?= $h['SessionDate'] ?></div>
                            </td>
                            <td>Lab <?= $h['Lab'] ?> • PC <?= $h['PCNumber'] ?: 'Any' ?></td>
                            <td><?= $h['TimeIn'] ?></td>
                            <td><?= $h['TimeOut'] ?: 'LIVE' ?></td>
                            <td><span class="status-badge <?= $hst === 'active' ? 'status-active' : 'status-completed' ?>"><?= $h['Status'] ?></span></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Settings Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="fw-800">Edit Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <?php csrf_input(); ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="small fw-800">First Name</label>
                            <input type="text" name="first_name" class="form-control" value="<?= $student['FirstName'] ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-800">Last Name</label>
                            <input type="text" name="last_name" class="form-control" value="<?= $student['LastName'] ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="small fw-800">Email Address</label>
                            <input type="email" name="email" class="form-control" value="<?= $student['Email'] ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="small fw-800">Home Address</label>
                            <input type="text" name="address" class="form-control" value="<?= $student['Address'] ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="small fw-800">New Password (leave blank to keep current)</label>
                            <input type="password" name="password" class="form-control" placeholder="••••••••">
                        </div>
                        <button type="submit" name="update_profile" class="btn-action w-100 py-3 mt-3">UPDATE PROFILE</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openPhotoModal(){new bootstrap.Modal(document.getElementById('photoModal')).show();}
function uploadPhoto(i){const fd=new FormData(); fd.append('photo',i.files[0]); fd.append('upload_photo','1'); fd.append('csrf_token','<?=get_csrf_token()?>'); fetch('',{method:'POST',body:fd}).then(()=>location.reload());}
function removePhoto(){if(confirm('Delete?')) fetch('',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'remove_photo=1&csrf_token=<?=get_csrf_token()?>'}).then(()=>location.reload());}
document.querySelectorAll('.lab-btn').forEach(b=>b.onclick=function(){document.querySelectorAll('.lab-btn').forEach(x=>x.classList.remove('active')); this.classList.add('active'); document.getElementById('res_lab_input').value=this.dataset.lab; document.getElementById('pcPickerWrap').classList.remove('d-none'); loadPCs(this.dataset.lab, document.querySelector('[name="res_date"]').value);});
function loadPCs(l,d){
    const g=document.getElementById('pcGrid'); 
    g.innerHTML='Loading...'; 
    fetch(`?get_pcs=1&lab=${l}&date=${d}`).then(r=>r.json()).then(data=>{
        g.innerHTML=''; 
        const occupied = data.occupied || [];
        const disabled = data.disabled || {};
        for(let i=1;i<=data.total;i++){
            const div=document.createElement('div');
            const is_occupied = occupied.includes(i);
            const is_disabled = disabled.hasOwnProperty(i);
            
            div.className = 'pc-item' + (is_occupied || is_disabled ? ' occupied' : '');
            div.innerText = i;
            
            if(is_disabled) {
                div.title = 'Condition: ' + disabled[i];
                div.style.cursor = 'help';
            } else if(is_occupied) {
                div.title = 'Status: Currently Booked';
            } else {
                div.onclick=function(){
                    g.querySelectorAll('.pc-item').forEach(p=>p.classList.remove('selected')); 
                    this.classList.add('selected'); 
                    document.getElementById('res_pc_input').value=i;
                };
            }
            g.appendChild(div);
        }
    }); 
}
function openFeedback(id,date){document.getElementById('fb_session_id').value=id; new bootstrap.Modal(document.getElementById('feedbackModal')).show();}
function markRead(){fetch('',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'mark_notifications_read=1&csrf_token=<?=get_csrf_token()?>'});}
document.addEventListener('DOMContentLoaded',()=>{
    const ctx=document.getElementById('usageChart');
    if(ctx){
        new Chart(ctx,{
            type:'doughnut',
            data:{
                labels: <?= json_encode($purp_labels) ?>,
                datasets:[{
                    data: <?= json_encode($purp_counts) ?>,
                    backgroundColor: ['#5c2b7a','#c09412','#198754','#dc3545','#0dcaf0','#6610f2','#fd7e14'],
                    borderWidth: 0
                }]
            },
            options:{
                responsive:true,
                maintainAspectRatio:false,
                cutout:'75%',
                plugins:{
                    legend:{ display: false },
                    tooltip:{
                        callbacks:{
                            label: (item) => ` ${item.label}: ${item.raw} sessions`
                        }
                    }
                }
            }
        });
    }
});
</script>
</body></html>