<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <title>Oundle School Swim Team - Login</title>
    <meta name="viewport" content="width=1024, initial-scale=1">
    <link rel="stylesheet" href="style.css">

    <style>
        .login-section {
            max-width: 370px;
            margin: 60px auto 0 auto;
            padding: 2rem 2.2rem 1.5rem 2.2rem;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 0 28px rgba(0,0,0,0.10);
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .login-section h2 {
            margin-bottom: 1.2rem;
            color: #002f63;
            font-family: 'Arial Rounded MT Bold', Arial, sans-serif;
        }
        .login-section form {
            width: 100%;
        }
        .login-section input[type="text"],
        .login-section input[type="password"] {
            width: 100%;
            padding: 0.65rem 0.8rem;
            border: 1px solid #bbb;
            border-radius: 6px;
            margin-bottom: 1rem;
            font-size: 1rem;
            font-family: inherit;
            background: #f4f7fa;
        }
        .login-section button[type="submit"] {
            width: 100%;
            padding: 0.65rem;
            background: #004F8B;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            cursor: pointer;
            margin-bottom: 0.5rem;
            font-family: 'Arial Rounded MT Bold', Arial, sans-serif;
            transition: background 0.2s;
        }
        .login-section button[type="submit"]:hover {
            background: #00335d;
        }
        .login-section .forgot-password {
            color: #0074d9;
            text-decoration: none;
            font-size: 0.97rem;
            display: block;
            margin-bottom: 1.4rem;
            text-align: right;
            transition: text-decoration 0.2s;
        }
        .login-section .forgot-password:hover {
            text-decoration: underline;
        }
        .login-section .signup-section {
            margin-top: 1.2rem;
            font-size: 0.97rem;
            text-align: center;
        }
        .login-section .signup-section a {
            color: #0074d9;
            text-decoration: none;
            font-weight: 500;
        }
        .login-section .signup-section a:hover {
            text-decoration: underline;
        }
        /* Responsive max-width for mobile */
        @media (max-width: 600px) {
            .login-section {
                padding: 1.1rem 0.5rem;
                max-width: 98vw;
            }
        }
        /* Adjust for navbar fixed height */
        body {
            padding-top: 100px;
        }
    </style>
</head>

<body>

    <?php include 'navbar.php';?>

    <div class="main-content">
        <div class="login-section">
            <h2>Login</h2>
            <form action="login.php" method="post">
                <input type="text" name="username" placeholder="Username" required autocomplete="username">
                <input type="password" name="password" placeholder="Password" required autocomplete="current-password">
                <button type="submit">Login</button>
            </form>
            <a class="forgot-password" href="forgot_password.php">Forgot password?</a>
            <div class="signup-section">
                Don't have an account?
                <a href="signup.php">Sign up</a>
            </div>
        </div>
    </div>
    
</body>
</html>