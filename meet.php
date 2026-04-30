<?php
session_start();
include_once('connection.php');
include_once('auth.php');
enforceSessionPolicies($conn); // Apply any session hardening rules (timeouts, regeneration, etc.)

// Access control: require logged-in user role >= 1 (i.e., not a guest / unauthenticated session)
if (!isset($_SESSION['role']) || (int)$_SESSION['role'] < 1) {
    // Inline "Access Denied" page shown instead of redirecting (then exit to stop any further output/queries)
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

// Build a query-string URL from current $_GET values, optionally overriding specific keys (used by filters/pagination links)
function currentUrl(array $overrides = []): string {
    $q = array_merge($_GET, $overrides);
    return '?' . http_build_query($q);
}

/* Meet list filters (reused admin_meetList patterns) */

// Map user-facing sort keys to DB columns (only allow known sorts to prevent unsafe ORDER BY input)
$validSort = ['id' => 'meetID', 'name' => 'meetName', 'date' => 'meetDate'];
$sort = isset($_GET['sort']) && array_key_exists($_GET['sort'], $validSort) ? $_GET['sort'] : 'id';

// Convert selected sort into a concrete ORDER BY clause for the meet list query
if ($sort === 'name') {
    $orderSql = 'ORDER BY meetName ASC, meetID DESC';
} elseif ($sort === 'date') {
    $orderSql = 'ORDER BY meetDate DESC, meetID DESC';
} else {
    $orderSql = 'ORDER BY meetID DESC';
}

// Pagination controls (per-page is restricted to a fixed set; page must be a positive integer)
$perPage = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], [20,35,50], true) ? (int)$_GET['per_page'] : 20;
$page = isset($_GET['page']) && ctype_digit((string)$_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;

// Course filters (L=Longcourse, S=Shortcourse); default is "include both" when no checkboxes are selected
$courseFilters = [];
if (isset($_GET['course_L']) || isset($_GET['course_S'])) {
    if (isset($_GET['course_L'])) $courseFilters[] = 'L';
    if (isset($_GET['course_S'])) $courseFilters[] = 'S';
} else {
    $courseFilters = ['L', 'S'];
}

// External filters (Y=External meet, N=School meet); default is "include both" when no checkboxes are selected
$externalFilters = [];
if (isset($_GET['ext_Y']) || isset($_GET['ext_N'])) {
    if (isset($_GET['ext_Y'])) $externalFilters[] = 'Y';
    if (isset($_GET['ext_N'])) $externalFilters[] = 'N';
} else {
    $externalFilters = ['Y', 'N'];
}

// Optional name search term for meetName LIKE queries
$q = isset($_GET['q']) ? trim($_GET['q']) : '';

// Build a parameterized IN (...) clause by generating unique placeholders and a matching bind array
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

// Accumulate WHERE fragments + bound parameters based on active filters
$where = [];
$params = [];

if (!empty($courseFilters)) {
    [$phs, $p] = buildInClause('c', $courseFilters);
    $where[] = 'course IN (' . implode(',', $phs) . ')';
    $params = array_merge($params, $p);
} else {
    $where[] = '1=0'; // If no course options are allowed, force query to return no rows
}

if (!empty($externalFilters)) {
    [$phs, $p] = buildInClause('e', $externalFilters);
    $where[] = 'external IN (' . implode(',', $phs) . ')';
    $params = array_merge($params, $p);
} else {
    $where[] = '1=0'; // If no external options are allowed, force query to return no rows
}

if ($q !== '') {
    $where[] = 'meetName LIKE :q'; // Search by meet name substring
    $params[':q'] = '%' . $q . '%';
}

// Final WHERE clause for both count query and list query
$whereSql = empty($where) ? '' : ('WHERE ' . implode(' AND ', $where));

/* Total + list */

// Count total rows for pagination (same WHERE as main list query)
$countSql = "SELECT COUNT(*) FROM tblmeet $whereSql";
$stmt = $conn->prepare($countSql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->execute();
$totalRows = (int)$stmt->fetchColumn();

// Clamp pagination values and compute LIMIT/OFFSET for the current page
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// Fetch the current page of meets using filters + ordering + pagination
$listSql = "SELECT meetID, meetName, meetDate, external, course FROM tblmeet $whereSql $orderSql LIMIT :limit OFFSET :offset";
$stmt = $conn->prepare($listSql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$meets = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Selected meet to view */

// When a meet is selected, load its details and the set of events that belong to it
$meetID = isset($_GET['meetID']) && ctype_digit((string)$_GET['meetID']) ? (int)$_GET['meetID'] : 0;
$meet = null;
$meetEvents = [];
$indivEvents = [];
$relayEvents = [];

if ($meetID) {
    // Fetch meet details (single row)
    $st = $conn->prepare("SELECT meetID, meetName, meetDate, meetInfo, external, course FROM tblmeet WHERE meetID = :id LIMIT 1");
    $st->bindValue(':id', $meetID, PDO::PARAM_INT);
    $st->execute();
    $meet = $st->fetch(PDO::FETCH_ASSOC);

    // Fetch events that are linked to this meet (used both for display and to validate event filters)
    $sql = "SELECT e.eventID, e.eventName, e.gender, e.eventType
            FROM tblevent e
            INNER JOIN tblmeetHasEvent me ON me.eventID = e.eventID
            WHERE me.meetID = :id
            ORDER BY e.eventID ASC";
    $st2 = $conn->prepare($sql);
    $st2->bindValue(':id', $meetID, PDO::PARAM_INT);
    $st2->execute();
    $meetEvents = $st2->fetchAll(PDO::FETCH_ASSOC);

    // Split event list into individual vs relay for separate filter dropdowns/results sections
    foreach ($meetEvents as $ev) {
        if ($ev['eventType'] === 'INDIV') $indivEvents[] = $ev;
        else $relayEvents[] = $ev;
    }
}

/* Filters inside selected meet view */

// Selected event filters for each results section (0 means "no filter / all events")
$filterEventIDIndiv = isset($_GET['ev_indiv']) && ctype_digit((string)$_GET['ev_indiv']) ? (int)$_GET['ev_indiv'] : 0;
$filterEventIDRelay = isset($_GET['ev_relay']) && ctype_digit((string)$_GET['ev_relay']) ? (int)$_GET['ev_relay'] : 0;

/* User gender filters for individual results (M/F/MIX; default all) */

// Filter on swimmer gender for individual result rows; default is all when no boxes are checked
$userGenderFilters = [];
if (isset($_GET['g_M']) || isset($_GET['g_F']) || isset($_GET['g_MIX'])) {
    if (isset($_GET['g_M'])) $userGenderFilters[] = 'M';
    if (isset($_GET['g_F'])) $userGenderFilters[] = 'F';
    if (isset($_GET['g_MIX'])) $userGenderFilters[] = 'MIX';
} else {
    $userGenderFilters = ['M','F','MIX'];
}

/* Event gender filter for relay (M/F/MIX; default all) */

// Filter on relay event gender; default is all when no boxes are checked
$relayGenderFilters = [];
if (isset($_GET['rg_M']) || isset($_GET['rg_F']) || isset($_GET['rg_MIX'])) {
    if (isset($_GET['rg_M'])) $relayGenderFilters[] = 'M';
    if (isset($_GET['rg_F'])) $relayGenderFilters[] = 'F';
    if (isset($_GET['rg_MIX'])) $relayGenderFilters[] = 'MIX';
} else {
    $relayGenderFilters = ['M','F','MIX'];
}

/* Fetch individual results */

// Query individual results for the selected meet, applying optional event + swimmer gender filters
$indivRows = [];
if ($meetID) {
    $whereParts = ["ms.meetID = :m", "e.eventType = 'INDIV'"];
    $binds = [':m' => $meetID];

    // If an individual event filter is set, only allow it if that event is actually part of this meet
    if ($filterEventIDIndiv) {
        $presentIndivIds = array_map(fn($x) => (int)$x['eventID'], $indivEvents);
        if (in_array($filterEventIDIndiv, $presentIndivIds, true)) {
            $whereParts[] = "ms.eventID = :ev";
            $binds[':ev'] = $filterEventIDIndiv;
        } else {
            $whereParts[] = "1=0"; // Invalid event filter -> force empty result set
        }
    }

    // Apply swimmer gender filters (u.gender comes from tbluser)
    if (!empty($userGenderFilters)) {
        [$phs, $p] = buildInClause('ug', $userGenderFilters);
        $whereParts[] = 'u.gender IN (' . implode(',', $phs) . ')';
        $binds = array_merge($binds, $p);
    } else {
        $whereParts[] = '1=0';
    }

    // Fetch swimmer name + year group at event time + event details + swim time, ordered for display
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

// Fetch relay teams for the selected meet, applying optional event + event gender filters
$relayTeams = [];
if ($meetID) {
    $whereParts = ["rt.meetID = :m"];
    $binds = [':m' => $meetID];

    // If a relay event filter is set, only allow it if that event is actually part of this meet
    if ($filterEventIDRelay) {
        $presentRelayIds = array_map(fn($x) => (int)$x['eventID'], $relayEvents);
        if (in_array($filterEventIDRelay, $presentRelayIds, true)) {
            $whereParts[] = "rt.eventID = :ev";
            $binds[':ev'] = $filterEventIDRelay;
        } else {
            $whereParts[] = "1=0";
        }
    }

    // Apply relay event gender filters (e.gender comes from tblevent)
    if (!empty($relayGenderFilters)) {
        [$phs, $p] = buildInClause('rg', $relayGenderFilters);
        $whereParts[] = 'e.gender IN (' . implode(',', $phs) . ')';
        $binds = array_merge($binds, $p);
    } else {
        $whereParts[] = '1=0';
    }

    // Fetch relay team summary rows (members are fetched later per row)
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

// Small helper to output HTML checked attribute when a condition is true
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

    <!-- Meet search + filter toolbar for the meet list (search text, sort, course/external filters, per-page) -->
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

                <!-- Course + external checkboxes feed into the server-side IN(...) filters above -->
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
                <!-- Per-page selector affects pagination calculations and LIMIT -->
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
                                    <!-- "View" keeps current filters and sets meetID to open the selected meet view -->
                                    <a class="btn" href="<?= currentUrl(['meetID' => (int)$m['meetID'], 'page' => 1]) ?>">View</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination links preserve the current filter query string -->
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

            <!-- Quick links to change per-page count (also resets page to 1 to avoid out-of-range pages) -->
            <div class="per-page">
                <span class="per-page-label">Entries:</span>
                <a class="btn <?= $perPage===20?'btn-active':'' ?>" href="<?= currentUrl(['per_page'=>20, 'page'=>1]) ?>">20</a>
                <a class="btn <?= $perPage===35?'btn-active':'' ?>" href="<?= currentUrl(['per_page'=>35, 'page'=>1]) ?>">35</a>
                <a class="btn <?= $perPage===50?'btn-active':'' ?>" href="<?= currentUrl(['per_page'=>50, 'page'=>1]) ?>">50</a>
            </div>
        </div>
    </div>

    <!-- Selected meet view: shows meet metadata plus result tables when meetID is set and found -->
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

    <!-- Individual results section for the selected meet (with optional event filter) -->
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

            </div>
            <div class="toolbar-right filters-group">
                <button type="submit" class="btn">Apply</button>
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
                            <td><?= (int)$r['yeargAtEvent'] ?></td> <!-- Display recorded year group at time of the event -->
                            <td><?= htmlspecialchars($r['time']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Relay results section for the selected meet (teams listed; members fetched per team row) -->
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

            </div>
            <div class="toolbar-right filters-group">
                <button type="submit" class="btn">Apply</button>
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
                                // Fetch the relay members for this meet + event, ordered by leg number for display
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