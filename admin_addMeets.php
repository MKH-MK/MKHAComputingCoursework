<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 2) {
    // Show error message and do not load the admin page content
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

include_once("connection.php");
$error_message = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        //
        $meetName = isset($_POST["meetName"]) ? trim($_POST["meetName"]) : '';
        $meetDate = isset($_POST["meetDate"]) ? trim($_POST["meetDate"]) : '';
        $meetInfo = isset($_POST["meetInfo"]) ? trim($_POST["meetInfo"]) : '';
        $external = isset($_POST["external"]) ? $_POST["external"] : 'N';
        $course   = isset($_POST["course"]) ? $_POST["course"] : 'L';

        if ($meetName === '' || $meetDate === '') {
            throw new Exception("Name and date are required.");
        }
        if (strlen($meetName) > 100) {
            throw new Exception("Meet name cannot exceed 100 characters.");
        }
        if (strlen($meetInfo) > 400) {
            throw new Exception("Description cannot exceed 400 characters.");
        }

        // Insert meet (store raw values; escape only when outputting)
        $stmt = $conn->prepare("INSERT INTO tblmeet 
            (meetID, meetName, meetDate, meetInfo, external, course)
            VALUES (null, :meetName, :meetDate, :meetInfo, :external, :course)");
        $stmt->bindParam(':meetName', $meetName);
        $stmt->bindParam(':meetDate', $meetDate);
        $stmt->bindParam(':meetInfo', $meetInfo);
        $stmt->bindParam(':external', $external);
        $stmt->bindParam(':course', $course);
        $stmt->execute();

        // Redirect: go straight into the editor view
        $newMeetId = $conn->lastInsertId();
        header("Location: admin_meetEditor.php?edit=" . urlencode($newMeetId));
        exit;

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

        <form action="admin_addMeets.php" method="post" autocomplete="off">
            <div class="form-row">
                <input type="text" name="meetName" placeholder="Name of Meet" required>
            </div>

            <h3>Course type:</h3>
            <div class="form-row">
                <select name="course" class="input" required>
                    <option value="L">Longcourse</option>
                    <option value="S">Shortcourse</option>
                </select>
            </div>

            <h3>Date of Meet:</h3>
            <div class="form-row">
                <input type="date" name="meetDate" required>
            </div>

            <h3>Is this meet for school?</h3>
            <div class="form-row">
                <select name="external" class="input" required>
                    <option value="N">Yes</option>
                    <option value="Y">No</option>
                </select>
            </div>

            <input type="text" name="meetInfo" placeholder="Meet description (400 characters max)" maxlength="400" required>
            <button type="submit">Create Meet</button>
        </form>
    </div>
</div>
</body>
</html>