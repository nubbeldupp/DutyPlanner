<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
session_start();

requireAuth();

if (!isTeamManager($_SESSION['user_id'])) {
    header('Location: ../dashboard.php');
    exit;
}

$teamId = filter_input(INPUT_GET, 'team_id', FILTER_VALIDATE_INT);
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');
$format = $_GET['format'] ?? 'csv';

$conn = getDBConnection();

// Get team name
$stmt = $conn->prepare("SELECT team_name FROM teams WHERE team_id = ?");
$stmt->execute([$teamId]);
$teamName = $stmt->fetchColumn();

// Get shift data
$stmt = $conn->prepare("
    SELECT 
        u.username,
        s.shift_type,
        s.start_time,
        s.end_time,
        s.status,
        TIMESTAMPDIFF(HOUR, s.start_time, s.end_time) as duration,
        (SELECT username FROM users WHERE user_id = s.approved_by) as approved_by
    FROM shifts s
    JOIN users u ON s.user_id = u.user_id
    WHERE s.team_id = ?
    AND s.start_time BETWEEN ? AND ?
    ORDER BY s.start_time ASC
");
$stmt->execute([$teamId, $startDate, $endDate]);
$shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set headers for file download
$filename = sprintf('shift_report_%s_%s_%s.%s', 
    $teamName,
    date('Y-m-d', strtotime($startDate)),
    date('Y-m-d', strtotime($endDate)),
    $format
);

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create output handle
$output = fopen('php://output', 'w');

// Write headers
fputcsv($output, [
    'Team Member',
    'Shift Type',
    'Start Time',
    'End Time',
    'Duration (Hours)',
    'Status',
    'Approved By'
]);

// Write data
foreach ($shifts as $shift) {
    fputcsv($output, [
        $shift['username'],
        ucfirst($shift['shift_type']),
        date('Y-m-d H:i', strtotime($shift['start_time'])),
        date('Y-m-d H:i', strtotime($shift['end_time'])),
        $shift['duration'],
        ucfirst($shift['status']),
        $shift['approved_by'] ?? 'N/A'
    ]);
}

fclose($output);
exit; 