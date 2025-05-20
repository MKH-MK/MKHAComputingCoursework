<?php

include_once("connection.php");

try {
    $_POST = array_map("htmlspecialchars", $_POST);

    // Validate email
    $email = trim($_POST["email"]);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Invalid email format.");
    }

    if (substr($email, -21) !== "@oundleschool.org.uk") {
        throw new Exception("Email must be a school address ending in @oundleschool.org.uk.");
    }

    // Check only one email input
    if (substr_count($email, '@') !== 1) {
        throw new Exception("Invalid email: multiple @ symbols found.");
    }

    echo($_POST["forename"]);

    switch($_POST["role"]){
        case "Pupil":
            $role = 0;
            break;
        case "Teacher":
            $role = 1;
            break;
        case "Admin":
            $role = 2;
            break;
        default:
            throw new Exception("Invalid role.");
    }

    // Username is the part before the '@'
    $Username = substr($email, 0, strpos($email, '@'));

    $hashed_password = password_hash($_POST["passwd"], PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO tblstudents (userid, username, surname, forename, passwd, gender, house, yearg, role, emailAddress)
                            VALUES (null, :username, :surname, :forename, :passwd, :gender, :house, :year, :role, :email)");

    $stmt->bindParam(':username', $Username);
    $stmt->bindParam(':surname', $_POST["surname"]);
    $stmt->bindParam(':forename', $_POST["forename"]);
    $stmt->bindParam(':passwd', $hashed_password);
    $stmt->bindParam(':gender', $_POST["gender"]);
    $stmt->bindParam(':year', $_POST["year"]);
    $stmt->bindParam(':house', $_POST["house"]);
    $stmt->bindParam(':role', $role);
    $stmt->bindParam(':email', $email);

    $stmt->execute();  

    echo "<div class='alert alert-success text-center'>Registration successful! <a href='login.php'>Login here</a></div>";
    
    header('Location: homepage.php');
    exit();

} catch (PDOException $e) {
    echo "<div class='alert alert-danger text-center'>Database Error: " . $e->getMessage() . "</div>";
} catch (Exception $e) {
    echo "<div class='alert alert-danger text-center'>Validation Error: " . $e->getMessage() . "</div>";
}

$conn = null;
?>
