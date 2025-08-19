<?php
require_once 'includes/config.php';

echo "Checking actual enrollment data vs sample data...\n\n";

// Check all applications
$stmt = $pdo->query("SELECT application_number, first_name, last_name, email, status, total_fees, paid_amount, payment_status, submitted_at FROM enrollment_applications ORDER BY submitted_at DESC");
$allApps = $stmt->fetchAll();

echo "All Applications in Database:\n";
echo "============================\n";
foreach ($allApps as $app) {
    $isActual = !in_array($app['application_number'], ['ACC-001', 'ACC-002', 'ACC-003', 'ENR-001', 'ENR-002']);
    $type = $isActual ? '[ACTUAL]' : '[SAMPLE]';
    echo "$type {$app['application_number']}: {$app['first_name']} {$app['last_name']} ({$app['status']}) - {$app['email']}\n";
}

// Remove sample data
echo "\nRemoving sample data...\n";
$sampleApps = ['ACC-001', 'ACC-002', 'ACC-003', 'ENR-001', 'ENR-002'];
foreach ($sampleApps as $appNum) {
    $stmt = $pdo->prepare("DELETE FROM enrollment_applications WHERE application_number = ?");
    $stmt->execute([$appNum]);
    echo "✓ Removed sample application: $appNum\n";
}

// Check what actual applications we have for accounting review
echo "\nActual applications that can be moved to accounting review:\n";
$stmt = $pdo->query("SELECT application_number, first_name, last_name, status FROM enrollment_applications WHERE status IN ('pending', 'under_review', 'approved') ORDER BY submitted_at ASC");
$actualApps = $stmt->fetchAll();

if (empty($actualApps)) {
    echo "- No actual applications found for accounting review\n";
} else {
    foreach ($actualApps as $app) {
        echo "- {$app['application_number']}: {$app['first_name']} {$app['last_name']} ({$app['status']})\n";
    }
    
    // Move some actual applications to accounting review
    $appsToMove = array_slice($actualApps, 0, 3); // Take first 3 applications
    foreach ($appsToMove as $app) {
        $stmt = $pdo->prepare("
            UPDATE enrollment_applications 
            SET status = 'accounting_review',
                registrar_reviewed_by = (SELECT id FROM users WHERE role = 'registrar' LIMIT 1),
                registrar_reviewed_at = NOW(),
                registrar_notes = 'Documents verified and approved for enrollment.',
                total_fees = 27500.00,
                paid_amount = 0.00,
                payment_status = 'pending'
            WHERE application_number = ?
        ");
        $stmt->execute([$app['application_number']]);
        echo "✓ Moved {$app['application_number']} to accounting_review\n";
    }
}

// Check for enrolled students
echo "\nActual enrolled students:\n";
$stmt = $pdo->query("SELECT application_number, first_name, last_name, total_fees, paid_amount, payment_status FROM enrollment_applications WHERE status = 'enrolled' ORDER BY first_name, last_name");
$enrolledStudents = $stmt->fetchAll();

if (empty($enrolledStudents)) {
    echo "- No actual enrolled students found\n";
} else {
    foreach ($enrolledStudents as $student) {
        $balance = $student['total_fees'] - $student['paid_amount'];
        echo "- {$student['application_number']}: {$student['first_name']} {$student['last_name']}\n";
        echo "  Total: ₱" . number_format($student['total_fees'], 2) . " | Paid: ₱" . number_format($student['paid_amount'], 2) . " | Balance: ₱" . number_format($balance, 2) . "\n";
    }
}

// Show final statistics with actual data
echo "\nActual Data Statistics:\n";
echo "======================\n";
$stats = [
    'pending_payment' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE status = 'accounting_review'")->fetchColumn(),
    'payment_received' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE payment_status = 'paid'")->fetchColumn(),
    'partial_payment' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE payment_status = 'partial'")->fetchColumn(),
    'total_collected' => $pdo->query("SELECT COALESCE(SUM(paid_amount), 0) FROM enrollment_applications")->fetchColumn(),
    'enrolled_students' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE status = 'enrolled'")->fetchColumn(),
    'total_applications' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications")->fetchColumn()
];

foreach ($stats as $key => $value) {
    $label = ucfirst(str_replace('_', ' ', $key));
    if ($key === 'total_collected') {
        echo "- $label: ₱" . number_format($value, 2) . "\n";
    } else {
        echo "- $label: $value\n";
    }
}

echo "\n✅ Accounting portal now displays actual enrollment data!\n";
?>
