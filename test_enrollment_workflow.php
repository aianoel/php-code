<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

echo "Testing Enrollment Applications Workflow...\n\n";

try {
    // Test 1: Check if applications are visible
    echo "1. Testing Application Visibility:\n";
    echo "================================\n";
    
    $stmt = $pdo->query("
        SELECT 
            id, application_number, first_name, last_name, email, status,
            grade_level, strand, submitted_at, total_fees, payment_status
        FROM enrollment_applications 
        ORDER BY submitted_at DESC
    ");
    
    $applications = $stmt->fetchAll();
    
    if (empty($applications)) {
        echo "✗ No applications found\n";
    } else {
        echo "✓ Found " . count($applications) . " applications:\n";
        foreach ($applications as $app) {
            echo "  - {$app['application_number']}: {$app['first_name']} {$app['last_name']} ({$app['status']})\n";
        }
    }
    
    // Test 2: Check admin user exists
    echo "\n2. Testing Admin User Access:\n";
    echo "============================\n";
    
    $admin = $pdo->query("SELECT * FROM users WHERE role = 'admin' LIMIT 1")->fetch();
    if ($admin) {
        echo "✓ Admin user found: {$admin['username']} (ID: {$admin['id']})\n";
    } else {
        echo "✗ No admin user found\n";
    }
    
    // Test 3: Simulate approval workflow
    echo "\n3. Testing Approval Workflow:\n";
    echo "============================\n";
    
    // Find an application ready for admin approval
    $appForApproval = $pdo->query("
        SELECT * FROM enrollment_applications 
        WHERE status IN ('admin_approval', 'under_review') 
        LIMIT 1
    ")->fetch();
    
    if ($appForApproval) {
        echo "✓ Found application for testing: {$appForApproval['application_number']}\n";
        
        // Simulate admin approval
        $stmt = $pdo->prepare("
            UPDATE enrollment_applications 
            SET 
                status = 'approved',
                admin_approved_by = ?,
                admin_approved_at = NOW(),
                admin_notes = 'Application approved during workflow testing'
            WHERE id = ?
        ");
        
        $stmt->execute([$admin['id'], $appForApproval['id']]);
        echo "✓ Application approved successfully\n";
        
        // Test creating user account
        $username = strtolower($appForApproval['first_name'] . '.' . $appForApproval['last_name']);
        $password = generate_random_password(8);
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Check if user already exists
        $existingUser = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $existingUser->execute([$username, $appForApproval['email']]);
        
        if (!$existingUser->fetch()) {
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password, role, first_name, last_name, status, created_at)
                VALUES (?, ?, ?, 'student', ?, ?, 'active', NOW())
            ");
            
            $stmt->execute([
                $username,
                $appForApproval['email'],
                $hashedPassword,
                $appForApproval['first_name'],
                $appForApproval['last_name']
            ]);
            
            $userId = $pdo->lastInsertId();
            echo "✓ User account created: $username (ID: $userId)\n";
            
            // Create student record
            $studentId = generate_student_id($appForApproval['grade_level']);
            $stmt = $pdo->prepare("
                INSERT INTO students (user_id, student_id, grade_level, strand, enrollment_date, status)
                VALUES (?, ?, ?, ?, NOW(), 'enrolled')
            ");
            
            $stmt->execute([
                $userId,
                $studentId,
                $appForApproval['grade_level'],
                $appForApproval['strand']
            ]);
            
            echo "✓ Student record created: $studentId\n";
            
            // Update application with user ID
            $stmt = $pdo->prepare("
                UPDATE enrollment_applications 
                SET student_user_id = ?, status = 'enrolled' 
                WHERE id = ?
            ");
            $stmt->execute([$userId, $appForApproval['id']]);
            
            echo "✓ Application status updated to 'enrolled'\n";
        } else {
            echo "! User already exists for this application\n";
        }
    } else {
        echo "! No applications available for approval testing\n";
    }
    
    // Test 4: Check rejection workflow
    echo "\n4. Testing Rejection Workflow:\n";
    echo "=============================\n";
    
    $appForRejection = $pdo->query("
        SELECT * FROM enrollment_applications 
        WHERE status = 'under_review' 
        LIMIT 1
    ")->fetch();
    
    if ($appForRejection) {
        $stmt = $pdo->prepare("
            UPDATE enrollment_applications 
            SET 
                status = 'rejected',
                admin_approved_by = ?,
                admin_approved_at = NOW(),
                rejection_reason = 'Test rejection - incomplete documentation'
            WHERE id = ?
        ");
        
        $stmt->execute([$admin['id'], $appForRejection['id']]);
        echo "✓ Application rejected successfully: {$appForRejection['application_number']}\n";
    } else {
        echo "! No applications available for rejection testing\n";
    }
    
    // Test 5: Final statistics
    echo "\n5. Final Application Statistics:\n";
    echo "===============================\n";
    
    $stats = [
        'pending' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE status = 'pending'")->fetchColumn(),
        'under_review' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE status = 'under_review'")->fetchColumn(),
        'admin_approval' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE status = 'admin_approval'")->fetchColumn(),
        'approved' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE status = 'approved'")->fetchColumn(),
        'enrolled' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE status = 'enrolled'")->fetchColumn(),
        'rejected' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE status = 'rejected'")->fetchColumn()
    ];
    
    foreach ($stats as $status => $count) {
        echo "- " . ucfirst(str_replace('_', ' ', $status)) . ": $count\n";
    }
    
    // Test 6: Check admin page functionality
    echo "\n6. Testing Admin Page Query:\n";
    echo "===========================\n";
    
    $adminQuery = "
        SELECT 
            ea.*,
            rr.username as registrar_reviewer,
            ar.username as accounting_reviewer,
            aa.username as admin_approver
        FROM enrollment_applications ea
        LEFT JOIN users rr ON ea.registrar_reviewed_by = rr.id
        LEFT JOIN users ar ON ea.accounting_reviewed_by = ar.id  
        LEFT JOIN users aa ON ea.admin_approved_by = aa.id
        WHERE ea.status IN ('admin_approval', 'under_review', 'pending', 'approved', 'enrolled', 'rejected')
        ORDER BY ea.submitted_at DESC
    ";
    
    $adminApps = $pdo->query($adminQuery)->fetchAll();
    echo "✓ Admin query returns " . count($adminApps) . " applications\n";
    
    if (!empty($adminApps)) {
        echo "Sample application data:\n";
        $sample = $adminApps[0];
        echo "  - App #: {$sample['application_number']}\n";
        echo "  - Name: {$sample['first_name']} {$sample['last_name']}\n";
        echo "  - Status: {$sample['status']}\n";
        echo "  - Grade: {$sample['grade_level']}\n";
        echo "  - Strand: {$sample['strand']}\n";
        echo "  - Fees: ₱" . number_format($sample['total_fees'], 2) . "\n";
    }
    
    echo "\n✅ Enrollment workflow testing completed successfully!\n";
    
} catch (Exception $e) {
    echo "✗ Error during testing: " . $e->getMessage() . "\n";
}
?>
