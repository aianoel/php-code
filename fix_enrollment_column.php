<?php
require_once 'includes/config.php';

try {
    // Check if application_number column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM enrollment_applications LIKE 'application_number'");
    $columnExists = $stmt->fetch();
    
    if (!$columnExists) {
        // Add the missing application_number column
        $pdo->exec("ALTER TABLE enrollment_applications ADD COLUMN application_number VARCHAR(20) UNIQUE AFTER application_id");
        echo "✓ Added application_number column to enrollment_applications table\n";
        
        // Update existing records with application numbers
        $stmt = $pdo->query("SELECT id FROM enrollment_applications WHERE application_number IS NULL");
        $applications = $stmt->fetchAll();
        
        foreach ($applications as $app) {
            $applicationNumber = 'APP-' . date('Y') . '-' . str_pad($app['id'], 6, '0', STR_PAD_LEFT);
            $updateStmt = $pdo->prepare("UPDATE enrollment_applications SET application_number = ? WHERE id = ?");
            $updateStmt->execute([$applicationNumber, $app['id']]);
        }
        
        echo "✓ Updated " . count($applications) . " existing applications with application numbers\n";
    } else {
        echo "✓ application_number column already exists\n";
    }
    
    echo "✓ Enrollment table fix completed successfully!\n";
    
} catch (PDOException $e) {
    echo "✗ Error fixing enrollment table: " . $e->getMessage() . "\n";
}
?>
