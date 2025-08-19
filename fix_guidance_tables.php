<?php
require_once 'includes/config.php';

try {
    // Create behavioral_records table for guidance portal
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS behavioral_records (
            id INT PRIMARY KEY AUTO_INCREMENT,
            student_id INT NOT NULL,
            incident_date DATE NOT NULL,
            incident_type ENUM('positive', 'negative', 'neutral') DEFAULT 'neutral',
            category VARCHAR(50) NOT NULL,
            description TEXT NOT NULL,
            action_taken TEXT,
            severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
            reported_by INT NOT NULL,
            follow_up_required BOOLEAN DEFAULT FALSE,
            follow_up_date DATE,
            status ENUM('open', 'resolved', 'ongoing') DEFAULT 'open',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            FOREIGN KEY (reported_by) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    
    // Create counseling_sessions table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS counseling_sessions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            student_id INT NOT NULL,
            counselor_id INT NOT NULL,
            session_date DATETIME NOT NULL,
            session_type ENUM('individual', 'group', 'family', 'crisis') DEFAULT 'individual',
            topic VARCHAR(200) NOT NULL,
            notes TEXT,
            recommendations TEXT,
            follow_up_required BOOLEAN DEFAULT FALSE,
            follow_up_date DATE,
            status ENUM('scheduled', 'completed', 'cancelled', 'no_show') DEFAULT 'scheduled',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            FOREIGN KEY (counselor_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    
    // Create career_guidance table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS career_guidance (
            id INT PRIMARY KEY AUTO_INCREMENT,
            student_id INT NOT NULL,
            counselor_id INT NOT NULL,
            assessment_date DATE NOT NULL,
            interests TEXT,
            strengths TEXT,
            career_goals TEXT,
            recommended_programs TEXT,
            action_plan TEXT,
            progress_notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            FOREIGN KEY (counselor_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    
    echo "Guidance portal tables created successfully!\n";
    echo "- behavioral_records table\n";
    echo "- counseling_sessions table\n";
    echo "- career_guidance table\n";
    
} catch (PDOException $e) {
    echo "Error creating guidance tables: " . $e->getMessage() . "\n";
}
?>
