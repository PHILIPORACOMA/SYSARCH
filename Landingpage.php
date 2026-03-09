<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCS | Home</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .navbar-custom { background-color: #5c2b7a; }
        .navbar-brand, .nav-link { color: white !important; padding-left: 10px; }
        
        .hero-section {
            height: calc(100vh - 56px);
            display: flex;
            align-items: center; 
            justify-content: center; 
            background-color: #f8f9fa;
        }

        .hero-image {
            max-width: 400px; 
            width: 100%;
            height: auto;
        }
        .UC-Logo{
            background-color: #5c2b7a;
            height: 40px;
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-custom px-4">
        <div class="container-fluid">
            <img src="UCLogo-removebg-preview.png" alt="logo" class="UC-Logo">
            <a class="navbar-brand fs-6" href="#">College of Computer Studies Sit-in Monitoring System</a>
            <div class="ms-auto d-flex">
                <a class="nav-link px-2" href="Landingpage.php">Home</a>
                <a class="nav-link px-2" href="About.php">About</a>
                <a class="nav-link px-2" href="Community.php">Community</a>
                <a class="nav-link px-2" href="Loginpage.php">Login</a>
                <a class="nav-link px-2" href="Register.php">Register</a>
            </div>
        </div>
    </nav>

    <div class="hero-section">
        <div class="text-center">
            <img src="CCSLogo.png" alt="Logo" class="hero-image">
            <h2 class="mt-3 text-secondary">Welcome to College of Computer Studies Sit-in System</h2>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>