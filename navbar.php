<?php
// Subpages on navbar 
$navItems = [
    [
        "link" => "homepage.php",
        "icon" => '<i class="fas fa-home"></i>',
        "label" => "Home"
    ],
    [
        "link" => "-",
        "icon" => '<i class="fas fa-trophy"></i>',
        "label" => "Results"
    ],
    [
        "link" => "-",
        "icon" => '<i class="fas fa-wrench"></i>',
        "label" => "Tools"
    ],
];
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

<!-- Navbar syyling, basically the CSS -->
<style>

body {
    margin: 0;
    background: #F5F5DC;
    font-family: 'Avenir Next LT Pro', Arial, sans-serif;
}

/* Navbar characterisitcs in terms of colour and positioning, as in solution description  */
.navbar {
    position: fixed;
    top: 0;
    left: 0;
    width: 230px;
    height: 100vh;
    background: #004F8B;    
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    z-index: 1000;
}

/* Oundle School logo */
.navbar-logo {
    display: block;
    margin: 28px auto 12px auto;
    max-width: 90px;
    max-height: 90px;
    border-radius: 16px;
    background: #fff;
    padding: 6px;
    box-shadow: 0 2px 8px #004f8b22;
}

/* Title on navbar */
.navbar-header {
    font-family: 'Arial Rounded MT Bold', Arial, sans-serif;
    color: #FFD700;
    font-size: 2.0rem;
    text-align: left;
    padding: 32px 0 24px 0;
    letter-spacing: 0.05rem;
}

.navbar-menu {
    flex: 1;
}

.navbar-menu ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.navbar-menu li {
    margin: 28px 0;
}

.navbar-menu a {
    display: flex;
    align-items: center;
    color: #fff;
    text-decoration: none;
    font-size: 1.2rem;
    padding: 10px 28px;
    transition: color 0.2s, font-weight 0.2s;
    border-radius: 6px 0 0 6px;
    font-family: 'Arial Rounded MT Bold', Arial, sans-serif;
    letter-spacing: 0.03rem;
}

.navbar-menu a:hover, .navbar-menu a:focus {
    color: #FFD700;
    font-weight: bold;
    background: rgba(255,255,255,0.07);
}

.navbar-menu i {
    margin-right: 15px;
    font-size: 1.3em;
}

.navbar-bottom {
    margin-bottom: 28px;
    padding: 0 20px;
}

.navbar-bottom .welcome {
    color: #FFD700;
    font-family: 'Arial Rounded MT Bold', Arial, sans-serif;
    font-size: 1.05rem;
    margin-bottom: 10px;
}

.navbar-bottom .account-links {
    display: flex;
    flex-direction: column;
    gap: 10px;
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
        width: 70px;
    }
    .navbar-header,
    .navbar-menu a span,
    .navbar-bottom .welcome {
        display: none;
    }
    .navbar-menu a, .navbar-menu i {
        justify-content: center;
        margin: 0 auto;
    }
}
</style>

<!-- Strucutre of navbar -->
<nav class="navbar">
    <div>

        <img src="images/oundle-logo1.png" alt="Oundle School Logo" class="navbar-logo">

        <div class="navbar-header">
            Oundle School Swimming Team
        </div>

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