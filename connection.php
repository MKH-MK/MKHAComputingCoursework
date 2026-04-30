<?php

// DB connection config (MySQL host/credentials + database name)
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "oundswimteam";

// Create PDO connection and throw exceptions on DB errors
$conn = new PDO("mysql:host=$servername; dbname=$dbname", $username, $password);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
#echo("DB Connected");

?>