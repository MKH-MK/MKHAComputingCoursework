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

$registration_success = false;
$error_message = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        // Keep basic like swimmerSignup: escape POST values up-front, no trims, no CSRF tokens.
        $_POST = array_map("htmlspecialchars", $_POST);

        // Full set of validations using $_POST directly (no local assignments)
        if (($_POST["forename"] ?? '') === '' || ($_POST["surname"] ?? '') === '') {
            throw new Exception("Forename and surname are required.");
        }

        if (!filter_var($_POST["email"] ?? '', FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format.");
        }

        if (substr_count($_POST["email"], '@') !== 1) {
            throw new Exception("Invalid email format");
        }

        if (substr($_POST["email"], -20) !== "@oundleschool.org.uk") {
            throw new Exception("Email must be a valid school email address");
        }

        if (!in_array($_POST["gender"] ?? '', ['M','F','MIX'], true)) {
            throw new Exception("Please select a valid gender.");
        }

        if ((int)($_POST["yearg"] ?? 0) < 7 || (int)($_POST["yearg"] ?? 0) > 13) {
            throw new Exception("Year group must be between 7 and 13.");
        }

        if (!isset($_POST["role"]) || !in_array((int)$_POST["role"], [0,1,2], true)) {
            throw new Exception("Invalid role selected.");
        }

        if (($_POST["passwd"] ?? '') === '' || ($_POST["confirm_passwd"] ?? '') === '') {
            throw new Exception("Password and confirmation are required.");
        }

        if (($_POST["passwd"] ?? '') !== ($_POST["confirm_passwd"] ?? '')) {
            throw new Exception("Passwords do not match.");
        }

        if (strlen($_POST["description"] ?? '') > 400) {
            throw new Exception("Description cannot exceed 400 characters.");
        }

        $userName = substr($_POST["email"], 0, strpos($_POST["email"], '@'));
        $hashed_password = password_hash($_POST["passwd"], PASSWORD_DEFAULT);

        // Check if email already exists
        $email_check = $conn->prepare("SELECT COUNT(*) FROM tbluser WHERE emailAddress = :email");
        $email_check->bindParam(':email', $_POST["email"]);
        $email_check->execute();
        if ($email_check->fetchColumn() > 0) {
            throw new Exception("An account already exists with this email.");
        }

        // Insert user
        $stmt = $conn->prepare("INSERT INTO tbluser 
            (userID, passwd, role, surname, forename, yearg, emailAddress, userName, gender, description)
            VALUES (null, :passwd, :role, :surname, :forename, :yearg, :emailAddress, :userName, :gender, :description)");

        $stmt->bindParam(':passwd', $hashed_password);
        $stmt->bindParam(':role', $_POST["role"], PDO::PARAM_INT); 
        $stmt->bindParam(':surname', $_POST["surname"]);
        $stmt->bindParam(':forename', $_POST["forename"]);
        $stmt->bindParam(':yearg', $_POST["yearg"], PDO::PARAM_INT);  
        $stmt->bindParam(':emailAddress', $_POST["email"]);  
        $stmt->bindParam(':userName', $userName); 
        $stmt->bindParam(':gender', $_POST["gender"]);
        $stmt->bindParam(':description', $_POST["description"]);

        $stmt->execute();

        $registration_success = true;
    } catch (PDOException $e) {
        $error_message = "Database Error: " . $e->getMessage();
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
    $conn = null;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Oundle School Swim Team - Admin User Creation</title>
    <meta name="viewport" content="width=1024, initial-scale=1">
    <link rel="stylesheet" href="style.css">
</head>

<body>
<?php include 'navbar.php'; ?>

<div class="main-content">
    <div class="form-section">
        <h2>Add User</h2>

        <?php if (!empty($error_message)): ?>
            <div class="alert-fail">
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <?php if ($registration_success): ?>
            <div class="alert-success">
                User created successfully!
                <br>
                <a href="admin_userList.php">Go to User List</a>
            </div>
        <?php else: ?>
            <form action="admin_addUser.php" method="post" autocomplete="off">
                <div class="form-row">
                    <input type="text" name="forename" placeholder="Forename" required>
                    <input type="text" name="surname" placeholder="Surname" required>
                </div>

                <div class="form-row">
                    <input type="email" name="email" placeholder="School Email" required autocomplete="email">
                </div>

                <div class="form-row form-row--center">
                    <input type="number" name="yearg" class="input-small" placeholder="Year Group" min="7" max="13" required>
                    <select name="gender" class="input-small" required>
                        <option value="" disabled selected>Gender</option>
                        <option value="M">Male</option>
                        <option value="F">Female</option>
                        <option value="MIX">Other</option>
                    </select>
                    <select name="role" class="input-small" required>
                        <option value="" disabled selected>Role</option>
                        <option value="0">Guest</option>
                        <option value="1">Student</option>
                        <option value="2">Coach / Admin</option>
                    </select>
                </div>

                <input type="password" name="passwd" placeholder="Password" required autocomplete="new-password">
                <input type="password" name="confirm_passwd" placeholder="Confirm Password" required autocomplete="new-password">
                <input type="text" name="description" placeholder="Description (optional & 400 characters max)" maxlength="400">
                <button type="submit">Create User</button>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>