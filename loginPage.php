<?php
session_start(); 
// Check for a login error message set by auth_process.php
$error_message = null;
if (isset($_SESSION['login_error'])) {
    $error_message = $_SESSION['login_error'];
    unset($_SESSION['login_error']); // Clear the error after displaying
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | The Bat Cave</title>
    
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Montserrat:wght@600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <style>
        /* ... (Keep all your existing CSS here) ... */
        :root {
            --gold-accent: #D4AF37;
            --gold-hover: #E8C76A;
            --bg-dark: #070606;
            --text-main: #F6EDD9;
            --input-bg: rgba(255, 255, 255, 0.05);
            --border-color: rgba(255, 255, 255, 0.1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }

        body {
            height: 100vh;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--bg-dark);
            background-image: url('../media/bg-image.png');
            background-size: cover;
            background-position: center;
            position: relative;
            overflow: hidden;
        }

        /* Dark Overlay */
        body::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(7, 6, 6, 0.85);
            backdrop-filter: blur(8px);
            z-index: 1;
        }

        /* Login Card */
        .login-card {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 420px;
            padding: 40px;
            background: rgba(20, 20, 20, 0.6);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            backdrop-filter: blur(20px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            animation: floatIn 0.8s ease-out;
        }

        @keyframes floatIn {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .brand-header {
            text-align: center;
            margin-bottom: 35px;
        }

        .brand-logo {
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
            font-size: 2rem;
            color: var(--gold-accent);
            letter-spacing: -1px;
            margin-bottom: 10px;
            text-shadow: 0 0 20px rgba(212, 175, 55, 0.3);
        }

        .brand-subtitle {
            color: #888;
            font-size: 0.9rem;
            font-weight: 500;
        }

        /* Form Elements */
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-main);
            font-size: 0.85rem;
            font-weight: 500;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            transition: 0.3s;
        }

        .form-control {
            width: 100%;
            padding: 14px 14px 14px 45px;
            background: var(--input-bg);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            color: #fff;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--gold-accent);
            background: rgba(255, 255, 255, 0.08);
            box-shadow: 0 0 0 4px rgba(212, 175, 55, 0.1);
        }

        .form-control:focus + i { color: var(--gold-accent); }

        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            font-size: 0.85rem;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #aaa;
            cursor: pointer;
        }

        .forgot-pass {
            color: var(--gold-accent);
            text-decoration: none;
            transition: 0.2s;
        }
        .forgot-pass:hover { text-decoration: underline; color: var(--gold-hover); }

        .btn-login {
            width: 100%;
            padding: 14px;
            background: var(--gold-accent);
            color: #000;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
        }

        .btn-login:hover {
            background: var(--gold-hover);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(212, 175, 55, 0.2);
        }

        .footer-text {
            text-align: center;
            margin-top: 25px;
            color: #666;
            font-size: 0.8rem;
        }
        
        /* POPUP/ERROR STYLE */
        .error-message {
            background: #ef4444; 
            color: white; 
            padding: 12px; 
            border-radius: 8px; 
            margin-bottom: 20px; 
            text-align: center;
            font-weight: 600;
            animation: bounceIn 0.5s;
        }
        @keyframes bounceIn {
            0% { opacity: 0; transform: scale(0.8); }
            100% { opacity: 1; transform: scale(1); }
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="brand-header">
            <div class="brand-logo"><i class="fas fa-mug-hot"></i> BAT CAVE</div>
            <p class="brand-subtitle">Admin Command Center</p>
        </div>
        
        <?php if ($error_message): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <form action="auth_process.php" method="POST">
            <div class="form-group">
                <label>Username</label>
                <div class="input-wrapper">
                    <input type="text" name="username" class="form-control" placeholder="admin" required>
                    <i class="fas fa-user"></i>
                </div>
            </div>

            <div class="form-group">
                <label>Password</label>
                <div class="input-wrapper">
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                    <i class="fas fa-lock"></i>
                </div>
            </div>

            <div class="form-options">
                <label class="remember-me">
                    <input type="checkbox"> Remember me
                </label>
                <a href="#" class="forgot-pass">Forgot Password?</a>
            </div>

            <button type="submit" class="btn-login">
                Sign In <i class="fas fa-arrow-right"></i>
            </button>
        </form>

        <div class="footer-text">
            Restricted Access. Authorized Personnel Only.<br>
            &copy; 2025 The Malvar Bat Cave.
        </div>
    </div>
    </body>
</html>