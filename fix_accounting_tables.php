<?php
require_once 'includes/config.php';

try {
    echo "Fixing accounting tables and workflow...\n\n";
    
    // Fix empty status values first
    $pdo->exec("UPDATE enrollment_applications SET status = 'pending' WHERE status IS NULL OR status = ''");
    echo "✓ Fixed empty status values\n";
    
    // Set specific applications to accounting_review status
    $stmt = $pdo->prepare("
        UPDATE enrollment_applications 
        SET status = 'accounting_review',
            registrar_reviewed_by = (SELECT id FROM users WHERE role = 'registrar' LIMIT 1),
            registrar_reviewed_at = NOW(),
            registrar_notes = 'Documents verified and approved for enrollment.'
        WHERE application_number IN ('APP-2025-000002', 'APP-2025-000008')
    ");
    $stmt->execute();
    echo "✓ Set applications to accounting_review status\n";

    // Update payment data
    $pdo->exec("UPDATE enrollment_applications SET payment_status = 'pending' WHERE payment_status IS NULL OR payment_status = ''");
    $pdo->exec("UPDATE enrollment_applications SET total_fees = 27500.00 WHERE total_fees IS NULL OR total_fees = 0");
    $pdo->exec("UPDATE enrollment_applications SET paid_amount = 0 WHERE paid_amount IS NULL");
    
    echo "✓ Updated payment data\n";
    
    // Set partial payment for one application
    $stmt = $pdo->prepare("
        UPDATE enrollment_applications 
        SET paid_amount = 15000.00, payment_status = 'partial'
        WHERE application_number = 'APP-2025-000002'
    ");
    $stmt->execute();
    
    echo "✓ Set partial payment for APP-2025-000002\n";

    // Verify accounting applications
    $stmt = $pdo->query("
        SELECT application_number, first_name, last_name, status, total_fees, paid_amount, payment_status
        FROM enrollment_applications 
        WHERE status = 'accounting_review'
    ");
    $accountingApps = $stmt->fetchAll();
    
    echo "\nApplications for Accounting Review:\n";
    echo "==================================\n";
    foreach ($accountingApps as $app) {
        $balance = $app['total_fees'] - $app['paid_amount'];
        echo "- {$app['application_number']}: {$app['first_name']} {$app['last_name']}\n";
        echo "  Total: ₱" . number_format($app['total_fees'], 2) . " | Paid: ₱" . number_format($app['paid_amount'], 2) . " | Balance: ₱" . number_format($balance, 2) . "\n";
    }

    // Show statistics
    $stats = [
        'pending_payment' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE status = 'accounting_review'")->fetchColumn(),
        'payment_received' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE payment_status = 'paid'")->fetchColumn(),
        'partial_payment' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE payment_status = 'partial'")->fetchColumn(),
        'total_collected' => $pdo->query("SELECT COALESCE(SUM(paid_amount), 0) FROM enrollment_applications")->fetchColumn()
    ];
    
    echo "\nAccounting Statistics:\n";
    echo "=====================\n";
    foreach ($stats as $key => $value) {
        $label = ucfirst(str_replace('_', ' ', $key));
        if ($key === 'total_collected') {
            echo "- $label: ₱" . number_format($value, 2) . "\n";
        } else {
            echo "- $label: $value\n";
        }
    }

    echo "\n✅ Accounting portal now functional with payment tracking!\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>
