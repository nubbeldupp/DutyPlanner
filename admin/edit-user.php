<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
session_start();

requireRole('admin');

// Get user ID to edit
$editUserId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
if (!$editUserId) {
    $_SESSION['error'] = "Invalid user ID";
    header('Location: users.php');
    exit;
}

$conn = getDBConnection();

// Fetch user details
$stmt = $conn->prepare("SELECT user_id, username, email FROM users WHERE user_id = ?");
$stmt->execute([$editUserId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $_SESSION['error'] = "User not found";
    header('Location: users.php');
    exit;
}

// Fetch user's team roles
$stmt = $conn->prepare("
    SELECT t.team_id, t.team_name, ur.role_type 
    FROM teams t
    LEFT JOIN user_roles ur ON t.team_id = ur.team_id AND ur.user_id = ?
    ORDER BY t.team_name
");
$stmt->execute([$editUserId]);
$userTeams = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    $errors = [];

    // Validate inputs
    if (empty($username)) {
        $errors[] = "Username is required";
    }
    if (empty($email)) {
        $errors[] = "Email is required";
    }

    // Update password only if provided
    $updatePassword = !empty($password);

    if (empty($errors)) {
        try {
            $conn->beginTransaction();

            // Update user details
            $stmt = $conn->prepare(
                $updatePassword 
                ? "UPDATE users SET username = ?, email = ?, password = ? WHERE user_id = ?"
                : "UPDATE users SET username = ?, email = ? WHERE user_id = ?"
            );

            $params = $updatePassword
                ? [$username, $email, password_hash($password, PASSWORD_DEFAULT), $editUserId]
                : [$username, $email, $editUserId];

            $stmt->execute($params);

            // Update team roles
            $stmt = $conn->prepare("
                INSERT INTO user_roles (user_id, team_id, role_type)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE role_type = ?
            ");

            // Remove existing roles first
            $conn->prepare("DELETE FROM user_roles WHERE user_id = ?")->execute([$editUserId]);

            // Add selected team roles
            if (isset($_POST['team_roles'])) {
                foreach ($_POST['team_roles'] as $teamId => $role) {
                    if (!empty($role)) {
                        $stmt->execute([$editUserId, $teamId, $role, $role]);
                    }
                }
            }

            $conn->commit();
            $_SESSION['success'] = "User updated successfully";
            header('Location: users.php');
            exit;
        } catch (Exception $e) {
            $conn->rollBack();
            $errors[] = "Update failed: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <?php include '../components/nav.php'; ?>
    
    <div class="container mt-4">
        <h1 class="mb-4">Edit User: <?php echo htmlspecialchars($user['username']); ?></h1>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" 
                           value="<?php echo htmlspecialchars($user['username']); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">New Password (optional)</label>
                <input type="password" class="form-control" id="password" name="password" 
                       placeholder="Leave blank to keep current password">
            </div>

            <h3 class="mt-4 mb-3">Team Roles</h3>
            <div class="row">
                <?php foreach ($userTeams as $team): ?>
                    <div class="col-md-3 mb-3">
                        <label class="form-label"><?php echo htmlspecialchars($team['team_name']); ?></label>
                        <select name="team_roles[<?php echo $team['team_id']; ?>]" class="form-select">
                            <option value="">Not a member</option>
                            <option value="teammember" <?php echo $team['role_type'] === 'teammember' ? 'selected' : ''; ?>>
                                Team Member
                            </option>
                            <option value="teamlead" <?php echo $team['role_type'] === 'teamlead' ? 'selected' : ''; ?>>
                                Team Lead
                            </option>
                            <option value="admin" <?php echo $team['role_type'] === 'admin' ? 'selected' : ''; ?>>
                                Admin
                            </option>
                        </select>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">Update User</button>
                <a href="users.php" class="btn btn-secondary ms-2">Cancel</a>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
