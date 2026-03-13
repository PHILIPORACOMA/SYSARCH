<?php
include "db.php";

if (isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] == "POST") {
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

        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #ddd;
            padding: 0.45rem 0.75rem;
            font-size: 0.88rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-control:focus, .form-select:focus {
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

        /* ── Section dividers inside form ── */
        .field-group-label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: var(--purple);
            font-weight: 700;
            margin-bottom: 8px;
            margin-top: 16px;
            border-bottom: 1px solid #f0e6f7;
            padding-bottom: 4px;
        }

        .logo-col img { max-width: 220px; width: 100%; height: auto; opacity: 0.92; }
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
                <a class="nav-link" href="Loginpage.php">Login</a>
                <a class="nav-link active-link" href="Register.php">Register</a>
            </div>
        </div>
    </nav>

    <div class="container pb-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="form-card p-4 p-md-5">
                    <div class="row align-items-start g-4">

                        <!-- Form side -->
                        <div class="col-md-7">
                            <h3 class="section-title mb-1">Create an Account</h3>
                            <p class="text-muted mb-3" style="font-size:0.85rem">Fill in your details to register</p>

                            <?php if (isset($error)): ?>
                                <div class="alert alert-danger py-2 px-3" style="font-size:0.85rem; border-radius:8px;">
                                    <i class="fa fa-circle-exclamation me-2"></i><?php echo htmlspecialchars($error); ?>
                                </div>
                            <?php endif; ?>
                            <?php if (isset($success)): ?>
                                <div class="alert alert-success py-2 px-3" style="font-size:0.85rem; border-radius:8px;">
                                    <i class="fa fa-circle-check me-2"></i><?php echo htmlspecialchars($success); ?>
                                </div>
                            <?php endif; ?>

                            <form action="" method="POST">

                                <!-- Personal Info -->
                                <div class="field-group-label"><i class="fa fa-user me-1"></i>Personal Information</div>
                                <div class="row g-2 mb-2">
                                    <div class="col-12">
                                        <label class="form-label-sm">ID Number</label>
                                        <input type="text" name="id_number" class="form-control" placeholder="e.g. 2024-00001" required>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label-sm">Last Name</label>
                                        <input type="text" name="last_name" class="form-control" placeholder="Last Name" required>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label-sm">First Name</label>
                                        <input type="text" name="first_name" class="form-control" placeholder="First Name" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label-sm">Middle Name <span class="text-muted">(optional)</span></label>
                                        <input type="text" name="middle_name" class="form-control" placeholder="Middle Name">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label-sm">Address</label>
                                        <input type="text" name="address" class="form-control" placeholder="Home address" required>
                                    </div>
                                </div>

                                <!-- Academic Info -->
                                <div class="field-group-label"><i class="fa fa-graduation-cap me-1"></i>Academic Information</div>
                                <div class="row g-2 mb-2">
                                    <div class="col-sm-6">
                                        <label class="form-label-sm">Course</label>
                                        <select name="course" class="form-select" required>
                                            <option value="">Select Course</option>
                                            <option value="Information Technology">Information Technology</option>
                                            <option value="Computer Engineering">Computer Engineering</option>
                                            <option value="Civil Engineering">Civil Engineering</option>
                                            <option value="Mechanical Engineering">Mechanical Engineering</option>
                                            <option value="Electrical Engineering">Electrical Engineering</option>
                                            <option value="Industrial Engineering">Industrial Engineering</option>
                                            <option value="Naval Architecture and Marine Engineering">Naval Architecture and Marine Engineering</option>
                                            <option value="Elementary Education (BEEd)">Elementary Education (BEEd)</option>
                                            <option value="Secondary Education (BSEd)">Secondary Education (BSEd)</option>
                                            <option value="Criminology">Criminology</option>
                                            <option value="Commerce">Commerce</option>
                                            <option value="Accountancy">Accountancy</option>
                                            <option value="Hotel and Restaurant Management">Hotel and Restaurant Management</option>
                                            <option value="Customs Administration">Customs Administration</option>
                                            <option value="Computer Secretarial">Computer Secretarial</option>
                                            <option value="Industrial Psychology">Industrial Psychology</option>
                                            <option value="AB Political Science">AB Political Science</option>
                                            <option value="AB English">AB English</option>
                                        </select>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label-sm">Year Level</label>
                                        <select name="course_level" class="form-select" required>
                                            <option value="">Select Year</option>
                                            <option value="1">1st Year</option>
                                            <option value="2">2nd Year</option>
                                            <option value="3">3rd Year</option>
                                            <option value="4">4th Year</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Account Info -->
                                <div class="field-group-label"><i class="fa fa-lock me-1"></i>Account Credentials</div>
                                <div class="row g-2 mb-3">
                                    <div class="col-12">
                                        <label class="form-label-sm">Email Address</label>
                                        <input type="email" name="email" class="form-control" placeholder="you@example.com" required>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label-sm">Password</label>
                                        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label-sm">Confirm Password</label>
                                        <input type="password" name="confirm_password" class="form-control" placeholder="••••••••" required>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-submit w-100">
                                    <i class="fa fa-user-plus me-2"></i>Create Account
                                </button>

                                <p class="text-center mt-3 mb-0" style="font-size:0.85rem">
                                    Already have an account?
                                    <a href="Loginpage.php" style="color:var(--purple); font-weight:600;">Login here</a>
                                </p>
                            </form>
                        </div>

                        <!-- Logo side -->
                        <div class="col-md-5 d-none d-md-flex flex-column align-items-center justify-content-center text-center gap-3 pt-4">
                            <img src="CCSLogo.png" alt="CCS Logo" class="logo-col">
                            <p class="text-muted mb-0" style="font-size:0.8rem">
                                College of Computer Studies<br>Sit-in Monitoring System
                            </p>
                            <!-- Mini info panel -->
                            <div class="mt-3 w-100" style="background:#f8f4fc; border-radius:10px; padding:1rem;">
                                <p class="mb-2" style="font-size:0.78rem; color:var(--purple); font-weight:700;">
                                    Why register?
                                </p>
                                <div class="d-flex flex-column gap-2 text-start">
                                    <div style="font-size:0.78rem; color:#666;">
                                        <i class="fa fa-check me-2" style="color:var(--purple);"></i>Track your sit-in sessions
                                    </div>
                                    <div style="font-size:0.78rem; color:#666;">
                                        <i class="fa fa-check me-2" style="color:var(--purple);"></i>View lab announcements
                                    </div>
                                    <div style="font-size:0.78rem; color:#666;">
                                        <i class="fa fa-check me-2" style="color:var(--purple);"></i>Manage your profile
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>