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

<?php
include_once("connection.php");
$registration_success = false;
$error_message = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        $_POST = array_map("htmlspecialchars", $_POST);

        // Validate email
        $email = trim($_POST["email"]);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format.");
        }

        if (substr($email, -20) !== "@oundleschool.org.uk") {
            throw new Exception("Email must be a valid school email address");
        }

        if (substr_count($email, '@') !== 1) {
            throw new Exception("Invalid email format");
        }

        // Validate passwords
        if ($_POST["passwd"] !== $_POST["confirm_passwd"]) {
            throw new Exception("Passwords do not match.");
        }
        
        $description = isset($_POST["description"]) ? $_POST["description"] : "";
        if (strlen($description) > 400) {
            throw new Exception("Description cannot exceed 400 characters.");
        }

        $role = 1;

        $userName = substr($email, 0, strpos($email, '@'));
        $hashed_password = password_hash($_POST["passwd"], PASSWORD_DEFAULT);

        // Check if email already exists
        $email_check = $conn->prepare("SELECT COUNT(*) FROM tbluser WHERE emailAddress = :email");
        $email_check->bindParam(':email', $email);
        $email_check->execute();
        if ($email_check->fetchColumn() > 0) {
            throw new Exception("An account already exists with this email.");
        }

        $stmt = $conn->prepare("INSERT INTO tbluser 
            (userID, passwd, role, surname, forename, yearg, emailAddress, userName, gender, description)
            VALUES (null, :passwd, :role, :surname, :forename, :yearg, :emailAddress, :userName, :gender, :description)");

        $stmt->bindParam(':passwd', $hashed_password);
        $stmt->bindParam(':role', $role); 
        $stmt->bindParam(':surname', $_POST["surname"]);
        $stmt->bindParam(':forename', $_POST["forename"]);
        $stmt->bindParam(':yearg', $_POST["yearg"], PDO::PARAM_INT);  
        $stmt->bindParam(':emailAddress', $email);  
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
        <h2>Swimmer Sign Up</h2>

        <?php if (!empty($error_message)): ?>
            <div class="alert-fail">
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <?php if ($registration_success): ?>
            <div class="alert-success">
                Registration successful! <a href="login.php">Login here</a>
            </div>
        <?php else: ?>
            <form action="swimmerSignup.php" method="post" autocomplete="off">
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
                </div>

                <input type="password" name="passwd" placeholder="Password" required autocomplete="new-password">
                <input type="password" name="confirm_passwd" placeholder="Confirm Password" required autocomplete="new-password">
                <input type="text" name="description" placeholder="Description (optional & 400 characters max)" maxlength="400">
                <button type="submit">Sign Up</button>
            </form>
            
            <div class="extra-section">
                Already have an account?
                <a href="login.php">Login</a>
                <br>
                <br>
                Staff members are kindly asked to contact the Head of Swimming to be registered
            </div>
        
        <?php endif; ?>
    </div>
</div>
</body>
</html>