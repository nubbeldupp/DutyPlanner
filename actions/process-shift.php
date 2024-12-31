<?php

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/notifications.php';

session_start();

// Check if user is authenticated
requireAuth();

// Validate input
$shiftId = filter_input(INPUT_POST, 'shift_id', FILTER_VALIDATE_INT);
$action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);
$userId = $_SESSION['user_id'];

// Map action to status
$statusMap = [
    'approve' => 'approved',
    'reject' => 'rejected'
];

// Validate inputs
if (!$shiftId || !$action || !isset($statusMap[$action])) {
    $_SESSION['error'] = "Invalid shift or action";
    header('Location: ../approve-shifts.php');
    exit;
}

$status = $statusMap[$action];

try {
    // Get database connection
    $conn = getDBConnection();

    // Check user's permission to approve/reject shift
    $stmt = $conn->prepare("
        SELECT s.team_id, ur.role_type 
        FROM shifts s
        JOIN user_roles ur ON s.team_id = ur.team_id
        WHERE s.shift_id = ? AND ur.user_id = ?
    ");
    $stmt->execute([$shiftId, $userId]);
    $userTeamRole = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$userTeamRole || !in_array($userTeamRole['role_type'], ['admin', 'teamlead'])) {
        $_SESSION['error'] = "You do not have permission to process this shift";
        header('Location: ../approve-shifts.php');
        exit;
    }

    // Update shift status
    $stmt = $conn->prepare("
        UPDATE shifts 
        SET status = ?, 
            approved_by = ?, 
            updated_at = NOW() 
        WHERE shift_id = ?
    ");

    if ($stmt->execute([$status, $userId, $shiftId])) {
        // Get shift details for notification
        $stmt = $conn->prepare("
            SELECT user_id, team_id, start_time, end_time 
            FROM shifts 
            WHERE shift_id = ?
        ");
        $stmt->execute([$shiftId]);
        $shiftDetails = $stmt->fetch(PDO::FETCH_ASSOC);

        // Send notification
        sendShiftNotification(
            $shiftDetails['user_id'], 
            $shiftId, 
            $status
        );

        $_SESSION['success'] = "Shift " . ucfirst($status);
    } else {
        $_SESSION['error'] = "Failed to process shift";
    }
} catch (PDOException $e) {
    // Log the error
    error_log("Shift processing error: " . $e->getMessage());
    $_SESSION['error'] = "Database error occurred";
}

// Redirect back to approve shifts page
header('Location: ../approve-shifts.php');
exit;