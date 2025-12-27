<?php

const INACTIVITY_TIMEOUT_SECONDS = 1800; // 30 minutes

function logout(): void {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}

function enforceSessionPolicies(PDO $conn): void {
    $now = time();

    // Idle timeout
    if (!empty($_SESSION['last_activity']) && ($now - (int)$_SESSION['last_activity'] > INACTIVITY_TIMEOUT_SECONDS)) {
        logout();
    }
    $_SESSION['last_activity'] = $now;

    // Role change check 
    if (!empty($_SESSION['userID']) && isset($_SESSION['role'])) {
        $stmt = $conn->prepare('SELECT role FROM tbluser WHERE userID = :id LIMIT 1');
        $stmt->bindValue(':id', (int)$_SESSION['userID'], PDO::PARAM_INT);
        $stmt->execute();
        $dbRole = $stmt->fetchColumn();

        if ($dbRole === false) {
            // User removed; logout
            logout();
        }

        if ((int)$dbRole !== (int)$_SESSION['role']) {
            // Any role change (demote or promote) forces re-login
            logout();
        }
    }
}