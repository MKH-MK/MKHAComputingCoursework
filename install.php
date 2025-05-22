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
    eventID INT AUTO_INCREMENT PRIMARY KEY,
    eventName VARCHAR(100) NOT NULL,
    course ENUM('L', 'S') NOT NULL,
    gender ENUM('M', 'F', 'MIX') NOT NULL
    );");

    $stmt->execute();
    $stmt->closeCursor();
    echo "<br>tblevent created";


    // Prefill events for Tbl_Event
    $insert = $conn->prepare("INSERT INTO tblevent (eventName, course, gender) VALUES
    INSERT INTO tblevent (eventName, course, gender) VALUES
    
    ('Freestyle 50m', 'L', 'M'),
    ('Freestyle 50m', 'L', 'F'),
    ('Freestyle 50m', 'S', 'M'),
    ('Freestyle 50m', 'S', 'F'),

    ('Freestyle 100m', 'L', 'M'),
    ('Freestyle 100m', 'L', 'F'),
    ('Freestyle 100m', 'S', 'M'),
    ('Freestyle 100m', 'S', 'F'),

    ('Freestyle 200m', 'L', 'M'),
    ('Freestyle 200m', 'L', 'F'),
    ('Freestyle 200m', 'S', 'M'),
    ('Freestyle 200m', 'S', 'F'),

    ('Freestyle 400m', 'L', 'M'),
    ('Freestyle 400m', 'L', 'F'),
    ('Freestyle 400m', 'S', 'M'),
    ('Freestyle 400m', 'S', 'F'),

    ('Freestyle 800m', 'L', 'M'),
    ('Freestyle 800m', 'L', 'F'),
    ('Freestyle 800m', 'S', 'M'),
    ('Freestyle 800m', 'S', 'F'),

    ('Freestyle 1500m', 'L', 'M'),
    ('Freestyle 1500m', 'L', 'F'),
    ('Freestyle 1500m', 'S', 'M'),
    ('Freestyle 1500m', 'S', 'F'),

    ('Backstroke 50m', 'L', 'M'),
    ('Backstroke 50m', 'L', 'F'),
    ('Backstroke 50m', 'S', 'M'),
    ('Backstroke 50m', 'S', 'F'),

    ('Backstroke 100m', 'L', 'M'),
    ('Backstroke 100m', 'L', 'F'),
    ('Backstroke 100m', 'S', 'M'),
    ('Backstroke 100m', 'S', 'F'),

    ('Backstroke 200m', 'L', 'M'),
    ('Backstroke 200m', 'L', 'F'),
    ('Backstroke 200m', 'S', 'M'),
    ('Backstroke 200m', 'S', 'F'),

    ('Breastroke 50m', 'L', 'M'),
    ('Breastroke 50m', 'L', 'F'),
    ('Breastroke 50m', 'S', 'M'),
    ('Breastroke 50m', 'S', 'F'),

    ('Breastroke 100m', 'L', 'M'),
    ('Breastroke 100m', 'L', 'F'),
    ('Breastroke 100m', 'S', 'M'),
    ('Breastroke 100m', 'S', 'F'),

    ('Breastroke 200m', 'L', 'M'),
    ('Breastroke 200m', 'L', 'F'),
    ('Breastroke 200m', 'S', 'M'),
    ('Breastroke 200m', 'S', 'F'),

    ('Fly 50m', 'L', 'M'),
    ('Fly 50m', 'L', 'F'),
    ('Fly 50m', 'S', 'M'),
    ('Fly 50m', 'S', 'F'),

    ('Fly 100m', 'L', 'M'),
    ('Fly 100m', 'L', 'F'),
    ('Fly 100m', 'S', 'M'),
    ('Fly 100m', 'S', 'F'),

    ('Fly 200m', 'L', 'M'),
    ('Fly 200m', 'L', 'F'),
    ('Fly 200m', 'S', 'M'),
    ('Fly 200m', 'S', 'F'),

    ('IM 100m', 'L', 'M'),
    ('IM 100m', 'L', 'F'),
    ('IM 100m', 'S', 'M'),
    ('IM 100m', 'S', 'F'),

    ('IM 200m', 'L', 'M'),
    ('IM 200m', 'L', 'F'),
    ('IM 200m', 'S', 'M'),
    ('IM 200m', 'S', 'F'),

    ('IM 400m', 'L', 'M'),
    ('IM 400m', 'L', 'F'),
    ('IM 400m', 'S', 'M'),
    ('IM 400m', 'S', 'F'),

    ('Freestyle Relay 200m', 'L', 'M'),
    ('Freestyle Relay 200m', 'L', 'F'),
    ('Medlay Relay 200m', 'S', 'M'),
    ('Medlay Relay 200m', 'S', 'F'),
    ('Freestyle Relay 200m', 'L', 'MIX'),
    ('Freestyle Relay 200m', 'S', 'MIX'),
    ('Medlay Relay 200m', 'L', 'MIX'),
    ('Medlay Relay 200m', 'S', 'MIX'),
    
    ('Freestyle Relay 400m', 'L', 'M'),
    ('Freestyle Relay 400m', 'L', 'F'),
    ('Medlay Relay 400m', 'S', 'M'),
    ('Medlay Relay 400m', 'S', 'F'),
    ('Freestyle Relay 400m', 'L', 'MIX'),
    ('Freestyle Relay 400m', 'S', 'MIX'),
    ('Medlay Relay 400m', 'L', 'MIX'),
    ('Medlay Relay 400m', 'S', 'MIX'),
    
    ;");
    $insert->execute();
    $insert->closeCursor();

echo "<br>tblevent created and populated";


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