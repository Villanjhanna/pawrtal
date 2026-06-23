<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$id   = (int)($_GET['id'] ?? 0);
$type = $_GET['type'] ?? '';

if (!$id || !in_array($type, ['lost', 'found'])) {
    die("Invalid request.");
}

$table = $type === 'lost' ? 'lost_reports' : 'found_reports';

/*
|--------------------------------------------------------------------------
| OPTION A - Soft Delete (Recommended)
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    UPDATE $table
    SET status = 'deleted'
    WHERE id = ? AND user_id = ?
");

$stmt->bind_param("ii", $id, $user_id);
$stmt->execute();

header("Location: my_reports.php");
exit();