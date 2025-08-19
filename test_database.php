<?php
// Test database connection and table structure
require_once 'includes/config.php';

echo "<h2>Database Connection Test</h2>";

try {
    // Test basic connection
    echo "✅ Database connection successful<br>";
    
    // Check if tables exist
    $tables = ['users', 'students', 'teachers', 'enrollments', 'activity_logs'];
    
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            if ($stmt->fetch()) {
                echo "✅ Table '$table' exists<br>";
                
                // Show table structure for users table
                if ($table === 'users') {
                    echo "<h3>Users table structure:</h3>";
                    $stmt = $pdo->prepare("DESCRIBE users");
                    $stmt->execute();
                    $columns = $stmt->fetchAll();
                    echo "<ul>";
                    foreach ($columns as $column) {
                        echo "<li>{$column['Field']} - {$column['Type']}</li>";
                    }
                    echo "</ul>";
                }
            } else {
                echo "❌ Table '$table' does not exist<br>";
            }
        } catch (PDOException $e) {
            echo "❌ Error checking table '$table': " . $e->getMessage() . "<br>";
        }
    }
    
    // Test user count
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users");
        $stmt->execute();
        $count = $stmt->fetch()['count'];
        echo "<br>📊 Total users in database: $count<br>";
    } catch (PDOException $e) {
        echo "❌ Error counting users: " . $e->getMessage() . "<br>";
    }
    
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "<br>";
    echo "<br><strong>To fix this:</strong><br>";
    echo "1. Make sure XAMPP MySQL is running<br>";
    echo "2. Run the database setup script: <a href='setup_database.php'>setup_database.php</a><br>";
}
?>
