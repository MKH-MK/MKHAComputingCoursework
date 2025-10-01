<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 2) {
    // Show error message and do not load the admin page content
    echo '<!DOCTYPE html>

<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Section - Access Denied</title>
    <meta name="viewport" content="width=1024, initial-scale=1">
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="main-content">
        <div class="page-title">
            Access Denied
        </div>
        <div class="section">
            <div class="alert-fail">
                Permision Error: You do not have the right privilege to view this page.
            </div>
            <h2>Further options:</h2>
            <ul>
                <li>If you think this is an error, please <a href="contact.php">contact the administrator</a>.
                <li><a href="login.php">Login</a></li>
                <li><a href="index.php">Return to Home</a></li>
            </ul>
        </div>
    </div>
</body>

</html>';
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Admin Section - Oundle School Swim Team</title>
    <meta name="viewport" content="width=1024, initial-scale=1">
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <?php include 'navbar.php'; ?>
    <div class="main-content">
        <div class="page-title">
            Admin Section
        </div>
        <div class="section">
            <h2>Admin Tools</h2>
            <ul>
                <li><a href="admin_meets.php">Manage Meets</a></li>
                <li><a href="admin_events.php">Manage Events</a></li>
                <li><a href="admin_swimmers.php">Manage Swimmers</a></li>
                <li><a href="admin_results.php">Enter Results</a></li>
            </ul>
        </div>
        <div class="section">
            <h2>Quick Links</h2>
            <ul>
                <li><a href="index.php">Back to Home</a></li>
            </ul>
        </div>
    </div>
</body>
</html>