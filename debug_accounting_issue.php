<?php
require_once 'includes/config.php';

echo "=== ENROLLMENT APPLICATIONS TABLE STRUCTURE ===\n";
$stmt = $pdo->query('DESCRIBE enrollment_applications');
while ($row = $stmt->fetch()) {
    echo $row['Field'] . ' - ' . $row['Type'] . ' - ' . $row['Null'] . ' - ' . $row['Default'] . "\n";
}

echo "\n=== CURRENT ENROLLMENT APPLICATIONS DATA ===\n";
$stmt = $pdo->query('SELECT id, application_number, first_name, last_name, status, registrar_reviewed_at, registrar_reviewed_by FROM enrollment_applications ORDER BY id DESC LIMIT 10');
while ($row = $stmt->fetch()) {
    echo 'ID: ' . $row['id'] . ' | App#: ' . $row['application_number'] . ' | Name: ' . $row['first_name'] . ' ' . $row['last_name'] . ' | Status: ' . $row['status'] . ' | Reg Review: ' . $row['registrar_reviewed_at'] . "\n";
}

echo "\n=== APPLICATIONS BY STATUS ===\n";
$stmt = $pdo->query('SELECT status, COUNT(*) as count FROM enrollment_applications GROUP BY status');
while ($row = $stmt->fetch()) {
    echo $row['status'] . ': ' . $row['count'] . "\n";
}

echo "\n=== ACCOUNTING QUERY TEST ===\n";
$stmt = $pdo->prepare("
    SELECT ea.*, 
    (SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE id = ea.registrar_reviewed_by) as registrar_name 
    FROM enrollment_applications ea
    WHERE ea.status IN ('accounting_review', 'admin_approval') 
    ORDER BY ea.accounting_reviewed_at ASC, ea.submitted_at ASC
");
$stmt->execute();
$applications = $stmt->fetchAll();

echo "Found " . count($applications) . " applications for accounting review\n";
foreach ($applications as $app) {
    echo 'ID: ' . $app['id'] . ' | Name: ' . $app['first_name'] . ' ' . $app['last_name'] . ' | Status: ' . $app['status'] . "\n";
}
?>
