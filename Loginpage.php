<?php
session_start();

$login_success = isset($_SESSION['login_success']) && $_SESSION['login_success'];

include "db.php";

if (isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] == "POST") {
    $email    = $_POST['email'];
    $password = $_POST['password'];

    $sql  = "SELECT IdNumber, Password, FirstName, LastName FROM students_info WHERE Email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user && password_verify($password, $user['Password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id']       = $user['IdNumber'];
        $_SESSION['user_name']     = $user['FirstName'] . ' ' . $user['LastName'];
        $_SESSION['login_success'] = true;
        header("Location: student_dashboard.php");
        exit();
    } else {
        $error = "Invalid email or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCS | Login</title>
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

        body { background-color: #f8f9fa; }

        /* ── Form card ── */
        .form-card {
            background: white;
            border-radius: 16px;
            border: 1px solid rgba(0,0,0,0.07);
            box-shadow: 0 20px 50px rgba(92,43,122,0.10), 0 6px 16px rgba(0,0,0,0.05);
        }

        .form-label-sm { font-size: 0.78rem; color: #777; margin-bottom: 3px; font-weight: 500; }

        .form-control {
            border-radius: 8px;
            border: 1px solid #ddd;
            padding: 0.45rem 0.75rem;
            font-size: 0.9rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-control:focus {
            border-color: var(--purple);
            box-shadow: 0 0 0 3px rgba(92,43,122,0.12);
        }

        .btn-submit {
            background-color: var(--gold);
            color: #1a1a1a;
            border: none;
            border-radius: 8px;
            padding: 0.5rem 1.8rem;
            font-weight: 600;
            transition: background 0.2s;
        }
        .btn-submit:hover { background-color: #a87e0f; color: white; }

        .section-title { color: var(--purple); font-weight: 700; }

        .logo-col img { max-width: 260px; width: 100%; height: auto; opacity: 0.92; }

        .divider-text {
            display: flex; align-items: center; gap: 10px;
            color: #bbb; font-size: 0.8rem; margin: 1rem 0;
        }
        .divider-text::before,
        .divider-text::after {
            content: ''; flex: 1; border-top: 1px solid #eee;
        }

        /* ── Success card (after login) ── */
        .success-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 50px rgba(92,43,122,0.10);
        }
        .success-icon { color: #28a745; font-size: 3.5rem; }
        .welcome-text { color: var(--purple); font-weight: 700; }

        .info-card {
            border: none;
            border-radius: 10px;
            background: #f8f4fc;
        }
        .info-card .card-title { font-size: 0.8rem; color: #999; text-transform: uppercase; letter-spacing: 0.05em; }

        .btn-go-home {
            background-color: var(--purple); color: white; border: none; border-radius: 8px;
        }
        .btn-go-home:hover { background-color: #4a2263; color: white; }
        .btn-go-logout {
            background-color: var(--gold); color: #1a1a1a; border: none; border-radius: 8px;
        }
        .btn-go-logout:hover { background-color: #a87e0f; color: white; }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg navbar-custom px-4 mb-4">
        <div class="container-fluid">
            <img src="UCLogo-removebg-preview.png" alt="UC Logo" class="UC-Logo">
            <a class="navbar-brand fs-6 ms-2" href="Landingpage.php">
                College of Computer Studies Sit-in Monitoring System
            </a>
            <div class="ms-auto d-flex align-items-center gap-1">
                <a class="nav-link" href="Landingpage.php">Home</a>
                <a class="nav-link" href="About.php">About</a>
                <a class="nav-link" href="Community.php">Community</a>
                <a class="nav-link active-link" href="Loginpage.php">Login</a>
                <a class="nav-link" href="Register.php">Register</a>
            </div>
        </div>
    </nav>

    <div class="container pb-5">

        <?php if ($login_success): ?>
        <!-- ── SUCCESS STATE ── -->
        <div class="row justify-content-center align-items-center" style="min-height:65vh;">
            <div class="col-lg-7">
                <div class="success-card p-5 text-center">
                    <i class="fa fa-circle-check success-icon mb-3"></i>
                    <h2 class="welcome-text mb-2">Login Successful!</h2>
                    <h5 class="text-muted mb-4">Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?></h5>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="card info-card p-3">
                                <div class="card-title mb-1">ID Number</div>
                                <div class="fw-bold fs-5" style="color:var(--purple)">
                                    <?php echo htmlspecialchars($_SESSION['user_id']); ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card info-card p-3">
                                <div class="card-title mb-1">Quick Actions</div>
                                <div class="d-flex gap-2 justify-content-center flex-wrap">
                                    <a href="student_dashboard.php" class="btn btn-go-home btn-sm px-3">Dashboard</a>
                                    <a href="logout.php" class="btn btn-go-logout btn-sm px-3">Logout</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p class="text-muted" style="font-size:0.85rem">
                        You are now logged in to the CCS Sit-in Monitoring System.
                    </p>
                </div>
            </div>
        </div>

        <?php else: ?>
        <!-- ── LOGIN FORM ── -->
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <div class="form-card p-4 p-md-5">
                    <div class="row align-items-center g-4">

                        <!-- Form side -->
                        <div class="col-md-6">
                            <h3 class="section-title mb-1">Welcome back</h3>
                            <p class="text-muted mb-4" style="font-size:0.85rem">Sign in to your CCS account</p>

                            <?php if (isset($error)): ?>
                                <div class="alert alert-danger py-2 px-3" style="font-size:0.85rem; border-radius:8px;">
                                    <i class="fa fa-circle-exclamation me-2"></i><?php echo htmlspecialchars($error); ?>
                                </div>
                            <?php endif; ?>

                            <form action="" method="POST">
                                <div class="mb-3">
                                    <label class="form-label-sm">Email Address</label>
                                    <input type="email" name="email" class="form-control" placeholder="you@example.com" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label-sm">Password</label>
                                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                                </div>
                                <div class="mb-4 form-check">
                                    <input type="checkbox" class="form-check-input" id="rememberCheck">
                                    <label class="form-check-label" for="rememberCheck" style="font-size:0.85rem">Remember me</label>
                                </div>
                                <button type="submit" class="btn btn-submit w-100">
                                    <i class="fa fa-right-to-bracket me-2"></i>Login
                                </button>
                            </form>

                            <div class="divider-text">or</div>

                            <p class="text-center mb-0" style="font-size:0.85rem">
                                Don't have an account?
                                <a href="Register.php" style="color:var(--purple); font-weight:600;">Register here</a>
                            </p>
                        </div>

                        <!-- Logo side -->
                        <div class="col-md-6 d-none d-md-flex flex-column align-items-center justify-content-center text-center gap-3">
                            <img src="CCSLogo.png" alt="CCS Logo" style="max-width:220px; width:100%; height:auto;">
                            <p class="text-muted mb-0" style="font-size:0.8rem">
                                College of Computer Studies<br>Sit-in Monitoring System
                            </p>
                        </div>

                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>