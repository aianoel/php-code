<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Create test users for each role
$testUsers = [
    ['username' => 'admin', 'email' => 'admin@test.com', 'password' => 'password', 'first_name' => 'Admin', 'last_name' => 'User', 'role' => 'admin'],
    ['username' => 'teacher', 'email' => 'teacher@test.com', 'password' => 'password', 'first_name' => 'Teacher', 'last_name' => 'User', 'role' => 'teacher'],
    ['username' => 'student', 'email' => 'student@test.com', 'password' => 'password', 'first_name' => 'Student', 'last_name' => 'User', 'role' => 'student'],
    ['username' => 'parent', 'email' => 'parent@test.com', 'password' => 'password', 'first_name' => 'Parent', 'last_name' => 'User', 'role' => 'parent'],
    ['username' => 'registrar', 'email' => 'registrar@test.com', 'password' => 'password', 'first_name' => 'Registrar', 'last_name' => 'User', 'role' => 'registrar'],
    ['username' => 'accounting', 'email' => 'accounting@test.com', 'password' => 'password', 'first_name' => 'Accounting', 'last_name' => 'User', 'role' => 'accounting'],
    ['username' => 'principal', 'email' => 'principal@test.com', 'password' => 'password', 'first_name' => 'Principal', 'last_name' => 'User', 'role' => 'principal'],
    ['username' => 'guidance', 'email' => 'guidance@test.com', 'password' => 'password', 'first_name' => 'Guidance', 'last_name' => 'User', 'role' => 'guidance'],
    ['username' => 'academic_coordinator', 'email' => 'academic@test.com', 'password' => 'password', 'first_name' => 'Academic', 'last_name' => 'Coordinator', 'role' => 'academic_coordinator']
];

echo "Creating test users...\n";

foreach ($testUsers as $userData) {
    try {
        // Check if user already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$userData['username'], $userData['email']]);
        
        if ($stmt->fetch()) {
            echo "User {$userData['username']} already exists, skipping...\n";
            continue;
        }
        
        // Create user
        $result = $dataManager->createUser($userData);
        if ($result) {
            echo "Created user: {$userData['username']} ({$userData['role']})\n";
            
            // Create additional records for specific roles
            $userId = $pdo->lastInsertId();
            
            if ($userData['role'] === 'student') {
                // Create student record
                $stmt = $pdo->prepare("INSERT INTO students (user_id, student_id, grade_level, section) VALUES (?, ?, ?, ?)");
                $stmt->execute([$userId, generate_student_id(), 'grade11', 'A']);
                echo "  - Created student record\n";
            } elseif ($userData['role'] === 'teacher') {
                // Create teacher record
                $stmt = $pdo->prepare("INSERT INTO teachers (user_id, employee_id, department) VALUES (?, ?, ?)");
                $stmt->execute([$userId, 'T' . str_pad($userId, 4, '0', STR_PAD_LEFT), 'Mathematics']);
                echo "  - Created teacher record\n";
            }
        } else {
            echo "Failed to create user: {$userData['username']}\n";
        }
    } catch (Exception $e) {
        echo "Error creating user {$userData['username']}: " . $e->getMessage() . "\n";
    }
}

echo "\nTest users creation completed!\n";
echo "All test users use password: 'password'\n";
?>
