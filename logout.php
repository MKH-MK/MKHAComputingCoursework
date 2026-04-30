<?php
session_start();
include_once('connection.php');
include_once('auth.php');
enforceSessionPolicies($conn); // Apply shared session rules before processing logout

// Clear all session variables (removes any stored login/role/user data)
$_SESSION = array();

// If sessions use cookies, expire the session cookie on the client as well
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),          // Session cookie name (e.g., PHPSESSID)
        '',                      // Empty value
        time() - 42000,          // Expire in the past so browser removes it
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy the session data on the server
session_destroy();

// Redirect back to login with a flag so the UI can show a "logged out" confirmation message
header("Location: login.php?loggedout=1");
exit;
?>