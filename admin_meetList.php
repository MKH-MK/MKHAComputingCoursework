<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 2) {
    // Show error message and do not load the admin page content
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
                Permision Error: You do not have the right privilege to view this page.
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

if (isset($_GET['edit']) && ctype_digit((string)$_GET['edit'])) {
    header("Location: admin_meetEditor.php?edit=" . urlencode($_GET['edit']));
    exit;
}

include_once("connection.php");

// CSRF token for destructive actions
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $meetID = isset($_POST['meetID']) && ctype_digit((string)$_POST['meetID']) ? (int)$_POST['meetID'] : 0;
    $csrf = $_POST['csrf_token'] ?? '';

    if (!$meetID || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
        header("Location: admin_meetList.php?err=csrf");
        exit;
    }

    try {
        $del = $conn->prepare("DELETE FROM tblmeet WHERE meetID = :id");
        $del->bindValue(':id', $meetID, PDO::PARAM_INT);
        $del->execute();
        header("Location: admin_meetList.php?msg=deleted");
        exit;
    } catch (PDOException $e) {
        header("Location: admin_meetList.php?err=dberror");
        exit;
    }
}

// Params
$validSort = ['id' => 'meetID', 'name' => 'meetName', 'date' => 'meetDate'];
$sort = isset($_GET['sort']) && array_key_exists($_GET['sort'], $validSort) ? $_GET['sort'] : 'id';

// Direction fixed: created/date = DESC, name = ASC
if ($sort === 'name') {
    $orderSql = 'ORDER BY meetName ASC, meetID DESC';
} elseif ($sort === 'date') {
    $orderSql = 'ORDER BY meetDate DESC, meetID DESC';
} else {
    $orderSql = 'ORDER BY meetID DESC';
}

// Per-page & pagination
$perPage = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], [20,35,50], true) ? (int)$_GET['per_page'] : 20;
$page = isset($_GET['page']) && ctype_digit((string)$_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;

// Filters: course (L/S) and external (Y/N)
$courseFilters = [];
if (isset($_GET['course_L']) || isset($_GET['course_S'])) {
    if (isset($_GET['course_L'])) $courseFilters[] = 'L';
    if (isset($_GET['course_S'])) $courseFilters[] = 'S';
} else {
    // default: both selected
    $courseFilters = ['L', 'S'];
}

$externalFilters = [];
if (isset($_GET['ext_Y']) || isset($_GET['ext_N'])) {
    if (isset($_GET['ext_Y'])) $externalFilters[] = 'Y';
    if (isset($_GET['ext_N'])) $externalFilters[] = 'N';
} else {
    // default: both selected
    $externalFilters = ['Y', 'N'];
}

// Search
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

// WHERE
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

// Total
$countSql = "SELECT COUNT(*) FROM tblmeet $whereSql";
$stmt = $conn->prepare($countSql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->execute();
$totalRows = (int)$stmt->fetchColumn();

$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// List
$listSql = "SELECT meetID, meetName, meetDate, external, course FROM tblmeet $whereSql $orderSql LIMIT :limit OFFSET :offset";
$stmt = $conn->prepare($listSql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$meets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helpers
function currentUrl(array $overrides = []): string {
    $q = array_merge($_GET, $overrides);
    return '?' . http_build_query($q);
}
function checked($cond) { return $cond ? 'checked' : ''; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Oundle School Swim Team - Manage Meets</title>
    <meta name="viewport" content="width=1024, initial-scale=1">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="main-content">
    <div class="page-title">Manage Meets</div>

    <div class="section">
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
            <div class="alert-success">Meet deleted.</div>
        <?php endif; ?>
        <?php if (isset($_GET['err']) && $_GET['err'] === 'csrf'): ?>
            <div class="alert-fail">Delete failed: invalid session token.</div>
        <?php endif; ?>
        <?php if (isset($_GET['err']) && $_GET['err'] === 'dberror'): ?>
            <div class="alert-fail">Delete failed due to a database error.</div>
        <?php endif; ?>

        <div class="toolbar">
            <form method="get" class="toolbar-left search-bar">
                <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search meet name...">
                <button type="submit" class="btn">Search</button>
            </form>

            <form method="get" class="toolbar-right filters-group">
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

                <?php if ($q !== ''): ?>
                    <input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>">
                <?php endif; ?>

                <button type="submit" class="btn">Apply</button>
                <a href="admin_meetList.php" class="btn btn-reset">Reset</a>
            </form>
        </div>

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
                                    <a class="btn" href="admin_meetEditor.php?edit=<?= urlencode($m['meetID']) ?>">Edit</a>

                                    <form method="post" class="js-confirm-delete">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="meetID" value="<?= (int)$m['meetID'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                        <button type="submit" class="btn btn-danger">Delete</button>
                                    </form>
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
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('form.js-confirm-delete').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            const ok = confirm('Are you sure you want to delete this meet? This cannot be undone.');
            if (!ok) e.preventDefault();
        });
    });
});
</script>
</body>
</html>