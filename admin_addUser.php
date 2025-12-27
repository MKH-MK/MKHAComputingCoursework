<?php
session_start();
include_once('connection.php');
include_once('auth.php');
enforceSessionPolicies($conn);

if (!isset($_SESSION['role']) || $_SESSION['role'] != 2) {
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

$registration_success = false;
$error_message = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        
        $_POST = array_map("htmlspecialchars", $_POST);

        $email = trim($_POST["email"] ?? '');
        $passwd = $_POST["passwd"] ?? '';
        $confirm = $_POST["confirm_passwd"] ?? '';
        $description = $_POST["description"] ?? '';
        $role = isset($_POST["role"]) ? (int)$_POST["role"] : 1; // default to Student if missing

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format.");
        }
        if (substr($email, -20) !== "@oundleschool.org.uk") {
            throw new Exception("Email must be a valid school email address");
        }
        if (substr_count($email, '@') !== 1) {
            throw new Exception("Invalid email format");
        }
        if ($passwd !== $confirm) {
            throw new Exception("Passwords do not match.");
        }
        if (strlen($description) > 400) {
            throw new Exception("Description cannot exceed 400 characters.");
        }

        $userName = substr($email, 0, strpos($email, '@'));
        $hashed_password = password_hash($passwd, PASSWORD_DEFAULT);

        // Uniqueness check (keep this to avoid duplicate accounts)
        $email_check = $conn->prepare("SELECT COUNT(*) FROM tbluser WHERE emailAddress = :email");
        $email_check->bindParam(':email', $email);
        $email_check->execute();
        if ($email_check->fetchColumn() > 0) {
            throw new Exception("An account already exists with this email.");
        }

        // Insert user (rely on HTML constraints for required fields and ranges)
        $stmt = $conn->prepare("INSERT INTO tbluser 
            (userID, passwd, role, surname, forename, yearg, emailAddress, userName, gender, description)
            VALUES (null, :passwd, :role, :surname, :forename, :yearg, :emailAddress, :userName, :gender, :description)");

        $stmt->bindParam(':passwd', $hashed_password);
        $stmt->bindParam(':role', $role, PDO::PARAM_INT);
        $stmt->bindParam(':surname', $_POST["surname"]);
        $stmt->bindParam(':forename', $_POST["forename"]);
        $stmt->bindParam(':yearg', $_POST["yearg"], PDO::PARAM_INT);
        $stmt->bindParam(':emailAddress', $email);
        $stmt->bindParam(':userName', $userName);
        $stmt->bindParam(':gender', $_POST["gender"]);
        $stmt->bindParam(':description', $description);

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