<?php
require_once 'includes/config.php';

// Quick setup - insert test data directly
try {
    $pdo->beginTransaction();
    
    // Create students
    $hashedPassword = password_hash('student123', PASSWORD_DEFAULT);
    
    $pdo->exec("INSERT IGNORE INTO users (id, first_name, last_name, email, password, role, status) VALUES 
        (100, 'John', 'Doe', 'john.doe@student.com', '$hashedPassword', 'student', 'active'),
        (101, 'Jane', 'Smith', 'jane.smith@student.com', '$hashedPassword', 'student', 'active'),
        (102, 'Mike', 'Johnson', 'mike.johnson@student.com', '$hashedPassword', 'student', 'active')");
    
    $pdo->exec("INSERT IGNORE INTO students (user_id, student_id, grade_level, section, enrollment_status) VALUES 
        (100, 'STU000100', '7', 'A', 'enrolled'),
        (101, 'STU000101', '8', 'B', 'enrolled'),
        (102, 'STU000102', '9', 'A', 'enrolled')");
    
    // Create teachers
    $teacherPassword = password_hash('teacher123', PASSWORD_DEFAULT);
    
    $pdo->exec("INSERT IGNORE INTO users (id, first_name, last_name, email, password, role, status) VALUES 
        (200, 'Sarah', 'Wilson', 'sarah.wilson@teacher.com', '$teacherPassword', 'teacher', 'active'),
        (201, 'Robert', 'Brown', 'robert.brown@teacher.com', '$teacherPassword', 'teacher', 'active')");
    
    $pdo->exec("INSERT IGNORE INTO teachers (user_id, employee_id, department, hire_date, status) VALUES 
        (200, 'TCH000200', 'Mathematics', CURDATE(), 'active'),
        (201, 'TCH000201', 'English', CURDATE(), 'active')");
    
    // Create subjects
    $pdo->exec("INSERT IGNORE INTO subjects (id, code, name, description, credits, units, department, grade_level, strand, status) VALUES 
        (1, 'MATH7', 'Mathematics 7', 'Basic Mathematics for Grade 7', 3, 3, 'Mathematics', '7', 'Core', 'active'),
        (2, 'ENG7', 'English 7', 'English Language Arts for Grade 7', 3, 3, 'English', '7', 'Core', 'active'),
        (3, 'SCI7', 'Science 7', 'General Science for Grade 7', 3, 3, 'Science', '7', 'Core', 'active')");
    
    $pdo->commit();
    
    echo "<h2>✅ Test Data Created Successfully!</h2>";
    echo "<p>Students: 3 created</p>";
    echo "<p>Teachers: 2 created</p>";
    echo "<p>Subjects: 3 created</p>";
    echo "<br><a href='academic_coordinator/student_assignments.php' style='background: #16a34a; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Student Assignments</a>";
    
} catch (Exception $e) {
    $pdo->rollback();
    echo "Error: " . $e->getMessage();
}
?>
