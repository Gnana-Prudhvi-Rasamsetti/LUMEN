<?php
session_start();

// If already logged in, redirect to home
if (isset($_SESSION['user_id'])) {
    header("Location: lumen_home.php");
    exit();
}

// Initialize error message
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    include 'db_connect.php';
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Validate LUMEN email
    if (!preg_match("/^[a-zA-Z0-9._%+-]+@lumen\.edu\.in$/", $email)) {
        $error = "Only valid LUMEN email addresses are allowed.";
    } else {
        // Use prepared statement to prevent SQL injection
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if ($password === $user['password']) { // In production, use password_verify()
                // After successful login, set these session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['profile_photo'] = $user['profile_photo'] ?? 'uploads/default.png';
                $_SESSION['email'] = $user['email'];
                $_SESSION['profile_photo'] = $user['profile_photo'];
                $_SESSION['role'] = $user['role'];

                header("Location: lumen_home.php");
                exit();
            } else {
                $error = "Invalid email or password.";
            }
        } else {
            $error = "User not found.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LUMEN - Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-primary: #FDFDFD;
            --bg-secondary: #F4F4F5;
            --accent-primary: #D6174B;
            --accent-secondary: #5EC4A3;
            --text-primary: #1E1E1E;
            --text-secondary: #6B7280;
            --highlight: #FFF1F4;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.08);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 15px 30px rgba(0, 0, 0, 0.12);
            --border-radius: 12px;
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        @font-face {
            font-family: 'Inter';
            font-style: normal;
            font-weight: 400;
            src: url(https://fonts.gstatic.com/s/inter/v12/UcCO3FwrK3iLTeHuS_fvQtMwCp50KnMw2boKoduKmMEVuLyfAZ9hiA.woff2) format('woff2');
        }

        body {
            background-color: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
        }

        /* Login Container */
        .login-container {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        /* Logo Section */
        .logo-section {
            flex: 1;
            background: linear-gradient(135deg, var(--accent-primary), #e83a6d);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            color: white;
        }

        .logo-img {
            width: 280px;
            margin-bottom: 1.5rem;
        }

        .logo-subtitle {
            font-size: 1.1rem;
            text-align: center;
            max-width: 400px;
            margin-top: 1rem;
            opacity: 0.9;
        }

        .welcome-text {
            font-size: 4rem;
            font-weight: 600;
            margin-bottom: 0rem;
            text-align: center;
        }

        /* Login Form Section */
        .form-section {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .login-form {
            width: 100%;
            max-width: 400px;
        }

        .form-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 2rem;
            color: var(--text-primary);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-primary);
        }

        .form-input {
            width: 100%;
            padding: 0.9rem 1.25rem;
            border-radius: var(--border-radius);
            border: 1px solid rgba(0, 0, 0, 0.1);
            font-size: 1rem;
            transition: var(--transition);
            background-color: white;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 3px rgba(214, 23, 75, 0.1);
        }

        .btn {
            padding: 0.9rem 1.75rem;
            border-radius: var(--border-radius);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            width: 100%;
        }

        .btn-primary {
            background: linear-gradient(90deg, var(--accent-primary), #e83a6d);
            color: white;
            box-shadow: 0 2px 8px rgba(214, 23, 75, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(214, 23, 75, 0.4);
        }



        /* Responsive Styles */
        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
            }

            .logo-section {
                padding: 2rem 1rem;
                text-align: center;
            }

            .logo-img {
                width: 200px;
            }

            .welcome-text {
                font-size: 1.5rem;
            }

            .form-section {
                padding: 2rem 1.5rem;
            }

            .form-title {
                font-size: 1.5rem;
                margin-bottom: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Logo Section -->
        <div class="logo-section">
        <h1 class="welcome-text">Welcome to LUMEN</h1>

            <img src="logo lumen.png" alt="LUMEN Logo" class="logo-img">
            <p class="logo-subtitle">Learning Uplift for Modern Educational Networks</p>
        </div>

        <!-- Login Form Section -->
        <div class="form-section">
            <div class="login-form">
                <h1 class="form-title">Sign In</h1>
                
                <?php if (!empty($error)): ?>
                    <div style="color: var(--accent-primary); margin-bottom: 1rem; padding: 0.75rem; background: var(--highlight); border-radius: var(--border-radius); text-align: center;">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form action="login.php" method="POST" id="loginForm">

                    <div class="form-group">
                        <label for="email" class="form-label">Email</label>
                        <input 
                            type="email" 
                            id="email" 
                            class="form-input" 
                            placeholder="example@lumen.edu.in" 
                            required
                            name="email"
                            pattern="[a-zA-Z0-9._%+-]+@lumen\.edu\.in"
                            title="Please enter a valid LUMEN email address (example@lumen.edu.in)"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password"
                            class="form-input" 
                            placeholder="Enter your password" 
                            required
                            minlength="8"
                        >
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Sign In</button>
                </form>
            </div>
        </div>
    </div>

    <script>
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;
        
        if (!email.endsWith('@lumen.edu.in')) {
            e.preventDefault();
            alert('Please use a valid LUMEN email address (@lumen.edu.in)');
            return;
        }
        
        if (password.length < 8) {
            e.preventDefault();
            alert('Password must be at least 8 characters long');
            return;
        }
    });
    </script>
</body>
</html>