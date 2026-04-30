<?php
session_start();
include_once('connection.php');
include_once('auth.php');
enforceSessionPolicies($conn); // Apply shared session rules before processing login

$login_error = "";

// Handle login submission (only run authentication logic on POST)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Read and normalize submitted credentials
    $userName = trim($_POST["userName"] ?? '');
    $password = $_POST["passwd"] ?? '';

    try {
        // Fetch the user row for this username (limit to 1 to avoid ambiguity and extra data reads)
        $stmt = $conn->prepare("SELECT * FROM tbluser WHERE userName = :userName LIMIT 1");
        $stmt->bindParam(":userName", $userName, PDO::PARAM_STR);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Check the submitted password against the stored hash and, if valid, initialize the session
        if ($user && password_verify($password, $user["passwd"])) {
            session_regenerate_id(true); // Regenerate session ID to reduce session fixation risk

            // Store login state + core identity fields used throughout the site
            $_SESSION["logged_in"] = true;
            $_SESSION["userID"]   = (int)$user["userID"];   // Used by auth.php / role checks
            $_SESSION["userName"] = $user["userName"];      // Used as the logged-in identity
            $_SESSION["role"]     = (int)$user["role"];     // Used for authorization checks

            // Redirect to homepage after successful login
            header("Location: index.php");
            exit;
        } else {
            $login_error = "Invalid details"; // Generic error to avoid revealing which field was wrong
        }
    } catch (PDOException $e) {
        $login_error = "Database error. Please try again."; // User-facing DB failure message
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
<?php include 'navbar.php'; ?> <!-- Shared site navigation -->

<div class="main-content">
    <div class="form-section">

        <!-- Optional banner shown when redirected here after logout -->
        <?php if (isset($_GET['loggedout'])): ?>
            <div class="alert-success">You have been successfully logged out.</div>
        <?php endif; ?>

        <!-- Display the current login error (if any) -->
        <?php if (!empty($login_error)): ?>
            <div class="alert-fail"><?= htmlspecialchars($login_error) ?></div>
        <?php endif; ?>

        <h2>Login</h2>

        <!-- Login form posts back to login.php and uses browser autocomplete hints for username/password -->
        <form action="login.php" method="post" autocomplete="off">
            <input type="text" name="userName" placeholder="Username" required autocomplete="username">
            <input type="password" name="passwd" placeholder="Password" required autocomplete="current-password">
            <button type="submit">Login</button>
        </form>

        <!-- "Forgot password" points to tools/help page rather than an automated reset flow -->
        <a class="link-secondary" href="tool.php">Forgot password?</a>

        <!-- Secondary CTA for new swimmers without accounts -->
        <div class="extra-section">
            A Swim Team member and don't have an account?
            <br>
            <a href="swimmerSignup.php">Sign up as a swimmer</a>
        </div>

    </div>
</div>
</body>
</html>