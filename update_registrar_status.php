<?php
require_once 'includes/config.php';

try {
    // Update one application to registrar_review status to demonstrate the buttons
    $stmt = $pdo->prepare("UPDATE enrollment_applications SET status = 'registrar_review' WHERE application_number = 'APP-2025-000001'");
    $stmt->execute();
    
    echo "✓ Updated APP-2025-000001 to 'registrar_review' status\n";
    echo "Now the registrar page will show approve and reject buttons for this application.\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>
