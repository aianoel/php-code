<?php
require_once 'includes/config.php';

echo "<h2>Removing Sample Enrollment Data</h2>";

// List of sample/test names to remove
$sampleNames = [
    'Camila Santos', 'Alexander Cruz', 'Juan Dela Cruz', 'Sofia Gonzales', 
    'Miguel Santos', 'Maria Santos', 'Carlos Reyes', 'John Doe', 'Jane Smith', 
    'Mike Johnson', 'Sarah Wilson', 'David Brown', 'Emily Davis'
];

$removedCount = 0;

foreach ($sampleNames as $fullName) {
    $nameParts = explode(' ', $fullName, 2);
    $firstName = $nameParts[0];
    $lastName = isset($nameParts[1]) ? $nameParts[1] : '';
    
    // Check if record exists
    $stmt = $pdo->prepare("SELECT id, application_number FROM enrollment_applications WHERE first_name = ? AND last_name = ?");
    $stmt->execute([$firstName, $lastName]);
    $records = $stmt->fetchAll();
    
    if (!empty($records)) {
        foreach ($records as $record) {
            echo "<p>Removing: " . htmlspecialchars($fullName) . " (ID: " . $record['id'] . ", App: " . htmlspecialchars($record['application_number']) . ")</p>";
            
            // Remove the record
            $deleteStmt = $pdo->prepare("DELETE FROM enrollment_applications WHERE id = ?");
            $deleteStmt->execute([$record['id']]);
            $removedCount++;
        }
    }
}

// Also remove any records with application numbers that look like samples
$stmt = $pdo->prepare("SELECT id, first_name, last_name, application_number FROM enrollment_applications WHERE application_number LIKE 'APP-2025-%' AND application_number IN ('APP-2025-000002', 'APP-2025-000001', 'APP-2025-000009', 'APP-2025-000010', 'APP-2025-000011', 'APP-2025-000006', 'APP-2025-000007')");
$stmt->execute();
$sampleApps = $stmt->fetchAll();

foreach ($sampleApps as $app) {
    echo "<p>Removing sample application: " . htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) . " (" . htmlspecialchars($app['application_number']) . ")</p>";
    
    $deleteStmt = $pdo->prepare("DELETE FROM enrollment_applications WHERE id = ?");
    $deleteStmt->execute([$app['id']]);
    $removedCount++;
}

echo "<h3>Summary</h3>";
echo "<p><strong>Total sample records removed:</strong> $removedCount</p>";

// Check remaining records
$stmt = $pdo->prepare('SELECT COUNT(*) as count FROM enrollment_applications');
$stmt->execute();
$result = $stmt->fetch();
echo "<p><strong>Remaining enrollment applications:</strong> " . $result['count'] . "</p>";

if ($result['count'] > 0) {
    echo "<h3>Remaining Applications:</h3>";
    $stmt = $pdo->prepare('SELECT first_name, last_name, application_number, status FROM enrollment_applications ORDER BY created_at DESC');
    $stmt->execute();
    $remaining = $stmt->fetchAll();
    
    echo "<ul>";
    foreach($remaining as $app) {
        echo "<li>" . htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) . " (" . htmlspecialchars($app['application_number']) . ") - " . htmlspecialchars($app['status']) . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p><em>Database is now clean. The registrar dashboard will show 'No applications found' until real students submit enrollment forms.</em></p>";
}

echo "<br><p><a href='registrar/index.php'>← Back to Registrar Dashboard</a></p>";
?>
