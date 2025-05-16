<?php 

include_once("connection.php");

try {
    $sql = "CREATE DATABASE IF NOT EXISTS OUNSwimTeam";
    $conn->exec($sql);
    
    $sql = "USE OUNSwimTeam";
    $conn->exec($sql);
    echo "DB created successfully";

}
catch(PDOException $e)
{
    echo $sql . "<br>" . $e->getMessage();
}
$conn = null;

?>