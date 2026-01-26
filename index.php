<?php
session_start();
include_once('connection.php');
include_once('auth.php');
enforceSessionPolicies($conn);

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
    
    <?php include 'navbar.php';?>
    
    <div class="main-content">
        <div class="page-title">
            Welcome to the Oundle School Swim Team
        </div>
        <div class="section">
            <h2>About us <i class="fas fa-info-circle" style="color:#004F8B"></i></h2>
            <p style="text-indent: 20px;">
                We believe in the value of sport in a balanced life, and we strive for every pupil to gain the fullest benefit from their sporting experience at Oundle. Whether your goal is leisure or high-level competition, our programme supports the whole team, celebrating individual milestones and collective progress alike, from junior and inter swimmers stepping up in ESSA relays and mixed events to seniors grinding through back to back races, from long away days to Warwick to a recent national finals berth in water polo, and with swimmers who have reached nationals. We’re proud to have swimmers competing at the highest levels while also focusing on every swimmer’s development and the strength of the squad.
            </p>
        </div>
        <div class="section">
            <h2>What We Do <i class="fas fa-star style="color:#004F8B"></i></h2>
            <p style="text-indent: 20px;">
                All our swimmers receive high level coaching three times a week in our 50m pool (varying from it's longcourse or shortcourse configuration), paired with a dryland session prior or after twice a week.
            </p>
            <p style="text-indent: 20px;">
                Our 1st team swimmers work closely with the head swimming outside the pool as well. This includes an extra swim session as well as S&C in our school's performance gym. We also have strong ties with local swim clubs, such as Kettering ASC, Rushden ASC, Croby ASC, and more, giving even more oppurtunities to the swimmers.
            </p>
        </div>
    </div>
</body>
</html>