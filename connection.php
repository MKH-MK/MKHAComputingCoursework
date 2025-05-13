<?php

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "OUNSwimTeam";

$conn = new PDO("mysql:host=$servername; dbname=$dbname", $username, $password);
$conn -> setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
#echo("connected!!");

?>