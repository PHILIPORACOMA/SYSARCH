<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "sysarchstudents";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle form submission
if (isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] == "POST") {
    $id_number = $_POST['id_number'];
    $last_name = $_POST['last_name'];
    $first_name = $_POST['first_name'];
    $middle_name = $_POST['middle_name'];
    $course_level = (int)$_POST['course_level'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $confirm_password = $_POST['confirm_password'];
    $email = $_POST['email'];
    $course = $_POST['course'];
    $address = $_POST['address'];

    // Basic validation
    if ($_POST['password'] !== $confirm_password) {
        $error = "Passwords do not match!";
    } elseif (empty($id_number) || empty($last_name) || empty($first_name) || empty($course_level) || empty($_POST['password']) || empty($email) || empty($course) || empty($address)) {
        $error = "All required fields must be filled!";
    } else {
        // Check if ID number or email already exists
        $check_sql = "SELECT IdNumber FROM students_info WHERE IdNumber = ? OR Email = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ss", $id_number, $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $error = "ID Number or Email already exists!";
        } else {
            // Insert new student
            $sql = "INSERT INTO students_info (IdNumber, LastName, FirstName, MiddleName, CourseLevel, Password, Email, Course, Address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssissss", $id_number, $last_name, $first_name, $middle_name, $course_level, $password, $email, $course, $address);

            if ($stmt->execute()) {
                $success = "Registration successful! You can now login.";
            } else {
                $error = "Error: " . $stmt->error;
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCS | Register</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .buttonReg{
            background-color: #c09412;
        }
        .navbar-custom { background-color: #5c2b7a; }
        .navbar-brand, .nav-link { color: white !important; padding-left: 10px; }
        .reg-card { 
            background: white; 
            border-radius: 20px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.05); 
            border: none;
        }
        .btn-back { background-color: #c09412; color: white; border: none; font-size: 0.8rem; padding: 4px 12px; border-radius: 5px; }
        .form-label-sm { font-size: 0.75rem; color: #777; margin-bottom: 2px; }
        .form-control { border-radius: 0; border: 1px solid #ddd; padding: 0.4rem 0.75rem; }
        .CCS-size {  max-width: 500px; width: 100%; height: auto; }
        .UC-Logo{
            background-color: #5c2b7a;
            height: 40px;
        }
      
        .modal-container { position: relative; }
        .modal-backdrop { position: absolute; inset: 0; border-radius: 20px; background: rgba(0,0,0,0.02); z-index: 0; pointer-events: none; }
        .reg-card { position: relative; z-index: 1; border: 1px solid rgba(0,0,0,0.08); box-shadow: 0 20px 40px rgba(0,0,0,0.08), 0 6px 12px rgba(0,0,0,0.04); outline: 6px solid rgba(0,0,0,0.03); }
    </style>
</head>
<body class="bg-light">

    <nav class="navbar navbar-expand-lg navbar-custom px-4 mb-4">
        <div class="container-fluid">
            <img src="UCLogo-removebg-preview.png" alt="Logo" class="UC-Logo">
            <a class="navbar-brand fs-6" href="index.php">College of Computer Studies Sit-in Monitoring System</a>
            <div class="ms-auto d-flex">
                <a class="nav-link px-2" href="Landingpage.php">Home</a>
                <a class="nav-link px-2" href="About.php">About</a>
                <a class="nav-link px-2" href="Community.php">Community</a>
                <a class="nav-link px-2" href="Loginpage.php">Login</a>
                <a class="nav-link px-2" href="Register.php">Register</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="modal-container">
                    <div class="modal-backdrop"></div>
                    <div class="card reg-card p-4 p-md-5">
                    <div class="row">
                        <div class="col-md-6">
                            <button class="btn-back mb-3">Back</button>
                            <h2 class="fw-bold mb-4">Sign up</h2>
                            
                            <?php if (isset($error)): ?>
                                <div class="alert alert-danger"><?php echo $error; ?></div>
                            <?php endif; ?>
                            <?php if (isset($success)): ?>
                                <div class="alert alert-success"><?php echo $success; ?></div>
                            <?php endif; ?>
                            
                            <form action="" method="POST">
                                <div class="mb-2">
                                    <input type="text" name="id_number" class="form-control" placeholder="ID Number" required>
                                </div>
                                <div class="mb-2">
                                    <input type="text" name="last_name" class="form-control" placeholder="Last Name" required>
                                </div>
                                <div class="mb-2">
                                    <input type="text" name="first_name" class="form-control" placeholder="First Name" required>
                                </div>
                                <div class="mb-2">
                                    <input type="text" name="middle_name" class="form-control" placeholder="Middle Name">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label-sm">Course Level</label>
                                    <select name="course_level" class="form-control" required>
                                        <option value="">Select Course Level</option>
                                        <option value="1">1st Year</option>
                                        <option value="2">2nd Year</option>
                                        <option value="3">3rd Year</option>
                                        <option value="4">4th Year</option>
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <input type="password" name="password" class="form-control" placeholder="Password" required>
                                </div>
                                <div class="mb-2">
                                    <input type="password" name="confirm_password" class="form-control" placeholder="Repeat your password" required>
                                </div>
                                <div class="mb-2">
                                    <input type="email" name="email" class="form-control" placeholder="Email" required>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label-sm">Course</label>
                                    <select name="course" class="form-control" required>
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
                                <div class="mb-3">
                                    <input type="text" name="address" class="form-control" placeholder="Address" required>
                                </div>
                                
                                <button type="submit" class="btn btn-primary px-4 py-1 buttonReg" style="color:black;">Register</button>
                            </form>
                        </div>

                        <div class="col-md-6 d-none d-md-flex align-items-center justify-content-center">
                            <img src="CCSLogo.png" class="CCS-size" alt="Registration Illustration">
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