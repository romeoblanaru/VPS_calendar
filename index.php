<?php
require_once __DIR__ . '/includes/lang_loader.php';
require_once __DIR__ . '/config/version.php';

// If already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user'])) {
    if ($_SESSION['role'] == 'admin_user') {
        header("Location: admin/admin_dashboard.php");
    } elseif ($_SESSION['role'] == 'specialist_user') {
        header("Location: booking_view_page.php?specialist_id=" . $_SESSION['specialist_id']);
    } elseif ($_SESSION['role'] == 'organisation_user') {
        header("Location: organisation_dashboard.php");
    } elseif ($_SESSION['role'] == 'workpoint_user') {
        header("Location: workpoint_supervisor_dashboard.php");
    } else {
        header("Location: booking_view_page.php");
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar Booking System - Welcome</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
        }
        
        .hero-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
        }
        
        .hero-content {
            text-align: center;
            max-width: 900px;
            margin: 0 auto;
            position: relative;
            z-index: 2;
        }
        
        .logo-container {
            margin-bottom: 3rem;
            animation: fadeInDown 1s ease-out;
        }
        
        .logo-main {
            width: 160px;
            height: 160px;
            border-radius: 50%;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            background: white;
            padding: 5px;
        }
        
        .hero-title {
            color: white;
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 2rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
            animation: fadeInUp 1s ease-out 0.3s backwards;
        }
        
        .hero-description {
            color: rgba(255,255,255,0.95);
            font-size: 1.25rem;
            line-height: 1.8;
            margin-bottom: 3rem;
            padding: 0 20px;
            animation: fadeInUp 1s ease-out 0.6s backwards;
            font-weight: 300;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
        }
        
        .enter-button {
            background: rgba(255,255,255,0.95);
            color: #667eea;
            border: none;
            padding: 18px 60px;
            font-size: 1.3rem;
            font-weight: 600;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s ease;
            animation: fadeInUp 1s ease-out 0.9s backwards;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .enter-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.3);
            background: white;
        }
        
        .login-form-container {
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            width: 100%;
            max-width: 450px;
            margin-bottom: 1rem;
            opacity: 0;
            visibility: hidden;
            transition: all 0.5s ease;
        }
        
        .login-form-container.show {
            opacity: 1;
            visibility: visible;
        }
        
        .login-form {
            background: rgba(255,255,255,0.95);
            padding: 1.5rem;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            max-width: 450px;
            margin: 0 auto;
        }
        
        .form-control {
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 1.1rem;
            margin-bottom: 1rem;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 10px;
            width: 100%;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        /* Background animation */
        .bg-shapes {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 1;
        }
        
        .shape {
            position: absolute;
            color: rgba(255, 255, 255, 0.1);
            font-size: 4rem;
        }
        
        .shape-1 {
            bottom: 15%;
            right: 10%;
            font-size: 5rem;
            animation: float 20s infinite ease-in-out;
        }
        
        .shape-2 {
            top: 60%;
            right: 15%;
            font-size: 4.5rem;
            animation: float 15s infinite ease-in-out reverse;
        }
        
        .shape-3 {
            bottom: 20%;
            left: 20%;
            font-size: 4rem;
            animation: float 25s infinite ease-in-out;
        }
        
        .shape-4 {
            top: 40%;
            right: 35%;
            font-size: 3.5rem;
            animation: float 18s infinite ease-in-out reverse;
        }
        
        .shape-5 {
            top: 15%;
            left: 10%;
            font-size: 4.2rem;
            animation: float 22s infinite ease-in-out;
        }
        
        .shape-6 {
            top: 20%;
            left: 40%;
            font-size: 3.8rem;
            animation: float 17s infinite ease-in-out reverse;
        }
        
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes float {
            0%, 100% {
                transform: translateY(0) rotate(0deg);
            }
            25% {
                transform: translateY(-20px) rotate(5deg);
            }
            50% {
                transform: translateY(0) rotate(0deg);
            }
            75% {
                transform: translateY(20px) rotate(-5deg);
            }
        }
        
        .alert {
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .alert-danger {
            background: #fee;
            color: #c33;
            border: none;
        }
        
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2rem;
            }
            .hero-description {
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
    <div class="hero-container">
        <div class="bg-shapes">
            <div class="shape shape-1"><i class="fas fa-calendar-check"></i></div>
            <div class="shape shape-2"><i class="fas fa-phone-volume"></i></div>
            <div class="shape shape-3"><i class="fas fa-clipboard-list"></i></div>
            <div class="shape shape-4"><i class="fas fa-user-clock"></i></div>
            <div class="shape shape-5"><i class="fas fa-comments"></i></div>
            <div class="shape shape-6"><i class="fas fa-pencil-alt"></i></div>
        </div>
        
        <div class="hero-content">
            <div class="logo-container">
                <img src="logo/my-bookings_logo_small_white.png" alt="My Bookings" class="logo-main">
            </div>
            
            <h1 class="hero-title">Calendar Booking System</h1>
            
            <p class="hero-description">
                Providing multiple ways of receiving appointments for your business (voice, SMS, Messenger, WhatsApp, web-interface and even custom webhooks integrated into your personal webpage), managed by a unique AI brain, giving the end user an easy and pleasant experience while providing businesses with a professional tool to manage their appointments across multiple workpoints or with multiple specialists, like in beauty salon cases, all based on a visual, easy-to-use web-based interface connected to powerful applications.
            </p>
            
            <div style="position: relative; display: inline-block;">
                <div class="login-form-container" id="loginForm">
                <div class="login-form">
                    <form action="login.php" method="POST" onsubmit="return handleLogin(event)">
                        <div id="errorContainer"></div>
                        <input type="text" 
                               name="username" 
                               class="form-control" 
                               placeholder="Username" 
                               required 
                               autocomplete="username">
                        <input type="password" 
                               name="password" 
                               class="form-control" 
                               placeholder="Password" 
                               required 
                               autocomplete="current-password">
                        <small class="text-muted mt-2 d-block text-center" style="font-size: 0.85rem;">
                            Press Enter to login
                        </small>
                    </form>
                </div>
            </div>
                <button class="enter-button" onclick="toggleLogin()">
                    <i class="fas fa-sign-in-alt"></i> Enter
                </button>
            </div>
        </div>
    </div>
    
    <footer style="position: fixed; bottom: 0; left: 0; right: 0; background: rgba(0, 0, 0, 0.8); color: white; text-align: center; padding: 1rem; font-size: 0.9rem; z-index: 10;">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <div>
                        &copy; <?= date('Y') ?> Calendar Booking System. All rights reserved. | <a href="privacy_policy.php" style="color: #667eea; text-decoration: none;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">Privacy Policy</a>
                    </div>
                    <div>
                        Contact: admin@my-bookings.co.uk | 
                        Phone: <a href="tel:+447504128961" style="color: #667eea; text-decoration: none;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">+44 7504 128961</a>
                    </div>
                </div>
            </div>
        </div>
    </footer>
    
    <script>
        function toggleLogin() {
            const loginForm = document.getElementById('loginForm');
            const enterButton = document.querySelector('.enter-button');
            
            if (loginForm.classList.contains('show')) {
                loginForm.classList.remove('show');
                enterButton.innerHTML = '<i class="fas fa-sign-in-alt"></i> Enter';
            } else {
                loginForm.classList.add('show');
                enterButton.innerHTML = '<i class="fas fa-times"></i> Close';
                // Focus on username field
                setTimeout(() => {
                    document.querySelector('input[name="username"]').focus();
                }, 300);
            }
        }
        
        function handleLogin(event) {
            // The form will submit normally to login.php
            return true;
        }
        
        // Allow Enter key to submit when form is visible
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && document.getElementById('loginForm').classList.contains('show')) {
                const form = document.querySelector('.login-form form');
                if (form.checkValidity()) {
                    form.submit();
                }
            }
        });
    </script>
</body>
</html>