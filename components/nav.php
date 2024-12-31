<?php
// Ensure $userId is always defined
$userId = $_SESSION['user_id'] ?? null;
$conn = getDBConnection();
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">
            <img src="assets/img/logo.png" alt="Dutyplanner Logo" height="30">
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                        <i class="bi bi-speedometer2 me-1"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'shifts.php' ? 'active' : ''; ?>" href="shifts.php">
                        <i class="bi bi-calendar3 me-1"></i>Shifts
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>" href="reports.php">
                        <i class="bi bi-graph-up me-1"></i>Reports
                    </a>
                </li>
                <?php 
                // Check if user is a team manager (only if $userId is set)
                $isTeamManager = false;
                if ($userId) {
                    $stmt = $conn->prepare("
                        SELECT COUNT(*) FROM user_roles 
                        WHERE user_id = ? AND role_type IN ('admin', 'teamlead')
                    ");
                    $stmt->execute([$userId]);
                    $isTeamManager = $stmt->fetchColumn() > 0;
                }
                
                if ($isTeamManager): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'approve-shifts.php' ? 'active' : ''; ?>" href="approve-shifts.php">
                            <i class="bi bi-check-circle me-1"></i>Approve Shifts
                        </a>
                    </li>
                <?php endif; ?>
                <?php 
                // Check if user is an admin (only if $userId is set)
                $isAdmin = false;
                if ($userId) {
                    $stmt = $conn->prepare("
                        SELECT COUNT(*) FROM user_roles 
                        WHERE user_id = ? AND role_type = 'admin'
                    ");
                    $stmt->execute([$userId]);
                    $isAdmin = $stmt->fetchColumn() > 0;
                }
                
                if ($isAdmin): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>" href="users.php">
                            <i class="bi bi-people me-1"></i>User Management
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'teams.php' ? 'active' : ''; ?>" href="teams.php">
                            <i class="bi bi-diagram-3 me-1"></i>Team Management
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item me-2">
                    <a href="profile.php" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-person me-1"></i><?php echo htmlspecialchars($_SESSION['username']); ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="logout.php" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-box-arrow-right me-1"></i>Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>