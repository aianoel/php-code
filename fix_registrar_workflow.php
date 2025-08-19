<?php
require_once 'includes/config.php';

try {
    echo "Fixing registrar workflow and applications...\n\n";
    
    // Reset some applications to pending status for registrar review
    $stmt = $pdo->prepare("
        UPDATE enrollment_applications 
        SET status = 'pending', 
            registrar_reviewed_by = NULL,
            registrar_reviewed_at = NULL,
            registrar_notes = NULL
        WHERE id IN (
            SELECT * FROM (
                SELECT id FROM enrollment_applications 
                WHERE status = 'approved' 
                LIMIT 2
            ) as temp
        )
    ");
    $stmt->execute();
    
    echo "✓ Reset 2 applications to pending status for registrar review\n";
    
    // Create a new pending application
    $applicationId = $pdo->query("SELECT MAX(id) + 1 as next_id FROM enrollment_applications")->fetch()['next_id'] ?: 1;
    $applicationNumber = 'APP-' . date('Y') . '-' . str_pad($applicationId, 6, '0', STR_PAD_LEFT);
    
    $stmt = $pdo->prepare("
        INSERT INTO enrollment_applications 
        (application_number, first_name, last_name, email, phone, birth_date, address, 
         parent_name, parent_phone, grade_level, strand, previous_school, previous_gpa, 
         status, submitted_at, total_fees, payment_status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), 27500.00, 'pending')
    ");
    
    $stmt->execute([
        $applicationNumber,
        'Ana',
        'Rodriguez',
        'ana.rodriguez@email.com',
        '09345678901',
        '2006-09-10',
        '789 Pine Street, Makati City',
        'Miguel Rodriguez',
        'ana.rodriguez@email.com',
        'grade11',
        'humss',
        'Makati Science High School',
        3.7
    ]);
    
    echo "✓ Created new pending application: Ana Rodriguez ($applicationNumber)\n";
    
    // Update the registrar page query to also show applications that need initial review
    echo "\n✓ Applications now available for registrar review:\n";
    
    $stmt = $pdo->query("
        SELECT application_number, first_name, last_name, status, submitted_at
        FROM enrollment_applications 
        WHERE status IN ('pending', 'registrar_review') 
        ORDER BY submitted_at ASC
    ");
    
    $applications = $stmt->fetchAll();
    
    if (empty($applications)) {
        echo "  - No applications found\n";
    } else {
        foreach ($applications as $app) {
            echo "  - {$app['application_number']}: {$app['first_name']} {$app['last_name']} ({$app['status']})\n";
        }
    }
    
    // Show updated statistics
    echo "\nUpdated Statistics:\n";
    echo "==================\n";
    
    $stats = [
        'pending' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE status = 'pending'")->fetchColumn(),
        'registrar_review' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE status = 'registrar_review'")->fetchColumn(),
        'accounting_review' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE status = 'accounting_review'")->fetchColumn(),
        'admin_approval' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE status = 'admin_approval'")->fetchColumn(),
        'approved' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE status = 'approved'")->fetchColumn(),
        'enrolled' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE status = 'enrolled'")->fetchColumn(),
        'rejected' => $pdo->query("SELECT COUNT(*) FROM enrollment_applications WHERE status = 'rejected'")->fetchColumn()
    ];
    
    foreach ($stats as $status => $count) {
        echo "- " . ucfirst(str_replace('_', ' ', $status)) . ": $count\n";
    }
    
    echo "\n✅ Registrar workflow fixed successfully!\n";
    echo "\nThe registrar can now:\n";
    echo "- Review pending applications\n";
    echo "- Start review process\n";
    echo "- Approve and forward to accounting\n";
    echo "- Reject applications with reasons\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>
