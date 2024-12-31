<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

$username = 'admin';
$password = 'admin123';

$conn = getDBConnection();
$stmt = $conn->prepare("SELECT user_id, username, password FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "No user found with username: $username\n";
    exit;
}

echo "User found:\n";
echo "User ID: " . $user['user_id'] . "\n";
echo "Username: " . $user['username'] . "\n";
echo "Password Hash: " . $user['password'] . "\n";

echo "\nPassword Verification:\n";
if (password_verify($password, $user['password'])) {
    echo "Password VERIFIED ✓\n";
} else {
    echo "Password FAILED ✗\n";
}

// Let's also try to generate a new hash to compare
$newHash = password_hash($password, PASSWORD_DEFAULT);
echo "\nNew Generated Hash: $newHash\n";
echo "Verify New Hash: " . (password_verify($password, $newHash) ? "Success" : "Fail") . "\n";
?>
