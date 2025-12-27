<?php
session_start();
include_once('connection.php');
include_once('auth.php');
enforceSessionPolicies($conn);

/* Access control: only allow logged-in users with role >= 1 (exclude guests / role 0) */
if (!isset($_SESSION['role']) || (int)$_SESSION['role'] < 1) {
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
        <div class="page-title">
            Access Denied
        </div>
        <div class="section">
            <div class="alert-fail">
                Permision Error: You are not logged in or do not have the right privilage to access this page
            </div>
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

/* Fetch logged-in user record */
$userRow = null;
if (isset($_SESSION['userName'])) {
    $stmt = $conn->prepare("
        SELECT userID, userName, forename, surname, yearg, emailAddress, gender, role, description
        FROM tbluser
        WHERE userName = :uname
        LIMIT 1
    ");
    $stmt->bindValue(':uname', $_SESSION['userName'], PDO::PARAM_STR);
    $stmt->execute();
    $userRow = $stmt->fetch(PDO::FETCH_ASSOC);
}

/* Stop if user not found (data inconsistency) */
if (!$userRow) {
    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Oundle School Swim Team - Account</title>
    <meta name="viewport" content="width=1024, initial-scale=1">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="main-content">
        <div class="page-title">My Account</div>
        <div class="section">
            <div class="alert-fail">Account not found. Please log out and log back in.</div>
            <ul>
                <li><a href="login.php">Login</a></li>
                <li><a href="index.php">Return to Home</a></li>
            </ul>
        </div>
    </div>
</body>
</html>';
    exit();
}

/* Helper: HTML escape */
function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* Map role number to label */
$roleLabel = ((int)$userRow['role'] === 2) ? 'Administrator/Coach' : 'Student';

/* Static event ordering EXACTLY as in install.php (unique event names, sequence preserved) */
/* CHANGES FOR RELAY: display list still built from install order for INDIV events; relay shown separately */
$installOrder = [
    'Backstroke 50m','Backstroke 100m','Backstroke 200m',
    'Breastroke 50m','Breastroke 100m','Breastroke 200m',
    'Fly 50m','Fly 100m','Fly 200m',
    'Freestyle 50m','Freestyle 100m','Freestyle 200m','Freestyle 400m','Freestyle 800m','Freestyle 1500m',
    'IM 100m','IM 200m','IM 400m',
    'Freestyle Relay 200m','Medlay Relay 200m','Mixed Freestyle Relay 200m','Mixed Medlay Relay 200m',
    'Freestyle Relay 400m','Medlay Relay 400m','Mixed Freestyle Relay 400m','Mixed Medlay Relay 400m'
];

/* Initialise events array keyed by event name (all start blank), but for INDIV events only */
$events = [];
foreach ($installOrder as $evName) {
    // skip relay names for INDIV PB table:
    if (stripos($evName, 'Relay') !== false) continue;
    $events[$evName] = [
        'short' => null,
        'short_date' => null,
        'long' => null,
        'long_date' => null
    ];
}

/* Fetch all recorded INDIV results for this swimmer */
$userResults = [];
try {
    $resStmt = $conn->prepare("
        SELECT e.eventName, e.course AS eventCourse, e.eventType, s.time, m.meetDate
        FROM tblmeetEventHasSwimmer AS s
        INNER JOIN tblevent AS e ON s.eventID = e.eventID
        INNER JOIN tblmeet AS m ON s.meetID = m.meetID
        WHERE s.userID = :uid AND e.eventType = 'INDIV'
    ");
    $resStmt->bindValue(':uid', (int)$userRow['userID'], PDO::PARAM_INT);
    $resStmt->execute();
    $userResults = $resStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    /* Leave empty if failure */
}

/* Convert time string "M:SS.xx" or "SS.xx" to numeric seconds for comparing best (lowest) */
function parseSwimTime(string $t): ?float {
    $t = trim($t);
    if ($t === '') return null;
    if (strpos($t, ':') !== false) {
        $parts = explode(':', $t);
        if (count($parts) !== 2) return null;
        $min = (int)$parts[0];
        $sec = (float)str_replace(',', '.', $parts[1]);
        return $min * 60 + $sec;
    }
    $clean = str_replace(',', '.', $t);
    return is_numeric($clean) ? (float)$clean : null;
}

/* Update best times (lowest) for each INDIV event/course */
foreach ($userResults as $r) {
    $ename = $r['eventName'];
    if (!isset($events[$ename])) continue;
    $rawTime = trim($r['time'] ?? '');
    if ($rawTime === '') continue;
    $numeric = parseSwimTime($rawTime);
    if ($numeric === null) continue;

    if ($r['eventCourse'] === 'S') {
        if ($events[$ename]['short'] === null || parseSwimTime($events[$ename]['short']) > $numeric) {
            $events[$ename]['short'] = $rawTime;
            $events[$ename]['short_date'] = $r['meetDate'];
        }
    } elseif ($r['eventCourse'] === 'L') {
        if ($events[$ename]['long'] === null || parseSwimTime($events[$ename]['long']) > $numeric) {
            $events[$ename]['long'] = $rawTime;
            $events[$ename]['long_date'] = $r['meetDate'];
        }
    }
}

/* CHANGES FOR RELAY: fetch relay teams where this swimmer is a member */
$relayRows = [];
try {
    $sql = "SELECT e.eventName, e.gender, m.leg, rt.teamCode, rt.totalTime, rt.finalPlace, m.splitTime
            FROM tblrelayTeamMember m
            INNER JOIN tblrelayTeam rt ON rt.relayTeamID = m.relayTeamID
            INNER JOIN tblevent e ON e.eventID = rt.eventID
            WHERE m.userID = :uid
            ORDER BY e.eventID ASC, rt.teamCode ASC, m.leg ASC";
    $st = $conn->prepare($sql);
    $st->bindValue(':uid', (int)$userRow['userID'], PDO::PARAM_INT);
    $st->execute();
    $relayRows = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$eventNamesOrdered = array_filter($installOrder, fn($n) => stripos($n, 'Relay') === false);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Oundle School Swim Team - My Account</title>
    <meta name="viewport" content="width=1024, initial-scale=1">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="main-content">
    <div class="page-title">My Account</div>

    <!-- PERSONAL DETAILS -->
    <div class="section">
        <h2>Personal Details</h2>
        <div class="table-wrap">
            <table class="meets">
                <thead>
                    <tr><th>Field</th><th>Value</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Name</td>
                        <td><?= h($userRow['forename'] . ' ' . $userRow['surname']) ?></td>
                    </tr>
                    <tr>
                        <td>Email</td>
                        <td><?= h($userRow['emailAddress']) ?></td>
                    </tr>
                    <tr>
                        <td>Gender</td>
                        <td><?= h($userRow['gender']) ?></td>
                    </tr>
                    <tr>
                        <td>Role</td>
                        <td><?= h($roleLabel) ?></td>
                    </tr>
                    <tr>
                        <td>Description</td>
                        <td><?= $userRow['description'] !== '' ? nl2br(h($userRow['description'])) : '—' ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- PERSONAL BESTS (INDIV only) — dates removed as requested -->
    <div class="section">
        <h2>Personal Bests</h2>
        <div class="table-wrap">
            <table class="meets">
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Short Course PB</th>
                        <th>Long Course PB</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($eventNamesOrdered as $ename): ?>
                    <tr>
                        <td><?= h($ename) ?></td>
                        <td><?= h($events[$ename]['short'] ?? '') ?></td>
                        <td><?= h($events[$ename]['long'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- CHANGES FOR RELAY: show relay participations -->
    <div class="section">
        <h2>Relay Participation</h2>
        <div class="table-wrap">
            <table class="meets">
                <thead>
                    <tr>
                        <th>Relay Event</th>
                        <th>Team</th>
                        <th>Total Time</th>
                        <th>Leg</th>
                        <th>Split</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($relayRows)): ?>
                    <tr><td colspan="5">No relay teams recorded.</td></tr>
                <?php else: ?>
                    <?php foreach ($relayRows as $rr): ?>
                        <tr>
                            <td><?= h($rr['eventName']) ?></td>
                            <td><?= h($rr['teamCode']) ?></td>
                            <td><?= h($rr['totalTime'] ?? '') ?></td>
                            <td><?= (int)$rr['leg'] ?></td>
                            <td><?= h($rr['splitTime'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- EXTRA SUPPORT SECTION -->
    <div class="section">
        <h2>Need Help?</h2>
        <div class="extra-section">
            Any issues with your account or results?
            <br>
            <a href="tools.php">Contact the administrator</a>
        </div>
    </div>
</div>

</body>
</html>