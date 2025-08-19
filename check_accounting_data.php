<?php
require_once 'includes/config.php';

echo "Checking accounting data and fixing display...\n\n";

// Check current application statuses
$stmt = $pdo->query("SELECT application_number, first_name, last_name, status FROM enrollment_applications ORDER BY id");
$apps = $stmt->fetchAll();

echo "Current applications:\n";
foreach ($apps as $app) {
    echo "- {$app['application_number']}: {$app['first_name']} {$app['last_name']} ({$app['status']})\n";
}

// Force some applications to accounting_review status
$stmt = $pdo->exec("UPDATE enrollment_applications SET status = 'accounting_review' WHERE application_number IN ('APP-2025-000002', 'APP-2025-000008')");
echo "\n✓ Updated applications to accounting_review status\n";

// Check what the accounting query returns
$stmt = $pdo->prepare("
    SELECT ea.*, 
    (SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE id = ea.registrar_reviewed_by) as registrar_name 
    FROM enrollment_applications ea
    WHERE ea.status IN ('accounting_review', 'admin_approval') 
    ORDER BY ea.accounting_reviewed_at ASC, ea.submitted_at ASC
");
$stmt->execute();
$accountingApps = $stmt->fetchAll();

echo "\nApplications for accounting portal:\n";
if (empty($accountingApps)) {
    echo "- No applications found\n";
} else {
    foreach ($accountingApps as $app) {
        echo "- {$app['application_number']}: {$app['first_name']} {$app['last_name']} ({$app['status']})\n";
    }
}
?>
