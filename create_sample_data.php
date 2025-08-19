<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

echo "Creating comprehensive sample data for testing...\n\n";

try {
    // Create additional students with parent relationships
    echo "1. Creating additional students and parent relationships...\n";
    
    // Create more students
    $students = [
        ['username' => 'john_doe', 'email' => 'john.doe@student.com', 'first_name' => 'John', 'last_name' => 'Doe', 'grade' => 'grade11', 'section' => 'A'],
        ['username' => 'jane_smith', 'email' => 'jane.smith@student.com', 'first_name' => 'Jane', 'last_name' => 'Smith', 'grade' => 'grade11', 'section' => 'B'],
        ['username' => 'mike_johnson', 'email' => 'mike.johnson@student.com', 'first_name' => 'Mike', 'last_name' => 'Johnson', 'grade' => 'grade12', 'section' => 'A'],
        ['username' => 'sarah_wilson', 'email' => 'sarah.wilson@student.com', 'first_name' => 'Sarah', 'last_name' => 'Wilson', 'grade' => 'grade10', 'section' => 'A']
    ];
    
    $studentIds = [];
    foreach ($students as $studentData) {
        // Check if student already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$studentData['username']]);
        if ($stmt->fetch()) continue;
        
        // Create user
        $userData = [
            'username' => $studentData['username'],
            'email' => $studentData['email'],
            'password' => 'password',
            'first_name' => $studentData['first_name'],
            'last_name' => $studentData['last_name'],
            'role' => 'student'
        ];
        
        if ($dataManager->createUser($userData)) {
            $userId = $pdo->lastInsertId();
            $studentIds[] = $userId;
            
            // Create student record
            $stmt = $pdo->prepare("INSERT INTO students (user_id, student_id, grade_level, section) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userId, generate_student_id(), $studentData['grade'], $studentData['section']]);
            
            echo "  Created student: {$studentData['first_name']} {$studentData['last_name']}\n";
        }
    }
    
    // Link students to existing parent
    $parentStmt = $pdo->prepare("SELECT id FROM users WHERE role = 'parent' LIMIT 1");
    $parentStmt->execute();
    $parent = $parentStmt->fetch();
    
    if ($parent && !empty($studentIds)) {
        $stmt = $pdo->prepare("UPDATE students SET parent_id = ? WHERE user_id IN (" . implode(',', array_fill(0, count($studentIds), '?')) . ")");
        $stmt->execute(array_merge([$parent['id']], $studentIds));
        echo "  Linked " . count($studentIds) . " students to parent\n";
    }
    
    // Create enrollments for students in classes
    echo "\n2. Creating student enrollments in classes...\n";
    
    $allStudents = $pdo->query("SELECT id FROM students")->fetchAll();
    $allClasses = $pdo->query("SELECT id FROM classes")->fetchAll();
    
    foreach ($allStudents as $student) {
        // Enroll each student in 3-5 random classes
        $numClasses = rand(3, 5);
        $selectedClasses = array_rand($allClasses, min($numClasses, count($allClasses)));
        if (!is_array($selectedClasses)) $selectedClasses = [$selectedClasses];
        
        foreach ($selectedClasses as $classIndex) {
            $classId = $allClasses[$classIndex]['id'];
            
            // Check if enrollment already exists
            $stmt = $pdo->prepare("SELECT id FROM enrollments WHERE student_id = ? AND class_id = ?");
            $stmt->execute([$student['id'], $classId]);
            if (!$stmt->fetch()) {
                $stmt = $pdo->prepare("INSERT INTO enrollments (student_id, class_id) VALUES (?, ?)");
                $stmt->execute([$student['id'], $classId]);
            }
        }
    }
    echo "  Created enrollments for all students\n";
    
    // Create grades for enrolled students
    echo "\n3. Creating sample grades...\n";
    
    $enrollments = $pdo->query("SELECT * FROM enrollments")->fetchAll();
    $gradeTypes = ['quiz', 'exam', 'assignment', 'project', 'participation'];
    
    foreach ($enrollments as $enrollment) {
        // Create 5-10 grades per enrollment
        $numGrades = rand(5, 10);
        for ($i = 0; $i < $numGrades; $i++) {
            $score = rand(70, 100);
            $maxScore = 100;
            $gradeType = $gradeTypes[array_rand($gradeTypes)];
            
            $stmt = $pdo->prepare("
                INSERT INTO grades (student_id, class_id, grade_type, title, score, max_score, letter_grade) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $letterGrade = $score >= 90 ? 'A' : ($score >= 80 ? 'B' : ($score >= 70 ? 'C' : 'D'));
            $stmt->execute([
                $enrollment['student_id'],
                $enrollment['class_id'],
                $gradeType,
                ucfirst($gradeType) . ' ' . ($i + 1),
                $score,
                $maxScore,
                $letterGrade
            ]);
        }
    }
    echo "  Created sample grades for all enrollments\n";
    
    // Create assignments
    echo "\n4. Creating sample assignments...\n";
    
    foreach ($allClasses as $class) {
        // Create 3-5 assignments per class
        $numAssignments = rand(3, 5);
        for ($i = 0; $i < $numAssignments; $i++) {
            $dueDate = date('Y-m-d H:i:s', strtotime('+' . rand(1, 30) . ' days'));
            
            $stmt = $pdo->prepare("
                INSERT INTO assignments (class_id, title, description, due_date, max_score) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $class['id'],
                'Assignment ' . ($i + 1),
                'This is a sample assignment for testing purposes.',
                $dueDate,
                100
            ]);
        }
    }
    echo "  Created sample assignments for all classes\n";
    
    // Create payments
    echo "\n5. Creating sample payments...\n";
    
    foreach ($allStudents as $student) {
        // Create tuition payment
        $stmt = $pdo->prepare("
            INSERT INTO payments (student_id, payment_type, description, amount, due_date, status) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $student['id'],
            'tuition',
            'Tuition Fee - Semester 1',
            25000.00,
            date('Y-m-d', strtotime('+30 days')),
            rand(0, 1) ? 'paid' : 'pending'
        ]);
        
        // Create miscellaneous fee
        $stmt->execute([
            $student['id'],
            'miscellaneous',
            'Laboratory Fee',
            2500.00,
            date('Y-m-d', strtotime('+15 days')),
            rand(0, 1) ? 'paid' : 'pending'
        ]);
    }
    echo "  Created sample payments for all students\n";
    
    // Create announcements
    echo "\n6. Creating sample announcements...\n";
    
    $adminUser = $pdo->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetch();
    
    $announcements = [
        ['title' => 'Midterm Examinations Schedule', 'content' => 'Midterm examinations will be held from October 15-20, 2024. Please check your class schedules.', 'target' => 'students'],
        ['title' => 'Parent-Teacher Conference', 'content' => 'Parent-teacher conferences are scheduled for November 5, 2024. Please confirm your attendance.', 'target' => 'parents'],
        ['title' => 'Faculty Meeting', 'content' => 'Monthly faculty meeting will be held on October 10, 2024 at 3:00 PM in the conference room.', 'target' => 'teachers'],
        ['title' => 'School Maintenance Notice', 'content' => 'The school will undergo maintenance on October 25, 2024. Classes will be suspended.', 'target' => 'all']
    ];
    
    foreach ($announcements as $announcement) {
        $stmt = $pdo->prepare("
            INSERT INTO announcements (title, content, target_audience, created_by) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $announcement['title'],
            $announcement['content'],
            $announcement['target'],
            $adminUser['id']
        ]);
    }
    echo "  Created sample announcements\n";
    
    // Create behavioral records for guidance portal
    echo "\n7. Creating sample behavioral records...\n";
    
    $guidanceUser = $pdo->query("SELECT id FROM users WHERE role = 'guidance' LIMIT 1")->fetch();
    
    if ($guidanceUser) {
        foreach (array_slice($allStudents, 0, 3) as $student) {
            $stmt = $pdo->prepare("
                INSERT INTO behavioral_records (student_id, incident_date, incident_type, category, description, reported_by) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $student['id'],
                date('Y-m-d', strtotime('-' . rand(1, 30) . ' days')),
                'positive',
                'Academic Achievement',
                'Student showed excellent performance in mathematics competition.',
                $guidanceUser['id']
            ]);
        }
        echo "  Created sample behavioral records\n";
    }
    
    // Create counseling sessions
    echo "\n8. Creating sample counseling sessions...\n";
    
    if ($guidanceUser) {
        foreach (array_slice($allStudents, 0, 2) as $student) {
            $stmt = $pdo->prepare("
                INSERT INTO counseling_sessions (student_id, counselor_id, session_date, topic, notes, status) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $student['id'],
                $guidanceUser['id'],
                date('Y-m-d H:i:s', strtotime('+' . rand(1, 14) . ' days')),
                'Academic Planning',
                'Discussed career goals and academic pathway options.',
                'scheduled'
            ]);
        }
        echo "  Created sample counseling sessions\n";
    }
    
    // Create messages between users
    echo "\n9. Creating sample messages...\n";
    
    $teachers = $pdo->query("SELECT id FROM users WHERE role = 'teacher'")->fetchAll();
    $students = $pdo->query("SELECT id FROM users WHERE role = 'student' LIMIT 3")->fetchAll();
    
    if (!empty($teachers) && !empty($students)) {
        foreach ($students as $student) {
            $teacher = $teachers[array_rand($teachers)];
            
            $stmt = $pdo->prepare("
                INSERT INTO messages (sender_id, recipient_id, subject, content) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $student['id'],
                $teacher['id'],
                'Question about Assignment',
                'Hi, I have a question about the recent assignment. Could you please clarify the requirements?'
            ]);
            
            // Teacher reply
            $stmt->execute([
                $teacher['id'],
                $student['id'],
                'Re: Question about Assignment',
                'Hello! Please refer to the assignment guidelines posted in the class portal. Let me know if you need further clarification.'
            ]);
        }
        echo "  Created sample messages between teachers and students\n";
    }
    
    // Create notifications
    echo "\n10. Creating sample notifications...\n";
    
    foreach (array_slice($allStudents, 0, 3) as $student) {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $student['id'],
            'New Grade Posted',
            'A new grade has been posted for Mathematics. Check your grades page.',
            'info'
        ]);
        
        $stmt->execute([
            $student['id'],
            'Assignment Due Soon',
            'Your Science assignment is due in 2 days. Please submit on time.',
            'warning'
        ]);
    }
    echo "  Created sample notifications\n";
    
    echo "\n✅ Sample data creation completed successfully!\n";
    echo "\nSUMMARY:\n";
    echo "========\n";
    
    // Generate summary statistics
    $stats = [
        'users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        'students' => $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn(),
        'teachers' => $pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn(),
        'classes' => $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn(),
        'enrollments' => $pdo->query("SELECT COUNT(*) FROM enrollments")->fetchColumn(),
        'grades' => $pdo->query("SELECT COUNT(*) FROM grades")->fetchColumn(),
        'assignments' => $pdo->query("SELECT COUNT(*) FROM assignments")->fetchColumn(),
        'payments' => $pdo->query("SELECT COUNT(*) FROM payments")->fetchColumn(),
        'announcements' => $pdo->query("SELECT COUNT(*) FROM announcements")->fetchColumn(),
        'messages' => $pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn(),
        'notifications' => $pdo->query("SELECT COUNT(*) FROM notifications")->fetchColumn(),
        'behavioral_records' => $pdo->query("SELECT COUNT(*) FROM behavioral_records")->fetchColumn(),
        'counseling_sessions' => $pdo->query("SELECT COUNT(*) FROM counseling_sessions")->fetchColumn()
    ];
    
    foreach ($stats as $table => $count) {
        echo "- " . ucfirst(str_replace('_', ' ', $table)) . ": $count records\n";
    }
    
} catch (Exception $e) {
    echo "Error creating sample data: " . $e->getMessage() . "\n";
}
?>
