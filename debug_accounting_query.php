<?php
require_once 'includes/config.php';

echo "Debugging accounting portal query...\n\n";

// Test the exact query from enrollment_payments.php
$stmt = $pdo->prepare("
    SELECT ea.*, 
    (SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE id = ea.registrar_reviewed_by) as registrar_name 
    FROM enrollment_applications ea
    WHERE ea.status IN ('accounting_review', 'admin_approval') 
    ORDER BY ea.accounting_reviewed_at ASC, ea.submitted_at ASC
");
$stmt->execute();
$applications = $stmt->fetchAll();

echo "Query returned " . count($applications) . " applications\n\n";

if (!empty($applications)) {
    foreach ($applications as $app) {
        echo "- {$app['application_number']}: {$app['first_name']} {$app['last_name']} ({$app['status']})\n";
    }
} else {
    echo "No applications found. Let's check what statuses exist:\n";
    
    $stmt = $pdo->query("SELECT DISTINCT status, COUNT(*) as count FROM enrollment_applications GROUP BY status");
    $statuses = $stmt->fetchAll();
    
    foreach ($statuses as $status) {
        echo "- Status '{$status['status']}': {$status['count']} applications\n";
    }
    
    echo "\nApplications with 'accounting_review' status:\n";
    $stmt = $pdo->query("SELECT application_number, first_name, last_name, status FROM enrollment_applications WHERE status = 'accounting_review'");
    $apps = $stmt->fetchAll();
    
    foreach ($apps as $app) {
        echo "- {$app['application_number']}: {$app['first_name']} {$app['last_name']}\n";
    }
}

// Force update the applications we just created
echo "\nForcing status update...\n";
$updated = $pdo->exec("UPDATE enrollment_applications SET status = 'accounting_review' WHERE application_number LIKE 'ACC-2025-%'");
echo "Updated $updated applications\n";

// Test query again
$stmt->execute();
$applications = $stmt->fetchAll();
echo "\nAfter update, query returned " . count($applications) . " applications\n";

foreach ($applications as $app) {
    echo "- {$app['application_number']}: {$app['first_name']} {$app['last_name']} ({$app['status']})\n";
}
?>
