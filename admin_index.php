<?php
session_start();
include_once('connection.php');
include_once('auth.php');

// Apply session policies (idle timeout + keep session role synced with DB)
enforceSessionPolicies($conn);

// Access control: this page is admin-only (role == 2)
if (!isset($_SESSION['role']) || $_SESSION['role'] != 2) {
    // Render an access-denied page and stop execution if the viewer is not an admin
    echo '<!DOCTYPE html>

<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Oundle School Swim Team - Denied Access</title>
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
    <title>Oundle School Swim Team - Admin Index</title>
    <meta name="viewport" content="width=1024, initial-scale=1">
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <?php include 'navbar.php'; ?> <!-- Shared site navigation -->

    <div class="main-content">
        <div class="page-title">
            Admin Section
        </div>

        <!-- Admin landing page: provides links to meet management and user management tools -->
        <div class="section">
            <h2>Admin Tools</h2>

            <p>Meet tools:</p>
            <ul>
                <li><a href="admin_addMeets.php">Add Meets</a></li>
                <li><a href="admin_meetList.php">Meets</a></li>
            </ul>

            <p>User tools:</p>
            <ul>
                <li><a href="admin_addUser.php">Add Users</a></li>
                <li><a href="admin_userList.php">Users List</a></li>
                <li><a href="admin_rollOverUser.php">Roll Over Users</a></li>
            </ul>
        </div>
    </div>
</body>
</html>