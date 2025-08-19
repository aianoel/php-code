<?php
require_once 'includes/config.php';

try {
    // First, let's check if we have any students to create payments for
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM students");
    $student_count = $stmt->fetch()['count'];
    
    if ($student_count == 0) {
        echo "No students found. Creating sample student first...\n";
        
        // Create a sample user first
        $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['John', 'Doe', 'john.doe@example.com', password_hash('password123', PASSWORD_DEFAULT), 'student']);
        $user_id = $pdo->lastInsertId();
        
        // Create a sample student
        $stmt = $pdo->prepare("INSERT INTO students (user_id, student_id, grade_level, strand, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, 'STU-2024-001', '11', 'STEM', 'active']);
        $student_id = $pdo->lastInsertId();
        
        echo "Created sample student with ID: $student_id\n";
    } else {
        // Get the first student ID
        $stmt = $pdo->query("SELECT id FROM students LIMIT 1");
        $student_id = $stmt->fetch()['id'];
        echo "Using existing student ID: $student_id\n";
    }
    
    // Check if payments already exist
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM payments");
    $payment_count = $stmt->fetch()['count'];
    
    if ($payment_count > 0) {
        echo "Payments already exist ($payment_count payments found). Skipping creation.\n";
        exit;
    }
    
    echo "Creating sample payment data...\n";
    
    // Create sample payments for the last 6 months
    $payments = [
        // January 2024
        ['2024-01-15', 'enrollment', 'Enrollment Fee', 15000, 'paid', '2024-01-15'],
        ['2024-01-20', 'tuition', 'First Quarter Tuition', 25000, 'paid', '2024-01-20'],
        ['2024-01-25', 'miscellaneous', 'Books and Supplies', 3500, 'paid', '2024-01-25'],
        
        // February 2024
        ['2024-02-10', 'tuition', 'Second Quarter Tuition', 25000, 'paid', '2024-02-10'],
        ['2024-02-15', 'miscellaneous', 'Laboratory Fee', 2000, 'paid', '2024-02-15'],
        ['2024-02-20', 'miscellaneous', 'Activity Fee', 1500, 'paid', '2024-02-20'],
        
        // March 2024
        ['2024-03-05', 'tuition', 'Third Quarter Tuition', 25000, 'paid', '2024-03-05'],
        ['2024-03-12', 'miscellaneous', 'Field Trip Fee', 800, 'paid', '2024-03-12'],
        
        // April 2024 (some pending)
        ['2024-04-01', 'tuition', 'Fourth Quarter Tuition', 25000, 'paid', '2024-04-01'],
        ['2024-04-15', 'miscellaneous', 'Graduation Fee', 2500, 'pending', null],
        
        // May 2024 (current month - some pending)
        ['2024-05-01', 'miscellaneous', 'Summer Class Fee', 5000, 'pending', null],
        ['2024-05-10', 'penalty', 'Late Payment Fee', 500, 'pending', null],
        
        // Some overdue payments (older than 30 days)
        ['2024-03-01', 'miscellaneous', 'Library Fine', 200, 'pending', null],
        ['2024-02-28', 'penalty', 'Overdue Penalty', 300, 'pending', null],
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO payments (student_id, payment_type, description, amount, status, payment_date, created_at, due_date) 
        VALUES (?, ?, ?, ?, ?, ?, ?, DATE_ADD(?, INTERVAL 30 DAY))
    ");
    
    foreach ($payments as $payment) {
        $created_at = $payment[0];
        $payment_type = $payment[1];
        $description = $payment[2];
        $amount = $payment[3];
        $status = $payment[4];
        $payment_date = $payment[5];
        
        $stmt->execute([
            $student_id,
            $payment_type,
            $description,
            $amount,
            $status,
            $payment_date,
            $created_at,
            $created_at
        ]);
    }
    
    echo "Successfully created " . count($payments) . " sample payments!\n";
    echo "You can now view the accounting dashboard with real data.\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
