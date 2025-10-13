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

include_once("connection.php");
$creation_success = false;
$error_message = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        $_POST = array_map("htmlspecialchars", $_POST);

        $stmt = $conn->prepare("INSERT INTO tblmeet 
            (meetID, meetName, meetDate, meetInfo, external)
            VALUES (null, :meetName, :meetDate, :meetInfo, :external)");


        $stmt->bindParam(':meetName', $_POST["meetName"]);
        $stmt->bindParam(':meetDate', $_POST["meetDate"]);
        $stmt->bindParam(':meetInfo', $_POST["meetInfo"]);  
        $stmt->bindParam(':external', $_POST["external"]);  

        $stmt->execute();

        $creation_success = true;
    } catch (PDOException $e) {
        $error_message = "Database Error: " . $e->getMessage();
    } catch (Exception $e) {
        $error_message = "Validation Error: " . $e->getMessage();
    }
    $conn = null;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Oundle School Swim Team - Admin Meet Creation</title>
    <meta name="viewport" content="width=1024, initial-scale=1">
    <link rel="stylesheet" href="style.css">
</head>

<body>
<?php include 'navbar.php'; ?>

<div class="main-content">
    <div class="form-section">
        <h2>Meet creation</h2>

        <?php if (!empty($error_message)): ?>
            <div class="alert-fail">
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <?php if ($creation_success): ?>
            <div class="alert-success">
                Meet created successful! <a href="admin_manageMeets.php">Manage the meet here</a>
            </div>
        <?php else: ?>
            <form action="admin_addMeets.php" method="post" autocomplete="off">
                <div class="form-row">
                    <input type="text" name="meetName" placeholder="Name of Meet" required>
                </div>
                
                <h3>Date of Meet:</h3>

                <div class="form-row">
                    <input type="date" name="meetDate" required>
                </div>

                <h3>Is this meet in school:</h3>

                <div class="form-row">
                    <select name="external" class="input" required>

                        <option value="N">Yes</option>
                        <option value="Y">No</option>

                    </select>
                </div>

                <input type="text" name="meetInfo" placeholder="Meet description (400 characters max)" maxlength="400" required>
                <button type="submit">Create Meet</button>
            </form>
        
        <?php endif; ?>
    </div>
</div>
</body>
</html>