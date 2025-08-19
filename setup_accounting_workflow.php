<?php
require_once 'includes/config.php';

try {
    echo "Setting up accounting workflow with enrolled students and payment data...\n\n";
    
    // First, let's move some applications to accounting_review status
    $stmt = $pdo->prepare("
        UPDATE enrollment_applications 
        SET status = 'accounting_review',
            registrar_reviewed_by = (SELECT id FROM users WHERE role = 'registrar' LIMIT 1),
            registrar_reviewed_at = NOW(),
            registrar_notes = 'Documents verified and approved for enrollment.'
        WHERE application_number IN ('APP-2025-000002', 'APP-2025-000008')
    ");
    $stmt->execute();
    
    echo "✓ Moved 2 applications to accounting_review status\n";
    
    // Create some enrolled students with payment data
    $enrolledStudents = [
        [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'juan.delacruz@email.com',
            'phone' => '09456789012',
            'birth_date' => '2005-12-05',
            'address' => '321 Rizal Street, Pasig City',
            'parent_name' => 'Maria Dela Cruz',
            'parent_phone' => '09765432109',
            'grade_level' => 'grade12',
            'strand' => 'stem',
            'previous_school' => 'Pasig High School',
            'previous_gpa' => 3.9,
            'status' => 'enrolled',
            'total_fees' => 27500.00,
            'paid_amount' => 27500.00,
            'payment_status' => 'paid'
        ],
        [
            'first_name' => 'Sofia',
            'last_name' => 'Gonzales',
            'email' => 'sofia.gonzales@email.com',
            'phone' => '09567890123',
            'birth_date' => '2006-04-18',
            'address' => '654 Bonifacio Avenue, Taguig City',
            'parent_name' => 'Roberto Gonzales',
            'parent_phone' => '09654321098',
            'grade_level' => 'grade11',
            'strand' => 'abm',
            'previous_school' => 'Taguig Science High School',
            'previous_gpa' => 3.7,
            'status' => 'enrolled',
            'total_fees' => 27500.00,
            'paid_amount' => 15000.00,
            'payment_status' => 'partial'
        ],
        [
            'first_name' => 'Miguel',
            'last_name' => 'Santos',
            'email' => 'miguel.santos@email.com',
            'phone' => '09678901234',
            'birth_date' => '2005-08-22',
            'address' => '987 Luna Street, Quezon City',
            'parent_name' => 'Carmen Santos',
            'parent_phone' => '09543210987',
            'grade_level' => 'grade12',
            'strand' => 'humss',
            'previous_school' => 'QC High School',
            'previous_gpa' => 3.8,
            'status' => 'enrolled',
            'total_fees' => 27500.00,
            'paid_amount' => 27500.00,
            'payment_status' => 'paid'
        ]
    ];
    
    $accountingUser = $pdo->query("SELECT id FROM users WHERE role = 'accounting' LIMIT 1")->fetch();
    
    foreach ($enrolledStudents as $student) {
        // Check if application already exists
        $stmt = $pdo->prepare("SELECT id FROM enrollment_applications WHERE email = ?");
        $stmt->execute([$student['email']]);
        if (!$stmt->fetch()) {
            $applicationId = $pdo->query("SELECT MAX(id) + 1 as next_id FROM enrollment_applications")->fetch()['next_id'] ?: 1;
            $applicationNumber = 'APP-' . date('Y') . '-' . str_pad($applicationId, 6, '0', STR_PAD_LEFT);
            
            $stmt = $pdo->prepare("
                INSERT INTO enrollment_applications 
                (application_number, first_name, last_name, email, phone, birth_date, address, 
                 parent_name, parent_phone, grade_level, strand, previous_school, previous_gpa, 
                 status, submitted_at, total_fees, paid_amount, payment_status,
                 registrar_reviewed_by, registrar_reviewed_at, registrar_notes,
                 accounting_reviewed_by, accounting_reviewed_at, accounting_notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, NOW(), 'Documents verified and approved.', ?, NOW(), 'Payment processed and verified.')
            ");
            
            $registrarId = $pdo->query("SELECT id FROM users WHERE role = 'registrar' LIMIT 1")->fetch()['id'];
            
            $stmt->execute([
                $applicationNumber,
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
                $accountingUser ? $accountingUser['id'] : null
            ]);
            
            $newAppId = $pdo->lastInsertId();
            
            // Create user account for enrolled students
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
            
            echo "✓ Created enrolled student: {$student['first_name']} {$student['last_name']} ($applicationNumber) - Student ID: $studentId\n";
        }
    }
    
    // Update payment fees for existing applications
    $stmt = $pdo->prepare("UPDATE enrollment_applications SET total_fees = 27500.00 WHERE total_fees = 0 OR total_fees IS NULL");
    $stmt->execute();
    
    echo "\n✓ Updated payment fees for all applications\n";
    
    // Show current accounting statistics
    echo "\nAccounting Portal Statistics:\n";
    echo "============================\n";
    
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
    
    if (empty($accountingApps)) {
        echo "- No applications pending accounting review\n";
    } else {
        foreach ($accountingApps as $app) {
            $balance = $app['total_fees'] - $app['paid_amount'];
            echo "- {$app['application_number']}: {$app['first_name']} {$app['last_name']} - ₱" . number_format($app['total_fees'], 2) . " (Paid: ₱" . number_format($app['paid_amount'], 2) . ", Balance: ₱" . number_format($balance, 2) . ")\n";
        }
    }
    
    // Show enrolled students
    echo "\nEnrolled Students with Payment Status:\n";
    echo "=====================================\n";
    
    $stmt = $pdo->query("
        SELECT ea.application_number, ea.first_name, ea.last_name, ea.total_fees, ea.paid_amount, ea.payment_status, s.student_id
        FROM enrollment_applications ea
        LEFT JOIN students s ON ea.student_user_id = s.user_id
        WHERE ea.status = 'enrolled'
        ORDER BY ea.first_name, ea.last_name
    ");
    
    $enrolledStudents = $stmt->fetchAll();
    
    if (empty($enrolledStudents)) {
        echo "- No enrolled students found\n";
    } else {
        foreach ($enrolledStudents as $student) {
            $balance = $student['total_fees'] - $student['paid_amount'];
            echo "- {$student['student_id']}: {$student['first_name']} {$student['last_name']} - ₱" . number_format($student['total_fees'], 2) . " (Paid: ₱" . number_format($student['paid_amount'], 2) . ", Status: {$student['payment_status']})\n";
        }
    }
    
    echo "\n✅ Accounting workflow setup completed successfully!\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>
