<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
session_start();

requireAuth();
$pageTitle = 'Dashboard';
$userId = $_SESSION['user_id'];
$conn = getDBConnection();
$useFullCalendar = true;

// Get user's upcoming shifts
$stmt = $conn->prepare("
    SELECT s.*, t.team_name,
           CASE 
               WHEN s.status = 'pending' THEN 'warning'
               WHEN s.status = 'approved' THEN 'success'
               WHEN s.status = 'rejected' THEN 'danger'
           END as status_class
    FROM shifts s
    JOIN teams t ON s.team_id = t.team_id
    WHERE s.user_id = ? 
    AND s.end_time >= NOW()
    ORDER BY s.start_time ASC
    LIMIT 5
");
$stmt->execute([$userId]);
$upcomingShifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get shifts requiring approval (for team leads/admins)
$stmt = $conn->prepare("
    SELECT s.*, u.username, t.team_name
    FROM shifts s
    JOIN users u ON s.user_id = u.user_id
    JOIN teams t ON s.team_id = t.team_id
    WHERE s.team_id IN (
        SELECT team_id FROM user_roles 
        WHERE user_id = ? AND role_type IN ('admin', 'teamlead')
    )
    AND s.status = 'pending'
    ORDER BY s.start_time ASC
    LIMIT 5
");
$stmt->execute([$userId]);
$pendingApprovals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get team statistics
$stmt = $conn->prepare("
    SELECT t.team_name,
           COUNT(DISTINCT ur.user_id) as member_count,
           COUNT(DISTINCT CASE WHEN s.status = 'approved' AND s.end_time >= NOW() THEN s.shift_id END) as active_shifts
    FROM teams t
    LEFT JOIN user_roles ur ON t.team_id = ur.team_id
    LEFT JOIN shifts s ON t.team_id = s.team_id
    WHERE t.team_id IN (SELECT team_id FROM user_roles WHERE user_id = ?)
    GROUP BY t.team_id, t.team_name
");
$stmt->execute([$userId]);
$teamStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch shifts for calendar
$stmt = $conn->prepare("
    SELECT s.shift_id, 
           s.start_time, 
           s.end_time, 
           u.username, 
           t.team_name,
           s.status,
           CASE 
               WHEN s.status = 'pending' THEN '#ffc107'
               WHEN s.status = 'approved' THEN '#28a745'
               WHEN s.status = 'rejected' THEN '#dc3545'
               ELSE '#6c757d'
           END as color
    FROM shifts s
    JOIN users u ON s.user_id = u.user_id
    JOIN teams t ON s.team_id = t.team_id
    WHERE t.team_id IN (
        SELECT DISTINCT team_id 
        FROM user_roles 
        WHERE user_id = ?
    )
    AND s.end_time >= NOW()
");
$stmt->execute([$userId]);
$teamShifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
$calendarEvents = json_encode(array_map(function($shift) {
    return [
        'title' => "{$shift['username']} - {$shift['team_name']}",
        'start' => $shift['start_time'],
        'end' => $shift['end_time'],
        'backgroundColor' => $shift['color'],
        'borderColor' => $shift['color']
    ];
}, $teamShifts));

// Include the header
include 'components/header.php';
?>

<div class="container mt-4">
    <h1 class="mb-4">Dashboard</h1>
    
    <div class="row">
        <!-- Team Statistics -->
        <?php foreach ($teamStats as $stat): ?>
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title"><?php echo htmlspecialchars($stat['team_name']); ?></h5>
                    <div class="d-flex justify-content-between mb-2">
                        <div>
                            <i class="bi bi-people"></i> Members
                        </div>
                        <div><?php echo $stat['member_count']; ?></div>
                    </div>
                    <div class="d-flex justify-content-between">
                        <div>
                            <i class="bi bi-calendar-check"></i> Active Shifts
                        </div>
                        <div><?php echo $stat['active_shifts']; ?></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="row mt-4">
        <!-- Upcoming Shifts -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Your Upcoming Shifts</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($upcomingShifts)): ?>
                        <p class="text-muted">No upcoming shifts</p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($upcomingShifts as $shift): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?php echo htmlspecialchars($shift['team_name']); ?></strong>
                                            <br>
                                            <small>
                                                <?php echo date('M j, Y g:i A', strtotime($shift['start_time'])); ?>
                                                -
                                                <?php echo date('M j, Y g:i A', strtotime($shift['end_time'])); ?>
                                            </small>
                                        </div>
                                        <span class="badge bg-<?php echo $shift['status_class']; ?>">
                                            <?php echo ucfirst($shift['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-3">
                            <a href="shifts.php" class="btn btn-outline-primary btn-sm">View All Shifts</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Pending Approvals -->
        <?php if (!empty($pendingApprovals)): ?>
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Pending Approvals</h5>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <?php foreach ($pendingApprovals as $shift): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($shift['username']); ?></strong>
                                        <br>
                                        <small>
                                            <?php echo htmlspecialchars($shift['team_name']); ?> -
                                            <?php echo date('M j, Y g:i A', strtotime($shift['start_time'])); ?>
                                        </small>
                                    </div>
                                    <div>
                                        <form method="POST" action="actions/process-shift.php" class="d-inline">
                                            <input type="hidden" name="shift_id" value="<?php echo $shift['shift_id']; ?>">
                                            <button type="submit" name="action" value="approve" 
                                                    class="btn btn-success btn-sm">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                            <button type="submit" name="action" value="reject" 
                                                    class="btn btn-danger btn-sm">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-3">
                        <a href="approve-shifts.php" class="btn btn-outline-primary btn-sm">View All Pending</a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Calendar Section -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Team Shifts Calendar</h5>
                </div>
                <div class="card-body">
                    <div id="calendar"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Calendar Events Script -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var calendarEl = document.getElementById('calendar');
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            events: <?php echo $calendarEvents; ?>,
            eventDisplay: 'block',
            height: 'auto'
        });
        calendar.render();
    });
</script>

<?php 
// Include the footer
include 'components/footer.php'; 
?>