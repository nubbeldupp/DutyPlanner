<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
session_start();

requireRole('admin');

$conn = getDBConnection();

// Handle team creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create' && !empty($_POST['team_name'])) {
        $stmt = $conn->prepare("INSERT INTO teams (team_name) VALUES (?)");
        $stmt->execute([trim($_POST['team_name'])]);
        $_SESSION['success'] = "Team created successfully";
    } elseif ($_POST['action'] === 'delete' && !empty($_POST['team_id'])) {
        $stmt = $conn->prepare("DELETE FROM teams WHERE team_id = ?");
        $stmt->execute([$_POST['team_id']]);
        $_SESSION['success'] = "Team deleted successfully";
    }
    header('Location: teams.php');
    exit;
}

// Get all teams and their member counts
$stmt = $conn->query("
    SELECT t.*, COUNT(ur.user_id) as member_count 
    FROM teams t 
    LEFT JOIN user_roles ur ON t.team_id = ur.team_id 
    GROUP BY t.team_id
");
$teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set page title for header
$pageTitle = 'Team Management';
$useFullCalendar = false;
?>
<!DOCTYPE html>
<html lang="en">
<?php include 'components/header.php'; ?>
<body>
    <?php include 'components/nav.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card bg-light">
                    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-diagram-3 me-2"></i>Team Management
                        </h5>
                        <button type="button" class="btn btn-outline-light btn-sm" data-bs-toggle="modal" data-bs-target="#createTeamModal">
                            <i class="bi bi-plus-circle me-1"></i>Create Team
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['success'])): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?php 
                                echo $_SESSION['success'];
                                unset($_SESSION['success']);
                                ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Team Name</th>
                                        <th>Members</th>
                                        <th>Created At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($teams as $team): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($team['team_name']); ?></td>
                                            <td>
                                                <span class="badge bg-primary rounded-pill">
                                                    <?php echo $team['member_count']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('Y-m-d', strtotime($team['created_at'])); ?></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="team-members.php?id=<?php echo $team['team_id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-people me-1"></i>Manage
                                                    </a>
                                                    <form method="POST" class="d-inline" 
                                                          onsubmit="return confirm('Are you sure you want to delete this team?');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="team_id" value="<?php echo $team['team_id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                                            <i class="bi bi-trash me-1"></i>Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Team Modal -->
    <div class="modal fade" id="createTeamModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-circle me-2"></i>Create New Team
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        <div class="mb-3">
                            <label for="team_name" class="form-label">Team Name</label>
                            <input type="text" class="form-control" id="team_name" name="team_name" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Team</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include 'components/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>