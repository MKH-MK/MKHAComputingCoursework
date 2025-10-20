<?php
/*
  Admin - Manage Meets
  - Auth check: only role 2 (admin)
  - GET params:
      q: keyword search by meetName only (ID search removed)
      order: newest | date | name (sorting mode)
      page: pagination (50 per page)
      edit: meetID to open the editor; when present, the list section is hidden and editor is shown at top
  - Lists: Name, Date (DD/MM/YYYY), Course, In School, Actions
  - Editor: loads inline at top via fetch to admin_meetEditor.php
*/

session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 2) {
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

// Inputs
$q     = isset($_GET['q']) ? trim($_GET['q']) : '';
$order = isset($_GET['order']) ? $_GET['order'] : 'newest'; // newest|date|name
$page  = max(1, (int)($_GET['page'] ?? 1));
$editID = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;

// Pagination
$limit  = 50;
$offset = ($page - 1) * $limit;

// Build WHERE: name-only search
$where = "1=1";
$params = [];
if ($q !== '') {
    $where .= " AND meetName LIKE :like";
    $params[':like'] = "%$q%";
}

// ORDER BY
switch ($order) {
    case 'name':
        $orderBy = "meetName ASC, meetDate DESC, meetID DESC";
        break;
    case 'date':
        $orderBy = "meetDate DESC, meetID DESC";
        break;
    case 'newest':
    default:
        $orderBy = "meetID DESC";
        $order = 'newest';
        break;
}

// Count
$stmt = $conn->prepare("SELECT COUNT(*) FROM tblmeet WHERE $where");
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v, PDO::PARAM_STR);
}
$stmt->execute();
$total = (int)$stmt->fetchColumn();
$stmt->closeCursor();

// Rows
$sqlRows = "SELECT meetID, meetName, meetDate, meetInfo, external, course
            FROM tblmeet
            WHERE $where
            ORDER BY $orderBy
            LIMIT :limit OFFSET :offset";
$stmt = $conn->prepare($sqlRows);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v, PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$meets = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt->closeCursor();

$totalPages = max(1, (int)ceil($total / $limit));

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function formatUK($ymd) {
    $ts = strtotime($ymd);
    return $ts ? date('d/m/Y', $ts) : h($ymd);
}

// Build a list URL without edit (used when closing editor)
$listUrl = 'admin_manageMeets.php?'
    . 'q=' . urlencode($q)
    . '&order=' . urlencode($order)
    . '&page=' . $page;
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

    <!-- Inline Editor -->
    <div id="editor-container" class="inline-editor" <?php if(!$editID) echo 'style="display:none"'; ?>>
        <div class="inline-editor-header">
            <h3>Edit Meet</h3>
            <button type="button" class="inline-editor-close" onclick="closeEditor()">×</button>
        </div>
        <div id="inline-editor-body"><?= $editID ? 'Loading…' : '' ?></div>
    </div>

    <!-- List section -->
    <div id="list-wrap" class="section table-wrap" <?php if($editID) echo 'style="display:none"'; ?>>
        <form class="search-bar" method="get" action="admin_manageMeets.php">
            <input type="text" name="q" placeholder="Search by Name" value="<?= h($q) ?>">
            <select name="order" class="search-order">
                <option value="newest" <?= $order==='newest'?'selected':'' ?>>Newest (latest created)</option>
                <option value="date"   <?= $order==='date'  ?'selected':'' ?>>By date (newest first)</option>
                <option value="name"   <?= $order==='name'  ?'selected':'' ?>>Name (A–Z)</option>
            </select>
            <button type="submit">Apply</button>
        </form>

        <table class="meets">
            <thead>
                <tr>
                    <th>Name</th>
                    <th style="width:140px;">Date</th>
                    <th style="width:120px;">Course</th>
                    <th style="width:120px;">In School</th>
                    <th style="width:110px;">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$meets): ?>
                <tr><td colspan="5">No meets found.</td></tr>
            <?php else: ?>
                <?php foreach ($meets as $m): ?>
                    <tr>
                        <td><?= h($m['meetName']) ?></td>
                        <td><?= formatUK($m['meetDate']) ?></td>
                        <td><?= ($m['course'] === 'L' ? 'Longcourse' : 'Shortcourse') ?></td>
                        <td><?= ($m['external'] === 'N' ? 'Yes' : 'No') ?></td>
                        <td class="actions">
                            <button type="button" class="btn" onclick="openEditor(<?= (int)$m['meetID'] ?>)">Edit</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>

        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?q=<?= urlencode($q) ?>&order=<?= urlencode($order) ?>&page=<?= $page-1 ?>">Prev</a>
            <?php endif; ?>
            <span class="current"><?= $page ?> / <?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?>
                <a href="?q=<?= urlencode($q) ?>&order=<?= urlencode($order) ?>&page=<?= $page+1 ?>">Next</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Base path so fetch always hits the right file (works in any subfolder)
const BASE_PATH = "<?= rtrim(dirname($_SERVER['PHP_SELF']), '/\\') ?>/";

// Open editor: show panel, load content, and update URL to ?edit={id}
function openEditor(meetID) {
    const cont = document.getElementById('editor-container');
    const body = document.getElementById('inline-editor-body');
    const list = document.getElementById('list-wrap');

    cont.style.display = 'block';
    if (list) list.style.display = 'none';
    body.textContent = 'Loading…';
    window.scrollTo({ top: 0, behavior: 'smooth' });

    // Update the address bar
    history.replaceState(null, '', 'admin_manageMeets.php?edit=' + encodeURIComponent(meetID));

    // Load editor
    fetch(BASE_PATH + 'admin_meetEditor.php?meetID=' + encodeURIComponent(meetID), {
        headers: { 'X-Requested-With': 'fetch' }
    })
    .then(r => r.text())
    .then(html => { body.innerHTML = html; })
    .catch(() => { body.textContent = 'Failed to load editor.'; });
}

// Close editor: hide panel, show list, restore list URL
function closeEditor() {
    const cont = document.getElementById('editor-container');
    const body = document.getElementById('inline-editor-body');
    const list = document.getElementById('list-wrap');
    if (cont) cont.style.display = 'none';
    if (list) list.style.display = 'block';
    if (body) body.innerHTML = '';

    // Restore list URL (keeps your filters/pagination)
    history.replaceState(null, '', "<?= $listUrl ?>");
}

// Intercept forms inside editor to submit via fetch and refresh editor content
document.getElementById('editor-container').addEventListener('submit', function(e) {
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;
    e.preventDefault();

    const body = document.getElementById('inline-editor-body');
    const data = new FormData(form);

    fetch(form.action, {
        method: form.method || 'POST',
        body: data,
        headers: { 'X-Requested-With': 'fetch' }
    })
    .then(r => r.text())
    .then(html => { body.innerHTML = html; })
    .catch(() => { body.innerHTML = '<div class="alert-fail">Action failed.</div>'; });
});

// If loaded with ?edit=, open immediately
<?php if ($editID): ?>
openEditor(<?= (int)$editID ?>);
<?php endif; ?>
</script>

</body>
</html>