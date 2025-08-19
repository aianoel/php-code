<?php
/**
 * Hostinger Deployment Helper Script
 * 
 * This script helps with deploying the SchoolEnroll-1 system to Hostinger.
 * It checks database connectivity and provides deployment instructions.
 */

// Test database connection with Hostinger credentials
$host = 'localhost';
$db_name = 'u870495195_admission';
$username = 'u870495195_admission';
$password = '8uJs293cjJB';
$charset = 'utf8mb4';

echo "=== SchoolEnroll-1 Hostinger Deployment Helper ===\n\n";
echo "Testing database connection...\n";

try {
    $dsn = "mysql:host=$host;dbname=$db_name;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $pdo = new PDO($dsn, $username, $password, $options);
    echo "SUCCESS: Database connection established!\n";
    
    // Check if essential tables exist
    $tables = ['users', 'students', 'enrollment_applications', 'classes'];
    $missing_tables = [];
    
    foreach ($tables as $table) {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if ($stmt->rowCount() == 0) {
            $missing_tables[] = $table;
        }
    }
    
    if (empty($missing_tables)) {
        echo "SUCCESS: All essential tables found in the database.\n";
    } else {
        echo "WARNING: The following tables are missing: " . implode(", ", $missing_tables) . "\n";
        echo "You may need to import the database schema.\n";
    }
    
} catch (PDOException $e) {
    echo "ERROR: Database connection failed: " . $e->getMessage() . "\n";
    echo "Please verify your Hostinger database credentials.\n";
}

echo "\n=== Deployment Instructions ===\n\n";
echo "1. Upload all files to your Hostinger hosting account using FTP or the File Manager.\n";
echo "2. Rename 'includes/config.hostinger.php' to 'includes/config.php' on the server.\n";
echo "3. Import the database schema from 'database/schema.sql' if tables are missing.\n";
echo "4. Set proper permissions (755 for directories, 644 for files).\n";
echo "5. Access your website at your Hostinger domain.\n";
echo "\nNote: Make sure to update any hardcoded URLs in the application to match your Hostinger domain.\n";
?>
