<?php
session_start();
include_once('connection.php');
include_once('auth.php');
enforceSessionPolicies($conn);

$login_error = "";

// Handle login submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $userName = trim($_POST["userName"] ?? '');
    $password = $_POST["passwd"] ?? '';

    try {
        // Look up the user by username
        $stmt = $conn->prepare("SELECT * FROM tbluser WHERE userName = :userName LIMIT 1");
        $stmt->bindParam(":userName", $userName, PDO::PARAM_STR);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verify password and initialize session
        if ($user && password_verify($password, $user["passwd"])) {
            // Prevent session fixation
            session_regenerate_id(true);

            $_SESSION["logged_in"] = true;
            $_SESSION["userID"]   = (int)$user["userID"];   // needed for auth.php role sync
            $_SESSION["userName"] = $user["userName"];
            $_SESSION["role"]     = (int)$user["role"];

            header("Location: index.php");
            exit;
        } else {
            $login_error = "Invalid details";
        }
    } catch (PDOException $e) {
        $login_error = "Database error. Please try again.";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Oundle School Swim Team - Login</title>
    <meta name="viewport" content="width=1024, initial-scale=1">
    <link rel="stylesheet" href="style.css">
</head>

<body>
<?php include 'navbar.php'; ?>

<div class="main-content">
    <div class="form-section">

        <?php if (isset($_GET['loggedout'])): ?>
            <div class="alert-success">You have been successfully logged out.</div>
        <?php endif; ?>

        <?php if (!empty($login_error)): ?>
            <div class="alert-fail"><?= htmlspecialchars($login_error) ?></div>
        <?php endif; ?>

        <h2>Login</h2>

        <form action="login.php" method="post" autocomplete="off">
            <input type="text" name="userName" placeholder="Username" required autocomplete="username">
            <input type="password" name="passwd" placeholder="Password" required autocomplete="current-password">
            <button type="submit">Login</button>
        </form>

        <a class="link-secondary" href="contact.php">Forgot password?</a>
        <div class="extra-section">
            A Swim Team member and don't have an account?
            <br>
            <a href="swimmerSignup.php">Sign up as a swimmer</a>
        </div>

    </div>
</div>
</body>
</html>