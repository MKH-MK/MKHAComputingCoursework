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
            (meetID, meetName, meetDate, meetInfo, external, course)
            VALUES (null, :meetName, :meetDate, :meetInfo, :external, :course)");


        $stmt->bindParam(':meetName', $_POST["meetName"]);
        $stmt->bindParam(':meetDate', $_POST["meetDate"]);
        $stmt->bindParam(':meetInfo', $_POST["meetInfo"]);  
        $stmt->bindParam(':external', $_POST["external"]);
        $stmt->bindParam(':course', $_POST["course"]);  

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