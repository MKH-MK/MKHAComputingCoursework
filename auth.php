<?php

const INACTIVITY_TIMEOUT_SECONDS = 1800; // Session idle timeout (in seconds) before forcing logout

// Clear session state and redirect to login (used by timeout / invalid user cases)
function logout(): void {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}

// Enforce session-level security rules (idle timeout + role/identity sync against the database)
function enforceSessionPolicies(PDO $conn): void {
    $now = time();

    // Enforce idle timeout based on last activity timestamp stored in the session
    if (!empty($_SESSION['last_activity']) && ($now - (int)$_SESSION['last_activity'] > INACTIVITY_TIMEOUT_SECONDS)) {
        logout();
    }
    $_SESSION['last_activity'] = $now; // Refresh activity timestamp on each request

    // Sync role from DB so session role cannot drift from the authoritative user record
    $dbRole = null;

    // Preferred lookup path: userID (stable key)
    if (!empty($_SESSION['userID'])) {
        $st = $conn->prepare('SELECT role FROM tbluser WHERE userID = :id LIMIT 1');
        $st->bindValue(':id', (int)$_SESSION['userID'], PDO::PARAM_INT);
        $st->execute();
        $dbRole = $st->fetchColumn();

    // Fallback lookup path: userName (then backfill userID for future requests)
    } elseif (!empty($_SESSION['userName'])) {
        $st = $conn->prepare('SELECT role, userID FROM tbluser WHERE userName = :uname LIMIT 1');
        $st->bindValue(':uname', $_SESSION['userName'], PDO::PARAM_STR);
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $dbRole = $row['role'];
            $_SESSION['userID'] = (int)$row['userID']; // Backfill userID to avoid future username-based lookups
        }
    }

    // If the DB lookup ran but returned no row, treat as user removed and force logout
    if ($dbRole === false) {
        logout();
    }

    // If a DB role was found, ensure the session role matches it
    if ($dbRole !== null) {
        $dbRoleInt = (int)$dbRole;
        if (!isset($_SESSION['role']) || (int)$_SESSION['role'] !== $dbRoleInt) {
            $_SESSION['role'] = $dbRoleInt; // Overwrite role in session with the authoritative DB value
            session_regenerate_id(true);    // Regenerate session ID when privilege context changes
        }
    }
}