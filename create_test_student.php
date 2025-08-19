<?php
require_once 'includes/config.php';

try {
    echo "<h2>Creating Test Data</h2>";
    
    // Check if students already exist
    $checkStmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'student'");
    $checkStmt->execute();
    $existingStudents = $checkStmt->fetch()['count'];
    
    if ($existingStudents > 0) {
        echo "<p style='color: orange;'>Students already exist: $existingStudents found</p>";
    } else {
        // Create test students
        $hashedPassword = password_hash('student123', PASSWORD_DEFAULT);
        
        // Student 1
        $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password, role, status) VALUES (?, ?, ?, ?, 'student', 'active')");
        $result = $stmt->execute(['John', 'Doe', 'john.doe@student.com', $hashedPassword]);
        
        if ($result) {
            $userId = $pdo->lastInsertId();
            $stmt = $pdo->prepare("INSERT INTO students (user_id, student_id, grade_level, section, enrollment_status) VALUES (?, ?, ?, ?, 'enrolled')");
            $studentId = 'STU' . str_pad($userId, 6, '0', STR_PAD_LEFT);
            $stmt->execute([$userId, $studentId, '7', 'A']);
            echo "<p style='color: green;'>✓ John Doe created (ID: $studentId)</p>";
        }
        
        // Student 2
        $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password, role, status) VALUES (?, ?, ?, ?, 'student', 'active')");
        $result = $stmt->execute(['Jane', 'Smith', 'jane.smith@student.com', $hashedPassword]);
        
        if ($result) {
            $userId = $pdo->lastInsertId();
            $stmt = $pdo->prepare("INSERT INTO students (user_id, student_id, grade_level, section, enrollment_status) VALUES (?, ?, ?, ?, 'enrolled')");
            $studentId = 'STU' . str_pad($userId, 6, '0', STR_PAD_LEFT);
            $stmt->execute([$userId, $studentId, '8', 'B']);
            echo "<p style='color: green;'>✓ Jane Smith created (ID: $studentId)</p>";
        }
        
        // Student 3
        $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password, role, status) VALUES (?, ?, ?, ?, 'student', 'active')");
        $result = $stmt->execute(['Mike', 'Johnson', 'mike.johnson@student.com', $hashedPassword]);
        
        if ($result) {
            $userId = $pdo->lastInsertId();
            $stmt = $pdo->prepare("INSERT INTO students (user_id, student_id, grade_level, section, enrollment_status) VALUES (?, ?, ?, ?, 'enrolled')");
            $studentId = 'STU' . str_pad($userId, 6, '0', STR_PAD_LEFT);
            $stmt->execute([$userId, $studentId, '9', 'A']);
            echo "<p style='color: green;'>✓ Mike Johnson created (ID: $studentId)</p>";
        }
    }
    
    // Check teachers
    $checkStmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'teacher'");
    $checkStmt->execute();
    $existingTeachers = $checkStmt->fetch()['count'];
    
    if ($existingTeachers == 0) {
        echo "<h3>Creating Test Teachers</h3>";
        $hashedPassword = password_hash('teacher123', PASSWORD_DEFAULT);
        
        // Teacher 1
        $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password, role, status) VALUES (?, ?, ?, ?, 'teacher', 'active')");
        $result = $stmt->execute(['Sarah', 'Wilson', 'sarah.wilson@teacher.com', $hashedPassword]);
        
        if ($result) {
            $userId = $pdo->lastInsertId();
            $stmt = $pdo->prepare("INSERT INTO teachers (user_id, employee_id, department, hire_date, status) VALUES (?, ?, ?, CURDATE(), 'active')");
            $employeeId = 'TCH' . str_pad($userId, 6, '0', STR_PAD_LEFT);
            $stmt->execute([$userId, $employeeId, 'Mathematics']);
            echo "<p style='color: green;'>✓ Sarah Wilson (Math Teacher) created</p>";
        }
        
        // Teacher 2
        $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password, role, status) VALUES (?, ?, ?, ?, 'teacher', 'active')");
        $result = $stmt->execute(['Robert', 'Brown', 'robert.brown@teacher.com', $hashedPassword]);
        
        if ($result) {
            $userId = $pdo->lastInsertId();
            $stmt = $pdo->prepare("INSERT INTO teachers (user_id, employee_id, department, hire_date, status) VALUES (?, ?, ?, CURDATE(), 'active')");
            $employeeId = 'TCH' . str_pad($userId, 6, '0', STR_PAD_LEFT);
            $stmt->execute([$userId, $employeeId, 'English']);
            echo "<p style='color: green;'>✓ Robert Brown (English Teacher) created</p>";
        }
    } else {
        echo "<p style='color: orange;'>Teachers already exist: $existingTeachers found</p>";
    }
    
    // Check subjects
    $checkStmt = $pdo->prepare("SELECT COUNT(*) as count FROM subjects");
    $checkStmt->execute();
    $existingSubjects = $checkStmt->fetch()['count'];
    
    if ($existingSubjects == 0) {
        echo "<h3>Creating Test Subjects</h3>";
        
        $subjects = [
            ['MATH7', 'Mathematics 7', 'Basic Mathematics for Grade 7', 3, 3, 'Mathematics', '7', 'Core'],
            ['ENG7', 'English 7', 'English Language Arts for Grade 7', 3, 3, 'English', '7', 'Core'],
            ['SCI7', 'Science 7', 'General Science for Grade 7', 3, 3, 'Science', '7', 'Core']
        ];
        
        foreach ($subjects as $subject) {
            $stmt = $pdo->prepare("INSERT INTO subjects (code, name, description, credits, units, department, grade_level, strand, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')");
            $stmt->execute($subject);
            echo "<p style='color: green;'>✓ {$subject[1]} created</p>";
        }
    } else {
        echo "<p style='color: orange;'>Subjects already exist: $existingSubjects found</p>";
    }
    
    echo "<br><h3>Summary:</h3>";
    
    // Final counts
    $studentCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
    $teacherCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn();
    $subjectCount = $pdo->query("SELECT COUNT(*) FROM subjects")->fetchColumn();
    $classCount = $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
    
    echo "<p>Students: $studentCount</p>";
    echo "<p>Teachers: $teacherCount</p>";
    echo "<p>Subjects: $subjectCount</p>";
    echo "<p>Classes: $classCount</p>";
    
    echo "<br><a href='academic_coordinator/student_assignments.php' style='background: #16a34a; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Student Assignments</a>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
