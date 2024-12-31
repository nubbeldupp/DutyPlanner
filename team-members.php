<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
session_start();

requireRole('admin');

$teamId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$teamId) {
    header('Location: teams.php');
    exit;
}

$conn = getDBConnection();

// Handle member management
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['user_id'], $_POST['role_type'])) {
        $stmt = $conn->prepare("
            INSERT INTO user_roles (user_id, team_id, role_type)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE role_type = ?
        ");
        $stmt->execute([
            $_POST['user_id'],
            $teamId,
            $_POST['role_type'],
            $_POST['role_type']
        ]);
        $_SESSION['success'] = "Member role updated successfully";
    } elseif (isset($_POST['remove_user_id'])) {
        $stmt = $conn->prepare("DELETE FROM user_roles WHERE user_id = ? AND team_id = ?");
        $stmt->execute([$_POST['remove_user_id'], $teamId]);
        $_SESSION['success'] = "Member removed successfully";
    }
    header("Location: team-members.php?id=$teamId");
    exit;
}

// Get team details
$stmt = $conn->prepare("SELECT team_name FROM teams WHERE team_id = ?");
$stmt->execute([$teamId]);
$team = $stmt->fetch(PDO::FETCH_ASSOC);

// Get team members
$stmt = $conn->prepare("
    SELECT u.user_id, u.username, u.email, ur.role_type
    FROM users u
    LEFT JOIN user_roles ur ON u.user_id = ur.user_id AND ur.team_id = ?
    ORDER BY u.username
");
$stmt->execute([$teamId]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Team Members - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../components/nav.php'; ?>
    
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Manage Team: <?php echo htmlspecialchars($team['team_name']); ?></h1>
            <a href="teams.php" class="btn btn-secondary">Back to Teams</a>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php 
                echo $_SESSION['success'];
                unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                    <select name="role_type" class="form-select form-select-sm" 
                                            onchange="this.form.submit()"
                                            style="width: auto;">
                                        <option value="">Not a member</option>
                                        <option value="teammember" <?php echo $user['role_type'] === 'teammember' ? 'selected' : ''; ?>>
                                            Team Member
                                        </option>
                                        <option value="teamlead" <?php echo $user['role_type'] === 'teamlead' ? 'selected' : ''; ?>>
                                            Team Lead
                                        </option>
                                        <option value="admin" <?php echo $user['role_type'] === 'admin' ? 'selected' : ''; ?>>
                                            Admin
                                        </option>
                                    </select>
                                </form>
                            </td>
                            <td>
                                <?php if ($user['role_type']): ?>
                                    <form method="POST" class="d-inline" 
                                          onsubmit="return confirm('Are you sure you want to remove this member?');">
                                        <input type="hidden" name="remove_user_id" value="<?php echo $user['user_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Remove</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 