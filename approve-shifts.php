<?php
// Ensure no output before headers
ob_start();

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
session_start();

requireAuth();
$pageTitle = 'Approve Shifts';
$userId = $_SESSION['user_id'];
$conn = getDBConnection();

try {
    // Fetch user's roles and teams
    $stmt = $conn->prepare("
        SELECT DISTINCT 
            ur.role_type,
            t.team_id, 
            t.team_name,
            r.role_name
        FROM user_roles ur
        JOIN teams t ON ur.team_id = t.team_id
        JOIN roles r ON ur.role_id = r.role_id
        WHERE ur.user_id = ?
    ");
    $stmt->execute([$userId]);
    $userTeams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare debug information for JavaScript console
    $debugInfo = [
        'userId' => $userId,
        'userTeams' => []
    ];

    // Populate debug info
    foreach ($userTeams as $team) {
        $debugInfo['userTeams'][] = [
            'teamId' => $team['team_id'],
            'teamName' => $team['team_name'],
            'roleType' => $team['role_type'],
            'roleName' => $team['role_name']
        ];
    }

    // Check if user has any teams
    if (empty($userTeams)) {
        ob_end_clean(); // Clear output buffer
        $_SESSION['error'] = "No teams found for the user.";
        header('Location: dashboard.php');
        exit;
    }

    // Determine if user can access approve shifts
    $canAccessApproveShifts = false;
    $userRoles = [];

    foreach ($userTeams as $team) {
        $userRoles[] = $team['role_name'];
        if (in_array($team['role_name'], ['admin', 'teamlead'])) {
            $canAccessApproveShifts = true;
            break;
        }
    }

    // Add roles to debug info
    $debugInfo['userRoles'] = $userRoles;
    $debugInfo['canAccessApproveShifts'] = $canAccessApproveShifts;

    // Redirect if cannot access approve shifts
    if (!$canAccessApproveShifts) {
        ob_end_clean(); // Clear output buffer
        $_SESSION['error'] = "You do not have permission to access shift approvals.";
        header('Location: dashboard.php');
        exit;
    }

    // Fetch shifts with overlap detection
    $shiftsQuery = "WITH TeamShifts AS (
        SELECT 
            s1.shift_id, 
            s1.start_time, 
            s1.end_time, 
            s1.status, 
            u.username, 
            t.team_name,
            t.team_id,
            s1.user_id,
            CASE 
                WHEN s1.status = 'pending' THEN '#ffc107'
                WHEN s1.status = 'approved' THEN '#28a745'
                WHEN s1.status = 'rejected' THEN '#dc3545'
                ELSE '#007bff'
            END as status_color,
            (
                SELECT COUNT(*) 
                FROM shifts s2 
                WHERE s2.team_id = s1.team_id 
                AND (
                    (s2.status = 'pending' AND s1.status = 'pending') OR
                    (s2.status = 'approved' AND s1.status = 'pending')
                )
                AND s1.shift_id != s2.shift_id
                AND s1.start_time < s2.end_time 
                AND s1.end_time > s2.start_time
            ) as overlap_count
        FROM shifts s1
        JOIN users u ON s1.user_id = u.user_id
        JOIN teams t ON s1.team_id = t.team_id
        JOIN user_roles ur ON t.team_id = ur.team_id
        JOIN roles r ON ur.role_id = r.role_id
        WHERE ur.user_id = ?
        AND (s1.status = 'pending' OR s1.status = 'approved' OR s1.status = 'rejected')
        AND r.role_name IN ('admin', 'teamlead')
    )
    SELECT * FROM TeamShifts
    ORDER BY start_time ASC";

    $stmt = $conn->prepare($shiftsQuery);
    $stmt->execute([$userId]);
    $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Add shifts to debug info
    $debugInfo['shiftsFound'] = count($shifts);

    // Separate shifts for display
    $pendingShifts = array_filter($shifts, function($shift) {
        return $shift['status'] === 'pending';
    });

    // Fetch shifts for the user's teams
    $teamIds = array_unique(array_column($userTeams, 'team_id'));

    // If no shifts found for the user's teams, this could be a teamlead scenario
    if (empty($pendingShifts)) {
        // Fetch shifts for teams where the user is a teamlead
        $teamLeadShiftsQuery = "
            SELECT s.*, u.username, t.team_name,
            CASE 
                WHEN s.status = 'pending' THEN '#ffc107'
                WHEN s.status = 'approved' THEN '#28a745'
                WHEN s.status = 'rejected' THEN '#dc3545'
                ELSE '#007bff'
            END as status_color
            FROM shifts s
            JOIN users u ON s.user_id = u.user_id
            JOIN teams t ON s.team_id = t.team_id
            JOIN user_roles ur ON t.team_id = ur.team_id
            JOIN roles r ON ur.role_id = r.role_id
            WHERE ur.user_id = ? 
            AND r.role_name = 'teamlead'
            AND s.status = 'pending'
            ORDER BY s.start_time ASC
        ";
        
        $stmt = $conn->prepare($teamLeadShiftsQuery);
        $stmt->execute([$userId]);
        $pendingShifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Debug: Log shifts
    error_log("Shifts Found: " . count($pendingShifts));
    error_log(print_r($pendingShifts, true));

    // Include the header AFTER all checks and redirects
    include 'components/header.php';
    
    // Output debug info to console AFTER header
    echo "<script>console.log('Approve Shifts Debug Info:', " . json_encode($debugInfo) . ");</script>";

} catch (PDOException $e) {
    // Clear any existing output
    ob_end_clean();

    // Prepare detailed error information
    $errorInfo = [
        'errorMessage' => $e->getMessage(),
        'errorCode' => $e->getCode(),
        'sqlState' => $e->errorInfo ? $e->errorInfo[0] : null,
        'driverCode' => $e->errorInfo ? $e->errorInfo[1] : null,
        'driverMessage' => $e->errorInfo ? $e->errorInfo[2] : null
    ];

    // Log error to server error log
    error_log('Database Error in Approve Shifts: ' . print_r($errorInfo, true));

    // Set session error and redirect
    $_SESSION['error'] = "A database error occurred. Please contact support.";
    header('Location: dashboard.php');
    exit;
}
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Pending Shift Approvals</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pendingShifts)): ?>
                        <div class="alert alert-info text-center">
                            <i class="bi bi-info-circle me-2"></i>
                            No pending shifts to approve.
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
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingShifts as $shift): 
                                        // Calculate duration
                                        $start = new DateTime($shift['start_time']);
                                        $end = new DateTime($shift['end_time']);
                                        $duration = $start->diff($end);
                                        $durationHours = $duration->h + ($duration->days * 24);
                                    ?>
                                        <tr <?php echo $shift['overlap_count'] > 0 ? 'class="table-warning"' : ''; ?>>
                                            <td><?php echo htmlspecialchars($shift['username']); ?></td>
                                            <td><?php echo htmlspecialchars($shift['team_name']); ?></td>
                                            <td><?php echo date('M j, Y g:i A', strtotime($shift['start_time'])); ?></td>
                                            <td><?php echo date('M j, Y g:i A', strtotime($shift['end_time'])); ?></td>
                                            <td><?php echo $durationHours; ?> hrs</td>
                                            <td>
                                                <span class="badge" style="background-color: <?php echo $shift['status_color']; ?>">
                                                    <?php echo ucfirst($shift['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($shift['overlap_count'] > 0): ?>
                                                    <span class="badge bg-warning text-dark" data-bs-toggle="tooltip" title="This shift overlaps with <?php echo $shift['overlap_count']; ?> other shift(s) in the same team">
                                                        <i class="bi bi-exclamation-triangle me-1"></i>
                                                        <?php echo $shift['overlap_count']; ?> Overlap<?php echo $shift['overlap_count'] > 1 ? 's' : ''; ?>
                                                    </span>
                                                <?php endif; ?>
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-sm btn-success approve-shift" data-shift-id="<?php echo $shift['shift_id']; ?>">
                                                        <i class="bi bi-check-circle me-1"></i>Approve
                                                    </button>
                                                    <button class="btn btn-sm btn-danger reject-shift" data-shift-id="<?php echo $shift['shift_id']; ?>">
                                                        <i class="bi bi-x-circle me-1"></i>Reject
                                                    </button>
                                                </div>
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

    <div class="row mt-4">
        <div class="col-12">
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
            'backgroundColor' => $shift['status_color'],
            'borderColor' => $shift['status_color'],
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
        eventRender: function(info) {
            // Add custom tooltip
            var tooltipContent = `
                <div class="custom-tooltip">
                    <strong>User:</strong> ${info.event.title.split(' - ')[0]}<br>
                    <strong>Team:</strong> ${info.event.title.split(' - ')[1]}<br>
                    <strong>Status:</strong> ${info.event.extendedProps.status}<br>
                    <strong>Start:</strong> ${moment(info.event.start).format('MMM D, YYYY h:mm A')}<br>
                    <strong>End:</strong> ${moment(info.event.end).format('MMM D, YYYY h:mm A')}
                </div>
            `;
            
            // Create tooltip
            new bootstrap.Tooltip(info.el, {
                title: tooltipContent,
                html: true,
                placement: 'top',
                trigger: 'hover',
                template: '<div class="tooltip" role="tooltip"><div class="tooltip-inner custom-tooltip-inner"></div></div>'
            });
        },
        eventDisplay: 'block',
        eventBorderColor: 'transparent',
        eventBackgroundColor: info => info.event.extendedProps.backgroundColor || '#007bff',
        eventDidMount: function(info) {
            // Check for overlapping shifts in the same team
            const event = info.event;
            const overlappingEvents = calendar.getEvents().filter(otherEvent => {
                // Skip the current event itself
                if (otherEvent.id === event.id) return false;

                // Check if events are in the same team
                const sameTeam = event.title.split(' - ')[1] === otherEvent.title.split(' - ')[1];
                
                // Check for time overlap
                const overlap = event.start < otherEvent.end && event.end > otherEvent.start;
                
                // Check if either event is pending or approved
                const isRelevantOverlap = 
                    (event.extendedProps.status === 'pending' || event.extendedProps.status === 'approved') &&
                    (otherEvent.extendedProps.status === 'pending' || otherEvent.extendedProps.status === 'approved');

                return sameTeam && overlap && isRelevantOverlap;
            });

            // Add visual indication for overlapping shifts
            if (overlappingEvents.length > 0 && event.extendedProps.status === 'pending') {
                const el = info.el;
                el.style.border = '3px solid red';
                el.setAttribute('title', 'Warning: Shift overlaps with existing shift');
                
                // Optional: Add a tooltip or custom popup
                const tooltipContent = `
                    <div class="alert alert-warning p-2 m-0">
                        <strong>Overlap Warning:</strong> 
                        This shift overlaps with ${overlappingEvents.length} existing ${overlappingEvents.length === 1 ? 'shift' : 'shifts'}
                    </div>
                `;
                
                // You might want to use a proper tooltip library here
                el.setAttribute('data-bs-toggle', 'tooltip');
                el.setAttribute('data-bs-html', 'true');
                el.setAttribute('data-bs-title', tooltipContent);
            }
        },
        eventClick: function(info) {
            // Create modal for shift details and approval
            const shift = info.event.extendedProps;
            const modalHtml = `
                <div class="modal fade" id="shiftDetailModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Shift Details</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p><strong>User:</strong> ${info.event.title}</p>
                                <p><strong>Start:</strong> ${info.event.start.toLocaleString()}</p>
                                <p><strong>End:</strong> ${info.event.end.toLocaleString()}</p>
                                <p><strong>Status:</strong> ${shift.status || 'N/A'}</p>
                                
                                ${info.event.extendedProps.status === 'pending' ? `
                                <div class="alert alert-warning mt-3">
                                    <strong>Overlap Check:</strong>
                                    Checking for potential shift conflicts...
                                </div>
                                ` : ''}
                            </div>
                            <div class="modal-footer">
                                <form method="POST" action="actions/process-shift.php">
                                    <input type="hidden" name="shift_id" value="${info.event.id}">
                                    <button type="submit" name="action" value="approve" class="btn btn-success">
                                        <i class="bi bi-check-lg me-1"></i>Approve
                                    </button>
                                    <button type="submit" name="action" value="reject" class="btn btn-danger">
                                        <i class="bi bi-x-lg me-1"></i>Reject
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove any existing modals
            document.getElementById('shiftDetailModal')?.remove();
            
            // Add modal to body and show
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            const modal = new bootstrap.Modal(document.getElementById('shiftDetailModal'));
            modal.show();

            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            });
        }
    });

    // Render the calendar
    calendar.render();
});
</script>