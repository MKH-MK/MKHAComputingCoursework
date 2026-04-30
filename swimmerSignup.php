<?php
include_once('connection.php');
include_once('auth.php');
enforceSessionPolicies($conn); // Apply shared session rules (if any session exists) before handling signup

$registration_success = false;
$error_message = '';

// Handle signup submission (validation + insert) on POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        // Basic output-sanitization of POST values (used later in messages / DB inserts)
        $_POST = array_map("htmlspecialchars", $_POST);

        // Read and validate email input
        $email = trim($_POST["email"]);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format.");
        }

        // Enforce school-only email domain for account creation
        if (substr($email, -20) !== "@oundleschool.org.uk") {
            throw new Exception("Email must be a valid school email address");
        }

        // Extra guard against malformed addresses with multiple @ symbols
        if (substr_count($email, '@') !== 1) {
            throw new Exception("Invalid email format");
        }

        // Confirm password fields match before hashing/storing
        if ($_POST["passwd"] !== $_POST["confirm_passwd"]) {
            throw new Exception("Passwords do not match.");
        }
        
        // Optional profile description length guard
        $description = isset($_POST["description"]) ? $_POST["description"] : "";
        if (strlen($description) > 400) {
            throw new Exception("Description cannot exceed 400 characters.");
        }

        $role = 1; // All signups through this form are created as swimmers/students

        // Derive username from email local-part (everything before @)
        $userName = substr($email, 0, strpos($email, '@'));

        // Hash the password for storage (never store plain text passwords)
        $hashed_password = password_hash($_POST["passwd"], PASSWORD_DEFAULT);

        // Prevent duplicate accounts by email address
        $email_check = $conn->prepare("SELECT COUNT(*) FROM tbluser WHERE emailAddress = :email");
        $email_check->bindParam(':email', $email);
        $email_check->execute();
        if ($email_check->fetchColumn() > 0) {
            throw new Exception("An account already exists with this email.");
        }

        // Insert a new swimmer record into tbluser
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

        // If insert succeeds, toggle UI into "success" state
        $registration_success = true;

    // DB-level errors (constraint violations, connectivity issues, etc.)
    } catch (PDOException $e) {
        $error_message = "Database Error: " . $e->getMessage();

    // Validation / business-rule errors thrown above
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }

    // Close DB connection at end of request handling
    $conn = null;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Oundle School Swim Team - Swimmer Sign Up</title>
    <meta name="viewport" content="width=1024, initial-scale=1">
    <link rel="stylesheet" href="style.css">
</head>

<body>
<?php include 'navbar.php'; ?> <!-- Shared site navigation -->

<div class="main-content">
    <div class="form-section">
        <h2>Swimmer Sign Up</h2>

        <!-- Show validation/DB error (if any) -->
        <?php if (!empty($error_message)): ?>
            <div class="alert-fail">
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <!-- On successful registration, show success message + link to login -->
        <?php if ($registration_success): ?>
            <div class="alert-success">
                Registration successful! <a href="login.php">Login here</a>
            </div>

        <!-- Otherwise, show the signup form -->
        <?php else: ?>
            <form action="swimmerSignup.php" method="post" autocomplete="off">
                <!-- Name fields (pattern prevents digits) -->
                <div class="form-row">
                    <input type="text" name="forename" placeholder="Forename" required pattern="^[^0-9]+$" title="No numbers allowed">
                    <input type="text" name="surname" placeholder="Surname" required pattern="^[^0-9]+$" title="No numbers allowed">
                </div>

                <!-- School email used to validate domain and derive username -->
                <div class="form-row">
                    <input type="email" name="email" placeholder="School Email" required autocomplete="email">
                </div>

                <!-- Year group + gender selectors -->
                <div class="form-row form-row--center">
                    <input type="number" name="yearg" class="input-small" placeholder="Year Group" min="7" max="13" required>
                    <select name="gender" class="input-small" required>
                        <option value="" disabled selected>Gender</option>
                        <option value="M">Male</option>
                        <option value="F">Female</option>
                        <option value="MIX">Other</option>
                    </select>
                </div>

                <!-- Password + confirmation (hashed before insert) -->
                <input type="password" name="passwd" placeholder="Password" required autocomplete="new-password">
                <input type="password" name="confirm_passwd" placeholder="Confirm Password" required autocomplete="new-password">

                <!-- Optional short profile description -->
                <input type="text" name="description" placeholder="Description (optional & 400 characters max)" maxlength="400">

                <button type="submit">Sign Up</button>
            </form>
            
            <!-- Secondary info/links for existing accounts and staff registration guidance -->
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