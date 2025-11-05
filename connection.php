<?php

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "oundswimteam";

$conn = new PDO("mysql:host=$servername; dbname=$dbname", $username, $password);
$conn -> setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
echo("DB Connected");

?>