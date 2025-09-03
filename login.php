<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Oundle School Swim Team - Login</title>
    <meta name="viewport" content="width=1024, initial-scale=1">
    <link rel="stylesheet" href="loginStyle.css">
</head>

<body>

    <?php include 'navbar.php';?>

    <div class="main-content">
        <div class="form-section">
            <h2>Login</h2>
            <form action="login.php" method="post">
                <input type="text" name="username" placeholder="Username" required autocomplete="username">
                <input type="password" name="password" placeholder="Password" required autocomplete="current-password">
                <button type="submit">Login</button>
            </form>
            <a class="link-secondary" href="forgot_password.php">Forgot password?</a>
            <div class="extra-section">
                A Swim Team member and don't have an account?
                <br>
                <a href="swimmerSignup.php">Sign up as a swimmer</a>
                <br>
                <br>
                Staff members are kindly asked to contact the Head of Swimming to be registered
            </div>
        </div>
    </div>

</body>
</html>