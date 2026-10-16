<?php
session_start();
include "../conn.php"; 

$error = ""; 
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header("Location: admin.php");
    exit();
} elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'staff') {
    header("Location: reception/index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($user = $result->fetch_assoc()) {
            if (password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                
                // Fixed logic: Handles routing accurately based on role types
                if ($user['role'] === 'staff') {
                    header("Location: reception/index.php");
                } elseif ($user['role'] === 'admin') {
                    header("Location: admin.php");
                } else {
                    // Safety fallback if role is neither admin nor staff
                    header("Location: ../index.php");
                }
                exit();
            } else { $error = "Invalid credentials!"; }
        } else { $error = "Invalid credentials!"; }
    } else { $error = "Please fill all fields."; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Avianna's Resort</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://jsdelivr.net">

    <style>
        :root {
            --admin-dark: #0e2a1d;
            --admin-teal: #1a4731;
            --admin-accent: #ffc107;
        }

        body { 
            background: linear-gradient(135deg, #0e2a1d 0%, #1a4731 100%);
            font-family: 'Poppins', sans-serif;
            height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center;
            margin: 0;
        }

        #scrollUp, .scroll-to-top, .back-to-top, [id*="scroll"], button[title*="top"] {
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
        }

        .login-card { 
            width: 100%; 
            max-width: 400px; 
            padding: 20px; 
        }

        .card {
            border: none;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.98);
            box-shadow: 0 15px 35px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .card-header-brand {
            background: var(--admin-dark);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .card-header-brand h3 {
            font-weight: 600;
            letter-spacing: 1px;
            margin: 0;
            font-size: 1.5rem;
        }

        .card-header-brand span {
            color: var(--admin-accent);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .form-label {
            color: var(--admin-teal);
            font-weight: 600;
            font-size: 0.85rem;
            margin-bottom: 8px;
        }

        .form-control {
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 12px;
        }

        .form-control:focus {
            border-color: var(--admin-teal);
            box-shadow: 0 0 0 0.25rem rgba(26, 71, 49, 0.1);
        }

        /* Styling for the eye toggle icon */
        .password-wrapper {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
            z-index: 10;
        }

        .password-toggle:hover {
            color: var(--admin-teal);
        }

        .btn-primary {
            background-color: var(--admin-teal);
            border: none;
            border-radius: 10px;
            font-weight: 600;
            padding: 12px;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background-color: var(--admin-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .alert {
            border-radius: 10px;
            border: none;
            background-color: #fff5f5;
            color: #c53030;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: rgba(255,255,255,0.6);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }

        .back-link:hover {
            color: var(--admin-accent);
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="card">
            <div class="card-header-brand">
                <span>Resort Management</span>
                <h3>Avianna's Portal</h3>
            </div>
            <div class="card-body p-4">
                <?php if($error): ?>
                    <div class="alert alert-danger py-2 small text-center mb-3">
                        <strong>Wait!</strong> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="post">
                    <div class="mb-3">
                        <label for="username" class="form-label">Admin Username</label>
                        <input type="text" name="username" class="form-control" id="username" placeholder="Enter username" required>
                    </div>
                    
                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <div class="password-wrapper">
                            <input type="password" name="password" class="form-control" id="password" placeholder="Enter password" required>
                            <i class="bi bi-eye-slash password-toggle" id="togglePassword"></i>
                        </div>
                    </div>

                    <button class="btn btn-primary w-100 py-2 mb-2" type="submit">Authorized Sign In</button>
                </form>
            </div>
        </div>
        <a href="../index.php" class="back-link">← Return to Public Website</a>
    </div>

    <!-- Password visibility toggle script -->
    <script>
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');

        togglePassword.addEventListener('click', function () {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.classList.toggle('bi-eye');
            this.classList.toggle('bi-eye-slash');
        });
    </script>
</body>
</html>
