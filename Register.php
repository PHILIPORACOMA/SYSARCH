<?php
session_start();
include "db.php";

if (isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] == "POST") {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "CSRF token validation failed.";
    } else {
        $id_number    = $_POST['id_number'];
        $last_name    = $_POST['last_name'];
        $first_name   = $_POST['first_name'];
        $middle_name  = $_POST['middle_name'];
        $course_level = (int)$_POST['course_level'];
        $raw_password = $_POST['password'];
        $confirm      = $_POST['confirm_password'];
        $email        = $_POST['email'];
        $course       = $_POST['course'];
        $address      = $_POST['address'];

        if ($raw_password !== $confirm) {
            $error = "Passwords do not match!";
        } elseif (empty($id_number) || empty($last_name) || empty($first_name) || empty($course_level) || empty($raw_password) || empty($email) || empty($course) || empty($address)) {
            $error = "All required fields must be filled!";
        } else {
            $check = $conn->prepare("SELECT IdNumber FROM students_info WHERE IdNumber = ? OR Email = ?");
            $check->bind_param("ss", $id_number, $email);
            $check->execute();
            $check->get_result()->num_rows > 0
                ? $error = "ID Number or Email already exists!"
                : null;
            $check->close();

            if (!isset($error)) {
                $hashed = password_hash($raw_password, PASSWORD_DEFAULT);
                $stmt   = $conn->prepare("INSERT INTO students_info (IdNumber, LastName, FirstName, MiddleName, CourseLevel, Password, Email, Course, Address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssissss", $id_number, $last_name, $first_name, $middle_name, $course_level, $hashed, $email, $course, $address);
                $stmt->execute() ? $success = "Registration successful! You can now login." : $error = "Error: " . $stmt->error;
                $stmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCS | Register</title>
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
        .nav-link.active { background-color: rgba(255,255,255,0.15) !important; }
        .UC-Logo { height: 40px; }

        /* ── Register Card ── */
        .register-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        .register-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 25px 50px rgba(92, 43, 122, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 1000px;
            display: flex;
        }

        .register-side-content {
            padding: 40px 50px;
            width: 65%;
            max-height: 85vh;
            overflow-y: auto;
        }

        .register-side-logo {
            background: linear-gradient(135deg, var(--purple) 0%, var(--purple-light) 100%);
            width: 35%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px;
            color: white;
            position: relative;
        }
        .register-side-logo::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: url('https://www.transparenttextures.com/patterns/cubes.png');
            opacity: 0.1;
        }

        .register-side-logo img {
            max-width: 180px;
            filter: drop-shadow(0 10px 20px rgba(0,0,0,0.2));
            z-index: 1;
        }

        .form-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--gray);
            margin-bottom: 5px;
        }

        .form-control, .form-select {
            border-radius: 10px;
            padding: 10px 14px;
            border: 1.5px solid #eee;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--purple);
            box-shadow: 0 0 0 4px rgba(92,43,122,0.1);
        }

        .field-group-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: var(--purple);
            font-weight: 700;
            margin: 25px 0 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .field-group-label::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--purple-soft);
        }

        .btn-register {
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
            margin-top: 20px;
        }
        .btn-register:hover {
            background: var(--purple-light);
            transform: translateY(-2px);
            box-shadow: 0 12px 20px rgba(92,43,122,0.3);
            color: white;
        }

        .register-side-content::-webkit-scrollbar { width: 6px; }
        .register-side-content::-webkit-scrollbar-track { background: transparent; }
        .register-side-content::-webkit-scrollbar-thumb { background: #eee; border-radius: 10px; }

        @media (max-width: 992px) {
            .register-card { flex-direction: column; max-width: 600px; }
            .register-side-content, .register-side-logo { width: 100%; padding: 30px; }
            .register-side-content { max-height: none; }
            .register-side-logo { order: -1; }
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
                <a class="nav-link" href="Loginpage.php">Login</a>
                <a class="nav-link active" href="Register.php">Register</a>
            </div>
        </div>
    </nav>

    <div class="register-container">
        <div class="register-card">
            <!-- Left Side: Form -->
            <div class="register-side-content">
                <h2 style="font-weight: 800; color: var(--purple); margin-bottom: 5px;">Create Account</h2>
                <p style="color: var(--gray); margin-bottom: 10px; font-weight: 500;">Join the CCS monitoring system.</p>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger border-0 shadow-sm mb-4" style="border-radius:12px; font-size:0.85rem;">
                        <i class="fa fa-circle-exclamation me-2"></i><?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($success)): ?>
                    <div class="alert alert-success border-0 shadow-sm mb-4" style="border-radius:12px; font-size:0.85rem;">
                        <i class="fa fa-circle-check me-2"></i><?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <form action="" method="POST">
                    <?php csrf_input(); ?>

                    <div class="field-group-label">Personal Information</div>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">ID Number</label>
                            <input type="text" name="id_number" class="form-control" placeholder="e.g. 23-1234-567" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="last_name" class="form-control" placeholder="Last" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">First Name</label>
                            <input type="text" name="first_name" class="form-control" placeholder="First" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Middle Name</label>
                            <input type="text" name="middle_name" class="form-control" placeholder="Optional">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Home Address</label>
                            <input type="text" name="address" class="form-control" placeholder="Full address" required>
                        </div>
                    </div>

                    <div class="field-group-label">Academic Details</div>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Course</label>
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
                            <label class="form-label">Year Level</label>
                            <select name="course_level" class="form-select" required>
                                <option value="">Select</option>
                                <option value="1">1st Year</option>
                                <option value="2">2nd Year</option>
                                <option value="3">3rd Year</option>
                                <option value="4">4th Year</option>
                            </select>
                        </div>
                    </div>

                    <div class="field-group-label">Account Credentials</div>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control" placeholder="name@example.com" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" name="confirm_password" class="form-control" placeholder="••••••••" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-register">
                        Create Account
                    </button>
                </form>

                <p class="text-center mt-4 mb-0" style="font-size: 0.9rem; color: var(--gray);">
                    Already have an account? 
                    <a href="Loginpage.php" style="color: var(--purple); font-weight: 700; text-decoration: none;">Log In</a>
                </p>
            </div>

            <!-- Right Side: Logo/Branding -->
            <div class="register-side-logo">
                <img src="CCSLogo.png" alt="CCS Logo">
                <h4 class="mt-4 fw-bold text-white text-center">Registration</h4>
                <p class="text-white-50 text-center small px-4 mt-2">Create your account to start tracking your sit-in sessions and access laboratory resources.</p>
                
                <div class="mt-auto pt-4 text-center">
                    <p class="small text-white-50 mb-0">&copy; 2026 University of Cebu</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>