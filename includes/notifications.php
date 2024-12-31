<?php
require_once 'config.php';
require_once 'mail.php';

function sendShiftNotification($userId, $shiftId, $type) {
    $conn = getDBConnection();
    
    // Get shift and user details
    $stmt = $conn->prepare("
        SELECT s.*, u.email, u.username, t.team_name,
               (SELECT username FROM users WHERE user_id = s.approved_by) as approver_name
        FROM shifts s
        JOIN users u ON s.user_id = u.user_id
        JOIN teams t ON s.team_id = t.team_id
        WHERE s.shift_id = ?
    ");
    $stmt->execute([$shiftId]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$data) {
        error_log("Could not find shift data for ID: $shiftId");
        return false;
    }

    $subject = "";
    $message = "";
    
    switch ($type) {
        case 'created':
            $subject = SITE_NAME . ": New Shift Created - Pending Approval";
            $message = "Hello {$data['username']},\n\n";
            $message .= "Your shift has been created and is pending approval:\n";
            break;
            
        case 'approved':
            $subject = SITE_NAME . ": Shift Approved";
            $message = "Hello {$data['username']},\n\n";
            $message .= "Your shift has been approved by {$data['approver_name']}:\n";
            break;
            
        case 'rejected':
            $subject = SITE_NAME . ": Shift Rejected";
            $message = "Hello {$data['username']},\n\n";
            $message .= "Your shift has been rejected by {$data['approver_name']}:\n";
            break;
            
        default:
            error_log("Invalid notification type: $type");
            return false;
    }
    
    $message .= "\nTeam: {$data['team_name']}";
    $message .= "\nStart: " . date('Y-m-d H:i', strtotime($data['start_time']));
    $message .= "\nEnd: " . date('Y-m-d H:i', strtotime($data['end_time']));
    $message .= "\nType: " . ucfirst($data['shift_type']);
    $message .= "\n\nYou can view your shifts at: " . SITE_URL . "/shifts.php";
    
    return sendMail($data['email'], $subject, $message);
} 