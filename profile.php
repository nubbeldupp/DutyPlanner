<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
session_start();

requireAuth();

$conn = getDBConnection();
$userId = $_SESSION['user_id'];

// Get user details
$stmt = $conn->prepare("SELECT username, email FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user's teams and roles
$stmt = $conn->prepare("
    SELECT t.team_name, ur.role_type 
    FROM user_roles ur
    JOIN teams t ON ur.team_id = t.team_id
    WHERE ur.user_id = ?
");
$stmt->execute([$userId]);
$teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set page title for header
$pageTitle = 'Profile';
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
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0 me-3">
                                <i class="bi bi-person-circle fs-1 text-muted"></i>
                            </div>
                            <div>
                                <h1 class="card-title mb-1"><?php echo htmlspecialchars($user['username']); ?></h1>
                                <p class="text-muted mb-0"><?php echo htmlspecialchars($user['email']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-dark text-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-gear me-2"></i>Account Details
                        </h5>
                    </div>
                    <div class="card-body">
                        <dl class="row">
                            <dt class="col-sm-4 text-muted">Username</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($user['username']); ?></dd>
                            
                            <dt class="col-sm-4 text-muted">Email</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($user['email']); ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-dark text-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-people me-2"></i>Team Memberships
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <?php foreach ($teams as $team): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <?php echo htmlspecialchars($team['team_name']); ?>
                                    <span class="badge bg-primary rounded-pill"><?php echo $team['role_type']; ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'components/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>