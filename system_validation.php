<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

class SystemValidator {
    private $pdo;
    private $dataManager;
    private $results = [];
    private $errors = [];
    
    public function __construct($pdo, $dataManager) {
        $this->pdo = $pdo;
        $this->dataManager = $dataManager;
    }
    
    public function runFullValidation() {
        echo "=== SchoolEnroll-1 System Deep Scan Validation ===\n\n";
        
        $this->testAuthentication();
        $this->testDatabaseIntegrity();
        $this->testUserRoles();
        $this->testPortalAccess();
        $this->testSecurityFeatures();
        $this->testDataOperations();
        $this->generateReport();
    }
    
    private function testAuthentication() {
        echo "1. AUTHENTICATION & SECURITY TESTING\n";
        echo "=====================================\n";
        
        $testUsers = [
            'admin' => 'admin',
            'teacher' => 'teacher', 
            'student' => 'student',
            'parent' => 'parent',
            'registrar' => 'registrar',
            'accounting' => 'accounting',
            'principal' => 'principal',
            'guidance' => 'guidance',
            'academic_coordinator' => 'academic_coordinator'
        ];
        
        foreach ($testUsers as $username => $role) {
            $user = $this->dataManager->getUserByCredentials($username, 'password');
            if ($user) {
                $this->results['auth'][$role] = 'PASS';
                echo "✓ {$role} login: PASS\n";
            } else {
                $this->results['auth'][$role] = 'FAIL';
                $this->errors[] = "{$role} authentication failed";
                echo "✗ {$role} login: FAIL\n";
            }
        }
        
        // Test password hashing
        $hashedPassword = password_hash('testpass', PASSWORD_DEFAULT);
        if (password_verify('testpass', $hashedPassword)) {
            $this->results['auth']['password_hashing'] = 'PASS';
            echo "✓ Password hashing: PASS\n";
        } else {
            $this->results['auth']['password_hashing'] = 'FAIL';
            $this->errors[] = "Password hashing verification failed";
            echo "✗ Password hashing: FAIL\n";
        }
        
        echo "\n";
    }
    
    private function testDatabaseIntegrity() {
        echo "2. DATABASE INTEGRITY TESTING\n";
        echo "==============================\n";
        
        $requiredTables = [
            'users', 'students', 'teachers', 'subjects', 'classes', 'enrollments',
            'grades', 'assignments', 'assignment_submissions', 'announcements',
            'enrollment_applications', 'payments', 'messages', 'notifications',
            'activity_logs', 'chat_messages', 'learning_modules', 'meetings',
            'system_settings', 'landing_page_content', 'news', 'events'
        ];
        
        foreach ($requiredTables as $table) {
            try {
                $stmt = $this->pdo->query("SELECT COUNT(*) FROM $table");
                $count = $stmt->fetchColumn();
                $this->results['database'][$table] = 'PASS';
                echo "✓ Table $table: PASS ($count records)\n";
            } catch (PDOException $e) {
                $this->results['database'][$table] = 'FAIL';
                $this->errors[] = "Table $table missing or inaccessible";
                echo "✗ Table $table: FAIL\n";
            }
        }
        
        echo "\n";
    }
    
    private function testUserRoles() {
        echo "3. USER ROLES & PERMISSIONS TESTING\n";
        echo "====================================\n";
        
        $roles = ['admin', 'teacher', 'student', 'parent', 'registrar', 'accounting', 'principal', 'guidance', 'academic_coordinator'];
        
        foreach ($roles as $role) {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE role = ? AND status = 'active'");
            $stmt->execute([$role]);
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                $this->results['roles'][$role] = 'PASS';
                echo "✓ {$role} role: PASS ($count users)\n";
            } else {
                $this->results['roles'][$role] = 'FAIL';
                $this->errors[] = "No active users found for role: $role";
                echo "✗ {$role} role: FAIL (no users)\n";
            }
        }
        
        echo "\n";
    }
    
    private function testPortalAccess() {
        echo "4. PORTAL ACCESS TESTING\n";
        echo "=========================\n";
        
        $portals = [
            'admin' => 'admin/index.php',
            'teacher' => 'teacher/index.php',
            'student' => 'student/index.php',
            'parent' => 'parent/index.php',
            'registrar' => 'registrar/index.php',
            'accounting' => 'accounting/index.php',
            'principal' => 'principal/index.php',
            'guidance' => 'guidance/index.php',
            'academic_coordinator' => 'academic_coordinator/index.php'
        ];
        
        foreach ($portals as $role => $path) {
            if (file_exists($path)) {
                $this->results['portals'][$role] = 'PASS';
                echo "✓ {$role} portal: PASS\n";
            } else {
                $this->results['portals'][$role] = 'FAIL';
                $this->errors[] = "Portal file missing: $path";
                echo "✗ {$role} portal: FAIL (file missing)\n";
            }
        }
        
        // Test enrollment portal
        if (file_exists('enrollment/index.php')) {
            $this->results['portals']['enrollment'] = 'PASS';
            echo "✓ enrollment portal: PASS\n";
        } else {
            $this->results['portals']['enrollment'] = 'FAIL';
            $this->errors[] = "Enrollment portal missing";
            echo "✗ enrollment portal: FAIL\n";
        }
        
        echo "\n";
    }
    
    private function testSecurityFeatures() {
        echo "5. SECURITY FEATURES TESTING\n";
        echo "=============================\n";
        
        // Test CSRF token generation
        $token = generate_csrf_token();
        if (!empty($token) && strlen($token) >= 32) {
            $this->results['security']['csrf'] = 'PASS';
            echo "✓ CSRF token generation: PASS\n";
        } else {
            $this->results['security']['csrf'] = 'FAIL';
            $this->errors[] = "CSRF token generation failed";
            echo "✗ CSRF token generation: FAIL\n";
        }
        
        // Test input sanitization
        $testInput = "<script>alert('xss')</script>";
        $sanitized = sanitize_input($testInput);
        if ($sanitized !== $testInput && !strpos($sanitized, '<script>')) {
            $this->results['security']['sanitization'] = 'PASS';
            echo "✓ Input sanitization: PASS\n";
        } else {
            $this->results['security']['sanitization'] = 'FAIL';
            $this->errors[] = "Input sanitization not working properly";
            echo "✗ Input sanitization: FAIL\n";
        }
        
        // Test unauthorized access protection
        if (file_exists('auth/unauthorized.php')) {
            $this->results['security']['unauthorized_page'] = 'PASS';
            echo "✓ Unauthorized access page: PASS\n";
        } else {
            $this->results['security']['unauthorized_page'] = 'FAIL';
            $this->errors[] = "Unauthorized access page missing";
            echo "✗ Unauthorized access page: FAIL\n";
        }
        
        echo "\n";
    }
    
    private function testDataOperations() {
        echo "6. DATA OPERATIONS TESTING\n";
        echo "===========================\n";
        
        // Test student data operations
        try {
            $students = $this->dataManager->getStudents(5);
            $this->results['data']['students'] = 'PASS';
            echo "✓ Student data retrieval: PASS (" . count($students) . " records)\n";
        } catch (Exception $e) {
            $this->results['data']['students'] = 'FAIL';
            $this->errors[] = "Student data retrieval failed: " . $e->getMessage();
            echo "✗ Student data retrieval: FAIL\n";
        }
        
        // Test teacher data operations
        try {
            $teachers = $this->dataManager->getTeachers(5);
            $this->results['data']['teachers'] = 'PASS';
            echo "✓ Teacher data retrieval: PASS (" . count($teachers) . " records)\n";
        } catch (Exception $e) {
            $this->results['data']['teachers'] = 'FAIL';
            $this->errors[] = "Teacher data retrieval failed: " . $e->getMessage();
            echo "✗ Teacher data retrieval: FAIL\n";
        }
        
        // Test announcements
        try {
            $announcements = $this->dataManager->getAnnouncements(5);
            $this->results['data']['announcements'] = 'PASS';
            echo "✓ Announcements retrieval: PASS (" . count($announcements) . " records)\n";
        } catch (Exception $e) {
            $this->results['data']['announcements'] = 'FAIL';
            $this->errors[] = "Announcements retrieval failed: " . $e->getMessage();
            echo "✗ Announcements retrieval: FAIL\n";
        }
        
        // Test enrollment applications
        try {
            $applications = $this->dataManager->getEnrollmentApplications();
            $this->results['data']['enrollment_applications'] = 'PASS';
            echo "✓ Enrollment applications: PASS (" . count($applications) . " records)\n";
        } catch (Exception $e) {
            $this->results['data']['enrollment_applications'] = 'FAIL';
            $this->errors[] = "Enrollment applications failed: " . $e->getMessage();
            echo "✗ Enrollment applications: FAIL\n";
        }
        
        echo "\n";
    }
    
    private function generateReport() {
        echo "7. VALIDATION SUMMARY REPORT\n";
        echo "=============================\n";
        
        $totalTests = 0;
        $passedTests = 0;
        
        foreach ($this->results as $category => $tests) {
            foreach ($tests as $test => $result) {
                $totalTests++;
                if ($result === 'PASS') {
                    $passedTests++;
                }
            }
        }
        
        $successRate = ($totalTests > 0) ? round(($passedTests / $totalTests) * 100, 2) : 0;
        
        echo "Total Tests: $totalTests\n";
        echo "Passed: $passedTests\n";
        echo "Failed: " . ($totalTests - $passedTests) . "\n";
        echo "Success Rate: $successRate%\n\n";
        
        if (!empty($this->errors)) {
            echo "CRITICAL ISSUES FOUND:\n";
            echo "======================\n";
            foreach ($this->errors as $error) {
                echo "• $error\n";
            }
            echo "\n";
        }
        
        // Detailed results by category
        echo "DETAILED RESULTS BY CATEGORY:\n";
        echo "==============================\n";
        
        foreach ($this->results as $category => $tests) {
            echo strtoupper($category) . ":\n";
            foreach ($tests as $test => $result) {
                $status = ($result === 'PASS') ? '✓' : '✗';
                echo "  $status $test: $result\n";
            }
            echo "\n";
        }
        
        // Recommendations
        echo "RECOMMENDATIONS:\n";
        echo "================\n";
        
        if ($successRate >= 90) {
            echo "✓ System is in excellent condition and ready for production use.\n";
        } elseif ($successRate >= 75) {
            echo "⚠ System is mostly functional but has some issues that should be addressed.\n";
        } elseif ($successRate >= 50) {
            echo "⚠ System has significant issues that need immediate attention.\n";
        } else {
            echo "✗ System has critical failures and is not ready for production use.\n";
        }
        
        echo "\nValidation completed at: " . date('Y-m-d H:i:s') . "\n";
    }
}

// Run the validation
$validator = new SystemValidator($pdo, $dataManager);
$validator->runFullValidation();
?>
