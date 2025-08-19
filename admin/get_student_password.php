<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Ensure only admin can access this page
require_role('admin');
$user = get_logged_in_user();

// Set content type to JSON
header('Content-Type: application/json');

// Check if user_id is provided
if (!isset($_GET['user_id']) || empty($_GET['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'User ID is required'
    ]);
    exit;
}

$user_id = $_GET['user_id'];

// Get student information for better logging
$stmt = $pdo->prepare("
    SELECT 
        CASE 
            WHEN s.id IS NOT NULL THEN CONCAT(u.first_name, ' ', u.last_name, ' (', s.student_id, ')') 
            WHEN ea.id IS NOT NULL THEN CONCAT(ea.first_name, ' ', ea.last_name, ' (', ea.application_id, ')')
            ELSE CONCAT('User ID: ', ?)
        END as student_info
    FROM users u
    LEFT JOIN students s ON u.id = s.user_id
    LEFT JOIN enrollment_applications ea ON u.email = ea.email
    WHERE u.id = ?
");
$stmt->execute([$user_id, $user_id]);
$student_info = $stmt->fetchColumn() ?: 'User ID: ' . $user_id;

// Log this sensitive action
$stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
$stmt->execute([
    $user['id'],
    'password_view',
    'Admin viewed password for ' . $student_info,
    $_SERVER['REMOTE_ADDR']
]);

try {
    // Verify this is a student account
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $role = $stmt->fetchColumn();
    
    if ($role !== 'student') {
        echo json_encode([
            'success' => false,
            'message' => 'This feature is only available for student accounts'
        ]);
        exit;
    }
    
    // Generate a new temporary password
    $temp_password = generateRandomPassword(10);
    
    // Update the user's password with this temporary password
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->execute([
        password_hash($temp_password, PASSWORD_DEFAULT),
        $user_id
    ]);
    
    // Return the temporary password
    echo json_encode([
        'success' => true,
        'password' => $temp_password,
        'message' => 'Password has been temporarily reset and is shown for one-time viewing'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving password: ' . $e->getMessage()
    ]);
}

// Generate random password
function generateRandomPassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $password;
}
?>
