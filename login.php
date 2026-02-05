<?php

// Enable error reporting for debugging
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// Include lang_loader first as it handles session management
require_once __DIR__ . '/includes/lang_loader.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/config/version.php';

$error = '';
$success = '';

if (isset($_SESSION['user'])) {
    if  ($_SESSION['role'] == 'admin_user') {
        header("Location: admin/admin_dashboard.php");
    } elseif ($_SESSION['role'] == 'specialist_user') {
        header("Location: booking_specialist_view.php?specialist_id=" . $_SESSION['specialist_id']);
    } elseif ($_SESSION['role'] == 'organisation_user') {
        header("Location: organisation_dashboard.php");
    } elseif ($_SESSION['role'] == 'workpoint_user') {
        // Redirect supervisors directly to booking supervisor view with their workpoint ID
        header("Location: booking_supervisor_view.php?working_point_user_id=" . $_SESSION['workpoint_id']);
    } else {
        header("Location: booking_specialist_view.php");
    }
    exit;
}
// Check if user is already logged in
// if (isset($_SESSION['user'])) {
//     // Redirect based on role
//     switch ($_SESSION['role']) {
//         case 'admin_user':
//             header("Location: admin/admin_dashboard.php");
//             break;
//         case 'organisation_user':
//             header("Location: organisation_dashboard.php");
//             break;
//         case 'workpoint_user':
//             // Redirect supervisors directly to booking supervisor view with their workpoint ID
//             header("Location: booking_supervisor_view.php?working_point_user_id=" . $user['unic_id']);
//             break;
//         case 'specialist_user':
//             header("Location: specialist_dashboard.php");
//             break;
//     }
//     exit;
// }

// Handle login attempts
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt_time'] = 0;
}

// Check if user is blocked due to too many attempts
if ($_SESSION['login_attempts'] >= 4) {
    $timeSinceLastAttempt = time() - $_SESSION['last_attempt_time'];
    if ($timeSinceLastAttempt < 300) { // 5 minutes = 300 seconds
        $remainingTime = 300 - $timeSinceLastAttempt;
        $minutes = floor($remainingTime / 60);
        $seconds = $remainingTime % 60;
        $error = "Too many login attempts. Please wait {$minutes}:{$seconds} before trying again.";
    } else {
        // Reset attempts after 5 minutes
        $_SESSION['login_attempts'] = 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // First check super_users table
    $stmt = $pdo->prepare("SELECT * FROM super_users WHERE user = ? AND pasword = ?");
    $stmt->execute([$username, $password]);
    $user = $stmt->fetch();

    if ($user) {
        // Admin user found
        $_SESSION['user'] = $user['user'];
        $_SESSION['role'] = 'admin_user';
        $_SESSION['user_id'] = $user['unic_id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['login_attempts'] = 0; // Reset attempts on successful login
        
        header("Location: admin/admin_dashboard.php");
        exit;
    }

    // Check organisations table
    $stmt = $pdo->prepare("SELECT * FROM organisations WHERE user = ? AND pasword = ?");
    $stmt->execute([$username, $password]);
    $user = $stmt->fetch();

    if ($user) {
        // Organisation user found
        $_SESSION['user'] = $user['user'];
        $_SESSION['role'] = 'organisation_user';
        $_SESSION['user_id'] = $user['unic_id'];
        $_SESSION['organisation_id'] = $user['unic_id'];
        $_SESSION['organisation_name'] = $user['alias_name'];
        $_SESSION['login_attempts'] = 0;
        
        header("Location: organisation_dashboard.php");
        exit;
    }

    // Check working_points table
    $stmt = $pdo->prepare("SELECT * FROM working_points WHERE user = ? AND password = ?");
    $stmt->execute([$username, $password]);
    $user = $stmt->fetch();

    if ($user) {
        // Workpoint supervisor found
        $_SESSION['user'] = $user['user'];
        $_SESSION['role'] = 'workpoint_user';
        $_SESSION['user_id'] = $user['unic_id'];
        $_SESSION['workpoint_id'] = $user['unic_id'];
        $_SESSION['workpoint_name'] = $user['name_of_the_place'];
        $_SESSION['organisation_id'] = $user['organisation_id'];
        $_SESSION['login_attempts'] = 0;
        
        // Redirect supervisors directly to booking supervisor view with their workpoint ID
        header("Location: booking_supervisor_view.php?working_point_user_id=" . $user['unic_id']);
        exit;
    }

    // Check specialists table
    $stmt = $pdo->prepare("SELECT * FROM specialists WHERE user = ? AND password = ?");
    $stmt->execute([$username, $password]);
    $user = $stmt->fetch();

    if ($user) {
        // Specialist found
        $_SESSION['user'] = $user['user'];
        $_SESSION['role'] = 'specialist_user';
        $_SESSION['user_id'] = $user['unic_id'];
        $_SESSION['specialist_id'] = $user['unic_id'];
        $_SESSION['specialist_name'] = $user['name'];
        $_SESSION['organisation_id'] = $user['organisation_id'];
        $_SESSION['login_attempts'] = 0;
        
        header("Location: booking_specialist_view.php?specialist_id=" . $user['unic_id']);
        exit;
    }

    // No match found
    $_SESSION['login_attempts']++;
    $_SESSION['last_attempt_time'] = time();
    $error = 'No such user/password match in the database. Please contact the web administrator.';
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar Booking System - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-container {
            min-height: calc(100vh - 80px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px;
            padding-top: 20px;
            margin-bottom: 80px;
            margin-top: -40px;
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 900px;
            width: 100%;
            display: flex;
            min-height: 500px;
        }
        
        .login-image {
            flex: 1.1;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .logo-img {
            width: 120px;
            height: 120px;
            margin-bottom: 1.5rem;
            border-radius: 50%;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .login-image::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="calendar" x="0" y="0" width="20" height="20" patternUnits="userSpaceOnUse"><rect width="18" height="18" x="1" y="1" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/><rect width="4" height="4" x="3" y="3" fill="rgba(255,255,255,0.1)"/><rect width="4" height="4" x="9" y="3" fill="rgba(255,255,255,0.1)"/><rect width="4" height="4" x="15" y="3" fill="rgba(255,255,255,0.1)"/><rect width="4" height="4" x="3" y="9" fill="rgba(255,255,255,0.1)"/><rect width="4" height="4" x="9" y="9" fill="rgba(255,255,255,0.1)"/><rect width="4" height="4" x="15" y="9" fill="rgba(255,255,255,0.1)"/><rect width="4" height="4" x="3" y="15" fill="rgba(255,255,255,0.1)"/><rect width="4" height="4" x="9" y="15" fill="rgba(255,255,255,0.1)"/><rect width="4" height="4" x="15" y="15" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23calendar)"/></svg>');
            opacity: 0.3;
        }
        
        .login-image-content {
            text-align: center;
            color: white;
            z-index: 1;
            position: relative;
        }
        
        .login-image-content i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.9;
        }
        
        .login-image-content h2 {
            font-size: 2rem;
            font-weight: 300;
            margin-bottom: 0.5rem;
        }
        
        .login-image-content p {
            font-size: 1.1rem;
            opacity: 0.9;
            font-weight: 300;
        }
        
        .version-badge-image {
            margin-top: 0.25rem;
            padding: 0.1rem 0.3rem;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            display: inline-block;
            border: 1px solid rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(10px);
        }
        
        .version-badge-image small {
            color: white !important;
            font-weight: 400;
            font-size: 0.7rem;
            opacity: 0.85;
        }
        
        .login-form {
            flex: 0.9;
            padding: 2.5rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 1.5rem;
            position: relative;
        }
        
        .login-header h1 {
            color: #333;
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .login-header p {
            color: #666;
            font-size: 1rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            color: #555;
            font-weight: 500;
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .form-control {
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            background: white;
        }
        
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 24px;
            font-size: 1.1rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 1rem;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .alert-danger {
            background: #fee;
            color: #c33;
            border-left: 4px solid #c33;
        }
        
        .alert-success {
            background: #efe;
            color: #363;
            border-left: 4px solid #363;
        }
        
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            text-align: center;
            padding: 1rem;
            font-size: 0.9rem;
        }
        
        .footer a {
            color: #667eea;
            text-decoration: none;
        }
        
        .footer a:hover {
            text-decoration: underline;
        }
        
        
        @media (max-width: 768px) {
            .login-card {
                flex-direction: column;
                max-width: 400px;
            }
            
            .login-image {
                min-height: 200px;
            }
            
            .login-form {
                padding: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-image">
                <div class="login-image-content">
                    <img src="logo/my-bookings_logo_small_white.png" alt="My Bookings Logo" class="logo-img">
                    <h2>Calendar Booking System</h2>
                    <p>Professional appointment management</p>
                    <div class="version-badge-image">
                        <small>
                            <?= APP_VERSION_DISPLAY ?>
                        </small>
                    </div>
                </div>
            </div>
            
            <div class="login-form">
                <div class="login-header">
                    <h1>Welcome Back</h1>
                    <p>Sign in to access your dashboard</p>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required 
                               placeholder="Enter your username" autocomplete="username">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required 
                               placeholder="Enter your password" autocomplete="current-password">
                    </div>
                    
                    <button type="submit" class="btn btn-login">
                        <i class="fas fa-sign-in-alt"></i>
                        Sign In
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <div>
                        &copy; <?= date('Y') ?> Calendar Booking System. All rights reserved. | <a href="privacy_policy.php">Privacy Policy</a>
                    </div>
                    <div>
                        Contact: admin@my-bookings.co.uk | 
                        Phone: <a href="tel:+447504128961">+44 7504 128961</a>
                    </div>
                </div>
            </div>
        </div>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
