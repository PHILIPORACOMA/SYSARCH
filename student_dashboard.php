<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

/* CHECK LOGIN */
if (!isset($_SESSION['user_id'])) {
    header("Location: Loginpage.php");
    exit();
}

include "db.php";

$id = $_SESSION['user_id'];

/* GET STUDENT INFO */
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
        /* ── Theme variables ── */
        :root {
            --purple: #5c2b7a;
            --gold:   #c09412;
        }

        /* ── Navbar (reused from other pages) ── */
        .navbar-custom { background-color: var(--purple); }
        .navbar-brand, .nav-link { color: white !important; }
        .nav-link:hover { color: #f0d080 !important; }
        .UC-Logo { background-color: var(--purple); height: 40px; }

        /* ── Page background ── */
        body { background-color: #f8f9fa; }

        /* ── Card headers use theme colours ── */
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

        /* ── Cards (match reg-card style) ── */
        .dash-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.07);
        }

        /* ── Profile avatar circle ── */
        .avatar {
            width: 80px; height: 80px;
            border-radius: 50%;
            background-color: var(--purple);
            color: white;
            font-size: 2rem;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1rem;
        }

        /* ── Profile info rows ── */
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px solid #f0f0f0;
            font-size: 0.88rem;
        }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: #888; }
        .info-value { font-weight: 600; color: #333; }

        /* ── Rules list ── */
        .rules-list li {
            padding: 5px 0;
            font-size: 0.88rem;
            border-bottom: 1px solid #f0f0f0;
        }
        .rules-list li:last-child { border-bottom: none; }
        .rules-list li::marker { color: var(--purple); }

        /* ── Announcement item ── */
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
        .badge-inactive { background-color: #6c757d; color: white; }

        /* ── Logout button ── */
        .btn-logout {
            background-color: var(--gold);
            color: #1a1a1a;
            border: none;
            font-size: 0.85rem;
        }
        .btn-logout:hover { background-color: #a87e0f; color: white; }
    </style>
</head>
<body>

<!-- ── NAVBAR (same as Landingpage / Loginpage / Register) ── -->
<nav class="navbar navbar-expand-lg navbar-custom px-4">
    <div class="container-fluid">
        <img src="UCLogo-removebg-preview.png" alt="UC Logo" class="UC-Logo">
        <a class="navbar-brand fs-6 ms-2" href="Landingpage.php">
            College of Computer Studies Sit-in Monitoring System
        </a>
        <div class="ms-auto d-flex align-items-center gap-2">
            <span class="text-white me-2">
                Welcome, <b><?php echo htmlspecialchars($_SESSION['user_name']); ?></b>
            </span>
            <a href="logout.php" class="btn btn-logout btn-sm px-3">
                <i class="fa fa-right-from-bracket me-1"></i>Logout
            </a>
        </div>
    </div>
</nav>

<!-- ── MAIN CONTENT ── -->
<div class="container mt-4 pb-5">
    <div class="row g-4">

        <!-- LEFT COLUMN -->
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
                        <h6 class="text-center fw-bold mb-3" style="color:var(--purple)">
                            <?php echo htmlspecialchars($student['FirstName'] . ' ' . $student['LastName']); ?>
                        </h6>
                        <div class="info-row">
                            <span class="info-label">ID Number</span>
                            <span class="info-value"><?php echo htmlspecialchars($student['IdNumber']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Email</span>
                            <span class="info-value" style="font-size:0.8rem"><?php echo htmlspecialchars($student['Email']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Course</span>
                            <span class="info-value"><?php echo htmlspecialchars($student['Course']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Year Level</span>
                            <span class="info-value">Year <?php echo htmlspecialchars($student['CourseLevel']); ?></span>
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


        <!-- RIGHT COLUMN -->
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
                            $sess_sql  = "SELECT * FROM sit_in_sessions WHERE StudentID = ?";
                            $sess_stmt = $conn->prepare($sess_sql);
                            $sess_stmt->bind_param("s", $id);
                            $sess_stmt->execute();
                            $sessions = $sess_stmt->get_result();

                            if ($sessions && $sessions->num_rows > 0):
                                while ($s = $sessions->fetch_assoc()):
                                    $badge = strtolower($s['Status']) === 'active' ? 'badge-active' : 'badge-inactive';
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($s['SessionDate']); ?></td>
                                    <td><?php echo htmlspecialchars($s['TimeIn']); ?></td>
                                    <td><?php echo htmlspecialchars($s['TimeOut']); ?></td>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>