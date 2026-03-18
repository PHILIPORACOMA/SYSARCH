<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    header("Location: Loginpage.php");
    exit();
}

include "db.php";
$id = $_SESSION['user_id'];

/* ── Handle Edit Profile ── */
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

    // Check for duplicate reservation on same date/lab/time
    $dup = $conn->prepare("SELECT SessionID FROM sit_in_sessions WHERE StudentID=? AND SessionDate=? AND Type='Reservation' AND Status='Pending'");
    $dup->bind_param("ss", $id, $date);
    $dup->execute();
    if ($dup->get_result()->num_rows > 0) {
        $res_error = "You already have a pending reservation on that date.";
    } else {
        $ins = $conn->prepare("INSERT INTO sit_in_sessions (StudentID, Purpose, Lab, TimeIn, SessionDate, Status, Type) VALUES (?,?,?,?,?,'Pending','Reservation')");
        $ins->bind_param("sssss", $id, $purpose, $lab, $timein, $date);
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

/* ── Session credits ── */
$used_sessions   = $conn->query("SELECT COUNT(*) as c FROM sit_in_sessions WHERE StudentID='".mysqli_real_escape_string($conn,$id)."' AND (Type='Sit-in' OR Type IS NULL)")->fetch_assoc()['c'];
$max_credits     = isset($student['SessionCredits']) ? (int)$student['SessionCredits'] : 30;
$credits_left    = max(0, $max_credits - $used_sessions);
$credits_percent = $max_credits > 0 ? round(($credits_left / $max_credits) * 100) : 0;
$credits_color   = $credits_left > 15 ? '#198754' : ($credits_left > 5 ? '#c09412' : '#dc3545');
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
        .avatar { width:82px; height:82px; border-radius:50%; background-color:var(--purple); color:white; font-size:2rem; display:flex; align-items:center; justify-content:center; margin:0 auto 0.75rem; border:4px solid #f3eaf9; }

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
        .badge-approved  { background-color:#198754; color:white; padding:3px 8px; border-radius:5px; font-size:0.78rem; }
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
                        <div class="avatar">
                            <?php echo strtoupper(substr($student['FirstName'],0,1).substr($student['LastName'],0,1)); ?>
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
                            <a href="#" class="btn-action btn-action-notif" data-bs-toggle="modal" data-bs-target="#notificationsModal">
                                <i class="fa fa-bell"></i>Notifications
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
            <div class="card dash-card">
                <div class="card-header card-header-gold">
                    <i class="fa fa-clock me-2"></i>Your Sit-in Sessions
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th><th>Purpose</th><th>Lab</th><th>Time In</th><th>Time Out</th><th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            $ss = $conn->prepare("SELECT * FROM sit_in_sessions WHERE StudentID=? ORDER BY SessionDate DESC, TimeIn DESC");
                            $ss->bind_param("s", $id);
                            $ss->execute();
                            $sessions = $ss->get_result();
                            if ($sessions && $sessions->num_rows > 0):
                                while ($s = $sessions->fetch_assoc()):
                                    $badge = strtolower($s['Status'])==='active' ? 'badge-active' : 'badge-completed';
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($s['SessionDate']); ?></td>
                                    <td><?php echo htmlspecialchars($s['Purpose'] ?? '—'); ?></td>
                                    <td><?php echo htmlspecialchars($s['Lab'] ?? '—'); ?></td>
                                    <td><?php echo htmlspecialchars($s['TimeIn']); ?></td>
                                    <td><?php echo $s['TimeOut'] ? htmlspecialchars($s['TimeOut']) : '<span class="text-muted">—</span>'; ?></td>
                                    <td><span class="<?= $badge ?>"><?php echo htmlspecialchars($s['Status']); ?></span></td>
                                </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="6" class="text-center text-muted py-3"><i class="fa fa-inbox me-2"></i>No sessions found</td></tr>
                            <?php endif; $ss->close(); ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
        <!-- END RIGHT -->

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
                <h6 class="modal-title"><i class="fa fa-bell me-2"></i>Notifications</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php
                $notif = $conn->query("SELECT * FROM announcements ORDER BY DatePosted DESC LIMIT 5");
                if ($notif && $notif->num_rows > 0):
                    while ($n = $notif->fetch_assoc()):
                ?>
                    <div class="d-flex gap-3 align-items-start pb-3 mb-3 border-bottom">
                        <div style="width:36px;height:36px;min-width:36px;border-radius:50%;background:#f3eaf9;display:flex;align-items:center;justify-content:center;">
                            <i class="fa fa-bullhorn" style="color:var(--purple);font-size:0.85rem;"></i>
                        </div>
                        <div>
                            <div style="font-size:0.88rem;font-weight:600;color:#333;"><?php echo htmlspecialchars($n['Title']); ?></div>
                            <div style="font-size:0.8rem;color:#888;"><?php echo htmlspecialchars($n['Message']); ?></div>
                            <div style="font-size:0.75rem;color:#bbb;margin-top:3px;"><i class="fa fa-calendar me-1"></i><?php echo htmlspecialchars($n['DatePosted']); ?></div>
                        </div>
                    </div>
                <?php endwhile; else: ?>
                    <p class="text-muted text-center mb-0"><i class="fa fa-bell-slash me-2"></i>No notifications yet.</p>
                <?php endif; ?>
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
                                $hb = strtolower($h['Status'])==='active' ? 'badge-active' : 'badge-completed';
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
                            <div class="col-sm-6">
                                <label class="res-field-label">Lab</label>
                                <select name="res_lab" class="form-select" required>
                                    <option value="">Select lab</option>
                                    <option>524</option>
                                    <option>526</option>
                                    <option>528</option>
                                    <option>530</option>
                                    <option>542</option>
                                    <option>Mac Lab</option>
                                </select>
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


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-open reservations modal if there was a form submission result
<?php if (isset($res_error) || isset($res_success)): ?>
document.addEventListener('DOMContentLoaded', function () {
    new bootstrap.Modal(document.getElementById('reservationsModal')).show();
});
<?php endif; ?>
</script>
</body>
</html>