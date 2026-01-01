<?php
session_start();
include_once('connection.php');
include_once('auth.php');
enforceSessionPolicies($conn);

if (!isset($_SESSION['role']) || (int)$_SESSION['role'] !== 2) {
    // Access denied page (admin only)
    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Oundle School Swim Team - Denied Access</title>
    <meta name="viewport" content="width=1024, initial-scale=1">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="main-content">
        <div class="page-title">Access Denied</div>
        <div class="section">
            <div class="alert-fail">Permision Error: You do not have the right privilege to view this page.</div>
            <h2>Further options:</h2>
            <ul>
                <li>If you think this is an error, please <a href="contact.php">contact the administrator</a>.
                <li><a href="login.php">Login</a></li>
                <li><a href="index.php">Return to Home</a></li>
            </ul>
        </div>
    </div>
</body>
</html>';
    exit();
}

// System settings: annual rollover guard (last_rollover_year + timestamp)
$conn->exec("CREATE TABLE IF NOT EXISTS tblsystem (
    syskey VARCHAR(50) PRIMARY KEY,
    sysvalue VARCHAR(50) NOT NULL
)");
$seed = $conn->prepare("INSERT IGNORE INTO tblsystem (syskey, sysvalue) VALUES
    ('last_rollover_year','0'),
    ('last_rollover_at','')");
$seed->execute();

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$error = '';

// Read last rollover info
$lastYear = '0';
$lastAt = '';
try {
    $stmt = $conn->prepare("SELECT syskey, sysvalue FROM tblsystem WHERE syskey IN ('last_rollover_year','last_rollover_at')");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        if ($r['syskey'] === 'last_rollover_year') $lastYear = $r['sysvalue'];
        if ($r['syskey'] === 'last_rollover_at') $lastAt = $r['sysvalue'];
    }
} catch (PDOException $e) {
    $error = "Database Error: " . htmlspecialchars($e->getMessage());
}

// Handle rollover request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'rollover_users') {
    try {
        // Validate CSRF
        $csrf = $_POST['csrf_token'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            throw new Exception("Invalid session token. Please try again.");
        }

        // Perform rollover inside a transaction
        $conn->beginTransaction();

        // Graduate original Year 13 first (become Guests, yearg = 0)
        $gradCount = $conn->exec("UPDATE tbluser SET role = 0, yearg = 0 WHERE role = 1 AND yearg = 13");

        // Then advance Years 7–12 by +1 (so Year 12 -> Year 13 remains role=1)
        $incCount = $conn->exec("UPDATE tbluser SET yearg = yearg + 1 WHERE role = 1 AND yearg BETWEEN 7 AND 12");

        // Record rollover time and year
        $tz = new DateTimeZone('Europe/London');
        $now = new DateTime('now', $tz);
        $yearStr = (string)$now->format('Y');
        $iso = $now->format('Y-m-d H:i:s');

        $updY = $conn->prepare("UPDATE tblsystem SET sysvalue = :y WHERE syskey = 'last_rollover_year'");
        $updY->bindValue(':y', $yearStr);
        $updY->execute();

        $updT = $conn->prepare("UPDATE tblsystem SET sysvalue = :t WHERE syskey = 'last_rollover_at'");
        $updT->bindValue(':t', $iso);
        $updT->execute();

        $conn->commit();

        $lastYear = $yearStr;
        $lastAt = $iso;
        $message = "Rollover complete: advanced {$incCount} students (Years 7–12); graduated {$gradCount} students (Year 13).";
    } catch (Exception $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        $error = "Error: " . htmlspecialchars($e->getMessage());
    } catch (PDOException $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        $error = "Database Error: " . htmlspecialchars($e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Oundle School Swim Team - Roll Over Users</title>
    <meta name="viewport" content="width=1024, initial-scale=1">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="main-content">
    <div class="page-title">Roll Over Users</div>

    <?php if (!empty($error)): ?>
        <div class="alert-fail"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!empty($message)): ?>
        <div class="alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="section">
        <h3>Last Rollover</h3>
        <p><strong>Year:</strong> <?= htmlspecialchars($lastYear) ?></p>
        <p><strong>Timestamp:</strong> <?= $lastAt !== '' ? htmlspecialchars($lastAt) : 'Never' ?></p>

        <form method="post" id="rolloverForm" class="form-section form-section--wide">
            <input type="hidden" name="action" value="rollover_users">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

            <button type="submit" class="btn btn-danger">Roll Over Users</button>
            <a href="index.php" class="btn">Cancel</a>
        </form>
    </div>
</div>

<script>
// Confirm popup before performing rollover
document.getElementById('rolloverForm')?.addEventListener('submit', function(e) {
    const ok = confirm('This will roll over all students:\n- Years 7–12 move up one\n- Year 13 become Guests (yearg = 0)\nProceed?');
    if (!ok) e.preventDefault();
});
</script>
</body>
</html>