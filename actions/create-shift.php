<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
session_start();

requireAuth();

// Enable error logging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Log all POST data for debugging
error_log("POST Data: " . print_r($_POST, true));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Support multiple team selection
    $teamIds = $_POST['team_ids'] ?? [];
    
    // Validate team IDs
    $teamIds = array_filter($teamIds, function($id) {
        return filter_var($id, FILTER_VALIDATE_INT) !== false;
    });
    
    $shiftType = $_POST['shift_type'] ?? null;
    $startDate = $_POST['start_date'] ?? null;
    $endDate = $_POST['end_date'] ?? null;
    $startTime = $_POST['start_time'] ?? '00:00';
    $endTime = $_POST['end_time'] ?? '23:59';
    
    // Combine date and time
    $startTime = $startDate . ' ' . $startTime . ':00';
    $endTime = $endDate . ' ' . $endTime . ':59';
    
    // Detailed input validation with logging
    error_log("Team IDs: " . print_r($teamIds, true));
    error_log("Shift Type: $shiftType");
    error_log("Start Time: $startTime");
    error_log("End Time: $endTime");
    
    // Validate inputs
    $validationErrors = [];
    if (empty($teamIds)) $validationErrors[] = "No teams selected";
    if (!in_array($shiftType, ['regular', 'adhoc'])) $validationErrors[] = "Invalid shift type";
    if (!$startDate) $validationErrors[] = "Start date is required";
    if (!$endDate) $validationErrors[] = "End date is required";
    
    if (!empty($validationErrors)) {
        $_SESSION['error'] = implode(", ", $validationErrors);
        error_log("Validation Errors: " . $_SESSION['error']);
        header('Location: ../shifts.php');
        exit;
    }

    // Validate shift duration
    $startTimestamp = strtotime($startTime);
    $endTimestamp = strtotime($endTime);
    
    if ($startTimestamp === false || $endTimestamp === false) {
        $_SESSION['error'] = "Invalid start or end time";
        error_log("Timestamp conversion failed. Start: $startTime, End: $endTime");
        header('Location: ../shifts.php');
        exit;
    }
    
    $duration = ($endTimestamp - $startTimestamp) / 3600; // in hours
    
    if ($shiftType === 'regular') {
        // Calculate exact days
        $daysDifference = floor(($endTimestamp - $startTimestamp) / (24 * 3600));
        
        // Allow shifts between 6.5 and 7.5 days to account for slight variations
        if ($daysDifference < 6 || $daysDifference > 7) {
            $_SESSION['error'] = "Regular shifts must be exactly 7 days long. Current duration: $daysDifference days";
            error_log("Regular shift duration invalid: $daysDifference days");
            header('Location: ../shifts.php?debug=1');
            exit;
        }
    }
    if ($shiftType === 'adhoc' && ($duration < 1 || $duration > 24)) {
        $_SESSION['error'] = "Ad-hoc shifts must be between 1 and 24 hours";
        error_log("Ad-hoc shift duration invalid: $duration hours");
        header('Location: ../shifts.php');
        exit;
    }

    $conn = getDBConnection();
    
    // Prepare to track created shifts and potential errors
    $createdShifts = 0;
    $failedTeams = [];

    // Create shifts for each selected team
    foreach ($teamIds as $teamId) {
        // Create the shift
        $stmt = $conn->prepare("
            INSERT INTO shifts (team_id, user_id, shift_type, start_time, end_time, status)
            VALUES (?, ?, ?, ?, ?, 'pending')
        ");
        
        try {
            $result = $stmt->execute([
                $teamId, 
                $_SESSION['user_id'], 
                $shiftType, 
                $startTime, 
                $endTime
            ]);
            
            if ($result) {
                $createdShifts++;
                error_log("Shift created successfully for team ID: $teamId");
            } else {
                $failedTeams[] = $teamId;
                error_log("Failed to create shift for team ID: $teamId");
            }
        } catch (PDOException $e) {
            $failedTeams[] = $teamId;
            error_log("PDO Exception for team ID $teamId: " . $e->getMessage());
        }
    }

    // Set appropriate session message
    if ($createdShifts > 0) {
        $_SESSION['success'] = "$createdShifts shift(s) created successfully";
        if (!empty($failedTeams)) {
            $_SESSION['warning'] = "Failed to create shifts for teams: " . implode(', ', $failedTeams);
        }
    } else {
        $_SESSION['error'] = "Failed to create any shifts. " . 
            (!empty($failedTeams) ? "Overlapping shifts or database errors." : "");
    }
}

header('Location: ../shifts.php');
exit;