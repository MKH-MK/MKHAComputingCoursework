<?php
session_start();
include_once('connection.php');
include_once('auth.php');
enforceSessionPolicies($conn);

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
        <div class="page-title">Access Denied</div>
        <div class="section">
            <div class="alert-fail">Permision Error: You are not logged in or do not have the right privilage to access this page</div>
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

/* Helpers */
function currentUrl(array $overrides = []): string {
    $q = array_merge($_GET, $overrides);
    return '?' . http_build_query($q);
}
function selected($cond) { return $cond ? 'selected' : ''; }

function courseLabel(string $c): string { return $c === 'L' ? 'Longcourse' : 'Shortcourse'; }

// School year: start 31 Aug (inclusive) to 30 Aug next year (inclusive)
function computeSchoolYearRange(DateTime $today, DateTimeZone $tz): array {
    $year = (int)$today->format('Y');
    $startThisYear = new DateTime("$year-08-31", $tz);
    if ($today >= $startThisYear) {
        $start = $startThisYear;
        $end = (clone $start)->modify('+1 year')->modify('-1 day'); // 30 Aug next year
    } else {
        $start = (new DateTime(($year - 1) . "-08-31", $tz));
        $end = (new DateTime("$year-08-30", $tz));
    }
    return [$start->format('Y-m-d'), $end->format('Y-m-d')];
}

/* Stroke options ordered by PK (eventID) */
function getStrokesByPK(PDO $conn, string $course, string $etype): array {
    $sql = "SELECT eventName, MIN(eventID) AS firstID
            FROM tblevent
            WHERE course = :c AND eventType = :t
            GROUP BY eventName
            ORDER BY firstID ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':c', $course);
    $stmt->bindValue(':t', $etype);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* Individual filters (GET) */
$indCourse = (isset($_GET['ind_course']) && in_array($_GET['ind_course'], ['L','S'], true)) ? $_GET['ind_course'] : 'L';
$indStroke = isset($_GET['ind_stroke']) ? trim($_GET['ind_stroke']) : '';
$indGender = (isset($_GET['ind_gender']) && in_array($_GET['ind_gender'], ['M','F'], true)) ? $_GET['ind_gender'] : 'M';
$indPeriod = (isset($_GET['ind_period']) && in_array($_GET['ind_period'], ['all','school'], true)) ? $_GET['ind_period'] : 'all';
$indYearg = (isset($_GET['ind_yearg']) && $_GET['ind_yearg'] !== '' && ctype_digit((string)$_GET['ind_yearg'])) ? (int)$_GET['ind_yearg'] : null;
$indPerPage = (isset($_GET['ind_per_page']) && in_array((int)$_GET['ind_per_page'], [20,35,50], true)) ? (int)$_GET['ind_per_page'] : 20;
$indPage = (isset($_GET['ind_page']) && ctype_digit((string)$_GET['ind_page']) && (int)$_GET['ind_page'] > 0) ? (int)$_GET['ind_page'] : 1;

/* Relay filters (GET) */
$rlCourse = (isset($_GET['rl_course']) && in_array($_GET['rl_course'], ['L','S'], true)) ? $_GET['rl_course'] : 'L';
$rlStroke = isset($_GET['rl_stroke']) ? trim($_GET['rl_stroke']) : '';
$rlGender = (isset($_GET['rl_gender']) && in_array($_GET['rl_gender'], ['M','F','MIX'], true)) ? $_GET['rl_gender'] : 'M';
$rlPeriod = (isset($_GET['rl_period']) && in_array($_GET['rl_period'], ['all','school'], true)) ? $_GET['rl_period'] : 'all';
$rlPerPage = (isset($_GET['rl_per_page']) && in_array((int)$_GET['rl_per_page'], [20,35,50], true)) ? (int)$_GET['rl_per_page'] : 20;
$rlPage = (isset($_GET['rl_page']) && ctype_digit((string)$_GET['rl_page']) && (int)$_GET['rl_page'] > 0) ? (int)$_GET['rl_page'] : 1;

/* Stroke options (ordered by PK) */
$indStrokeOpts = getStrokesByPK($conn, $indCourse, 'INDIV'); // array of ['eventName','firstID']
$rlStrokeOpts  = getStrokesByPK($conn, $rlCourse, 'RELAY');

/* Flatten names for validation */
$indStrokes = array_map(fn($r) => $r['eventName'], $indStrokeOpts);
$rlStrokes  = array_map(fn($r) => $r['eventName'], $rlStrokeOpts);

/* Date range for school year */
$tz = new DateTimeZone('Europe/London');
[$schoolStart, $schoolEnd] = computeSchoolYearRange(new DateTime('now', $tz), $tz);

/* Individual results */
$indResults = [];
$indTotalRows = 0;

if ($indStroke !== '' && in_array($indStroke, $indStrokes, true)) {
    // Count
    $countSql = "SELECT COUNT(*) 
                 FROM tblmeetEventHasSwimmer ms
                 INNER JOIN tblevent e ON e.eventID = ms.eventID
                 INNER INNER JOIN tbluser u ON u.userID = ms.userID
                 INNER JOIN tblmeet m ON m.meetID = ms.meetID
                 WHERE e.eventType = 'INDIV' 
                   AND e.course = :c 
                   AND e.eventName = :stroke
                   AND e.gender = :evgender
                   AND " . ($indGender === 'M' ? "u.gender IN ('M','MIX')" : "u.gender = 'F'") . "
                   " . ($indPeriod === 'school' ? "AND m.meetDate BETWEEN :ds AND :de" : "") . "
                   " . ($indYearg !== null ? "AND ms.yeargAtEvent = :yg" : "");
    $countSql = str_replace('INNER INNER JOIN', 'INNER JOIN', $countSql);
    $stCount = $conn->prepare($countSql);
    $stCount->bindValue(':c', $indCourse);
    $stCount->bindValue(':stroke', $indStroke);
    $stCount->bindValue(':evgender', $indGender);
    if ($indPeriod === 'school') {
        $stCount->bindValue(':ds', $schoolStart);
        $stCount->bindValue(':de', $schoolEnd);
    }
    if ($indYearg !== null) $stCount->bindValue(':yg', $indYearg, PDO::PARAM_INT);
    $stCount->execute();
    $indTotalRows = (int)$stCount->fetchColumn();

    $indTotalPages = max(1, (int)ceil($indTotalRows / $indPerPage));
    $indPage = min($indPage, $indTotalPages);
    $indOffset = ($indPage - 1) * $indPerPage;

    // List
    $listSql = "SELECT 
                    u.userID, u.forename, u.surname, ms.yeargAtEvent, ms.time, 
                    m.meetDate, m.meetName, m.meetID
                FROM tblmeetEventHasSwimmer ms
                INNER JOIN tblevent e ON e.eventID = ms.eventID
                INNER JOIN tbluser u ON u.userID = ms.userID
                INNER JOIN tblmeet m ON m.meetID = ms.meetID
                WHERE e.eventType = 'INDIV' 
                  AND e.course = :c 
                  AND e.eventName = :stroke
                  AND e.gender = :evgender
                  AND " . ($indGender === 'M' ? "u.gender IN ('M','MIX')" : "u.gender = 'F'") . "
                  " . ($indPeriod === 'school' ? "AND m.meetDate BETWEEN :ds AND :de" : "") . "
                  " . ($indYearg !== null ? "AND ms.yeargAtEvent = :yg" : "") . "
                ORDER BY 
                  (CAST(SUBSTRING(ms.time, 1, 2) AS UNSIGNED) * 6000 +
                   CAST(SUBSTRING(ms.time, 4, 2) AS UNSIGNED) * 100 +
                   CAST(SUBSTRING(ms.time, 7, 2) AS UNSIGNED)) ASC,
                  m.meetDate ASC
                LIMIT :limit OFFSET :offset";
    $stList = $conn->prepare($listSql);
    $stList->bindValue(':c', $indCourse);
    $stList->bindValue(':stroke', $indStroke);
    $stList->bindValue(':evgender', $indGender);
    if ($indPeriod === 'school') {
        $stList->bindValue(':ds', $schoolStart);
        $stList->bindValue(':de', $schoolEnd);
    }
    if ($indYearg !== null) $stList->bindValue(':yg', $indYearg, PDO::PARAM_INT);
    $stList->bindValue(':limit', $indPerPage, PDO::PARAM_INT);
    $stList->bindValue(':offset', $indOffset, PDO::PARAM_INT);
    $stList->execute();
    $indResults = $stList->fetchAll(PDO::FETCH_ASSOC);
}

/* Relay results */
$rlResults = [];
$rlTotalRows = 0;
if ($rlStroke !== '' && in_array($rlStroke, $rlStrokes, true)) {
    $countSql = "SELECT COUNT(*) 
                 FROM tblrelayTeam rt
                 INNER JOIN tblevent e ON e.eventID = rt.eventID
                 INNERJOIN tblmeet m ON m.meetID = rt.meetID
                 WHERE e.eventType = 'RELAY'
                   AND e.course = :c
                   AND e.eventName = :stroke
                   AND e.gender = :evgender
                   " . ($rlPeriod === 'school' ? "AND m.meetDate BETWEEN :ds AND :de" : "");
    $countSql = str_replace('INNERJOIN', 'INNER JOIN', $countSql);
    $stCount = $conn->prepare($countSql);
    $stCount->bindValue(':c', $rlCourse);
    $stCount->bindValue(':stroke', $rlStroke);
    $stCount->bindValue(':evgender', $rlGender);
    if ($rlPeriod === 'school') {
        $stCount->bindValue(':ds', $schoolStart);
        $stCount->bindValue(':de', $schoolEnd);
    }
    $stCount->execute();
    $rlTotalRows = (int)$stCount->fetchColumn();

    $rlTotalPages = max(1, (int)ceil($rlTotalRows / $rlPerPage));
    $rlPage = min($rlPage, $rlTotalPages);
    $rlOffset = ($rlPage - 1) * $rlPerPage;

    $listSql = "SELECT 
                    rt.teamName, rt.totalTime, m.meetDate, m.meetName, m.meetID
                FROM tblrelayTeam rt
                INNER JOIN tblevent e ON e.eventID = rt.eventID
                INNER JOIN tblmeet m ON m.meetID = rt.meetID
                WHERE e.eventType = 'RELAY'
                  AND e.course = :c
                  AND e.eventName = :stroke
                  AND e.gender = :evgender
                  " . ($rlPeriod === 'school' ? "AND m.meetDate BETWEEN :ds AND :de" : "") . "
                ORDER BY 
                  (CAST(SUBSTRING(rt.totalTime, 1, 2) AS UNSIGNED) * 6000 +
                   CAST(SUBSTRING(rt.totalTime, 4, 2) AS UNSIGNED) * 100 +
                   CAST(SUBSTRING(rt.totalTime, 7, 2) AS UNSIGNED)) ASC,
                  m.meetDate ASC
                LIMIT :limit OFFSET :offset";
    $stList = $conn->prepare($listSql);
    $stList->bindValue(':c', $rlCourse);
    $stList->bindValue(':stroke', $rlStroke);
    $stList->bindValue(':evgender', $rlGender);
    if ($rlPeriod === 'school') {
        $stList->bindValue(':ds', $schoolStart);
        $stList->bindValue(':de', $schoolEnd);
    }
    $stList->bindValue(':limit', $rlPerPage, PDO::PARAM_INT);
    $stList->bindValue(':offset', $rlOffset, PDO::PARAM_INT);
    $stList->execute();
    $rlResults = $stList->fetchAll(PDO::FETCH_ASSOC);
}

/* Titles */
$indTitle = ($indStroke !== '' ? ($indStroke . ' – ' . courseLabel($indCourse)) : 'Select filters and Search');
$rlTitle  = ($rlStroke !== '' ? ($rlStroke . ' – ' . courseLabel($rlCourse)) : 'Select filters and Search');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Oundle School Swim Team - Records</title>
    <meta name="viewport" content="width=1024, initial-scale=1">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="main-content">
    <div class="page-title">Records</div>

    <!-- INDIVIDUAL RECORDS -->
    <div class="section">
        <h2>Individual Records</h2>
        <form method="get" class="form-section form-section--wide">
            <div class="form-row">
                <label class="check-item">Course</label>
                <select name="ind_course" class="input">
                    <option value="L" <?= selected($indCourse==='L') ?>>Long</option>
                    <option value="S" <?= selected($indCourse==='S') ?>>Short</option>
                </select>
            </div>

            <div class="form-row">
                <label class="check-item">Stroke</label>
                <select name="ind_stroke" class="input">
                    <option value="">Please Choose</option>
                    <?php foreach ($indStrokeOpts as $opt): ?>
                        <option value="<?= htmlspecialchars($opt['eventName']) ?>" <?= selected($indStroke===$opt['eventName']) ?>>
                            <?= htmlspecialchars($opt['eventName']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <label class="check-item">Gender</label>
                <select name="ind_gender" class="input">
                    <option value="M" <?= selected($indGender==='M') ?>>Male (Open)</option>
                    <option value="F" <?= selected($indGender==='F') ?>>Female</option>
                </select>
            </div>

            <div class="form-row">
                <label class="check-item">Period</label>
                <select name="ind_period" class="input">
                    <option value="all" <?= selected($indPeriod==='all') ?>>All Time</option>
                    <option value="school" <?= selected($indPeriod==='school') ?>>School Year</option>
                </select>
            </div>

            <div class="form-row">
                <label class="check-item">Year Group</label>
                <select name="ind_yearg" class="input">
                    <option value="" <?= selected($indYearg===null) ?>>Open (7–13)</option>
                    <?php for ($yg=7; $yg<=13; $yg++): ?>
                        <option value="<?= $yg ?>" <?= selected($indYearg===$yg) ?>><?= $yg ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <!-- Always start on page 1 for each search -->
            <input type="hidden" name="ind_page" value="1">

            <div class="form-row form-row--center">
                <button type="submit" class="btn">Search</button>
                <a href="record.php" class="btn btn-reset">Reset</a>
            </div>
        </form>

        <div class="table-wrap">
            <h3 class="event-subtitle"><?= htmlspecialchars($indTitle) ?></h3>
            <table class="meets">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Time</th>
                        <th>Year Group</th>
                        <th>Date</th>
                        <th>Meet</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($indStroke === '' || empty($indResults)): ?>
                    <tr><td colspan="5">No results for this selection.</td></tr>
                <?php else: ?>
                    <?php foreach ($indResults as $row): ?>
                        <tr>
                            <td>
                                <a class="link" href="<?= 'account.php?userID=' . (int)$row['userID'] ?>">
                                    <?= htmlspecialchars($row['forename'] . ' ' . $row['surname']) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($row['time']) ?></td>
                            <td><?= (int)$row['yeargAtEvent'] ?></td>
                            <td><?= htmlspecialchars($row['meetDate']) ?></td>
                            <td>
                                <a class="link" href="<?= 'meet.php?meetID=' . (int)$row['meetID'] ?>">
                                    <?= htmlspecialchars($row['meetName']) ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <?php if ($indTotalRows > $indPerPage): ?>
                <?php
                    $indTotalPages = max(1, (int)ceil($indTotalRows / $indPerPage));
                    $prevPage = max(1, $indPage - 1);
                    $nextPage = min($indTotalPages, $indPage + 1);
                ?>
                <div class="pagination">
                    <a href="<?= currentUrl(['ind_page' => $prevPage]) ?>">&#8592; Prev</a>
                    <span class="current"><?= $indPage ?> / <?= $indTotalPages ?></span>
                    <a href="<?= currentUrl(['ind_page' => $nextPage]) ?>">Next &#8594;</a>
                </div>
            <?php endif; ?>
            <div class="per-page">
                <span class="per-page-label">Entries:</span>
                <a class="btn <?= $indPerPage===20?'btn-active':'' ?>" href="<?= currentUrl(['ind_per_page'=>20, 'ind_page'=>1]) ?>">20</a>
                <a class="btn <?= $indPerPage===35?'btn-active':'' ?>" href="<?= currentUrl(['ind_per_page'=>35, 'ind_page'=>1]) ?>">35</a>
                <a class="btn <?= $indPerPage===50?'btn-active':'' ?>" href="<?= currentUrl(['ind_per_page'=>50, 'ind_page'=>1]) ?>">50</a>
            </div>
        </div>
    </div>

    <!-- RELAY RECORDS -->
    <div class="section">
        <h2>Relay Records</h2>
        <form method="get" class="form-section form-section--wide">
            <div class="form-row">
                <label class="check-item">Course</label>
                <select name="rl_course" class="input">
                    <option value="L" <?= selected($rlCourse==='L') ?>>Long</option>
                    <option value="S" <?= selected($rlCourse==='S') ?>>Short</option>
                </select>
            </div>

            <div class="form-row">
                <label class="check-item">Relay Event</label>
                <select name="rl_stroke" class="input">
                    <option value="">Please Choose</option>
                    <?php foreach ($rlStrokeOpts as $opt): ?>
                        <option value="<?= htmlspecialchars($opt['eventName']) ?>" <?= selected($rlStroke===$opt['eventName']) ?>>
                            <?= htmlspecialchars($opt['eventName']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <label class="check-item">Gender</label>
                <select name="rl_gender" class="input">
                    <option value="M" <?= selected($rlGender==='M') ?>>Male</option>
                    <option value="F" <?= selected($rlGender==='F') ?>>Female</option>
                    <option value="MIX" <?= selected($rlGender==='MIX') ?>>Mixed</option>
                </select>
            </div>

            <div class="form-row">
                <label class="check-item">Period</label>
                <select name="rl_period" class="input">
                    <option value="all" <?= selected($rlPeriod==='all') ?>>All Time</option>
                    <option value="school" <?= selected($rlPeriod==='school') ?>>School Year</option>
                </select>
            </div>

            <!-- Always start on page 1 for each search -->
            <input type="hidden" name="rl_page" value="1">

            <div class="form-row form-row--center">
                <button type="submit" class="btn">Search</button>
                <a href="<?= currentUrl(['rl_stroke'=>'', 'rl_gender'=>'M', 'rl_period'=>'all', 'rl_page'=>1, 'rl_per_page'=>20]) ?>" class="btn btn-reset">Reset</a>
            </div>
        </form>

        <div class="table-wrap">
            <h3 class="event-subtitle"><?= htmlspecialchars($rlTitle) ?></h3>
            <table class="meets">
                <thead>
                    <tr>
                        <th>Team</th>
                        <th>Total Time</th>
                        <th>Date</th>
                        <th>Meet</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($rlStroke === '' || empty($rlResults)): ?>
                    <tr><td colspan="4">No results for this selection.</td></tr>
                <?php else: ?>
                    <?php foreach ($rlResults as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['teamName'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['totalTime'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['meetDate']) ?></td>
                            <td>
                                <a class="link" href="<?= 'meet.php?meetID=' . (int)$row['meetID'] ?>">
                                    <?= htmlspecialchars($row['meetName']) ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <?php if ($rlTotalRows > $rlPerPage): ?>
                <?php
                    $rlTotalPages = max(1, (int)ceil($rlTotalRows / $rlPerPage));
                    $prevPage = max(1, $rlPage - 1);
                    $nextPage = min($rlTotalPages, $rlPage + 1);
                ?>
                <div class="pagination">
                    <a href="<?= currentUrl(['rl_page' => $prevPage]) ?>">&#8592; Prev</a>
                    <span class="current"><?= $rlPage ?> / <?= $rlTotalPages ?></span>
                    <a href="<?= currentUrl(['rl_page' => $nextPage]) ?>">Next &#8594;</a>
                </div>
            <?php endif; ?>
            <div class="per-page">
                <span class="per-page-label">Entries:</span>
                <a class="btn <?= $rlPerPage===20?'btn-active':'' ?>" href="<?= currentUrl(['rl_per_page'=>20, 'rl_page'=>1]) ?>">20</a>
                <a class="btn <?= $rlPerPage===35?'btn-active':'' ?>" href="<?= currentUrl(['rl_per_page'=>35, 'rl_page'=>1]) ?>">35</a>
                <a class="btn <?= $rlPerPage===50?'btn-active':'' ?>" href="<?= currentUrl(['rl_per_page'=>50, 'rl_page'=>1]) ?>">50</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>