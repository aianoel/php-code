<?php
/**
 * Setup Activity Logs Table
 * 
 * This script creates the activity_logs table in the database if it doesn't exist.
 * This table is used for tracking sensitive admin actions like password viewing and resetting.
 */

// Include configuration
require_once 'includes/config.php';

try {
    // Read the SQL file
    $sql = file_get_contents('database/create_activity_logs.sql');
    
    // Execute the SQL commands
    $pdo->exec($sql);
    
    echo "Activity logs table created successfully!";
} catch (PDOException $e) {
    echo "Error creating activity logs table: " . $e->getMessage();
}
?>
