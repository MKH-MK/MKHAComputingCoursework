<?php
session_start();
// Example: $_SESSION['username'] = 'JohnDoe'; // Uncomment for demo
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Oundle School Swim Team - Homepage</title>
    <meta name="viewport" content="width=1024, initial-scale=1">

    <style>
    .main-content {
        margin-left: 230px;
        padding: 48px 5vw 24px 5vw;
        min-height: 100vh;
        background: #F5F5DC;
        font-family: 'Avenir Next LT Pro', Arial, sans-serif;
        color: #222;
        box-sizing: border-box;
    }

    .page-title {
        font-family: 'Arial Rounded MT Bold', Arial, sans-serif;
        font-size: 2.2rem;
        color: #004F8B;
        letter-spacing: 0.06rem;
        margin-bottom: 18px;
    }

    .section {
        background: #fffef8ee;
        border-radius: 12px;
        box-shadow: 0 2px 12px #004f8b17;
        padding: 32px;
        margin-bottom: 32px;
        min-height: 180px;
    }

    .section h2 {
        color: #004F8B;
        font-family: 'Arial Rounded MT Bold', Arial, sans-serif;
        margin-bottom: 10px;
        font-size: 1.4rem;
    }

    .section p {
        font-family: 'Avenir Next LT Pro', Arial, sans-serif;
        letter-spacing: 0.01rem;
        font-size: 1.08rem;
        color: #333;
        line-height: 1.6;
    }

    @media (max-width: 800px) {
        .main-content {
            margin-left: 70px;
            padding: 28px 2vw 12px 2vw;
        }
    }
    </style>
</head>

<body>
    <?php include 'navbar.php'; ?>
    <div class="main-content">
        <div class="page-title">
            Welcome to the Oundle School Swim Team
        </div>
        <div class="section">
            <h2>Placeholder <i class="fas fa-info-circle" style="color:#004F8B"></i></h2>
            <p>
                Placeholder
            </p>
        </div>
        <div class="section">
            <h2>Placeholder</h2>
            <p>
                Placehodlder
            </p>
        </div>
    </div>
</body>
</html>