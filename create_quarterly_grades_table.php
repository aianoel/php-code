<?php
require_once 'includes/config.php';

try {
    // Create quarterly_grades table
    $sql = "CREATE TABLE IF NOT EXISTS quarterly_grades (
        id INT PRIMARY KEY AUTO_INCREMENT,
        student_id INT NOT NULL,
        class_id INT NOT NULL,
        quarter ENUM('1st', '2nd', '3rd', '4th') NOT NULL,
        grade DECIMAL(5,2) NOT NULL,
        remarks TEXT,
        teacher_id INT NOT NULL,
        school_year VARCHAR(20) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
        FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
        UNIQUE KEY unique_quarterly_grade (student_id, class_id, quarter, school_year)
    )";
    
    $pdo->exec($sql);
    echo "✅ Quarterly grades table created successfully!\n";
    
    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'quarterly_grades'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Table 'quarterly_grades' verified to exist.\n";
        
        // Show table structure
        $stmt = $pdo->query("DESCRIBE quarterly_grades");
        echo "\n📋 Table structure:\n";
        while ($row = $stmt->fetch()) {
            echo "- {$row['Field']}: {$row['Type']}\n";
        }
    } else {
        echo "❌ Table 'quarterly_grades' was not created.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error creating quarterly_grades table: " . $e->getMessage() . "\n";
}
?>
