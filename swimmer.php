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
            <div class="alert-fail">You are not logged in or do not have the right privilege to access this page.</div>
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

/* Helpers */
function currentUrl(array $overrides = []): string {
    $q = array_merge($_GET, $overrides);
    return '?' . http_build_query($q);
}
function selected($cond) { return $cond ? 'selected' : ''; }
function checked($cond) { return $cond ? 'checked' : ''; }

/* Filters (GET) */
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$genderSel = isset($_GET['gender']) && is_array($_GET['gender']) ? array_values(array_intersect($_GET['gender'], ['M','F','MIX'])) : ['M','F','MIX'];
$yearg = (isset($_GET['yearg']) && $_GET['yearg'] !== '' && ctype_digit((string)$_GET['yearg'])) ? (int)$_GET['yearg'] : null;
$sort = (isset($_GET['sort']) && in_array($_GET['sort'], ['name','yearg','username'], true)) ? $_GET['sort'] : 'name';
$perPage = (isset($_GET['per_page']) && in_array((int)$_GET['per_page'], [20,35,50], true)) ? (int)$_GET['per_page'] : 20;
$page = (isset($_GET['page']) && ctype_digit((string)$_GET['page']) && (int)$_GET['page'] > 0) ? (int)$_GET['page'] : 1;

/* WHERE conditions (only swimmers role=1) */
$where = ["role = 1"];
$params = [];

if ($q !== '') {
    $where[] = "(forename LIKE :q OR surname LIKE :q OR userName LIKE :q)";
    $params[':q'] = '%' . $q . '%';
}

if (!empty($genderSel) && count($genderSel) < 3) {
    $inPlaceholders = [];
    foreach ($genderSel as $i => $g) {
        $ph = ":g$i";
        $inPlaceholders[] = $ph;
        $params[$ph] = $g;
    }
    $where[] = "gender IN (" . implode(',', $inPlaceholders) . ")";
}

if ($yearg !== null) {
    $where[] = "yearg = :yearg";
    $params[':yearg'] = $yearg;
}

$whereSql = implode(' AND ', $where);

/* Sorting */
switch ($sort) {
    case 'yearg':
        $orderSql = "ORDER BY yearg ASC, surname ASC, forename ASC";
        break;
    case 'username':
        $orderSql = "ORDER BY userName ASC";
        break;
    case 'name':
    default:
        $orderSql = "ORDER BY surname ASC, forename ASC";
        break;
}

/* Count */
$countSql = "SELECT COUNT(*) FROM tbluser WHERE $whereSql";
$stCount = $conn->prepare($countSql);
foreach ($params as $k => $v) $stCount->bindValue($k, $v);
$stCount->execute();
$totalRows = (int)$stCount->fetchColumn();

/* Pagination */
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

/* List */
$listSql = "SELECT userID, forename, surname, userName, yearg, gender
            FROM tbluser
            WHERE $whereSql
            $orderSql
            LIMIT :limit OFFSET :offset";
$stList = $conn->prepare($listSql);
foreach ($params as $k => $v) $stList->bindValue($k, $v);
$stList->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stList->bindValue(':offset', $offset, PDO::PARAM_INT);
$stList->execute();
$rows = $stList->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Oundle School Swim Team - Swimmers</title>
    <meta name="viewport" content="width=1024, initial-scale=1">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="main-content">
    <div class="page-title">Swimmers</div>

    <!-- Single section: toolbar + table (styled like meet/admin user list) -->
    <div class="section">
        <form method="get" class="toolbar">
            <div class="toolbar-left search-bar">
                <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search name or username...">

                <label class="check-item">
                    <input type="checkbox" name="gender[]" value="M" <?= checked(in_array('M', $genderSel, true)) ?>> Male
                </label>
                <label class="check-item">
                    <input type="checkbox" name="gender[]" value="F" <?= checked(in_array('F', $genderSel, true)) ?>> Female
                </label>
                <label class="check-item">
                    <input type="checkbox" name="gender[]" value="MIX" <?= checked(in_array('MIX', $genderSel, true)) ?>> Other/MIX
                </label>

                <select name="yearg" class="search-order">
                    <option value="" <?= selected($yearg===null) ?>>All year groups</option>
                    <?php for ($yg=7; $yg<=13; $yg++): ?>
                        <option value="<?= $yg ?>" <?= selected($yearg===$yg) ?>>Year <?= $yg ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="toolbar-right filters-group">
                <select name="sort" class="search-order">
                    <option value="name" <?= selected($sort==='name') ?>>Sort: Name</option>
                    <option value="yearg" <?= selected($sort==='yearg') ?>>Sort: Year Group</option>
                    <option value="username" <?= selected($sort==='username') ?>>Sort: Username</option>
                </select>

                <!-- Always reset to page 1 on apply -->
                <input type="hidden" name="page" value="1">

                <button type="submit" class="btn">Apply</button>
                <a href="swimmer.php" class="btn btn-reset">Reset</a>
            </div>
        </form>

        <div class="table-wrap">
            <table class="meets">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Year Group</th>
                        <th>Gender</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="4">No swimmers found.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td>
                                <a class="link" href="<?= 'account.php?userID=' . (int)$r['userID'] ?>">
                                    <?= htmlspecialchars($r['forename'] . ' ' . $r['surname']) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($r['userName']) ?></td>
                            <td><?= ((int)$r['yearg'] === 0) ? '—' : (int)$r['yearg'] ?></td>
                            <td><?= htmlspecialchars($r['gender']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <?php if ($totalRows > $perPage): ?>
                <?php
                    $prevPage = max(1, $page - 1);
                    $nextPage = min($totalPages, $page + 1);
                ?>
                <div class="pagination">
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