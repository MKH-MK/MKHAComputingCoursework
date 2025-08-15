<?php

include_once("connection.php");

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Oundle School Swim Team - Homepage</title>
    <meta name="viewport" content="width=1024, initial-scale=1">
    <link rel="stylesheet" href="style.css">
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

    // Prepare the statement with correct table and columns order/names
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
    
    header('Location: homepage.php');
    exit();

} catch (PDOException $e) {
    echo "<div class='alert alert-danger text-center'>Database Error: " . $e->getMessage() . "</div>";
} catch (Exception $e) {
    echo "<div class='alert alert-danger text-center'>Validation Error: " . $e->getMessage() . "</div>";
}

$conn = null;
?>
