<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
session_start();

requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['shift_id'])) {
    $shiftId = filter_input(INPUT_POST, 'shift_id', FILTER_VALIDATE_INT);
    
    if ($shiftId) {
        $conn = getDBConnection();
        
        // Only allow deletion of pending shifts by the shift owner
        $stmt = $conn->prepare("
            DELETE FROM shifts 
            WHERE shift_id = ? 
            AND user_id = ? 
            AND status = 'pending'
        ");
        
        if ($stmt->execute([$shiftId, $_SESSION['user_id']])) {
            $_SESSION['success'] = "Shift deleted successfully";
        } else {
            $_SESSION['error'] = "Failed to delete shift";
        }
    }
}

header('Location: ../shifts.php');
exit; 