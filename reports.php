<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
session_start();

requireAuth();
$pageTitle = 'Shift Reports';
$userId = $_SESSION['user_id'];
$conn = getDBConnection();

try {
    // Attempt to get user's role and teams
    $stmt = $conn->prepare("
        SELECT DISTINCT 
            CASE 
                WHEN ur.role_type = 'admin' THEN 'admin'
                WHEN ur.role_type = 'teamlead' THEN 'teamlead'
                ELSE 'teammember'
            END as role_name, 
            t.team_id, 
            t.team_name
        FROM user_roles ur
        JOIN teams t ON ur.team_id = t.team_id
        WHERE ur.user_id = ?
    ");
    $stmt->execute([$userId]);
    $userTeams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Determine which shifts the user can view based on their role
    $shiftsQuery = "
        SELECT 
            s.shift_id,
            s.user_id,
            u.username,
            t.team_name,
            s.start_time,
            s.end_time,
            s.status,
            CASE 
                WHEN s.status = 'pending' THEN '#ffc107'  -- warning yellow
                WHEN s.status = 'approved' THEN '#28a745'  -- success green
                WHEN s.status = 'rejected' THEN '#dc3545'  -- danger red
                ELSE '#6c757d'  -- secondary grey
            END as color,
            TIMESTAMPDIFF(HOUR, s.start_time, s.end_time) as duration_hours
        FROM shifts s
        JOIN users u ON s.user_id = u.user_id
        JOIN teams t ON s.team_id = t.team_id
        WHERE 1=1
    ";

    $params = [];
    $isAdmin = false;

    foreach ($userTeams as $team) {
        if ($team['role_name'] === 'admin') {
            // Admin can view shifts for all teams
            $isAdmin = true;
            break;
        }
    }

    // Add team filter for non-admin users
    if (!$isAdmin) {
        $teamConditions = [];
        foreach ($userTeams as $team) {
            $teamConditions[] = "t.team_id = ?";
            $params[] = $team['team_id'];
        }
        $shiftsQuery .= " AND (" . implode(' OR ', $teamConditions) . ")";
    }

    // Add date range filter
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

    $shiftsQuery .= " AND s.start_time BETWEEN ? AND ?";
    $params[] = $startDate . ' 00:00:00';
    $params[] = $endDate . ' 23:59:59';

    // Add status filter
    $status = isset($_GET['status']) ? $_GET['status'] : 'all';
    if ($status !== 'all') {
        $shiftsQuery .= " AND s.status = ?";
        $params[] = $status;
    }

    $shiftsQuery .= " ORDER BY s.start_time DESC";

    $stmt = $conn->prepare($shiftsQuery);
    $stmt->execute($params);
    $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Convert shifts to FullCalendar format
    $calendarEvents = array_map(function($shift) {
        return [
            'id' => $shift['shift_id'],
            'title' => $shift['username'] . ' (' . $shift['team_name'] . ')',
            'start' => $shift['start_time'],
            'end' => $shift['end_time'],
            'color' => $shift['color'],
            'extendedProps' => [
                'status' => $shift['status'],
                'userId' => $shift['user_id'],
                'duration' => round($shift['duration_hours'], 2)
            ]
        ];
    }, $shifts);

    // Calculate total duration and shift statistics
    $totalDuration = array_sum(array_column($shifts, 'duration_hours'));
    $shiftStatusCounts = array_count_values(array_column($shifts, 'status'));

    // Ensure default values for status counts
    foreach (['pending', 'approved', 'rejected'] as $status) {
        if (!isset($shiftStatusCounts[$status])) {
            $shiftStatusCounts[$status] = 0;
        }
    }

    // Include the header
    include 'components/header.php';
} catch (PDOException $e) {
    // Log the error
    error_log("Database error in reports: " . $e->getMessage());
    
    // Redirect with error message
    $_SESSION['error'] = "A database error occurred. Please contact support.";
    header('Location: dashboard.php');
    exit;
}
?>

<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Shift Reports</h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-control" 
                                   value="<?php echo htmlspecialchars($startDate); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" class="form-control" 
                                   value="<?php echo htmlspecialchars($endDate); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="all" selected>All Shifts</option>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                        <div class="col-12 mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search me-1"></i>Apply Filters
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">Shift Statistics</h5>
                    <div class="d-flex justify-content-between mb-2">
                        <div>Total Shifts</div>
                        <div><?php echo count($shifts); ?></div>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <div>Total Hours</div>
                        <div><?php echo number_format($totalDuration, 2); ?></div>
                    </div>
                    <?php foreach (['pending', 'approved', 'rejected'] as $shiftStatus): ?>
                        <div class="d-flex justify-content-between">
                            <div>
                                <span class="badge" style="background-color: 
                                    <?php 
                                    echo $shiftStatus === 'pending' ? '#ffc107' : 
                                         ($shiftStatus === 'approved' ? '#28a745' : '#dc3545'); 
                                    ?>">
                                    <?php echo ucfirst($shiftStatus); ?>
                                </span>
                            </div>
                            <div><?php echo $shiftStatusCounts[$shiftStatus]; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Shift Calendar</h5>
                </div>
                <div class="card-body">
                    <div id="calendar"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Shifts Overview</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($shifts)): ?>
                        <div class="alert alert-info text-center">
                            <i class="bi bi-info-circle me-2"></i>
                            No shifts found for the selected period.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Team</th>
                                        <th>Start Time</th>
                                        <th>End Time</th>
                                        <th>Duration</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($shifts as $shift): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($shift['username']); ?></td>
                                            <td><?php echo htmlspecialchars($shift['team_name']); ?></td>
                                            <td><?php echo date('M j, Y g:i A', strtotime($shift['start_time'])); ?></td>
                                            <td><?php echo date('M j, Y g:i A', strtotime($shift['end_time'])); ?></td>
                                            <td><?php echo $shift['duration_hours']; ?> hrs</td>
                                            <td>
                                                <span class="badge" style="background-color: <?php echo $shift['color']; ?>">
                                                    <?php echo ucfirst($shift['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
// Include the footer
include 'components/footer.php'; 
?>

<link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.10.2/main.min.css' rel='stylesheet' />
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.10.2/main.min.js'></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Prepare calendar events
    const calendarEvents = <?php echo json_encode(array_map(function($shift) {
        return [
            'id' => $shift['shift_id'],
            'title' => "{$shift['username']} - {$shift['team_name']}",
            'start' => $shift['start_time'],
            'end' => $shift['end_time'],
            'backgroundColor' => $shift['color'],
            'borderColor' => $shift['color'],
            'extendedProps' => [
                'status' => $shift['status']
            ]
        ];
    }, $shifts)); ?>;

    console.log('Calendar Events:', calendarEvents);

    const calendarEl = document.getElementById('calendar');
    
    // Ensure calendar element exists
    if (!calendarEl) {
        console.error('Calendar element not found');
        return;
    }

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        events: calendarEvents,
        eventDisplay: 'block',
        height: 'auto'
    });

    // Render the calendar
    calendar.render();
});
</script>