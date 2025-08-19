<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Test SMS functionality
$dataManager = new DataManager($pdo);

// Test data
$testPhoneNumber = '09123456789'; // Replace with a test phone number
$testMessage = "Test SMS from EduManage System\n\nApplication ID: APP-2025-000001\nUsername: john123\nPassword: Test123\n\nThis is a test message.";

echo "<h2>SMS Test</h2>";
echo "<p>Testing SMS functionality...</p>";

$result = $dataManager->sendSMS($testPhoneNumber, $testMessage);

if ($result) {
    echo "<div style='color: green; padding: 10px; border: 1px solid green; background: #f0fff0;'>";
    echo "<strong>✓ SMS Test PASSED</strong><br>";
    echo "SMS sent successfully to: " . htmlspecialchars($testPhoneNumber) . "<br>";
    echo "Response: " . htmlspecialchars(json_encode($result));
    echo "</div>";
} else {
    echo "<div style='color: red; padding: 10px; border: 1px solid red; background: #fff0f0;'>";
    echo "<strong>✗ SMS Test FAILED</strong><br>";
    echo "Failed to send SMS to: " . htmlspecialchars($testPhoneNumber);
    echo "</div>";
}

echo "<br><h3>Test Credential Generation</h3>";
$credentials = $dataManager->generateStudentCredentials('John', 'Doe', 123);
echo "<p><strong>Generated Credentials:</strong></p>";
echo "<ul>";
echo "<li>Username: " . htmlspecialchars($credentials['username']) . "</li>";
echo "<li>Password: " . htmlspecialchars($credentials['password']) . "</li>";
echo "</ul>";

echo "<br><p><em>Note: Change the test phone number in this script before running.</em></p>";
?>
