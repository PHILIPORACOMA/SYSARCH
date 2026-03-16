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

$sql  = "SELECT * FROM students_info WHERE IdNumber = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) die("Query Error: " . $conn->error);
$stmt->bind_param("s", $id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();
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
        :root {
            --purple: #5c2b7a;
            --gold:   #c09412;
        }

        /* ── Navbar ── */
        .navbar-custom { background-color: var(--purple); }
        .navbar-brand, .nav-link { color: white !important; }
        .nav-link { padding: 0.4rem 0.75rem !important; border-radius: 6px; transition: background 0.2s; }
        .nav-link:hover { background-color: rgba(255,255,255,0.12) !important; color: #f0d080 !important; }
        .UC-Logo { background-color: var(--purple); height: 40px; }

        body { background-color: #f8f9fa; }

        /* ── Card headers ── */
        .card-header-purple {
            background-color: var(--purple);
            color: white;
            font-weight: 600;
            border-radius: 12px 12px 0 0 !important;
        }
        .card-header-gold {
            background-color: var(--gold);
            color: #1a1a1a;
            font-weight: 600;
            border-radius: 12px 12px 0 0 !important;
        }

        /* ── Cards ── */
        .dash-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.07);
        }

        /* ── Avatar ── */
        .avatar {
            width: 82px; height: 82px;
            border-radius: 50%;
            background-color: var(--purple);
            color: white;
            font-size: 2rem;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 0.75rem;
            border: 4px solid #f3eaf9;
        }

        /* ── Profile info rows ── */
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 7px 0;
            border-bottom: 1px solid #f0f0f0;
            font-size: 0.87rem;
        }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: #999; }
        .info-value { font-weight: 600; color: #333; text-align: right; max-width: 60%; }

        /* ── Profile action buttons ── */
        .profile-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-top: 1rem;
        }
        .btn-action {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 5px;
            padding: 10px 6px;
            border-radius: 10px;
            border: 1.5px solid #eee;
            background: white;
            color: #555;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        .btn-action i { font-size: 1.1rem; }
        .btn-action:hover {
            border-color: var(--purple);
            background: #f3eaf9;
            color: var(--purple);
        }
        .btn-action.btn-action-edit  i { color: var(--purple); }
        .btn-action.btn-action-notif i { color: #e67e22; }
        .btn-action.btn-action-hist  i { color: #2980b9; }
        .btn-action.btn-action-res   i { color: #27ae60; }

        /* ── Rules list ── */
        .rules-list li {
            padding: 5px 0;
            font-size: 0.87rem;
            border-bottom: 1px solid #f0f0f0;
        }
        .rules-list li:last-child { border-bottom: none; }
        .rules-list li::marker { color: var(--purple); }

        /* ── Announcements ── */
        .announcement-item {
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .announcement-item:last-child { border-bottom: none; }
        .announcement-item h6 { color: var(--purple); font-weight: 700; margin-bottom: 4px; }

        /* ── Sessions table ── */
        .table thead th {
            background-color: var(--purple);
            color: white;
            font-size: 0.85rem;
            border: none;
        }
        .table tbody tr:hover { background-color: #f3eaf9; }
        .badge-active   { background-color: #198754; color: white; }
        .badge-completed { background-color: #6c757d; color: white; }

        /* ── Navbar buttons ── */
        .btn-home {
            background-color: rgba(255,255,255,0.15);
            color: white !important;
            border: 1.5px solid rgba(255,255,255,0.3);
            border-radius: 7px;
            font-size: 0.85rem;
            padding: 0.3rem 0.9rem;
            transition: background 0.2s;
        }
        .btn-home:hover { background-color: rgba(255,255,255,0.25); }

        .btn-logout {
            background-color: var(--gold);
            color: #1a1a1a !important;
            border: none;
            border-radius: 7px;
            font-size: 0.85rem;
            padding: 0.3rem 0.9rem;
            transition: background 0.2s;
        }
        .btn-logout:hover { background-color: #a87e0f; color: white !important; }

        /* ── Modal styling ── */
        .modal-header { background-color: var(--purple); color: white; border-radius: 12px 12px 0 0; }
        .modal-header .btn-close { filter: invert(1); }
        .modal-content { border-radius: 12px; border: none; box-shadow: 0 20px 50px rgba(0,0,0,0.15); }
        .form-control:focus, .form-select:focus {
            border-color: var(--purple);
            box-shadow: 0 0 0 3px rgba(92,43,122,0.12);
        }
        .btn-save {
            background-color: var(--gold);
            color: #1a1a1a;
            border: none;
            border-radius: 8px;
            font-weight: 600;
        }
        .btn-save:hover { background-color: #a87e0f; color: white; }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-custom px-4">
    <div class="container-fluid">
        <img src="UCLogo-removebg-preview.png" alt="UC Logo" class="UC-Logo">
        <a class="navbar-brand fs-6 ms-2" href="Landingpage.php">
            College of Computer Studies Sit-in Monitoring System
        </a>
        <div class="ms-auto d-flex align-items-center gap-2">
            <span class="text-white me-1" style="font-size:0.9rem;">
                Welcome, <b><?php echo htmlspecialchars($_SESSION['user_name']); ?></b>
            </span>
            <a href="Landingpage.php" class="btn btn-home btn-sm">
                <i class="fa fa-house me-1"></i>Home
            </a>
            <a href="logout.php" class="btn btn-logout btn-sm">
                <i class="fa fa-right-from-bracket me-1"></i>Logout
            </a>
        </div>
    </div>
</nav>

<!-- MAIN CONTENT -->
<div class="container mt-4 pb-5">
    <div class="row g-4">

        <!-- ════ LEFT COLUMN ════ -->
        <div class="col-md-4">

            <!-- Student Profile Card -->
            <div class="card dash-card mb-4">
                <div class="card-header card-header-purple py-2">
                    <i class="fa fa-user me-2"></i>Student Profile
                </div>
                <div class="card-body pt-4">
                    <?php if ($student): ?>
                        <div class="avatar">
                            <?php echo strtoupper(substr($student['FirstName'], 0, 1) . substr($student['LastName'], 0, 1)); ?>
                        </div>
                        <h6 class="text-center fw-bold mb-1" style="color:var(--purple)">
                            <?php echo htmlspecialchars($student['FirstName'] . ' ' . $student['LastName']); ?>
                        </h6>
                        <p class="text-center text-muted mb-3" style="font-size:0.78rem;">
                            <?php echo htmlspecialchars($student['Course']); ?> &mdash; Year <?php echo htmlspecialchars($student['CourseLevel']); ?>
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
                            <span class="info-value">Year <?php echo htmlspecialchars($student['CourseLevel']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Address</span>
                            <span class="info-value"><?php echo htmlspecialchars($student['Address'] ?? '—'); ?></span>
                        </div>

                        <!-- Action Buttons -->
                        <div class="profile-actions mt-3">
                            <a href="#" class="btn-action btn-action-edit" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                                <i class="fa fa-pen-to-square"></i>
                                Edit Profile
                            </a>
                            <a href="#" class="btn-action btn-action-notif" data-bs-toggle="modal" data-bs-target="#notificationsModal">
                                <i class="fa fa-bell"></i>
                                Notifications
                            </a>
                            <a href="#" class="btn-action btn-action-hist" data-bs-toggle="modal" data-bs-target="#historyModal">
                                <i class="fa fa-clock-rotate-left"></i>
                                History
                            </a>
                            <a href="#" class="btn-action btn-action-res" data-bs-toggle="modal" data-bs-target="#reservationsModal">
                                <i class="fa fa-calendar-check"></i>
                                Reservations
                            </a>
                        </div>

                    <?php else: ?>
                        <p class="text-danger text-center">Student information not found.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Rules & Regulations Card -->
            <div class="card dash-card">
                <div class="card-header card-header-gold py-2">
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
        <!-- END LEFT COLUMN -->


        <!-- ════ RIGHT COLUMN ════ -->
        <div class="col-md-8">

            <!-- Announcements Card -->
            <div class="card dash-card mb-4">
                <div class="card-header card-header-purple py-2">
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
                            <p class="mb-1 text-muted" style="font-size:0.88rem">
                                <?php echo htmlspecialchars($row['Message']); ?>
                            </p>
                            <small class="text-muted">
                                <i class="fa fa-calendar me-1"></i><?php echo htmlspecialchars($row['DatePosted']); ?>
                            </small>
                        </div>
                    <?php
                        endwhile;
                    else:
                        echo '<p class="text-muted mb-0">No announcements available.</p>';
                    endif;
                    ?>
                </div>
            </div>

            <!-- Sit-in Sessions Card -->
            <div class="card dash-card">
                <div class="card-header card-header-gold py-2">
                    <i class="fa fa-clock me-2"></i>Your Sit-in Sessions
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Time In</th>
                                    <th>Time Out</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            $sess_sql  = "SELECT * FROM sit_in_sessions WHERE StudentID = ? ORDER BY SessionDate DESC";
                            $sess_stmt = $conn->prepare($sess_sql);
                            $sess_stmt->bind_param("s", $id);
                            $sess_stmt->execute();
                            $sessions = $sess_stmt->get_result();

                            if ($sessions && $sessions->num_rows > 0):
                                while ($s = $sessions->fetch_assoc()):
                                    $badge = strtolower($s['Status']) === 'active' ? 'badge-active' : 'badge-completed';
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($s['SessionDate']); ?></td>
                                    <td><?php echo htmlspecialchars($s['TimeIn']); ?></td>
                                    <td><?php echo $s['TimeOut'] ? htmlspecialchars($s['TimeOut']) : '<span class="text-muted">—</span>'; ?></td>
                                    <td>
                                        <span class="badge <?php echo $badge; ?>">
                                            <?php echo htmlspecialchars($s['Status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php
                                endwhile;
                            else:
                            ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3">
                                        <i class="fa fa-inbox me-2"></i>No sessions found
                                    </td>
                                </tr>
                            <?php
                            endif;
                            $sess_stmt->close();
                            ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
        <!-- END RIGHT COLUMN -->

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
                <form action="update_profile.php" method="POST">
                    <div class="mb-3">
                        <label class="form-label" style="font-size:0.8rem; color:#777; font-weight:500;">First Name</label>
                        <input type="text" name="first_name" class="form-control"
                            value="<?php echo htmlspecialchars($student['FirstName'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-size:0.8rem; color:#777; font-weight:500;">Middle Name</label>
                        <input type="text" name="middle_name" class="form-control"
                            value="<?php echo htmlspecialchars($student['MiddleName'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-size:0.8rem; color:#777; font-weight:500;">Last Name</label>
                        <input type="text" name="last_name" class="form-control"
                            value="<?php echo htmlspecialchars($student['LastName'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-size:0.8rem; color:#777; font-weight:500;">Email</label>
                        <input type="email" name="email" class="form-control"
                            value="<?php echo htmlspecialchars($student['Email'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-size:0.8rem; color:#777; font-weight:500;">Address</label>
                        <input type="text" name="address" class="form-control"
                            value="<?php echo htmlspecialchars($student['Address'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-size:0.8rem; color:#777; font-weight:500;">New Password <span class="text-muted">(leave blank to keep current)</span></label>
                        <input type="password" name="password" class="form-control" placeholder="••••••••">
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-save btn-sm px-4">Save Changes</button>
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
                        <div style="width:36px; height:36px; min-width:36px; border-radius:50%; background:#f3eaf9; display:flex; align-items:center; justify-content:center;">
                            <i class="fa fa-bullhorn" style="color:var(--purple); font-size:0.85rem;"></i>
                        </div>
                        <div>
                            <div style="font-size:0.88rem; font-weight:600; color:#333;">
                                <?php echo htmlspecialchars($n['Title']); ?>
                            </div>
                            <div style="font-size:0.8rem; color:#888;">
                                <?php echo htmlspecialchars($n['Message']); ?>
                            </div>
                            <div style="font-size:0.75rem; color:#bbb; margin-top:3px;">
                                <i class="fa fa-calendar me-1"></i><?php echo htmlspecialchars($n['DatePosted']); ?>
                            </div>
                        </div>
                    </div>
                <?php
                    endwhile;
                else:
                    echo '<p class="text-muted text-center mb-0"><i class="fa fa-bell-slash me-2"></i>No notifications yet.</p>';
                endif;
                ?>
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
                                <th style="background:var(--purple); color:white;">Date</th>
                                <th style="background:var(--purple); color:white;">Time In</th>
                                <th style="background:var(--purple); color:white;">Time Out</th>
                                <th style="background:var(--purple); color:white;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $hist_stmt = $conn->prepare("SELECT * FROM sit_in_sessions WHERE StudentID = ? ORDER BY SessionDate DESC");
                        $hist_stmt->bind_param("s", $id);
                        $hist_stmt->execute();
                        $hist = $hist_stmt->get_result();

                        if ($hist && $hist->num_rows > 0):
                            while ($h = $hist->fetch_assoc()):
                                $hbadge = strtolower($h['Status']) === 'active' ? 'badge-active' : 'badge-completed';
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($h['SessionDate']); ?></td>
                                <td><?php echo htmlspecialchars($h['TimeIn']); ?></td>
                                <td><?php echo $h['TimeOut'] ? htmlspecialchars($h['TimeOut']) : '<span class="text-muted">—</span>'; ?></td>
                                <td><span class="badge <?php echo $hbadge; ?>"><?php echo htmlspecialchars($h['Status']); ?></span></td>
                            </tr>
                        <?php
                            endwhile;
                        else:
                        ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">
                                    <i class="fa fa-inbox me-2"></i>No history found
                                </td>
                            </tr>
                        <?php
                        endif;
                        $hist_stmt->close();
                        ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- ════ RESERVATIONS MODAL ════ -->
<div class="modal fade" id="reservationsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title"><i class="fa fa-calendar-check me-2"></i>Reservations</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted text-center mb-0" style="font-size:0.9rem;">
                    <i class="fa fa-calendar-xmark fa-2x mb-3 d-block" style="color:#ddd;"></i>
                    No reservations found.<br>
                    <span style="font-size:0.8rem;">Reservation booking will be available soon.</span>
                </p>
            </div>
        </div>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>