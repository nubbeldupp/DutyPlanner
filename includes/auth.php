<?php
function isAuthenticated() {
    return isset($_SESSION['user_id']);
}

function getUserRole($userId, $teamId = null) {
    $conn = getDBConnection();
    if ($teamId) {
        $stmt = $conn->prepare("SELECT role_type FROM user_roles WHERE user_id = ? AND team_id = ?");
        $stmt->execute([$userId, $teamId]);
    } else {
        // Check if user is admin in any team
        $stmt = $conn->prepare("SELECT role_type FROM user_roles WHERE user_id = ? AND role_type = 'admin' LIMIT 1");
        $stmt->execute([$userId]);
    }
    $role = $stmt->fetch(PDO::FETCH_COLUMN);
    return $role ?: 'teammember';
}

function requireAuth() {
    if (!isAuthenticated()) {
        header('Location: login.php');
        exit;
    }
}

function requireRole($requiredRole, $teamId = null) {
    requireAuth();
    $userRole = getUserRole($_SESSION['user_id'], $teamId);
    
    $roleHierarchy = [
        'admin' => 3,
        'teamlead' => 2,
        'teammember' => 1
    ];
    
    if ($roleHierarchy[$userRole] < $roleHierarchy[$requiredRole]) {
        header('Location: unauthorized.php');
        exit;
    }
}

function isTeamManager($userId) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM user_roles 
        WHERE user_id = ? 
        AND role_type IN ('admin', 'teamlead')
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchColumn() > 0;
} 