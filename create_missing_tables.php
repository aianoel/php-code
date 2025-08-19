<?php
require_once 'includes/config.php';

try {
    echo "Creating missing database tables...\n";
    
    // Create messages table
    $sql = "CREATE TABLE IF NOT EXISTS messages (
        id INT PRIMARY KEY AUTO_INCREMENT,
        sender_id INT NOT NULL,
        recipient_id INT NOT NULL,
        subject VARCHAR(200) NOT NULL,
        content TEXT NOT NULL,
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        read_at TIMESTAMP NULL,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $pdo->exec($sql);
    echo "✓ Messages table created\n";
    
    // Create class_enrollments table
    $sql = "CREATE TABLE IF NOT EXISTS class_enrollments (
        id INT PRIMARY KEY AUTO_INCREMENT,
        student_id INT NOT NULL,
        class_id INT NOT NULL,
        enrollment_date DATE DEFAULT (CURRENT_DATE),
        status ENUM('active', 'dropped', 'completed') DEFAULT 'active',
        final_grade DECIMAL(5,2),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
        UNIQUE KEY unique_enrollment (student_id, class_id)
    )";
    $pdo->exec($sql);
    echo "✓ Class enrollments table created\n";
    
    // Create grades table
    $sql = "CREATE TABLE IF NOT EXISTS grades (
        id INT PRIMARY KEY AUTO_INCREMENT,
        student_id INT NOT NULL,
        assignment_id INT NOT NULL,
        grade DECIMAL(5,2) NOT NULL,
        feedback TEXT,
        graded_by INT NOT NULL,
        graded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
        FOREIGN KEY (graded_by) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_grade (student_id, assignment_id)
    )";
    $pdo->exec($sql);
    echo "✓ Grades table created\n";
    
    // Verify tables exist
    $stmt = $pdo->query("SHOW TABLES LIKE 'messages'");
    $messagesExists = $stmt->rowCount() > 0;
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'class_enrollments'");
    $enrollmentsExists = $stmt->rowCount() > 0;
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'grades'");
    $gradesExists = $stmt->rowCount() > 0;
    
    echo "\nVerification:\n";
    echo "Messages table: " . ($messagesExists ? "EXISTS" : "MISSING") . "\n";
    echo "Class enrollments table: " . ($enrollmentsExists ? "EXISTS" : "MISSING") . "\n";
    echo "Grades table: " . ($gradesExists ? "EXISTS" : "MISSING") . "\n";
    
    echo "\nMissing tables creation completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
