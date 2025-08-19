<?php
// Update subjects table to add missing columns
require_once 'includes/config.php';

try {
    // Add units column if it doesn't exist
    $pdo->exec("ALTER TABLE subjects ADD COLUMN IF NOT EXISTS units INT DEFAULT 1");
    echo "✓ Added 'units' column to subjects table<br>";
    
    // Add department column if it doesn't exist
    $pdo->exec("ALTER TABLE subjects ADD COLUMN IF NOT EXISTS department VARCHAR(50)");
    echo "✓ Added 'department' column to subjects table<br>";
    
    // Update existing subjects with default values
    $pdo->exec("UPDATE subjects SET units = 1 WHERE units IS NULL");
    $pdo->exec("UPDATE subjects SET department = 'General' WHERE department IS NULL OR department = ''");
    
    echo "✓ Updated existing subjects with default values<br>";
    echo "<br><strong>Database update completed successfully!</strong><br>";
    echo "<a href='admin/academic-setup.php'>Go to Academic Setup</a>";
    
} catch (Exception $e) {
    echo "Error updating database: " . $e->getMessage();
}
?>
