<?php
// Database setup
require_once '../includes/config.php';

try {
    // Create a sample teacher user if not exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'teacher1'");
    $stmt->execute();
    $teacher = $stmt->fetch();
    
    if (!$teacher) {
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, first_name, last_name, role, status) 
                              VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute(['teacher1', 'teacher1@edumanage.school', 
                       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 
                       'John', 'Smith', 'teacher', 'active']);
        $teacherUserId = $pdo->lastInsertId();
    } else {
        $teacherUserId = $teacher['id'];
    }
    
    // Create teacher record if not exists
    $stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
    $stmt->execute([$teacherUserId]);
    $teacherRecord = $stmt->fetch();
    
    if (!$teacherRecord) {
        $stmt = $pdo->prepare("INSERT INTO teachers (user_id, employee_id, department, specialization, hire_date, status)
                              VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$teacherUserId, 'T' . str_pad($teacherUserId, 4, '0', STR_PAD_LEFT), 
                       'Science', 'Physics', '2023-01-15', 'active']);
    }
    
    // Create sample subjects if not exist
    $subjects = [
        ['PHYS101', 'Physics 101', 'Introduction to Physics', 'grade11'],
        ['CHEM101', 'Chemistry 101', 'Introduction to Chemistry', 'grade11'],
        ['MATH101', 'Mathematics 101', 'Advanced Mathematics', 'grade11']
    ];
    
    $subjectIds = [];
    
    foreach ($subjects as $subject) {
        $stmt = $pdo->prepare("SELECT id FROM subjects WHERE code = ?");
        $stmt->execute([$subject[0]]);
        $existingSubject = $stmt->fetch();
        
        if (!$existingSubject) {
            $stmt = $pdo->prepare("INSERT INTO subjects (code, name, description, grade_level)
                                  VALUES (?, ?, ?, ?)");
            $stmt->execute($subject);
            $subjectIds[] = $pdo->lastInsertId();
        } else {
            $subjectIds[] = $existingSubject['id'];
        }
    }
    
    // Create sample classes assigned to the teacher
    $classes = [
        [$subjectIds[0], $teacherUserId, 'Physics 101 - A', 'Section A', 'grade11', '2024-2025', 'MWF', '08:00:00', '09:30:00', 'Room 101'],
        [$subjectIds[1], $teacherUserId, 'Chemistry 101 - B', 'Section B', 'grade11', '2024-2025', 'TTh', '10:00:00', '11:30:00', 'Lab 201'],
        [$subjectIds[2], $teacherUserId, 'Mathematics 101 - C', 'Section C', 'grade11', '2024-2025', 'MWF', '13:00:00', '14:30:00', 'Room 305']
    ];
    
    $classIds = [];
    
    foreach ($classes as $class) {
        $stmt = $pdo->prepare("SELECT id FROM classes WHERE subject_id = ? AND teacher_id = ? AND section = ?");
        $stmt->execute([$class[0], $class[1], $class[3]]);
        $existingClass = $stmt->fetch();
        
        if (!$existingClass) {
            $stmt = $pdo->prepare("INSERT INTO classes (subject_id, teacher_id, name, section, grade_level, school_year, schedule_days, schedule_time_start, schedule_time_end, room)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute($class);
            $classIds[] = $pdo->lastInsertId();
        } else {
            $classIds[] = $existingClass['id'];
        }
    }
    
    // Create sample students
    $students = [
        ['student1', 'student1@edumanage.school', 'Alice', 'Johnson', '2024001', 'grade11', 'Section A'],
        ['student2', 'student2@edumanage.school', 'Bob', 'Williams', '2024002', 'grade11', 'Section B'],
        ['student3', 'student3@edumanage.school', 'Carol', 'Davis', '2024003', 'grade11', 'Section C']
    ];
    
    $studentIds = [];
    
    foreach ($students as $student) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$student[0]]);
        $existingUser = $stmt->fetch();
        
        if (!$existingUser) {
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, first_name, last_name, role, status)
                                  VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$student[0], $student[1], 
                           '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 
                           $student[2], $student[3], 'student', 'active']);
            $studentUserId = $pdo->lastInsertId();
        } else {
            $studentUserId = $existingUser['id'];
        }
        
        $stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
        $stmt->execute([$studentUserId]);
        $existingStudent = $stmt->fetch();
        
        if (!$existingStudent) {
            $stmt = $pdo->prepare("INSERT INTO students (user_id, student_id, grade_level, section)
                                  VALUES (?, ?, ?, ?)");
            $stmt->execute([$studentUserId, $student[4], $student[5], $student[6]]);
            $studentIds[] = $pdo->lastInsertId();
        } else {
            $studentIds[] = $existingStudent['id'];
        }
    }
    
    // Enroll students in classes
    $enrollments = [
        [$studentIds[0], $classIds[0]],
        [$studentIds[0], $classIds[1]],
        [$studentIds[1], $classIds[0]],
        [$studentIds[1], $classIds[2]],
        [$studentIds[2], $classIds[1]],
        [$studentIds[2], $classIds[2]]
    ];
    
    foreach ($enrollments as $enrollment) {
        $stmt = $pdo->prepare("SELECT id FROM class_enrollments WHERE student_id = ? AND class_id = ?");
        $stmt->execute($enrollment);
        $existingEnrollment = $stmt->fetch();
        
        if (!$existingEnrollment) {
            $stmt = $pdo->prepare("INSERT INTO class_enrollments (student_id, class_id, status)
                                  VALUES (?, ?, ?)");
            $stmt->execute([$enrollment[0], $enrollment[1], 'active']);
        }
    }
    
    // Create sample assignments
    $assignments = [
        [$classIds[0], 'Physics Homework 1', 'Complete problems 1-10 in Chapter 1', date('Y-m-d', strtotime('+7 days')), 100],
        [$classIds[0], 'Physics Lab Report', 'Write a report on the pendulum experiment', date('Y-m-d', strtotime('+14 days')), 100],
        [$classIds[1], 'Chemistry Quiz', 'Periodic table elements quiz', date('Y-m-d', strtotime('+5 days')), 50],
        [$classIds[2], 'Math Problem Set', 'Complete the calculus problem set', date('Y-m-d', strtotime('+10 days')), 100]
    ];
    
    foreach ($assignments as $assignment) {
        $stmt = $pdo->prepare("SELECT id FROM assignments WHERE class_id = ? AND title = ?");
        $stmt->execute([$assignment[0], $assignment[1]]);
        $existingAssignment = $stmt->fetch();
        
        if (!$existingAssignment) {
            $stmt = $pdo->prepare("INSERT INTO assignments (class_id, title, description, due_date, max_score)
                                  VALUES (?, ?, ?, ?, ?)");
            $stmt->execute($assignment);
        }
    }
    
    echo "Sample data created successfully!";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
