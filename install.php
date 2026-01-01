<?php 

$servername = "localhost";
$username = "root";
$password = "";

try {
    // Create initial connection
    $conn = new PDO("mysql:host=$servername", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $conn->exec("DROP DATABASE IF EXISTS oundswimteam");
    $conn->exec("CREATE DATABASE oundswimteam");
    echo "DB made";

    // Connect to DB
    $conn = new PDO("mysql:host=$servername;dbname=oundswimteam", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connection made";

    // Create Tbl_User
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
            gender ENUM('M', 'F', 'MIX') NOT NULL,
            description TEXT NOT NULL
        );"
    );

    $stmt->execute();
    $stmt->closeCursor();
    echo "<br>tbluser created";


    // Create Tbl_Event
    // CHANGES FOR RELAY: add eventType to distinguish individual vs relay events
    $stmt = $conn->prepare(
        "CREATE TABLE tblevent (
            eventID INT(3) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            eventName VARCHAR(100) NOT NULL,
            course ENUM('L', 'S') NOT NULL,
            gender ENUM('M', 'F', 'MIX') NOT NULL,
            eventType ENUM('INDIV','RELAY') NOT NULL DEFAULT 'INDIV'
        );"
    );

    $stmt->execute();
    $stmt->closeCursor();
    echo "<br>tblevent created";

    // Prefill events for Tbl_Event
    // CHANGES FOR RELAY: mark relay rows as eventType='RELAY', others default INDIV
    $insert_sql = "INSERT INTO tblevent (eventName, course, gender, eventType) VALUES

    ('Backstroke 50m', 'L', 'M', 'INDIV'),
    ('Backstroke 50m', 'L', 'F', 'INDIV'),
    ('Backstroke 50m', 'S', 'M', 'INDIV'),
    ('Backstroke 50m', 'S', 'F', 'INDIV'),

    ('Backstroke 100m', 'L', 'M', 'INDIV'),
    ('Backstroke 100m', 'L', 'F', 'INDIV'),
    ('Backstroke 100m', 'S', 'M', 'INDIV'),
    ('Backstroke 100m', 'S', 'F', 'INDIV'),

    ('Backstroke 200m', 'L', 'M', 'INDIV'),
    ('Backstroke 200m', 'L', 'F', 'INDIV'),
    ('Backstroke 200m', 'S', 'M', 'INDIV'),
    ('Backstroke 200m', 'S', 'F', 'INDIV'),

    ('Breastroke 50m', 'L', 'M', 'INDIV'),
    ('Breastroke 50m', 'L', 'F', 'INDIV'),
    ('Breastroke 50m', 'S', 'M', 'INDIV'),
    ('Breastroke 50m', 'S', 'F', 'INDIV'),

    ('Breastroke 100m', 'L', 'M', 'INDIV'),
    ('Breastroke 100m', 'L', 'F', 'INDIV'),
    ('Breastroke 100m', 'S', 'M', 'INDIV'),
    ('Breastroke 100m', 'S', 'F', 'INDIV'),

    ('Breastroke 200m', 'L', 'M', 'INDIV'),
    ('Breastroke 200m', 'L', 'F', 'INDIV'),
    ('Breastroke 200m', 'S', 'M', 'INDIV'),
    ('Breastroke 200m', 'S', 'F', 'INDIV'),

    ('Fly 50m', 'L', 'M', 'INDIV'),
    ('Fly 50m', 'L', 'F', 'INDIV'),
    ('Fly 50m', 'S', 'M', 'INDIV'),
    ('Fly 50m', 'S', 'F', 'INDIV'),

    ('Fly 100m', 'L', 'M', 'INDIV'),
    ('Fly 100m', 'L', 'F', 'INDIV'),
    ('Fly 100m', 'S', 'M', 'INDIV'),
    ('Fly 100m', 'S', 'F', 'INDIV'),

    ('Fly 200m', 'L', 'M', 'INDIV'),
    ('Fly 200m', 'L', 'F', 'INDIV'),
    ('Fly 200m', 'S', 'M', 'INDIV'),
    ('Fly 200m', 'S', 'F', 'INDIV'),

    ('Freestyle 50m', 'L', 'M', 'INDIV'),
    ('Freestyle 50m', 'L', 'F', 'INDIV'),
    ('Freestyle 50m', 'S', 'M', 'INDIV'),
    ('Freestyle 50m', 'S', 'F', 'INDIV'),

    ('Freestyle 100m', 'L', 'M', 'INDIV'),
    ('Freestyle 100m', 'L', 'F', 'INDIV'),
    ('Freestyle 100m', 'S', 'M', 'INDIV'),
    ('Freestyle 100m', 'S', 'F', 'INDIV'),

    ('Freestyle 200m', 'L', 'M', 'INDIV'),
    ('Freestyle 200m', 'L', 'F', 'INDIV'),
    ('Freestyle 200m', 'S', 'M', 'INDIV'),
    ('Freestyle 200m', 'S', 'F', 'INDIV'),

    ('Freestyle 400m', 'L', 'M', 'INDIV'),
    ('Freestyle 400m', 'L', 'F', 'INDIV'),
    ('Freestyle 400m', 'S', 'M', 'INDIV'),
    ('Freestyle 400m', 'S', 'F', 'INDIV'),

    ('Freestyle 800m', 'L', 'M', 'INDIV'),
    ('Freestyle 800m', 'L', 'F', 'INDIV'),
    ('Freestyle 800m', 'S', 'M', 'INDIV'),
    ('Freestyle 800m', 'S', 'F', 'INDIV'),

    ('Freestyle 1500m', 'L', 'M', 'INDIV'),
    ('Freestyle 1500m', 'L', 'F', 'INDIV'),
    ('Freestyle 1500m', 'S', 'M', 'INDIV'),
    ('Freestyle 1500m', 'S', 'F', 'INDIV'),

    ('IM 100m', 'L', 'M', 'INDIV'),
    ('IM 100m', 'L', 'F', 'INDIV'),
    ('IM 100m', 'S', 'M', 'INDIV'),
    ('IM 100m', 'S', 'F', 'INDIV'),

    ('IM 200m', 'L', 'M', 'INDIV'),
    ('IM 200m', 'L', 'F', 'INDIV'),
    ('IM 200m', 'S', 'M', 'INDIV'),
    ('IM 200m', 'S', 'F', 'INDIV'),

    ('IM 400m', 'L', 'M', 'INDIV'),
    ('IM 400m', 'L', 'F', 'INDIV'),
    ('IM 400m', 'S', 'M', 'INDIV'),
    ('IM 400m', 'S', 'F', 'INDIV'),

    ('Freestyle Relay 200m', 'L', 'M', 'RELAY'),
    ('Freestyle Relay 200m', 'L', 'F', 'RELAY'),
    ('Medlay Relay 200m', 'S', 'M', 'RELAY'),
    ('Medlay Relay 200m', 'S', 'F', 'RELAY'),
    ('Mixed Freestyle Relay 200m', 'L', 'MIX', 'RELAY'),
    ('Mixed Freestyle Relay 200m', 'S', 'MIX', 'RELAY'),
    ('Mixed Medlay Relay 200m', 'L', 'MIX', 'RELAY'),
    ('Mixed Medlay Relay 200m', 'S', 'MIX', 'RELAY'),
    
    ('Freestyle Relay 400m', 'L', 'M', 'RELAY'),
    ('Freestyle Relay 400m', 'L', 'F', 'RELAY'),
    ('Medlay Relay 400m', 'S', 'M', 'RELAY'),
    ('Medlay Relay 400m', 'S', 'F', 'RELAY'),
    ('Mixed Freestyle Relay 400m', 'L', 'MIX', 'RELAY'),
    ('Mixed Freestyle Relay 400m', 'S', 'MIX', 'RELAY'),
    ('Mixed Medlay Relay 400m', 'L', 'MIX', 'RELAY'),
    ('Mixed Medlay Relay 400m', 'S', 'MIX', 'RELAY')";

    $stmt = $conn->prepare($insert_sql);
    $stmt->execute();
    $stmt->closeCursor();
    echo "<br>tblevent populated";

    // Create Tbl_Meet
    $stmt = $conn->prepare(
        "CREATE TABLE tblmeet (
            meetID INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            meetName VARCHAR(100) NOT NULL,
            meetDate DATE NOT NULL,
            meetInfo TEXT NOT NULL,
            external ENUM('Y', 'N'),
            course ENUM('L', 'S')
        );"
    );
    $stmt->execute();
    $stmt->closeCursor();
    echo "<br>tblmeet created";


    // Create Tbl_MeetHasEvent
    $stmt = $conn->prepare(
        "CREATE TABLE tblmeetHasEvent (
            meetID INT(6) UNSIGNED,
            eventID INT(3) UNSIGNED,

            PRIMARY KEY (meetID, eventID),
            FOREIGN KEY (meetID) REFERENCES tblmeet(meetID) ON DELETE CASCADE,
            FOREIGN KEY (eventID) REFERENCES tblevent(eventID) ON DELETE CASCADE
        );"
    );
    $stmt->execute();
    $stmt->closeCursor();
    echo "<br>tblmeetHasEvent created";


    // Create Tbl_MeetEventHasSwimmer (INDIV results only)
    // CHANGE: added yeargAtEvent to snapshot the swimmer's year at the time of result creation
    $stmt = $conn->prepare(
        "CREATE TABLE tblmeetEventHasSwimmer (
            userID INT(6) UNSIGNED,
            meetID INT(6) UNSIGNED,
            eventID INT(3) UNSIGNED,
            time VARCHAR(8),
            yeargAtEvent INT(2) NOT NULL,

            PRIMARY KEY (userID, meetID, eventID),
            FOREIGN KEY (meetID, eventID) REFERENCES tblmeetHasEvent(meetID, eventID) ON DELETE CASCADE,
            FOREIGN KEY (userID) REFERENCES tbluser(userID) ON DELETE CASCADE
        );"
    );
    $stmt->execute();
    $stmt->closeCursor();
    echo "<br>tblmeetEventHasSwimmer created";

    // CHANGES FOR RELAY: new tables to store team + members
    $stmt = $conn->prepare(
        "CREATE TABLE tblrelayTeam (
            meetID INT(6) UNSIGNED NOT NULL,
            eventID INT(3) UNSIGNED NOT NULL,
            teamName VARCHAR(20) NULL,
            totalTime VARCHAR(8) NULL,
            notes TEXT NULL,
                
            PRIMARY KEY (meetID, eventID),
            FOREIGN KEY (meetID) REFERENCES tblmeet(meetID) ON DELETE CASCADE,
            FOREIGN KEY (eventID) REFERENCES tblevent(eventID) ON DELETE CASCADE
        );"
    );
    $stmt->execute();
    $stmt->closeCursor();
    echo "<br>tblrelayTeam created";

    $stmt = $conn->prepare(
        "CREATE TABLE tblrelayTeamMember (
            meetID INT(6) UNSIGNED NOT NULL,
            eventID INT(3) UNSIGNED NOT NULL,
            userID INT(6) UNSIGNED NOT NULL,
            leg TINYINT NOT NULL,

            PRIMARY KEY (meetID, eventID, leg),
            KEY idx_user (userID),
            FOREIGN KEY (meetID, eventID) REFERENCES tblrelayTeam(meetID, eventID) ON DELETE CASCADE,
            FOREIGN KEY (userID) REFERENCES tbluser(userID) ON DELETE CASCADE
        );"
    );
    $stmt->execute();
    $stmt->closeCursor();
    echo "<br>tblrelayTeamMember created";

    // System settings: annual rollover guard (last_rollover_year + timestamp)
    $stmt = $conn->prepare(
        "CREATE TABLE IF NOT EXISTS tblsystem (
            syskey VARCHAR(50) PRIMARY KEY,
            sysvalue VARCHAR(50) NOT NULL
        );"
    );
    $stmt->execute();
    $stmt->closeCursor();
    echo "<br>tblsystem created";

    $stmt = $conn->prepare("INSERT IGNORE INTO tblsystem (syskey, sysvalue) VALUES
        ('last_rollover_year','0'),
        ('last_rollover_at','')");
    $stmt->execute();
    $stmt->closeCursor();
    echo "<br>tblsystem populated";

    echo "<br>Database created successfully";

} catch(PDOException $e) {
    echo "<br>Database setup failed: " . $e->getMessage();
}
$conn = null;

?>