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
        if (substr($email, -21) !== "@oundleschool.org.uk") {
            throw new Exception("Email must be a valid school email address ending in @oundleschool.org.uk.");
        }
        if (substr_count($email, '@') !== 1) {
            throw new Exception("Invalid email: multiple @ symbols found.");
        }

        // Validate passwords
        if ($_POST["passwd"] !== $_POST["confirm_passwd"]) {
            throw new Exception("Passwords do not match.");
        }

        $role = 0;

        $Username = substr($email, 0, strpos($email, '@'));
        $hashed_password = password_hash($_POST["passwd"], PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO tbluser 
            (userID, passwd, role, surname, forename, yearg, emailAddress, userName, gender, description)
            VALUES (null, :passwd, :role, :surname, :forename, :yearg, :emailAddress, :userName, :gender, :description)");

        $stmt->bindParam(':passwd', $hashed_password);
        $stmt->bindParam(':role', $role); 
        $stmt->bindParam(':surname', $_POST["surname"]);
        $stmt->bindParam(':forename', $_POST["forename"]);
        $stmt->bindParam(':yearg', $_POST["yearg"], PDO::PARAM_INT);  
        $stmt->bindParam(':emailAddress', $email);  
        $stmt->bindParam(':userName', $Username); 
        $stmt->bindParam(':gender', $_POST["gender"]);
        $stmt->bindParam(':description', $_POST["description"]);

        $stmt->execute();

        $registration_success = true;
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
    <title>Oundle School Swim Team - Swimmer Sign Up</title>
    <meta name="viewport" content="width=1024, initial-scale=1">
    <link rel="stylesheet" href="loginStyle.css">
</head>

<body>
<?php include 'navbar.php'; ?>

<div class="main-content">
    <div class="form-section">
        <h2>Swimmer Sign Up</h2>

        <?php if ($registration_success): ?>
            <div class="alert alert-success text-center">
                Registration successful! <a href="login.php">Login here</a>
            </div>

        <?php else: ?>
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger text-center"><?= $error_message ?></div>
            <?php endif; ?>

            <form action="signup.php" method="post" autocomplete="off">
                <div class="form-row">
                    <input type="text" name="forename" placeholder="Forename" required>
                    <input type="text" name="surname" placeholder="Surname" required>
                </div>

                <div class="form-row">
                    <input type="email" name="email" placeholder="School Email" required autocomplete="email">
                </div>

                <div class="form-row">
                    <input type="number" name="yearg" class="input-small" placeholder="Year Group" min="7" max="13" required>
                    <select name="gender" class="input-small" required>
                        <option value="" disabled selected>Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <input type="password" name="passwd" placeholder="Password" required autocomplete="new-password">
                <input type="password" name="confirm_passwd" placeholder="Confirm Password" required autocomplete="new-password">
                <input type="text" name="description" placeholder="Description (optional)">
                <button type="submit">Sign Up</button>
            </form>
            <div class="extra-section">
                Already have an account?
                <a href="login.php">Login</a>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>