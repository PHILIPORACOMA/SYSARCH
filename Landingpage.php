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
            --gold:   #c09412;
        }

        /* ── Navbar ── */
        .navbar-custom { background-color: var(--purple); }
        .navbar-brand, .nav-link { color: white !important; }
        .nav-link { padding: 0.4rem 0.75rem !important; border-radius: 6px; transition: background 0.2s; }
        .nav-link:hover { background-color: rgba(255,255,255,0.12) !important; color: #f0d080 !important; }
        .nav-link.active-link { background-color: rgba(255,255,255,0.18) !important; }
        .UC-Logo { background-color: var(--purple); height: 40px; }

        body { background-color: #f8f9fa; margin: 0; }

        /* ── Hero ── */
        .hero-section {
            background: linear-gradient(135deg, #f3eaf9 0%, #f8f9fa 60%, #fdf6e3 100%);
            padding: 60px 0 40px;
        }

        .hero-logo {
            max-width: 360px;
            width: 100%;
            height: auto;
            filter: drop-shadow(0 8px 24px rgba(92,43,122,0.18));
        }

        .hero-title {
            color: var(--purple);
            font-weight: 800;
            font-size: 1.9rem;
            line-height: 1.25;
        }

        .hero-subtitle {
            color: var(--gold);
            font-weight: 600;
            font-size: 1rem;
            letter-spacing: 0.02em;
        }

        .hero-desc {
            color: #666;
            font-size: 0.93rem;
            max-width: 500px;
        }

        .divider-gold {
            width: 48px;
            height: 4px;
            background-color: var(--gold);
            border-radius: 4px;
            margin: 12px 0 16px;
        }

        /* ── Feature cards ── */
        .feature-card {
            background: white;
            border-radius: 14px;
            border: none;
            box-shadow: 0 4px 18px rgba(92,43,122,0.08);
            padding: 1.5rem 1.2rem;
            text-align: center;
            height: 100%;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .feature-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 28px rgba(92,43,122,0.13);
        }
        .feature-icon {
            width: 52px; height: 52px;
            border-radius: 12px;
            background-color: #f3eaf9;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.3rem;
            color: var(--purple);
        }
        .feature-card h6 {
            color: var(--purple);
            font-weight: 700;
            font-size: 0.92rem;
            margin-bottom: 6px;
        }
        .feature-card p {
            color: #888;
            font-size: 0.8rem;
            margin: 0;
            line-height: 1.5;
        }

        /* ── About section ── */
        .about-section {
            background: white;
            padding: 56px 0;
        }

        .about-section .section-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--gold);
            font-weight: 700;
        }

        .about-section h2 {
            color: var(--purple);
            font-weight: 800;
            font-size: 1.6rem;
        }

        .about-section p {
            color: #666;
            font-size: 0.92rem;
            line-height: 1.75;
        }

        .stat-box {
            text-align: center;
            padding: 1.2rem;
            background: #f8f4fc;
            border-radius: 12px;
        }
        .stat-box .stat-number {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--purple);
            line-height: 1;
        }
        .stat-box .stat-label {
            font-size: 0.78rem;
            color: #999;
            margin-top: 4px;
        }

        /* ── Rules section ── */
        .rules-section {
            background: linear-gradient(135deg, #f3eaf9, #fdf6e3);
            padding: 56px 0;
        }

        .rules-section h2 {
            color: var(--purple);
            font-weight: 800;
            font-size: 1.6rem;
        }

        .rule-item {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 14px 0;
            border-bottom: 1px solid rgba(92,43,122,0.08);
        }
        .rule-item:last-child { border-bottom: none; }

        .rule-num {
            width: 32px; height: 32px;
            min-width: 32px;
            border-radius: 50%;
            background-color: var(--purple);
            color: white;
            font-size: 0.8rem;
            font-weight: 700;
            display: flex; align-items: center; justify-content: center;
        }
        .rule-text {
            font-size: 0.9rem;
            color: #555;
            padding-top: 5px;
        }

        /* ── Footer ── */
        .footer {
            background-color: var(--purple);
            color: rgba(255,255,255,0.75);
            padding: 24px 0;
            font-size: 0.82rem;
            text-align: center;
        }
        .footer strong { color: white; }
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
            <div class="ms-auto d-flex align-items-center gap-1">
                <a class="nav-link active-link" href="Landingpage.php">Home</a>
                <a class="nav-link" href="About.php">About</a>
                <a class="nav-link" href="Community.php">Community</a>
                <a class="nav-link" href="Loginpage.php">Login</a>
                <a class="nav-link" href="Register.php">Register</a>
            </div>
        </div>
    </nav>

    <!-- HERO -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-md-7">
                    <p class="hero-subtitle mb-1">
                        <i class="fa fa-location-dot me-1"></i>University of Cebu — Main Campus
                    </p>
                    <h1 class="hero-title">College of Computer Studies<br>Sit-in Monitoring System</h1>
                    <div class="divider-gold"></div>
                    <p class="hero-desc mb-4">
                        A digital platform designed to manage and monitor student sit-in sessions
                        in the CCS computer laboratories. Track usage, view announcements, and
                        maintain a clean and organized lab environment.
                    </p>

                    <!-- Feature strip -->
                    <div class="row g-3 mt-2">
                        <div class="col-6 col-sm-3">
                            <div class="feature-card">
                                <div class="feature-icon"><i class="fa fa-clock"></i></div>
                                <h6>Session Tracking</h6>
                                <p>Monitor time-in and time-out records</p>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <div class="feature-card">
                                <div class="feature-icon"><i class="fa fa-bullhorn"></i></div>
                                <h6>Announcements</h6>
                                <p>Stay updated with lab notices</p>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <div class="feature-card">
                                <div class="feature-icon"><i class="fa fa-shield-halved"></i></div>
                                <h6>Secure Access</h6>
                                <p>Student-only authenticated login</p>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <div class="feature-card">
                                <div class="feature-icon"><i class="fa fa-laptop"></i></div>
                                <h6>Lab Management</h6>
                                <p>Organized computer lab records</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-5 text-center">
                    <img src="CCSLogo.png" alt="CCS Logo" class="hero-logo">
                </div>
            </div>
        </div>
    </section>

    <!-- ABOUT SECTION -->
    <section class="about-section">
        <div class="container">
            <div class="row g-5 align-items-center">
                <div class="col-md-7">
                    <span class="section-label">About the System</span>
                    <h2 class="mt-1 mb-3">What is the CCS Sit-in Monitoring System?</h2>
                    <p>
                        The CCS Sit-in Monitoring System is an official web-based tool developed for the
                        <strong>College of Computer Studies</strong> at the <strong>University of Cebu Main Campus</strong>.
                        It was built to replace manual logbooks and streamline the process of recording
                        student usage of the computer laboratories.
                    </p>
                    <p>
                        Students can register, log in, and view their complete sit-in history.
                        Faculty and administrators can post announcements, track session data,
                        and ensure that laboratory rules and time limits are properly enforced.
                    </p>
                    <p>
                        The system promotes accountability, transparency, and efficient use of
                        shared computing resources within the college.
                    </p>
                </div>
                <div class="col-md-5">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="stat-box">
                                <div class="stat-number">3hrs</div>
                                <div class="stat-label">Max Session Length</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stat-box">
                                <div class="stat-number">24/7</div>
                                <div class="stat-label">Record Access</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stat-box">
                                <div class="stat-number">CCS</div>
                                <div class="stat-label">College of Computer Studies</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stat-box">
                                <div class="stat-number">UC</div>
                                <div class="stat-label">University of Cebu</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- RULES SECTION -->
    <section class="rules-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-7 text-center mb-4">
                    <span class="section-label" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.1em; color:var(--gold); font-weight:700;">
                        Laboratory Policies
                    </span>
                    <h2 class="mt-1" style="color:var(--purple); font-weight:800; font-size:1.6rem;">
                        Rules &amp; Regulations
                    </h2>
                    <p style="color:#888; font-size:0.88rem;">
                        All students must comply with the following rules when using the CCS computer laboratories.
                    </p>
                </div>
            </div>
            <div class="row justify-content-center">
                <div class="col-lg-7">
                    <div style="background:white; border-radius:16px; box-shadow:0 4px 20px rgba(92,43,122,0.08); padding: 1.5rem 2rem;">
                        <div class="rule-item">
                            <div class="rule-num">1</div>
                            <div class="rule-text">No food or drinks are allowed inside the computer laboratory at any time.</div>
                        </div>
                        <div class="rule-item">
                            <div class="rule-num">2</div>
                            <div class="rule-text">Students must maintain silence and avoid disturbing others during sit-in sessions.</div>
                        </div>
                        <div class="rule-item">
                            <div class="rule-num">3</div>
                            <div class="rule-text">The maximum allowed sit-in session duration is <strong>3 hours</strong> per visit.</div>
                        </div>
                        <div class="rule-item">
                            <div class="rule-num">4</div>
                            <div class="rule-text">Students are responsible for the proper use and care of all laboratory equipment.</div>
                        </div>
                        <div class="rule-item">
                            <div class="rule-num">5</div>
                            <div class="rule-text">Every sit-in session must be logged in the system upon arrival and departure.</div>
                        </div>
                        <div class="rule-item">
                            <div class="rule-num">6</div>
                            <div class="rule-text">Unauthorized installation of software or modification of system settings is strictly prohibited.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer class="footer">
        <div class="container">
            <strong>College of Computer Studies — Sit-in Monitoring System</strong><br>
            University of Cebu Main Campus &nbsp;|&nbsp; Sanciangko St, Cebu City
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>