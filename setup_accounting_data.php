<?php
require_once 'includes/config.php';

try {
    echo "Setting up accounting portal with functional data...\n\n";
    
    // First, let's directly insert applications in accounting_review status
    $registrarId = $pdo->query("SELECT id FROM users WHERE role = 'registrar' LIMIT 1")->fetch()['id'];
    
    // Clear and recreate specific applications for accounting review
    $pdo->exec("DELETE FROM enrollment_applications WHERE application_number IN ('ACC-2025-001', 'ACC-2025-002', 'ACC-2025-003')");
    
    $accountingApps = [
        [
            'application_number' => 'ACC-2025-001',
            'first_name' => 'Isabella',
            'last_name' => 'Martinez',
            'email' => 'isabella.martinez@email.com',
            'phone' => '09123456789',
            'birth_date' => '2006-01-15',
            'address' => '123 Accounting Street, Manila',
            'parent_name' => 'Carlos Martinez',
            'parent_phone' => '09987654321',
            'grade_level' => 'grade11',
            'strand' => 'abm',
            'previous_school' => 'Manila Business High School',
            'previous_gpa' => 3.8,
            'status' => 'accounting_review',
            'total_fees' => 27500.00,
            'paid_amount' => 0.00,
            'payment_status' => 'pending'
        ],
        [
            'application_number' => 'ACC-2025-002',
            'first_name' => 'Gabriel',
            'last_name' => 'Torres',
            'email' => 'gabriel.torres@email.com',
            'phone' => '09234567890',
            'birth_date' => '2005-11-22',
            'address' => '456 Payment Avenue, Quezon City',
            'parent_name' => 'Maria Torres',
            'parent_phone' => '09876543210',
            'grade_level' => 'grade12',
            'strand' => 'stem',
            'previous_school' => 'QC Science High School',
            'previous_gpa' => 3.9,
            'status' => 'accounting_review',
            'total_fees' => 27500.00,
            'paid_amount' => 15000.00,
            'payment_status' => 'partial'
        ],
        [
            'application_number' => 'ACC-2025-003',
            'first_name' => 'Sophia',
            'last_name' => 'Reyes',
            'email' => 'sophia.reyes@email.com',
            'phone' => '09345678901',
            'birth_date' => '2006-03-08',
            'address' => '789 Finance Road, Makati',
            'parent_name' => 'Antonio Reyes',
            'parent_phone' => '09765432109',
            'grade_level' => 'grade11',
            'strand' => 'humss',
            'previous_school' => 'Makati High School',
            'previous_gpa' => 3.7,
            'status' => 'accounting_review',
            'total_fees' => 27500.00,
            'paid_amount' => 27500.00,
            'payment_status' => 'paid'
        ]
    ];
    
    foreach ($accountingApps as $app) {
        $stmt = $pdo->prepare("
            INSERT INTO enrollment_applications 
            (application_number, first_name, last_name, email, phone, birth_date, address, 
             parent_name, parent_phone, grade_level, strand, previous_school, previous_gpa, 
             status, submitted_at, total_fees, paid_amount, payment_status,
             registrar_reviewed_by, registrar_reviewed_at, registrar_notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, NOW(), 'Documents verified and approved for enrollment.')
        ");
        
        $stmt->execute([
            $app['application_number'],
            $app['first_name'],
            $app['last_name'],
            $app['email'],
            $app['phone'],
            $app['birth_date'],
            $app['address'],
            $app['parent_name'],
            $app['parent_phone'],
            $app['grade_level'],
            $app['strand'],
            $app['previous_school'],
            $app['previous_gpa'],
            $app['status'],
            $app['total_fees'],
            $app['paid_amount'],
            $app['payment_status'],
            $registrarId
        ]);
        
        echo "✓ Created accounting application: {$app['first_name']} {$app['last_name']} ({$app['application_number']})\n";
    }
    
    // Create enrolled students with payment history
    $enrolledStudents = [
        [
            'application_number' => 'ENR-2025-001',
            'first_name' => 'Alexander',
            'last_name' => 'Cruz',
            'email' => 'alexander.cruz@email.com',
            'phone' => '09456789012',
            'birth_date' => '2005-07-12',
            'address' => '321 Student Street, Pasig',
            'parent_name' => 'Elena Cruz',
            'parent_phone' => '09654321098',
            'grade_level' => 'grade12',
            'strand' => 'stem',
            'previous_school' => 'Pasig Science High School',
            'previous_gpa' => 3.9,
            'status' => 'enrolled',
            'total_fees' => 27500.00,
            'paid_amount' => 27500.00,
            'payment_status' => 'paid'
        ],
        [
            'application_number' => 'ENR-2025-002',
            'first_name' => 'Camila',
            'last_name' => 'Santos',
            'email' => 'camila.santos@email.com',
            'phone' => '09567890123',
            'birth_date' => '2006-04-25',
            'address' => '654 Enrollment Ave, Taguig',
            'parent_name' => 'Roberto Santos',
            'parent_phone' => '09543210987',
            'grade_level' => 'grade11',
            'strand' => 'abm',
            'previous_school' => 'Taguig Business School',
            'previous_gpa' => 3.8,
            'status' => 'enrolled',
            'total_fees' => 27500.00,
            'paid_amount' => 20000.00,
            'payment_status' => 'partial'
        ]
    ];
    
    $accountingId = $pdo->query("SELECT id FROM users WHERE role = 'accounting' LIMIT 1")->fetch()['id'] ?? null;
    
    foreach ($enrolledStudents as $student) {
        // Check if application already exists
        $stmt = $pdo->prepare("SELECT id FROM enrollment_applications WHERE email = ?");
        $stmt->execute([$student['email']]);
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare("
                INSERT INTO enrollment_applications 
                (application_number, first_name, last_name, email, phone, birth_date, address, 
                 parent_name, parent_phone, grade_level, strand, previous_school, previous_gpa, 
                 status, submitted_at, total_fees, paid_amount, payment_status,
                 registrar_reviewed_by, registrar_reviewed_at, registrar_notes,
                 accounting_reviewed_by, accounting_reviewed_at, accounting_notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, NOW(), 'Documents verified.', ?, NOW(), 'Payment processed and verified.')
            ");
            
            $stmt->execute([
                $student['application_number'],
                $student['first_name'],
                $student['last_name'],
                $student['email'],
                $student['phone'],
                $student['birth_date'],
                $student['address'],
                $student['parent_name'],
                $student['parent_phone'],
                $student['grade_level'],
                $student['strand'],
                $student['previous_school'],
                $student['previous_gpa'],
                $student['status'],
                $student['total_fees'],
                $student['paid_amount'],
                $student['payment_status'],
                $registrarId,
                $accountingId
            ]);
            
            $newAppId = $pdo->lastInsertId();
            
            // Create user account
            $username = strtolower($student['first_name'] . '.' . $student['last_name']);
            $password = generate_password(8);
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password, role, first_name, last_name, status, created_at)
                VALUES (?, ?, ?, 'student', ?, ?, 'active', NOW())
            ");
            
            $stmt->execute([
                $username,
                $student['email'],
                $hashedPassword,
                $student['first_name'],
                $student['last_name']
            ]);
            
            $userId = $pdo->lastInsertId();
            
            // Create student record
            $studentId = generate_student_id($student['grade_level']);
            $stmt = $pdo->prepare("
                INSERT INTO students (user_id, student_id, grade_level, strand, enrollment_date, status)
                VALUES (?, ?, ?, ?, NOW(), 'enrolled')
            ");
            
            $stmt->execute([
                $userId,
                $studentId,
                $student['grade_level'],
                $student['strand']
            ]);
            
            // Update application with user ID
            $stmt = $pdo->prepare("UPDATE enrollment_applications SET student_user_id = ? WHERE id = ?");
            $stmt->execute([$userId, $newAppId]);
            
            echo "✓ Created enrolled student: {$student['first_name']} {$student['last_name']} (Student ID: $studentId)\n";
        }
    }
    
    // Display accounting portal statistics
    echo "\nAccounting Portal Data:\n";
    echo "======================\n";
    
    $stats = [
        'pending_payment' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE status = 'accounting_review'")->fetchColumn(),
        'payment_received' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE payment_status = 'paid'")->fetchColumn(),
        'partial_payment' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE payment_status = 'partial'")->fetchColumn(),
        'total_collected' => $pdo->query("SELECT COALESCE(SUM(paid_amount), 0) FROM enrollment_applications")->fetchColumn(),
        'enrolled_students' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE status = 'enrolled'")->fetchColumn()
    ];
    
    foreach ($stats as $key => $value) {
        $label = ucfirst(str_replace('_', ' ', $key));
        if ($key === 'total_collected') {
            echo "- $label: ₱" . number_format($value, 2) . "\n";
        } else {
            echo "- $label: $value\n";
        }
    }
    
    // Show applications for accounting review
    echo "\nApplications for Accounting Review:\n";
    echo "==================================\n";
    
    $stmt = $pdo->query("
        SELECT application_number, first_name, last_name, total_fees, paid_amount, payment_status
        FROM enrollment_applications 
        WHERE status = 'accounting_review'
        ORDER BY submitted_at ASC
    ");
    
    $accountingApps = $stmt->fetchAll();
    
    foreach ($accountingApps as $app) {
        $balance = $app['total_fees'] - $app['paid_amount'];
        echo "- {$app['application_number']}: {$app['first_name']} {$app['last_name']}\n";
        echo "  Total: ₱" . number_format($app['total_fees'], 2) . " | Paid: ₱" . number_format($app['paid_amount'], 2) . " | Balance: ₱" . number_format($balance, 2) . "\n";
    }
    
    // Show enrolled students
    echo "\nEnrolled Students:\n";
    echo "=================\n";
    
    $stmt = $pdo->query("
        SELECT ea.application_number, ea.first_name, ea.last_name, ea.total_fees, ea.paid_amount, ea.payment_status, s.student_id
        FROM enrollment_applications ea
        LEFT JOIN students s ON ea.student_user_id = s.user_id
        WHERE ea.status = 'enrolled'
        ORDER BY ea.first_name, ea.last_name
    ");
    
    $enrolledStudents = $stmt->fetchAll();
    
    foreach ($enrolledStudents as $student) {
        $balance = $student['total_fees'] - $student['paid_amount'];
        echo "- {$student['student_id']}: {$student['first_name']} {$student['last_name']}\n";
        echo "  Total: ₱" . number_format($student['total_fees'], 2) . " | Paid: ₱" . number_format($student['paid_amount'], 2) . " | Status: {$student['payment_status']}\n";
    }
    
    echo "\n✅ Accounting portal setup completed successfully!\n";
    echo "The accounting portal now displays:\n";
    echo "- Applications pending payment review\n";
    echo "- Enrolled students with payment status\n";
    echo "- Payment fee totals and balances\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>
