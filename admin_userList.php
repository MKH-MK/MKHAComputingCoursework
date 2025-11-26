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

include_once("connection.php");

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $userID = isset($_POST['userID']) && ctype_digit((string)$_POST['userID']) ? (int)$_POST['userID'] : 0;
    $csrf = $_POST['csrf_token'] ?? '';

    if (!$userID || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
        header("Location: admin_userList.php?err=csrf");
        exit;
    }

    try {
        $del = $conn->prepare("DELETE FROM tbluser WHERE userID = :id");
        $del->bindValue(':id', $userID, PDO::PARAM_INT);
        $del->execute();
        header("Location: admin_userList.php?msg=deleted");
        exit;
    } catch (PDOException $e) {
        header("Location: admin_userList.php?err=dberror");
        exit;
    }
}

// Sorting
$validSort = [
    'id'   => 'userID',
    'name' => 'name',
    'role' => 'role',
    'year' => 'yearg'
];
$sort = isset($_GET['sort']) && array_key_exists($_GET['sort'], $validSort) ? $_GET['sort'] : 'id';

if ($sort === 'name') {
    $orderSql = 'ORDER BY surname ASC, forename ASC, userID DESC';
} elseif ($sort === 'role') {
    $orderSql = 'ORDER BY role DESC, surname ASC, forename ASC, userID DESC';
} elseif ($sort === 'year') {
    $orderSql = 'ORDER BY yearg DESC, surname ASC, forename ASC, userID DESC';
} else {
    $orderSql = 'ORDER BY userID DESC';
}

// Per-page & pagination
$perPage = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], [20,35,50], true) ? (int)$_GET['per_page'] : 20;
$page = isset($_GET['page']) && ctype_digit((string)$_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;

// Filters: role (0/1/2) and gender (M/F/MIX)
$roleFilters = [];
if (isset($_GET['role_0']) || isset($_GET['role_1']) || isset($_GET['role_2'])) {
    if (isset($_GET['role_0'])) $roleFilters[] = 0;
    if (isset($_GET['role_1'])) $roleFilters[] = 1;
    if (isset($_GET['role_2'])) $roleFilters[] = 2;
} else {
    $roleFilters = [0,1,2]; // default: all
}

$genderFilters = [];
if (isset($_GET['g_M']) || isset($_GET['g_F']) || isset($_GET['g_MIX'])) {
    if (isset($_GET['g_M'])) $genderFilters[] = 'M';
    if (isset($_GET['g_F'])) $genderFilters[] = 'F';
    if (isset($_GET['g_MIX'])) $genderFilters[] = 'MIX';
} else {
    $genderFilters = ['M','F','MIX']; // default: all
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

// WHERE building
$where = [];
$params = [];

if (!empty($roleFilters)) {
    [$phs, $p] = buildInClause('r', $roleFilters);
    $where[] = 'role IN (' . implode(',', $phs) . ')';
    $params = array_merge($params, $p);
} else {
    $where[] = '1=0';
}

if (!empty($genderFilters)) {
    [$phs, $p] = buildInClause('g', $genderFilters);
    $where[] = 'gender IN (' . implode(',', $phs) . ')';
    $params = array_merge($params, $p);
} else {
    $where[] = '1=0';
}

if ($q !== '') {
    $where[] = '(forename LIKE :q OR surname LIKE :q OR emailAddress LIKE :q OR userName LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}

$whereSql = empty($where) ? '' : ('WHERE ' . implode(' AND ', $where));

// Total count
$countSql = "SELECT COUNT(*) FROM tbluser $whereSql";
$stmt = $conn->prepare($countSql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->execute();
$totalRows = (int)$stmt->fetchColumn();

$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// Fetch list
$listSql = "SELECT userID, forename, surname, userName, emailAddress, yearg, gender, role 
            FROM tbluser $whereSql $orderSql LIMIT :limit OFFSET :offset";
$stmt = $conn->prepare($listSql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helpers
function currentUrl(array $overrides = []): string {
    $q = array_merge($_GET, $overrides);
    return '?' . http_build_query($q);
}
function checked($cond) { return $cond ? 'checked' : ''; }
function roleLabel($r) {
    if ((int)$r === 2) return 'Coach/Admin';
    if ((int)$r === 1) return 'Student';
    return 'Guest';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Oundle School Swim Team - Manage Users</title>
    <meta name="viewport" content="width=1024, initial-scale=1">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="main-content">
    <div class="page-title">Manage Users</div>

    <div class="section">
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
            <div class="alert-success">User deleted.</div>
        <?php endif; ?>
        <?php if (isset($_GET['err']) && $_GET['err'] === 'csrf'): ?>
            <div class="alert-fail">Delete failed: invalid session token.</div>
        <?php endif; ?>
        <?php if (isset($_GET['err']) && $_GET['err'] === 'dberror'): ?>
            <div class="alert-fail">Delete failed due to a database error.</div>
        <?php endif; ?>

        <div class="toolbar">
            <form method="get" class="toolbar-left search-bar">
                <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search name, username, or email...">
                <button type="submit" class="btn">Search</button>
            </form>

            <form method="get" class="toolbar-right filters-group">
                <select name="sort" class="search-order">
                    <option value="id" <?= $sort==='id'?'selected':'' ?>>Date Created</option>
                    <option value="name" <?= $sort==='name'?'selected':'' ?>>Alphabetical</option>
                    <option value="role" <?= $sort==='role'?'selected':'' ?>>Role</option>
                    <option value="year" <?= $sort==='year'?'selected':'' ?>>Year Group</option>
                </select>

                <label class="check-item">
                    <input type="checkbox" name="role_2" value="1" <?= checked(in_array(2, $roleFilters, true)) ?>> Coach/Admin
                </label>
                <label class="check-item">
                    <input type="checkbox" name="role_1" value="1" <?= checked(in_array(1, $roleFilters, true)) ?>> Student
                </label>
                <label class="check-item">
                    <input type="checkbox" name="role_0" value="1" <?= checked(in_array(0, $roleFilters, true)) ?>> Guest
                </label>

                <label class="check-item">
                    <input type="checkbox" name="g_M" value="1" <?= checked(in_array('M', $genderFilters, true)) ?>> Male
                </label>
                <label class="check-item">
                    <input type="checkbox" name="g_F" value="1" <?= checked(in_array('F', $genderFilters, true)) ?>> Female
                </label>
                <label class="check-item">
                    <input type="checkbox" name="g_MIX" value="1" <?= checked(in_array('MIX', $genderFilters, true)) ?>> Other
                </label>

                <?php if ($q !== ''): ?>
                    <input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>">
                <?php endif; ?>

                <button type="submit" class="btn">Apply</button>
                <a href="admin_userList.php" class="btn btn-reset">Reset</a>
            </form>
        </div>

        <div class="table-wrap">
            <table class="meets">
                <thead>
                    <tr>
                        <th>Surname</th>
                        <th>Forename</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Year</th>
                        <th>Gender</th>
                        <th>Role</th>
                        <th class="col-actions-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($users)): ?>
                    <tr><td colspan="8">No users found.</td></tr>
                <?php else: ?>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= htmlspecialchars($u['surname']) ?></td>
                            <td><?= htmlspecialchars($u['forename']) ?></td>
                            <td><?= htmlspecialchars($u['userName']) ?></td>
                            <td><?= htmlspecialchars($u['emailAddress']) ?></td>
                            <td><?= (int)$u['yearg'] ?></td>
                            <td><?= htmlspecialchars($u['gender']) ?></td>
                            <td><?= htmlspecialchars(roleLabel($u['role'])) ?></td>
                            <td class="col-actions-right">
                                <div class="action-buttons">
                                    <a class="btn" href="admin_userEditor.php?edit=<?= urlencode($u['userID']) ?>">Edit</a>

                                    <form method="post" class="js-confirm-delete">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="userID" value="<?= (int)$u['userID'] ?>">
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
            const ok = confirm('Are you sure you want to delete this user? This cannot be undone.');
            if (!ok) e.preventDefault();
        });
    });
});
</script>
</body>
</html>