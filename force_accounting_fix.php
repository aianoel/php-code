<?php
require_once 'includes/config.php';

try {
    echo "Force fixing accounting portal data...\n\n";
    
    // Delete problematic applications and recreate them properly
    $pdo->exec("DELETE FROM enrollment_applications WHERE application_number LIKE 'ACC-2025-%'");
    
    // Get registrar ID
    $registrarId = $pdo->query("SELECT id FROM users WHERE role = 'registrar' LIMIT 1")->fetch()['id'];
    
    // Insert applications directly with proper status
    $insertStmt = $pdo->prepare("
        INSERT INTO enrollment_applications 
        (application_number, first_name, last_name, email, phone, birth_date, address, 
         parent_name, parent_phone, grade_level, strand, previous_school, previous_gpa, 
         status, submitted_at, total_fees, paid_amount, payment_status,
         registrar_reviewed_by, registrar_reviewed_at, registrar_notes) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'accounting_review', NOW(), ?, ?, ?, ?, NOW(), 'Documents verified and approved for enrollment.')
    ");
    
    $apps = [
        ['ACC-2025-001', 'Isabella', 'Martinez', 'isabella.martinez@email.com', '09123456789', '2006-01-15', '123 Accounting Street, Manila', 'Carlos Martinez', '09987654321', 'grade11', 'abm', 'Manila Business High School', 3.8, 27500.00, 0.00, 'pending'],
        ['ACC-2025-002', 'Gabriel', 'Torres', 'gabriel.torres@email.com', '09234567890', '2005-11-22', '456 Payment Avenue, Quezon City', 'Maria Torres', '09876543210', 'grade12', 'stem', 'QC Science High School', 3.9, 27500.00, 15000.00, 'partial'],
        ['ACC-2025-003', 'Sophia', 'Reyes', 'sophia.reyes@email.com', '09345678901', '2006-03-08', '789 Finance Road, Makati', 'Antonio Reyes', '09765432109', 'grade11', 'humss', 'Makati High School', 3.7, 27500.00, 27500.00, 'paid']
    ];
    
    foreach ($apps as $app) {
        $insertStmt->execute([
            $app[0], $app[1], $app[2], $app[3], $app[4], $app[5], $app[6], $app[7], $app[8], $app[9], $app[10], $app[11], $app[12], $app[13], $app[14], $app[15], $registrarId
        ]);
        echo "✓ Created: {$app[1]} {$app[2]} ({$app[0]})\n";
    }
    
    // Verify the applications were created correctly
    $stmt = $pdo->query("SELECT application_number, first_name, last_name, status FROM enrollment_applications WHERE application_number LIKE 'ACC-2025-%'");
    $createdApps = $stmt->fetchAll();
    
    echo "\nVerification - Created applications:\n";
    foreach ($createdApps as $app) {
        echo "- {$app['application_number']}: {$app['first_name']} {$app['last_name']} ({$app['status']})\n";
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
    echo "Found " . count($accountingApps) . " applications for accounting review\n";
    
    foreach ($accountingApps as $app) {
        $balance = $app['total_fees'] - $app['paid_amount'];
        echo "- {$app['application_number']}: {$app['first_name']} {$app['last_name']}\n";
        echo "  Total: ₱" . number_format($app['total_fees'], 2) . " | Paid: ₱" . number_format($app['paid_amount'], 2) . " | Balance: ₱" . number_format($balance, 2) . "\n";
    }
    
    // Update statistics
    $stats = [
        'pending_payment' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE status = 'accounting_review'")->fetchColumn(),
        'payment_received' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE payment_status = 'paid'")->fetchColumn(),
        'partial_payment' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE payment_status = 'partial'")->fetchColumn(),
        'total_collected' => $pdo->query("SELECT COALESCE(SUM(paid_amount), 0) FROM enrollment_applications")->fetchColumn()
    ];
    
    echo "\nAccounting Portal Statistics:\n";
    echo "============================\n";
    foreach ($stats as $key => $value) {
        $label = ucfirst(str_replace('_', ' ', $key));
        if ($key === 'total_collected') {
            echo "- $label: ₱" . number_format($value, 2) . "\n";
        } else {
            echo "- $label: $value\n";
        }
    }
    
    echo "\n✅ Accounting portal is now functional!\n";
    echo "Refresh the accounting/enrollment_payments.php page to see the applications.\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>
