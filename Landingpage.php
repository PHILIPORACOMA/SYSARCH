<?php
session_start();
include "db.php";
include "leaderboard_helper.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCS | Home</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --purple: #5c2b7a;
            --purple-light: #763b9a;
            --purple-soft: #f3eaf9;
            --gold:   #c09412;
            --gold-light: #e6b119;
            --dark: #1a1a1a;
            --gray: #6c757d;
        }

        body { 
            background-color: #ffffff; 
            margin: 0; 
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            color: var(--dark);
            scroll-behavior: smooth;
        }

        /* ── Navbar ── */
        .navbar-custom { 
            background-color: var(--purple); 
            padding: 12px 0;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        .navbar-brand {
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        .nav-link { 
            font-weight: 500;
            padding: 0.5rem 1rem !important; 
            border-radius: 8px; 
            transition: all 0.2s; 
            color: white !important;
        }
        .nav-link:hover { 
            background-color: rgba(255,255,255,0.1) !important; 
            color: #f0d080 !important; 
        }
        .btn-login-nav {
            background-color: var(--gold);
            color: var(--dark) !important;
            font-weight: 700;
            border-radius: 8px;
            padding: 8px 20px !important;
            margin-left: 10px;
        }
        .btn-login-nav:hover {
            background-color: var(--gold-light) !important;
            transform: translateY(-1px);
        }
        .UC-Logo { height: 40px; }

        /* ── Hero ── */
        .hero-section {
            background: radial-gradient(circle at 10% 20%, rgba(92, 43, 122, 0.05) 0%, rgba(255, 255, 255, 1) 90%);
            padding: 100px 0 80px;
            position: relative;
            overflow: hidden;
        }
        .hero-section::after {
            content: '';
            position: absolute;
            top: -100px;
            right: -100px;
            width: 400px;
            height: 400px;
            background: var(--purple-soft);
            border-radius: 50%;
            z-index: -1;
            opacity: 0.5;
        }

        .hero-logo {
            max-width: 420px;
            width: 100%;
            height: auto;
            filter: drop-shadow(0 20px 40px rgba(92,43,122,0.15));
            transition: transform 0.3s ease;
        }
        .hero-logo:hover {
            transform: scale(1.02);
        }

        .hero-title {
            color: var(--purple);
            font-weight: 800;
            font-size: 3.2rem;
            line-height: 1.1;
            margin-bottom: 20px;
        }
        .hero-subtitle {
            color: var(--gold);
            font-weight: 700;
            font-size: 1.1rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 15px;
            display: block;
        }
        .hero-desc {
            color: var(--gray);
            font-size: 1.1rem;
            line-height: 1.6;
            max-width: 550px;
            margin-bottom: 35px;
        }

        .btn-hero-primary {
            background-color: var(--purple);
            color: white;
            padding: 14px 32px;
            border-radius: 12px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 10px 20px rgba(92,43,122,0.2);
            transition: all 0.3s;
        }
        .btn-hero-primary:hover {
            background-color: var(--purple-light);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 15px 30px rgba(92,43,122,0.3);
        }

        /* ── Feature cards ── */
        .feature-card {
            background: white;
            border-radius: 20px;
            border: 1px solid rgba(0,0,0,0.05);
            box-shadow: 0 10px 30px rgba(0,0,0,0.04);
            padding: 30px 20px;
            text-align: center;
            height: 100%;
            transition: all 0.3s;
        }
        .feature-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(92,43,122,0.1);
            border-color: var(--purple-soft);
        }
        .feature-icon {
            width: 64px; height: 64px;
            border-radius: 16px;
            background-color: var(--purple-soft);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 1.5rem;
            color: var(--purple);
        }
        .feature-card h6 {
            color: var(--purple);
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 10px;
        }
        .feature-card p {
            color: var(--gray);
            font-size: 0.9rem;
            margin-bottom: 0;
            line-height: 1.5;
        }

        /* ── Sections ── */
        .section-label {
            color: var(--gold);
            font-weight: 700;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 10px;
            display: block;
        }
        h2 {
            color: var(--purple);
            font-weight: 800;
            font-size: 2.2rem;
            letter-spacing: -0.5px;
        }

        .stat-box {
            background: white;
            padding: 25px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.04);
            text-align: center;
            border: 1px solid rgba(0,0,0,0.02);
            height: 100%;
        }
        .stat-number {
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--purple);
            margin-bottom: 5px;
        }
        .stat-label {
            font-size: 0.85rem;
            color: var(--gray);
            font-weight: 600;
            text-transform: uppercase;
        }

        .rule-item {
            display: flex;
            align-items: flex-start;
            gap: 20px;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .rule-item:last-child { border-bottom: none; }
        .rule-num {
            width: 28px; height: 28px;
            background: var(--purple);
            color: white;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 800;
            font-size: 0.85rem;
            flex-shrink: 0;
            margin-top: 3px;
        }
        .rule-text {
            font-size: 1rem;
            color: #444;
            line-height: 1.5;
        }

        .footer {
            background-color: #fcfaff;
            border-top: 1px solid #eee;
            padding: 60px 0 30px;
            text-align: center;
            color: var(--gray);
        }
        .footer strong { color: var(--purple); }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-custom px-4 sticky-top">
    <div class="container">
        <div class="d-flex align-items-center">
            <img src="UCLogo-removebg-preview.png" alt="UC Logo" class="UC-Logo">
            <a class="navbar-brand fs-5 ms-3 text-white" href="#">CCS Sit-in</a>
        </div>
        
        <button class="navbar-toggler border-0 text-white" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <i class="fa fa-bars"></i>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item"><a class="nav-link" href="#home">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="#about">About</a></li>
                <li class="nav-item"><a class="nav-link" href="#rules">Rules</a></li>
                <li class="nav-item"><a class="nav-link" href="#leaderboard">Leaderboard</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item">
                        <a class="nav-link btn-login-nav" href="<?= $_SESSION['is_admin'] ? 'admin_dashboard.php' : 'student_dashboard.php' ?>">
                            <i class="fa fa-gauge me-2"></i>Dashboard
                        </a>
                    </li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="Register.php">Register</a></li>
                    <li class="nav-item"><a class="nav-link btn-login-nav" href="Loginpage.php">Login</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- HERO -->
<section class="hero-section" id="home">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <span class="hero-subtitle">UC College of Computer Studies</span>
                <h1 class="hero-title">Sit-in Monitoring System</h1>
                <p class="hero-desc">
                    A digital ecosystem for CCS students to seamlessly manage laboratory sessions, 
                    track sit-in hours, and stay updated with official college announcements.
                </p>
                <div class="d-flex gap-3">
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <a href="Loginpage.php" class="btn-hero-primary">Get Started <i class="fa fa-arrow-right"></i></a>
                        <a href="#about" class="btn btn-outline-dark px-4 py-3" style="border-radius:12px; font-weight:700; border-width:2px;">Learn More</a>
                    <?php else: ?>
                        <a href="<?= $_SESSION['is_admin'] ? 'admin_dashboard.php' : 'student_dashboard.php' ?>" class="btn-hero-primary">Go to Dashboard <i class="fa fa-gauge"></i></a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="position-relative">
                    <img src="CCSLogo.png" alt="CCS Logo" class="hero-logo">
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FEATURES -->
<section class="py-5">
    <div class="container py-4">
        <div class="row g-4">
            <div class="col-md-3">
                <div class="feature-card">
                    <div class="feature-icon"><i class="fa fa-clock"></i></div>
                    <h6>Live Tracking</h6>
                    <p>Accurate time-in and time-out records for every session.</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="feature-card">
                    <div class="feature-icon"><i class="fa fa-bullhorn"></i></div>
                    <h6>Announcements</h6>
                    <p>Never miss a lab notice with our real-time updates.</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="feature-card">
                    <div class="feature-icon"><i class="fa fa-shield-halved"></i></div>
                    <h6>Secure Access</h6>
                    <p>Protected student accounts and administrative controls.</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="feature-card">
                    <div class="feature-icon"><i class="fa fa-laptop-code"></i></div>
                    <h6>Resource Mgmt</h6>
                    <p>Efficiently managed computer lab schedules and PC counts.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ABOUT -->
<section class="py-5" id="about">
    <div class="container py-5">
        <div class="row g-5 align-items-center">
            <div class="col-md-6">
                <span class="section-label">Our Mission</span>
                <h2>Digitizing the Lab Experience</h2>
                <p class="mt-4 text-muted" style="font-size:1.1rem; line-height:1.7;">
                    The CCS Sit-in Monitoring System is the official platform for the 
                    <strong>College of Computer Studies</strong> at the University of Cebu Main Campus.
                    Designed to modernize laboratory administration, it replaces traditional 
                    paper-based logs with a transparent, efficient digital workflow.
                </p>
                <div class="row g-3 mt-4">
                    <div class="col-sm-6">
                        <div class="d-flex align-items-center gap-3">
                            <i class="fa fa-circle-check text-success fs-4"></i>
                            <span class="fw-bold">No more manual logs</span>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="d-flex align-items-center gap-3">
                            <i class="fa fa-circle-check text-success fs-4"></i>
                            <span class="fw-bold">Instant record access</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="row g-4">
                    <div class="col-6">
                        <div class="stat-box">
                            <div class="stat-number">3hr</div>
                            <div class="stat-label">Max Session</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-box">
                            <div class="stat-number">UC</div>
                            <div class="stat-label">University of Cebu</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-box">
                            <div class="stat-number">CCS</div>
                            <div class="stat-label">College of IT</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-box" style="background:var(--purple); border:none;">
                            <div class="stat-number text-white">100%</div>
                            <div class="stat-label text-white-50">Digital Records</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- RULES -->
<section class="py-5" id="rules" style="background-color: #fafbfc;">
    <div class="container py-5">
        <div class="row justify-content-center mb-5">
            <div class="col-lg-7 text-center">
                <span class="section-label">Code of Conduct</span>
                <h2>Laboratory Policies</h2>
                <p class="text-muted">Standard procedures for a productive learning environment.</p>
            </div>
        </div>
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div style="background:white; border-radius:24px; box-shadow:0 15px 45px rgba(0,0,0,0.05); padding: 40px; border: 1px solid #f0f0f0;">
                    <div class="rule-item">
                        <div class="rule-num">1</div>
                        <div class="rule-text">No food or drinks allowed inside the computer laboratories.</div>
                    </div>
                    <div class="rule-item">
                        <div class="rule-num">2</div>
                        <div class="rule-text">Maintain silence and professional conduct during sessions.</div>
                    </div>
                    <div class="rule-item">
                        <div class="rule-num">3</div>
                        <div class="rule-text">Maximum session duration is <strong>3 hours</strong>.</div>
                    </div>
                    <div class="rule-item">
                        <div class="rule-num">4</div>
                        <div class="rule-text">Proper care of all laboratory equipment is mandatory.</div>
                    </div>
                    <div class="rule-item">
                        <div class="rule-num">5</div>
                        <div class="rule-text">Always log-in and log-out of your sessions accurately.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- LEADERBOARD -->
<section class="py-5" id="leaderboard">
    <div class="container py-5">
        <div class="row justify-content-center mb-5">
            <div class="col-lg-8 text-center">
                <span class="section-label">Leaderboard</span>
                <h2>Top Performance</h2>
                <p class="text-muted">Rankings based on dedicated lab hours and task completion.</p>
            </div>
        </div>
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div style="border-radius:20px; box-shadow:0 20px 50px rgba(92,43,122,0.12); border: 1px solid rgba(0,0,0,0.05); overflow:hidden;">
                    <?php
                    $leaderboard = getLeaderboardData($conn, 10);
                    displayLeaderboard($leaderboard, false);
                    ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FOOTER -->
<footer class="footer">
    <div class="container">
        <div class="mb-4">
            <img src="UCLogo-removebg-preview.png" alt="UC Logo" style="height: 50px; opacity: 0.8;">
        </div>
        <p class="mb-1"><strong>College of Computer Studies — Sit-in Monitoring System</strong></p>
        <p class="small text-muted mb-4">University of Cebu Main Campus &nbsp;|&nbsp; Sanciangko St, Cebu City</p>
        <div class="d-flex justify-content-center gap-3">
            <a href="#" class="text-muted"><i class="fab fa-facebook-f"></i></a>
            <a href="#" class="text-muted"><i class="fab fa-twitter"></i></a>
            <a href="#" class="text-muted"><i class="fab fa-instagram"></i></a>
        </div>
        <hr class="my-4 mx-auto" style="max-width: 200px; opacity: 0.1;">
        <p class="small text-muted mb-0">&copy; 2026 CCS Monitoring System. All rights reserved.</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</body>
</html>