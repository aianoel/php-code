<?php
// Data management functions

class DataManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // User management
    public function createUser($data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO users (username, email, password, first_name, last_name, role, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())
        ");
        return $stmt->execute([
            $data['username'],
            $data['email'],
            password_hash($data['password'], PASSWORD_DEFAULT),
            $data['first_name'],
            $data['last_name'],
            $data['role']
        ]);
    }
    
    public function getUserByCredentials($username, $password) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND status = 'active'");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }
        return false;
    }
    
    public function getUserById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    public function updateUser($id, $data) {
        $fields = [];
        $values = [];
        
        foreach ($data as $key => $value) {
            if ($key !== 'id') {
                $fields[] = "$key = ?";
                $values[] = $value;
            }
        }
        
        $values[] = $id;
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($values);
    }
    
    // Student management
    public function getStudents($limit = null) {
        $sql = "SELECT u.*, s.student_id, s.grade_level, s.section 
                FROM users u 
                LEFT JOIN students s ON u.id = s.user_id 
                WHERE u.role = 'student' AND u.status = 'active'
                ORDER BY u.last_name, u.first_name";
        
        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function getStudentStats() {
        $stats = [];
        
        // Total students
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'student' AND status = 'active'");
        $stmt->execute();
        $stats['total_students'] = $stmt->fetch()['total'];
        
        // Students by grade level
        $stmt = $this->pdo->prepare("
            SELECT s.grade_level, COUNT(*) as count 
            FROM students s 
            JOIN users u ON s.user_id = u.id 
            WHERE u.status = 'active' 
            GROUP BY s.grade_level
        ");
        $stmt->execute();
        $stats['by_grade'] = $stmt->fetchAll();
        
        return $stats;
    }
    
    // Teacher management
    public function getTeachers($limit = null) {
        $sql = "SELECT u.*, t.employee_id, t.department, t.specialization 
                FROM users u 
                LEFT JOIN teachers t ON u.id = t.user_id 
                WHERE u.role = 'teacher' AND u.status = 'active'
                ORDER BY u.last_name, u.first_name";
        
        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function getTeacherClasses($teacher_id) {
        $stmt = $this->pdo->prepare("
            SELECT c.*, s.name as subject_name 
            FROM classes c 
            JOIN subjects s ON c.subject_id = s.id 
            WHERE c.teacher_id = ? AND c.status = 'active'
            ORDER BY c.name
        ");
        $stmt->execute([$teacher_id]);
        return $stmt->fetchAll();
    }
    
    public function getTeacherStudents($teacher_id) {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT u.id, u.first_name, u.last_name, st.student_id, st.grade_level, st.section
            FROM users u
            JOIN students st ON u.id = st.user_id
            JOIN enrollments e ON st.id = e.student_id
            JOIN classes c ON e.class_id = c.id
            WHERE c.teacher_id = ? AND u.status = 'active'
            ORDER BY u.last_name, u.first_name
        ");
        $stmt->execute([$teacher_id]);
        return $stmt->fetchAll();
    }
    
    public function getTeacherStats($teacher_id) {
        $stats = [];
        
        // Total classes
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM classes WHERE teacher_id = ? AND status = 'active'");
        $stmt->execute([$teacher_id]);
        $stats['total_classes'] = $stmt->fetch()['total'];
        
        // Total students
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT e.student_id) as total
            FROM enrollments e
            JOIN classes c ON e.class_id = c.id
            WHERE c.teacher_id = ?
        ");
        $stmt->execute([$teacher_id]);
        $stats['total_students'] = $stmt->fetch()['total'];
        
        // Pending assignments
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as total 
            FROM assignments a
            JOIN classes c ON a.class_id = c.id
            WHERE c.teacher_id = ? AND a.due_date >= CURDATE()
        ");
        $stmt->execute([$teacher_id]);
        $stats['pending_assignments'] = $stmt->fetch()['total'];
        
        return $stats;
    }
    
    // Enrollment management
    public function createEnrollmentApplication($data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO enrollment_applications 
            (first_name, last_name, email, phone, birth_date, address, parent_name, parent_phone, 
             grade_level, strand, previous_school, previous_gpa, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        
        $result = $stmt->execute([
            $data['firstName'],
            $data['lastName'],
            $data['email'],
            $data['phoneNumber'],
            $data['dateOfBirth'],
            $data['address'],
            $data['parentName'],
            $data['parentPhone'],
            $data['desiredGradeLevel'],
            $data['desiredStrand'],
            $data['previousSchool'],
            $data['previousGPA']
        ]);
        
        if ($result) {
            return $this->pdo->lastInsertId();
        }
        return false;
    }
    
    public function getEnrollmentApplications($status = null) {
        $sql = "SELECT * FROM enrollment_applications";
        $params = [];
        
        if ($status) {
            $sql .= " WHERE status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    // SMS notification function
    public function sendSMS($phoneNumber, $message) {
        $apiKey = 'ad7e27a483935c25d4960577a031a52e';
        $apiUrl = 'https://api.semaphore.co/api/v4/messages';
        
        $data = [
            'apikey' => $apiKey,
            'number' => $phoneNumber,
            'message' => $message,
            'sendername' => 'EduManage'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200) {
            return json_decode($response, true);
        }
        
        return false;
    }
    
    // Generate student credentials
    public function generateStudentCredentials($firstName, $lastName, $applicationId) {
        // Generate username: first name + last initial + application ID
        $username = strtolower($firstName . substr($lastName, 0, 1) . $applicationId);
        
        // Generate a secure random password
        $password = $this->generateRandomPassword(8);
        
        return [
            'username' => $username,
            'password' => $password
        ];
    }
    
    // Generate random password
    private function generateRandomPassword($length = 8) {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $password;
    }
    
    public function updateEnrollmentStatus($id, $status) {
        $stmt = $this->pdo->prepare("UPDATE enrollment_applications SET status = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$status, $id]);
    }
    
    // Grades management
    public function getStudentGrades($student_id) {
        $stmt = $this->pdo->prepare("
            SELECT g.*, s.name as subject_name, c.name as class_name
            FROM grades g
            JOIN classes c ON g.class_id = c.id
            JOIN subjects s ON c.subject_id = s.id
            WHERE g.student_id = ?
            ORDER BY g.created_at DESC
        ");
        $stmt->execute([$student_id]);
        return $stmt->fetchAll();
    }
    
    public function addGrade($data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO grades (student_id, class_id, grade_type, score, max_score, percentage, letter_grade, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        return $stmt->execute([
            $data['student_id'],
            $data['class_id'],
            $data['grade_type'],
            $data['score'],
            $data['max_score'],
            $data['percentage'],
            $data['letter_grade']
        ]);
    }
    
    // Assignments management
    public function getAssignments($class_id = null, $student_id = null) {
        $sql = "SELECT a.*, c.name as class_name, s.name as subject_name";
        $params = [];
        
        if ($student_id) {
            $sql .= ", sub.submitted_at, sub.score, sub.feedback
                     FROM assignments a
                     JOIN classes c ON a.class_id = c.id
                     JOIN subjects s ON c.subject_id = s.id
                     LEFT JOIN assignment_submissions sub ON a.id = sub.assignment_id AND sub.student_id = ?";
            $params[] = $student_id;
        } else {
            $sql .= " FROM assignments a
                     JOIN classes c ON a.class_id = c.id
                     JOIN subjects s ON c.subject_id = s.id";
        }
        
        if ($class_id) {
            $sql .= " WHERE a.class_id = ?";
            $params[] = $class_id;
        }
        
        $sql .= " ORDER BY a.due_date DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function createAssignment($data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO assignments (class_id, title, description, due_date, max_score, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        return $stmt->execute([
            $data['class_id'],
            $data['title'],
            $data['description'],
            $data['due_date'],
            $data['max_score']
        ]);
    }
    
    // Announcements management
    public function getAnnouncements($limit = null, $role = null) {
        $sql = "SELECT a.*, u.first_name, u.last_name 
                FROM announcements a 
                JOIN users u ON a.created_by = u.id 
                WHERE a.status = 'active'";
        
        if ($role) {
            $sql .= " AND (a.target_audience = 'all' OR a.target_audience = ?)";
        }
        
        $sql .= " ORDER BY a.created_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }
        
        $stmt = $this->pdo->prepare($sql);
        $params = $role ? [$role] : [];
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function createAnnouncement($data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO announcements (title, content, target_audience, priority, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        return $stmt->execute([
            $data['title'],
            $data['content'],
            $data['target_audience'],
            $data['priority'],
            $data['created_by']
        ]);
    }
    
    // Dashboard statistics
    public function getDashboardStats($role, $user_id = null) {
        $stats = [];
        
        switch ($role) {
            case 'admin':
                $stats = $this->getAdminStats();
                break;
            case 'teacher':
                $stats = $this->getTeacherStats($user_id);
                break;
            case 'student':
                $stats = $this->getStudentDashboardStats($user_id);
                break;
            case 'parent':
                $stats = $this->getParentStats($user_id);
                break;
            default:
                $stats = [];
        }
        
        return $stats;
    }
    
    private function getAdminStats() {
        $stats = [];
        
        try {
            // Total users by role - with error handling
            $stmt = $this->pdo->prepare("SELECT role, COUNT(*) as count FROM users WHERE status = 'active' GROUP BY role");
            $stmt->execute();
            $stats['users_by_role'] = $stmt->fetchAll();
        } catch (PDOException $e) {
            $stats['users_by_role'] = [];
        }
        
        try {
            // Recent enrollments - with error handling
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM enrollments WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $stmt->execute();
            $stats['recent_enrollments'] = $stmt->fetch()['count'] ?? 0;
        } catch (PDOException $e) {
            $stats['recent_enrollments'] = 0;
        }
        
        try {
            // System activity - with error handling
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
            $stmt->execute();
            $stats['daily_activity'] = $stmt->fetch()['count'] ?? 0;
        } catch (PDOException $e) {
            $stats['daily_activity'] = 0;
        }
        
        // Provide default stats if database queries fail
        $stats['total_users'] = $this->getTableCount('users');
        $stats['total_students'] = $this->getTableCount('students');
        $stats['total_teachers'] = $this->getTableCount('teachers');
        $stats['pending_enrollments'] = $this->getTableCount('enrollments', "status = 'pending'");
        
        return $stats;
    }
    
    private function getTableCount($table, $condition = null) {
        try {
            $sql = "SELECT COUNT(*) as count FROM $table";
            if ($condition) {
                $sql .= " WHERE $condition";
            }
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            return $stmt->fetch()['count'] ?? 0;
        } catch (PDOException $e) {
            return 0;
        }
    }
    
    private function getStudentDashboardStats($student_id) {
        $stats = [];
        
        // Get student record
        $stmt = $this->pdo->prepare("SELECT * FROM students WHERE user_id = ?");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch();
        
        if (!$student) {
            return $stats;
        }
        
        // Current GPA
        $stmt = $this->pdo->prepare("
            SELECT AVG(percentage) as gpa 
            FROM grades 
            WHERE student_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
        ");
        $stmt->execute([$student['id']]);
        $gpa = $stmt->fetch()['gpa'];
        $stats['current_gpa'] = $gpa ? round($gpa / 25, 2) : 0; // Convert to 4.0 scale
        
        // Total subjects
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT c.subject_id) as count
            FROM enrollments e
            JOIN classes c ON e.class_id = c.id
            WHERE e.student_id = ?
        ");
        $stmt->execute([$student['id']]);
        $stats['total_subjects'] = $stmt->fetch()['count'];
        
        // Assignments due
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count
            FROM assignments a
            JOIN classes c ON a.class_id = c.id
            JOIN enrollments e ON c.id = e.class_id
            LEFT JOIN assignment_submissions sub ON a.id = sub.assignment_id AND sub.student_id = ?
            WHERE e.student_id = ? AND a.due_date >= CURDATE() AND sub.id IS NULL
        ");
        $stmt->execute([$student['id'], $student['id']]);
        $stats['assignments_due'] = $stmt->fetch()['count'];
        
        // Total grades
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM grades WHERE student_id = ?");
        $stmt->execute([$student['id']]);
        $stats['total_grades'] = $stmt->fetch()['count'];
        
        return $stats;
    }
    
    private function getParentStats($parent_id) {
        $stats = [];
        
        // Get children
        $stmt = $this->pdo->prepare("
            SELECT s.* FROM students s 
            JOIN users u ON s.user_id = u.id 
            WHERE s.parent_id = ? AND u.status = 'active'
        ");
        $stmt->execute([$parent_id]);
        $children = $stmt->fetchAll();
        
        $stats['total_children'] = count($children);
        $stats['children_stats'] = [];
        
        foreach ($children as $child) {
            $child_stats = $this->getStudentDashboardStats($child['user_id']);
            $child_stats['name'] = $child['first_name'] . ' ' . $child['last_name'];
            $stats['children_stats'][] = $child_stats;
        }
        
        return $stats;
    }
}

// Initialize DataManager
$dataManager = new DataManager($pdo);

/**
 * Helper function to include the sidebar component
 * This function defines the ALLOW_ACCESS constant and includes the sidebar component
 */
function include_sidebar() {
    if (!defined('ALLOW_ACCESS')) {
        define('ALLOW_ACCESS', true);
    }
    include_once __DIR__ . '/sidebar.php';
}

?>
