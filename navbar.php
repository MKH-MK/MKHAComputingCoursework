<?php
// Subpages for subnav
$navItems = [
    [
        "link" => "index.php",
        "icon" => '<i class="fas fa-home"></i>',
        "label" => "Home"
    ],
    [
        "link" => "meets.php",
        "icon" => '<i class="fas fa-swimmer"></i>',
        "label" => "Meets"
    ],
    [
        "link" => "swimmers.php",
        "icon" => '<i class="fas fa-user"></i>',
        "label" => "Swimmers"
    ], 
    [
        "link" => "records.php",
        "icon" => '<i class="fas fa-trophy"></i>',
        "label" => "Records"
    ],
    [
        "link" => "tools.php",
        "icon" => '<i class="fas fa-wrench"></i>',
        "label" => "Tools"
    ] 
];
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

<!-- Navbar CSS -->

<style>

body {
    margin: 0;
    background: #fff;
    font-family: 'Avenir Next LT Pro', Arial, sans-serif;
}

/* Main Navbar */
.navbar > div:first-child {
    display: flex;
    align-items: center;
    gap: 20px;
    flex: 1;
}

/* Navbar characterisitcs in terms of colour and positioning, as in solution description  */

.navbar {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100px;
    background: #002f63;
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
    padding: 0 20px;
    z-index: 1000;
}

/* Oundle School logo */
.navbar-logo {
    display: block;
    width: 85px;
    height: auto;
    background: transparent;
    pointer-events: none;
    border-radius: 0;
}

/* Title on navbar */
.navbar-header {
    font-family: 'Arial Rounded MT Bold', Arial, sans-serif;
    color:#FFFFFF;
    font-size: 1.5rem;
    font-weight: bold;
    letter-spacing: 0.05rem;
    max-width: 200px;      
    white-space: normal;
    line-height: 1.2; 
}

.navbar-bottom {
    display: flex;
    align-items: center;
    gap: 16px;
    padding-right: 25px;
    margin-right: 10px;
}

.navbar-bottom .welcome {
    color: #FFD700;
    font-family: 'Arial Rounded MT Bold', Arial, sans-serif;
    font-size: 1.05rem;
    margin-bottom: 15px;
}

.navbar-bottom .account-links {
    display: flex;
    flex-direction: row;
    gap: 14px;
    align-items: center;
}

.navbar-bottom .account-links a,
.navbar-bottom .account-links form button {
    color: #fff;
    background: none;
    border: none;
    text-align: left;
    padding: 0;
    font-size: 1rem;
    cursor: pointer;
    text-decoration: none;
    transition: color 0.2s;
    font-family: 'Arial Rounded MT Bold', Arial, sans-serif;
}

.navbar-bottom .account-links a:hover,
.navbar-bottom .account-links form button:hover {
    color: #FFD700;
}

/* Subnav Styles */
.subnav {
    position: sticky;
    top: 100px; /* Just below main navbar */
    background: #fff;
    border-bottom: 1px solid #ccc;
    z-index: 999;
    width: 100%;
}

.subnav ul {
    display: flex;
    justify-content: center;
    margin: 0;
    padding: 0;
    list-style: none;
}

.subnav li {
    display: inline-block;
    border-right: 1px solid #ccc;
}

.subnav li:last-child {
    border-right: none;
}

.subnav a {
    display: flex;
    align-items: center;
    padding: 10px 16px;
    color: #002f63;
    text-decoration: none;
    font-size: 0.95rem;
    font-family: 'Arial Rounded MT Bold', Arial, sans-serif;
    transition: background 0.2s, color 0.2s;
}

.subnav a i {
    margin-right: 8px;
    font-size: 1.2em;
}

.subnav a:hover {
    color: #FFD700;
    background: #f5f5f5;
}

@media (max-width: 800px) {
    .navbar {
        flex-direction: column;
        height: auto;
        padding: 10px;
        align-items: flex-start;
    }

    .navbar-bottom {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }

    .subnav ul {
        flex-direction: column;
        align-items: flex-start;
    }

    .subnav li {
        border-right: none;
        width: 100%;
    }
}
</style>

<!-- Main Navbar -->
<nav class="navbar">
    <div>
        <img src="images/OundleLogoWHITE.png" alt="Oundle School Logo" class="navbar-logo">
        <div class="navbar-header">Swimming Team</div>
    </div>

    <div class="navbar-bottom">
        <?php if (isset($_SESSION['username'])): ?>
            <div class="welcome">
                Welcome, <?= htmlspecialchars($_SESSION['username']) ?>!
            </div>

            <div class="account-links">
                <a href="account.php"><i class="fas fa-user-circle"></i> My Account</a>
                <form action="logout.php" method="post" style="margin:0;">
                    <button type="submit"><i class="fas fa-sign-out-alt"></i> Logout</button>
                </form>
            </div>
        <?php else: ?>
            <div class="account-links">
                <a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
            </div>
        <?php endif; ?>
    </div>
</nav>

<!-- Sub Navigation Bar -->
<div class="subnav">
    <ul>
        <?php foreach ($navItems as $item): ?>
            <li>
                <a href="<?= htmlspecialchars($item['link']) ?>">
                    <?= $item['icon'] ?>
                    <span><?= htmlspecialchars($item['label']) ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>