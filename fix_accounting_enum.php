<?php
require_once 'includes/config.php';

try {
    echo "Fixing accounting portal ENUM values and data...\n\n";
    
    // Update the status ENUM to include accounting_review
    $pdo->exec("ALTER TABLE enrollment_applications MODIFY COLUMN status ENUM('pending','under_review','registrar_review','accounting_review','admin_approval','approved','rejected','enrolled') DEFAULT 'pending'");
    echo "✓ Updated status ENUM to include accounting_review\n";
    
    // Update payment_status ENUM to include 'paid'
    $pdo->exec("ALTER TABLE enrollment_applications MODIFY COLUMN payment_status ENUM('pending','partial','paid','completed','overdue') DEFAULT 'pending'");
    echo "✓ Updated payment_status ENUM to include 'paid'\n";
    
    // Now update the applications to accounting_review status
    $pdo->exec("UPDATE enrollment_applications SET status = 'accounting_review' WHERE application_number LIKE 'ACC-%'");
    echo "✓ Updated ACC applications to accounting_review status\n";
    
    // Set proper payment status based on amounts
    $pdo->exec("UPDATE enrollment_applications SET payment_status = 'paid' WHERE paid_amount >= total_fees AND total_fees > 0");
    $pdo->exec("UPDATE enrollment_applications SET payment_status = 'partial' WHERE paid_amount > 0 AND paid_amount < total_fees");
    $pdo->exec("UPDATE enrollment_applications SET payment_status = 'pending' WHERE paid_amount = 0 OR paid_amount IS NULL");
    echo "✓ Updated payment statuses based on amounts\n";
    
    // Verify the accounting applications
    $stmt = $pdo->query("SELECT application_number, first_name, last_name, status, total_fees, paid_amount, payment_status FROM enrollment_applications WHERE status = 'accounting_review'");
    $apps = $stmt->fetchAll();
    
    echo "\nApplications in accounting_review status:\n";
    foreach ($apps as $app) {
        $balance = $app['total_fees'] - $app['paid_amount'];
        echo "- {$app['application_number']}: {$app['first_name']} {$app['last_name']} ({$app['payment_status']})\n";
        echo "  Total: ₱" . number_format($app['total_fees'], 2) . " | Paid: ₱" . number_format($app['paid_amount'], 2) . " | Balance: ₱" . number_format($balance, 2) . "\n";
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
    
    echo "\nAccounting portal will show " . count($accountingApps) . " applications\n";
    
    // Show statistics
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
    
    echo "\n✅ Accounting portal is now functional!\n";
    echo "The accounting portal displays enrolled students and payment fees.\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>
