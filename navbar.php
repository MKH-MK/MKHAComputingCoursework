<?php
// Navbar, include this file with: include('navbar.php');
?>

<style>
body {
  margin: 0;
  background-color: #f5f5dc; /* Beige background */
  font-family: 'Avenir Next LT Pro', sans-serif;
}

.sidebar {
  height: 100vh;
  position: fixed;
  top: 0;
  left: 0;
  width: 220px;
  background-color: #004F8B; /* Royal blue */
  padding-top: 1rem;
  color: white;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.sidebar img {
  width: 60px;
  margin-bottom: 0.5rem;
}

.sidebar h5 {
  font-size: 1.1rem;
  font-weight: bold;
  text-align: center;
  line-height: 1.4;
}

.nav-link {
  color: white;
  font-size: 1rem;
  padding: 0.75rem 1rem;
  transition: all 0.3s ease;
  text-decoration: none;
}

.nav-link:hover {
  color: #FFD700; /* Gold hover */
  font-weight: bold;
}

.nav-bottom {
  margin-top: auto;
}

.nav-icon {
  margin-right: 8px;
}

.main-content {
  margin-left: 220px; /* Matches sidebar width */
  padding: 2rem;
}
</style>

<!-- Sidebar navigation -->
<div class="sidebar">

  <!-- School logo -->
  <img src="images/oundle_logo.png" alt="Oundle School Logo">

  <!-- Title -->
  <h5>Oundle School<br>Swim Team</h5>

  <!-- Navigation links -->
  <nav class="nav flex-column mt-4 d-flex w-100">
    <a class="nav-link" href="homepage.php"><i class="fas fa-home nav-icon"></i>Homepage</a>
    <a class="nav-link" href="about.php"><i class="fas fa-info-circle nav-icon"></i>About Us</a>
    <a class="nav-link" href="tools.php"><i class="fas fa-wrench nav-icon"></i>Tools</a>
    
    <div class="nav-bottom">
      <a class="nav-link" href="login.php"><i class="fas fa-sign-in-alt nav-icon"></i>Login</a>
      <a class="nav-link" href="account.php"><i class="fas fa-user nav-icon"></i>Account</a>
    </div>

  </nav>

</div>