<?php
session_start();
include_once('connection.php');
include_once('auth.php');
enforceSessionPolicies($conn);


/* CHANGES FOR RELAY: records page supports INDIV and RELAY by selecting event from tblevent (with eventType set).
   Follow existing patterns: toolbars, filters, pagination like admin_meetList.php.
*/

// Params and defaults
$perPage = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], [20,35,50], true) ? (int)$_GET['per_page'] : 20;
$page = isset($_GET['page']) && ctype_digit((string)$_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;

$course = isset($_GET['course']) && in_array($_GET['course'], ['L','S'], true) ? $_GET['course'] : '';
$yearg = isset($_GET['yearg']) && ctype_digit((string)$_GET['yearg']) ? (int)$_GET['yearg'] : 0;
$eventID = isset($_GET['eventID']) && ctype_digit((string)$_GET['eventID']) ? (int)$_GET['eventID'] : 0;

// Build event options (user chooses first)
$events = [];
try {
    $stmt = $conn->prepare("SELECT eventID, eventName, course, gender, eventType FROM tblevent ORDER BY eventID ASC");
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

function currentUrl(array $overrides = []): string {
    $q = array_merge($_GET, $overrides);
    return '?' . http_build_query($q);
}

// Build WHERE based on selections
$whereParts = [];
$params = [];

if ($eventID) {
    // detect type
    $etypeStmt = $conn->prepare("SELECT eventType, course FROM tblevent WHERE eventID = :e LIMIT 1");
    $etypeStmt->bindValue(':e', $eventID, PDO::PARAM_INT);
    $etypeStmt->execute();
    $etypeRow = $etypeStmt->fetch(PDO::FETCH_ASSOC);
    $selectedType = $etypeRow['eventType'] ?? 'INDIV';
    $selectedCourse = $etypeRow['course'] ?? null;

    // validate course filter: if user chose a course, it must match event's course
    if ($course && $selectedCourse && $course !== $selectedCourse) {
        // mismatch -> no results
        $whereParts[] = '1=0';
    }

    if ($selectedType === 'INDIV') {
        // Query individual results
        $countSql = "SELECT COUNT(*) 
                     FROM tblmeetEventHasSwimmer s
                     INNER JOIN tbluser u ON u.userID = s.userID
                     WHERE s.eventID = :e" . ($yearg ? " AND u.yearg = :y" : "");
        $listSql = "SELECT u.forename, u.surname, u.yearg, s.time, s.userID
                    FROM tblmeetEventHasSwimmer s
                    INNER JOIN tbluser u ON u.userID = s.userID
                    WHERE s.eventID = :e" . ($yearg ? " AND u.yearg = :y" : "") . "
                    ORDER BY s.time ASC"; // simple fastest-first sort

        $countStmt = $conn->prepare($countSql);
        $countStmt->bindValue(':e', $eventID, PDO::PARAM_INT);
        if ($yearg) $countStmt->bindValue(':y', $yearg, PDO::PARAM_INT);
        $countStmt->execute();
        $totalRows = (int)$countStmt->fetchColumn();

        $totalPages = max(1, (int)ceil($totalRows / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $listStmt = $conn->prepare($listSql . " LIMIT :limit OFFSET :offset");
        $listStmt->bindValue(':e', $eventID, PDO::PARAM_INT);
        if ($yearg) $listStmt->bindValue(':y', $yearg, PDO::PARAM_INT);
        $listStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $listStmt->execute();
        $rows = $listStmt->fetchAll(PDO::FETCH_ASSOC);

    } else {
        // RELAY results: list teams and their total times, filter by year group if any member matches (simple: any leg in yearg)
        $countSql = "SELECT COUNT(DISTINCT rt.relayTeamID)
                     FROM tblrelayTeam rt
                     INNER JOIN tblrelayTeamMember m ON m.relayTeamID = rt.relayTeamID
                     INNER JOIN tbluser u ON u.userID = m.userID
                     WHERE rt.eventID = :e" . ($yearg ? " AND u.yearg = :y" : "");
        $listSql = "SELECT rt.relayTeamID, rt.teamCode, rt.totalTime,
                           GROUP_CONCAT(CONCAT(u.forename,' ',u.surname,' (Y',u.yearg,', leg ',m.leg,')') ORDER BY m.leg SEPARATOR '; ') AS members
                    FROM tblrelayTeam rt
                    INNER JOIN tblrelayTeamMember m ON m.relayTeamID = rt.relayTeamID
                    INNER JOIN tbluser u ON u.userID = m.userID
                    WHERE rt.eventID = :e" . ($yearg ? " AND u.yearg = :y" : "") . "
                    GROUP BY rt.relayTeamID
                    ORDER BY rt.totalTime ASC, rt.teamCode ASC";

        $countStmt = $conn->prepare($countSql);
        $countStmt->bindValue(':e', $eventID, PDO::PARAM_INT);
        if ($yearg) $countStmt->bindValue(':y', $yearg, PDO::PARAM_INT);
        $countStmt->execute();
        $totalRows = (int)$countStmt->fetchColumn();

        $totalPages = max(1, (int)ceil($totalRows / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $listStmt = $conn->prepare($listSql . " LIMIT :limit OFFSET :offset");
        $listStmt->bindValue(':e', $eventID, PDO::PARAM_INT);
        if ($yearg) $listStmt->bindValue(':y', $yearg, PDO::PARAM_INT);
        $listStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $listStmt->execute();
        $rows = $listStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} else {
    $rows = [];
    $totalRows = 0;
    $totalPages = 1;
    $page = 1;
    $offset = 0;
}

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

    <div class="section">
        <h2>Find Records</h2>

        <form method="get" class="toolbar">
            <div class="toolbar-left search-bar">
                <!-- Event select -->
                <select name="eventID" class="search-order" required>
                    <option value="">Select Event</option>
                    <?php foreach ($events as $ev): ?>
                        <option value="<?= (int)$ev['eventID'] ?>" <?= $eventID===(int)$ev['eventID']?'selected':'' ?>>
                            <?= htmlspecialchars($ev['eventName'] . ' (' . $ev['gender'] . ') ' . ($ev['eventType']==='RELAY'?'[Relay]':'')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <!-- Course display (informational only; event has fixed course) -->
                <select name="course" class="search-order">
                    <option value="">Course (auto)</option>
                    <option value="L" <?= $course==='L'?'selected':'' ?>>Longcourse</option>
                    <option value="S" <?= $course==='S'?'selected':'' ?>>Shortcourse</option>
                </select>

                <!-- Year group filter -->
                <input type="number" name="yearg" class="search-order" min="7" max="13" value="<?= $yearg ?: '' ?>" placeholder="Year Group (optional)">
            </div>

            <div class="toolbar-right filters-group">
                <select name="per_page" class="search-order">
                    <option value="20" <?= $perPage===20?'selected':'' ?>>20</option>
                    <option value="35" <?= $perPage===35?'selected':'' ?>>35</option>
                    <option value="50" <?= $perPage===50?'selected':'' ?>>50</option>
                </select>
                <button type="submit" class="btn">Apply</button>
                <a href="records.php" class="btn btn-reset">Reset</a>
            </div>
        </form>

        <div class="table-wrap">
            <table class="meets">
                <thead>
                    <?php
                    // Headings depend on event type if selected
                    $headRelay = ['Team', 'Total Time', 'Members'];
                    $headIndiv = ['Surname', 'Forename', 'Year', 'Time'];
                    ?>
                    <tr>
                        <?php if ($eventID && isset($etypeRow) && ($etypeRow['eventType'] ?? 'INDIV') === 'RELAY'): ?>
                            <th><?= htmlspecialchars('Team') ?></th>
                            <th><?= htmlspecialchars('Total Time') ?></th>
                            <th><?= htmlspecialchars('Members') ?></th>
                        <?php else: ?>
                            <th><?= htmlspecialchars('Surname') ?></th>
                            <th><?= htmlspecialchars('Forename') ?></th>
                            <th><?= htmlspecialchars('Year') ?></th>
                            <th><?= htmlspecialchars('Time') ?></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="4">No results to show. Select an event above.</td></tr>
                <?php else: ?>
                    <?php if ($eventID && isset($etypeRow) && ($etypeRow['eventType'] ?? 'INDIV') === 'RELAY'): ?>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['teamCode'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['totalTime'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['members'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['surname']) ?></td>
                                <td><?= htmlspecialchars($r['forename']) ?></td>
                                <td><?= (int)$r['yearg'] ?></td>
                                <td><?= htmlspecialchars($r['time']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <?php if (($totalPages ?? 1) > 1): ?>
                <div class="pagination">
                    <?php
                    $prevPage = max(1, $page - 1);
                    $nextPage = min($totalPages, $page + 1);
                    ?>
                    <a href="<?= currentUrl(['page' => $prevPage]) ?>">&#8592; Prev</a>
                    <span class="current"><?= $page ?> / <?= $totalPages ?></span>
                    <a href="<?= currentUrl(['page' => $nextPage]) ?>">Next &#8594;</a>
                </div>
            <?php endif; ?>

            <div class="per-page">
                <span class="per-page-label">Entries:</span>
                <a class="btn <?= $perPage===20?'btn-active':'' ?>" href="<?= currentUrl(['per_page'=>20, 'page'=>1]) ?>">20</a>
                <a class="btn <?= $perPage===35?'btn-active':'' ?>" href="<?= currentUrl(['per_page'=>35, 'page'=>1]) ?>">35</a>
                <a class="btn <?= $perPage===50?'btn-active':'' ?>" href="<?= currentUrl(['per_page'=>50, 'page'=>1]) ?>">50</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>