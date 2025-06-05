<?php
// Subpages on navbar 
$navItems = [
    [
        "link" => "index.php",
        "icon" => '<i class="fas fa-home"></i>',
        "label" => "Home"
    ],
    [
        "link" => "-",
        "icon" => '<i class="fas fa-swimmer"></i>',
        "label" => "Meets"
    ],
    [
        "link" => "-",
        "icon" => '<i class="fas fa-trophy"></i>',
        "label" => "Records"
    ],
    [
        "link" => "-",
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

/* Navbar rule that makes the left logo + title + menu use flex layout  */
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
    color: #FFD700;
    font-size: 1.5rem;
    font-weight: bold;
    padding: 0;
    text-align: left;
    letter-spacing: 0.05rem;
    max-width: 200px;      
    white-space: normal;
    line-height: 1.2; 
}

.navbar-menu {
    flex: 1;
}

.navbar-menu ul {
    list-style: none;
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: row;
    gap: 20px;
}

.navbar-menu li {
    margin: 0;
}

.navbar-menu a {
    display: flex;
    display: flex;
    align-items: center;
    color: #fff;
    text-decoration: none;
    font-size: 1.1rem;
    padding: 10px 14px;
    border-radius: 6px;
    font-family: 'Arial Rounded MT Bold', Arial, sans-serif;
    letter-spacing: 0.03rem;
}

.navbar-menu a:hover, .navbar-menu a:focus {
    color: #FFD700;
    font-weight: bold;
    background: rgba(255,255,255,0.07);
}

.navbar-menu i {
    margin-right: 12px;
    font-size: 1.4em;
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
    font-weight: bold;
}

@media (max-width: 800px) {
    .navbar {
        flex-direction: column;
        height: auto;
        padding: 10px;
        align-items: flex-start;
    }

    .navbar-menu ul {
        flex-direction: column;
        gap: 10px;
    }

    .navbar-bottom {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
}
</style>

<!-- Strucutre of navbar -->
<nav class="navbar">
    <div>

        <!-- All images stored in a file called images in the root -->
        <img src="images/OundleLogoWHITE.png" alt="Oundle School Logo" class="navbar-logo">

        <div class="navbar-header">Oundle School Swimming Team</div>

        <div class="navbar-menu">
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
                <a href="signup.php"><i class="fas fa-user-plus"></i> Sign Up</a>
            </div>
        <?php endif; ?>

    </div>
</nav>