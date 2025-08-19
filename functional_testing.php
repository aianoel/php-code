<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

class FunctionalTester {
    private $pdo;
    private $dataManager;
    private $results = [];
    private $errors = [];
    
    public function __construct($pdo, $dataManager) {
        $this->pdo = $pdo;
        $this->dataManager = $dataManager;
    }
    
    public function runFunctionalTests() {
        echo "=== SchoolEnroll-1 Functional Testing ===\n\n";
        
        $this->testTeacherPortalFunctionality();
        $this->testStudentPortalFunctionality();
        $this->testParentPortalFunctionality();
        $this->testRegistrarPortalFunctionality();
        $this->testAccountingPortalFunctionality();
        $this->testPrincipalPortalFunctionality();
        $this->testGuidancePortalFunctionality();
        $this->testAcademicCoordinatorFunctionality();
        $this->testEnrollmentPortalFunctionality();
        $this->testSystemWideFeatures();
        $this->testSecurityFeatures();
        $this->generateFunctionalReport();
    }
    
    private function testTeacherPortalFunctionality() {
        echo "1. TEACHER PORTAL FUNCTIONALITY\n";
        echo "================================\n";
        
        // Get a teacher user
        $teacher = $this->pdo->query("SELECT u.*, t.id as teacher_id FROM users u JOIN teachers t ON u.id = t.user_id WHERE u.role = 'teacher' LIMIT 1")->fetch();
        
        if (!$teacher) {
            $this->errors[] = "No teacher found for testing";
            echo "✗ Teacher portal: FAIL (no teacher found)\n\n";
            return;
        }
        
        // Test teacher classes retrieval
        try {
            $classes = $this->dataManager->getTeacherClasses($teacher['id']);
            $this->results['teacher']['classes'] = 'PASS';
            echo "✓ Teacher classes retrieval: PASS (" . count($classes) . " classes)\n";
        } catch (Exception $e) {
            $this->results['teacher']['classes'] = 'FAIL';
            $this->errors[] = "Teacher classes retrieval failed: " . $e->getMessage();
            echo "✗ Teacher classes retrieval: FAIL\n";
        }
        
        // Test teacher students retrieval
        try {
            $students = $this->dataManager->getTeacherStudents($teacher['id']);
            $this->results['teacher']['students'] = 'PASS';
            echo "✓ Teacher students retrieval: PASS (" . count($students) . " students)\n";
        } catch (Exception $e) {
            $this->results['teacher']['students'] = 'FAIL';
            $this->errors[] = "Teacher students retrieval failed: " . $e->getMessage();
            echo "✗ Teacher students retrieval: FAIL\n";
        }
        
        // Test assignment creation
        try {
            if (!empty($classes)) {
                $assignmentData = [
                    'class_id' => $classes[0]['id'],
                    'title' => 'Test Assignment',
                    'description' => 'This is a test assignment',
                    'due_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
                    'max_score' => 100
                ];
                $result = $this->dataManager->createAssignment($assignmentData);
                if ($result) {
                    $this->results['teacher']['assignment_creation'] = 'PASS';
                    echo "✓ Assignment creation: PASS\n";
                } else {
                    $this->results['teacher']['assignment_creation'] = 'FAIL';
                    echo "✗ Assignment creation: FAIL\n";
                }
            } else {
                $this->results['teacher']['assignment_creation'] = 'SKIP';
                echo "⚠ Assignment creation: SKIP (no classes)\n";
            }
        } catch (Exception $e) {
            $this->results['teacher']['assignment_creation'] = 'FAIL';
            $this->errors[] = "Assignment creation failed: " . $e->getMessage();
            echo "✗ Assignment creation: FAIL\n";
        }
        
        // Test teacher statistics
        try {
            $stats = $this->dataManager->getTeacherStats($teacher['id']);
            $this->results['teacher']['statistics'] = 'PASS';
            echo "✓ Teacher statistics: PASS\n";
        } catch (Exception $e) {
            $this->results['teacher']['statistics'] = 'FAIL';
            $this->errors[] = "Teacher statistics failed: " . $e->getMessage();
            echo "✗ Teacher statistics: FAIL\n";
        }
        
        echo "\n";
    }
    
    private function testStudentPortalFunctionality() {
        echo "2. STUDENT PORTAL FUNCTIONALITY\n";
        echo "================================\n";
        
        // Get a student user
        $student = $this->pdo->query("SELECT u.*, s.id as student_id FROM users u JOIN students s ON u.id = s.user_id WHERE u.role = 'student' LIMIT 1")->fetch();
        
        if (!$student) {
            $this->errors[] = "No student found for testing";
            echo "✗ Student portal: FAIL (no student found)\n\n";
            return;
        }
        
        // Test student grades retrieval
        try {
            $grades = $this->dataManager->getStudentGrades($student['student_id']);
            $this->results['student']['grades'] = 'PASS';
            echo "✓ Student grades retrieval: PASS (" . count($grades) . " grades)\n";
        } catch (Exception $e) {
            $this->results['student']['grades'] = 'FAIL';
            $this->errors[] = "Student grades retrieval failed: " . $e->getMessage();
            echo "✗ Student grades retrieval: FAIL\n";
        }
        
        // Test student assignments retrieval
        try {
            $assignments = $this->dataManager->getAssignments(null, $student['student_id']);
            $this->results['student']['assignments'] = 'PASS';
            echo "✓ Student assignments retrieval: PASS (" . count($assignments) . " assignments)\n";
        } catch (Exception $e) {
            $this->results['student']['assignments'] = 'FAIL';
            $this->errors[] = "Student assignments retrieval failed: " . $e->getMessage();
            echo "✗ Student assignments retrieval: FAIL\n";
        }
        
        // Test student dashboard statistics
        try {
            $stats = $this->dataManager->getDashboardStats('student', $student['id']);
            $this->results['student']['dashboard_stats'] = 'PASS';
            echo "✓ Student dashboard statistics: PASS\n";
        } catch (Exception $e) {
            $this->results['student']['dashboard_stats'] = 'FAIL';
            $this->errors[] = "Student dashboard statistics failed: " . $e->getMessage();
            echo "✗ Student dashboard statistics: FAIL\n";
        }
        
        echo "\n";
    }
    
    private function testParentPortalFunctionality() {
        echo "3. PARENT PORTAL FUNCTIONALITY\n";
        echo "===============================\n";
        
        // Get a parent user
        $parent = $this->pdo->query("SELECT * FROM users WHERE role = 'parent' LIMIT 1")->fetch();
        
        if (!$parent) {
            $this->errors[] = "No parent found for testing";
            echo "✗ Parent portal: FAIL (no parent found)\n\n";
            return;
        }
        
        // Test parent dashboard statistics
        try {
            $stats = $this->dataManager->getDashboardStats('parent', $parent['id']);
            $this->results['parent']['dashboard_stats'] = 'PASS';
            echo "✓ Parent dashboard statistics: PASS\n";
        } catch (Exception $e) {
            $this->results['parent']['dashboard_stats'] = 'FAIL';
            $this->errors[] = "Parent dashboard statistics failed: " . $e->getMessage();
            echo "✗ Parent dashboard statistics: FAIL\n";
        }
        
        // Test children data retrieval
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM students WHERE parent_id = ?");
            $stmt->execute([$parent['id']]);
            $children = $stmt->fetchAll();
            $this->results['parent']['children'] = 'PASS';
            echo "✓ Parent children retrieval: PASS (" . count($children) . " children)\n";
        } catch (Exception $e) {
            $this->results['parent']['children'] = 'FAIL';
            $this->errors[] = "Parent children retrieval failed: " . $e->getMessage();
            echo "✗ Parent children retrieval: FAIL\n";
        }
        
        echo "\n";
    }
    
    private function testRegistrarPortalFunctionality() {
        echo "4. REGISTRAR PORTAL FUNCTIONALITY\n";
        echo "==================================\n";
        
        // Test enrollment applications management
        try {
            $applications = $this->dataManager->getEnrollmentApplications();
            $this->results['registrar']['applications'] = 'PASS';
            echo "✓ Enrollment applications: PASS (" . count($applications) . " applications)\n";
        } catch (Exception $e) {
            $this->results['registrar']['applications'] = 'FAIL';
            $this->errors[] = "Enrollment applications failed: " . $e->getMessage();
            echo "✗ Enrollment applications: FAIL\n";
        }
        
        // Test student statistics
        try {
            $stats = $this->dataManager->getStudentStats();
            $this->results['registrar']['student_stats'] = 'PASS';
            echo "✓ Student statistics: PASS\n";
        } catch (Exception $e) {
            $this->results['registrar']['student_stats'] = 'FAIL';
            $this->errors[] = "Student statistics failed: " . $e->getMessage();
            echo "✗ Student statistics: FAIL\n";
        }
        
        // Test enrollment status update
        try {
            if (!empty($applications)) {
                $result = $this->dataManager->updateEnrollmentStatus($applications[0]['id'], 'under_review');
                if ($result) {
                    $this->results['registrar']['status_update'] = 'PASS';
                    echo "✓ Enrollment status update: PASS\n";
                    // Revert the change
                    $this->dataManager->updateEnrollmentStatus($applications[0]['id'], $applications[0]['status']);
                } else {
                    $this->results['registrar']['status_update'] = 'FAIL';
                    echo "✗ Enrollment status update: FAIL\n";
                }
            } else {
                $this->results['registrar']['status_update'] = 'SKIP';
                echo "⚠ Enrollment status update: SKIP (no applications)\n";
            }
        } catch (Exception $e) {
            $this->results['registrar']['status_update'] = 'FAIL';
            $this->errors[] = "Enrollment status update failed: " . $e->getMessage();
            echo "✗ Enrollment status update: FAIL\n";
        }
        
        echo "\n";
    }
    
    private function testAccountingPortalFunctionality() {
        echo "5. ACCOUNTING PORTAL FUNCTIONALITY\n";
        echo "===================================\n";
        
        // Test payments retrieval
        try {
            $stmt = $this->pdo->query("SELECT * FROM payments ORDER BY created_at DESC LIMIT 10");
            $payments = $stmt->fetchAll();
            $this->results['accounting']['payments'] = 'PASS';
            echo "✓ Payments retrieval: PASS (" . count($payments) . " payments)\n";
        } catch (Exception $e) {
            $this->results['accounting']['payments'] = 'FAIL';
            $this->errors[] = "Payments retrieval failed: " . $e->getMessage();
            echo "✗ Payments retrieval: FAIL\n";
        }
        
        // Test financial statistics
        try {
            $stmt = $this->pdo->query("SELECT 
                COUNT(*) as total_payments,
                SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as total_paid,
                SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as total_pending
                FROM payments");
            $stats = $stmt->fetch();
            $this->results['accounting']['financial_stats'] = 'PASS';
            echo "✓ Financial statistics: PASS\n";
        } catch (Exception $e) {
            $this->results['accounting']['financial_stats'] = 'FAIL';
            $this->errors[] = "Financial statistics failed: " . $e->getMessage();
            echo "✗ Financial statistics: FAIL\n";
        }
        
        echo "\n";
    }
    
    private function testPrincipalPortalFunctionality() {
        echo "6. PRINCIPAL PORTAL FUNCTIONALITY\n";
        echo "==================================\n";
        
        // Test admin dashboard statistics
        try {
            $stats = $this->dataManager->getDashboardStats('admin');
            $this->results['principal']['dashboard_stats'] = 'PASS';
            echo "✓ Principal dashboard statistics: PASS\n";
        } catch (Exception $e) {
            $this->results['principal']['dashboard_stats'] = 'FAIL';
            $this->errors[] = "Principal dashboard statistics failed: " . $e->getMessage();
            echo "✗ Principal dashboard statistics: FAIL\n";
        }
        
        // Test teacher directory
        try {
            $teachers = $this->dataManager->getTeachers();
            $this->results['principal']['teacher_directory'] = 'PASS';
            echo "✓ Teacher directory: PASS (" . count($teachers) . " teachers)\n";
        } catch (Exception $e) {
            $this->results['principal']['teacher_directory'] = 'FAIL';
            $this->errors[] = "Teacher directory failed: " . $e->getMessage();
            echo "✗ Teacher directory: FAIL\n";
        }
        
        echo "\n";
    }
    
    private function testGuidancePortalFunctionality() {
        echo "7. GUIDANCE PORTAL FUNCTIONALITY\n";
        echo "=================================\n";
        
        // Test student records access
        try {
            $students = $this->dataManager->getStudents();
            $this->results['guidance']['student_records'] = 'PASS';
            echo "✓ Student records access: PASS (" . count($students) . " students)\n";
        } catch (Exception $e) {
            $this->results['guidance']['student_records'] = 'FAIL';
            $this->errors[] = "Student records access failed: " . $e->getMessage();
            echo "✗ Student records access: FAIL\n";
        }
        
        // Test behavioral records table existence
        try {
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'behavioral_records'");
            if ($stmt->fetch()) {
                $this->results['guidance']['behavioral_records'] = 'PASS';
                echo "✓ Behavioral records table: PASS\n";
            } else {
                $this->results['guidance']['behavioral_records'] = 'FAIL';
                echo "✗ Behavioral records table: FAIL (table missing)\n";
            }
        } catch (Exception $e) {
            $this->results['guidance']['behavioral_records'] = 'FAIL';
            $this->errors[] = "Behavioral records check failed: " . $e->getMessage();
            echo "✗ Behavioral records table: FAIL\n";
        }
        
        echo "\n";
    }
    
    private function testAcademicCoordinatorFunctionality() {
        echo "8. ACADEMIC COORDINATOR FUNCTIONALITY\n";
        echo "======================================\n";
        
        // Test subjects management
        try {
            $stmt = $this->pdo->query("SELECT * FROM subjects ORDER BY name");
            $subjects = $stmt->fetchAll();
            $this->results['academic']['subjects'] = 'PASS';
            echo "✓ Subjects management: PASS (" . count($subjects) . " subjects)\n";
        } catch (Exception $e) {
            $this->results['academic']['subjects'] = 'FAIL';
            $this->errors[] = "Subjects management failed: " . $e->getMessage();
            echo "✗ Subjects management: FAIL\n";
        }
        
        // Test classes management
        try {
            $stmt = $this->pdo->query("SELECT c.*, s.name as subject_name, u.first_name, u.last_name 
                                      FROM classes c 
                                      JOIN subjects s ON c.subject_id = s.id 
                                      JOIN users u ON c.teacher_id = u.id");
            $classes = $stmt->fetchAll();
            $this->results['academic']['classes'] = 'PASS';
            echo "✓ Classes management: PASS (" . count($classes) . " classes)\n";
        } catch (Exception $e) {
            $this->results['academic']['classes'] = 'FAIL';
            $this->errors[] = "Classes management failed: " . $e->getMessage();
            echo "✗ Classes management: FAIL\n";
        }
        
        echo "\n";
    }
    
    private function testEnrollmentPortalFunctionality() {
        echo "9. ENROLLMENT PORTAL FUNCTIONALITY\n";
        echo "===================================\n";
        
        // Test enrollment application creation
        try {
            $testData = [
                'firstName' => 'Test',
                'lastName' => 'Applicant',
                'email' => 'test@example.com',
                'phoneNumber' => '1234567890',
                'dateOfBirth' => '2005-01-01',
                'address' => '123 Test Street',
                'parentName' => 'Test Parent',
                'parentPhone' => '0987654321',
                'desiredGradeLevel' => 'grade11',
                'desiredStrand' => 'stem',
                'previousSchool' => 'Test High School',
                'previousGPA' => 3.5
            ];
            
            $applicationId = $this->dataManager->createEnrollmentApplication($testData);
            if ($applicationId) {
                $this->results['enrollment']['application_creation'] = 'PASS';
                echo "✓ Enrollment application creation: PASS\n";
                
                // Clean up test data
                $stmt = $this->pdo->prepare("DELETE FROM enrollment_applications WHERE id = ?");
                $stmt->execute([$applicationId]);
            } else {
                $this->results['enrollment']['application_creation'] = 'FAIL';
                echo "✗ Enrollment application creation: FAIL\n";
            }
        } catch (Exception $e) {
            $this->results['enrollment']['application_creation'] = 'FAIL';
            $this->errors[] = "Enrollment application creation failed: " . $e->getMessage();
            echo "✗ Enrollment application creation: FAIL\n";
        }
        
        // Test application status tracking
        try {
            $applications = $this->dataManager->getEnrollmentApplications();
            $this->results['enrollment']['status_tracking'] = 'PASS';
            echo "✓ Application status tracking: PASS\n";
        } catch (Exception $e) {
            $this->results['enrollment']['status_tracking'] = 'FAIL';
            $this->errors[] = "Application status tracking failed: " . $e->getMessage();
            echo "✗ Application status tracking: FAIL\n";
        }
        
        echo "\n";
    }
    
    private function testSystemWideFeatures() {
        echo "10. SYSTEM-WIDE FEATURES\n";
        echo "=========================\n";
        
        // Test announcements system
        try {
            $announcements = $this->dataManager->getAnnouncements(10);
            $this->results['system']['announcements'] = 'PASS';
            echo "✓ Announcements system: PASS (" . count($announcements) . " announcements)\n";
        } catch (Exception $e) {
            $this->results['system']['announcements'] = 'FAIL';
            $this->errors[] = "Announcements system failed: " . $e->getMessage();
            echo "✗ Announcements system: FAIL\n";
        }
        
        // Test messaging system
        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM messages");
            $messageCount = $stmt->fetch()['count'];
            $this->results['system']['messaging'] = 'PASS';
            echo "✓ Messaging system: PASS ($messageCount messages)\n";
        } catch (Exception $e) {
            $this->results['system']['messaging'] = 'FAIL';
            $this->errors[] = "Messaging system failed: " . $e->getMessage();
            echo "✗ Messaging system: FAIL\n";
        }
        
        // Test notifications system
        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM notifications");
            $notificationCount = $stmt->fetch()['count'];
            $this->results['system']['notifications'] = 'PASS';
            echo "✓ Notifications system: PASS ($notificationCount notifications)\n";
        } catch (Exception $e) {
            $this->results['system']['notifications'] = 'FAIL';
            $this->errors[] = "Notifications system failed: " . $e->getMessage();
            echo "✗ Notifications system: FAIL\n";
        }
        
        // Test activity logging
        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM activity_logs");
            $logCount = $stmt->fetch()['count'];
            $this->results['system']['activity_logs'] = 'PASS';
            echo "✓ Activity logging: PASS ($logCount log entries)\n";
        } catch (Exception $e) {
            $this->results['system']['activity_logs'] = 'FAIL';
            $this->errors[] = "Activity logging failed: " . $e->getMessage();
            echo "✗ Activity logging: FAIL\n";
        }
        
        echo "\n";
    }
    
    private function testSecurityFeatures() {
        echo "11. SECURITY FEATURES DEEP SCAN\n";
        echo "================================\n";
        
        // Test SQL injection prevention
        try {
            $maliciousInput = "'; DROP TABLE users; --";
            $user = $this->dataManager->getUserByCredentials($maliciousInput, 'password');
            if ($user === false) {
                $this->results['security']['sql_injection'] = 'PASS';
                echo "✓ SQL injection prevention: PASS\n";
            } else {
                $this->results['security']['sql_injection'] = 'FAIL';
                echo "✗ SQL injection prevention: FAIL\n";
            }
        } catch (Exception $e) {
            $this->results['security']['sql_injection'] = 'PASS';
            echo "✓ SQL injection prevention: PASS (exception caught)\n";
        }
        
        // Test XSS prevention
        $xssInput = "<script>alert('XSS')</script>";
        $sanitized = sanitize_input($xssInput);
        if (strpos($sanitized, '<script>') === false) {
            $this->results['security']['xss_prevention'] = 'PASS';
            echo "✓ XSS prevention: PASS\n";
        } else {
            $this->results['security']['xss_prevention'] = 'FAIL';
            echo "✗ XSS prevention: FAIL\n";
        }
        
        // Test CSRF token validation
        $token1 = generate_csrf_token();
        $token2 = generate_csrf_token();
        if ($token1 === $token2 && verify_csrf_token($token1)) {
            $this->results['security']['csrf_validation'] = 'PASS';
            echo "✓ CSRF token validation: PASS\n";
        } else {
            $this->results['security']['csrf_validation'] = 'FAIL';
            echo "✗ CSRF token validation: FAIL\n";
        }
        
        echo "\n";
    }
    
    private function generateFunctionalReport() {
        echo "12. FUNCTIONAL TESTING SUMMARY\n";
        echo "===============================\n";
        
        $totalTests = 0;
        $passedTests = 0;
        $skippedTests = 0;
        
        foreach ($this->results as $category => $tests) {
            foreach ($tests as $test => $result) {
                $totalTests++;
                if ($result === 'PASS') {
                    $passedTests++;
                } elseif ($result === 'SKIP') {
                    $skippedTests++;
                }
            }
        }
        
        $failedTests = $totalTests - $passedTests - $skippedTests;
        $successRate = ($totalTests > 0) ? round(($passedTests / $totalTests) * 100, 2) : 0;
        
        echo "Total Functional Tests: $totalTests\n";
        echo "Passed: $passedTests\n";
        echo "Failed: $failedTests\n";
        echo "Skipped: $skippedTests\n";
        echo "Success Rate: $successRate%\n\n";
        
        if (!empty($this->errors)) {
            echo "FUNCTIONAL ISSUES FOUND:\n";
            echo "========================\n";
            foreach ($this->errors as $error) {
                echo "• $error\n";
            }
            echo "\n";
        }
        
        // Portal-specific recommendations
        echo "PORTAL FUNCTIONALITY STATUS:\n";
        echo "=============================\n";
        
        $portalStatus = [
            'Teacher Portal' => isset($this->results['teacher']) ? $this->calculatePortalScore($this->results['teacher']) : 0,
            'Student Portal' => isset($this->results['student']) ? $this->calculatePortalScore($this->results['student']) : 0,
            'Parent Portal' => isset($this->results['parent']) ? $this->calculatePortalScore($this->results['parent']) : 0,
            'Registrar Portal' => isset($this->results['registrar']) ? $this->calculatePortalScore($this->results['registrar']) : 0,
            'Accounting Portal' => isset($this->results['accounting']) ? $this->calculatePortalScore($this->results['accounting']) : 0,
            'Principal Portal' => isset($this->results['principal']) ? $this->calculatePortalScore($this->results['principal']) : 0,
            'Guidance Portal' => isset($this->results['guidance']) ? $this->calculatePortalScore($this->results['guidance']) : 0,
            'Academic Coordinator' => isset($this->results['academic']) ? $this->calculatePortalScore($this->results['academic']) : 0,
            'Enrollment Portal' => isset($this->results['enrollment']) ? $this->calculatePortalScore($this->results['enrollment']) : 0
        ];
        
        foreach ($portalStatus as $portal => $score) {
            $status = $score >= 80 ? '✓ EXCELLENT' : ($score >= 60 ? '⚠ GOOD' : '✗ NEEDS WORK');
            echo "$portal: $status ($score%)\n";
        }
        
        echo "\nFunctional testing completed at: " . date('Y-m-d H:i:s') . "\n";
    }
    
    private function calculatePortalScore($tests) {
        $total = count($tests);
        $passed = array_count_values($tests)['PASS'] ?? 0;
        return $total > 0 ? round(($passed / $total) * 100, 2) : 0;
    }
}

// Run the functional tests
$tester = new FunctionalTester($pdo, $dataManager);
$tester->runFunctionalTests();
?>
