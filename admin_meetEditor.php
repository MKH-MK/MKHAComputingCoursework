<?php
session_start();
include_once('connection.php');
include_once('auth.php');
enforceSessionPolicies($conn);

if (!isset($_SESSION['role']) || $_SESSION['role'] != 2) {
    // unchanged access check
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

$messages = [];
$errors = [];

// Find meetID (GET edit or POST meetID)
$meetID = null;
if (isset($_GET['edit']) && ctype_digit((string)$_GET['edit'])) {
    $meetID = (int)$_GET['edit'];
} elseif (isset($_POST['meetID']) && ctype_digit((string)$_POST['meetID'])) {
    $meetID = (int)$_POST['meetID'];
} else {
    $errors[] = "No meet selected.";
}

// Helpers

// Time normalization helpers: parse flexible input -> centiseconds; format -> canonical MM:SS.hh
function parseTimeToCentiseconds(string $raw): ?int {
    $s = trim(str_replace(',', '.', $raw));
    if ($s === '') return null;

    $minutes = 0;
    $secPart = $s;

    // Minutes optional
    if (strpos($s, ':') !== false) {
        [$mStr, $secPart] = explode(':', $s, 2);
        if ($mStr === '' || !ctype_digit($mStr)) return null;
        $minutes = (int)$mStr;
        if ($minutes < 0) return null;
    }

    // Seconds + hundredths
    $seconds = 0;
    $hundredths = 0;
    if (strpos($secPart, '.') !== false) {
        [$secStr, $fracStr] = explode('.', $secPart, 2);
        if ($secStr === '' || !ctype_digit($secStr)) return null;
        if ($fracStr === '' || !ctype_digit($fracStr)) return null;
        $seconds = (int)$secStr;
        // Use first two digits; pad single digit like ".3" -> ".30"
        $hundredths = (int)substr($fracStr, 0, 2);
        if (strlen($fracStr) === 1) $hundredths *= 10;
    } else {
        if ($secPart === '' || !ctype_digit($secPart)) return null;
        $seconds = (int)$secPart;
    }

    if ($seconds < 0 || $seconds > 59) return null;

    return ($minutes * 60 + $seconds) * 100 + $hundredths;
}
function formatCentiseconds(int $cs): string {
    if ($cs < 0) $cs = 0;
    $m = intdiv($cs, 6000);
    $rem = $cs % 6000;
    $s = intdiv($rem, 100);
    $h = $rem % 100;
    return sprintf('%02d:%02d.%02d', $m, $s, $h);
}

function getMeet(PDO $conn, int $id) {
    $stmt = $conn->prepare("SELECT meetID, meetName, meetDate, meetInfo, external, course FROM tblmeet WHERE meetID = :id");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
function getAllowedEvents(PDO $conn, string $course) {
    // include eventType so we can render relay editor
    $stmt = $conn->prepare("SELECT eventID, eventName, gender, eventType FROM tblevent WHERE course = :c ORDER BY eventID ASC");
    $stmt->bindValue(':c', $course);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function getMeetEventIds(PDO $conn, int $meetID) {
    $stmt = $conn->prepare("SELECT eventID FROM tblmeetHasEvent WHERE meetID = :id");
    $stmt->bindValue(':id', $meetID, PDO::PARAM_INT);
    $stmt->execute();
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}
function getMeetEventsLabeled(PDO $conn, int $meetID) {
    // also select eventType for filtering times UI
    $sql = "SELECT e.eventID, e.eventName, e.gender, e.eventType
            FROM tblevent e
            INNER JOIN tblmeetHasEvent me ON me.eventID = e.eventID
            WHERE me.meetID = :id
            ORDER BY e.eventID ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':id', $meetID, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function getSwimmers(PDO $conn) {
    // include yearg to allow auto-fill of year-at-event in UI
    $stmt = $conn->prepare("SELECT userID, forename, surname, userName, gender, yearg FROM tbluser WHERE role = 1 ORDER BY surname, forename");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function getTimes(PDO $conn, int $meetID) {
    // individual times only
    // include ms.yeargAtEvent so admin can view/edit snapshot year group
    $sql = "SELECT ms.userID, u.forename, u.surname, ms.eventID, e.eventName, e.gender, ms.time, ms.yeargAtEvent
            FROM tblmeetEventHasSwimmer ms
            INNER JOIN tblevent e ON e.eventID = ms.eventID
            INNER JOIN tbluser u ON u.userID = ms.userID
            WHERE ms.meetID = :id
            ORDER BY e.eventID ASC, u.surname ASC, u.forename ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':id', $meetID, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Relay helpers adapted to installed schema (no relayTeamID; one team per event per meet)
function getRelayTeams(PDO $conn, int $meetID) {
    $sql = "SELECT rt.eventID, e.eventName, e.gender, rt.teamName, rt.totalTime
            FROM tblrelayTeam rt
            INNER JOIN tblevent e ON e.eventID = rt.eventID
            WHERE rt.meetID = :m
            ORDER BY e.eventID ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':m', $meetID, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function getRelayMembers(PDO $conn, int $meetID, int $eventID) {
    // removed m.splitTime to match schema without splitTime column
    $sql = "SELECT m.leg, u.userID, u.forename, u.surname, u.gender
            FROM tblrelayTeamMember m
            INNER JOIN tbluser u ON u.userID = m.userID
            WHERE m.meetID = :m AND m.eventID = :e
            ORDER BY m.leg ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':m', $meetID, PDO::PARAM_INT);
    $stmt->bindValue(':e', $eventID, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle POST actions
if ($meetID && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'update_meet') {
            $stmt = $conn->prepare("UPDATE tblmeet 
                SET meetName = :n, meetDate = :d, meetInfo = :i, external = :x, course = :c
                WHERE meetID = :id");
            $stmt->bindValue(':n', $_POST['meetName']);
            $stmt->bindValue(':d', $_POST['meetDate']);
            $stmt->bindValue(':i', $_POST['meetInfo']);
            $stmt->bindValue(':x', ($_POST['external'] === 'Y' ? 'Y' : 'N'));
            $stmt->bindValue(':c', ($_POST['course'] === 'S' ? 'S' : 'L'));
            $stmt->bindValue(':id', $meetID, PDO::PARAM_INT);
            $stmt->execute();

            $messages[] = "Meet details updated.";
        }

        if ($action === 'save_events') {
            $selected = isset($_POST['event_ids']) && is_array($_POST['event_ids']) ? array_map('intval', $_POST['event_ids']) : [];

            $meet = getMeet($conn, $meetID);
            if (!$meet) throw new Exception("Meet not found.");

            $allowed = getAllowedEvents($conn, $meet['course']);
            $allowedIds = array_map(fn($e) => (int)$e['eventID'], $allowed);

            $selected = array_values(array_intersect($selected, $allowedIds));

            $current = getMeetEventIds($conn, $meetID);

            $toAdd = array_diff($selected, $current);
            $toDel = array_diff($current, $selected);

            if (!empty($toAdd)) {
                $ins = $conn->prepare("INSERT INTO tblmeetHasEvent (meetID, eventID) VALUES (:m, :e)");
                foreach ($toAdd as $eid) {
                    $ins->bindValue(':m', $meetID, PDO::PARAM_INT);
                    $ins->bindValue(':e', $eid, PDO::PARAM_INT);
                    $ins->execute();
                }
            }
            if (!empty($toDel)) {
                $del = $conn->prepare("DELETE FROM tblmeetHasEvent WHERE meetID = :m AND eventID = :e");
                foreach ($toDel as $eid) {
                    $del->bindValue(':m', $meetID, PDO::PARAM_INT);
                    $del->bindValue(':e', $eid, PDO::PARAM_INT);
                    $del->execute();
                }
            }

            $messages[] = "Events updated.";
        }

        if ($action === 'add_time' || $action === 'update_time') {
            // individual time handler
            $eventID = isset($_POST['eventID']) ? (int)$_POST['eventID'] : 0;
            $userID  = isset($_POST['userID']) ? (int)$_POST['userID'] : 0;
            $timeRaw = isset($_POST['time']) ? trim($_POST['time']) : '';

            if ($eventID <= 0 || $userID <= 0 || $timeRaw === '') {
                throw new Exception("All fields are required for adding/updating a time.");
            }

            $chk = $conn->prepare("SELECT 1 FROM tblmeetHasEvent WHERE meetID = :m AND eventID = :e");
            $chk->bindValue(':m', $meetID, PDO::PARAM_INT);
            $chk->bindValue(':e', $eventID, PDO::PARAM_INT);
            $chk->execute();
            if (!$chk->fetchColumn()) throw new Exception("Event is not attached to the meet.");

            // Guard: eventType must be INDIV for this path
            $tstmt = $conn->prepare("SELECT eventType FROM tblevent WHERE eventID = :e LIMIT 1");
            $tstmt->bindValue(':e', $eventID, PDO::PARAM_INT);
            $tstmt->execute();
            $etype = $tstmt->fetchColumn();
            if ($etype !== 'INDIV') throw new Exception("Use the Relay section to add relay times.");

            // Normalize time to canonical MM:SS.hh
            $cs = parseTimeToCentiseconds($timeRaw);
            if ($cs === null) {
                throw new Exception("Invalid time format. Use a format like: SS.hh, M:SS.hh, MM:SS.");
            }
            $timeCanonical = formatCentiseconds($cs);

            if ($action === 'add_time') {
                // yeargAtEvent: optional input; if blank, snapshot swimmer's current yearg
                $postedYear = isset($_POST['yeargAtEvent']) ? trim($_POST['yeargAtEvent']) : '';
                if ($postedYear === '') {
                    $ystmt = $conn->prepare("SELECT yearg FROM tbluser WHERE userID = :u LIMIT 1");
                    $ystmt->bindValue(':u', $userID, PDO::PARAM_INT);
                    $ystmt->execute();
                    $snap = $ystmt->fetchColumn();
                    if ($snap === false || (int)$snap < 7 || (int)$snap > 13) {
                        throw new Exception("Could not snapshot swimmer's year group.");
                    }
                    $yeargAtEvent = (int)$snap;
                } else {
                    $yeargAtEvent = (int)$postedYear;
                    if ($yeargAtEvent < 7 || $yeargAtEvent > 13) {
                        throw new Exception("Year-at-event must be between 7 and 13.");
                    }
                }

                // store yeargAtEvent on insert
                $sql = "INSERT INTO tblmeetEventHasSwimmer (userID, meetID, eventID, time, yeargAtEvent)
                        VALUES (:u, :m, :e, :t, :yg)
                        ON DUPLICATE KEY UPDATE time = VALUES(time)";
                $stmt = $conn->prepare($sql);
                $stmt->bindValue(':u', $userID, PDO::PARAM_INT);
                $stmt->bindValue(':m', $meetID, PDO::PARAM_INT);
                $stmt->bindValue(':e', $eventID, PDO::PARAM_INT);
                $stmt->bindValue(':t', $timeCanonical);
                $stmt->bindValue(':yg', $yeargAtEvent, PDO::PARAM_INT);
                $stmt->execute();

                $messages[] = "Time added.";
            } else {
                // update_time: time always updates; yeargAtEvent may change (confirmation handled by JS)
                $updateYear = false;
                $newYear = null;
                if (isset($_POST['yeargAtEvent']) && $_POST['yeargAtEvent'] !== '') {
                    $newYear = (int)$_POST['yeargAtEvent'];
                    if ($newYear < 7 || $newYear > 13) {
                        throw new Exception("Year-at-event must be between 7 and 13.");
                    }
                    // compare with current value
                    $cstmt = $conn->prepare("SELECT yeargAtEvent FROM tblmeetEventHasSwimmer WHERE userID = :u AND meetID = :m AND eventID = :e");
                    $cstmt->bindValue(':u', $userID, PDO::PARAM_INT);
                    $cstmt->bindValue(':m', $meetID, PDO::PARAM_INT);
                    $cstmt->bindValue(':e', $eventID, PDO::PARAM_INT);
                    $cstmt->execute();
                    $currentYear = (int)$cstmt->fetchColumn();
                    if ($currentYear !== $newYear) {
                        $updateYear = true;
                    }
                }

                // update time (always), optionally update yeargAtEvent
                $usql = "UPDATE tblmeetEventHasSwimmer
                         SET time = :t" . ($updateYear ? ", yeargAtEvent = :yg" : "") . "
                         WHERE userID = :u AND meetID = :m AND eventID = :e";
                $ustmt = $conn->prepare($usql);
                $ustmt->bindValue(':t', $timeCanonical);
                if ($updateYear) $ustmt->bindValue(':yg', $newYear, PDO::PARAM_INT);
                $ustmt->bindValue(':u', $userID, PDO::PARAM_INT);
                $ustmt->bindValue(':m', $meetID, PDO::PARAM_INT);
                $ustmt->bindValue(':e', $eventID, PDO::PARAM_INT);
                $ustmt->execute();

                $messages[] = "Time updated.";
            }
        }

        if ($action === 'delete_time') {
            // individual delete
            $eventID = isset($_POST['eventID']) ? (int)$_POST['eventID'] : 0;
            $userID  = isset($_POST['userID']) ? (int)$_POST['userID'] : 0;
            if ($eventID <= 0 || $userID <= 0) throw new Exception("Invalid delete request.");

            $del = $conn->prepare("DELETE FROM tblmeetEventHasSwimmer WHERE userID = :u AND meetID = :m AND eventID = :e");
            $del->bindValue(':u', $userID, PDO::PARAM_INT);
            $del->bindValue(':m', $meetID, PDO::PARAM_INT);
            $del->bindValue(':e', $eventID, PDO::PARAM_INT);
            $del->execute();

            $messages[] = "Time removed.";
        }

        // Relay: create/update/delete adapted to installed schema (no relayTeamID)
        if ($action === 'create_relay') {
            $eventID = isset($_POST['relay_eventID']) ? (int)$_POST['relay_eventID'] : 0;
            // read team name directly
            $teamName = isset($_POST['teamName']) ? trim($_POST['teamName']) : '';
            $totalTimeRaw = isset($_POST['totalTime']) ? trim($_POST['totalTime']) : '';

            if ($eventID <= 0 || $teamName === '' || $totalTimeRaw === '') {
                throw new Exception("Relay event, team name, and total time are required.");
            }

            // Ensure event is attached and is relay
            $chk = $conn->prepare("SELECT e.eventType FROM tblmeetHasEvent me INNER JOIN tblevent e ON e.eventID = me.eventID WHERE me.meetID = :m AND me.eventID = :e");
            $chk->bindValue(':m', $meetID, PDO::PARAM_INT);
            $chk->bindValue(':e', $eventID, PDO::PARAM_INT);
            $chk->execute();
            $etype = $chk->fetchColumn();
            if ($etype !== 'RELAY') throw new Exception("Selected event is not a relay.");

            // Normalize relay total time to canonical MM:SS.hh
            $relayCs = parseTimeToCentiseconds($totalTimeRaw);
            if ($relayCs === null) {
                throw new Exception("Invalid relay total time. Use a format like: SS.hh, M:SS.hh, MM:SS.");
            }
            $totalTimeCanonical = formatCentiseconds($relayCs);

            // Create or update team (PK is meetID+eventID)
            $ins = $conn->prepare("INSERT INTO tblrelayTeam (meetID, eventID, teamName, totalTime) 
                                   VALUES (:m,:e,:n,:t)
                                   ON DUPLICATE KEY UPDATE teamName = VALUES(teamName), totalTime = VALUES(totalTime)");
            $ins->bindValue(':m', $meetID, PDO::PARAM_INT);
            $ins->bindValue(':e', $eventID, PDO::PARAM_INT);
            $ins->bindValue(':n', $teamName);
            $ins->bindValue(':t', $totalTimeCanonical);
            $ins->execute();

            $messages[] = "Relay team saved. Add members below.";
        }

        if ($action === 'add_member') {
            $eventID = isset($_POST['relay_eventID']) ? (int)$_POST['relay_eventID'] : 0;
            $userID  = isset($_POST['member_userID']) ? (int)$_POST['member_userID'] : 0;
            $leg     = isset($_POST['member_leg']) ? (int)$_POST['member_leg'] : 0;
            // split field removed from DB; ignore any provided split
            // $split   = isset($_POST['member_split']) ? trim($_POST['member_split']) : '';

            if ($eventID <= 0 || $userID <= 0 || $leg <= 0) {
                throw new Exception("Relay event, user, and leg are required.");
            }

            // Insert/Update member (leg unique per meet+event)
            $ins = $conn->prepare("INSERT INTO tblrelayTeamMember (meetID, eventID, userID, leg) 
                                   VALUES (:m,:e,:u,:l)
                                   ON DUPLICATE KEY UPDATE userID = VALUES(userID)");
            $ins->bindValue(':m', $meetID, PDO::PARAM_INT);
            $ins->bindValue(':e', $eventID, PDO::PARAM_INT);
            $ins->bindValue(':u', $userID, PDO::PARAM_INT);
            $ins->bindValue(':l', $leg, PDO::PARAM_INT);
            $ins->execute();

            $messages[] = "Relay member saved.";
        }

        if ($action === 'delete_relay') {
            $eventID = isset($_POST['relay_eventID']) ? (int)$_POST['relay_eventID'] : 0;
            if ($eventID <= 0) throw new Exception("Invalid relay delete request.");

            $del = $conn->prepare("DELETE FROM tblrelayTeam WHERE meetID = :m AND eventID = :e");
            $del->bindValue(':m', $meetID, PDO::PARAM_INT);
            $del->bindValue(':e', $eventID, PDO::PARAM_INT);
            $del->execute();

            $messages[] = "Relay team deleted.";
        }

    } catch (Exception $ex) {
        $errors[] = $ex->getMessage();
    } catch (PDOException $ex) {
        $errors[] = "Database error: " . $ex->getMessage();
    }
}

// Load data for render
$meet = $meetID ? getMeet($conn, $meetID) : null;
$allowedEvents = ($meet && isset($meet['course'])) ? getAllowedEvents($conn, $meet['course']) : [];
$currentEventIds = $meetID ? getMeetEventIds($conn, $meetID) : [];
$meetEventsForTimes = $meetID ? getMeetEventsLabeled($conn, $meetID) : [];
$swimmers = getSwimmers($conn);
$times = $meetID ? getTimes($conn, $meetID) : [];

// Relay teams for this meet
$relayTeams = $meetID ? getRelayTeams($conn, $meetID) : [];

// Build swimmer options for JS (id, label, gender, yearg)
$swimmerOptions = array_map(function($sw) {
    return [
        'id' => (int)$sw['userID'],
        'label' => $sw['surname'] . ', ' . $sw['forename'] . ' [' . $sw['userName'] . ']',
        'gender' => $sw['gender'],
        'yearg' => (int)$sw['yearg']
    ];
}, $swimmers);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Oundle School Swim Team - Meet Editor</title>
    <meta name="viewport" content="width=1024, initial-scale=1">
    <link rel="stylesheet" href="style.css">

</head>
<body>
<?php include 'navbar.php'; ?>

<div class="main-content">
    <div class="page-title">Meet Editor</div>

    <div class="section">
        <a href="admin_meetList.php" class="btn">Back to Meet List</a>

        <?php foreach ($messages as $msg): ?>
            <div class="alert-success"><?= htmlspecialchars($msg) ?></div>
        <?php endforeach; ?>
        <?php foreach ($errors as $err): ?>
            <div class="alert-fail"><?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>

        <?php if ($meet): ?>
        <form method="post" class="form-section form-section--wide">
            <h2>Edit Meet Details</h2>
            <input type="hidden" name="meetID" value="<?= (int)$meet['meetID'] ?>">

            <div class="form-row">
                <input type="text" name="meetName" value="<?= htmlspecialchars($meet['meetName']) ?>" placeholder="Name of Meet" required>
            </div>

            <h3>Course type:</h3>
            <div class="form-row">
                <select name="course" class="input" required>
                    <option value="L" <?= $meet['course']==='L'?'selected':'' ?>>Longcourse</option>
                    <option value="S" <?= $meet['course']==='S'?'selected':'' ?>>Shortcourse</option>
                </select>
            </div>

            <h3>Date of Meet:</h3>
            <div class="form-row">
                <input type="date" name="meetDate" value="<?= htmlspecialchars($meet['meetDate']) ?>" required>
            </div>

            <h3>External:</h3>
            <div class="form-row">
                <select name="external" class="input" required>
                    <option value="N" <?= $meet['external']==='N'?'selected':'' ?>>No</option>
                    <option value="Y" <?= $meet['external']==='Y'?'selected':'' ?>>Yes</option>
                </select>
            </div>

            <input type="text" name="meetInfo" value="<?= htmlspecialchars($meet['meetInfo']) ?>" placeholder="Meet description (400 characters max)" maxlength="400" required>

            <div class="form-row form-row--center">
                <input type="hidden" name="action" value="update_meet">
                <button type="submit">Save Details</button>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <?php if ($meet): ?>
    <div class="section">
        <h2>Add/Remove Events (<?= $meet['course']==='L' ? 'Longcourse' : 'Shortcourse' ?>)</h2>

        <form method="post">
            <input type="hidden" name="meetID" value="<?= (int)$meet['meetID'] ?>">
            <input type="hidden" name="action" value="save_events">

            <table class="meets events-table">
                <tbody>
                <?php if (empty($allowedEvents)): ?>
                    <tr><td>No events available for this course.</td></tr>
                <?php else: ?>
                    <?php foreach (array_chunk($allowedEvents, 3) as $row): ?>
                        <tr>
                            <?php for ($i=0; $i<3; $i++): ?>
                                <td>
                                    <?php if (isset($row[$i])): 
                                        $ev = $row[$i];
                                        $eid = (int)$ev['eventID'];
                                        $label = $ev['eventName'] . ' (' . $ev['gender'] . ')';
                                        if ($ev['eventType'] === 'RELAY') $label .= ' [Relay]';
                                    ?>
                                    <label class="check-item">
                                        <input type="checkbox" name="event_ids[]" value="<?= $eid ?>" <?= in_array($eid, $currentEventIds, true) ? 'checked' : '' ?>>
                                        <?= htmlspecialchars($label) ?>
                                    </label>
                                    <?php endif; ?>
                                </td>
                            <?php endfor; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <div class="form-row form-row--center mt-12">
                <button type="submit">Save Events</button>
            </div>
        </form>
    </div>

    <div class="section">
        <h2>Times</h2>

        <div class="sub-section">
            <h3 class="event-subtitle">Add a Time (Individual Events)</h3>
            <form method="post" class="inline-form wrap" id="addTimeForm">
                <input type="hidden" name="meetID" value="<?= (int)$meet['meetID'] ?>">
                <input type="hidden" name="action" value="add_time">

                <select name="eventID" id="eventSelect" class="student-select" required>
                    <option value="">Select event</option>
                    <?php foreach ($meetEventsForTimes as $mev): ?>
                        <?php if ($mev['eventType'] === 'INDIV'): ?>
                            <option value="<?= (int)$mev['eventID'] ?>" data-gender="<?= htmlspecialchars($mev['gender']) ?>">
                                <?= htmlspecialchars($mev['eventName'] . ' (' . $mev['gender'] . ')') ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>

                <input type="text" id="swimmerSearch" class="student-select" list="swimmerList" placeholder="Type to search swimmer" autocomplete="off">
                <datalist id="swimmerList"></datalist>
                <input type="hidden" name="userID" id="userIDHidden" value="" />

                <!-- year-at-event input (optional; will auto-fill on swimmer select if blank) -->
                <input type="number" name="yeargAtEvent" id="yeargAtEventInput" class="student-select" placeholder="Year at event (7-13)" min="7" max="13">

                <input type="text" name="time" class="time-input" placeholder="mm:ss.ss" maxlength="8" required>
                <button type="submit" class="btn">Add</button>
            </form>
        </div>

        <div class="sub-section mt-12">
            <h3 class="event-subtitle">Existing Times</h3>
            <table class="meets">
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Swimmer</th>
                        <th>Year</th>
                        <th>Time</th>
                        <th class="col-actions-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($times)): ?>
                    <tr><td colspan="5">No times recorded yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($times as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['eventName'] . ' (' . $row['gender'] . ')') ?></td>
                            <td><?= htmlspecialchars($row['surname'] . ', ' . $row['forename']) ?></td>
                            <td>
                                <form method="post" class="inline-form js-year-update-form">
                                    <input type="hidden" name="meetID" value="<?= (int)$meet['meetID'] ?>">
                                    <input type="hidden" name="eventID" value="<?= (int)$row['eventID'] ?>">
                                    <input type="hidden" name="userID" value="<?= (int)$row['userID'] ?>">
                                    <!-- editable yeargAtEvent input + original hidden for JS comparison -->
                                    <input type="hidden" name="original_yeargAtEvent" value="<?= (int)$row['yeargAtEvent'] ?>">
                                    <input type="number" name="yeargAtEvent" class="student-select" value="<?= (int)$row['yeargAtEvent'] ?>" min="7" max="13" required>
                            </td>
                            <td>
                                    <input type="text" name="time" class="time-input" value="<?= htmlspecialchars($row['time']) ?>" maxlength="8" required>
                            </td>
                            <td class="col-actions-right">
                                    <button type="submit" name="action" value="update_time" class="btn">Update</button>
                                    <button type="submit" name="action" value="delete_time" class="btn btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Relay Teams editor adapted to installed schema -->
    <div class="section">
        <h2>Relay Teams</h2>

        <div class="sub-section">
            <h3 class="event-subtitle">Create Relay Team</h3>
            <form method="post" class="inline-form wrap">
                <input type="hidden" name="meetID" value="<?= (int)$meet['meetID'] ?>">
                <input type="hidden" name="action" value="create_relay">

                <select name="relay_eventID" class="student-select" required>
                    <option value="">Select relay event</option>
                    <?php foreach ($meetEventsForTimes as $mev): ?>
                        <?php if ($mev['eventType'] === 'RELAY'): ?>
                            <option value="<?= (int)$mev['eventID'] ?>">
                                <?= htmlspecialchars($mev['eventName'] . ' (' . $mev['gender'] . ') [Relay]') ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>

                <input type="text" name="teamName" class="student-select" placeholder="Team Name" required>
                <input type="text" name="totalTime" class="time-input" placeholder="Total Time mm:ss.ss" maxlength="8" required>
                <button type="submit" class="btn">Create</button>
            </form>
        </div>

        <div class="sub-section mt-12">
            <h3 class="event-subtitle">Teams & Members</h3>
            <table class="meets">
                <thead>
                    <tr>
                        <th>Relay Event</th>
                        <th>Team</th>
                        <th>Total Time</th>
                        <th>Members (add below)</th>
                        <th class="col-actions-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($relayTeams)): ?>
                    <tr><td colspan="5">No relay teams yet.</td></tr>
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
                                $mem->bindValue(':m', (int)$meet['meetID'], PDO::PARAM_INT);
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
                            <td class="col-actions-right">
                                <form method="post" class="inline-form">
                                    <input type="hidden" name="action" value="delete_relay">
                                    <input type="hidden" name="meetID" value="<?= (int)$meet['meetID'] ?>">
                                    <input type="hidden" name="relay_eventID" value="<?= (int)$rt['eventID'] ?>">
                                    <button type="submit" class="btn btn-danger">Delete Team</button>
                                </form>
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

<script>
const SWIMMERS = <?= json_encode($swimmerOptions, JSON_UNESCAPED_UNICODE) ?>;

const eventSelect = document.getElementById('eventSelect');
const swimmerSearch = document.getElementById('swimmerSearch');
const swimmerList = document.getElementById('swimmerList');
const userIDHidden = document.getElementById('userIDHidden');
const addTimeForm = document.getElementById('addTimeForm');
const yeargAtEventInput = document.getElementById('yeargAtEventInput');

// Auto-build swimmer datalist, with yearg for auto-fill
function rebuildSwimmerDatalist(requiredGender) {
    while (swimmerList.firstChild) swimmerList.removeChild(swimmerList.firstChild);
    // For individual events:
    // - Male event: include swimmers with gender 'M' and 'MIX'
    // - Female event: include only 'F'
    // - If no gender provided, allow all
    SWIMMERS.forEach(sw => {
        const isEligible =
            !requiredGender ||
            (requiredGender === 'M' && (sw.gender === 'M' || sw.gender === 'MIX')) ||
            (requiredGender === 'F' && sw.gender === 'F');
        if (isEligible) {
            const opt = document.createElement('option');
            opt.value = sw.label;
            opt.dataset.userid = String(sw.id);
            opt.dataset.yearg = String(sw.yearg);
            swimmerList.appendChild(opt);
        }
    });
    swimmerSearch.value = '';
    userIDHidden.value = '';
    if (yeargAtEventInput) yeargAtEventInput.value = '';
}

function handleEventChange() {
    const selected = eventSelect.options[eventSelect.selectedIndex];
    const evtGender = selected ? (selected.dataset.gender || '') : '';
    rebuildSwimmerDatalist(evtGender);
}

// Resolve selected swimmer, set hidden userID, and auto-fill yeargAtEvent if blank
function handleSwimmerChosen() {
    const label = swimmerSearch.value;
    let chosenId = '';
    let chosenYear = '';
    const opts = swimmerList.querySelectorAll('option');
    for (const opt of opts) {
        if (opt.value === label) {
            chosenId = opt.dataset.userid || '';
            chosenYear = opt.dataset.yearg || '';
            break;
        }
    }
    if (!chosenId) {
        const match = SWIMMERS.find(sw => sw.label === label);
        if (match) {
            chosenId = String(match.id);
            chosenYear = String(match.yearg);
        }
    }
    userIDHidden.value = chosenId || '';
    // Auto-fill year-at-event if empty
    if (yeargAtEventInput && (!yeargAtEventInput.value || yeargAtEventInput.value.trim() === '') && chosenYear) {
        yeargAtEventInput.value = chosenYear;
    }
}

// Prevent add if swimmer not selected and validate year range
function handleAddFormSubmit(e) {
    if (!userIDHidden.value) {
        e.preventDefault();
        alert('Please select a swimmer from the list (type and choose a suggestion).');
        swimmerSearch.focus();
        return;
    }
    if (yeargAtEventInput && yeargAtEventInput.value) {
        const yg = parseInt(yeargAtEventInput.value, 10);
        if (isNaN(yg) || yg < 7 || yg > 13) {
            e.preventDefault();
            alert('Year-at-event must be between 7 and 13.');
            yeargAtEventInput.focus();
        }
    }
}

// Confirm popup on updating year-at-event in Existing Times
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('form.js-year-update-form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            const original = form.querySelector('input[name="original_yeargAtEvent"]')?.value || '';
            const current = form.querySelector('input[name="yeargAtEvent"]')?.value || '';
            if (original && current && original !== current) {
                const ok = confirm('You are changing the year group at this event for this result. Click OK to confirm.');
                if (!ok) e.preventDefault();
            }
        });
    });
});

if (eventSelect) {
    eventSelect.addEventListener('change', handleEventChange);
    handleEventChange();
}
if (swimmerSearch) {
    swimmerSearch.addEventListener('change', handleSwimmerChosen);
    swimmerSearch.addEventListener('blur', handleSwimmerChosen);
}
if (addTimeForm) {
    addTimeForm.addEventListener('submit', handleAddFormSubmit);
}
</script>
</body>
</html>