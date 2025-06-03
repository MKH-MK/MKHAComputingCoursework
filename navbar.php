<div class="sidebar">
  <img src="your_logo.png" alt="Oundle School Logo">
  <h5>Oundle School<br>Swim Team</h5>
  <nav class="nav flex-column mt-4" aria-label="Main navigation">
    <a class="nav-link<?php if(basename($_SERVER['PHP_SELF']) === 'homepage.php') echo ' active'; ?>" href="homepage.php" aria-current="<?php if(basename($_SERVER['PHP_SELF']) === 'homepage.php') echo 'page'; ?>">
      <i class="fas fa-home nav-icon"></i>Homepage
    </a>
    <a class="nav-link<?php if(basename($_SERVER['PHP_SELF']) === 'about.php') echo ' active'; ?>" href="about.php" aria-current="<?php if(basename($_SERVER['PHP_SELF']) === 'about.php') echo 'page'; ?>">
      <i class="fas fa-info-circle nav-icon"></i>About Us
    </a>
    <a class="nav-link<?php if(basename($_SERVER['PHP_SELF']) === 'tools.php') echo ' active'; ?>" href="tools.php" aria-current="<?php if(basename($_SERVER['PHP_SELF']) === 'tools.php') echo 'page'; ?>">
      <i class="fas fa-wrench nav-icon"></i>Tools
    </a>
    <a class="nav-link<?php if(basename($_SERVER['PHP_SELF']) === 'login.php') echo ' active'; ?>" href="login.php" aria-current="<?php if(basename($_SERVER['PHP_SELF']) === 'login.php') echo 'page'; ?>">
      <i class="fas fa-sign-in-alt nav-icon"></i>Login
    </a>
    <a class="nav-link<?php if(basename($_SERVER['PHP_SELF']) === 'account.php') echo ' active'; ?>" href="account.php" aria-current="<?php if(basename($_SERVER['PHP_SELF']) === 'account.php') echo 'page'; ?>">
      <i class="fas fa-user nav-icon"></i>Account
    </a>
  </nav>
</div>