<?php 

$servername = "localhost";
$username = "root";
$password = "";

try {
    $conn = new PDO("mysql:host=$servername", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $sql = "CREATE DATABASE IF NOT EXISTS oundswimteam";
    $conn->exec($sql);
    
    $sql = "USE oundswimteam";
    $conn->exec($sql);
    #echo "DB created successfully";

    // Create Tbl_User
    $stmt = $conn->prepare("DROP TABLE IF EXISTS tblusers;

    CREATE TABLE tblusers (
    userid INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    passwd VARCHAR(255) NOT NULL,
    role TINYINT(1),
    surname VARCHAR(25) NOT NULL,
    forename VARCHAR(25) NOT NULL,
    yearg INT(2) NOT NULL,
    emailAddress VARCHAR(40) NOT NULL,
    userName VARCHAR(25) NOT NULL,
    gender VARCHAR(1) NOT NULL,
    description TEXT NOT NULL,
    );");

    $stmt->execute();
    $stmt->closeCursor();
    echo "<br>tblusers created";

    // Create Tbl_Event
    $stmt = $conn->prepare("DROP TABLE IF EXISTS tblevent;

    CREATE TABLE tblevent (
    
    );");

    $stmt->execute();
    $stmt->closeCursor();
    echo "<br>tblevent created";

    // Create Tbl_Meet
    $stmt = $conn->prepare("DROP TABLE IF EXISTS tblmeet;

    CREATE TABLE tblmeet (
    
    );");

    $stmt->execute();
    $stmt->closeCursor();
    echo "<br>tblmeet created";

    // Create Tbl_MeetHasEvent
    $stmt = $conn->prepare("DROP TABLE IF EXISTS tblmeetHasEvent;

    CREATE TABLE tblmeetHasEvent (
    
    );");

    $stmt->execute();
    $stmt->closeCursor();
    echo "<br>tblmeetHasEvent created";

    // Create Tbl_MeetEventHasSwimmer
    $stmt = $conn->prepare("DROP TABLE IF EXISTS tblmeetEventHasSwimmer;

    CREATE TABLE tblmeetEventHasSwimmer (
    
    );");

    $stmt->execute();
    $stmt->closeCursor();
    echo "<br>tblmeetEventHasSwimmer created";


    $hashed_password = password_hash("passwd", PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO tblstudents (userid, username, surname, forename, passwd, gender, house, yearg, role) VALUES 
    (NULL, 'Mark.KHA', 'Khametov', 'Mark', :hp, 'M', 'Crosby', 12, 2)");
   
    $stmt->bindParam(':hp', $hashed_password);
    $stmt->execute();
    // $stmt->closeCursor();

}
catch(PDOException $e)
{
    echo $sql . "<br>" . $e->getMessage();
}
$conn = null;

?>