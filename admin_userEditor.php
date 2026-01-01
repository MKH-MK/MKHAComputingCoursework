<?php
session_start();
include_once('connection.php');
include_once('auth.php');
enforceSessionPolicies($conn);

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

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error_message = '';
$success_message = '';
$user = null;

// Validate ID
if (isset($_GET['edit']) && ctype_digit((string)$_GET['edit'])) {
    $userID = (int)$_GET['edit'];
} elseif (isset($_POST['userID']) && ctype_digit((string)$_POST['userID'])) {
    $userID = (int)$_POST['userID'];
} else {
    $error_message = "Invalid user ID.";
    $userID = 0;
}

if ($userID) {
    // Handle POST update
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            // Validate CSRF
            $csrf = $_POST['csrf_token'] ?? '';
            if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
                throw new Exception("Invalid session token. Please try again.");
            }

            // Collect inputs
            $forename = isset($_POST["forename"]) ? trim($_POST["forename"]) : '';
            $surname  = isset($_POST["surname"]) ? trim($_POST["surname"]) : '';
            $email    = isset($_POST["email"]) ? trim($_POST["email"]) : '';
            $yearg    = isset($_POST["yearg"]) ? (int)$_POST["yearg"] : 0;
            $gender   = isset($_POST["gender"]) ? $_POST["gender"] : '';
            $description = isset($_POST["description"]) ? trim($_POST["description"]) : '';
            $role     = isset($_POST["role"]) ? (int)$_POST["role"] : 1;

            $newPass  = $_POST['new_passwd'] ?? '';
            $newPass2 = $_POST['confirm_new_passwd'] ?? '';

            if ($forename === '' || $surname === '') {
                throw new Exception("Forename and surname are required.");
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email format.");
            }
            if (substr_count($email, '@') !== 1) {
                throw new Exception("Invalid email format.");
            }
            if (substr($email, -20) !== "@oundleschool.org.uk") {
                throw new Exception("Email must be a valid school email address");
            }

            if (!in_array($gender, ['M','F','MIX'], true)) {
                throw new Exception("Please select a valid gender.");
            }

            // CHANGE: Role-based year group validation
            if (!in_array($role, [0,1,2], true)) {
                throw new Exception("Invalid role selected.");
            }
            if ($role === 2) { // Coach/Admin
                if ($yearg !== 0) throw new Exception("Staff/Admin must have Year Group = 0.");
            } elseif ($role === 1) { // Student
                if ($yearg < 7 || $yearg > 13) throw new Exception("Students must have Year Group between 7 and 13.");
            } elseif ($role === 0) { // Guest
                if ($yearg !== 0) throw new Exception("Guests must have Year Group = 0.");
            }

            if (strlen($description) > 400) {
                throw new Exception("Description cannot exceed 400 characters.");
            }

            if ($newPass !== '' || $newPass2 !== '') {
                if ($newPass !== $newPass2) {
                    throw new Exception("New passwords do not match.");
                }
            }

            // Unique email check (excluding current user)
            $email_check = $conn->prepare("SELECT COUNT(*) FROM tbluser WHERE emailAddress = :email AND userID <> :id");
            $email_check->bindValue(':email', $email);
            $email_check->bindValue(':id', $userID, PDO::PARAM_INT);
            $email_check->execute();
            if ($email_check->fetchColumn() > 0) {
                throw new Exception("Another account already exists with this email.");
            }

            $userName = substr($email, 0, strpos($email, '@'));

            // Build update
            if ($newPass !== '') {
                $hashed_password = password_hash($newPass, PASSWORD_DEFAULT);
                $sql = "UPDATE tbluser
                        SET forename = :forename,
                            surname = :surname,
                            emailAddress = :email,
                            userName = :userName,
                            yearg = :yearg,
                            gender = :gender,
                            role = :role,
                            description = :description,
                            passwd = :passwd
                        WHERE userID = :id";
            } else {
                $sql = "UPDATE tbluser
                        SET forename = :forename,
                            surname = :surname,
                            emailAddress = :email,
                            userName = :userName,
                            yearg = :yearg,
                            gender = :gender,
                            role = :role,
                            description = :description
                        WHERE userID = :id";
            }

            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':forename', $forename);
            $stmt->bindValue(':surname', $surname);
            $stmt->bindValue(':email', $email);
            $stmt->bindValue(':userName', $userName);
            $stmt->bindValue(':yearg', $yearg, PDO::PARAM_INT);
            $stmt->bindValue(':gender', $gender);
            $stmt->bindValue(':role', $role, PDO::PARAM_INT);
            $stmt->bindValue(':description', $description);
            if ($newPass !== '') {
                $stmt->bindValue(':passwd', $hashed_password);
            }
            $stmt->bindValue(':id', $userID, PDO::PARAM_INT);
            $stmt->execute();

            $success_message = "User updated successfully.";
        } catch (PDOException $e) {
            $error_message = "Database Error: " . $e->getMessage();
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    }

    // Load user for display (or reload after save)
    try {
        $stmt = $conn->prepare("SELECT userID, forename, surname, emailAddress, userName, yearg, gender, role, description FROM tbluser WHERE userID = :id");
        $stmt->bindValue(':id', $userID, PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            $error_message = $error_message ?: "User not found.";
        }
    } catch (PDOException $e) {
        $error_message = "Database Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Oundle School Swim Team - Edit User</title>
    <meta name="viewport" content="width=1024, initial-scale=1">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="main-content">
    <div class="form-section">
        <h2>Edit User</h2>

        <?php if (!empty($error_message)): ?>
            <div class="alert-fail">
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="alert-success">
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <?php if ($user): ?>
            <form action="admin_userEditor.php" method="post" autocomplete="off">
                <div class="form-row">
                    <input type="text" name="forename" placeholder="Forename" required value="<?= htmlspecialchars($user['forename']) ?>">
                    <input type="text" name="surname" placeholder="Surname" required value="<?= htmlspecialchars($user['surname']) ?>">
                </div>

                <div class="form-row">
                    <input type="email" name="email" placeholder="School Email" required autocomplete="email" value="<?= htmlspecialchars($user['emailAddress']) ?>">
                </div>

                <div class="form-row form-row--center">
                    <!-- CHANGE: allow 0 for Staff/Admin and Guest -->
                    <input type="number" name="yearg" class="input-small" placeholder="Year Group" min="0" max="13" required value="<?= (int)$user['yearg'] ?>">
                    <select name="gender" class="input-small" required>
                        <option value="" disabled>Gender</option>
                        <option value="M" <?= $user['gender']==='M'?'selected':'' ?>>Male</option>
                        <option value="F" <?= $user['gender']==='F'?'selected':'' ?>>Female</option>
                        <option value="MIX" <?= $user['gender']==='MIX'?'selected':'' ?>>Other</option>
                    </select>
                    <select name="role" class="input-small" required>
                        <option value="" disabled>Role</option>
                        <option value="0" <?= (int)$user['role']===0?'selected':'' ?>>Guest</option>
                        <option value="1" <?= (int)$user['role']===1?'selected':'' ?>>Student</option>
                        <option value="2" <?= (int)$user['role']===2?'selected':'' ?>>Coach / Admin</option>
                    </select>
                </div>

                <input type="text" name="description" placeholder="Description (optional & 400 characters max)" maxlength="400" value="<?= htmlspecialchars($user['description'] ?? '') ?>">

                <div class="section">
                    <h3>Reset Password (optional)</h3>
                    <input type="password" name="new_passwd" placeholder="New Password" autocomplete="new-password">
                    <input type="password" name="confirm_new_passwd" placeholder="Confirm New Password" autocomplete="new-password">
                </div>

                <input type="hidden" name="userID" value="<?= (int)$user['userID'] ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

                <button type="submit">Save Changes</button>
                <a class="btn" href="admin_userList.php">Back to User List</a>
            </form>
        <?php else: ?>
            <div class="section">
                <a class="btn" href="admin_userList.php">Back to User List</a>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>