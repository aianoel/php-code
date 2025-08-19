<?php
require_once 'includes/config.php';

echo "Checking and fixing accounting status...\n\n";

// Check current status
$stmt = $pdo->query("SELECT application_number, first_name, last_name, status FROM enrollment_applications ORDER BY id");
$apps = $stmt->fetchAll();

echo "All applications:\n";
foreach($apps as $app) {
    echo "- {$app['application_number']}: {$app['first_name']} {$app['last_name']} ({$app['status']})\n";
}

// Force update specific applications to accounting_review
$stmt = $pdo->exec("UPDATE enrollment_applications SET status = 'accounting_review' WHERE application_number IN ('APP-2025-000002', 'APP-2025-000008')");
echo "\nForced update result: $stmt rows affected\n";

// Check accounting_review applications
$stmt = $pdo->query("SELECT application_number, first_name, last_name, status FROM enrollment_applications WHERE status = 'accounting_review'");
$accountingApps = $stmt->fetchAll();

echo "\nApplications in accounting_review status:\n";
foreach($accountingApps as $app) {
    echo "- {$app['application_number']}: {$app['first_name']} {$app['last_name']}\n";
}
?>
