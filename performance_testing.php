<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

class PerformanceTester {
    private $pdo;
    private $dataManager;
    private $results = [];
    
    public function __construct($pdo, $dataManager) {
        $this->pdo = $pdo;
        $this->dataManager = $dataManager;
    }
    
    public function runPerformanceTests() {
        echo "=== SchoolEnroll-1 Performance Testing ===\n\n";
        
        $this->testDatabasePerformance();
        $this->testPageLoadSimulation();
        $this->testConcurrentUserSimulation();
        $this->testLargeDatasetHandling();
        $this->generatePerformanceReport();
    }
    
    private function testDatabasePerformance() {
        echo "1. DATABASE PERFORMANCE TESTING\n";
        echo "================================\n";
        
        // Test query performance with current data
        $queries = [
            'users_query' => "SELECT * FROM users WHERE status = 'active'",
            'students_with_grades' => "SELECT s.*, AVG(g.percentage) as gpa FROM students s LEFT JOIN grades g ON s.id = g.student_id GROUP BY s.id",
            'teacher_classes' => "SELECT c.*, s.name as subject_name, u.first_name, u.last_name FROM classes c JOIN subjects s ON c.subject_id = s.id JOIN users u ON c.teacher_id = u.id",
            'enrollment_stats' => "SELECT grade_level, COUNT(*) as count FROM students GROUP BY grade_level",
            'recent_activity' => "SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 100"
        ];
        
        foreach ($queries as $name => $query) {
            $startTime = microtime(true);
            $stmt = $this->pdo->query($query);
            $results = $stmt->fetchAll();
            $endTime = microtime(true);
            
            $executionTime = round(($endTime - $startTime) * 1000, 2);
            $recordCount = count($results);
            
            $this->results['database'][$name] = [
                'time' => $executionTime,
                'records' => $recordCount,
                'status' => $executionTime < 100 ? 'EXCELLENT' : ($executionTime < 500 ? 'GOOD' : 'SLOW')
            ];
            
            echo "✓ $name: {$executionTime}ms ($recordCount records) - {$this->results['database'][$name]['status']}\n";
        }
        
        echo "\n";
    }
    
    private function testPageLoadSimulation() {
        echo "2. PAGE LOAD SIMULATION\n";
        echo "========================\n";
        
        // Simulate dashboard loading for different roles
        $roles = ['admin', 'teacher', 'student', 'parent'];
        
        foreach ($roles as $role) {
            $startTime = microtime(true);
            
            // Simulate dashboard data loading
            try {
                $user = $this->pdo->query("SELECT * FROM users WHERE role = '$role' LIMIT 1")->fetch();
                if ($user) {
                    $stats = $this->dataManager->getDashboardStats($role, $user['id']);
                    $announcements = $this->dataManager->getAnnouncements(5, $role);
                    
                    if ($role === 'student') {
                        $grades = $this->dataManager->getStudentGrades($user['id']);
                        $assignments = $this->dataManager->getAssignments(null, $user['id']);
                    } elseif ($role === 'teacher') {
                        $classes = $this->dataManager->getTeacherClasses($user['id']);
                        $students = $this->dataManager->getTeacherStudents($user['id']);
                    }
                }
                
                $endTime = microtime(true);
                $loadTime = round(($endTime - $startTime) * 1000, 2);
                
                $this->results['page_load'][$role] = [
                    'time' => $loadTime,
                    'status' => $loadTime < 200 ? 'EXCELLENT' : ($loadTime < 500 ? 'GOOD' : 'SLOW')
                ];
                
                echo "✓ {$role} dashboard: {$loadTime}ms - {$this->results['page_load'][$role]['status']}\n";
                
            } catch (Exception $e) {
                echo "✗ {$role} dashboard: ERROR - " . $e->getMessage() . "\n";
            }
        }
        
        echo "\n";
    }
    
    private function testConcurrentUserSimulation() {
        echo "3. CONCURRENT USER SIMULATION\n";
        echo "==============================\n";
        
        // Simulate multiple users accessing the system
        $startTime = microtime(true);
        
        $operations = 0;
        
        // Simulate 10 concurrent operations
        for ($i = 0; $i < 10; $i++) {
            // Random operations that users might perform
            $operation = rand(1, 4);
            
            switch ($operation) {
                case 1: // Login simulation
                    $user = $this->dataManager->getUserByCredentials('student', 'password');
                    break;
                case 2: // Data retrieval
                    $students = $this->dataManager->getStudents(10);
                    break;
                case 3: // Announcements
                    $announcements = $this->dataManager->getAnnouncements(5);
                    break;
                case 4: // Statistics
                    $stats = $this->dataManager->getDashboardStats('admin');
                    break;
            }
            $operations++;
        }
        
        $endTime = microtime(true);
        $totalTime = round(($endTime - $startTime) * 1000, 2);
        $avgTime = round($totalTime / $operations, 2);
        
        $this->results['concurrent'] = [
            'operations' => $operations,
            'total_time' => $totalTime,
            'avg_time' => $avgTime,
            'status' => $avgTime < 50 ? 'EXCELLENT' : ($avgTime < 100 ? 'GOOD' : 'SLOW')
        ];
        
        echo "✓ Concurrent operations: $operations ops in {$totalTime}ms (avg: {$avgTime}ms) - {$this->results['concurrent']['status']}\n";
        echo "\n";
    }
    
    private function testLargeDatasetHandling() {
        echo "4. LARGE DATASET HANDLING\n";
        echo "==========================\n";
        
        // Test with all available data
        $datasets = [
            'all_students' => "SELECT COUNT(*) FROM students",
            'all_grades' => "SELECT COUNT(*) FROM grades",
            'all_enrollments' => "SELECT COUNT(*) FROM enrollments",
            'all_assignments' => "SELECT COUNT(*) FROM assignments",
            'all_payments' => "SELECT COUNT(*) FROM payments"
        ];
        
        foreach ($datasets as $name => $countQuery) {
            $count = $this->pdo->query($countQuery)->fetchColumn();
            
            if ($count > 0) {
                $startTime = microtime(true);
                
                // Test retrieval of large dataset
                $tableName = str_replace('all_', '', $name);
                $query = "SELECT * FROM $tableName LIMIT 1000"; // Limit to prevent memory issues
                $stmt = $this->pdo->query($query);
                $results = $stmt->fetchAll();
                
                $endTime = microtime(true);
                $retrievalTime = round(($endTime - $startTime) * 1000, 2);
                
                $this->results['large_data'][$name] = [
                    'total_records' => $count,
                    'retrieved' => count($results),
                    'time' => $retrievalTime,
                    'status' => $retrievalTime < 500 ? 'EXCELLENT' : ($retrievalTime < 1000 ? 'GOOD' : 'SLOW')
                ];
                
                echo "✓ $name: $count total, retrieved " . count($results) . " in {$retrievalTime}ms - {$this->results['large_data'][$name]['status']}\n";
            } else {
                echo "⚠ $name: No data available for testing\n";
            }
        }
        
        echo "\n";
    }
    
    private function generatePerformanceReport() {
        echo "5. PERFORMANCE SUMMARY REPORT\n";
        echo "==============================\n";
        
        // Calculate overall performance score
        $scores = [];
        
        // Database performance score
        if (isset($this->results['database'])) {
            $dbScores = [];
            foreach ($this->results['database'] as $query => $result) {
                $dbScores[] = $result['status'] === 'EXCELLENT' ? 100 : ($result['status'] === 'GOOD' ? 75 : 50);
            }
            $scores['database'] = array_sum($dbScores) / count($dbScores);
        }
        
        // Page load performance score
        if (isset($this->results['page_load'])) {
            $pageScores = [];
            foreach ($this->results['page_load'] as $page => $result) {
                $pageScores[] = $result['status'] === 'EXCELLENT' ? 100 : ($result['status'] === 'GOOD' ? 75 : 50);
            }
            $scores['page_load'] = array_sum($pageScores) / count($pageScores);
        }
        
        // Concurrent performance score
        if (isset($this->results['concurrent'])) {
            $scores['concurrent'] = $this->results['concurrent']['status'] === 'EXCELLENT' ? 100 : 
                                   ($this->results['concurrent']['status'] === 'GOOD' ? 75 : 50);
        }
        
        // Large data performance score
        if (isset($this->results['large_data'])) {
            $dataScores = [];
            foreach ($this->results['large_data'] as $dataset => $result) {
                $dataScores[] = $result['status'] === 'EXCELLENT' ? 100 : ($result['status'] === 'GOOD' ? 75 : 50);
            }
            if (!empty($dataScores)) {
                $scores['large_data'] = array_sum($dataScores) / count($dataScores);
            }
        }
        
        $overallScore = !empty($scores) ? round(array_sum($scores) / count($scores), 2) : 0;
        
        echo "PERFORMANCE SCORES:\n";
        echo "===================\n";
        foreach ($scores as $category => $score) {
            echo "• " . ucfirst(str_replace('_', ' ', $category)) . ": {$score}%\n";
        }
        echo "\nOVERALL PERFORMANCE SCORE: {$overallScore}%\n\n";
        
        // Performance recommendations
        echo "PERFORMANCE RECOMMENDATIONS:\n";
        echo "============================\n";
        
        if ($overallScore >= 90) {
            echo "✓ EXCELLENT: System performance is outstanding and ready for production.\n";
        } elseif ($overallScore >= 75) {
            echo "✓ GOOD: System performance is acceptable with minor optimization opportunities.\n";
        } elseif ($overallScore >= 60) {
            echo "⚠ FAIR: System performance needs improvement before production deployment.\n";
        } else {
            echo "✗ POOR: System performance requires significant optimization.\n";
        }
        
        // Specific recommendations
        if (isset($this->results['database'])) {
            $slowQueries = array_filter($this->results['database'], function($result) {
                return $result['status'] === 'SLOW';
            });
            
            if (!empty($slowQueries)) {
                echo "\nSlow database queries detected:\n";
                foreach ($slowQueries as $query => $result) {
                    echo "• $query: {$result['time']}ms - Consider adding indexes or optimizing query\n";
                }
            }
        }
        
        echo "\nPerformance testing completed at: " . date('Y-m-d H:i:s') . "\n";
    }
}

// Run performance tests
$tester = new PerformanceTester($pdo, $dataManager);
$tester->runPerformanceTests();
?>
