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

    // Role sync: prefer userID; fall back to userName if needed
    $dbRole = null;

    if (!empty($_SESSION['userID'])) {
        $st = $conn->prepare('SELECT role FROM tbluser WHERE userID = :id LIMIT 1');
        $st->bindValue(':id', (int)$_SESSION['userID'], PDO::PARAM_INT);
        $st->execute();
        $dbRole = $st->fetchColumn();
    } elseif (!empty($_SESSION['userName'])) {
        $st = $conn->prepare('SELECT role, userID FROM tbluser WHERE userName = :uname LIMIT 1');
        $st->bindValue(':uname', $_SESSION['userName'], PDO::PARAM_STR);
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $dbRole = $row['role'];
            // Backfill userID for future checks
            $_SESSION['userID'] = (int)$row['userID'];
        }
    }

    if ($dbRole === false) {
        // User removed; logout
        logout();
    }

    if ($dbRole !== null) {
        $dbRoleInt = (int)$dbRole;
        if (!isset($_SESSION['role']) || (int)$_SESSION['role'] !== $dbRoleInt) {
            // Always sync session role to match DB; regenerate session id for safety
            $_SESSION['role'] = $dbRoleInt;
            session_regenerate_id(true);
        }
    }
}