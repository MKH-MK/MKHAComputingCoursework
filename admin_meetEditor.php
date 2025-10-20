<?php
/*
  Admin - Meet Editor (HTML fragment returned to inline editor)
  - POST target is ALWAYS this file, using an absolute path based on dirname($_SERVER['PHP_SELF'])
  - Multiple event add uses checkbox list name="eventID[]"
*/

session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 2) {
    http_response_code(403);
    echo '<div class="alert-fail">Access denied.</div>';
    exit();
}

include_once("connection.php");
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Build absolute action to avoid "Not Found" due to relative paths
$ACTION_URL = htmlspecialchars(rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . '/admin_meetEditor.php', ENT_QUOTES, 'UTF-8');

$meetID = 0;
if (isset($_GET['meetID'])) $meetID = (int)$_GET['meetID'];
if (!$meetID && isset($_POST['meetID'])) $meetID = (int)$_POST['meetID'];

$action = $_POST['action'] ?? '';
$notice = '';
$error  = '';

// Student search state
$studentSearchEventID = 0;
$studentSearchQuery = '';
$studentMatches = [];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action) {
        if ($action === 'update_meet') {
            $_POST = array_map('htmlspecialchars', $_POST);
            $stmt = $conn->prepare("UPDATE tblmeet
                SET meetName = :name, meetDate = :date, meetInfo = :info, external = :external, course = :course
                WHERE meetID = :id");
            $stmt->execute([
                ':name' => $_POST['meetName'],
                ':date' => $_POST['meetDate'],
                ':info' => $_POST['meetInfo'],
                ':external' => $_POST['external'],
                ':course' => $_POST['course'],
                ':id' => $meetID,
            ]);
            $notice = 'Meet updated.';
        }
        elseif ($action === 'add_events') {
            $ids = isset($_POST['eventID']) ? (array)$_POST['eventID'] : [];
            $ids = array_values(array_unique(array_map('intval', $ids)));
            if ($ids) {
                $chk = $conn->prepare("SELECT 1 FROM tblmeetHasEvent WHERE meetID = :mid AND eventID = :eid");
                $ins = $conn->prepare("INSERT INTO tblmeetHasEvent (meetID, eventID) VALUES (:mid, :eid)");
                foreach ($ids as $eid) {
                    $chk->execute([':mid' => $meetID, ':eid' => $eid]);
                    if (!$chk->fetch()) {
                        $ins->execute([':mid' => $meetID, ':eid' => $eid]);
                    }
                }
                $notice = 'Event(s) added to meet.';
            }
        }
        elseif ($action === 'remove_event') {
            $eventID = (int)($_POST['eventID'] ?? 0);
            if ($eventID) {
                $del = $conn->prepare("DELETE FROM tblmeetHasEvent WHERE meetID = :mid AND eventID = :eid");
                $del->execute([':mid' => $meetID, ':eid' => $eventID]);
                $notice = 'Event removed from meet.';
            }
        }
        elseif ($action === 'search_students') {
            $studentSearchEventID = (int)($_POST['eventID'] ?? 0);
            $studentSearchQuery = trim($_POST['student_q'] ?? '');
            if ($studentSearchEventID && $studentSearchQuery !== '') {
                $like = "%$studentSearchQuery%";
                $sql = "SELECT userID, forename, surname, userName, emailAddress
                        FROM tbluser
                        WHERE role = 1 AND (
                            forename LIKE :like OR surname LIKE :like OR userName LIKE :like OR emailAddress LIKE :like
                        )
                        ORDER BY surname, forename
                        LIMIT 15";
                $stmt = $conn->prepare($sql);
                $stmt->execute([':like' => $like]);
                $studentMatches = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
        elseif ($action === 'add_time_entry') {
            $eventID = (int)($_POST['eventID'] ?? 0);
            $userID  = (int)($_POST['userID'] ?? 0);
            $time    = substr(trim($_POST['time'] ?? ''), 0, 8);
            if ($eventID && $userID) {
                $chk = $conn->prepare("SELECT 1 FROM tblmeetEventHasSwimmer WHERE userID = :uid AND meetID = :mid AND eventID = :eid");
                $chk->execute([':uid' => $userID, ':mid' => $meetID, ':eid' => $eventID]);
                if ($chk->fetch()) {
                    $up = $conn->prepare("UPDATE tblmeetEventHasSwimmer SET time = :t WHERE userID = :uid AND meetID = :mid AND eventID = :eid");
                    $up->execute([':t' => $time, ':uid' => $userID, ':mid' => $meetID, ':eid' => $eventID]);
                    $notice = 'Time updated for swimmer.';
                } else {
                    $ins = $conn->prepare("INSERT INTO tblmeetEventHasSwimmer (userID, meetID, eventID, time) VALUES (:uid, :mid, :eid, :t)");
                    $ins->execute([':uid' => $userID, ':mid' => $meetID, ':eid' => $eventID, ':t' => $time]);
                    $notice = 'Swimmer added to event.';
                }
            }
        }
        elseif ($action === 'update_time_entry') {
            $eventID = (int)($_POST['eventID'] ?? 0);
            $userID  = (int)($_POST['userID'] ?? 0);
            $time    = substr(trim($_POST['time'] ?? ''), 0, 8);
            if ($eventID && $userID) {
                $up = $conn->prepare("UPDATE tblmeetEventHasSwimmer SET time = :t WHERE userID = :uid AND meetID = :mid AND eventID = :eid");
                $up->execute([':t' => $time, ':uid' => $userID, ':mid' => $meetID, ':eid' => $eventID]);
                $notice = 'Time updated.';
            }
        }
        elseif ($action === 'remove_time_entry') {
            $eventID = (int)($_POST['eventID'] ?? 0);
            $userID  = (int)($_POST['userID'] ?? 0);
            if ($eventID && $userID) {
                $del = $conn->prepare("DELETE FROM tblmeetEventHasSwimmer WHERE userID = :uid AND meetID = :mid AND eventID = :eid");
                $del->execute([':uid' => $userID, ':mid' => $meetID, ':eid' => $eventID]);
                $notice = 'Swimmer removed from event.';
            }
        }
        elseif ($action === 'delete_meet') {
            $del = $conn->prepare("DELETE FROM tblmeet WHERE meetID = :id");
            $del->execute([':id' => $meetID]);
            echo '<div class="alert-success">Meet deleted. Close the editor and refresh the page.</div>';
            exit;
        }
    }

    // Load the meet
    $stmt = $conn->prepare("SELECT meetID, meetName, meetDate, meetInfo, external, course FROM tblmeet WHERE meetID = :id");
    $stmt->execute([':id' => $meetID]);
    $meet = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$meet) {
        echo '<div class="alert-fail">Meet not found.</div>';
        exit;
    }

    // Linked events
    $stmt = $conn->prepare("SELECT e.eventID, e.eventName, e.course, e.gender
                            FROM tblmeetHasEvent mhe
                            JOIN tblevent e ON e.eventID = mhe.eventID
                            WHERE mhe.meetID = :id
                            ORDER BY e.eventName, e.gender");
    $stmt->execute([':id' => $meetID]);
    $linkedEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Available events to add (course 'L'/'S')
    $stmt = $conn->prepare("SELECT eventID, eventName, course, gender
                            FROM tblevent
                            WHERE course = :course
                            ORDER BY eventName, gender");
    $stmt->execute([':course' => $meet['course']]);
    $eventOptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = 'Database Error: ' . $e->getMessage();
}
?>

<?php if ($error): ?>
    <div class="alert-fail"><?= h($error) ?></div>
<?php endif; ?>
<?php if ($notice): ?>
    <div class="alert-success"><?= h($notice) ?></div>
<?php endif; ?>

<!-- Top: Meet details -->
<div class="section">
    <h2>Edit Meet</h2>
    <form action="<?= $ACTION_URL ?>" method="post" autocomplete="off">
        <input type="hidden" name="action" value="update_meet">
        <input type="hidden" name="meetID" value="<?= (int)$meet['meetID'] ?>">

        <h3>Name of Meet</h3>
        <div class="form-row"><input type="text" name="meetName" value="<?= h($meet['meetName']) ?>" required></div>

        <h3>Course type</h3>
        <div class="form-row">
            <select name="course" required>
                <option value="L" <?= $meet['course']==='L'?'selected':'' ?>>Longcourse</option>
                <option value="S" <?= $meet['course']==='S'?'selected':'' ?>>Shortcourse</option>
            </select>
        </div>

        <h3>Date of Meet</h3>
        <div class="form-row"><input type="date" name="meetDate" value="<?= h($meet['meetDate']) ?>" required></div>

        <h3>Is this meet in school?</h3>
        <div class="form-row">
            <select name="external" required>
                <option value="N" <?= $meet['external']==='N'?'selected':'' ?>>Yes</option>
                <option value="Y" <?= $meet['external']==='Y'?'selected':'' ?>>No</option>
            </select>
        </div>

        <h3>Meet description</h3>
        <div class="form-row"><input type="text" name="meetInfo" maxlength="400" value="<?= h($meet['meetInfo']) ?>" required></div>

        <div class="form-row">
            <button type="submit">Save changes</button>
        </div>
    </form>
</div>

<!-- Events -->
<div class="section">
    <h2>Events in this meet</h2>

    <?php if (!$linkedEvents): ?>
        <p>No events added yet.</p>
    <?php else: ?>
        <table class="meets">
            <thead>
                <tr>
                    <th style="width:80px;">Event ID</th>
                    <th>Event</th>
                    <th style="width:105px;">Course</th>
                    <th style="width:90px;">Gender</th>
                    <th style="width:110px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($linkedEvents as $e): ?>
                <tr>
                    <td><?= (int)$e['eventID'] ?></td>
                    <td><?= h($e['eventName']) ?></td>
                    <td><?= ($e['course']==='L' ? 'Longcourse' : 'Shortcourse') ?></td>
                    <td><?= h($e['gender']) ?></td>
                    <td>
                        <form action="<?= $ACTION_URL ?>" method="post" style="display:inline;">
                            <input type="hidden" name="action" value="remove_event">
                            <input type="hidden" name="meetID" value="<?= (int)$meetID ?>">
                            <input type="hidden" name="eventID" value="<?= (int)$e['eventID'] ?>">
                            <button type="submit">Remove</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- Add multiple events via checkbox list (W3Schools pattern name="eventID[]") -->
    <form action="<?= $ACTION_URL ?>" method="post" class="add-events-row">
        <input type="hidden" name="action" value="add_events">
        <input type="hidden" name="meetID" value="<?= (int)$meetID ?>">

        <?php if (!$eventOptions): ?>
            <p>No available events for this course.</p>
        <?php else: ?>
            <div class="form-row" style="flex-wrap:wrap; gap:10px;">
                <?php foreach ($eventOptions as $opt): ?>
                    <label style="display:flex; align-items:center; gap:6px;">
                        <input type="checkbox" name="eventID[]" value="<?= (int)$opt['eventID'] ?>">
                        <span><?= h($opt['eventName']) ?> (<?= $opt['course']==='L' ? 'L' : 'S' ?>/<?= h($opt['gender']) ?>)</span>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="form-row">
                <button type="submit">Add Selected Event(s)</button>
            </div>
        <?php endif; ?>
    </form>
</div>

<!-- Times per event -->
<div class="section">
    <h2>Times (per swimmer)</h2>

    <?php if (!$linkedEvents): ?>
        <p>Add events first to manage times.</p>
    <?php endif; ?>

    <?php foreach ($linkedEvents as $e): ?>
        <?php
        $stmt = $conn->prepare("SELECT s.userID, s.forename, s.surname, s.userName, s.emailAddress, mes.time
                                FROM tblmeetEventHasSwimmer mes
                                JOIN tbluser s ON s.userID = mes.userID
                                WHERE mes.meetID = :mid AND mes.eventID = :eid
                                ORDER BY s.surname, s.forename");
        $stmt->execute([':mid' => $meetID, ':eid' => (int)$e['eventID']]);
        $swimmers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <div class="sub-section">
            <h3 class="event-subtitle">
                <?= h($e['eventName']) ?> (<?= $e['course']==='L' ? 'Long' : 'Short' ?> / <?= h($e['gender']) ?>)
            </h3>

            <?php if (!$swimmers): ?>
                <p>No swimmers added yet.</p>
            <?php else: ?>
                <table class="meets">
                    <thead>
                        <tr>
                            <th style="width:80px;">User ID</th>
                            <th>Swimmer</th>
                            <th style="width:120px;">Username</th>
                            <th style="width:160px;">Email</th>
                            <th style="width:120px;">Time</th>
                            <th style="width:170px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($swimmers as $row): ?>
                            <tr>
                                <td><?= (int)$row['userID'] ?></td>
                                <td><?= h($row['forename'] . ' ' . $row['surname']) ?></td>
                                <td><?= h($row['userName']) ?></td>
                                <td><?= h($row['emailAddress']) ?></td>
                                <td>
                                    <form action="<?= $ACTION_URL ?>" method="post" class="inline-form">
                                        <input type="hidden" name="action" value="update_time_entry">
                                        <input type="hidden" name="meetID" value="<?= (int)$meetID ?>">
                                        <input type="hidden" name="eventID" value="<?= (int)$e['eventID'] ?>">
                                        <input type="hidden" name="userID"  value="<?= (int)$row['userID'] ?>">
                                        <input type="text" name="time" value="<?= h($row['time']) ?>" placeholder="mm:ss.hh" maxlength="8" class="time-input">
                                        <button type="submit">Save</button>
                                    </form>
                                </td>
                                <td>
                                    <form action="<?= $ACTION_URL ?>" method="post" onsubmit="return confirm('Remove swimmer from this event?');" class="inline-form">
                                        <input type="hidden" name="action" value="remove_time_entry">
                                        <input type="hidden" name="meetID" value="<?= (int)$meetID ?>">
                                        <input type="hidden" name="eventID" value="<?= (int)$e['eventID'] ?>">
                                        <input type="hidden" name="userID"  value="<?= (int)$row['userID'] ?>">
                                        <button type="submit" class="btn-danger">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <!-- Add swimmer to this event -->
            <form action="<?= $ACTION_URL ?>" method="post" class="search-and-add">
                <input type="hidden" name="action" value="search_students">
                <input type="hidden" name="meetID" value="<?= (int)$meetID ?>">
                <input type="hidden" name="eventID" value="<?= (int)$e['eventID'] ?>">
                <input type="text" name="student_q" placeholder="Search student (name/username/email)" class="search-input">
                <button type="submit">Search</button>
            </form>
        </div>
    <?php endforeach; ?>
</div>

<!-- Danger zone -->
<div class="section">
    <h2 style="color:#9b1c1c;">Delete</h2>
    <form action="<?= $ACTION_URL ?>" method="post" onsubmit="return confirm('Delete this meet? This action cannot be undone.');">
        <input type="hidden" name="action" value="delete_meet">
        <input type="hidden" name="meetID" value="<?= (int)$meetID ?>">
        <button type="submit" class="btn-danger">Delete Meet</button>
    </form>
</div>