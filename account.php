<?php
session_start();
include_once('connection.php');
include_once('auth.php');
enforceSessionPolicies($conn);

if (!isset($_SESSION['userName'])) {
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
            <div class="alert-fail">You must be logged in to view account pages.</div>
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

/* Load the logged-in (self) user first */
$selfRow = null;
try {
    $stSelf = $conn->prepare("
        SELECT userID, userName, forename, surname, yearg, emailAddress, gender, role, description
        FROM tbluser
        WHERE userName = :uname
        LIMIT 1
    ");
    $stSelf->bindValue(':uname', $_SESSION['userName'], PDO::PARAM_STR);
    $stSelf->execute();
    $selfRow = $stSelf->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

if (!$selfRow) {
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
        <div class="page-title">Account</div>
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

$viewerRole = (int)$selfRow['role'];

$userRow = $selfRow;

if ($viewerRole >= 1 && isset($_GET['userID']) && ctype_digit((string)$_GET['userID']) && (int)$_GET['userID'] > 0) {
    $targetID = (int)$_GET['userID'];
    if ($targetID !== (int)$selfRow['userID']) {
        try {
            $stTarget = $conn->prepare("
                SELECT userID, userName, forename, surname, yearg, emailAddress, gender, role, description
                FROM tbluser
                WHERE userID = :uid
                LIMIT 1
            ");
            $stTarget->bindValue(':uid', $targetID, PDO::PARAM_INT);
            $stTarget->execute();
            $targetRow = $stTarget->fetch(PDO::FETCH_ASSOC);
            if ($targetRow) {
                $userRow = $targetRow;
            }
        } catch (PDOException $e) {}
    }
}

/* Helper: HTML escape */
function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* Role label for displayed user */
$roleLabel = ((int)$userRow['role'] === 2) ? 'Administrator/Coach' : (((int)$userRow['role'] === 1) ? 'Student' : 'Guest');

/* Install-order event names (INDIV only in PB table) */
$installOrder = [
    'Backstroke 50m','Backstroke 100m','Backstroke 200m',
    'Breastroke 50m','Breastroke 100m','Breastroke 200m',
    'Fly 50m','Fly 100m','Fly 200m',
    'Freestyle 50m','Freestyle 100m','Freestyle 200m','Freestyle 400m','Freestyle 800m','Freestyle 1500m',
    'IM 100m','IM 200m','IM 400m',
    'Freestyle Relay 200m','Medlay Relay 200m','Mixed Freestyle Relay 200m','Mixed Medlay Relay 200m',
    'Freestyle Relay 400m','Medlay Relay 400m','Mixed Freestyle Relay 400m','Mixed Medlay Relay 400m'
];

$events = [];
foreach ($installOrder as $evName) {
    if (stripos($evName, 'Relay') !== false) continue; // INDIV PBs only
    $events[$evName] = ['short' => null, 'short_date' => null, 'long' => null, 'long_date' => null];
}

/* Canonical time parser "MM:SS.hh" -> centiseconds */
function parseCanonicalToCentiseconds(string $t): ?int {
    $t = trim($t);
    if ($t === '') return null;
    if (strlen($t) !== 8 || $t[2] !== ':' || $t[5] !== '.') return null;
    $m = (int)substr($t, 0, 2);
    $s = (int)substr($t, 3, 2);
    $h = (int)substr($t, 6, 2);
    if ($s < 0 || $s > 59) return null;
    return ($m * 60 + $s) * 100 + $h;
}

/* Fetch INDIV results for displayed user and compute PBs */
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
} catch (PDOException $e) {}

foreach ($userResults as $r) {
    $ename = $r['eventName'];
    if (!isset($events[$ename])) continue;
    $cs = parseCanonicalToCentiseconds(trim($r['time'] ?? ''));
    if ($cs === null) continue;

    if ($r['eventCourse'] === 'S') {
        $curCs = $events[$ename]['short'] ? parseCanonicalToCentiseconds($events[$ename]['short']) : null;
        if ($curCs === null || $cs < $curCs) {
            $events[$ename]['short'] = $r['time'];
            $events[$ename]['short_date'] = $r['meetDate'];
        }
    } elseif ($r['eventCourse'] === 'L') {
        $curCs = $events[$ename]['long'] ? parseCanonicalToCentiseconds($events[$ename]['long']) : null;
        if ($curCs === null || $cs < $curCs) {
            $events[$ename]['long'] = $r['time'];
            $events[$ename]['long_date'] = $r['meetDate'];
        }
    }
}

/* Relay participation (meetID+eventID schema) */
$relayRows = [];
try {
    $sql = "SELECT 
                e.eventName, e.gender, tm.leg, rt.teamName, rt.totalTime, 
                m.meetDate, m.meetName, m.meetID
            FROM tblrelayTeamMember tm
            INNER JOIN tblrelayTeam rt 
                ON rt.meetID = tm.meetID AND rt.eventID = tm.eventID
            INNER JOIN tblevent e 
                ON e.eventID = tm.eventID
            INNER JOIN tblmeet m 
                ON m.meetID = tm.meetID
            WHERE tm.userID = :uid
            ORDER BY e.eventID ASC, tm.leg ASC";
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
    <title>Oundle School Swim Team - Account</title>
    <meta name="viewport" content="width=1024, initial-scale=1">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="main-content">
    <div class="page-title">Account</div>

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
                        <td>Year Group</td>
                        <td><?= ((int)$userRow['yearg'] === 0) ? '—' : h($userRow['yearg']) ?></td>
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

    <!-- PERSONAL BESTS (INDIV only) -->
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

    <!-- RELAY PARTICIPATION -->
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
                        <th>Date</th>
                        <th>Meet</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($relayRows)): ?>
                    <tr><td colspan="6">No relay teams recorded.</td></tr>
                <?php else: ?>
                    <?php foreach ($relayRows as $rr): ?>
                        <tr>
                            <td><?= h($rr['eventName']) ?></td>
                            <td><?= h($rr['teamName'] ?? '') ?></td>
                            <td><?= h($rr['totalTime'] ?? '') ?></td>
                            <td><?= (int)$rr['leg'] ?></td>
                            <td><?= h($rr['meetDate']) ?></td>
                            <td>
                                <a class="link" href="<?= 'meet.php?meetID=' . (int)$rr['meetID'] ?>">
                                    <?= h($rr['meetName']) ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>