<?php 

$servername = "localhost";
$username = "root";
$password = "";

try {
    // Create initial connection
    $conn = new PDO("mysql:host=$servername", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $sql = "CREATE DATABASE IF NOT EXISTS oundswimteam";
    $conn->exec($sql);
    
    // Connect to database
    $conn = new PDO("mysql:host=$servername;dbname=oundswimteam", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $conn->beginTransaction();


    // Create Tbl_User
    $stmt = $conn->prepare("DROP TABLE IF EXISTS tbluser;");
    $stmt->execute();
    $stmt->closeCursor();

    $stmt = $conn->prepare(
        "CREATE TABLE tbluser (
            userID INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            passwd VARCHAR(255) NOT NULL,
            role TINYINT(1),
            surname VARCHAR(25) NOT NULL,
            forename VARCHAR(25) NOT NULL,
            yearg INT(2) NOT NULL,
            emailAddress VARCHAR(100) NOT NULL,
            userName VARCHAR(25) NOT NULL,
            gender ENUM('M', 'F') NOT NULL,
            description TEXT NOT NULL
        );"
    );

    $stmt->execute();
    $stmt->closeCursor();
    echo "<br>tbluser created";


    // Create Tbl_Event
    $stmt = $conn->prepare("DROP TABLE IF EXISTS tblevent;");
    $stmt->execute();
    $stmt->closeCursor();

    $stmt = $conn->prepare(
        "CREATE TABLE tblevent (
            eventID INT(3) AUTO_INCREMENT PRIMARY KEY,
            eventName VARCHAR(100) NOT NULL,
            course ENUM('L', 'S') NOT NULL,
            gender ENUM('M', 'F', 'MIX') NOT NULL
        );"
    );

    $stmt->execute();
    $stmt->closeCursor();
    echo "<br>tblevent created";


    // Prefill events for Tbl_Event
    $insert_sql = "INSERT INTO tblevent (eventName, course, gender) VALUES
    
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
    ('Medlay Relay 400m', 'S', 'MIX')";

    $stmt = $conn->prepare($insert_sql);
    $stmt->execute();
    $stmt->closeCursor();
    echo "<br>tblevent populated";

    // Create Tbl_Meet
    $stmt = $conn->prepare("DROP TABLE IF EXISTS tblmeet;");
    $stmt->execute();
    $stmt->closeCursor();

    $stmt = $conn->prepare(
        "CREATE TABLE tblmeet (
            meetID INT(6) AUTO_INCREMENT PRIMARY KEY,
            meetName VARCHAR(100) NOT NULL,
            meetInfo TEXT NOT NULL,
            external ENUM('Y', 'N')
        );"
    );
    $stmt->execute();
    $stmt->closeCursor();
    echo "<br>tblmeet created";


    // Create Tbl_MeetHasEvent
    $stmt = $conn->prepare("DROP TABLE IF EXISTS tblmeetHasEvent;");
    $stmt->execute();
    $stmt->closeCursor();

    $stmt = $conn->prepare(
        "CREATE TABLE tblmeetHasEvent (
            meetID INT(6),
            eventID INT(3),

            PRIMARY KEY (meetID, eventID),
            FOREIGN KEY (meetID) REFERENCES tblmeet(meetID) ON DELETE CASCADE,
            FOREIGN KEY (eventID) REFERENCES tblevent(eventID) ON DELETE CASCADE
        );"
    );
    $stmt->execute();
    $stmt->closeCursor();
    echo "<br>tblmeetHasEvent created";


    // Create Tbl_MeetEventHasSwimmer
    $stmt = $conn->prepare("DROP TABLE IF EXISTS tblmeetEventHasSwimmer;");
    $stmt->execute();
    $stmt->closeCursor();

    $stmt = $conn->prepare(
        "CREATE TABLE tblmeetEventHasSwimmer (
            userID INT(6),
            meetID INT(6),
            eventID INT(3),
            time VARCHAR(8),
            
            PRIMARY KEY (userID, meetID, eventID),
            FOREIGN KEY (meetID, eventID) REFERENCES tblmeetHasEvent(meetID, eventID) ON DELETE CASCADE,
            FOREIGN KEY (userID) REFERENCES tbluser(userid) ON DELETE CASCADE
        );"
    );
    $stmt->execute();
    $stmt->closeCursor();
    echo "<br>tblmeetEventHasSwimmer created";


    $hashed_password = password_hash("Mark123", PASSWORD_DEFAULT);
    $stmt = $conn->prepare(
        "INSERT INTO tbluser (passwd, role, surname, forename, yearg, emailAddress, userName, gender, description)
         VALUES (:passwd, :role, :surname, :forename, :yearg, :emailAddress, :userName, :gender, :description)"
    );
    
    $stmt->execute([
        ':passwd' => $hashed_password,
        ':role' => 2,
        ':surname' => 'Khametov',
        ':forename' => 'Mark',
        ':yearg' => 12,
        ':emailAddress' => 'khametov.m@oundleschool.org.uk',
        ':userName' => 'Mark123',
        ':gender' => 'M',
        ':description' => 'I love testing, testing, testing'
    ]);

    $stmt->closeCursor();

    $conn->commit();
    echo "<br>Database created successfully";

} catch(PDOException $e) {
    // Roll back all changes if any error occurs
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo "<br>Database setup failed: " . $e->getMessage();
}
$conn = null;

?>