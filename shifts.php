<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
session_start();

requireAuth();
$pageTitle = 'My Shifts';
$userId = $_SESSION['user_id'];
$conn = getDBConnection();

// Fetch user's shifts
$stmt = $conn->prepare("
    SELECT s.*, 
           t.team_name,
           CASE 
               WHEN s.status = 'pending' THEN 'warning'
               WHEN s.status = 'approved' THEN 'success'
               WHEN s.status = 'rejected' THEN 'danger'
           END as status_class
    FROM shifts s
    JOIN teams t ON s.team_id = t.team_id
    WHERE s.user_id = ?
    ORDER BY s.start_time DESC
");
$stmt->execute([$userId]);
$shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch user's teams
$stmt = $conn->prepare("
    SELECT DISTINCT t.team_id, t.team_name
    FROM teams t
    JOIN user_roles ur ON t.team_id = ur.team_id
    WHERE ur.user_id = ?
");
$stmt->execute([$userId]);
$userTeams = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Include the header
include 'components/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">My Shifts</h5>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createShiftModal">
                        <i class="bi bi-plus-circle me-1"></i> Create Shift
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <?php if (empty($shifts)): ?>
            <div class="col-12">
                <div class="alert alert-info text-center">
                    <i class="bi bi-info-circle me-2"></i>
                    You have no shifts yet. Create your first shift!
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($shifts as $shift): ?>
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-calendar-event me-2"></i>
                                    <?php echo htmlspecialchars($shift['team_name']); ?>
                                </h5>
                                <span class="badge bg-<?php echo $shift['status_class']; ?>">
                                    <?php echo ucfirst($shift['status']); ?>
                                </span>
                            </div>
                            <p class="card-text">
                                <strong>Start:</strong> 
                                <?php echo date('M j, Y g:i A', strtotime($shift['start_time'])); ?>
                                <br>
                                <strong>End:</strong> 
                                <?php echo date('M j, Y g:i A', strtotime($shift['end_time'])); ?>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Create Shift Modal -->
    <div class="modal fade" id="createShiftModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Shift</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="createShiftForm" action="actions/create-shift.php" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Shift Type</label>
                            <select name="shift_type" id="shift_type" class="form-select" required>
                                <option value="regular">Regular (7 days)</option>
                                <option value="adhoc">Ad-Hoc (1-24 hours)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Teams</label>
                            <select name="team_ids[]" id="team_ids" class="form-select" multiple required>
                                <?php foreach ($userTeams as $team): ?>
                                    <option value="<?php echo $team['team_id']; ?>">
                                        <?php echo htmlspecialchars($team['team_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" id="start_date" name="start_date" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">End Date</label>
                            <input type="date" id="end_date" name="end_date" class="form-control" required>
                        </div>
                        <input type="hidden" id="start_time" name="start_time" value="00:00">
                        <input type="hidden" id="end_time" name="end_time" value="23:59">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Shift</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php 
// Include the footer
include 'components/footer.php'; 
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const startTimeInput = document.getElementById('start_time');
    const endTimeInput = document.getElementById('end_time');
    const shiftTypeSelect = document.getElementById('shift_type');
    const teamIdsSelect = document.getElementById('team_ids');
    const createShiftForm = document.getElementById('createShiftForm');

    function setDefaultTimes() {
        const now = new Date();
        const startDate = new Date(now);
        const endDate = new Date(now);

        // Set start date to current day
        startDate.setHours(0, 0, 0, 0);

        // Set end date based on shift type
        if (shiftTypeSelect.value === 'regular') {
            // 7 days from start
            endDate.setDate(startDate.getDate() + 7);
        }

        // Format for date input
        startDateInput.value = startDate.toISOString().split('T')[0];
        endDateInput.value = endDate.toISOString().split('T')[0];

        // Set default times
        startTimeInput.value = '00:00';
        endTimeInput.value = '23:59';
    }

    // Set initial times
    setDefaultTimes();

    // Update times when shift type changes
    shiftTypeSelect.addEventListener('change', setDefaultTimes);

    // Add form submission event listener
    createShiftForm.addEventListener('submit', function(event) {
        event.preventDefault();

        // Log form data for debugging
        console.log('Shift Type:', shiftTypeSelect.value);
        console.log('Team IDs:', Array.from(teamIdsSelect.selectedOptions).map(opt => opt.value));
        console.log('Start Date:', startDateInput.value);
        console.log('End Date:', endDateInput.value);
        console.log('Start Time:', startTimeInput.value);
        console.log('End Time:', endTimeInput.value);

        // Prepare form data
        const formData = new FormData(createShiftForm);

        // Send AJAX request
        fetch(createShiftForm.action, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Response Status:', response.status);
            return response.text();
        })
        .then(text => {
            console.log('Response Text:', text);
            // Redirect or handle response
            window.location.href = '../shifts.php';
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while creating the shift.');
        });
    });
});