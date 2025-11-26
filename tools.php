<?php
session_start();
//$_SESSION['username'] = '';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Oundle School Swim Team - Tools</title>
    <meta name="viewport" content="width=1024, initial-scale=1">
    <link rel="stylesheet" href="style.css">
</head>

<body>
    
    <?php include 'navbar.php';?>
    
    <div class="main-content">
    <div class="page-title">
        Helpful Links
    </div>

    <div class="section">
        <h2>Time Converter <i class="fas fa-stopwatch" style="color:#004F8B"></i></h2>
        <p style="text-indent: 20px;">
            Time converters are useful to estimate your time when converting from shortcourse to longcourse (and vice versa).
            Try the
            <a href="https://swimswam.com/swimming-times-conversion-tool/" target="_blank" rel="noopener noreferrer">
                Swimming Times Conversion Tool
            </a>.
        </p>
    </div>

    <div class="section">
        <h2>Swim England <i class="fas fa-list" style="color:#004F8B"></i></h2>
        <p>
            Swim England is the only recognised national governing body for swimming in England.
            If you are a registered member, you can view your standings on the
            <a href="https://www.swimmingresults.org/12months/" target="_blank" rel="noopener noreferrer">
                Swim England 12‑Month Results Page
            </a>.
        </p>
    </div>

</body>
</html>