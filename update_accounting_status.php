<?php
require_once 'includes/config.php';

// Direct SQL update to fix the status issue
$pdo->exec("UPDATE enrollment_applications SET status = 'accounting_review' WHERE application_number LIKE 'ACC-2025-%'");

// Verify the update
$stmt = $pdo->query("SELECT application_number, first_name, last_name, status FROM enrollment_applications WHERE application_number LIKE 'ACC-2025-%'");
$apps = $stmt->fetchAll();

echo "Updated applications:\n";
foreach ($apps as $app) {
    echo "- {$app['application_number']}: {$app['first_name']} {$app['last_name']} ({$app['status']})\n";
}

// Test accounting query
$stmt = $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE status = 'accounting_review'");
echo "\nApplications in accounting_review status: " . $stmt->fetchColumn() . "\n";

echo "✅ Status updated successfully!\n";
?>
