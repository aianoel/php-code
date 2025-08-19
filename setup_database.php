<?php
// Simple database setup script
try {
    // Connect to MySQL without specifying database
    $pdo = new PDO("mysql:host=localhost", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS school_enrollment");
    $pdo->exec("USE school_enrollment");
    
    // Read and execute schema
    $schema = file_get_contents(__DIR__ . '/database/schema.sql');
    $statements = explode(';', $schema);
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement) && !preg_match('/^(CREATE DATABASE|USE)/', $statement)) {
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                // Skip if table already exists
                if (strpos($e->getMessage(), 'already exists') === false) {
                    echo "Warning: " . $e->getMessage() . "\n";
                }
            }
        }
    }
    
    // Insert demo admin user if not exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password, first_name, last_name, role) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            'admin',
            'admin@school.com',
            password_hash('admin123', PASSWORD_DEFAULT),
            'System',
            'Administrator',
            'admin'
        ]);
        echo "Demo admin user created: admin/admin123\n";
    }
    
    echo "Database setup completed successfully!\n";
    
} catch (PDOException $e) {
    echo "Database setup failed: " . $e->getMessage() . "\n";
}
?>
