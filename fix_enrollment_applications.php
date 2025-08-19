<?php
require_once 'includes/config.php';

try {
    echo "Fixing enrollment applications functionality...\n\n";
    
    // Add missing columns to enrollment_applications table
    $columnsToAdd = [
        'submitted_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
        'registrar_reviewed_by' => 'INT NULL',
        'registrar_reviewed_at' => 'TIMESTAMP NULL',
        'registrar_notes' => 'TEXT NULL',
        'accounting_reviewed_by' => 'INT NULL', 
        'accounting_reviewed_at' => 'TIMESTAMP NULL',
        'accounting_notes' => 'TEXT NULL',
        'admin_approved_by' => 'INT NULL',
        'admin_approved_at' => 'TIMESTAMP NULL',
        'admin_notes' => 'TEXT NULL',
        'rejection_reason' => 'TEXT NULL',
        'total_fees' => 'DECIMAL(10,2) DEFAULT 0',
        'paid_amount' => 'DECIMAL(10,2) DEFAULT 0',
        'student_user_id' => 'INT NULL'
    ];
    
    foreach ($columnsToAdd as $column => $definition) {
        // Check if column exists
        $stmt = $pdo->query("SHOW COLUMNS FROM enrollment_applications LIKE '$column'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE enrollment_applications ADD COLUMN $column $definition");
            echo "✓ Added column: $column\n";
        }
    }
    
    // Add foreign key constraints
    try {
        $pdo->exec("ALTER TABLE enrollment_applications 
                   ADD CONSTRAINT fk_registrar_reviewed_by 
                   FOREIGN KEY (registrar_reviewed_by) REFERENCES users(id) ON DELETE SET NULL");
    } catch (PDOException $e) {
        // Constraint might already exist
    }
    
    try {
        $pdo->exec("ALTER TABLE enrollment_applications 
                   ADD CONSTRAINT fk_accounting_reviewed_by 
                   FOREIGN KEY (accounting_reviewed_by) REFERENCES users(id) ON DELETE SET NULL");
    } catch (PDOException $e) {
        // Constraint might already exist
    }
    
    try {
        $pdo->exec("ALTER TABLE enrollment_applications 
                   ADD CONSTRAINT fk_admin_approved_by 
                   FOREIGN KEY (admin_approved_by) REFERENCES users(id) ON DELETE SET NULL");
    } catch (PDOException $e) {
        // Constraint might already exist
    }
    
    // Update existing applications with proper workflow statuses
    $applications = $pdo->query("SELECT * FROM enrollment_applications")->fetchAll();
    
    foreach ($applications as $app) {
        // Set submitted_at if not set
        if (!$app['submitted_at']) {
            $stmt = $pdo->prepare("UPDATE enrollment_applications SET submitted_at = created_at WHERE id = ?");
            $stmt->execute([$app['id']]);
        }
        
        // Update status to show workflow progression
        if ($app['status'] === 'pending') {
            // Set some to different stages for demonstration
            $newStatus = ['under_review', 'admin_approval', 'approved'][(int)$app['id'] % 3];
            
            $stmt = $pdo->prepare("UPDATE enrollment_applications SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $app['id']]);
            
            // If admin_approval, simulate registrar and accounting approval
            if ($newStatus === 'admin_approval') {
                $registrar = $pdo->query("SELECT id FROM users WHERE role = 'registrar' LIMIT 1")->fetch();
                $accounting = $pdo->query("SELECT id FROM users WHERE role = 'accounting' LIMIT 1")->fetch();
                
                if ($registrar) {
                    $stmt = $pdo->prepare("UPDATE enrollment_applications SET 
                        registrar_reviewed_by = ?, 
                        registrar_reviewed_at = NOW(), 
                        registrar_notes = 'Documents verified and approved for enrollment.' 
                        WHERE id = ?");
                    $stmt->execute([$registrar['id'], $app['id']]);
                }
                
                if ($accounting) {
                    $stmt = $pdo->prepare("UPDATE enrollment_applications SET 
                        accounting_reviewed_by = ?, 
                        accounting_reviewed_at = NOW(), 
                        accounting_notes = 'Payment requirements reviewed and approved.',
                        total_fees = 27500.00,
                        paid_amount = 27500.00,
                        payment_status = 'paid'
                        WHERE id = ?");
                    $stmt->execute([$accounting['id'], $app['id']]);
                }
            }
            
            echo "✓ Updated application #{$app['application_number']} status to: $newStatus\n";
        }
    }
    
    // Create some additional test applications in different statuses
    $testApplications = [
        [
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'email' => 'maria.santos@email.com',
            'phone' => '09123456789',
            'birth_date' => '2006-03-15',
            'address' => '123 Main Street, Manila',
            'parent_name' => 'Roberto Santos',
            'parent_phone' => '09987654321',
            'grade_level' => 'grade11',
            'strand' => 'stem',
            'previous_school' => 'Manila High School',
            'previous_gpa' => 3.8,
            'status' => 'admin_approval'
        ],
        [
            'first_name' => 'Carlos',
            'last_name' => 'Reyes',
            'email' => 'carlos.reyes@email.com',
            'phone' => '09234567890',
            'birth_date' => '2005-07-22',
            'address' => '456 Oak Avenue, Quezon City',
            'parent_name' => 'Elena Reyes',
            'parent_phone' => '09876543210',
            'grade_level' => 'grade12',
            'strand' => 'abm',
            'previous_school' => 'Quezon City High School',
            'previous_gpa' => 3.6,
            'status' => 'under_review'
        ]
    ];
    
    foreach ($testApplications as $appData) {
        // Check if application already exists
        $stmt = $pdo->prepare("SELECT id FROM enrollment_applications WHERE email = ?");
        $stmt->execute([$appData['email']]);
        if (!$stmt->fetch()) {
            $applicationId = $pdo->query("SELECT MAX(id) + 1 as next_id FROM enrollment_applications")->fetch()['next_id'] ?: 1;
            $applicationNumber = 'APP-' . date('Y') . '-' . str_pad($applicationId, 6, '0', STR_PAD_LEFT);
            
            $stmt = $pdo->prepare("
                INSERT INTO enrollment_applications 
                (application_number, first_name, last_name, email, phone, birth_date, address, 
                 parent_name, parent_phone, grade_level, strand, previous_school, previous_gpa, 
                 status, submitted_at, total_fees, payment_status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 27500.00, 'pending')
            ");
            
            $stmt->execute([
                $applicationNumber,
                $appData['first_name'],
                $appData['last_name'],
                $appData['email'],
                $appData['phone'],
                $appData['birth_date'],
                $appData['address'],
                $appData['parent_name'],
                $appData['parent_phone'],
                $appData['grade_level'],
                $appData['strand'],
                $appData['previous_school'],
                $appData['previous_gpa'],
                $appData['status']
            ]);
            
            $newAppId = $pdo->lastInsertId();
            
            // If admin_approval status, add registrar and accounting approvals
            if ($appData['status'] === 'admin_approval') {
                $registrar = $pdo->query("SELECT id FROM users WHERE role = 'registrar' LIMIT 1")->fetch();
                $accounting = $pdo->query("SELECT id FROM users WHERE role = 'accounting' LIMIT 1")->fetch();
                
                if ($registrar) {
                    $stmt = $pdo->prepare("UPDATE enrollment_applications SET 
                        registrar_reviewed_by = ?, 
                        registrar_reviewed_at = NOW(), 
                        registrar_notes = 'All required documents submitted and verified.' 
                        WHERE id = ?");
                    $stmt->execute([$registrar['id'], $newAppId]);
                }
                
                if ($accounting) {
                    $stmt = $pdo->prepare("UPDATE enrollment_applications SET 
                        accounting_reviewed_by = ?, 
                        accounting_reviewed_at = NOW(), 
                        accounting_notes = 'Enrollment fees paid in full.',
                        paid_amount = 27500.00,
                        payment_status = 'paid'
                        WHERE id = ?");
                    $stmt->execute([$accounting['id'], $newAppId]);
                }
            }
            
            echo "✓ Created test application: {$appData['first_name']} {$appData['last_name']} ($applicationNumber)\n";
        }
    }
    
    // Add missing function for password generation
    $functionsFile = file_get_contents('includes/functions.php');
    if (strpos($functionsFile, 'generate_random_password') === false) {
        $newFunction = "\n\nfunction generate_random_password(\$length = 8) {\n    \$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';\n    return substr(str_shuffle(\$chars), 0, \$length);\n}\n\nfunction generate_student_id(\$grade_level = null) {\n    return date('Y') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);\n}\n";
        file_put_contents('includes/functions.php', $functionsFile . $newFunction);
        echo "✓ Added missing helper functions\n";
    }
    
    echo "\n✅ Enrollment applications functionality fixed successfully!\n";
    echo "\nSUMMARY:\n";
    echo "========\n";
    
    // Show current application statistics
    $stats = [
        'total' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications")->fetchColumn(),
        'pending' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE status = 'pending'")->fetchColumn(),
        'under_review' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE status = 'under_review'")->fetchColumn(),
        'admin_approval' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE status = 'admin_approval'")->fetchColumn(),
        'approved' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE status = 'approved'")->fetchColumn(),
        'enrolled' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE status = 'enrolled'")->fetchColumn(),
        'rejected' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE status = 'rejected'")->fetchColumn()
    ];
    
    foreach ($stats as $status => $count) {
        echo "- " . ucfirst(str_replace('_', ' ', $status)) . ": $count applications\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>
