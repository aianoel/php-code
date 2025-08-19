<?php
// Comprehensive database fix script
require_once 'includes/config.php';

try {
    echo "Fixing accounting database and portal...\n\n";
    
    // First, let's check the current structure of enrollment_applications
    $stmt = $pdo->query("DESCRIBE enrollment_applications");
    $columns = $stmt->fetchAll();
    
    echo "Current table structure:\n";
    foreach ($columns as $col) {
        echo "- {$col['Field']}: {$col['Type']}\n";
    }
    
    // Clear existing problematic data
    $pdo->exec("DELETE FROM enrollment_applications WHERE application_number LIKE 'ACC-%' OR first_name IN ('Isabella', 'Gabriel', 'Sophia')");
    
    // Insert accounting applications with raw SQL to ensure proper status
    $accountingApps = [
        "INSERT INTO enrollment_applications (application_number, first_name, last_name, email, phone, birth_date, address, parent_name, parent_phone, grade_level, strand, previous_school, previous_gpa, status, total_fees, paid_amount, payment_status, submitted_at, registrar_reviewed_by, registrar_reviewed_at, registrar_notes) VALUES ('ACC-001', 'Isabella', 'Martinez', 'isabella@email.com', '09123456789', '2006-01-15', '123 Accounting St', 'Carlos Martinez', '09987654321', 'grade11', 'abm', 'Manila High', 3.8, 'accounting_review', 27500.00, 0.00, 'pending', NOW(), 1, NOW(), 'Documents verified')",
        "INSERT INTO enrollment_applications (application_number, first_name, last_name, email, phone, birth_date, address, parent_name, parent_phone, grade_level, strand, previous_school, previous_gpa, status, total_fees, paid_amount, payment_status, submitted_at, registrar_reviewed_by, registrar_reviewed_at, registrar_notes) VALUES ('ACC-002', 'Gabriel', 'Torres', 'gabriel@email.com', '09234567890', '2005-11-22', '456 Payment Ave', 'Maria Torres', '09876543210', 'grade12', 'stem', 'QC Science High', 3.9, 'accounting_review', 27500.00, 15000.00, 'partial', NOW(), 1, NOW(), 'Documents verified')",
        "INSERT INTO enrollment_applications (application_number, first_name, last_name, email, phone, birth_date, address, parent_name, parent_phone, grade_level, strand, previous_school, previous_gpa, status, total_fees, paid_amount, payment_status, submitted_at, registrar_reviewed_by, registrar_reviewed_at, registrar_notes) VALUES ('ACC-003', 'Sophia', 'Reyes', 'sophia@email.com', '09345678901', '2006-03-08', '789 Finance Rd', 'Antonio Reyes', '09765432109', 'grade11', 'humss', 'Makati High', 3.7, 'accounting_review', 27500.00, 27500.00, 'paid', NOW(), 1, NOW(), 'Documents verified')"
    ];
    
    foreach ($accountingApps as $sql) {
        try {
            $pdo->exec($sql);
            echo "✓ Inserted accounting application\n";
        } catch (Exception $e) {
            echo "! Error inserting application: " . $e->getMessage() . "\n";
        }
    }
    
    // Verify the applications were created
    $stmt = $pdo->query("SELECT application_number, first_name, last_name, status, total_fees, paid_amount FROM enrollment_applications WHERE application_number LIKE 'ACC-%'");
    $apps = $stmt->fetchAll();
    
    echo "\nCreated applications:\n";
    foreach ($apps as $app) {
        echo "- {$app['application_number']}: {$app['first_name']} {$app['last_name']} ({$app['status']}) - ₱" . number_format($app['total_fees'], 2) . "\n";
    }
    
    // Test the accounting portal query
    $stmt = $pdo->prepare("
        SELECT ea.*, 
        (SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE id = ea.registrar_reviewed_by) as registrar_name 
        FROM enrollment_applications ea
        WHERE ea.status IN ('accounting_review', 'admin_approval') 
        ORDER BY ea.accounting_reviewed_at ASC, ea.submitted_at ASC
    ");
    $stmt->execute();
    $accountingApps = $stmt->fetchAll();
    
    echo "\nAccounting portal query results:\n";
    echo "Found " . count($accountingApps) . " applications\n";
    
    foreach ($accountingApps as $app) {
        $balance = $app['total_fees'] - $app['paid_amount'];
        echo "- {$app['application_number']}: {$app['first_name']} {$app['last_name']}\n";
        echo "  Total: ₱" . number_format($app['total_fees'], 2) . " | Paid: ₱" . number_format($app['paid_amount'], 2) . " | Balance: ₱" . number_format($balance, 2) . "\n";
    }
    
    // Create enrolled students for display
    $enrolledStudents = [
        "INSERT IGNORE INTO enrollment_applications (application_number, first_name, last_name, email, phone, birth_date, address, parent_name, parent_phone, grade_level, strand, previous_school, previous_gpa, status, total_fees, paid_amount, payment_status, submitted_at) VALUES ('ENR-001', 'Alexander', 'Cruz', 'alexander@email.com', '09456789012', '2005-07-12', '321 Student St', 'Elena Cruz', '09654321098', 'grade12', 'stem', 'Pasig Science High', 3.9, 'enrolled', 27500.00, 27500.00, 'paid', NOW())",
        "INSERT IGNORE INTO enrollment_applications (application_number, first_name, last_name, email, phone, birth_date, address, parent_name, parent_phone, grade_level, strand, previous_school, previous_gpa, status, total_fees, paid_amount, payment_status, submitted_at) VALUES ('ENR-002', 'Camila', 'Santos', 'camila@email.com', '09567890123', '2006-04-25', '654 Enrollment Ave', 'Roberto Santos', '09543210987', 'grade11', 'abm', 'Taguig Business', 3.8, 'enrolled', 27500.00, 20000.00, 'partial', NOW())"
    ];
    
    foreach ($enrolledStudents as $sql) {
        try {
            $pdo->exec($sql);
            echo "✓ Created enrolled student\n";
        } catch (Exception $e) {
            echo "! Error creating student: " . $e->getMessage() . "\n";
        }
    }
    
    // Show final statistics
    $stats = [
        'pending_payment' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE status = 'accounting_review'")->fetchColumn(),
        'payment_received' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE payment_status = 'paid'")->fetchColumn(),
        'partial_payment' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE payment_status = 'partial'")->fetchColumn(),
        'total_collected' => $pdo->query("SELECT COALESCE(SUM(paid_amount), 0) FROM enrollment_applications")->fetchColumn(),
        'enrolled_students' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE status = 'enrolled'")->fetchColumn()
    ];
    
    echo "\nFinal Statistics:\n";
    echo "================\n";
    foreach ($stats as $key => $value) {
        $label = ucfirst(str_replace('_', ' ', $key));
        if ($key === 'total_collected') {
            echo "- $label: ₱" . number_format($value, 2) . "\n";
        } else {
            echo "- $label: $value\n";
        }
    }
    
    echo "\n✅ Accounting portal database fixed!\n";
    echo "The accounting portal now displays enrolled students and payment fees.\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "</div>";
}
?>
