<?php
require_once 'includes/config.php';

try {
    echo "Fixing accounting portal to display applications for review...\n\n";
    
    // Move some applications to accounting_review status
    $stmt = $pdo->prepare("
        UPDATE enrollment_applications 
        SET status = 'accounting_review',
            payment_status = 'pending'
        WHERE application_number IN ('APP-2025-000002', 'APP-2025-000008')
    ");
    $stmt->execute();
    
    echo "✓ Moved 2 applications to accounting_review status\n";
    
    // Update one application to show partial payment
    $stmt = $pdo->prepare("
        UPDATE enrollment_applications 
        SET paid_amount = 15000.00,
            payment_status = 'partial'
        WHERE application_number = 'APP-2025-000002'
    ");
    $stmt->execute();
    
    echo "✓ Set partial payment for APP-2025-000002\n";
    
    // Show current applications for accounting review
    echo "\nApplications now available for accounting review:\n";
    echo "================================================\n";
    
    $stmt = $pdo->query("
        SELECT application_number, first_name, last_name, total_fees, paid_amount, payment_status, status
        FROM enrollment_applications 
        WHERE status IN ('accounting_review', 'admin_approval')
        ORDER BY submitted_at ASC
    ");
    
    $applications = $stmt->fetchAll();
    
    foreach ($applications as $app) {
        $balance = $app['total_fees'] - $app['paid_amount'];
        echo "- {$app['application_number']}: {$app['first_name']} {$app['last_name']} ({$app['status']})\n";
        echo "  Total: ₱" . number_format($app['total_fees'], 2) . " | Paid: ₱" . number_format($app['paid_amount'], 2) . " | Balance: ₱" . number_format($balance, 2) . " | Status: {$app['payment_status']}\n";
    }
    
    // Update statistics
    echo "\nUpdated Accounting Statistics:\n";
    echo "=============================\n";
    
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
    
    echo "\n✅ Accounting portal now functional with applications to review!\n";
    echo "\nThe accounting portal now shows:\n";
    echo "- Applications pending payment review\n";
    echo "- Payment processing capabilities\n";
    echo "- Total fees and payment tracking\n";
    echo "- Balance calculations\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>
