<?php
    session_start();
    include_once("connection.php");

    // Checks if the data is sent via post
    if ($_SERVER["REQUEST_METHOD"]=="POST"){

        // Binds data
        $userName=$_POST["userName"];
        $password=$_POST["passwd"];

        // Find locate the username
        $stmt=$conn->prepare("SELECT * FROM tbluser WHERE userName=:userName LIMIT 1");
        $stmt->bindParam(":userName",$userName);
        $stmt->execute();

        // Fetch the user row if a match is found
        $user=$stmt->fetch(PDO::FETCH_ASSOC);

        // Compares the password entered and the hashed password stored in the DB by using password_verify
        if ($user && password_verify($password, $user["passwd"])){
            // Uses the session superglobal to store data about if the user is logged in, which can be accessed on other pages
            $_SESSION["logged_in"]=true;
            $_SESSION["userName"]=$user["userName"];
            $_SESSION["role"] = $user["role"];

            header("Location: index.php");
            exit;
        }
        else{
            $login_error = "Invalid details";
        }
    }
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

    <?php
        if (isset($_GET['loggedout'])) {
            echo '<div class="success-message">You have been successfully logged out.</div>';
        }

        if (!empty($login_error)) 
            echo "<div class='failed-message'>$login_error</div>";
    
    ?>

            <h2>Login</h2>

            <form action="login.php" method="post">
                <input type="text" name="userName" placeholder="Username" required autocomplete="username">
                <input type="password" name="passwd" placeholder="Password" required autocomplete="current-password">
                <button type="submit">Login</button>
            </form>

            <a class="link-secondary" href="forgot_password.php">Forgot password?</a>
            <div class="extra-section">
                A Swim Team member and don't have an account?
                <br>
                <a href="swimmerSignup.php">Sign up as a swimmer</a>
            </div>
        
        </div>
    </div>

</body>
</html>