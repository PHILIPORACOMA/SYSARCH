<?php
session_start();

include "db.php";

// Check for session expiration message
$session_expired = isset($_GET['session_expired']) && $_GET['session_expired'] == 1;

if (isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] == "POST") {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "CSRF token validation failed. Please refresh and try again.";
    } else {
        $login_id = trim($_POST['id_number']);
        $password = $_POST['password'];

        // Use IdNumber as the identifier (can be Student ID or Admin Username)
        $sql  = "SELECT IdNumber, Password, FirstName, LastName, is_admin FROM students_info WHERE IdNumber = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $login_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['Password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']       = $user['IdNumber'];
            $_SESSION['user_name']     = $user['FirstName'] . ' ' . $user['LastName'];
            $_SESSION['is_admin']      = (int)$user['is_admin'];
            $_SESSION['login_success'] = true;
            $_SESSION['login_time']    = time(); 
            $_SESSION['last_activity'] = time(); 
            
            $redirect = (!empty($user['is_admin'])) ? "admin_dashboard.php" : "student_dashboard.php";
            header("Location: " . $redirect);
            exit();
        } else {
            $error = "Invalid Student ID / Username or password.";
        }
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
            --purple-light: #763b9a;
            --purple-soft: #f3eaf9;
            --gold:   #c09412;
            --gold-light: #e6b119;
            --dark: #1a1a1a;
            --gray: #6c757d;
        }

        body { 
            background: linear-gradient(135deg, var(--purple-soft) 0%, #ffffff 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            margin: 0;
            display: flex;
            flex-direction: column;
        }

        /* ── Navbar ── */
        .navbar-custom { 
            background-color: var(--purple); 
            padding: 12px 0;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        .navbar-brand { font-weight: 700; letter-spacing: -0.5px; }
        .nav-link { 
            font-weight: 500; 
            color: white !important;
            padding: 0.5rem 1rem !important; 
            border-radius: 8px; 
            transition: all 0.2s; 
        }
        .nav-link:hover { background-color: rgba(255,255,255,0.1) !important; color: #f0d080 !important; }
        .UC-Logo { height: 40px; }

        /* ── Login Card ── */
        .login-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 25px 50px rgba(92, 43, 122, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 900px;
            display: flex;
        }

        .login-side-content {
            padding: 50px;
            width: 55%;
        }

        .login-side-logo {
            background: linear-gradient(135deg, var(--purple) 0%, var(--purple-light) 100%);
            width: 45%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px;
            color: white;
            position: relative;
        }
        .login-side-logo::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: url('https://www.transparenttextures.com/patterns/cubes.png');
            opacity: 0.1;
        }

        .login-side-logo img {
            max-width: 200px;
            filter: drop-shadow(0 10px 20px rgba(0,0,0,0.2));
            z-index: 1;
        }

        .form-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--gray);
            margin-bottom: 8px;
        }

        .form-control {
            border-radius: 12px;
            padding: 12px 16px;
            border: 2px solid #eee;
            font-size: 0.95rem;
            transition: all 0.2s;
        }
        .form-control:focus {
            border-color: var(--purple);
            box-shadow: 0 0 0 4px rgba(92,43,122,0.1);
        }

        .btn-login {
            background: var(--purple);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 14px;
            font-weight: 700;
            font-size: 1rem;
            width: 100%;
            transition: all 0.3s;
            box-shadow: 0 8px 15px rgba(92,43,122,0.2);
            margin-top: 10px;
        }
        .btn-login:hover {
            background: var(--purple-light);
            transform: translateY(-2px);
            box-shadow: 0 12px 20px rgba(92,43,122,0.3);
            color: white;
        }

        @media (max-width: 768px) {
            .login-card { flex-direction: column; max-width: 500px; }
            .login-side-content, .login-side-logo { width: 100%; padding: 40px; }
            .login-side-logo { order: -1; }
        }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg navbar-custom px-4 sticky-top">
        <div class="container">
            <div class="d-flex align-items-center">
                <img src="UCLogo-removebg-preview.png" alt="UC Logo" class="UC-Logo">
                <a class="navbar-brand fs-5 ms-3 text-white" href="Landingpage.php">CCS Sit-in</a>
            </div>
            <div class="ms-auto d-none d-lg-flex">
                <a class="nav-link" href="Landingpage.php">Home</a>
                <a class="nav-link active" href="Loginpage.php">Login</a>
                <a class="nav-link" href="Register.php">Register</a>
            </div>
        </div>
    </nav>

    <div class="login-container">
        <div class="login-card">
            <!-- Left Side: Form -->
            <div class="login-side-content">
                <h2 style="font-weight: 800; color: var(--purple); margin-bottom: 5px;">Welcome Back</h2>
                <p style="color: var(--gray); margin-bottom: 30px; font-weight: 500;">Please log in using your student credentials.</p>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger border-0 shadow-sm mb-4" style="border-radius:12px; font-size:0.85rem;">
                        <i class="fa fa-circle-exclamation me-2"></i><?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($session_expired): ?>
                    <div class="alert alert-warning border-0 shadow-sm mb-4" style="border-radius:12px; font-size:0.85rem;">
                        <i class="fa fa-clock me-2"></i>Your session has expired. Please login again.
                    </div>
                <?php endif; ?>

                <form action="" method="POST">
                    <?php csrf_input(); ?>
                    <div class="mb-3">
                        <label class="form-label">Student ID or Username</label>
                        <input type="text" name="id_number" class="form-control" placeholder="e.g. 21234567" required autofocus>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                    </div>
                    <button type="submit" class="btn btn-login">
                        Log In
                    </button>
                </form>

                <p class="text-center mt-4 mb-0" style="font-size: 0.9rem; color: var(--gray);">
                    Don't have an account? 
                    <a href="Register.php" style="color: var(--purple); font-weight: 700; text-decoration: none;">Register</a>
                </p>
            </div>

            <!-- Right Side: Logo/Branding -->
            <div class="login-side-logo">
                <img src="CCSLogo.png" alt="CCS Logo">
                <h4 class="mt-4 fw-bold text-white text-center">College of Computer Studies</h4>
                <p class="text-white-50 text-center small px-4 mt-2">Laboratory Sit-in Monitoring & Resource Management System</p>
                
                <div class="mt-auto pt-4 text-center">
                    <p class="small text-white-50 mb-0">&copy; 2026 University of Cebu</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>