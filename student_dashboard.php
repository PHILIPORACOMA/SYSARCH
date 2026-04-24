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
$id = $_SESSION['user_id'];

// Create uploads directory if it doesn't exist
$uploads_dir = 'uploads/student_photos';
if (!is_dir($uploads_dir)) {
    mkdir($uploads_dir, 0755, true);
}

/* ── AJAX: Upload Photo ── */
if (isset($_POST['upload_photo'])) {
    header('Content-Type: application/json');
    
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] != 0) {
        echo json_encode(['status' => 'error', 'message' => 'No file uploaded or upload error.']);
        exit();
    }
    
    $file = $_FILES['photo'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    // Validate file type
    if (!in_array($file['type'], $allowed_types)) {
        echo json_encode(['status' => 'error', 'message' => 'Only JPG, PNG, and GIF files are allowed.']);
        exit();
    }
    
    // Validate file size
    if ($file['size'] > $max_size) {
        echo json_encode(['status' => 'error', 'message' => 'File size must not exceed 5MB.']);
        exit();
    }
    
    // Delete old photo if exists
    $old_photo_q = $conn->prepare("SELECT PhotoPath FROM students_info WHERE IdNumber=?");
    $old_photo_q->bind_param("s", $id);
    $old_photo_q->execute();
    $old_row = $old_photo_q->get_result()->fetch_assoc();
    $old_photo_q->close();
    
    if ($old_row && $old_row['PhotoPath'] && file_exists($old_row['PhotoPath'])) {
        unlink($old_row['PhotoPath']);
    }
    
    // Save new photo
    $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_filename = $id . '_' . time() . '.' . $file_ext;
    $file_path = $uploads_dir . '/' . $new_filename;
    
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        // Update database
        $upd = $conn->prepare("UPDATE students_info SET PhotoPath=? WHERE IdNumber=?");
        $upd->bind_param("ss", $file_path, $id);
        $upd->execute();
        $upd->close();
        
        echo json_encode(['status' => 'ok', 'photo_path' => $file_path]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to save file.']);
    }
    exit();
}

/* ── AJAX: Remove Photo ── */
if (isset($_POST['remove_photo'])) {
    header('Content-Type: application/json');
    
    $photo_q = $conn->prepare("SELECT PhotoPath FROM students_info WHERE IdNumber=?");
    $photo_q->bind_param("s", $id);
    $photo_q->execute();
    $photo_row = $photo_q->get_result()->fetch_assoc();
    $photo_q->close();
    
    if ($photo_row && $photo_row['PhotoPath'] && file_exists($photo_row['PhotoPath'])) {
        unlink($photo_row['PhotoPath']);
    }
    
    $upd = $conn->prepare("UPDATE students_info SET PhotoPath=NULL WHERE IdNumber=?");
    $upd->bind_param("s", $id);
    $upd->execute();
    $upd->close();
    
    echo json_encode(['status' => 'ok']);
    exit();
}

/* ── AJAX: Get current photo ── */
if (isset($_GET['get_current_photo'])) {
    header('Content-Type: application/json');
    
    $photo_q = $conn->prepare("SELECT PhotoPath FROM students_info WHERE IdNumber=?");
    $photo_q->bind_param("s", $id);
    $photo_q->execute();
    $photo_row = $photo_q->get_result()->fetch_assoc();
    $photo_q->close();
    
    $photo_path = null;
    if ($photo_row && $photo_row['PhotoPath'] && file_exists($photo_row['PhotoPath'])) {
        $photo_path = $photo_row['PhotoPath'];
    }
    
    echo json_encode(['photo_path' => $photo_path]);
    exit();
}

/* ── AJAX: Get occupied PCs for a lab on a date ── */
if (isset($_GET['get_pcs'])) {
    $lab  = trim($_GET['lab']);
    $date = trim($_GET['date']);
    // Get total PC count from labs table
    $lab_q = $conn->prepare("SELECT PCCount FROM labs WHERE LabName=?");
    $lab_q->bind_param("s", $lab);
    $lab_q->execute();
    $lab_row = $lab_q->get_result()->fetch_assoc();
    $lab_q->close();
    $pc_count = $lab_row ? (int)$lab_row['PCCount'] : 40;
    // Get occupied PCs
    $occ  = $conn->prepare("SELECT PCNumber FROM sit_in_sessions WHERE Lab=? AND SessionDate=? AND Status IN ('Active','Pending') AND PCNumber IS NOT NULL");
    $occ->bind_param("ss", $lab, $date);
    $occ->execute();
    $rows = $occ->get_result()->fetch_all(MYSQLI_ASSOC);
    $occ->close();
    $occupied = array_column($rows, 'PCNumber');
    header('Content-Type: application/json');
    echo json_encode(['occupied' => $occupied, 'total' => $pc_count]);
    exit();
}

/* ── AJAX: Get labs list for PC picker ── */
if (isset($_GET['get_labs'])) {
    $labs_q = $conn->query("SELECT LabName, PCCount FROM labs ORDER BY LabName ASC");
    $labs_list = $labs_q ? $labs_q->fetch_all(MYSQLI_ASSOC) : [];
    header('Content-Type: application/json');
    echo json_encode($labs_list);
    exit();
}

/* ── Handle Feedback Submission ── */
if (isset($_POST['submit_feedback'])) {
    $session_id = (int)$_POST['fb_session_id'];
    $rating     = (int)$_POST['fb_rating'];
    $task_completed = floatval($_POST['fb_task_completed']);
    $message    = trim($_POST['fb_message']);
    // Check if already submitted
    $chk = $conn->prepare("SELECT FeedbackID FROM feedback WHERE SessionID=? AND StudentID=?");
    $chk->bind_param("is", $session_id, $id);
    $chk->execute();
    if ($chk->get_result()->num_rows === 0) {
        $ins = $conn->prepare("INSERT INTO feedback (SessionID, StudentID, Rating, Message) VALUES (?,?,?,?)");
        $ins->bind_param("isis", $session_id, $id, $rating, $message);
        $ins->execute(); $ins->close();
        
        // Update sit_in_sessions with task completion status
        $upd = $conn->prepare("UPDATE sit_in_sessions SET TaskCompleted=? WHERE SessionID=?");
        $upd->bind_param("di", $task_completed, $session_id);
        $upd->execute(); $upd->close();
        
        $feedback_result = 'success';
    } else {
        $feedback_result = 'already';
    }
    $chk->close();
    header("Location: student_dashboard.php?feedback=" . $feedback_result); exit();
}


if (isset($_POST['update_profile'])) {
    $fname = trim($_POST['first_name']);
    $mname = trim($_POST['middle_name']);
    $lname = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $addr  = trim($_POST['address']);
    $newpw = $_POST['password'];

    if (!empty($newpw)) {
        $hashed = password_hash($newpw, PASSWORD_DEFAULT);
        $s = $conn->prepare("UPDATE students_info SET FirstName=?,MiddleName=?,LastName=?,Email=?,Address=?,Password=? WHERE IdNumber=?");
        $s->bind_param("sssssss", $fname, $mname, $lname, $email, $addr, $hashed, $id);
    } else {
        $s = $conn->prepare("UPDATE students_info SET FirstName=?,MiddleName=?,LastName=?,Email=?,Address=? WHERE IdNumber=?");
        $s->bind_param("ssssss", $fname, $mname, $lname, $email, $addr, $id);
    }
    $s->execute(); $s->close();
    $_SESSION['user_name'] = $fname . ' ' . $lname;
    $profile_success = "Profile updated successfully.";
}

/* ── Handle Reservation ── */
if (isset($_POST['submit_reservation'])) {
    $purpose = trim($_POST['res_purpose']);
    $lab     = trim($_POST['res_lab']);
    $timein  = trim($_POST['res_timein']);
    $date    = trim($_POST['res_date']);
    $pc      = !empty($_POST['res_pc']) ? (int)$_POST['res_pc'] : null;

    // Check for duplicate reservation on same date
    $dup = $conn->prepare("SELECT SessionID FROM sit_in_sessions WHERE StudentID=? AND SessionDate=? AND Type='Reservation' AND Status='Pending'");
    $dup->bind_param("ss", $id, $date);
    $dup->execute();
    if ($dup->get_result()->num_rows > 0) {
        $res_error = "You already have a pending reservation on that date.";
    } else {
        $ins = $conn->prepare("INSERT INTO sit_in_sessions (StudentID, Purpose, Lab, TimeIn, SessionDate, Status, Type, PCNumber) VALUES (?,?,?,?,?,'Pending','Reservation',?)");
        $ins->bind_param("sssssi", $id, $purpose, $lab, $timein, $date, $pc);
        $ins->execute(); $ins->close();
        $res_success = "Reservation submitted successfully!";
    }
    $dup->close();
}

/* ── Cancel Reservation ── */
if (isset($_POST['cancel_reservation'])) {
    $rid = (int)$_POST['res_id'];
    $s = $conn->prepare("DELETE FROM sit_in_sessions WHERE SessionID=? AND StudentID=? AND Type='Reservation'");
    $s->bind_param("is", $rid, $id);
    $s->execute(); $s->close();
    $res_success = "Reservation cancelled.";
}

/* ── Fetch student ── */
$stmt = $conn->prepare("SELECT * FROM students_info WHERE IdNumber=?");
$stmt->bind_param("s", $id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

/* ── Unread notifications count ── */
$unread_stmt = $conn->prepare("SELECT COUNT(*) as c FROM notifications WHERE StudentID=? AND IsRead=0");
$unread_stmt->bind_param("s", $id);
$unread_stmt->execute();
$unread_count = $unread_stmt->get_result()->fetch_assoc()['c'];
$unread_stmt->close();

/* ── Mark notifications as read when modal opened ── */
if (isset($_POST['mark_notifications_read'])) {
    $conn->prepare("UPDATE notifications SET IsRead=1 WHERE StudentID=?")->bind_param("s", $id);
    $upd = $conn->prepare("UPDATE notifications SET IsRead=1 WHERE StudentID=?");
    $upd->bind_param("s", $id);
    $upd->execute(); $upd->close();
    echo json_encode(['status' => 'ok']);
    exit();
}

/* ── Get AI Recommendations (AJAX) ── */
if (isset($_GET['get_recommendations'])) {
    header('Content-Type: application/json');
    $recommendations = getStudentRecommendations($conn, $id);
    echo json_encode($recommendations);
    exit();
}

/* ── Session credits ── */
$used_sessions   = $conn->query("SELECT COUNT(*) as c FROM sit_in_sessions WHERE StudentID='".mysqli_real_escape_string($conn,$id)."' AND (Type='Sit-in' OR Type IS NULL)")->fetch_assoc()['c'];
$max_credits     = isset($student['SessionCredits']) ? (int)$student['SessionCredits'] : 30;
$credits_left    = max(0, $max_credits - $used_sessions);
$credits_percent = $max_credits > 0 ? round(($credits_left / $max_credits) * 100) : 0;
$credits_color   = $credits_left > 15 ? '#198754' : ($credits_left > 5 ? '#c09412' : '#dc3545');

/* ── AI Recommendations ── */
$recommendations = getStudentRecommendations($conn, $id);

// Check if recommendations should be shown (not shown more than once per session)
$show_recommendations = !isset($_SESSION['recommendations_shown']) || ($_SESSION['recommendations_shown'] < time() - 3600); // Show every hour
if ($show_recommendations && !empty($recommendations)) {
    $_SESSION['recommendations_shown'] = time();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCS | Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --purple:#5c2b7a; --gold:#c09412; }
        body { background-color:#f8f9fa; }

        /* Navbar */
        .navbar-custom { background-color:var(--purple); }
        .navbar-brand, .nav-link { color:white !important; }
        .nav-link { padding:0.4rem 0.75rem !important; border-radius:6px; transition:background 0.2s; }
        .nav-link:hover { background-color:rgba(255,255,255,0.12) !important; color:#f0d080 !important; }
        .UC-Logo { background-color:var(--purple); height:40px; }

        /* Cards */
        .dash-card { border:none; border-radius:12px; box-shadow:0 6px 20px rgba(0,0,0,0.07); }
        .card-header-purple { background-color:var(--purple); color:white; font-weight:600; border-radius:12px 12px 0 0 !important; padding:10px 16px; }
        .card-header-gold   { background-color:var(--gold); color:#1a1a1a; font-weight:600; border-radius:12px 12px 0 0 !important; padding:10px 16px; }

        /* Avatar */
        /* Profile Photo Container */
        .profile-photo-container {
            position: relative;
            width: 90px;
            height: 90px;
            margin: 0 auto 0.75rem;
        }
        .avatar {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background-color: var(--purple);
            color: white;
            font-size: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 4px solid #f3eaf9;
            object-fit: cover;
        }
        .avatar-img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #f3eaf9;
            display: block;
        }
        .photo-upload-btn {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: var(--gold);
            color: #1a1a1a;
            border: 3px solid white;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .photo-upload-btn:hover {
            background-color: #a87e0f;
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        /* Info rows */
        .info-row { display:flex; justify-content:space-between; padding:7px 0; border-bottom:1px solid #f0f0f0; font-size:0.87rem; }
        .info-row:last-child { border-bottom:none; }
        .info-label { color:#999; }
        .info-value { font-weight:600; color:#333; text-align:right; max-width:60%; word-break:break-all; }

        /* Credits box */
        .credits-box {
            background: #f8f4fc;
            border-radius: 10px;
            padding: 12px 14px;
            margin-top: 12px;
        }
        .credits-title { font-size:0.72rem; text-transform:uppercase; letter-spacing:0.07em; color:var(--purple); font-weight:700; margin-bottom:8px; }
        .credits-nums { display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:6px; }
        .credits-big  { font-size:1.6rem; font-weight:800; line-height:1; }
        .credits-sub  { font-size:0.75rem; color:#999; }
        .credits-bar  { height:8px; border-radius:4px; background:#e0e0e0; overflow:hidden; }
        .credits-fill { height:100%; border-radius:4px; transition:width 0.4s; }

        /* Action buttons */
        .profile-actions { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:1rem; }
        .btn-action { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:5px; padding:10px 6px; border-radius:10px; border:1.5px solid #eee; background:white; color:#555; font-size:0.75rem; font-weight:600; cursor:pointer; transition:all 0.2s; text-decoration:none; }
        .btn-action i { font-size:1.1rem; }
        .btn-action:hover { border-color:var(--purple); background:#f3eaf9; color:var(--purple); }
        .btn-action.btn-action-edit  i { color:var(--purple); }
        .btn-action.btn-action-notif i { color:#e67e22; }
        .btn-action.btn-action-tips  i { color:#f39c12; }
        .btn-action.btn-action-hist  i { color:#2980b9; }
        .btn-action.btn-action-res   i { color:#27ae60; }

        /* Rules */
        .rules-list li { padding:5px 0; font-size:0.87rem; border-bottom:1px solid #f0f0f0; }
        .rules-list li:last-child { border-bottom:none; }
        .rules-list li::marker { color:var(--purple); }

        /* Announcements */
        .announcement-item { padding:12px 0; border-bottom:1px solid #f0f0f0; }
        .announcement-item:last-child { border-bottom:none; }
        .announcement-item h6 { color:var(--purple); font-weight:700; margin-bottom:4px; }

        /* Table */
        .table thead th { background-color:var(--purple); color:white; font-size:0.85rem; border:none; }
        .table tbody tr:hover { background-color:#f3eaf9; }
        .badge-active    { background-color:#198754; color:white; padding:3px 8px; border-radius:5px; font-size:0.78rem; }
        .badge-completed { background-color:#6c757d; color:white; padding:3px 8px; border-radius:5px; font-size:0.78rem; }
        .badge-pending   { background-color:#c09412; color:#1a1a1a; padding:3px 8px; border-radius:5px; font-size:0.78rem; }
        .badge-approved  { background-color:#0d6efd; color:white; padding:3px 8px; border-radius:5px; font-size:0.78rem; }
        .badge-cancelled { background-color:#6c757d; color:white; padding:3px 8px; border-radius:5px; font-size:0.78rem; }

        /* Navbar buttons */
        .btn-home { background-color:rgba(255,255,255,0.15); color:white !important; border:1.5px solid rgba(255,255,255,0.3); border-radius:7px; font-size:0.85rem; padding:0.3rem 0.9rem; }
        .btn-home:hover { background-color:rgba(255,255,255,0.25); }
        .btn-logout { background-color:var(--gold); color:#1a1a1a !important; border:none; border-radius:7px; font-size:0.85rem; padding:0.3rem 0.9rem; }
        .btn-logout:hover { background-color:#a87e0f; color:white !important; }

        /* Modals */
        .modal-header { background-color:var(--purple); color:white; border-radius:12px 12px 0 0; }
        .modal-header .btn-close { filter:invert(1); }
        .modal-content { border-radius:12px; border:none; box-shadow:0 20px 50px rgba(0,0,0,0.15); overflow:hidden; }
        .form-control:focus, .form-select:focus { border-color:var(--purple); box-shadow:0 0 0 3px rgba(92,43,122,0.12); }
        .form-control, .form-select { border-radius:8px; font-size:0.88rem; }
        .btn-save { background-color:var(--gold); color:#1a1a1a; border:none; border-radius:8px; font-weight:600; }
        .btn-save:hover { background-color:#a87e0f; color:white; }
        .btn-purple-solid { background-color:var(--purple); color:white; border:none; border-radius:8px; font-weight:600; }
        .btn-purple-solid:hover { background-color:#4a2263; color:white; }

        /* Reservation form */
        .res-field-label { font-size:0.78rem; color:#777; font-weight:500; margin-bottom:3px; }

        /* Leaderboard Styles */
        .leaderboard-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .leaderboard-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        
        .leaderboard-table thead {
            background-color: var(--purple);
            color: white;
            font-weight: 600;
        }
        
        .leaderboard-table thead th {
            padding: 12px 16px;
            text-align: left;
            border: none;
        }
        
        .rank-col { width: 80px; }
        .name-col { flex: 1; min-width: 180px; }
        .course-col { width: 140px; }
        .score-col { width: 100px; text-align: right; }
        .detail-col { width: 90px; text-align: center; }
        
        .leaderboard-table tbody tr {
            border-bottom: 1px solid #f0f0f0;
            transition: background-color 0.2s;
        }
        
        .leaderboard-table tbody tr:hover {
            background-color: #f8f4fc;
        }
        
        .leaderboard-table tbody tr.top-rank {
            background-color: #faf6ff;
        }
        
        .leaderboard-table tbody tr.rank-1 {
            border-left: 4px solid #ffc107;
        }
        
        .leaderboard-table tbody tr.rank-2 {
            border-left: 4px solid #c0c0c0;
        }
        
        .leaderboard-table tbody tr.rank-3 {
            border-left: 4px solid #cd7f32;
        }
        
        .leaderboard-table td {
            padding: 12px 16px;
        }
        
        .rank-cell {
            text-align: center;
            font-weight: 600;
        }
        
        .rank-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 700;
            color: white;
        }
        
        .rank-badge.gold {
            background-color: #ffc107;
            color: #1a1a1a;
        }
        
        .rank-badge.silver {
            background-color: #c0c0c0;
            color: #1a1a1a;
        }
        
        .rank-badge.bronze {
            background-color: #cd7f32;
            color: white;
        }
        
        .rank-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background-color: var(--purple);
            color: white;
            font-weight: 700;
            font-size: 0.85rem;
        }
        
        .student-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .student-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e0e0e0;
        }
        
        .student-avatar-initials {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: var(--purple);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
        }
        
        .student-name {
            font-weight: 600;
            color: #333;
        }
        
        .course-cell {
            font-size: 0.85rem;
            color: #666;
        }
        
        .score-cell {
            text-align: right;
            font-weight: 700;
        }
        
        .score-badge {
            display: inline-block;
            background-color: var(--purple);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .detail-cell {
            text-align: center;
            color: #666;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-custom px-4">
    <div class="container-fluid">
        <img src="UCLogo-removebg-preview.png" alt="UC Logo" class="UC-Logo">
        <a class="navbar-brand fs-6 ms-2" href="Landingpage.php">College of Computer Studies Sit-in Monitoring System</a>
        <div class="ms-auto d-flex align-items-center gap-2">
            <span class="text-white me-1" style="font-size:0.9rem;">Welcome, <b><?php echo htmlspecialchars($_SESSION['user_name']); ?></b></span>
            <a href="Landingpage.php" class="btn btn-home btn-sm"><i class="fa fa-house me-1"></i>Home</a>
            <a href="logout.php" class="btn btn-logout btn-sm"><i class="fa fa-right-from-bracket me-1"></i>Logout</a>
        </div>
    </div>
</nav>

<!-- MAIN -->
<div class="container mt-4 pb-5">

    <?php if (isset($profile_success)): ?>
        <div class="alert alert-success alert-dismissible fade show py-2 px-3 mb-3" style="border-radius:8px;font-size:0.88rem;">
            <i class="fa fa-circle-check me-2"></i><?= htmlspecialchars($profile_success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- ════ LEFT COLUMN ════ -->
        <div class="col-md-4">

            <!-- Profile Card -->
            <div class="card dash-card mb-4">
                <div class="card-header card-header-purple">
                    <i class="fa fa-user me-2"></i>Student Profile
                </div>
                <div class="card-body pt-4">
                    <?php if ($student): ?>
                        <!-- Profile Photo with Plus Button -->
                        <div class="profile-photo-container">
                            <?php if ($student['PhotoPath'] && file_exists($student['PhotoPath'])): ?>
                                <img src="<?php echo htmlspecialchars($student['PhotoPath']); ?>" alt="Student Photo" class="avatar-img">
                            <?php else: ?>
                                <div class="avatar">
                                    <?php echo strtoupper(substr($student['FirstName'],0,1).substr($student['LastName'],0,1)); ?>
                                </div>
                            <?php endif; ?>
                            <div class="photo-upload-btn" onclick="openPhotoModal()" title="Edit photo">
                                <i class="fa fa-plus"></i>
                            </div>
                        </div>

                        <h6 class="text-center fw-bold mb-1" style="color:var(--purple)">
                            <?php echo htmlspecialchars($student['FirstName'].' '.$student['LastName']); ?>
                        </h6>
                        <p class="text-center text-muted mb-3" style="font-size:0.78rem;">
                            <?php echo htmlspecialchars($student['Course']); ?> &mdash; Year <?php echo $student['CourseLevel']; ?>
                        </p>

                        <div class="info-row">
                            <span class="info-label">ID Number</span>
                            <span class="info-value"><?php echo htmlspecialchars($student['IdNumber']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Email</span>
                            <span class="info-value" style="font-size:0.78rem"><?php echo htmlspecialchars($student['Email']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Course</span>
                            <span class="info-value"><?php echo htmlspecialchars($student['Course']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Year Level</span>
                            <span class="info-value">Year <?php echo $student['CourseLevel']; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Address</span>
                            <span class="info-value"><?php echo htmlspecialchars($student['Address'] ?? '—'); ?></span>
                        </div>

                        <!-- Session Credits -->
                        <div class="credits-box">
                            <div class="credits-title"><i class="fa fa-ticket me-1"></i>Session Credits</div>
                            <div class="credits-nums">
                                <div>
                                    <div class="credits-big" style="color:<?= $credits_color ?>"><?= $credits_left ?></div>
                                    <div class="credits-sub">credits remaining</div>
                                </div>
                                <div class="text-end">
                                    <div style="font-size:0.78rem;color:#aaa;">Used</div>
                                    <div style="font-size:1rem;font-weight:700;color:#555;"><?= $used_sessions ?> / <?= $max_credits ?></div>
                                </div>
                            </div>
                            <div class="credits-bar">
                                <div class="credits-fill" style="width:<?= $credits_percent ?>%; background:<?= $credits_color ?>;"></div>
                            </div>
                            <?php if ($credits_left <= 5): ?>
                                <div style="font-size:0.75rem;color:#dc3545;margin-top:5px;">
                                    <i class="fa fa-triangle-exclamation me-1"></i>Low credits! Contact admin to reset.
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Action Buttons -->
                        <div class="profile-actions">
                            <a href="#" class="btn-action btn-action-edit" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                                <i class="fa fa-pen-to-square"></i>Edit Profile
                            </a>
                            <a href="#" class="btn-action btn-action-notif" data-bs-toggle="modal" data-bs-target="#notificationsModal" onclick="markNotificationsRead()">
                                <i class="fa fa-bell"></i>
                                Notifications
                                <?php if ($unread_count > 0): ?>
                                    <span class="notif-badge" style="background:#dc3545;color:white;border-radius:20px;padding:1px 7px;font-size:0.68rem;font-weight:700;line-height:1.4;">
                                        <?= $unread_count ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                            <a href="#" class="btn-action btn-action-tips" onclick="event.preventDefault(); new bootstrap.Modal(document.getElementById('recommendationsModal')).show(); loadRecommendations();" title="View personalized tips">
                                <i class="fa fa-lightbulb"></i>AI Tips
                            </a>
                            <a href="#" class="btn-action btn-action-hist" data-bs-toggle="modal" data-bs-target="#historyModal">
                                <i class="fa fa-clock-rotate-left"></i>History
                            </a>
                            <a href="#" class="btn-action btn-action-res" data-bs-toggle="modal" data-bs-target="#reservationsModal">
                                <i class="fa fa-calendar-check"></i>Reservations
                            </a>
                        </div>
                    <?php else: ?>
                        <p class="text-danger text-center">Student information not found.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Rules Card -->
            <div class="card dash-card">
                <div class="card-header card-header-gold">
                    <i class="fa fa-scale-balanced me-2"></i>Rules &amp; Regulations
                </div>
                <div class="card-body">
                    <ul class="rules-list ps-3 mb-0">
                        <li>No food inside the laboratory.</li>
                        <li>Maintain silence in the lab.</li>
                        <li>Maximum sit-in session is 3 hours.</li>
                        <li>Respect laboratory equipment.</li>
                        <li>Always log your sit-in session.</li>
                    </ul>
                </div>
            </div>

        </div>
        <!-- END LEFT -->

        <!-- ════ RIGHT COLUMN ════ -->
        <div class="col-md-8">

            <!-- Announcements -->
            <div class="card dash-card mb-4">
                <div class="card-header card-header-purple">
                    <i class="fa fa-bullhorn me-2"></i>Announcements
                </div>
                <div class="card-body">
                    <?php
                    $ann = $conn->query("SELECT * FROM announcements ORDER BY DatePosted DESC");
                    if ($ann && $ann->num_rows > 0):
                        while ($row = $ann->fetch_assoc()):
                    ?>
                        <div class="announcement-item">
                            <h6><?php echo htmlspecialchars($row['Title']); ?></h6>
                            <p class="mb-1 text-muted" style="font-size:0.88rem"><?php echo htmlspecialchars($row['Message']); ?></p>
                            <small class="text-muted"><i class="fa fa-calendar me-1"></i><?php echo htmlspecialchars($row['DatePosted']); ?></small>
                        </div>
                    <?php endwhile; else: ?>
                        <p class="text-muted mb-0">No announcements available.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sit-in Sessions -->
            <?php if (isset($_GET['feedback'])): ?>
                <?php if ($_GET['feedback'] === 'success'): ?>
                    <div class="alert alert-success alert-dismissible fade show py-2 px-3 mb-3" style="border-radius:8px;font-size:0.88rem;">
                        <i class="fa fa-circle-check me-2"></i>Feedback submitted successfully. Thank you!
                        <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
                    </div>
                <?php elseif ($_GET['feedback'] === 'already'): ?>
                    <div class="alert alert-info alert-dismissible fade show py-2 px-3 mb-3" style="border-radius:8px;font-size:0.88rem;">
                        <i class="fa fa-circle-info me-2"></i>You have already submitted feedback for this session.
                        <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            <div class="card dash-card">
                <div class="card-header card-header-gold">
                    <i class="fa fa-clock me-2"></i>Your Sit-in Sessions
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th><th>Purpose</th><th>Lab</th><th>Time In</th><th>Time Out</th><th>Status</th><th>Feedback</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            $ss = $conn->prepare("
                                SELECT s.*, f.FeedbackID, f.Rating as fb_rating
                                FROM sit_in_sessions s
                                LEFT JOIN feedback f ON f.SessionID = s.SessionID AND f.StudentID = s.StudentID
                                WHERE s.StudentID=? AND (s.Type='Sit-in' OR s.Type IS NULL)
                                ORDER BY s.SessionDate DESC, s.TimeIn DESC
                            ");
                            $ss->bind_param("s", $id);
                            $ss->execute();
                            $sessions = $ss->get_result();
                            if ($sessions && $sessions->num_rows > 0):
                                while ($s = $sessions->fetch_assoc()):
                                    $badge = match(strtolower($s['Status'])) {
                                        'active'    => 'badge-active',
                                        'approved'  => 'badge-approved',
                                        'completed' => 'badge-completed',
                                        'cancelled' => 'badge-cancelled',
                                        default     => 'badge-completed'
                                    };
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($s['SessionDate']); ?></td>
                                    <td><?php echo htmlspecialchars($s['Purpose'] ?? '—'); ?></td>
                                    <td><?php echo htmlspecialchars($s['Lab'] ?? '—'); ?></td>
                                    <td><?php echo htmlspecialchars($s['TimeIn']); ?></td>
                                    <td><?php echo $s['TimeOut'] ? htmlspecialchars($s['TimeOut']) : '<span class="text-muted">—</span>'; ?></td>
                                    <td><span class="<?= $badge ?>"><?php echo htmlspecialchars($s['Status']); ?></span></td>
                                    <td>
                                        <?php if (strtolower($s['Status']) === 'completed'): ?>
                                            <?php if ($s['FeedbackID']): ?>
                                                <span style="color:var(--gold);font-size:0.82rem;">
                                                    <?php for($x=1;$x<=5;$x++) echo $x<=$s['fb_rating'] ? '★' : '☆'; ?>
                                                </span>
                                            <?php else: ?>
                                                <button class="btn btn-sm"
                                                    style="background:var(--purple);color:white;border:none;border-radius:6px;font-size:0.75rem;padding:2px 10px;"
                                                    onclick="openFeedback(<?= $s['SessionID'] ?>, '<?= htmlspecialchars($s['SessionDate']) ?>', '<?= htmlspecialchars($s['Purpose'] ?? '') ?>')">
                                                    <i class="fa fa-star me-1"></i>Rate
                                                </button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted" style="font-size:0.78rem;">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="7" class="text-center text-muted py-3"><i class="fa fa-inbox me-2"></i>No sessions found</td></tr>
                            <?php endif; $ss->close(); ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
        <!-- END RIGHT -->

    </div>

    <!-- ════ LEADERBOARD SECTION ════ -->
    <div class="row g-4 mt-2">
        <div class="col-12">
            <div class="card dash-card">
                <div class="card-header card-header-gold">
                    <i class="fa fa-ranking-star me-2"></i>Leaderboard
                    <small style="float:right; font-size:0.75rem; font-weight:400; color:#333;">Score: 30% Hours • 50% Sit-ins (every 3 = 1 point) • 20% Tasks Completed</small>
                </div>
                <div class="card-body">
                    <?php
                    $leaderboard = getLeaderboardData($conn, 15);
                    displayLeaderboard($leaderboard, true);
                    ?>
                </div>
            </div>
        </div>
    </div>


<!-- ════ PHOTO MANAGEMENT MODAL ════ -->
<div class="modal fade" id="photoModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title"><i class="fa fa-camera me-2"></i>Manage Profile Photo</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <!-- Current Photo Preview -->
                <div id="photoPreview" style="margin-bottom: 20px;">
                    <img id="currentPhotoImg" src="" alt="Current Photo" style="max-width: 200px; max-height: 200px; border-radius: 8px; display: none;">
                    <div id="photoPlaceholder" style="width: 200px; height: 200px; margin: 0 auto; background-color: var(--purple); color: white; display: flex; align-items: center; justify-content: center; border-radius: 8px; font-size: 3rem;">
                        <i class="fa fa-image"></i>
                    </div>
                </div>

                <div id="photoMessage" style="display:none; margin-bottom:15px; padding:10px 12px; border-radius:6px; font-size:0.85rem;"></div>

                <!-- Upload Section -->
                <div style="margin-bottom: 15px;">
                    <input type="file" id="photoUploadInput" style="display:none;" accept="image/*" onchange="uploadPhoto(this)">
                    <button type="button" class="btn btn-sm" style="background-color:var(--purple); color:white; border:none; border-radius:8px; font-weight:600; width:100%; padding:8px 0;" onclick="document.getElementById('photoUploadInput').click();">
                        <i class="fa fa-cloud-arrow-up me-2"></i>Upload New Photo
                    </button>
                </div>

                <!-- Delete Button (only if photo exists) -->
                <div id="deletePhotoSection" style="display:none; margin-bottom: 15px;">
                    <button type="button" class="btn btn-sm btn-danger" style="border:none; border-radius:8px; font-weight:600; width:100%; padding:8px 0;" onclick="removePhoto()">
                        <i class="fa fa-trash me-2"></i>Delete Photo
                    </button>
                </div>

                <!-- Close Button -->
                <button type="button" class="btn btn-secondary btn-sm" style="border-radius:8px; width:100%; padding:8px 0;" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ════ EDIT PROFILE MODAL ════ -->
<div class="modal fade" id="editProfileModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title"><i class="fa fa-pen-to-square me-2"></i>Edit Profile</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="res-field-label">First Name</label>
                            <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($student['FirstName'] ?? ''); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="res-field-label">Last Name</label>
                            <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($student['LastName'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="res-field-label">Middle Name</label>
                        <input type="text" name="middle_name" class="form-control" value="<?php echo htmlspecialchars($student['MiddleName'] ?? ''); ?>">
                    </div>
                    <div class="mb-2">
                        <label class="res-field-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($student['Email'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-2">
                        <label class="res-field-label">Address</label>
                        <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($student['Address'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="res-field-label">New Password <span class="text-muted">(leave blank to keep current)</span></label>
                        <input type="password" name="password" class="form-control" placeholder="••••••••">
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_profile" class="btn btn-save btn-sm px-4">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<!-- ════ NOTIFICATIONS MODAL ════ -->
<div class="modal fade" id="notificationsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title">
                    <i class="fa fa-bell me-2"></i>Notifications
                    <?php if ($unread_count > 0): ?>
                        <span class="notif-badge" style="background:#dc3545;color:white;border-radius:20px;padding:1px 8px;font-size:0.72rem;font-weight:700;margin-left:6px;">
                            <?= $unread_count ?> new
                        </span>
                    <?php endif; ?>
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <?php
                $notif_stmt = $conn->prepare("SELECT * FROM notifications WHERE StudentID=? ORDER BY DateCreated DESC LIMIT 20");
                $notif_stmt->bind_param("s", $id);
                $notif_stmt->execute();
                $notifs = $notif_stmt->get_result();
                if ($notifs && $notifs->num_rows > 0):
                    while ($n = $notifs->fetch_assoc()):
                        $is_new    = !$n['IsRead'];
                        $icon_bg   = $is_new ? '#5c2b7a' : '#f3eaf9';
                        $icon_color= $is_new ? 'white' : 'var(--purple)';
                        $row_bg    = $is_new ? '#f8f4fc' : 'white';
                        $approved  = stripos($n['Message'], 'approved') !== false;
                        $icon      = $approved ? 'fa-circle-check' : 'fa-circle-xmark';
                        $icon_clr  = $approved ? '#198754' : '#dc3545';
                ?>
                    <div style="display:flex;gap:12px;align-items:flex-start;padding:12px 16px;background:<?= $row_bg ?>;border-bottom:1px solid #f0f0f0;">
                        <div style="width:36px;height:36px;min-width:36px;border-radius:50%;background:<?= $icon_bg ?>;display:flex;align-items:center;justify-content:center;">
                            <i class="fa <?= $icon ?>" style="color:<?= $is_new ? $icon_clr : 'var(--purple)' ?>;font-size:1rem;"></i>
                        </div>
                        <div style="flex:1;">
                            <div style="font-size:0.87rem;color:#333;line-height:1.4;<?= $is_new ? 'font-weight:600;' : '' ?>">
                                <?php echo htmlspecialchars($n['Message']); ?>
                            </div>
                            <div style="font-size:0.75rem;color:#bbb;margin-top:3px;">
                                <i class="fa fa-clock me-1"></i><?php echo htmlspecialchars($n['DateCreated']); ?>
                            </div>
                        </div>
                        <?php if ($is_new): ?>
                            <span style="width:8px;height:8px;min-width:8px;border-radius:50%;background:#dc3545;margin-top:5px;"></span>
                        <?php endif; ?>
                    </div>
                <?php endwhile; else: ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fa fa-bell-slash fa-2x mb-2 d-block" style="color:#ddd;"></i>
                        No notifications yet.
                    </div>
                <?php endif; $notif_stmt->close(); ?>
            </div>
        </div>
    </div>
</div>


<!-- ════ HISTORY MODAL ════ -->
<div class="modal fade" id="historyModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title"><i class="fa fa-clock-rotate-left me-2"></i>Sit-in History</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="background:var(--purple);color:white;">Date</th>
                                <th style="background:var(--purple);color:white;">Purpose</th>
                                <th style="background:var(--purple);color:white;">Lab</th>
                                <th style="background:var(--purple);color:white;">Time In</th>
                                <th style="background:var(--purple);color:white;">Time Out</th>
                                <th style="background:var(--purple);color:white;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $hs = $conn->prepare("SELECT * FROM sit_in_sessions WHERE StudentID=? ORDER BY SessionDate DESC");
                        $hs->bind_param("s", $id);
                        $hs->execute();
                        $hist = $hs->get_result();
                        if ($hist && $hist->num_rows > 0):
                            while ($h = $hist->fetch_assoc()):
                                $hb = match(strtolower($h['Status'])) {
                                    'active'    => 'badge-active',
                                    'approved'  => 'badge-approved',
                                    'completed' => 'badge-completed',
                                    'cancelled' => 'badge-cancelled',
                                    default     => 'badge-completed'
                                };
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($h['SessionDate']); ?></td>
                                <td><?php echo htmlspecialchars($h['Purpose'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($h['Lab'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($h['TimeIn']); ?></td>
                                <td><?php echo $h['TimeOut'] ? htmlspecialchars($h['TimeOut']) : '<span class="text-muted">—</span>'; ?></td>
                                <td><span class="<?= $hb ?>"><?php echo htmlspecialchars($h['Status']); ?></span></td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="6" class="text-center text-muted py-3"><i class="fa fa-inbox me-2"></i>No history found</td></tr>
                        <?php endif; $hs->close(); ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- ════ RESERVATIONS MODAL ════ -->
<div class="modal fade" id="reservationsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title"><i class="fa fa-calendar-check me-2"></i>Reservations</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">

                <?php if (isset($res_error)): ?>
                    <div class="alert alert-danger py-2 px-3 mb-3" style="border-radius:8px;font-size:0.85rem;">
                        <i class="fa fa-circle-exclamation me-2"></i><?= htmlspecialchars($res_error) ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($res_success)): ?>
                    <div class="alert alert-success py-2 px-3 mb-3" style="border-radius:8px;font-size:0.85rem;">
                        <i class="fa fa-circle-check me-2"></i><?= htmlspecialchars($res_success) ?>
                    </div>
                <?php endif; ?>

                <!-- Reservation Form -->
                <div style="background:#f8f4fc;border-radius:10px;padding:1.2rem;margin-bottom:1.2rem;">
                    <div style="font-size:0.72rem;color:var(--purple);font-weight:700;text-transform:uppercase;letter-spacing:0.07em;margin-bottom:12px;">
                        <i class="fa fa-plus me-1"></i>New Reservation
                    </div>
                    <form method="POST">
                        <div class="row g-2">
                            <div class="col-sm-6">
                                <label class="res-field-label">ID Number</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($student['IdNumber'] ?? ''); ?>" readonly style="background:#ede6f5;font-weight:600;">
                            </div>
                            <div class="col-sm-6">
                                <label class="res-field-label">Name</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars(($student['FirstName'] ?? '').' '.($student['LastName'] ?? '')); ?>" readonly style="background:#ede6f5;font-weight:600;">
                            </div>
                            <div class="col-sm-6">
                                <label class="res-field-label">Purpose</label>
                                <select name="res_purpose" class="form-select" required>
                                    <option value="">Select purpose</option>
                                    <option>C Programming</option>
                                    <option>Java Programming</option>
                                    <option>PHP Programming</option>
                                    <option>ASP.Net Programming</option>
                                    <option>C# Programming</option>
                                    <option>Database</option>
                                    <option>Research</option>
                                    <option>Other</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="res-field-label">Lab &amp; PC Selection</label>
                                <div class="d-flex gap-2 mb-2 flex-wrap" id="labButtonsWrap">
                                    <?php
                                    $labs_q = $conn->query("SELECT LabName, PCCount FROM labs ORDER BY LabName ASC");
                                    if ($labs_q && $labs_q->num_rows > 0):
                                        while ($lb = $labs_q->fetch_assoc()):
                                    ?>
                                        <button type="button" class="lab-btn btn btn-sm"
                                            data-lab="<?= htmlspecialchars($lb['LabName']) ?>"
                                            data-total="<?= (int)$lb['PCCount'] ?>"
                                            style="border:2px solid #ddd;border-radius:8px;background:white;color:#555;font-size:0.82rem;padding:5px 14px;transition:all 0.2s;">
                                            <i class="fa fa-door-open me-1"></i>Lab <?= htmlspecialchars($lb['LabName']) ?>
                                        </button>
                                    <?php endwhile; else: ?>
                                        <span style="font-size:0.78rem;color:#999;">No labs configured.</span>
                                    <?php endif; ?>
                                </div>
                                <input type="hidden" name="res_lab" id="res_lab_input" required>
                                <input type="hidden" name="res_pc"  id="res_pc_input">

                                <!-- PC Grid -->
                                <div id="pcPickerWrap" style="display:none;margin-top:10px;">
                                    <div style="font-size:0.72rem;color:var(--purple);font-weight:700;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:8px;">
                                        Select a PC — <span id="pcLabLabel"></span>
                                    </div>
                                    <div class="d-flex gap-3 mb-2 flex-wrap">
                                        <div style="display:flex;align-items:center;gap:5px;font-size:0.75rem;color:#666;">
                                            <div style="width:14px;height:14px;border-radius:4px;background:#e9f7ef;border:2px solid #198754;"></div> Available
                                        </div>
                                        <div style="display:flex;align-items:center;gap:5px;font-size:0.75rem;color:#666;">
                                            <div style="width:14px;height:14px;border-radius:4px;background:#fde8e8;border:2px solid #dc3545;"></div> Occupied
                                        </div>
                                        <div style="display:flex;align-items:center;gap:5px;font-size:0.75rem;color:#666;">
                                            <div style="width:14px;height:14px;border-radius:4px;background:#f3eaf9;border:2px solid #5c2b7a;"></div> Your Selection
                                        </div>
                                    </div>
                                    <div id="pcGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(52px,1fr));gap:8px;max-width:420px;"></div>
                                    <div id="pcPickerMsg" style="font-size:0.78rem;color:#888;margin-top:8px;"></div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <label class="res-field-label">Preferred Time In</label>
                                <input type="time" name="res_timein" class="form-control" required>
                            </div>
                            <div class="col-sm-6">
                                <label class="res-field-label">Date</label>
                                <input type="date" name="res_date" class="form-control" min="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                        <div class="mt-3">
                            <button type="submit" name="submit_reservation" class="btn btn-purple-solid px-4">
                                <i class="fa fa-calendar-plus me-2"></i>Submit Reservation
                            </button>
                        </div>
                    </form>
                </div>

                <!-- My Reservations List -->
                <div style="font-size:0.72rem;color:#999;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;margin-bottom:8px;">
                    <i class="fa fa-list me-1"></i>My Reservations
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" style="font-size:0.85rem;">
                        <thead>
                            <tr>
                                <th style="background:var(--purple);color:white;">Date</th>
                                <th style="background:var(--purple);color:white;">Purpose</th>
                                <th style="background:var(--purple);color:white;">Lab</th>
                                <th style="background:var(--purple);color:white;">PC</th>
                                <th style="background:var(--purple);color:white;">Time In</th>
                                <th style="background:var(--purple);color:white;">Status</th>
                                <th style="background:var(--purple);color:white;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $res = $conn->prepare("SELECT * FROM sit_in_sessions WHERE StudentID=? AND Type='Reservation' ORDER BY SessionDate DESC, TimeIn DESC");
                        $res->bind_param("s", $id);
                        $res->execute();
                        $reservations = $res->get_result();
                        if ($reservations && $reservations->num_rows > 0):
                            while ($r = $reservations->fetch_assoc()):
                                $rbadge = match(strtolower($r['Status'])) {
                                    'pending'   => 'badge-pending',
                                    'approved'  => 'badge-approved',
                                    'cancelled' => 'badge-cancelled',
                                    default     => 'badge-pending'
                                };
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($r['SessionDate']) ?></td>
                                <td><?= htmlspecialchars($r['Purpose']) ?></td>
                                <td><?= htmlspecialchars($r['Lab']) ?></td>
                                <td><?= $r['PCNumber'] ? 'PC '.$r['PCNumber'] : '<span class="text-muted">—</span>' ?></td>
                                <td><?= htmlspecialchars($r['TimeIn']) ?></td>
                                <td><span class="<?= $rbadge ?>"><?= htmlspecialchars($r['Status']) ?></span></td>
                                <td>
                                    <?php if (strtolower($r['Status']) === 'pending'): ?>
                                        <form method="POST" onsubmit="return confirm('Cancel this reservation?')">
                                            <input type="hidden" name="res_id" value="<?= $r['SessionID'] ?>">
                                            <button name="cancel_reservation" class="btn btn-sm btn-danger" style="font-size:0.75rem;padding:2px 8px;">
                                                <i class="fa fa-xmark me-1"></i>Cancel
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted" style="font-size:0.78rem;">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="6" class="text-center text-muted py-3">
                                <i class="fa fa-calendar-xmark me-2"></i>No reservations yet.
                            </td></tr>
                        <?php endif; $res->close(); ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>


<!-- ════ FEEDBACK MODAL ════ -->
<div class="modal fade" id="feedbackModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title"><i class="fa fa-star me-2"></i>Session Feedback</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="fb_session_info" style="background:#f8f4fc;border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:0.85rem;color:#555;"></div>
                <form method="POST" id="feedbackForm">
                    <input type="hidden" name="fb_session_id" id="fb_session_id">

                    <!-- Task Completion -->
                    <div class="mb-4">
                        <label style="font-size:0.8rem;color:#777;font-weight:600;display:block;margin-bottom:10px;">
                            <i class="fa fa-check-circle me-1" style="color:#198754;"></i>Was the task completed?
                        </label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="fb_task_completed" id="task_yes" value="1" required>
                                <label class="form-check-label" for="task_yes">Yes</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="fb_task_completed" id="task_no" value="0" required>
                                <label class="form-check-label" for="task_no">No</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="fb_task_completed" id="task_partial" value="0.5" required>
                                <label class="form-check-label" for="task_partial">Partially</label>
                            </div>
                        </div>
                    </div>

                    <!-- Star Rating -->
                    <div class="mb-3">
                        <label style="font-size:0.8rem;color:#777;font-weight:500;display:block;margin-bottom:8px;">Experience Rating</label>
                        <div class="star-rating d-flex gap-2">
                            <?php for($i=1;$i<=5;$i++): ?>
                            <label style="cursor:pointer;">
                                <input type="radio" name="fb_rating" value="<?= $i ?>" style="display:none;" required>
                                <i class="fa fa-star star-icon" data-val="<?= $i ?>"
                                    style="font-size:1.8rem;color:#ddd;transition:color 0.15s;"></i>
                            </label>
                            <?php endfor; ?>
                        </div>
                        <div id="ratingLabel" style="font-size:0.78rem;color:#999;margin-top:4px;"></div>
                    </div>

                    <!-- Message -->
                    <div class="mb-3">
                        <label style="font-size:0.8rem;color:#777;font-weight:500;">Comments <span class="text-muted">(optional)</span></label>
                        <textarea name="fb_message" class="form-control mt-1" rows="3"
                            placeholder="Share your experience in the lab..." style="border-radius:8px;font-size:0.88rem;"></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="submit_feedback" class="btn btn-sm btn-save px-4">
                            <i class="fa fa-paper-plane me-1"></i>Submit Feedback
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- AI Recommendations Modal -->
<div class="modal fade" id="recommendationsModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius:15px;border:none;box-shadow:0 25px 60px rgba(0,0,0,0.2);">
            <div class="modal-header" style="background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);border:none;border-radius:15px 15px 0 0;">
                <h6 class="modal-title" style="color:white;font-weight:700;font-size:1.1rem;">
                    <i class="fa fa-lightbulb me-2"></i>Personalized Recommendations
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding:0;max-height:70vh;overflow-y:auto;">
                <div id="recommendationsContainer"></div>
            </div>
            <div class="modal-footer" style="border-top:1px solid #e0e0e0;padding:15px;background:#f8f9fa;border-radius:0 0 15px 15px;">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal" style="background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);border:none;">
                    Got it, Thanks!
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ══════════════════════════════════════
   PHOTO MANAGEMENT WITH MODAL
══════════════════════════════════════ */

function openPhotoModal() {
    const modal = new bootstrap.Modal(document.getElementById('photoModal'));
    
    // Load current photo
    fetch(window.location.pathname + '?get_current_photo=1')
        .then(r => r.json())
        .then(data => {
            const imgEl = document.getElementById('currentPhotoImg');
            const placeholderEl = document.getElementById('photoPlaceholder');
            const deleteBtn = document.getElementById('deletePhotoSection');
            
            if (data.photo_path) {
                imgEl.src = data.photo_path;
                imgEl.style.display = 'block';
                placeholderEl.style.display = 'none';
                deleteBtn.style.display = 'block';
            } else {
                imgEl.style.display = 'none';
                placeholderEl.style.display = 'flex';
                deleteBtn.style.display = 'none';
            }
        });
    
    modal.show();
}

function uploadPhoto(input) {
    if (!input.files || !input.files[0]) return;
    
    const file = input.files[0];
    const messageEl = document.getElementById('photoMessage');
    
    // Validate file size (5MB max)
    if (file.size > 5 * 1024 * 1024) {
        showPhotoMessage('error', 'File size must not exceed 5MB.');
        input.value = '';
        return;
    }
    
    // Validate file type
    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!allowedTypes.includes(file.type)) {
        showPhotoMessage('error', 'Only JPG, PNG, and GIF files are allowed.');
        input.value = '';
        return;
    }
    
    // Show loading state
    showPhotoMessage('info', 'Uploading photo...');
    
    const formData = new FormData();
    formData.append('photo', file);
    formData.append('upload_photo', '1');
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        input.value = '';
        if (data.status === 'ok') {
            showPhotoMessage('success', 'Photo uploaded successfully!');
            setTimeout(() => location.reload(), 1500);
        } else {
            showPhotoMessage('error', data.message || 'Upload failed.');
        }
    })
    .catch(err => {
        showPhotoMessage('error', 'Upload error: ' + err.message);
        input.value = '';
    });
}

function removePhoto() {
    if (!confirm('Remove your profile photo?')) return;
    
    showPhotoMessage('info', 'Removing photo...');
    
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'remove_photo=1'
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'ok') {
            showPhotoMessage('success', 'Photo removed successfully!');
            setTimeout(() => location.reload(), 1500);
        } else {
            showPhotoMessage('error', 'Remove failed.');
        }
    })
    .catch(err => {
        showPhotoMessage('error', 'Error: ' + err.message);
    });
}

function showPhotoMessage(type, text) {
    const messageEl = document.getElementById('photoMessage');
    const bgColor = type === 'success' ? '#d4edda' : (type === 'error' ? '#f8d7da' : '#d1ecf1');
    const textColor = type === 'success' ? '#155724' : (type === 'error' ? '#721c24' : '#0c5460');
    const icon = type === 'success' ? 'fa-check-circle' : (type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle');
    
    messageEl.style.backgroundColor = bgColor;
    messageEl.style.color = textColor;
    messageEl.innerHTML = '<i class="fa ' + icon + ' me-2"></i>' + text;
    messageEl.style.display = 'block';
}

// Auto-open reservations modal if there was a form submission result
<?php if (isset($res_error) || isset($res_success)): ?>
document.addEventListener('DOMContentLoaded', function () {
    new bootstrap.Modal(document.getElementById('reservationsModal')).show();
});
<?php endif; ?>

// Mark notifications as read via AJAX when modal opens
function markNotificationsRead() {
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'mark_notifications_read=1'
    }).then(() => {
        document.querySelectorAll('.notif-badge').forEach(el => el.remove());
    });
}

/* ── AI Recommendations ── */
function loadRecommendations() {
    fetch('?get_recommendations=1')
        .then(response => response.json())
        .then(recommendations => {
            if (recommendations && recommendations.length > 0) {
                displayRecommendations(recommendations);
                // Show modal automatically on first load
                setTimeout(() => {
                    new bootstrap.Modal(document.getElementById('recommendationsModal')).show();
                }, 500);
            }
        })
        .catch(error => console.error('Error loading recommendations:', error));
}

function displayRecommendations(recommendations) {
    const container = document.getElementById('recommendationsContainer');
    container.innerHTML = '';
    
    recommendations.forEach((rec, index) => {
        const priorityClass = rec.priority === 'high' ? 'danger' : 
                            rec.priority === 'medium' ? 'warning' : 'info';
        const priorityBadge = rec.priority === 'high' ? '<span class="badge bg-danger ms-2">Important</span>' : '';
        
        const recHTML = `
            <div class="recommendation-item p-4 border-bottom" style="border-left: 4px solid ${rec.color}; background-color: rgba(0,0,0,0.01);">
                <div class="d-flex align-items-start">
                    <div style="font-size:2rem;margin-right:15px;color:${rec.color};">
                        <i class="fa ${rec.icon}"></i>
                    </div>
                    <div style="flex:1;">
                        <h6 style="margin:0 0 5px 0;color:#333;font-weight:700;font-size:1rem;">
                            ${rec.title}
                            ${priorityBadge}
                        </h6>
                        <p style="margin:5px 0 0 0;color:#666;font-size:0.95rem;line-height:1.5;">
                            ${rec.message}
                        </p>
                    </div>
                </div>
            </div>
        `;
        container.innerHTML += recHTML;
    });
}

// Load recommendations when page loads
window.addEventListener('load', function() {
    loadRecommendations();
});

/* ── Feedback Modal ── */
const ratingLabels = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];

function openFeedback(sessionId, date, purpose) {
    document.getElementById('fb_session_id').value = sessionId;
    document.getElementById('fb_session_info').innerHTML =
        '<i class="fa fa-calendar me-1" style="color:var(--purple);"></i><b>Date:</b> ' + date +
        ' &nbsp;|&nbsp; <i class="fa fa-code me-1" style="color:var(--purple);"></i><b>Purpose:</b> ' + (purpose || '—');
    // Reset task completion
    document.querySelectorAll('input[name="fb_task_completed"]').forEach(r => r.checked = false);
    // Reset stars
    document.querySelectorAll('.star-icon').forEach(s => s.style.color = '#ddd');
    document.querySelectorAll('input[name="fb_rating"]').forEach(r => r.checked = false);
    document.getElementById('ratingLabel').innerText = '';
    new bootstrap.Modal(document.getElementById('feedbackModal')).show();
}

// Star hover and click
document.querySelectorAll('.star-icon').forEach(star => {
    star.addEventListener('mouseover', function() {
        const val = parseInt(this.dataset.val);
        document.querySelectorAll('.star-icon').forEach((s, i) => {
            s.style.color = i < val ? '#c09412' : '#ddd';
        });
    });
    star.addEventListener('mouseout', function() {
        const checked = document.querySelector('input[name="fb_rating"]:checked');
        const val = checked ? parseInt(checked.value) : 0;
        document.querySelectorAll('.star-icon').forEach((s, i) => {
            s.style.color = i < val ? '#c09412' : '#ddd';
        });
    });
    star.addEventListener('click', function() {
        const val = parseInt(this.dataset.val);
        const radio = document.querySelector('input[name="fb_rating"][value="' + val + '"]');
        if (radio) radio.checked = true;
        document.querySelectorAll('.star-icon').forEach((s, i) => {
            s.style.color = i < val ? '#c09412' : '#ddd';
        });
        document.getElementById('ratingLabel').innerHTML =
            '<span style="color:var(--gold);font-weight:600;">' + ratingLabels[val] + '</span>';
    });
});

/* ══════════════════════════════════════
   PC PICKER
══════════════════════════════════════ */

document.querySelectorAll('.lab-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        // Highlight selected lab button
        document.querySelectorAll('.lab-btn').forEach(b => {
            b.style.borderColor = '#ddd';
            b.style.background  = 'white';
            b.style.color       = '#555';
        });
        this.style.borderColor = '#5c2b7a';
        this.style.background  = '#f3eaf9';
        this.style.color       = '#5c2b7a';

        const lab  = this.dataset.lab;
        const date = document.querySelector('[name="res_date"]')?.value || '';
        document.getElementById('res_lab_input').value = lab;
        document.getElementById('res_pc_input').value  = '';
        document.getElementById('pcLabLabel').innerText = 'Lab ' + lab;
        document.getElementById('pcPickerWrap').style.display = 'block';
        document.getElementById('pcPickerMsg').innerText = '';
        loadPCs(lab, date);
    });
});

// Re-load PCs when date changes
const resDateInput = document.querySelector('[name="res_date"]');
if (resDateInput) {
    resDateInput.addEventListener('change', function () {
        const lab = document.getElementById('res_lab_input').value;
        if (lab) loadPCs(lab, this.value);
    });
}

function loadPCs(lab, date) {
    const grid = document.getElementById('pcGrid');
    grid.innerHTML = '<div style="grid-column:1/-1;font-size:0.78rem;color:#999;padding:8px 0;">Loading PCs...</div>';

    const url = window.location.pathname + '?get_pcs=1&lab=' + encodeURIComponent(lab) + '&date=' + encodeURIComponent(date);
    fetch(url)
        .then(r => r.json())
        .then(data => {
            const occupied = (data.occupied || []).map(Number);
            const total    = data.total || 40;
            grid.innerHTML = '';
            let available  = 0;

            for (let i = 1; i <= total; i++) {
                const isOcc = occupied.includes(i);
                if (!isOcc) available++;

                const pc = document.createElement('div');
                pc.dataset.pc    = i;
                pc.title         = 'PC ' + i + (isOcc ? ' — Occupied' : ' — Available');
                pc.innerHTML     = '<i class="fa fa-desktop" style="font-size:1rem;display:block;margin-bottom:2px;"></i>' + i;
                pc.style.cssText =
                    'border-radius:8px;padding:6px 4px;text-align:center;font-size:0.72rem;font-weight:700;' +
                    'cursor:' + (isOcc ? 'not-allowed' : 'pointer') + ';' +
                    'border:2px solid ' + (isOcc ? '#dc3545' : '#198754') + ';' +
                    'background:' + (isOcc ? '#fde8e8' : '#e9f7ef') + ';' +
                    'color:' + (isOcc ? '#dc3545' : '#198754') + ';' +
                    'transition:all 0.15s;';

                if (!isOcc) {
                    pc.addEventListener('click', function () {
                        grid.querySelectorAll('div[data-pc]').forEach(p => {
                            if (!occupied.includes(Number(p.dataset.pc))) {
                                p.style.borderColor = '#198754';
                                p.style.background  = '#e9f7ef';
                                p.style.color       = '#198754';
                            }
                        });
                        this.style.borderColor = '#5c2b7a';
                        this.style.background  = '#f3eaf9';
                        this.style.color       = '#5c2b7a';
                        document.getElementById('res_pc_input').value = this.dataset.pc;
                        document.getElementById('pcPickerMsg').innerHTML =
                            '<i class="fa fa-check-circle me-1" style="color:#198754;"></i>PC ' + this.dataset.pc + ' selected';
                    });
                }
                grid.appendChild(pc);
            }

            document.getElementById('pcPickerMsg').innerHTML =
                '<i class="fa fa-circle-info me-1" style="color:var(--purple);"></i>' +
                available + ' of ' + total + ' PCs available — click a green PC to reserve';
        })
        .catch(() => {
            grid.innerHTML = '<div style="grid-column:1/-1;font-size:0.78rem;color:#dc3545;padding:8px 0;">Could not load PC availability.</div>';
        });
}
</script>
</body>
</html>