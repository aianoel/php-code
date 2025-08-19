<?php
require_once 'includes/config.php';

echo "<h2>Enrollment Applications Database Check</h2>";

// Check total count
$stmt = $pdo->prepare('SELECT COUNT(*) as count FROM enrollment_applications');
$stmt->execute();
$result = $stmt->fetch();
echo "<p><strong>Total enrollment applications:</strong> " . $result['count'] . "</p>";

// Check sample records
$stmt = $pdo->prepare('SELECT id, first_name, last_name, application_number, status, created_at FROM enrollment_applications ORDER BY created_at DESC LIMIT 10');
$stmt->execute();
$applications = $stmt->fetchAll();

echo "<h3>Recent Applications:</h3>";
if (empty($applications)) {
    echo "<p>No enrollment applications found in database.</p>";
} else {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Application Number</th><th>Status</th><th>Created</th></tr>";
    foreach($applications as $app) {
        echo "<tr>";
        echo "<td>" . $app['id'] . "</td>";
        echo "<td>" . htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) . "</td>";
        echo "<td>" . htmlspecialchars($app['application_number']) . "</td>";
        echo "<td>" . htmlspecialchars($app['status']) . "</td>";
        echo "<td>" . htmlspecialchars($app['created_at']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Check if there are any sample/test records
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM enrollment_applications WHERE first_name IN ('Camila', 'Alexander', 'Juan', 'Sofia', 'Miguel', 'Maria', 'Carlos')");
$stmt->execute();
$sampleCount = $stmt->fetch();
echo "<p><strong>Potential sample records found:</strong> " . $sampleCount['count'] . "</p>";

if ($sampleCount['count'] > 0) {
    echo "<h3>Sample Records to Remove:</h3>";
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, application_number FROM enrollment_applications WHERE first_name IN ('Camila', 'Alexander', 'Juan', 'Sofia', 'Miguel', 'Maria', 'Carlos')");
    $stmt->execute();
    $sampleRecords = $stmt->fetchAll();
    
    echo "<ul>";
    foreach($sampleRecords as $record) {
        echo "<li>" . htmlspecialchars($record['first_name'] . ' ' . $record['last_name']) . " (" . htmlspecialchars($record['application_number']) . ")</li>";
    }
    echo "</ul>";
}
?>
