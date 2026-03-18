<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    header("Location: Loginpage.php");
    exit();
}

include "db.php";

// Check if admin
$chk = $conn->prepare("SELECT is_admin FROM students_info WHERE IdNumber = ?");
$chk->bind_param("s", $_SESSION['user_id']);
$chk->execute();
$chk_row = $chk->get_result()->fetch_assoc();
$chk->close();

if (!$chk_row || !$chk_row['is_admin']) {
    header("Location: student_dashboard.php");
    exit();
}

// ── Handle POST actions ──────────────────────────────────────────────

// Post announcement
if (isset($_POST['post_announcement'])) {
    $title   = trim($_POST['ann_title']);
    $message = trim($_POST['ann_message']);
    if ($title && $message) {
        $s = $conn->prepare("INSERT INTO announcements (Title, Message) VALUES (?, ?)");
        $s->bind_param("ss", $title, $message);
        $s->execute(); $s->close();
    }
    header("Location: admin_dashboard.php"); exit();
}

// Delete announcement
if (isset($_POST['delete_announcement'])) {
    $aid = (int)$_POST['ann_id'];
    $s = $conn->prepare("DELETE FROM announcements WHERE AnnouncementID = ?");
    $s->bind_param("i", $aid);
    $s->execute(); $s->close();
    header("Location: admin_dashboard.php"); exit();
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
    $sid = $_POST['del_id'];
    $s = $conn->prepare("DELETE FROM students_info WHERE IdNumber = ? AND is_admin = 0");
    $s->bind_param("s", $sid);
    $s->execute(); $s->close();
    header("Location: admin_dashboard.php?tab=students"); exit();
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
    $date    = date('Y-m-d');
    $timein  = date('H:i:s');
    $chk2 = $conn->prepare("SELECT SessionID FROM sit_in_sessions WHERE StudentID=? AND Status='Active'");
    $chk2->bind_param("s", $sid);
    $chk2->execute();
    if ($chk2->get_result()->num_rows > 0) {
        $sitin_error = "Student already has an active session.";
    } else {
        $ins = $conn->prepare("INSERT INTO sit_in_sessions (StudentID, SessionDate, TimeIn, Purpose, Lab, Status) VALUES (?,?,?,?,?,'Active')");
        $ins->bind_param("sssss", $sid, $date, $timein, $purpose, $lab);
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
    $s = $conn->prepare("UPDATE sit_in_sessions SET Status='Approved' WHERE SessionID=? AND Type='Reservation'");
    $s->bind_param("i", $rid);
    $s->execute(); $s->close();
    header("Location: admin_dashboard.php?tab=reservations"); exit();
}

// Reject reservation
if (isset($_POST['reject_reservation'])) {
    $rid = (int)$_POST['res_id'];
    $s = $conn->prepare("UPDATE sit_in_sessions SET Status='Cancelled' WHERE SessionID=? AND Type='Reservation'");
    $s->bind_param("i", $rid);
    $s->execute(); $s->close();
    header("Location: admin_dashboard.php?tab=reservations"); exit();
}

// Reset all sessions
if (isset($_POST['reset_all_sessions'])) {
    $conn->query("UPDATE sit_in_sessions SET Status='Completed', TimeOut=NOW() WHERE Status='Active'");
    header("Location: admin_dashboard.php?tab=sitin"); exit();
}

// ── Stats ────────────────────────────────────────────────────────────
$total_students = $conn->query("SELECT COUNT(*) as c FROM students_info WHERE is_admin=0")->fetch_assoc()['c'];
$currently_sitin= $conn->query("SELECT COUNT(*) as c FROM sit_in_sessions WHERE Status='Active'")->fetch_assoc()['c'];
$total_sitin    = $conn->query("SELECT COUNT(*) as c FROM sit_in_sessions")->fetch_assoc()['c'];

$active_tab = $_GET['tab'] ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCS | Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { --purple:#5c2b7a; --gold:#c09412; }

        body { background:#f4f6fb; margin:0; }

        /* ── Sidebar ── */
        .sidebar {
            width: 230px; min-height: 100vh;
            background: var(--purple);
            position: fixed; top:0; left:0;
            display: flex; flex-direction: column;
            z-index: 100;
        }
        .sidebar-logo {
            padding: 20px 16px 12px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar-logo img { height: 38px; background:var(--purple); }
        .sidebar-logo span {
            display: block; color: white;
            font-size: 0.78rem; font-weight: 600;
            margin-top: 8px; line-height: 1.3;
        }

        .sidebar-nav { padding: 12px 0; flex: 1; }
        .nav-item-label {
            font-size: 0.65rem; text-transform: uppercase;
            letter-spacing: 0.1em; color: rgba(255,255,255,0.4);
            padding: 10px 16px 4px;
        }
        .sidebar-link {
            display: flex; align-items: center; gap: 10px;
            padding: 9px 16px; color: rgba(255,255,255,0.75);
            text-decoration: none; font-size: 0.88rem;
            border-left: 3px solid transparent;
            transition: all 0.2s;
        }
        .sidebar-link:hover { background: rgba(255,255,255,0.08); color: white; }
        .sidebar-link.active {
            background: rgba(255,255,255,0.13);
            border-left-color: var(--gold);
            color: white; font-weight: 600;
        }
        .sidebar-link i { width: 18px; text-align: center; font-size: 0.9rem; }

        .sidebar-footer {
            padding: 12px 16px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        .btn-logout-side {
            display: flex; align-items: center; gap: 8px;
            width: 100%; padding: 8px 12px;
            background: rgba(255,255,255,0.1);
            border: none; border-radius: 8px;
            color: rgba(255,255,255,0.8); font-size: 0.85rem;
            cursor: pointer; transition: background 0.2s;
        }
        .btn-logout-side:hover { background: rgba(255,255,255,0.2); color: white; }

        /* ── Main content ── */
        .main-content { margin-left: 230px; padding: 24px; }

        /* ── Topbar ── */
        .topbar {
            display: flex; align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }
        .topbar h4 { color: var(--purple); font-weight: 800; margin: 0; }
        .admin-badge {
            background: var(--purple); color: white;
            border-radius: 20px; padding: 4px 14px;
            font-size: 0.8rem; font-weight: 600;
        }

        /* ── Stat cards ── */
        .stat-card {
            background: white; border-radius: 14px;
            padding: 1.2rem 1.4rem;
            box-shadow: 0 4px 16px rgba(92,43,122,0.08);
            display: flex; align-items: center; gap: 16px;
        }
        .stat-icon {
            width: 52px; height: 52px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem;
        }
        .stat-icon.purple { background: #f3eaf9; color: var(--purple); }
        .stat-icon.gold   { background: #fdf6e3; color: var(--gold); }
        .stat-icon.green  { background: #e9f7ef; color: #27ae60; }
        .stat-num  { font-size: 1.7rem; font-weight: 800; color: #333; line-height: 1; }
        .stat-label{ font-size: 0.78rem; color: #999; margin-top: 3px; }

        /* ── Cards ── */
        .dash-card {
            background: white; border-radius: 14px; border: none;
            box-shadow: 0 4px 16px rgba(92,43,122,0.07);
        }
        .card-header-purple {
            background: var(--purple); color: white; font-weight: 600;
            border-radius: 14px 14px 0 0 !important; padding: 10px 16px;
        }
        .card-header-gold {
            background: var(--gold); color: #1a1a1a; font-weight: 600;
            border-radius: 14px 14px 0 0 !important; padding: 10px 16px;
        }

        /* ── Tables ── */
        .table thead th {
            background: var(--purple); color: white;
            font-size: 0.82rem; border: none;
        }
        .table tbody tr:hover { background: #f3eaf9; }
        .table td, .table th { vertical-align: middle; font-size: 0.88rem; }

        .badge-active    { background:#198754; color:white; }
        .badge-completed { background:#6c757d; color:white; }

        /* ── Buttons ── */
        .btn-purple { background:var(--purple); color:white; border:none; border-radius:7px; }
        .btn-purple:hover { background:#4a2263; color:white; }
        .btn-gold   { background:var(--gold); color:#1a1a1a; border:none; border-radius:7px; }
        .btn-gold:hover { background:#a87e0f; color:white; }

        /* ── Announcement items ── */
        .ann-item {
            padding: 12px 0; border-bottom: 1px solid #f0f0f0;
        }
        .ann-item:last-child { border-bottom: none; }
        .ann-item h6 { color:var(--purple); font-weight:700; margin-bottom:3px; font-size:0.9rem; }

        /* ── Modal ── */
        .modal-header { background:var(--purple); color:white; border-radius:12px 12px 0 0; }
        .modal-header .btn-close { filter:invert(1); }
        .modal-content { border-radius:12px; border:none; box-shadow:0 20px 50px rgba(0,0,0,0.15); }
        .form-control:focus, .form-select:focus {
            border-color:var(--purple);
            box-shadow:0 0 0 3px rgba(92,43,122,0.12);
        }

        /* ── Search box ── */
        .search-box {
            border-radius: 8px; border: 1px solid #ddd;
            padding: 0.4rem 0.75rem; font-size: 0.88rem;
        }
        .search-box:focus { border-color:var(--purple); outline:none; box-shadow:0 0 0 3px rgba(92,43,122,0.1); }
    </style>
</head>
<body>

<!-- ════ SIDEBAR ════ -->
<div class="sidebar">
    <div class="sidebar-logo">
        <img src="UCLogo-removebg-preview.png" alt="UC">
        <span>CCS Admin<br>Sit-in Monitoring</span>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-item-label">Main</div>
        <a href="admin_dashboard.php" class="sidebar-link <?= $active_tab==='dashboard' ? 'active':'' ?>">
            <i class="fa fa-gauge"></i> Dashboard
        </a>
        <a href="admin_dashboard.php?tab=students" class="sidebar-link <?= $active_tab==='students' ? 'active':'' ?>">
            <i class="fa fa-users"></i> Students
        </a>
        <a href="admin_dashboard.php?tab=sitinform" class="sidebar-link <?= $active_tab==='sitinform' ? 'active':'' ?>">
            <i class="fa fa-right-to-bracket"></i> Sit-in
        </a>
        <a href="admin_dashboard.php?tab=sitin" class="sidebar-link <?= $active_tab==='sitin' ? 'active':'' ?>">
            <i class="fa fa-desktop"></i> Current Sit-in
        </a>
        <a href="admin_dashboard.php?tab=records" class="sidebar-link <?= $active_tab==='records' ? 'active':'' ?>">
            <i class="fa fa-table-list"></i> Sit-in Records
        </a>

        <div class="nav-item-label">Manage</div>
        <a href="admin_dashboard.php?tab=announcements" class="sidebar-link <?= $active_tab==='announcements' ? 'active':'' ?>">
            <i class="fa fa-bullhorn"></i> Announcements
        </a>
        <a href="admin_dashboard.php?tab=reservations" class="sidebar-link <?= $active_tab==='reservations' ? 'active':'' ?>">
            <i class="fa fa-calendar-check"></i> Reservations
        </a>
    </nav>

    <div class="sidebar-footer">
        <form method="POST" action="logout.php">
            <button class="btn-logout-side">
                <i class="fa fa-right-from-bracket"></i> Log Out
            </button>
        </form>
    </div>
</div>

<!-- ════ MAIN CONTENT ════ -->
<div class="main-content">

    <!-- Topbar -->
    <div class="topbar">
        <h4>
            <?php
            $titles = [
                'dashboard'     => '<i class="fa fa-gauge me-2"></i>Dashboard',
                'students'      => '<i class="fa fa-users me-2"></i>Students Information',
                'sitinform'     => '<i class="fa fa-right-to-bracket me-2"></i>Sit-in Student',
                'sitin'         => '<i class="fa fa-desktop me-2"></i>Current Sit-in',
                'records'       => '<i class="fa fa-table-list me-2"></i>Sit-in Records',
                'announcements' => '<i class="fa fa-bullhorn me-2"></i>Announcements',
                'reservations'  => '<i class="fa fa-calendar-check me-2"></i>Reservations',
            ];
            echo $titles[$active_tab] ?? $titles['dashboard'];
            ?>
        </h4>
        <span class="admin-badge"><i class="fa fa-shield-halved me-1"></i>CCS Admin</span>
    </div>


    <!-- ══════════════ DASHBOARD TAB ══════════════ -->
    <?php if ($active_tab === 'dashboard'): ?>

    <!-- Stat cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fa fa-users"></i></div>
                <div>
                    <div class="stat-num"><?= $total_students ?></div>
                    <div class="stat-label">Students Registered</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon green"><i class="fa fa-desktop"></i></div>
                <div>
                    <div class="stat-num"><?= $currently_sitin ?></div>
                    <div class="stat-label">Currently Sit-in</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon gold"><i class="fa fa-clock-rotate-left"></i></div>
                <div>
                    <div class="stat-num"><?= $total_sitin ?></div>
                    <div class="stat-label">Total Sit-in Sessions</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts + Announcements -->
    <div class="row g-4">
        <!-- Pie chart: sessions by purpose -->
        <div class="col-md-5">
            <div class="dash-card h-100">
                <div class="card-header-purple">
                    <i class="fa fa-chart-pie me-2"></i>Sessions by Purpose
                </div>
                <div class="card-body d-flex align-items-center justify-content-center" style="min-height:260px;">
                    <canvas id="purposeChart" style="max-height:240px;"></canvas>
                </div>
            </div>
        </div>

        <!-- Recent announcements -->
        <div class="col-md-7">
            <div class="dash-card h-100">
                <div class="card-header-gold d-flex justify-content-between align-items-center">
                    <span><i class="fa fa-bullhorn me-2"></i>Recent Announcements</span>
                    <a href="admin_dashboard.php?tab=announcements" class="btn btn-sm btn-purple px-3" style="font-size:0.78rem;">Manage</a>
                </div>
                <div class="card-body">
                    <?php
                    $ann = $conn->query("SELECT * FROM announcements ORDER BY DatePosted DESC LIMIT 4");
                    if ($ann && $ann->num_rows > 0):
                        while ($a = $ann->fetch_assoc()):
                    ?>
                        <div class="ann-item">
                            <h6><?= htmlspecialchars($a['Title']) ?></h6>
                            <p class="mb-1 text-muted" style="font-size:0.82rem;"><?= htmlspecialchars($a['Message']) ?></p>
                            <small class="text-muted"><i class="fa fa-calendar me-1"></i><?= $a['DatePosted'] ?></small>
                        </div>
                    <?php endwhile; else: ?>
                        <p class="text-muted mb-0">No announcements yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php
    // Get purpose data for chart
    $pc = $conn->query("SELECT Purpose, COUNT(*) as cnt FROM sit_in_sessions GROUP BY Purpose");
    $p_labels = []; $p_data = [];
    if ($pc) while ($r = $pc->fetch_assoc()) { $p_labels[] = $r['Purpose']; $p_data[] = $r['cnt']; }
    ?>
    <script>
    const ctx = document.getElementById('purposeChart');
    new Chart(ctx, {
        type: 'pie',
        data: {
            labels: <?= json_encode($p_labels) ?>,
            datasets: [{ data: <?= json_encode($p_data) ?>,
                backgroundColor: ['#5c2b7a','#c09412','#27ae60','#2980b9','#e74c3c','#8e44ad','#f39c12'],
            }]
        },
        options: { plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } }, responsive: true }
    });
    </script>


    <!-- ══════════════ STUDENTS TAB ══════════════ -->
    <?php elseif ($active_tab === 'students'): ?>

    <div class="dash-card">
        <div class="card-header-purple d-flex justify-content-between align-items-center">
            <span><i class="fa fa-users me-2"></i>All Students</span>
            <div class="d-flex gap-2 align-items-center">
                <input type="text" id="studentSearch" class="search-box" placeholder="Search..." style="width:180px;">
                <form method="POST" onsubmit="return confirm('Reset ALL active sessions?')">
                    <button name="reset_all_sessions" class="btn btn-sm btn-gold px-3">
                        <i class="fa fa-rotate me-1"></i>Reset All Sessions
                    </button>
                </form>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="studentTable">
                    <thead>
                        <tr>
                            <th>ID Number</th>
                            <th>Name</th>
                            <th>Year Level</th>
                            <th>Course</th>
                            <th>Email</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $stu = $conn->query("SELECT * FROM students_info WHERE is_admin=0 ORDER BY LastName ASC");
                    if ($stu && $stu->num_rows > 0):
                        while ($s = $stu->fetch_assoc()):
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($s['IdNumber']) ?></td>
                            <td><?= htmlspecialchars($s['FirstName'].' '.$s['LastName']) ?></td>
                            <td>Year <?= $s['CourseLevel'] ?></td>
                            <td><?= htmlspecialchars($s['Course']) ?></td>
                            <td style="font-size:0.8rem"><?= htmlspecialchars($s['Email']) ?></td>
                            <td>
                                <button class="btn btn-sm btn-purple me-1"
                                    onclick="openEdit(
                                        '<?= htmlspecialchars($s['IdNumber']) ?>',
                                        '<?= htmlspecialchars($s['FirstName']) ?>',
                                        '<?= htmlspecialchars($s['LastName']) ?>',
                                        '<?= htmlspecialchars($s['Course']) ?>',
                                        '<?= $s['CourseLevel'] ?>'
                                    )">
                                    <i class="fa fa-pen"></i> Edit
                                </button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this student?')">
                                    <input type="hidden" name="del_id" value="<?= $s['IdNumber'] ?>">
                                    <button name="delete_student" class="btn btn-sm btn-danger">
                                        <i class="fa fa-trash"></i> Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="6" class="text-center text-muted py-3">No students found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Edit Student Modal -->
    <div class="modal fade" id="editStudentModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title"><i class="fa fa-pen me-2"></i>Edit Student</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="edit_id" id="edit_id">
                        <div class="mb-3">
                            <label class="form-label" style="font-size:0.8rem;color:#777;">First Name</label>
                            <input type="text" name="edit_first" id="edit_first" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" style="font-size:0.8rem;color:#777;">Last Name</label>
                            <input type="text" name="edit_last" id="edit_last" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" style="font-size:0.8rem;color:#777;">Course</label>
                            <input type="text" name="edit_course" id="edit_course" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" style="font-size:0.8rem;color:#777;">Year Level</label>
                            <select name="edit_level" id="edit_level" class="form-select">
                                <option value="1">1st Year</option>
                                <option value="2">2nd Year</option>
                                <option value="3">3rd Year</option>
                                <option value="4">4th Year</option>
                            </select>
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="edit_student" class="btn btn-sm btn-gold px-4">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>


    <!-- ══════════════ CURRENT SIT-IN TAB ══════════════ -->
    <?php elseif ($active_tab === 'sitin'): ?>

    <div class="dash-card">
        <div class="card-header-purple d-flex justify-content-between align-items-center">
            <span><i class="fa fa-desktop me-2"></i>Currently Active Sessions</span>
            <form method="POST" onsubmit="return confirm('Log out ALL active sessions?')">
                <button name="reset_all_sessions" class="btn btn-sm btn-gold px-3">
                    <i class="fa fa-rotate me-1"></i>Reset All Sessions
                </button>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Sit ID</th>
                            <th>ID Number</th>
                            <th>Name</th>
                            <th>Purpose</th>
                            <th>Lab</th>
                            <th>Time In</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $sit = $conn->query("
                        SELECT s.*, st.FirstName, st.LastName
                        FROM sit_in_sessions s
                        JOIN students_info st ON s.StudentID = st.IdNumber
                        WHERE s.Status = 'Active'
                        ORDER BY s.SessionDate DESC
                    ");
                    if ($sit && $sit->num_rows > 0):
                        while ($r = $sit->fetch_assoc()):
                    ?>
                        <tr>
                            <td><?= $r['SessionID'] ?></td>
                            <td><?= htmlspecialchars($r['StudentID']) ?></td>
                            <td><?= htmlspecialchars($r['FirstName'].' '.$r['LastName']) ?></td>
                            <td><?= htmlspecialchars($r['Purpose'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($r['Lab'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($r['TimeIn']) ?></td>
                            <td><span class="badge badge-active">Active</span></td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="session_id" value="<?= $r['SessionID'] ?>">
                                    <button name="logout_session" class="btn btn-sm btn-danger">
                                        <i class="fa fa-right-from-bracket me-1"></i>Logout
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="8" class="text-center text-muted py-3">
                            <i class="fa fa-desktop me-2"></i>No active sit-in sessions.
                        </td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>


    <!-- ══════════════ SIT-IN RECORDS TAB ══════════════ -->
    <?php elseif ($active_tab === 'records'): ?>

    <div class="dash-card">
        <div class="card-header-purple d-flex justify-content-between align-items-center">
            <span><i class="fa fa-table-list me-2"></i>All Sit-in Records</span>
            <input type="text" id="recordSearch" class="search-box" placeholder="Search..." style="width:180px;">
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="recordTable">
                    <thead>
                        <tr>
                            <th>Sit ID</th>
                            <th>ID Number</th>
                            <th>Name</th>
                            <th>Purpose</th>
                            <th>Lab</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $rec = $conn->query("
                        SELECT s.*, st.FirstName, st.LastName
                        FROM sit_in_sessions s
                        JOIN students_info st ON s.StudentID = st.IdNumber
                        ORDER BY s.SessionDate DESC, s.TimeIn DESC
                    ");
                    if ($rec && $rec->num_rows > 0):
                        while ($r = $rec->fetch_assoc()):
                            $rb = strtolower($r['Status']) === 'active' ? 'badge-active' : 'badge-completed';
                    ?>
                        <tr>
                            <td><?= $r['SessionID'] ?></td>
                            <td><?= htmlspecialchars($r['StudentID']) ?></td>
                            <td><?= htmlspecialchars($r['FirstName'].' '.$r['LastName']) ?></td>
                            <td><?= htmlspecialchars($r['Purpose'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($r['Lab'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($r['TimeIn']) ?></td>
                            <td><?= $r['TimeOut'] ? htmlspecialchars($r['TimeOut']) : '<span class="text-muted">—</span>' ?></td>
                            <td><?= htmlspecialchars($r['SessionDate']) ?></td>
                            <td><span class="badge <?= $rb ?>"><?= htmlspecialchars($r['Status']) ?></span></td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="9" class="text-center text-muted py-3">No records found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>


    <!-- ══════════════ ANNOUNCEMENTS TAB ══════════════ -->
    <?php elseif ($active_tab === 'announcements'): ?>

    <div class="row g-4">
        <!-- Post new -->
        <div class="col-md-5">
            <div class="dash-card">
                <div class="card-header-purple">
                    <i class="fa fa-plus me-2"></i>Post New Announcement
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label" style="font-size:0.8rem;color:#777;">Title</label>
                            <input type="text" name="ann_title" class="form-control" placeholder="Announcement title" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" style="font-size:0.8rem;color:#777;">Message</label>
                            <textarea name="ann_message" class="form-control" rows="5" placeholder="Write your announcement here..." required></textarea>
                        </div>
                        <button type="submit" name="post_announcement" class="btn btn-gold w-100">
                            <i class="fa fa-paper-plane me-2"></i>Post Announcement
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Posted announcements -->
        <div class="col-md-7">
            <div class="dash-card">
                <div class="card-header-gold">
                    <i class="fa fa-list me-2"></i>Posted Announcements
                </div>
                <div class="card-body">
                    <?php
                    $ann = $conn->query("SELECT * FROM announcements ORDER BY DatePosted DESC");
                    if ($ann && $ann->num_rows > 0):
                        while ($a = $ann->fetch_assoc()):
                    ?>
                        <div class="ann-item d-flex justify-content-between align-items-start">
                            <div>
                                <h6><?= htmlspecialchars($a['Title']) ?></h6>
                                <p class="mb-1 text-muted" style="font-size:0.82rem;"><?= htmlspecialchars($a['Message']) ?></p>
                                <small class="text-muted"><i class="fa fa-calendar me-1"></i><?= $a['DatePosted'] ?></small>
                            </div>
                            <form method="POST" onsubmit="return confirm('Delete this announcement?')" class="ms-3">
                                <input type="hidden" name="ann_id" value="<?= $a['AnnouncementID'] ?>">
                                <button name="delete_announcement" class="btn btn-sm btn-danger">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    <?php endwhile; else: ?>
                        <p class="text-muted mb-0">No announcements posted yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>


    <!-- ══════════════ RESERVATIONS TAB ══════════════ -->
    <?php elseif ($active_tab === 'reservations'): ?>

    <?php
    $pending_count = $conn->query("SELECT COUNT(*) as c FROM sit_in_sessions WHERE Type='Reservation' AND Status='Pending'")->fetch_assoc()['c'];
    ?>

    <div class="dash-card">
        <div class="card-header-purple d-flex justify-content-between align-items-center">
            <span><i class="fa fa-calendar-check me-2"></i>Student Reservations</span>
            <?php if ($pending_count > 0): ?>
                <span style="background:var(--gold);color:#1a1a1a;border-radius:20px;padding:3px 12px;font-size:0.78rem;font-weight:700;">
                    <?= $pending_count ?> Pending
                </span>
            <?php endif; ?>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Purpose</th>
                            <th>Lab</th>
                            <th>Time In</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $rvq = $conn->query("
                        SELECT s.*, st.FirstName, st.LastName
                        FROM sit_in_sessions s
                        JOIN students_info st ON s.StudentID = st.IdNumber
                        WHERE s.Type = 'Reservation'
                        ORDER BY FIELD(s.Status,'Pending','Approved','Cancelled'), s.SessionDate ASC, s.TimeIn ASC
                    ");
                    if ($rvq && $rvq->num_rows > 0):
                        while ($r = $rvq->fetch_assoc()):
                            $rbadge = match(strtolower($r['Status'])) {
                                'pending'   => 'background:#c09412;color:#1a1a1a;',
                                'approved'  => 'background:#198754;color:white;',
                                'cancelled' => 'background:#6c757d;color:white;',
                                default     => 'background:#c09412;color:#1a1a1a;'
                            };
                    ?>
                        <tr>
                            <td><?= $r['SessionID'] ?></td>
                            <td><?= htmlspecialchars($r['StudentID']) ?></td>
                            <td><?= htmlspecialchars($r['FirstName'].' '.$r['LastName']) ?></td>
                            <td><?= htmlspecialchars($r['Purpose'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($r['Lab'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($r['TimeIn']) ?></td>
                            <td><?= htmlspecialchars($r['SessionDate']) ?></td>
                            <td>
                                <span style="<?= $rbadge ?> padding:3px 10px;border-radius:5px;font-size:0.78rem;font-weight:600;">
                                    <?= htmlspecialchars($r['Status']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (strtolower($r['Status']) === 'pending'): ?>
                                    <div class="d-flex gap-1">
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="res_id" value="<?= $r['SessionID'] ?>">
                                            <button name="approve_reservation" class="btn btn-sm btn-success" style="font-size:0.75rem;padding:3px 10px;">
                                                <i class="fa fa-check me-1"></i>Approve
                                            </button>
                                        </form>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Reject this reservation?')">
                                            <input type="hidden" name="res_id" value="<?= $r['SessionID'] ?>">
                                            <button name="reject_reservation" class="btn btn-sm btn-danger" style="font-size:0.75rem;padding:3px 10px;">
                                                <i class="fa fa-xmark me-1"></i>Reject
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size:0.78rem;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                <i class="fa fa-calendar-xmark me-2"></i>No reservations found.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>


    <!-- ══════════════ SIT-IN FORM TAB ══════════════ -->
    <?php elseif ($active_tab === 'sitinform'): ?>

    <div class="row g-4 justify-content-center">
        <div class="col-lg-7">
            <div class="dash-card">
                <div class="card-header-purple">
                    <i class="fa fa-magnifying-glass me-2"></i>Search Student
                </div>
                <div class="card-body">
                    <!-- Search box -->
                    <div class="mb-3">
                        <label class="form-label" style="font-size:0.8rem;color:#777;">Search by ID Number or Name</label>
                        <input type="text" id="studentLookup" class="form-control" placeholder="e.g. 2024-00001 or Juan Dela Cruz" autocomplete="off">
                    </div>

                    <!-- Search results dropdown -->
                    <div id="searchResults" class="mb-4" style="display:none;">
                        <div style="font-size:0.78rem;color:#999;margin-bottom:6px;">Select a student:</div>
                        <div id="resultsList"></div>
                    </div>

                    <!-- Sit-in Form (hidden until student selected) -->
                    <form method="POST" id="sitinForm" style="display:none;">
                        <?php if (isset($sitin_error)): ?>
                            <div class="alert alert-danger py-2 px-3" style="font-size:0.85rem;border-radius:8px;">
                                <i class="fa fa-circle-exclamation me-2"></i><?= htmlspecialchars($sitin_error) ?>
                            </div>
                        <?php endif; ?>

                        <div style="background:#f8f4fc;border-radius:10px;padding:1rem;margin-bottom:1rem;">
                            <div style="font-size:0.72rem;color:var(--purple);font-weight:700;text-transform:uppercase;letter-spacing:0.07em;margin-bottom:8px;">Selected Student</div>
                            <div class="row g-2">
                                <div class="col-sm-6">
                                    <div style="font-size:0.78rem;color:#999;">ID Number</div>
                                    <div id="display_id" style="font-weight:700;color:#333;"></div>
                                </div>
                                <div class="col-sm-6">
                                    <div style="font-size:0.78rem;color:#999;">Name</div>
                                    <div id="display_name" style="font-weight:700;color:#333;"></div>
                                </div>
                                <div class="col-sm-6">
                                    <div style="font-size:0.78rem;color:#999;">Course</div>
                                    <div id="display_course" style="font-weight:600;color:#555;"></div>
                                </div>
                                <div class="col-sm-6">
                                    <div style="font-size:0.78rem;color:#999;">Year Level</div>
                                    <div id="display_level" style="font-weight:600;color:#555;"></div>
                                </div>
                            </div>
                        </div>

                        <input type="hidden" name="sitin_id" id="sitin_id">

                        <div class="mb-3">
                            <label class="form-label" style="font-size:0.8rem;color:#777;">Purpose</label>
                            <select name="sitin_purpose" class="form-select" required>
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
                        <div class="mb-4">
                            <label class="form-label" style="font-size:0.8rem;color:#777;">Lab</label>
                            <select name="sitin_lab" class="form-select" required>
                                <option value="">Select lab</option>
                                <option>524</option>
                                <option>526</option>
                                <option>528</option>
                                <option>530</option>
                                <option>542</option>
                                <option>Mac Lab</option>
                            </select>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-secondary btn-sm px-3" onclick="resetSitinForm()">
                                <i class="fa fa-xmark me-1"></i>Cancel
                            </button>
                            <button type="submit" name="do_sitin" class="btn btn-gold px-4">
                                <i class="fa fa-right-to-bracket me-2"></i>Sit In
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php endif; ?>

</div><!-- end main-content -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Edit student modal
function openEdit(id, first, last, course, level) {
    document.getElementById('edit_id').value    = id;
    document.getElementById('edit_first').value = first;
    document.getElementById('edit_last').value  = last;
    document.getElementById('edit_course').value= course;
    document.getElementById('edit_level').value = level;
    new bootstrap.Modal(document.getElementById('editStudentModal')).show();
}

// Live search — students
const studentSearch = document.getElementById('studentSearch');
if (studentSearch) {
    studentSearch.addEventListener('input', function () {
        const q = this.value.toLowerCase();
        document.querySelectorAll('#studentTable tbody tr').forEach(tr => {
            tr.style.display = tr.innerText.toLowerCase().includes(q) ? '' : 'none';
        });
    });
}

// Live search — records
const recordSearch = document.getElementById('recordSearch');
if (recordSearch) {
    recordSearch.addEventListener('input', function () {
        const q = this.value.toLowerCase();
        document.querySelectorAll('#recordTable tbody tr').forEach(tr => {
            tr.style.display = tr.innerText.toLowerCase().includes(q) ? '' : 'none';
        });
    });
}

// Sit-in student search
const lookup = document.getElementById('studentLookup');
if (lookup) {
    let debounce;
    lookup.addEventListener('input', function () {
        clearTimeout(debounce);
        const q = this.value.trim();
        if (q.length < 2) {
            document.getElementById('searchResults').style.display = 'none';
            return;
        }
        debounce = setTimeout(() => {
            fetch('admin_dashboard.php?search_student=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(data => {
                    const list = document.getElementById('resultsList');
                    const box  = document.getElementById('searchResults');
                    if (data.length === 0) {
                        list.innerHTML = '<div style="font-size:0.85rem;color:#999;padding:8px;">No students found.</div>';
                    } else {
                        list.innerHTML = data.map(s => `
                            <div class="result-item" onclick="selectStudent('${s.IdNumber}','${s.FirstName}','${s.LastName}','${s.Course}','${s.CourseLevel}')"
                                style="padding:10px 12px;border-radius:8px;cursor:pointer;border:1px solid #eee;margin-bottom:6px;background:white;transition:background 0.15s;"
                                onmouseover="this.style.background='#f3eaf9'" onmouseout="this.style.background='white'">
                                <div style="font-weight:600;font-size:0.88rem;color:#333;">${s.FirstName} ${s.LastName}</div>
                                <div style="font-size:0.78rem;color:#999;">${s.IdNumber} &mdash; ${s.Course}, Year ${s.CourseLevel}</div>
                            </div>`).join('');
                    }
                    box.style.display = 'block';
                });
        }, 300);
    });
}

function selectStudent(id, first, last, course, level) {
    document.getElementById('sitin_id').value      = id;
    document.getElementById('display_id').innerText    = id;
    document.getElementById('display_name').innerText  = first + ' ' + last;
    document.getElementById('display_course').innerText= course;
    document.getElementById('display_level').innerText = 'Year ' + level;
    document.getElementById('searchResults').style.display = 'none';
    document.getElementById('studentLookup').value = first + ' ' + last;
    document.getElementById('sitinForm').style.display = 'block';
}

function resetSitinForm() {
    document.getElementById('sitinForm').style.display = 'none';
    document.getElementById('studentLookup').value = '';
    document.getElementById('searchResults').style.display = 'none';
}
</script>
</body>
</html>         