<?php
session_start();
include_once('connection.php');
include_once('auth.php');
enforceSessionPolicies($conn); // Apply session timeout + role sync before allowing admin actions

// Access control: admin-only (role 2)
if (!isset($_SESSION['role']) || (int)$_SESSION['role'] !== 2) {
    // Render access denied page and stop (prevents non-admins from triggering rollover)
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

// System settings storage used to guard/track annual rollover operations
$conn->exec("CREATE TABLE IF NOT EXISTS tblsystem (
    syskey VARCHAR(50) PRIMARY KEY,
    sysvalue VARCHAR(50) NOT NULL
)");

// Seed baseline keys if they don't exist yet (records last rollover year and timestamp)
$seed = $conn->prepare("INSERT IGNORE INTO tblsystem (syskey, sysvalue) VALUES
    ('last_rollover_year','0'),
    ('last_rollover_at','')");
$seed->execute();

// Create CSRF token once per session to protect the rollover action from cross-site requests
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$error = '';

// Load last rollover metadata for display (year + timestamp)
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

// Handle rollover request (POST action from the confirmation form)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'rollover_users') {
    try {
        // Validate CSRF token from form against the session token
        $csrf = $_POST['csrf_token'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            throw new Exception("Invalid session token. Please try again.");
        }

        // Ensure the updates (graduate + increment + metadata updates) all succeed or all fail together
        $conn->beginTransaction();

        // Step 1: graduate Year 13 students (role=1) into Guests (role=0) and clear year group to 0
        $gradCount = $conn->exec("UPDATE tbluser SET role = 0, yearg = 0 WHERE role = 1 AND yearg = 13");

        // Step 2: promote Years 7–12 by +1 (keeps them as students, role=1)
        $incCount = $conn->exec("UPDATE tbluser SET yearg = yearg + 1 WHERE role = 1 AND yearg BETWEEN 7 AND 12");

        // Capture rollover time for logging/display (stored as year + timestamp string)
        $tz = new DateTimeZone('Europe/London');
        $now = new DateTime('now', $tz);
        $yearStr = (string)$now->format('Y');
        $iso = $now->format('Y-m-d H:i:s');

        // Persist "last rollover year" in tblsystem
        $updY = $conn->prepare("UPDATE tblsystem SET sysvalue = :y WHERE syskey = 'last_rollover_year'");
        $updY->bindValue(':y', $yearStr);
        $updY->execute();

        // Persist "last rollover timestamp" in tblsystem
        $updT = $conn->prepare("UPDATE tblsystem SET sysvalue = :t WHERE syskey = 'last_rollover_at'");
        $updT->bindValue(':t', $iso);
        $updT->execute();

        $conn->commit();

        // Update values shown on page without requiring a reload
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
<?php include 'navbar.php'; ?> <!-- Shared site navigation -->

<div class="main-content">
    <div class="page-title">Roll Over Users</div>

    <!-- Display any error messages from setup/DB reads/rollover action -->
    <?php if (!empty($error)): ?>
        <div class="alert-fail"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Display success message after a completed rollover -->
    <?php if (!empty($message)): ?>
        <div class="alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="section">
        <h3>Last Rollover</h3>
        <p><strong>Year:</strong> <?= htmlspecialchars($lastYear) ?></p>
        <p><strong>Timestamp:</strong> <?= $lastAt !== '' ? htmlspecialchars($lastAt) : 'Never' ?></p>

        <!-- Admin action form posts back to this page and includes CSRF token -->
        <form method="post" id="rolloverForm" class="form-section form-section--wide">
            <input type="hidden" name="action" value="rollover_users">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

            <button type="submit" class="btn btn-danger">Roll Over Users</button>
            <a href="index.php" class="btn">Cancel</a>
        </form>
    </div>
</div>

<script>
// Client-side confirmation to reduce accidental rollover actions
document.getElementById('rolloverForm')?.addEventListener('submit', function(e) {
    const ok = confirm('This will roll over all students:\n- Years 7–12 move up one\n- Year 13 become Guests (yearg = 0)\nProceed?');
    if (!ok) e.preventDefault();
});
</script>
</body>
</html>