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

// Check if admin
$chk = $conn->prepare("SELECT is_admin FROM students_info WHERE IdNumber = ?");
$chk->bind_param("s", $_SESSION['user_id']);
$chk->execute();
$chk_row = $chk->get_result()->fetch_assoc();
$chk_close = $chk->close();

if (!$chk_row || !$chk_row['is_admin']) {
    header("Location: student_dashboard.php");
    exit();
}

// ── Handle POST actions ──────────────────────────────────────────────

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die("CSRF token validation failed.");
    }
}

// Update lab PC count
if (isset($_POST['update_lab'])) {
    $lab_id   = (int)$_POST['lab_id'];
    $lab_name = trim($_POST['lab_name']);
    $pc_count = (int)$_POST['pc_count'];
    $desc     = trim($_POST['lab_desc']);
    $s = $conn->prepare("UPDATE labs SET LabName=?, PCCount=?, Description=? WHERE LabID=?");
    $s->bind_param("sisi", $lab_name, $pc_count, $desc, $lab_id);
    $s->execute(); $s->close();
    header("Location: admin_dashboard.php?tab=labs"); exit();
}

// Add new lab
if (isset($_POST['add_lab'])) {
    $name = trim($_POST['lab_name']);
    $cnt  = (int)$_POST['lab_count'];
    $desc = trim($_POST['lab_desc_new']);
    $s = $conn->prepare("INSERT INTO labs (LabName, PCCount, Description) VALUES (?,?,?)");
    $s->bind_param("sis", $name, $cnt, $desc);
    $s->execute(); $s->close();
    header("Location: admin_dashboard.php?tab=labs"); exit();
}

// Delete lab
if (isset($_POST['delete_lab'])) {
    $lab_id = (int)$_POST['lab_id'];
    $s = $conn->prepare("DELETE FROM labs WHERE LabID=?");
    $s->bind_param("i", $lab_id);
    $s->execute(); $s->close();
    header("Location: admin_dashboard.php?tab=labs"); exit();
}

// Post announcement
if (isset($_POST['post_announcement'])) {
    $title   = trim($_POST['ann_title']);
    $message = trim($_POST['ann_message']);
    if ($title && $message) {
        $s = $conn->prepare("INSERT INTO announcements (Title, Message) VALUES (?, ?)");
        $s->bind_param("ss", $title, $message);
        $s->execute(); $s->close();
    }
    header("Location: admin_dashboard.php?tab=announcements"); exit();
}

// Delete announcement
if (isset($_POST['delete_announcement'])) {
    $aid = (int)$_POST['ann_id'];
    $s = $conn->prepare("DELETE FROM announcements WHERE AnnouncementID = ?");
    $s->bind_param("i", $aid);
    $s->execute(); $s->close();
    header("Location: admin_dashboard.php?tab=announcements"); exit();
}

// Edit student
if (isset($_POST['edit_student'])) {
    $sid   = $_POST['edit_id'];
    $fname = $_POST['edit_first'];
    $lname = $_POST['edit_last'];
    $course= $_POST['edit_course'];
    $level = (int)$_POST['edit_level'];
    $s = $conn->prepare("UPDATE students_info SET FirstName=?, LastName=?, Course=?, CourseLevel=? WHERE IdNumber=?");
    $s->bind_param("sssiss", $fname, $lname, $course, $level, $sid);
    $s->execute(); $s->close();
    header("Location: admin_dashboard.php?tab=students"); exit();
}

// Delete student
if (isset($_POST['delete_student'])) {
    $sid = $_POST['delete_id'];
    $s = $conn->prepare("DELETE FROM students_info WHERE IdNumber = ?");
    $s->bind_param("s", $sid);
    $s->execute(); $s->close();
    header("Location: admin_dashboard.php?tab=students"); exit();
}

// Add student manually
if (isset($_POST['add_student_manual'])) {
    $sid   = trim($_POST['id_num']);
    $fn    = trim($_POST['first_name']);
    $ln    = trim($_POST['last_name']);
    $mn    = trim($_POST['middle_name']);
    $em    = trim($_POST['email']);
    $crs   = trim($_POST['course']);
    $lvl   = (int)$_POST['level'];
    $adr   = trim($_POST['address']);
    $pwd   = password_hash($_POST['password'] ?: '123456', PASSWORD_DEFAULT);
    
    $chk = $conn->prepare("SELECT IdNumber FROM students_info WHERE IdNumber = ? OR Email = ?");
    $chk->bind_param("ss", $sid, $em); $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        header("Location: admin_dashboard.php?tab=students&error=exists");
    } else {
        $s = $conn->prepare("INSERT INTO students_info (IdNumber, FirstName, LastName, MiddleName, Email, Course, CourseLevel, Address, Password, is_admin) VALUES (?,?,?,?,?,?,?,?,?,0)");
        $s->bind_param("ssssssiss", $sid, $fn, $ln, $mn, $em, $crs, $lvl, $adr, $pwd);
        $s->execute(); $s->close();
        header("Location: admin_dashboard.php?tab=students&success=added");
    }
    exit();
}

// Add resource
if (isset($_POST['add_resource'])) {
    $lab = trim($_POST['res_lab']);
    $app = trim($_POST['res_app']);
    $cat = trim($_POST['res_cat']);
    $desc = trim($_POST['res_desc']);
    $s = $conn->prepare("INSERT INTO lab_resources (LabName, AppName, Category, Description) VALUES (?,?,?,?)");
    $s->bind_param("ssss", $lab, $app, $cat, $desc);
    $s->execute(); $s->close();
    header("Location: admin_dashboard.php?tab=resources"); exit();
}

// Delete resource
if (isset($_POST['delete_resource'])) {
    $rid = (int)$_POST['res_id'];
    $s = $conn->prepare("DELETE FROM lab_resources WHERE ResourceID = ?");
    $s->bind_param("i", $rid);
    $s->execute(); $s->close();
    header("Location: admin_dashboard.php?tab=resources"); exit();
}

// Upload Resource PDF (Import)
if (isset($_POST['upload_resource_pdf'])) {
    if (isset($_FILES['resource_pdf']) && $_FILES['resource_pdf']['error'] == 0) {
        $file = $_FILES['resource_pdf'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext === 'pdf') {
            $dest = 'resources_docs/master_software_list.pdf';
            move_uploaded_file($file['tmp_name'], $dest);
            header("Location: admin_dashboard.php?tab=resources&pdf=success");
        } else {
            header("Location: admin_dashboard.php?tab=resources&pdf=error");
        }
    }
    exit();
}

// Toggle PC status (Disabled/Enabled)
if (isset($_POST['toggle_pc_status'])) {
    $lab = $_POST['pc_lab'];
    $pc = (int)$_POST['pc_num'];
    $action = $_POST['pc_action']; // 'disable' or 'enable'
    
    if ($action === 'disable') {
        $reason = trim($_POST['pc_reason'] ?? 'Under Maintenance');
        $s = $conn->prepare("INSERT IGNORE INTO disabled_pcs (LabName, PCNumber, Reason) VALUES (?, ?, ?)");
        $s->bind_param("sis", $lab, $pc, $reason);
        $s->execute(); $s->close();
    } else {
        $s = $conn->prepare("DELETE FROM disabled_pcs WHERE LabName=? AND PCNumber=?");
        $s->bind_param("si", $lab, $pc);
        $s->execute(); $s->close();
    }
    header("Location: admin_dashboard.php?tab=labs&managed_lab=" . urlencode($lab)); exit();
}

// Logout student session
if (isset($_POST['logout_session'])) {
    $sess_id = (int)$_POST['session_id'];
    $s = $conn->prepare("UPDATE sit_in_sessions SET Status='Completed', TimeOut=NOW() WHERE SessionID=?");
    $s->bind_param("i", $sess_id);
    $s->execute(); $s->close();
    header("Location: admin_dashboard.php?tab=sitin"); exit();
}

// Sit-in a student
if (isset($_POST['do_sitin'])) {
    $sid     = trim($_POST['sitin_id']);
    $purpose = trim($_POST['sitin_purpose']);
    $lab     = trim($_POST['sitin_lab']);
    $pc      = !empty($_POST['sitin_pc']) ? trim($_POST['sitin_pc']) : NULL;
    $date    = date('Y-m-d');
    $timein  = date('H:i:s');
    $chk2 = $conn->prepare("SELECT SessionID FROM sit_in_sessions WHERE StudentID=? AND Status='Active'");
    $chk2->bind_param("s", $sid);
    $chk2->execute();
    if ($chk2->get_result()->num_rows > 0) {
        $sitin_error = "Student already has an active session.";
    } else {
        $ins = $conn->prepare("INSERT INTO sit_in_sessions (StudentID, SessionDate, TimeIn, Purpose, Lab, PCNumber, Status) VALUES (?,?,?,?,?,?,'Active')");
        $ins->bind_param("ssssss", $sid, $date, $timein, $purpose, $lab, $pc);
        $ins->execute(); $ins->close();
        header("Location: admin_dashboard.php?tab=sitin"); exit();
    }
    $chk2->close();
}

// Search student via AJAX
if (isset($_GET['search_student'])) {
    $q = '%' . trim($_GET['search_student']) . '%';
    $s = $conn->prepare("SELECT IdNumber, FirstName, LastName, Course, CourseLevel FROM students_info WHERE is_admin=0 AND (IdNumber LIKE ? OR FirstName LIKE ? OR LastName LIKE ?) LIMIT 10");
    $s->bind_param("sss", $q, $q, $q);
    $s->execute();
    $rows = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();
    header('Content-Type: application/json');
    echo json_encode($rows);
    exit();
}

// Approve reservation
if (isset($_POST['approve_reservation'])) {
    $rid = (int)$_POST['res_id'];
    $r_stmt = $conn->prepare("SELECT StudentID, SessionDate, Lab, Purpose FROM sit_in_sessions WHERE SessionID=? AND Type='Reservation'");
    $r_stmt->bind_param("i", $rid);
    $r_stmt->execute();
    $r_row = $r_stmt->get_result()->fetch_assoc();
    $r_stmt->close();
    if ($r_row) {
        $s = $conn->prepare("UPDATE sit_in_sessions SET Status='Approved' WHERE SessionID=? AND Type='Reservation'");
        $s->bind_param("i", $rid);
        $s->execute(); $s->close();
        $msg = "Your reservation for " . $r_row['Purpose'] . " at Lab " . $r_row['Lab'] . " on " . $r_row['SessionDate'] . " has been approved.";
        $n = $conn->prepare("INSERT INTO notifications (StudentID, Message) VALUES (?,?)");
        $n->bind_param("ss", $r_row['StudentID'], $msg);
        $n->execute(); $n->close();
    }
    header("Location: admin_dashboard.php?tab=reservations"); exit();
}

// Reject reservation
if (isset($_POST['reject_reservation'])) {
    $rid = (int)$_POST['res_id'];
    $reason = trim($_POST['cancel_reason'] ?? 'Your reservation has been rejected/cancelled by the admin.');
    
    $r_stmt = $conn->prepare("SELECT StudentID, SessionDate, Lab, Purpose FROM sit_in_sessions WHERE SessionID=? AND Type='Reservation'");
    $r_stmt->bind_param("i", $rid);
    $r_stmt->execute();
    $r_row = $r_stmt->get_result()->fetch_assoc();
    $r_stmt->close();
    
    if ($r_row) {
        $s = $conn->prepare("UPDATE sit_in_sessions SET Status='Cancelled' WHERE SessionID=? AND Type='Reservation'");
        $s->bind_param("i", $rid);
        $s->execute(); $s->close();
        
        $msg = "Reservation Update: " . $reason . " (Lab " . $r_row['Lab'] . " on " . $r_row['SessionDate'] . ")";
        $n = $conn->prepare("INSERT INTO notifications (StudentID, Message) VALUES (?,?)");
        $n->bind_param("ss", $r_row['StudentID'], $msg);
        $n->execute(); $n->close();
    }
    header("Location: admin_dashboard.php?tab=reservations"); exit();
}

// Reset all sessions
if (isset($_POST['reset_all_sessions'])) {
    $conn->query("UPDATE sit_in_sessions SET Status='Completed', TimeOut=NOW() WHERE Status='Active' AND SessionDate=CURDATE()");
    header("Location: admin_dashboard.php?tab=sitin"); exit();
}

// ── Stats ────────────────────────────────────────────────────────────
$total_students  = $conn->query("SELECT COUNT(*) as c FROM students_info WHERE is_admin=0")->fetch_assoc()['c'];
$currently_sitin = $conn->query("SELECT COUNT(*) as c FROM sit_in_sessions WHERE Status='Active' AND SessionDate=CURDATE()")->fetch_assoc()['c'];
$total_sitin     = $conn->query("SELECT COUNT(*) as c FROM sit_in_sessions")->fetch_assoc()['c'];
$pending_res     = $conn->query("SELECT COUNT(*) as c FROM sit_in_sessions WHERE Type='Reservation' AND Status='Pending'")->fetch_assoc()['c'];

$active_tab = $_GET['tab'] ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | CCS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

        :root {
            --primary-purple: #5c2b7a;
            --purple-light: #7b3da3;
            --purple-soft: #f3eaf9;
            --gold: #c09412;
            --gold-light: #d4a72c;
            --bg-body: #f8f9fa;
            --bg-card: rgba(255, 255, 255, 0.7);
            --border-card: rgba(255, 255, 255, 0.4);
            --text-main: #2d3436;
            --text-dim: #636e72;
            --card-radius: 24px;
            --shadow: 0 8px 32px 0 rgba(92, 43, 122, 0.08);
        }

        body {
            background-color: var(--bg-body);
            background-image: 
                radial-gradient(at 0% 0%, rgba(92, 43, 122, 0.05) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(192, 148, 18, 0.03) 0px, transparent 50%);
            font-family: 'Inter', sans-serif;
            color: var(--text-main);
            margin: 0;
            min-height: 100vh;
            display: flex;
        }

        /* ── Dock Sidebar ── */
        .dock-sidebar {
            width: 80px; background: var(--primary-purple); height: 100vh; position: fixed; left: 0; top: 0;
            display: flex; flex-direction: column; align-items: center; padding: 30px 0; z-index: 1000;
            box-shadow: 10px 0 30px rgba(0,0,0,0.05); transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .dock-sidebar:hover { width: 240px; }
        .dock-logo { margin-bottom: 50px; transition: 0.3s; }
        .dock-logo img { width: 45px; filter: drop-shadow(0 4px 8px rgba(0,0,0,0.2)); }
        .dock-nav { flex: 1; width: 100%; display: flex; flex-direction: column; gap: 12px; padding: 0 12px; }
        .dock-link {
            width: 100%; height: 50px; display: flex; align-items: center; text-decoration: none;
            color: rgba(255,255,255,0.7); border-radius: 14px; transition: 0.2s; overflow: hidden; white-space: nowrap;
        }
        .dock-link i { min-width: 56px; text-align: center; font-size: 1.25rem; }
        .dock-link span { font-weight: 600; font-size: 0.9rem; opacity: 0; transform: translateX(-10px); transition: 0.3s; }
        .dock-sidebar:hover .dock-link span { opacity: 1; transform: translateX(0); }
        .dock-link:hover { background: rgba(255,255,255,0.1); color: white; }
        .dock-link.active { background: white; color: var(--primary-purple); box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .dock-link.active i { color: var(--gold); }

        /* ── Main Layout ── */
        .main-wrapper { margin-left: 80px; flex: 1; padding: 40px; transition: 0.3s; }
        .header-section { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 40px; }
        .header-section h1 { font-weight: 800; font-size: 2.2rem; letter-spacing: -1px; margin: 0; color: var(--primary-purple); }

        /* ── Bento Grid ── */
        .bento-grid { display: grid; grid-template-columns: repeat(4, 1fr); grid-auto-rows: minmax(160px, auto); gap: 24px; }
        .bento-card {
            background: var(--bg-card); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--border-card); border-radius: var(--card-radius); padding: 24px;
            transition: 0.3s ease; display: flex; flex-direction: column; position: relative; overflow: hidden; box-shadow: var(--shadow);
        }
        .bento-card:hover { transform: translateY(-5px); box-shadow: 0 15px 40px rgba(92, 43, 122, 0.12); }

        .tile-large { grid-column: span 2; grid-row: span 2; }
        .tile-wide { grid-column: span 4; }
        .tile-medium { grid-column: span 2; }
        .tile-small { grid-column: span 1; }

        .card-title { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 1.2px; color: var(--text-dim); font-weight: 800; margin-bottom: 18px; display: flex; align-items: center; gap: 8px; }
        .card-title i { color: var(--gold); }

        .vital-sign { display: flex; flex-direction: column; justify-content: center; height: 100%; }
        .vital-value { font-size: 2.5rem; font-weight: 900; line-height: 1; margin-bottom: 5px; color: var(--primary-purple); }
        .vital-label { color: var(--text-dim); font-weight: 700; font-size: 0.72rem; text-transform: uppercase; }

        .glass-table-container { flex: 1; overflow: auto; }
        .glass-table { width: 100%; border-collapse: separate; border-spacing: 0 8px; }
        .glass-table th { text-align: left; font-size: 0.65rem; text-transform: uppercase; color: var(--text-dim); padding: 0 15px; font-weight: 800; }
        .glass-table tr { background: rgba(255, 255, 255, 0.4); transition: 0.2s; }
        .glass-table tr:hover { background: var(--purple-soft); }
        .glass-table td { padding: 12px 15px; font-weight: 600; font-size: 0.85rem; border-top: 1px solid rgba(0,0,0,0.02); border-bottom: 1px solid rgba(0,0,0,0.02); }
        .glass-table td:first-child { border-left: 1px solid rgba(0,0,0,0.02); border-radius: 12px 0 0 12px; }
        .glass-table td:last-child { border-right: 1px solid rgba(0,0,0,0.02); border-radius: 0 12px 12px 0; }

        .status-badge { padding: 5px 12px; border-radius: 100px; font-size: 0.6rem; font-weight: 800; text-transform: uppercase; }
        .status-active { background: #e3fcef; color: #00a854; }
        .status-completed { background: #f4f5f7; color: #5e6c84; }

        .lab-btn { border: 2px solid #eee; border-radius: 12px; background: white; padding: 10px 18px; font-weight: 800; font-size: 0.9rem; color: var(--text-main); transition: 0.2s; }
        .lab-btn.active { background: var(--primary-purple); color: white; border-color: var(--primary-purple); }
        .pc-item { width: 50px; height: 50px; border-radius: 15px; background: #e3fcef; color: #00a854; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 1.1rem; cursor: pointer; transition: all 0.2s ease; border: 2px solid transparent; user-select: none; }
        .pc-item:hover:not(.occupied) { transform: scale(1.1); background: var(--purple-soft); border-color: var(--primary-purple); }
        .pc-item.occupied { background: #fee2e2; color: #b91c1c; cursor: help; }
        .pc-item.selected { border: 3px solid var(--primary-purple); background: white; color: var(--primary-purple); box-shadow: 0 4px 15px rgba(92, 43, 122, 0.2); transform: scale(1.1); }

        .btn-action { background: var(--primary-purple); border: none; color: white; padding: 10px 18px; border-radius: 12px; font-weight: 700; font-size: 0.78rem; transition: 0.3s; }
        .btn-action:hover { transform: translateY(-2px); background: var(--purple-light); }
        .search-bar { background: #f1f3f5; border: 2px solid transparent; border-radius: 14px; padding: 10px 15px 10px 40px; color: var(--text-main); font-size: 0.88rem; width: 100%; font-weight: 600; }
        .search-bar:focus { outline: none; border-color: var(--purple-soft); background: white; }

        .chart-container { position: relative; height: 200px; width: 100%; }
        .lab-item { display: flex; align-items: center; gap: 12px; padding: 12px; border-radius: 16px; background: rgba(255, 255, 255, 0.3); margin-bottom: 10px; border: 1px solid rgba(0,0,0,0.02); }
        .lab-avatar { width: 38px; height: 38px; border-radius: 10px; background: var(--primary-purple); color: white; display: flex; align-items: center; justify-content: center; font-weight: 800; }

        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-thumb { background: #e0e0e0; border-radius: 10px; }
    </style>
</head>
<body>

<aside class="dock-sidebar">
    <div class="dock-logo"><img src="UCLogo-removebg-preview.png" alt="UC"></div>
    <nav class="dock-nav">
        <a href="admin_dashboard.php?tab=dashboard" class="dock-link <?= $active_tab==='dashboard'?'active':'' ?>"><i class="fa-solid fa-house"></i><span>Dashboard</span></a>
        <a href="admin_dashboard.php?tab=students" class="dock-link <?= $active_tab==='students'?'active':'' ?>"><i class="fa-solid fa-users"></i><span>Students</span></a>
        <a href="admin_dashboard.php?tab=sitin" class="dock-link <?= $active_tab==='sitin'?'active':'' ?>"><i class="fa-solid fa-microchip"></i><span>Live Monitor</span></a>
        <a href="admin_dashboard.php?tab=sitinform" class="dock-link <?= $active_tab==='sitinform'?'active':'' ?>"><i class="fa-solid fa-plus-circle"></i><span>New Entry</span></a>
        <a href="admin_dashboard.php?tab=reservations" class="dock-link <?= $active_tab==='reservations'?'active':'' ?>"><i class="fa-solid fa-calendar-check"></i><span>Reservations</span></a>
        <a href="admin_dashboard.php?tab=announcements" class="dock-link <?= $active_tab==='announcements'?'active':'' ?>"><i class="fa-solid fa-bullhorn"></i><span>Announcements</span></a>
        <a href="admin_dashboard.php?tab=records" class="dock-link <?= $active_tab==='records'?'active':'' ?>"><i class="fa-solid fa-history"></i><span>History</span></a>
        <a href="admin_dashboard.php?tab=leaderboard" class="dock-link <?= $active_tab==='leaderboard'?'active':'' ?>"><i class="fa-solid fa-trophy"></i><span>Leaderboard</span></a>
        <a href="admin_dashboard.php?tab=labs" class="dock-link <?= $active_tab==='labs'?'active':'' ?>"><i class="fa-solid fa-flask"></i><span>Laboratories</span></a>
        <a href="admin_dashboard.php?tab=resources" class="dock-link <?= $active_tab==='resources'?'active':'' ?>"><i class="fa-solid fa-layer-group"></i><span>Resources</span></a>
    </nav>
    <form method="POST" action="logout.php" class="w-100 px-2"><?php csrf_input(); ?><button class="dock-link border-0 bg-transparent w-100 text-danger"><i class="fa-solid fa-power-off"></i><span>Logout</span></button></form>
</aside>

<main class="main-wrapper">
    <div class="header-section">
        <div>
            <div class="vital-label mb-1">ADMIN ACCOUNT</div>
            <h1>
                <?php
                $titles = ['dashboard'=>'Overview','students'=>'Student List','sitin'=>'Live Monitor','sitinform'=>'Add Sit-in','reservations'=>'Reservations','announcements'=>'Announcements','records'=>'History','leaderboard'=>'Leaderboard','labs'=>'Labs','resources'=>'Lab Resources'];
                echo $titles[$active_tab] ?? 'Dashboard';
                ?>
            </h1>
        </div>
        <div class="text-end">
            <div class="fw-800 small"><?= date('l, M d') ?></div>
            <div class="small text-dim"><?= date('h:i A') ?></div>
        </div>
    </div>

    <?php if ($active_tab === 'dashboard'): ?>
    <div class="bento-grid">
        <div class="bento-card tile-small"><div class="card-title">Registered</div><div class="vital-sign"><div class="vital-value"><?= $total_students ?></div><div class="vital-label">Students</div></div></div>
        <div class="bento-card tile-small"><div class="card-title">Active</div><div class="vital-sign"><div class="vital-value text-success"><?= $currently_sitin ?></div><div class="vital-label">Sit-ins</div></div></div>
        <div class="bento-card tile-small"><div class="card-title">Pending</div><div class="vital-sign"><div class="vital-value text-gold"><?= $pending_res ?></div><div class="vital-label">Bookings</div></div></div>
        <div class="bento-card tile-small"><div class="card-title">History</div><div class="vital-sign"><div class="vital-value"><?= $total_sitin ?></div><div class="vital-label">Total Logs</div></div></div>

        <div class="bento-card tile-large">
            <div class="card-title">Current Sit-ins</div>
            <div class="glass-table-container">
                <?php
                $live = $conn->query("SELECT s.*, st.FirstName, st.LastName FROM sit_in_sessions s JOIN students_info st ON s.StudentID = st.IdNumber WHERE s.Status = 'Active' ORDER BY s.TimeIn DESC LIMIT 6");
                if ($live->num_rows > 0): while($l = $live->fetch_assoc()):
                ?>
                <div class="lab-item"><div class="lab-avatar"><?= substr($l['FirstName'],0,1) ?></div><div class="flex-fill"><div class="fw-800 small"><?= $l['FirstName'].' '.$l['LastName'] ?></div><div class="text-dim small fw-600">Lab <?= $l['Lab'] ?> • PC <?= $l['PCNumber']?:'—' ?></div></div><div class="text-end small fw-800 text-gold"><?= date('h:i A',strtotime($l['TimeIn'])) ?></div></div>
                <?php endwhile; else: echo '<div class="h-100 d-flex align-items-center justify-content-center text-dim small">No active students.</div>'; endif; ?>
            </div>
        </div>

        <div class="bento-card tile-medium">
            <div class="card-title">Usage Analysis</div>
            <div class="chart-container"><canvas id="usageChart"></canvas></div>
            <?php $purpose_q = $conn->query("SELECT Purpose, COUNT(*) as count FROM sit_in_sessions WHERE Purpose!='' GROUP BY Purpose ORDER BY count DESC LIMIT 5"); $purposes=[]; $counts=[]; while($p=$purpose_q->fetch_assoc()){$purposes[]=$p['Purpose'];$counts[]=$p['count'];} ?>
        </div>

        <div class="bento-card tile-wide">
            <div class="card-title d-flex justify-content-between"><span>Recent Activity</span><a href="?tab=records" class="text-dim small fw-700">View All →</a></div>
            <div class="glass-table-container">
                <table class="glass-table">
                    <thead><tr><th>Student</th><th>Lab & PC</th><th>Purpose</th><th>Time In</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php $recent = $conn->query("SELECT s.*, st.FirstName, st.LastName FROM sit_in_sessions s JOIN students_info st ON s.StudentID = st.IdNumber ORDER BY s.SessionID DESC LIMIT 5"); while($r=$recent->fetch_assoc()): ?>
                        <tr><td><div class="fw-800"><?= $r['FirstName'].' '.$r['LastName'] ?></div><div class="text-dim small fw-600"><?= $r['StudentID'] ?></div></td><td>Lab <?= $r['Lab'] ?> • PC <?= $r['PCNumber']?:'—' ?></td><td><?= $r['Purpose'] ?></td><td><?= date('h:i A',strtotime($r['TimeIn'])) ?></td><td><span class="status-badge <?= strtolower($r['Status'])==='active'?'status-active':'status-completed' ?>"><?= $r['Status'] ?></span></td></tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php elseif ($active_tab === 'students'): ?>
    <div class="bento-card tile-wide">
        <div class="card-title d-flex justify-content-between">
            <span>Student List</span>
            <div class="d-flex gap-2 w-50 justify-content-end">
                <input type="text" id="studentSearch" class="search-bar" placeholder="Search students..." style="max-width: 250px;">
                <button class="btn-action" data-bs-toggle="modal" data-bs-target="#addStudentManualModal"><i class="fa fa-plus me-1"></i> Add Student</button>
            </div>
        </div>
        
        <?php if(isset($_GET['success'])): ?>
            <div class="alert alert-success py-2 small fw-600 animate__animated animate__fadeIn">Student successfully added to the system.</div>
        <?php endif; ?>
        <?php if(isset($_GET['error'])): ?>
            <div class="alert alert-danger py-2 small fw-600 animate__animated animate__fadeIn">Error: ID Number already exists in the system.</div>
        <?php endif; ?>

        <div class="glass-table-container">
            <table class="glass-table" id="studentTable">
                <thead><tr><th>Student</th><th>Course</th><th>Logs</th><th>Progress</th><th class="text-end">Action</th></tr></thead>
                <tbody>
                    <?php $stu=$conn->query("SELECT si.*,30 as MaxCredits,COUNT(s.SessionID) as Used FROM students_info si LEFT JOIN sit_in_sessions s ON s.StudentID=si.IdNumber AND (s.Type='Sit-in' OR s.Type IS NULL) WHERE si.is_admin=0 GROUP BY si.IdNumber ORDER BY si.LastName");
                    while($s=$stu->fetch_assoc()): $rem=max(0,30-$s['Used']); $pct=round(($rem/30)*100); $clr=$rem>15?'#2ecc71':($rem>5?'#c09412':'#e74c3c'); ?>
                    <tr><td><div class="fw-800"><?= $s['LastName'].', '.$s['FirstName'] ?></div><div class="text-dim small fw-600"><?= $s['IdNumber'] ?></div></td><td><?= $s['Course'] ?> L<?= $s['CourseLevel'] ?></td><td><?= $s['Used'] ?>/30</td><td><div style="width:100px; height:4px; background:rgba(0,0,0,0.05); border-radius:10px;"><div style="width:<?= $pct ?>%; height:100%; background:<?= $clr ?>; border-radius:10px;"></div></div></td><td class="text-end"><button class="btn-action py-1 px-3" onclick="openEdit('<?= $s['IdNumber'] ?>','<?= $s['FirstName'] ?>','<?= $s['LastName'] ?>','<?= $s['Course'] ?>','<?= $s['CourseLevel'] ?>')">Edit</button></td></tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php elseif ($active_tab === 'sitin'): ?>
    <div class="bento-card tile-wide">
        <div class="card-title d-flex justify-content-between"><span>Live Lab Monitor</span><form method="POST"><?php csrf_input(); ?><button name="reset_all_sessions" class="btn-action bg-danger">End All</button></form></div>
        <div class="glass-table-container">
            <table class="glass-table">
                <thead><tr><th>Student</th><th>Lab & PC</th><th>Purpose</th><th>Start Time</th><th class="text-end">Action</th></tr></thead>
                <tbody>
                    <?php $sit=$conn->query("SELECT s.*, st.FirstName, st.LastName FROM sit_in_sessions s JOIN students_info st ON s.StudentID=st.IdNumber WHERE s.Status='Active' ORDER BY s.TimeIn DESC");
                    while($r=$sit->fetch_assoc()): ?>
                    <tr><td><div class="fw-800"><?= $r['FirstName'].' '.$r['LastName'] ?></div><div class="text-dim small fw-600"><?= $r['StudentID'] ?></div></td><td>Lab <?= $r['Lab'] ?> • PC <?= $r['PCNumber']?:'—' ?></td><td><?= $r['Purpose'] ?></td><td><?= date('h:i A',strtotime($r['TimeIn'])) ?></td><td class="text-end"><form method="POST"><?php csrf_input(); ?><input type="hidden" name="session_id" value="<?= $r['SessionID'] ?>"><button name="logout_session" class="btn-action bg-danger py-1 px-3">Logout</button></form></td></tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php elseif ($active_tab === 'sitinform'): ?>
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="bento-card">
                <div class="card-title">Add Sit-in Entry</div>
                <input type="text" id="studentLookup" class="search-bar mb-3" placeholder="Search student name or ID...">
                <div id="searchResults" style="display:none;" class="mb-3"><div id="resultsList" class="d-flex flex-column gap-2" style="max-height: 200px; overflow-y: auto;"></div></div>
                
                <form method="POST" id="sitinForm" style="display:none;">
                    <?php csrf_input(); ?>
                    <input type="hidden" name="sitin_id" id="sitin_id">
                    <input type="hidden" name="sitin_date" id="sitin_date" value="<?= date('Y-m-d') ?>">
                    
                    <div class="p-3 rounded-4 mb-4 d-flex justify-content-between align-items-center" style="background:var(--purple-soft);">
                        <div>
                            <div class="vital-label">Selected Student</div>
                            <div id="display_name" class="fw-800 fs-5" style="color: var(--primary-purple);"></div>
                            <div id="display_id" class="text-gold small fw-800"></div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-bold" onclick="document.getElementById('sitinForm').style.display='none'; document.getElementById('studentLookup').value='';">Change</button>
                    </div>

                    <div class="row g-4">
                        <div class="col-md-12">
                            <label class="small fw-800 mb-2">Purpose</label>
                            <select name="sitin_purpose" class="form-select w-50">
                                <option>C Programming</option>
                                <option>Java Programming</option>
                                <option>Python Programming</option>
                                <option>C++ Programming</option>
                                <option>Web Development</option>
                                <option>Database Management</option>
                                <option>Networking</option>
                                <option>Mobile Development</option>
                                <option>Cybersecurity</option>
                                <option>Data Science</option>
                                <option>Research</option>
                                <option>Examination</option>
                                <option>Assignment</option>
                                <option>Others</option>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <label class="small fw-800 mb-2">Laboratory</label>
                            <div class="d-flex gap-2 flex-wrap">
                                <?php $lbq=$conn->query("SELECT * FROM labs"); while($lb=$lbq->fetch_assoc()): ?>
                                    <button type="button" class="lab-btn" data-lab="<?= $lb['LabName'] ?>" data-total="<?= $lb['PCCount'] ?>">Lab <?= $lb['LabName'] ?></button>
                                <?php endwhile; ?>
                            </div>
                            <input type="hidden" name="sitin_lab" id="sitin_lab_input" required>
                            <input type="hidden" name="sitin_pc" id="sitin_pc_input">
                        </div>

                        <div id="labInfoArea" class="col-12 d-none">
                            <div class="small fw-800 mb-2">Select PC Number</div>
                            <div id="pcGrid" style="display:grid; grid-template-columns:repeat(auto-fill,minmax(50px,1fr)); gap:8px;"></div>
                        </div>

                        <div class="col-12 mt-4">
                            <button type="submit" name="do_sitin" class="btn-action w-100 py-3 fs-6">START SESSION</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php elseif ($active_tab === 'reservations'): ?>
    <div class="bento-card tile-wide">
        <div class="card-title">Pending Reservations</div>
        <div class="glass-table-container">
            <table class="glass-table">
                <thead><tr><th>Date</th><th>Student</th><th>Lab & PC</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                <tbody>
                    <?php $rv=$conn->query("SELECT s.*, st.FirstName, st.LastName FROM sit_in_sessions s JOIN students_info st ON s.StudentID=st.IdNumber WHERE s.Type='Reservation' ORDER BY SessionDate ASC");
                    while($r=$rv->fetch_assoc()): $st=strtolower($r['Status']); ?>
                    <tr>
                        <td><?= $r['SessionDate'] ?></td>
                        <td><?= $r['FirstName'].' '.$r['LastName'] ?></td>
                        <td>Lab <?= $r['Lab'] ?> • PC <?= $r['PCNumber']?:'Any' ?></td>
                        <td><span class="status-badge <?= ($st==='pending'?'bg-warning text-dark':($st==='approved'?'bg-success text-white':'bg-secondary text-white')) ?>"><?= $r['Status'] ?></span></td>
                        <td class="text-end">
                            <?php if($st==='pending'): ?>
                                <button class="btn-action py-1 px-2 me-1 bg-success" onclick="approveRes(<?= $r['SessionID'] ?>)">Approve</button>
                                <button class="btn-action bg-danger py-1 px-2" onclick="openReject(<?= $r['SessionID'] ?>)">Reject</button>
                            <?php elseif($st==='approved'): ?>
                                <button class="btn-action bg-danger py-1 px-2" onclick="openReject(<?= $r['SessionID'] ?>)">Cancel Approval</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php elseif ($active_tab === 'announcements'): ?>
    <div class="row g-4"><div class="col-md-4"><div class="bento-card"><div class="card-title">Post Announcement</div><form method="POST"><?php csrf_input(); ?><div class="mb-2"><label class="small fw-800">Title</label><input type="text" name="ann_title" class="form-control" required></div><div class="mb-3"><label class="small fw-800">Message</label><textarea name="ann_message" class="form-control" rows="4" required></textarea></div><button type="submit" name="post_announcement" class="btn-action w-100 py-3">POST NOW</button></form></div></div><div class="col-md-8"><div class="bento-card h-100"><div class="card-title">Past Announcements</div><div class="d-flex flex-column gap-3 overflow-auto" style="max-height: 400px;"><?php $an=$conn->query("SELECT * FROM announcements ORDER BY DatePosted DESC"); while($a=$an->fetch_assoc()): ?><div class="p-3 rounded-4 bg-light"><div class="d-flex justify-content-between"><strong><?= $a['Title'] ?></strong><form method="POST"><?php csrf_input(); ?><input type="hidden" name="ann_id" value="<?= $a['AnnouncementID'] ?>"><button name="delete_announcement" class="btn text-danger py-0"><i class="fa fa-trash"></i></button></form></div><p class="small mb-1"><?= $a['Message'] ?></p><div class="small opacity-50"><?= $a['DatePosted'] ?></div></div><?php endwhile; ?></div></div></div></div>

    <?php elseif ($active_tab === 'records'): ?>
    <div class="bento-card tile-wide">
        <div class="card-title d-flex justify-content-between align-items-center">
            <span>Sit-in History</span>
            <div class="d-flex gap-2">
                <a href="export.php?type=csv" class="btn btn-sm btn-outline-secondary fw-700" target="_blank"><i class="fa fa-file-csv me-1"></i> CSV</a>
                <a href="export.php?type=xls" class="btn btn-sm btn-outline-secondary fw-700" target="_blank"><i class="fa fa-file-excel me-1"></i> Excel</a>
                <a href="export.php?type=pdf" class="btn btn-sm btn-outline-secondary fw-700" target="_blank"><i class="fa fa-file-pdf me-1"></i> PDF</a>
            </div>
        </div>
        <div class="glass-table-container">
            <table class="glass-table">
                <thead><tr><th>Date</th><th>Student</th><th>Lab & PC</th><th>Purpose</th><th>Time Range</th><th>Status</th></tr></thead>
                <tbody>
                    <?php $rc=$conn->query("SELECT s.*,st.FirstName,st.LastName FROM sit_in_sessions s JOIN students_info st ON s.StudentID=st.IdNumber ORDER BY SessionDate DESC LIMIT 50"); while($r=$rc->fetch_assoc()): ?>
                    <tr><td><?= $r['SessionDate'] ?></td><td><?= $r['FirstName'].' '.$r['LastName'] ?></td><td>Lab <?= $r['Lab'] ?> • PC <?= $r['PCNumber']?:'—' ?></td><td><?= $r['Purpose'] ?></td><td><?= substr($r['TimeIn'],0,5) ?> - <?= $r['TimeOut']?substr($r['TimeOut'],0,5):'LIVE' ?></td><td><span class="status-badge <?= strtolower($r['Status'])==='active'?'status-active':'status-completed' ?>"><?= $r['Status'] ?></span></td></tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php elseif ($active_tab === 'leaderboard'): ?>
    <div class="bento-card tile-wide">
        <div class="card-title">Sit-in Leaderboard (Top 50)</div>
        <div class="p-3">
            <p class="text-dim small mb-4 fw-600">Overview of student engagement across all laboratories. Rankings are calculated based on total cumulative lab hours and successful task completion.</p>
            <?php
            $leaderboard = getLeaderboardData($conn, 50);
            displayLeaderboard($leaderboard, true);
            ?>
        </div>
    </div>

    <?php elseif ($active_tab === 'labs'): ?>
    <div class="bento-card tile-wide mb-4">
        <div class="card-title d-flex justify-content-between"><span>Laboratory Management</span><button class="btn-action" data-bs-toggle="modal" data-bs-target="#addLabModal">Add New Lab</button></div>
        <div class="glass-table-container">
            <table class="glass-table">
                <thead><tr><th>Lab Name</th><th>Total PCs</th><th>Location / Notes</th><th class="text-end">Action</th></tr></thead>
                <tbody>
                    <?php $lb=$conn->query("SELECT * FROM labs ORDER BY LabName"); while($l=$lb->fetch_assoc()): ?>
                    <tr>
                        <td><strong>Lab <?= $l['LabName'] ?></strong></td>
                        <td><?= $l['PCCount'] ?></td>
                        <td><?= $l['Description'] ?></td>
                        <td class="text-end">
                            <a href="admin_dashboard.php?tab=labs&manage_lab=<?= urlencode($l['LabName']) ?>&pcs=<?= $l['PCCount'] ?>" class="btn-action py-1 px-3 me-2" style="text-decoration:none;">Manage PCs</a>
                            <button class="btn-action py-1 px-3" onclick="toggleEdit(<?= $l['LabID'] ?>)">Edit</button>
                            <form method="POST" class="d-inline"><?php csrf_input(); ?><input type="hidden" name="lab_id" value="<?= $l['LabID'] ?>"><button name="delete_lab" class="btn text-danger ms-2"><i class="fa fa-trash"></i></button></form>
                            <div id="editLab_<?= $l['LabID'] ?>" style="display:none;" class="mt-2 text-start p-3 bg-white rounded-4 border">
                                <form method="POST"><?php csrf_input(); ?><input type="hidden" name="lab_id" value="<?= $l['LabID'] ?>"><div class="row g-2"><div class="col-8"><input type="text" name="lab_name" class="form-control" value="<?= $l['LabName'] ?>"></div><div class="col-4"><input type="number" name="pc_count" class="form-control" value="<?= $l['PCCount'] ?>"></div><div class="col-12"><textarea name="lab_desc" class="form-control mt-1"><?= $l['Description'] ?></textarea></div><button type="submit" name="update_lab" class="btn-action w-100 mt-2">Save</button></div></form>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if (isset($_GET['manage_lab'])): 
        $mlab = $_GET['manage_lab'];
        $mcount = (int)$_GET['pcs'];
        $dis = []; $res = $conn->query("SELECT PCNumber, Reason FROM disabled_pcs WHERE LabName='$mlab'");
        while($d=$res->fetch_assoc()) $dis[$d['PCNumber']] = $d['Reason'];
    ?>
    <div class="bento-card tile-wide">
        <div class="card-title d-flex justify-content-between">
            <span>Managing PCs for Lab <?= htmlspecialchars($mlab) ?></span>
            <a href="admin_dashboard.php?tab=labs" class="btn btn-sm btn-outline-secondary">Close</a>
        </div>
        <div class="alert alert-info py-2 small fw-600">Click any computer card to toggle its availability. Disabled PCs will be hidden from students.</div>
        <div class="d-flex flex-wrap gap-3">
            <?php for($i=1; $i<=$mcount; $i++): $is_dis = isset($dis[$i]); ?>
                <div class="pc-status-card <?= $is_dis?'disabled':'available' ?>" onclick="togglePC('<?= $mlab ?>', <?= $i ?>, '<?= $is_dis?'enable':'disable' ?>')">
                    <div class="pc-card-icon">
                        <i class="fa fa-desktop"></i>
                    </div>
                    <div class="pc-card-info">
                        <div class="pc-card-num">PC <?= str_pad($i, 2, '0', STR_PAD_LEFT) ?></div>
                        <div class="pc-card-status-badge"><?= $is_dis?'OFF':'ON' ?></div>
                    </div>
                    <?php if($is_dis): ?>
                        <div class="pc-card-reason" title="<?= htmlspecialchars($dis[$i]) ?>"><?= htmlspecialchars($dis[$i]) ?></div>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
        </div>
    </div>
    
    <div class="modal fade" id="pcToggleModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content"><div class="modal-header py-3"><h6 class="mb-0 fw-800" id="pcModalTitle">Disable PC</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form method="POST"><?php csrf_input(); ?><input type="hidden" name="pc_lab" id="modal_pc_lab"><input type="hidden" name="pc_num" id="modal_pc_num"><input type="hidden" name="pc_action" id="modal_pc_action"><div class="modal-body py-3"><div id="disableOptions"><label class="small fw-800 mb-1">Reason for disabling</label><input type="text" name="pc_reason" class="form-control form-control-sm" value="Under Maintenance" required></div><div id="enableText" class="fw-600 small text-center">Are you sure you want to enable this PC?</div><button type="submit" name="toggle_pc_status" class="btn-action w-100 mt-3 py-2" id="pcModalBtn">Confirm</button></div></form></div></div></div>
    <script>
    function togglePC(lab, num, action) {
        document.getElementById('modal_pc_lab').value = lab;
        document.getElementById('modal_pc_num').value = num;
        document.getElementById('modal_pc_action').value = action;
        document.getElementById('pcModalTitle').innerText = (action === 'disable' ? 'Disable' : 'Enable') + ' PC #' + num;
        document.getElementById('pcModalBtn').className = 'btn-action w-100 mt-3 py-2 ' + (action === 'disable' ? 'bg-danger' : 'bg-success');
        document.getElementById('disableOptions').style.display = action === 'disable' ? 'block' : 'none';
        document.getElementById('enableText').style.display = action === 'enable' ? 'block' : 'none';
        new bootstrap.Modal(document.getElementById('pcToggleModal')).show();
    }
    </script>
    <style>
        .pc-status-card {
            width: 120px; background: white; border-radius: 20px; padding: 15px; text-align: center;
            cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); border: 1px solid rgba(0,0,0,0.05);
            box-shadow: 0 4px 12px rgba(0,0,0,0.03); display: flex; flex-direction: column; align-items: center; gap: 10px;
        }
        .pc-status-card:hover { transform: translateY(-5px); box-shadow: 0 12px 24px rgba(92, 43, 122, 0.12); border-color: var(--primary-purple); }
        
        .pc-card-icon { width: 45px; height: 45px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
        .pc-status-card.available .pc-card-icon { background: #e3fcef; color: #00a854; }
        .pc-status-card.disabled .pc-card-icon { background: #fee2e2; color: #b91c1c; }
        
        .pc-card-num { font-weight: 800; font-size: 0.9rem; color: var(--text-main); margin-bottom: 2px; }
        .pc-card-status-badge { font-size: 0.6rem; font-weight: 900; text-transform: uppercase; padding: 2px 8px; border-radius: 100px; }
        .pc-status-card.available .pc-card-status-badge { background: #e3fcef; color: #00a854; }
        .pc-status-card.disabled .pc-card-status-badge { background: #fee2e2; color: #b91c1c; }
        
        .pc-card-reason { font-size: 0.6rem; color: var(--text-dim); font-weight: 600; width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    </style>
    <?php endif; ?>

    <?php elseif ($active_tab === 'resources'): ?>
    <div class="row g-4 mb-4">
        <div class="col-md-12">
            <div class="bento-card">
                <div class="card-title d-flex justify-content-between align-items-center">
                    <span>Software Inventory Management</span>
                    <div class="d-flex gap-2">
                        <?php if(file_exists('resources_docs/master_software_list.pdf')): ?>
                            <a href="resources_docs/master_software_list.pdf" class="btn btn-sm btn-outline-secondary fw-700" target="_blank"><i class="fa fa-file-pdf me-1"></i> Current Master List</a>
                        <?php endif; ?>
                        <button class="btn btn-sm btn-action" data-bs-toggle="modal" data-bs-target="#importPdfModal"><i class="fa fa-upload me-1"></i> Import PDF</button>
                    </div>
                </div>
                <?php if(isset($_GET['pdf']) && $_GET['pdf']==='success'): ?>
                    <div class="alert alert-success py-2 small fw-600 animate__animated animate__fadeIn">Master software list updated successfully.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="row g-4">
        <div class="col-md-4">
            <div class="bento-card">
                <div class="card-title">Add Lab Resource</div>
                <form method="POST">
                    <?php csrf_input(); ?>
                    <div class="mb-3">
                        <label class="small fw-800">Target Lab</label>
                        <select name="res_lab" class="form-select" required>
                            <?php $lb=$conn->query("SELECT LabName FROM labs ORDER BY LabName"); while($l=$lb->fetch_assoc()) echo "<option>".$l['LabName']."</option>"; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-800">App/Software Name</label>
                        <input type="text" name="res_app" class="form-control" placeholder="e.g. Visual Studio Code" required>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-800">Category</label>
                        <select name="res_cat" class="form-select">
                            <option>IDE / Editor</option>
                            <option>Compiler / Runtime</option>
                            <option>Database</option>
                            <option>Design / Graphics</option>
                            <option>Office / Productivity</option>
                            <option>Browser</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-800">Version / Details (Optional)</label>
                        <input type="text" name="res_desc" class="form-control" placeholder="e.g. v1.82">
                    </div>  
                    <button type="submit" name="add_resource" class="btn-action w-100 py-3">ADD RESOURCE</button>
                </form>
            </div>
        </div>
        <div class="col-md-8">
            <div class="bento-card h-100">
                <div class="card-title">Installed Applications</div>
                <div class="glass-table-container">
                    <table class="glass-table">
                        <thead><tr><th>App Name</th><th>Lab</th><th>Category</th><th class="text-end">Action</th></tr></thead>
                        <tbody>
                            <?php $res=$conn->query("SELECT * FROM lab_resources ORDER BY LabName, AppName"); 
                            if($res && $res->num_rows > 0): while($r=$res->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?= $r['AppName'] ?></strong><div class="small text-dim"><?= $r['Description'] ?></div></td>
                                <td><span class="badge bg-light text-dark">Lab <?= $r['LabName'] ?></span></td>
                                <td><?= $r['Category'] ?></td>
                                <td class="text-end">
                                    <form method="POST"><?php csrf_input(); ?><input type="hidden" name="res_id" value="<?= $r['ResourceID'] ?>"><button name="delete_resource" class="btn text-danger"><i class="fa fa-trash"></i></button></form>
                                </td>
                            </tr>
                            <?php endwhile; else: echo "<tr><td colspan='4' class='text-center py-4 text-dim'>No resources added yet.</td></tr>"; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="modal fade" id="editStudentModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="fw-800">Edit Student Profile</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form method="POST"><?php csrf_input(); ?><div class="modal-body"><input type="hidden" name="edit_id" id="edit_id"><div class="row g-3"><div class="col-6"><label class="small fw-800">First Name</label><input type="text" name="edit_first" id="edit_first" class="form-control"></div><div class="col-6"><label class="small fw-800">Last Name</label><input type="text" name="edit_last" id="edit_last" class="form-control"></div><div class="col-8"><label class="small fw-800">Course</label><input type="text" name="edit_course" id="edit_course" class="form-control"></div><div class="col-4"><label class="small fw-800">Year</label><input type="number" name="edit_level" id="edit_level" class="form-control"></div></div></div><div class="modal-footer border-0"><button type="submit" name="edit_student" class="btn-action w-100 py-3">Save Changes</button></div></form></div></div></div>
    <div class="modal fade" id="addLabModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="fw-800">Add New Lab</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form method="POST"><?php csrf_input(); ?><div class="modal-body"><div class="mb-2"><label class="small fw-800">Lab Name</label><input type="text" name="lab_name" class="form-control"></div><div class="mb-2"><label class="small fw-800">Total PCs</label><input type="number" name="lab_count" class="form-control"></div><div class="mb-2"><label class="small fw-800">Notes</label><textarea name="lab_desc_new" class="form-control"></textarea></div></div><div class="modal-footer border-0"><button type="submit" name="add_lab" class="btn-action w-100 py-3">Save Laboratory</button></div></form></div></div></div>

    <div class="modal fade" id="addStudentManualModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="fw-800">Manually Add Student</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <?php csrf_input(); ?>
                    <div class="modal-body">
                        <div class="field-group-label" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1.5px; color: var(--primary-purple); font-weight: 700; margin: 0 0 15px; display: flex; align-items: center; gap: 10px;">
                            Personal Information <div style="flex: 1; height: 1px; background: var(--purple-soft);"></div>
                        </div>
                        <div class="row g-3 mb-4">
                            <div class="col-12">
                                <label class="small fw-800">ID Number</label>
                                <input type="text" name="id_num" class="form-control" placeholder="e.g. 23-1234-567" required>
                            </div>
                            <div class="col-md-4">
                                <label class="small fw-800">Last Name</label>
                                <input type="text" name="last_name" class="form-control" placeholder="Last" required>
                            </div>
                            <div class="col-md-4">
                                <label class="small fw-800">First Name</label>
                                <input type="text" name="first_name" class="form-control" placeholder="First" required>
                            </div>
                            <div class="col-md-4">
                                <label class="small fw-800">Middle Name</label>
                                <input type="text" name="middle_name" class="form-control" placeholder="Optional">
                            </div>
                            <div class="col-12">
                                <label class="small fw-800">Home Address</label>
                                <input type="text" name="address" class="form-control" placeholder="Full address" required>
                            </div>
                        </div>

                        <div class="field-group-label" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1.5px; color: var(--primary-purple); font-weight: 700; margin: 0 0 15px; display: flex; align-items: center; gap: 10px;">
                            Academic Details <div style="flex: 1; height: 1px; background: var(--purple-soft);"></div>
                        </div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-8">
                                <label class="small fw-800">Course</label>
                                <select name="course" class="form-select" required>
                                    <option value="">Select Course</option>
                                    <option>Information Technology</option>
                                    <option>Computer Science</option>
                                    <option>Computer Engineering</option>
                                    <option>Data Science</option>
                                    <option>Cybersecurity</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="small fw-800">Year Level</label>
                                <select name="level" class="form-select" required>
                                    <option value="">Select</option>
                                    <option value="1">1st Year</option>
                                    <option value="2">2nd Year</option>
                                    <option value="3">3rd Year</option>
                                    <option value="4">4th Year</option>
                                </select>
                            </div>
                        </div>

                        <div class="field-group-label" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1.5px; color: var(--primary-purple); font-weight: 700; margin: 0 0 15px; display: flex; align-items: center; gap: 10px;">
                            Account Credentials <div style="flex: 1; height: 1px; background: var(--purple-soft);"></div>
                        </div>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="small fw-800">Email Address</label>
                                <input type="email" name="email" class="form-control" placeholder="name@example.com" required>
                            </div>
                            <div class="col-12">
                                <label class="small fw-800">Password (Optional)</label>
                                <input type="password" name="password" class="form-control" placeholder="Default: 123456">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="submit" name="add_student_manual" class="btn-action w-100 py-3">REGISTER STUDENT</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header py-3"><h6 class="mb-0 fw-800">Reason for Cancellation</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <form method="POST">
                    <?php csrf_input(); ?>
                    <input type="hidden" name="res_id" id="reject_res_id">
                    <div class="modal-body py-3">
                        <select name="cancel_reason" class="form-select mb-3" required>
                            <option value="Lab is currently unavailable due to class.">Lab unavailable (Class)</option>
                            <option value="The lab is under maintenance.">Under Maintenance</option>
                            <option value="Maximum lab capacity reached.">Lab at Full Capacity</option>
                            <option value="Invalid reservation details.">Invalid Details</option>
                            <option value="Other">Other (Manual input below)</option>
                        </select>
                        <textarea name="cancel_reason_manual" class="form-control" placeholder="Type custom reason here..." rows="2"></textarea>
                        <button type="submit" name="reject_reservation" class="btn-action bg-danger w-100 mt-3 py-2">Confirm Cancellation</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <form id="approveForm" method="POST" style="display:none;">
        <?php csrf_input(); ?>
        <input type="hidden" name="res_id" id="approve_res_id">
        <input type="hidden" name="approve_reservation" value="1">
    </form>
    <div class="modal fade" id="importPdfModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header"><h5 class="fw-800">Import Master Software List</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <form method="POST" enctype="multipart/form-data">
                    <?php csrf_input(); ?>
                    <div class="modal-body">
                        <div class="alert alert-info small fw-600 mb-3">Upload a PDF file containing the official software list. This will be available for all students to download.</div>
                        <div class="mb-3">
                            <label class="small fw-800">Select PDF File</label>
                            <input type="file" name="resource_pdf" class="form-control" accept=".pdf" required>
                        </div>
                    </div>
                    <div class="modal-footer border-0"><button type="submit" name="upload_resource_pdf" class="btn-action w-100 py-3">UPLOAD MASTER LIST</button></div>
                </form>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePC(lab, num, action) {
    document.getElementById('modal_pc_lab').value = lab;
    document.getElementById('modal_pc_num').value = num;
    document.getElementById('modal_pc_action').value = action;
    document.getElementById('pcModalTitle').innerText = (action === 'disable' ? 'Disable' : 'Enable') + ' PC #' + num;
    document.getElementById('pcModalBtn').className = 'btn-action w-100 mt-3 py-2 ' + (action === 'disable' ? 'bg-danger' : 'bg-success');
    document.getElementById('disableOptions').style.display = action === 'disable' ? 'block' : 'none';
    document.getElementById('enableText').style.display = action === 'enable' ? 'block' : 'none';
    new bootstrap.Modal(document.getElementById('pcToggleModal')).show();
}
function openReject(id){ document.getElementById('reject_res_id').value=id; new bootstrap.Modal(document.getElementById('rejectModal')).show(); }
function approveRes(id){ document.getElementById('approve_res_id').value=id; document.getElementById('approveForm').submit(); }
function toggleEdit(id){let el=document.getElementById('editLab_'+id); el.style.display=el.style.display==='none'?'block':'none';}
function openEdit(id,f,l,c,v){document.getElementById('edit_id').value=id;document.getElementById('edit_first').value=f;document.getElementById('edit_last').value=l;document.getElementById('edit_course').value=c;document.getElementById('edit_level').value=v;new bootstrap.Modal(document.getElementById('editStudentModal')).show();}
const search=document.getElementById('studentSearch'); if(search){search.oninput=function(){let q=this.value.toLowerCase();document.querySelectorAll('#studentTable tbody tr').forEach(tr=>tr.style.display=tr.innerText.toLowerCase().includes(q)?'':'none');};}
const lookup=document.getElementById('studentLookup'); if(lookup){let db;lookup.oninput=function(){clearTimeout(db);const q=this.value.trim();if(q.length<2){document.getElementById('searchResults').style.display='none';return;}db=setTimeout(()=>{fetch('admin_dashboard.php?search_student='+encodeURIComponent(q)).then(r=>r.json()).then(data=>{const list=document.getElementById('resultsList');if(data.length===0)list.innerHTML='<div class="p-2 small">No matches.</div>';else list.innerHTML=data.map(s=>`<div class="p-3 rounded-4 bg-white border cursor-pointer" onclick="selectStudent('${s.IdNumber}','${s.FirstName}','${s.LastName}')"><div class="fw-800 text-gold">${s.FirstName} ${s.LastName}</div><div class="small text-dim">${s.IdNumber} • ${s.Course}</div></div>`).join('');document.getElementById('searchResults').style.display='block';});},300);};}
function selectStudent(id,f,l){document.getElementById('sitin_id').value=id;document.getElementById('display_id').innerText=id;document.getElementById('display_name').innerText=f+' '+l;document.getElementById('searchResults').style.display='none';document.getElementById('studentLookup').value='';document.getElementById('sitinForm').style.display='block';}

document.querySelectorAll('.lab-btn').forEach(b=>b.onclick=function(){
    document.querySelectorAll('.lab-btn').forEach(x=>x.classList.remove('active')); 
    this.classList.add('active'); 
    document.getElementById('sitin_lab_input').value=this.dataset.lab; 
    document.getElementById('labInfoArea').classList.remove('d-none'); 
    loadPCsAdmin(this.dataset.lab, document.getElementById('sitin_date').value);
});

function loadPCsAdmin(l,d){
    const g=document.getElementById('pcGrid'); 
    g.innerHTML='Loading...'; 
    fetch(`student_dashboard.php?get_pcs=1&lab=${l}&date=${d}`).then(r=>r.json()).then(data=>{
        g.innerHTML=''; 
        const occupied = data.occupied || [];
        const disabled = data.disabled || {};
        for(let i=1;i<=data.total;i++){
            const div=document.createElement('div');
            const is_occupied = occupied.includes(i);
            const is_disabled = disabled.hasOwnProperty(i);
            
            div.className = 'pc-item' + (is_occupied || is_disabled ? ' occupied' : '');
            div.innerText = i;
            
            if(is_disabled) { div.title = 'Condition: ' + disabled[i]; div.style.cursor = 'help'; } 
            else if(is_occupied) { div.title = 'Status: Currently Booked'; } 
            else {
                div.onclick=function(){
                    g.querySelectorAll('.pc-item').forEach(p=>p.classList.remove('selected')); 
                    this.classList.add('selected'); 
                    document.getElementById('sitin_pc_input').value=i;
                };
            }
            g.appendChild(div);
        }
    }); 
}
<?php if($active_tab==='dashboard'): ?>
document.addEventListener('DOMContentLoaded',()=>{const ctx=document.getElementById('usageChart');if(ctx)new Chart(ctx,{type:'doughnut',data:{labels:<?=json_encode($purposes)?>,datasets:[{data:<?=json_encode($counts)?>,backgroundColor:['#5c2b7a','#c09412','#7b3da3','#d4a72c','#2D1B4E'],borderWidth:0}]},options:{responsive:true,maintainAspectRatio:false,cutout:'75%',plugins:{legend:{position:'bottom',labels:{usePointStyle:true,font:{size:10}}}}}});});
<?php endif; ?>
</script>
</body></html>