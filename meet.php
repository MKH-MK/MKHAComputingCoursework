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

/* Helpers */
function currentUrl(array $overrides = []): string {
    $q = array_merge($_GET, $overrides);
    return '?' . http_build_query($q);
}

/* Meet list filters (reuse admin_meetList patterns) */
$validSort = ['id' => 'meetID', 'name' => 'meetName', 'date' => 'meetDate'];
$sort = isset($_GET['sort']) && array_key_exists($_GET['sort'], $validSort) ? $_GET['sort'] : 'id';

if ($sort === 'name') {
    $orderSql = 'ORDER BY meetName ASC, meetID DESC';
} elseif ($sort === 'date') {
    $orderSql = 'ORDER BY meetDate DESC, meetID DESC';
} else {
    $orderSql = 'ORDER BY meetID DESC';
}

$perPage = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], [20,35,50], true) ? (int)$_GET['per_page'] : 20;
$page = isset($_GET['page']) && ctype_digit((string)$_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;

$courseFilters = [];
if (isset($_GET['course_L']) || isset($_GET['course_S'])) {
    if (isset($_GET['course_L'])) $courseFilters[] = 'L';
    if (isset($_GET['course_S'])) $courseFilters[] = 'S';
} else {
    $courseFilters = ['L', 'S'];
}

$externalFilters = [];
if (isset($_GET['ext_Y']) || isset($_GET['ext_N'])) {
    if (isset($_GET['ext_Y'])) $externalFilters[] = 'Y';
    if (isset($_GET['ext_N'])) $externalFilters[] = 'N';
} else {
    $externalFilters = ['Y', 'N'];
}

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

function buildInClause($prefix, $values) {
    $placeholders = [];
    $params = [];
    foreach ($values as $i => $v) {
        $ph = ':' . $prefix . $i;
        $placeholders[] = $ph;
        $params[$ph] = $v;
    }
    return [$placeholders, $params];
}

/* WHERE for meet list */
$where = [];
$params = [];

if (!empty($courseFilters)) {
    [$phs, $p] = buildInClause('c', $courseFilters);
    $where[] = 'course IN (' . implode(',', $phs) . ')';
    $params = array_merge($params, $p);
} else {
    $where[] = '1=0';
}

if (!empty($externalFilters)) {
    [$phs, $p] = buildInClause('e', $externalFilters);
    $where[] = 'external IN (' . implode(',', $phs) . ')';
    $params = array_merge($params, $p);
} else {
    $where[] = '1=0';
}

if ($q !== '') {
    $where[] = 'meetName LIKE :q';
    $params[':q'] = '%' . $q . '%';
}

$whereSql = empty($where) ? '' : ('WHERE ' . implode(' AND ', $where));

/* Total + list */
$countSql = "SELECT COUNT(*) FROM tblmeet $whereSql";
$stmt = $conn->prepare($countSql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->execute();
$totalRows = (int)$stmt->fetchColumn();

$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$listSql = "SELECT meetID, meetName, meetDate, external, course FROM tblmeet $whereSql $orderSql LIMIT :limit OFFSET :offset";
$stmt = $conn->prepare($listSql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$meets = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Selected meet to view */
$meetID = isset($_GET['meetID']) && ctype_digit((string)$_GET['meetID']) ? (int)$_GET['meetID'] : 0;
$meet = null;
$meetEvents = [];
$indivEvents = [];
$relayEvents = [];

if ($meetID) {
    // Fetch meet details
    $st = $conn->prepare("SELECT meetID, meetName, meetDate, meetInfo, external, course FROM tblmeet WHERE meetID = :id LIMIT 1");
    $st->bindValue(':id', $meetID, PDO::PARAM_INT);
    $st->execute();
    $meet = $st->fetch(PDO::FETCH_ASSOC);

    // Events present in this meet (only these are filterable)
    $sql = "SELECT e.eventID, e.eventName, e.gender, e.eventType
            FROM tblevent e
            INNER JOIN tblmeetHasEvent me ON me.eventID = e.eventID
            WHERE me.meetID = :id
            ORDER BY e.eventID ASC";
    $st2 = $conn->prepare($sql);
    $st2->bindValue(':id', $meetID, PDO::PARAM_INT);
    $st2->execute();
    $meetEvents = $st2->fetchAll(PDO::FETCH_ASSOC);

    foreach ($meetEvents as $ev) {
        if ($ev['eventType'] === 'INDIV') $indivEvents[] = $ev;
        else $relayEvents[] = $ev;
    }
}

/* Filters inside selected meet view */
$filterEventIDIndiv = isset($_GET['ev_indiv']) && ctype_digit((string)$_GET['ev_indiv']) ? (int)$_GET['ev_indiv'] : 0;
$filterEventIDRelay = isset($_GET['ev_relay']) && ctype_digit((string)$_GET['ev_relay']) ? (int)$_GET['ev_relay'] : 0;

/* User gender filters for individual results (M/F/MIX; default all) */
$userGenderFilters = [];
if (isset($_GET['g_M']) || isset($_GET['g_F']) || isset($_GET['g_MIX'])) {
    if (isset($_GET['g_M'])) $userGenderFilters[] = 'M';
    if (isset($_GET['g_F'])) $userGenderFilters[] = 'F';
    if (isset($_GET['g_MIX'])) $userGenderFilters[] = 'MIX';
} else {
    $userGenderFilters = ['M','F','MIX'];
}

/* Event gender filter for relay (M/F/MIX; default all) */
$relayGenderFilters = [];
if (isset($_GET['rg_M']) || isset($_GET['rg_F']) || isset($_GET['rg_MIX'])) {
    if (isset($_GET['rg_M'])) $relayGenderFilters[] = 'M';
    if (isset($_GET['rg_F'])) $relayGenderFilters[] = 'F';
    if (isset($_GET['rg_MIX'])) $relayGenderFilters[] = 'MIX';
} else {
    $relayGenderFilters = ['M','F','MIX'];
}

/* Fetch individual results */
$indivRows = [];
if ($meetID) {
    $whereParts = ["ms.meetID = :m", "e.eventType = 'INDIV'"];
    $binds = [':m' => $meetID];

    // Event filter must be one present in this meet
    if ($filterEventIDIndiv) {
        $presentIndivIds = array_map(fn($x) => (int)$x['eventID'], $indivEvents);
        if (in_array($filterEventIDIndiv, $presentIndivIds, true)) {
            $whereParts[] = "ms.eventID = :ev";
            $binds[':ev'] = $filterEventIDIndiv;
        } else {
            $whereParts[] = "1=0"; // invalid filter -> no results
        }
    }

    // User gender filters
    if (!empty($userGenderFilters)) {
        [$phs, $p] = buildInClause('ug', $userGenderFilters);
        $whereParts[] = 'u.gender IN (' . implode(',', $phs) . ')';
        $binds = array_merge($binds, $p);
    } else {
        $whereParts[] = '1=0';
    }

    // CHANGE: select and use yeargAtEvent (no fallbacks)
    $sql = "SELECT u.forename, u.surname, ms.yeargAtEvent, u.gender AS userGender,
                   e.eventID, e.eventName, e.gender AS eventGender, ms.time
            FROM tblmeetEventHasSwimmer ms
            INNER JOIN tblevent e ON e.eventID = ms.eventID
            INNER JOIN tbluser u ON u.userID = ms.userID
            WHERE " . implode(' AND ', $whereParts) . "
            ORDER BY e.eventID ASC, u.surname ASC, u.forename ASC, ms.time ASC";
    $st = $conn->prepare($sql);
    foreach ($binds as $k => $v) {
        $st->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $st->execute();
    $indivRows = $st->fetchAll(PDO::FETCH_ASSOC);
}

/* Fetch relay teams + members */
$relayTeams = [];
if ($meetID) {
    $whereParts = ["rt.meetID = :m"];
    $binds = [':m' => $meetID];

    // Relay event filter must be present
    if ($filterEventIDRelay) {
        $presentRelayIds = array_map(fn($x) => (int)$x['eventID'], $relayEvents);
        if (in_array($filterEventIDRelay, $presentRelayIds, true)) {
            $whereParts[] = "rt.eventID = :ev";
            $binds[':ev'] = $filterEventIDRelay;
        } else {
            $whereParts[] = "1=0";
        }
    }

    // Event gender filter
    if (!empty($relayGenderFilters)) {
        [$phs, $p] = buildInClause('rg', $relayGenderFilters);
        $whereParts[] = 'e.gender IN (' . implode(',', $phs) . ')';
        $binds = array_merge($binds, $p);
    } else {
        $whereParts[] = '1=0';
    }

    $sql = "SELECT rt.eventID, e.eventName, e.gender, rt.teamName, rt.totalTime
            FROM tblrelayTeam rt
            INNER JOIN tblevent e ON e.eventID = rt.eventID
            WHERE " . implode(' AND ', $whereParts) . "
            ORDER BY e.eventID ASC, rt.teamName ASC";
    $st = $conn->prepare($sql);
    foreach ($binds as $k => $v) {
        $st->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $st->execute();
    $relayTeams = $st->fetchAll(PDO::FETCH_ASSOC);
}

/* Helper for checkbox attributes */
function checked($cond) { return $cond ? 'checked' : ''; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Oundle School Swim Team - Meets</title>
    <meta name="viewport" content="width=1024, initial-scale=1">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="main-content">
    <div class="page-title">Meets</div>

    <!-- MEET PICKER (filters reused from admin list) -->
    <div class="section">
        <h2>Find Meets</h2>

        <form method="get" class="toolbar">
            <div class="toolbar-left search-bar">
                <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search meet name...">
                <select name="sort" class="search-order">
                    <option value="id" <?= $sort==='id'?'selected':'' ?>>Date Created</option>
                    <option value="date" <?= $sort==='date'?'selected':'' ?>>Meet Date</option>
                    <option value="name" <?= $sort==='name'?'selected':'' ?>>Alphabetical</option>
                </select>

                <label class="check-item">
                    <input type="checkbox" name="course_L" value="1" <?= checked(in_array('L', $courseFilters, true)) ?>> Longcourse
                </label>
                <label class="check-item">
                    <input type="checkbox" name="course_S" value="1" <?= checked(in_array('S', $courseFilters, true)) ?>> Shortcourse
                </label>
                <label class="check-item">
                    <input type="checkbox" name="ext_Y" value="1" <?= checked(in_array('Y', $externalFilters, true)) ?>> External
                </label>
                <label class="check-item">
                    <input type="checkbox" name="ext_N" value="1" <?= checked(in_array('N', $externalFilters, true)) ?>> School
                </label>
            </div>

            <div class="toolbar-right filters-group">
                <select name="per_page" class="search-order">
                    <option value="20" <?= $perPage===20?'selected':'' ?>>20</option>
                    <option value="35" <?= $perPage===35?'selected':'' ?>>35</option>
                    <option value="50" <?= $perPage===50?'selected':'' ?>>50</option>
                </select>
                <button type="submit" class="btn">Apply</button>
                <a href="meet.php" class="btn btn-reset">Reset</a>
            </div>
        </form>

        <div class="table-wrap">
            <table class="meets">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Date</th>
                        <th>Course</th>
                        <th>External</th>
                        <th class="col-actions-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($meets)): ?>
                    <tr><td colspan="5">No meets found.</td></tr>
                <?php else: ?>
                    <?php foreach ($meets as $m): ?>
                        <tr>
                            <td><?= htmlspecialchars($m['meetName']) ?></td>
                            <td><?= htmlspecialchars($m['meetDate']) ?></td>
                            <td><?= $m['course']==='L' ? 'Longcourse' : 'Shortcourse' ?></td>
                            <td><?= $m['external']==='Y' ? 'Yes' : 'No' ?></td>
                            <td class="col-actions-right">
                                <div class="action-buttons">
                                    <a class="btn" href="<?= currentUrl(['meetID' => (int)$m['meetID'], 'page' => 1]) ?>">View</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <?php if ($totalPages > 1): ?>
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

    <!-- SELECTED MEET VIEW -->
    <?php if ($meet): ?>
    <div class="section">
        <h2>Meet Details</h2>
        <div class="table-wrap">
            <table class="meets">
                <thead>
                    <tr><th>Field</th><th>Value</th></tr>
                </thead>
                <tbody>
                    <tr><td>Name</td><td><?= htmlspecialchars($meet['meetName']) ?></td></tr>
                    <tr><td>Date</td><td><?= htmlspecialchars($meet['meetDate']) ?></td></tr>
                    <tr><td>Course</td><td><?= $meet['course']==='L'?'Longcourse':'Shortcourse' ?></td></tr>
                    <tr><td>External</td><td><?= $meet['external']==='Y'?'Yes':'No' ?></td></tr>
                    <tr><td>Description</td><td><?= htmlspecialchars($meet['meetInfo']) ?></td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Individual Results -->
    <div class="section">
        <h2>Individual Results</h2>
        <form method="get" class="toolbar">
            <div class="toolbar-left search-bar">
                <input type="hidden" name="meetID" value="<?= (int)$meetID ?>">
                <select name="ev_indiv" class="search-order">
                    <option value="">All Events</option>
                    <?php foreach ($indivEvents as $ev): ?>
                        <option value="<?= (int)$ev['eventID'] ?>" <?= $filterEventIDIndiv===(int)$ev['eventID']?'selected':'' ?>>
                            <?= htmlspecialchars($ev['eventName'] . ' (' . $ev['gender'] . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label class="check-item">
                    <input type="checkbox" name="g_M" value="1" <?= checked(in_array('M', $userGenderFilters, true)) ?>> Male
                </label>
                <label class="check-item">
                    <input type="checkbox" name="g_F" value="1" <?= checked(in_array('F', $userGenderFilters, true)) ?>> Female
                </label>
                <label class="check-item">
                    <input type="checkbox" name="g_MIX" value="1" <?= checked(in_array('MIX', $userGenderFilters, true)) ?>> Other
                </label>
            </div>
            <div class="toolbar-right filters-group">
                <button type="submit" class="btn">Apply</button>
                <a href="<?= currentUrl(['meetID' => $meetID, 'ev_indiv' => '', 'g_M'=>1, 'g_F'=>1, 'g_MIX'=>1]) ?>" class="btn btn-reset">Reset</a>
            </div>
        </form>

        <div class="table-wrap">
            <table class="meets">
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Swimmer</th>
                        <th>Year</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($indivRows)): ?>
                    <tr><td colspan="4">No individual results for this selection.</td></tr>
                <?php else: ?>
                    <?php foreach ($indivRows as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['eventName'] . ' (' . $r['eventGender'] . ')') ?></td>
                            <td><?= htmlspecialchars($r['surname'] . ', ' . $r['forename']) ?></td>
                            <!-- CHANGE: show yeargAtEvent -->
                            <td><?= (int)$r['yeargAtEvent'] ?></td>
                            <td><?= htmlspecialchars($r['time']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Relay Results -->
    <div class="section">
        <h2>Relay Results</h2>
        <form method="get" class="toolbar">
            <div class="toolbar-left search-bar">
                <input type="hidden" name="meetID" value="<?= (int)$meetID ?>">
                <select name="ev_relay" class="search-order">
                    <option value="">All Relay Events</option>
                    <?php foreach ($relayEvents as $ev): ?>
                        <option value="<?= (int)$ev['eventID'] ?>" <?= $filterEventIDRelay===(int)$ev['eventID']?'selected':'' ?>>
                            <?= htmlspecialchars($ev['eventName'] . ' (' . $ev['gender'] . ') [Relay]') ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label class="check-item">
                    <input type="checkbox" name="rg_M" value="1" <?= checked(in_array('M', $relayGenderFilters, true)) ?>> Male
                </label>
                <label class="check-item">
                    <input type="checkbox" name="rg_F" value="1" <?= checked(in_array('F', $relayGenderFilters, true)) ?>> Female
                </label>
                <label class="check-item">
                    <input type="checkbox" name="rg_MIX" value="1" <?= checked(in_array('MIX', $relayGenderFilters, true)) ?>> Other
                </label>
            </div>
            <div class="toolbar-right filters-group">
                <button type="submit" class="btn">Apply</button>
                <a href="<?= currentUrl(['meetID' => $meetID, 'ev_relay' => '', 'rg_M'=>1, 'rg_F'=>1, 'rg_MIX'=>1]) ?>" class="btn btn-reset">Reset</a>
            </div>
        </form>

        <div class="table-wrap">
            <table class="meets">
                <thead>
                    <tr>
                        <th>Relay Event</th>
                        <th>Team</th>
                        <th>Total Time</th>
                        <th>Members</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($relayTeams)): ?>
                    <tr><td colspan="4">No relay teams for this selection.</td></tr>
                <?php else: ?>
                    <?php foreach ($relayTeams as $rt): ?>
                        <tr>
                            <td><?= htmlspecialchars($rt['eventName'] . ' (' . $rt['gender'] . ')') ?></td>
                            <td><?= htmlspecialchars($rt['teamName'] ?? '') ?></td>
                            <td><?= htmlspecialchars($rt['totalTime'] ?? '') ?></td>
                            <td>
                                <?php
                                // List members for each team
                                $mem = $conn->prepare("SELECT m.leg, u.forename, u.surname FROM tblrelayTeamMember m INNER JOIN tbluser u ON u.userID = m.userID WHERE m.meetID = :m AND m.eventID = :e ORDER BY m.leg ASC");
                                $mem->bindValue(':m', (int)$meetID, PDO::PARAM_INT);
                                $mem->bindValue(':e', (int)$rt['eventID'], PDO::PARAM_INT);
                                $mem->execute();
                                $members = $mem->fetchAll(PDO::FETCH_ASSOC);
                                ?>
                                <?php if (empty($members)): ?>
                                    <em>No members listed.</em>
                                <?php else: ?>
                                    <?php foreach ($members as $m): ?>
                                        <div><?= (int)$m['leg'] ?>) <?= htmlspecialchars($m['surname'] . ', ' . $m['forename']) ?></div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
</body>
</html>