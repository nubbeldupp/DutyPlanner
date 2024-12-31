<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

$username = 'admin';
$password = 'admin123';
$newPasswordHash = password_hash($password, PASSWORD_DEFAULT);

$conn = getDBConnection();

try {
    // Update the password for the admin user
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = ?");
    $stmt->execute([$newPasswordHash, $username]);

    echo "Admin password successfully updated!\n";
    echo "New Password Hash: $newPasswordHash\n";
} catch (Exception $e) {
    echo "Error updating password: " . $e->getMessage();
}

// Verify the new password
$stmt = $conn->prepare("SELECT password FROM users WHERE username = ?");
$stmt->execute([$username]);
$storedHash = $stmt->fetchColumn();

echo "\nVerification:\n";
echo "Stored Hash: $storedHash\n";
echo "Password Verify: " . (password_verify($password, $storedHash) ? "Success" : "Fail");
?>
