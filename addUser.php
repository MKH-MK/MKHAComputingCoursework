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
            throw new Exception("Invalid school email syntax: multiple @ symbols found.");
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
    <title>Oundle School Swim Team - Homepage</title>
    <meta name="viewport" content="width=1024, initial-scale=1">
    <link rel="stylesheet" href="loginStyle.css">
</head>

<body>

<?php 

include 'navbar.php';

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
        throw new Exception("Invalid school email syntax: multiple @ symbols found.");
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

    $stmt = $conn->prepare("INSERT INTO tbluser 
        (userID, passwd, role, surname, forename, yearg, emailAddress, userName, gender, description)
        VALUES (null, :passwd, :role, :surname, :forename, :yearg, :emailAddress, :userName, :gender, :description)");
    
    $stmt->bindParam(':passwd', $hashed_password);
    $stmt->bindParam(':role', $role); 
    $stmt->bindParam(':surname', $_POST["surname"]);
    $stmt->bindParam(':forename', $_POST["forename"]);
    $stmt->bindParam(':yearg', $yearg, PDO::PARAM_INT);  
    $stmt->bindParam(':emailAddress', $email);  
    $stmt->bindParam(':userName', $Username); 
    $stmt->bindParam(':gender', $_POST["gender"]);
    $stmt->bindParam(':description', $_POST["description"]);
    
    $stmt->execute();

    echo "<div class='alert alert-success text-center'>Registration successful! <a href='login.php'>Login here</a></div>";
    
    header('Location: index.php');
    exit();

} catch (PDOException $e) {
    echo "<div class='alert alert-danger text-center'>Database Error: " . $e->getMessage() . "</div>";
} catch (Exception $e) {
    echo "<div class='alert alert-danger text-center'>Validation Error: " . $e->getMessage() . "</div>";
}

$conn = null;
?>
