<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../auth/login.php"); exit(); }
$user_id = (int)$_SESSION['user_id'];

$id   = isset($_GET['id'])   ? (int)$_GET['id']   : 0;
$type = isset($_GET['type']) ? $_GET['type']       : '';

if (!$id || !in_array($type, ['lost', 'found'])) {
    header("Location: my_reports.php"); exit();
}

if ($type === 'lost') {

    // Verify ownership
    $report = $conn->query("
        SELECT id FROM lost_reports WHERE id=$id AND user_id=$user_id LIMIT 1
    ")->fetch_assoc();

    if ($report) {
        // 1. Notify finders of any confirmed/pending matches that it's been removed
        $affected = $conn->query("
            SELECT m.id, f.user_id AS finder_id, f.id AS f_id
            FROM matches m
            JOIN found_reports f ON m.found_report_id = f.id
            WHERE m.lost_report_id = $id
              AND m.status IN ('pending','confirmed')
        ")->fetch_all(MYSQLI_ASSOC);

        foreach ($affected as $m) {
            $finder_id = (int)$m['finder_id'];
            $f_id      = (int)$m['f_id'];
            $msg = $conn->real_escape_string(
                "A lost pet report that was matched with your found report has been removed by the owner. "
              . "Your found report is still active and may match other lost pets."
            );
            $conn->query("
                INSERT INTO notifications (user_id, type, message, link)
                VALUES ($finder_id, 'match_cancelled', '$msg',
                        '../public/view_found.php?id=$f_id')
            ");
        }

        // 2. Delete all matches for this lost report
        $conn->query("DELETE FROM matches WHERE lost_report_id=$id");

        // 3. Delete any claims on lost reports (if applicable)
        // (claims are on found reports, so nothing to do here)

        // 4. Delete the report
        $conn->query("DELETE FROM lost_reports WHERE id=$id AND user_id=$user_id");
    }

    header("Location: my_reports.php?tab=lost&deleted=1"); exit();

} else {

    // Verify ownership
    $report = $conn->query("
        SELECT id FROM found_reports WHERE id=$id AND user_id=$user_id LIMIT 1
    ")->fetch_assoc();

    if ($report) {
        // 1. Notify owners of any confirmed/pending matches
        $affected = $conn->query("
            SELECT m.id, l.user_id AS owner_id, l.id AS l_id, l.pet_name
            FROM matches m
            JOIN lost_reports l ON m.lost_report_id = l.id
            WHERE m.found_report_id = $id
              AND m.status IN ('pending','confirmed')
        ")->fetch_all(MYSQLI_ASSOC);

        foreach ($affected as $m) {
            $owner_id = (int)$m['owner_id'];
            $pet      = $conn->real_escape_string($m['pet_name']);
            $msg = $conn->real_escape_string(
                "The found report matched with \"$pet\" has been removed by the finder. "
              . "Your lost report is still active and will continue to be matched."
            );
            $conn->query("
                INSERT INTO notifications (user_id, type, message, link)
                VALUES ($owner_id, 'match_cancelled', '$msg', '../dashboard/matches.php')
            ");
        }

        // 2. Delete all matches for this found report
        $conn->query("DELETE FROM matches WHERE found_report_id=$id");

        // 3. Delete any claims on this found report
        $conn->query("DELETE FROM claims WHERE found_report_id=$id");

        // 4. Delete the report
        $conn->query("DELETE FROM found_reports WHERE id=$id AND user_id=$user_id");
    }

    header("Location: my_reports.php?tab=found&deleted=1"); exit();
}