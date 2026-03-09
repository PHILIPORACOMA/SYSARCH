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
                            
                            <form action="" method="POST">
                                <div class="mb-2">
                                    <input type="text" class="form-control" placeholder="ID Number">
                                </div>
                                <div class="mb-2">
                                    <input type="text" class="form-control" placeholder="Last Name">
                                </div>
                                <div class="mb-2">
                                    <input type="text" class="form-control" placeholder="First Name">
                                </div>
                                <div class="mb-2">
                                    <input type="text" class="form-control" placeholder="Middle Name">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label-sm">Course Level</label>
                                    <input type="text" class="form-control" placeholder="Course Level   ">
                                </div>
                                <div class="mb-2">
                                    <input type="password" class="form-control" placeholder="Password">
                                </div>
                                <div class="mb-2">
                                    <input type="password" class="form-control" placeholder="Repeat your password">
                                </div>
                                <div class="mb-2">
                                    <input type="email" class="form-control" placeholder="Email">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label-sm">Course</label>
                                    <input type="text" class="form-control" placeholder="Course">
                                </div>
                                <div class="mb-3">
                                    <input type="text" class="form-control" placeholder="Address">
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