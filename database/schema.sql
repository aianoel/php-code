-- EduManage School Management System Database Schema
-- Created for PHP conversion of SchoolEnroll-1 system

CREATE DATABASE IF NOT EXISTS school_enrollment;
USE school_enrollment;

-- Users table (main authentication table)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    role ENUM('admin', 'principal', 'teacher', 'student', 'parent', 'guidance', 'registrar', 'accounting', 'academic_coordinator') NOT NULL,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    profile_image VARCHAR(255),
    phone VARCHAR(20),
    address TEXT,
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other'),
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Students table (extended info for students)
CREATE TABLE students (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    student_id VARCHAR(20) UNIQUE NOT NULL,
    parent_id INT,
    grade_level VARCHAR(10) NOT NULL,
    section VARCHAR(10),
    strand VARCHAR(20),
    enrollment_date DATE,
    graduation_date DATE,
    status ENUM('enrolled', 'graduated', 'transferred', 'dropped') DEFAULT 'enrolled',
    emergency_contact_name VARCHAR(100),
    emergency_contact_phone VARCHAR(20),
    medical_info TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Teachers table (extended info for teachers)
CREATE TABLE teachers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    employee_id VARCHAR(20) UNIQUE NOT NULL,
    department VARCHAR(50),
    specialization VARCHAR(100),
    hire_date DATE,
    salary DECIMAL(10,2),
    status ENUM('active', 'inactive', 'on_leave') DEFAULT 'active',
    qualifications TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Subjects table
CREATE TABLE subjects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    credits INT DEFAULT 1,
    units INT DEFAULT 1,
    department VARCHAR(50),
    grade_level VARCHAR(10),
    strand VARCHAR(20),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Classes table
CREATE TABLE classes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    subject_id INT NOT NULL,
    teacher_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    section VARCHAR(10),
    grade_level VARCHAR(10),
    school_year VARCHAR(10),
    semester ENUM('1st', '2nd', 'summer') DEFAULT '1st',
    schedule_days VARCHAR(20), -- JSON array of days
    schedule_time_start TIME,
    schedule_time_end TIME,
    room VARCHAR(20),
    max_students INT DEFAULT 40,
    status ENUM('active', 'inactive', 'completed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Enrollments table (student-class relationships)
CREATE TABLE enrollments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    class_id INT NOT NULL,
    enrollment_date DATE DEFAULT (CURRENT_DATE),
    status ENUM('enrolled', 'dropped', 'completed') DEFAULT 'enrolled',
    final_grade DECIMAL(5,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    UNIQUE KEY unique_enrollment (student_id, class_id)
);

-- Grades table
CREATE TABLE grades (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    class_id INT NOT NULL,
    grade_type ENUM('quiz', 'exam', 'assignment', 'project', 'participation', 'final') NOT NULL,
    title VARCHAR(100),
    score DECIMAL(5,2) NOT NULL,
    max_score DECIMAL(5,2) NOT NULL,
    percentage DECIMAL(5,2) GENERATED ALWAYS AS ((score / max_score) * 100) STORED,
    letter_grade VARCHAR(2),
    date_recorded DATE DEFAULT (CURRENT_DATE),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
);

-- Assignments table
CREATE TABLE assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    class_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    instructions TEXT,
    due_date DATETIME NOT NULL,
    max_score DECIMAL(5,2) DEFAULT 100,
    assignment_type ENUM('homework', 'project', 'quiz', 'exam', 'essay') DEFAULT 'homework',
    status ENUM('draft', 'published', 'closed') DEFAULT 'published',
    allow_late_submission BOOLEAN DEFAULT FALSE,
    late_penalty DECIMAL(5,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
);

-- Assignment submissions table
CREATE TABLE assignment_submissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    assignment_id INT NOT NULL,
    student_id INT NOT NULL,
    submission_text TEXT,
    file_path VARCHAR(255),
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    score DECIMAL(5,2),
    feedback TEXT,
    status ENUM('submitted', 'graded', 'returned') DEFAULT 'submitted',
    is_late BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY unique_submission (assignment_id, student_id)
);

-- Announcements table
CREATE TABLE announcements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    target_audience ENUM('all', 'students', 'teachers', 'parents', 'staff') DEFAULT 'all',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    status ENUM('draft', 'active', 'archived') DEFAULT 'active',
    publish_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    expire_date DATETIME,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Enrollment applications table (for new student applications)
CREATE TABLE enrollment_applications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    application_id VARCHAR(20) UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    birth_date DATE NOT NULL,
    gender ENUM('male', 'female', 'other'),
    address TEXT NOT NULL,
    parent_name VARCHAR(100) NOT NULL,
    parent_phone VARCHAR(20) NOT NULL,
    parent_email VARCHAR(100),
    grade_level VARCHAR(10) NOT NULL,
    strand VARCHAR(20),
    previous_school VARCHAR(100),
    previous_gpa DECIMAL(3,2),
    documents_submitted BOOLEAN DEFAULT FALSE,
    payment_status ENUM('pending', 'partial', 'completed') DEFAULT 'pending',
    status ENUM('pending', 'under_review', 'approved', 'rejected', 'enrolled') DEFAULT 'pending',
    notes TEXT,
    reviewed_by INT,
    reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Payments table
CREATE TABLE payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT,
    application_id INT,
    payment_type ENUM('enrollment', 'tuition', 'miscellaneous', 'penalty') NOT NULL,
    description VARCHAR(200) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash', 'check', 'bank_transfer', 'online', 'installment') DEFAULT 'cash',
    reference_number VARCHAR(50),
    due_date DATE,
    payment_date DATE,
    status ENUM('pending', 'paid', 'overdue', 'cancelled') DEFAULT 'pending',
    processed_by INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (application_id) REFERENCES enrollment_applications(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Messages table
CREATE TABLE messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sender_id INT NOT NULL,
    recipient_id INT NOT NULL,
    subject VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Class enrollments table
CREATE TABLE class_enrollments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    class_id INT NOT NULL,
    enrollment_date DATE DEFAULT (CURRENT_DATE),
    status ENUM('active', 'dropped', 'completed') DEFAULT 'active',
    final_grade DECIMAL(5,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    UNIQUE KEY unique_enrollment (student_id, class_id)
);

-- Grades table
CREATE TABLE grades (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    assignment_id INT NOT NULL,
    grade DECIMAL(5,2) NOT NULL,
    feedback TEXT,
    graded_by INT NOT NULL,
    graded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (graded_by) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_grade (student_id, assignment_id)
);

-- Quarterly grades table for subject-based grading
CREATE TABLE quarterly_grades (
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
);

-- Notifications table
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    action_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Activity logs table
CREATE TABLE activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Chat messages table
CREATE TABLE chat_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sender_id INT NOT NULL,
    receiver_id INT,
    room_id VARCHAR(50),
    message TEXT NOT NULL,
    message_type ENUM('text', 'file', 'image', 'system') DEFAULT 'text',
    file_path VARCHAR(255),
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Learning modules table
CREATE TABLE learning_modules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    class_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    content TEXT,
    module_type ENUM('lesson', 'video', 'document', 'quiz', 'assignment') DEFAULT 'lesson',
    file_path VARCHAR(255),
    order_index INT DEFAULT 0,
    is_published BOOLEAN DEFAULT FALSE,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Meeting/Conference table
CREATE TABLE meetings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    meeting_type ENUM('class', 'parent_teacher', 'staff', 'general') DEFAULT 'class',
    host_id INT NOT NULL,
    meeting_url VARCHAR(255),
    meeting_id VARCHAR(100),
    password VARCHAR(50),
    start_time DATETIME NOT NULL,
    end_time DATETIME,
    status ENUM('scheduled', 'ongoing', 'completed', 'cancelled') DEFAULT 'scheduled',
    max_participants INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (host_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Meeting participants table
CREATE TABLE meeting_participants (
    id INT PRIMARY KEY AUTO_INCREMENT,
    meeting_id INT NOT NULL,
    user_id INT NOT NULL,
    joined_at TIMESTAMP NULL,
    left_at TIMESTAMP NULL,
    duration_minutes INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_participant (meeting_id, user_id)
);

-- System settings table
CREATE TABLE system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default admin user
INSERT INTO users (username, email, password, first_name, last_name, role) VALUES 
('admin', 'admin@edumanage.school', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System', 'Administrator', 'admin');

-- Insert default system settings
INSERT INTO system_settings (setting_key, setting_value, setting_type, description, is_public) VALUES
('school_name', 'EduManage School', 'string', 'Official school name', TRUE),
('school_year', '2024-2025', 'string', 'Current school year', TRUE),
('current_semester', '1st', 'string', 'Current semester', TRUE),
('enrollment_open', 'true', 'boolean', 'Whether enrollment is currently open', TRUE),
('max_students_per_class', '40', 'number', 'Maximum students per class', FALSE),
('grade_scale', '{"A": 90, "B": 80, "C": 70, "D": 60, "F": 0}', 'json', 'Grading scale configuration', FALSE);

-- Insert sample subjects
INSERT INTO subjects (code, name, description, grade_level, strand) VALUES
('MATH7', 'Mathematics 7', 'Basic Mathematics for Grade 7', 'grade7', NULL),
('ENG7', 'English 7', 'English Language for Grade 7', 'grade7', NULL),
('SCI7', 'Science 7', 'General Science for Grade 7', 'grade7', NULL),
('MATH11-STEM', 'Pre-Calculus', 'Pre-Calculus for STEM Grade 11', 'grade11', 'stem'),
('CHEM11-STEM', 'General Chemistry', 'General Chemistry for STEM Grade 11', 'grade11', 'stem'),
('PHYS11-STEM', 'General Physics', 'General Physics for STEM Grade 11', 'grade11', 'stem');

-- Landing page content table
CREATE TABLE landing_page_content (
    id INT PRIMARY KEY AUTO_INCREMENT,
    section_name VARCHAR(100) NOT NULL,
    title VARCHAR(200) NOT NULL,
    subtitle VARCHAR(200),
    content TEXT,
    image_url VARCHAR(255),
    button_text VARCHAR(50),
    button_url VARCHAR(255),
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Landing page announcements table
CREATE TABLE landing_announcements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    message TEXT NOT NULL,
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default landing page content
INSERT INTO landing_page_content (section_name, title, subtitle, content, display_order, is_active) VALUES
('hero', 'Welcome to EduManage School', 'Empowering Students for a Brighter Future', 'We provide quality education with modern teaching methods and a supportive learning environment.', 1, TRUE),
('about', 'About Our School', 'Excellence in Education Since 2000', 'EduManage School is dedicated to providing a comprehensive education that prepares students for success in college and beyond.', 2, TRUE),
('programs', 'Our Programs', 'Comprehensive Learning Paths', 'We offer a variety of academic programs designed to meet the diverse needs of our students.', 3, TRUE);

-- Create indexes for better performance
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_status ON users(status);
CREATE INDEX idx_students_grade_level ON students(grade_level);
CREATE INDEX idx_grades_student_class ON grades(student_id, class_id);
CREATE INDEX idx_assignments_due_date ON assignments(due_date);
CREATE INDEX idx_announcements_target ON announcements(target_audience, status);
CREATE INDEX idx_notifications_user_read ON notifications(user_id, is_read);
CREATE INDEX idx_activity_logs_user_date ON activity_logs(user_id, created_at);
CREATE INDEX idx_chat_messages_participants ON chat_messages(sender_id, receiver_id);

-- News table for school news articles
CREATE TABLE news (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    excerpt TEXT,
    content TEXT NOT NULL,
    date DATE NOT NULL,
    image_url VARCHAR(255),
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Events table for school events
CREATE TABLE events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    date DATE NOT NULL,
    time TIME,
    location VARCHAR(200),
    image_url VARCHAR(255),
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert sample news
INSERT INTO news (title, excerpt, content, date, display_order, is_active) VALUES
('School Year 2024-2025 Begins', 'Welcome back to all students for the new school year!', 'We are excited to welcome all students back to campus for the 2024-2025 school year. Please check the academic calendar for important dates and events.', CURRENT_DATE(), 1, TRUE),
('New STEM Laboratory Opening', 'State-of-the-art STEM lab now available for students', 'We are proud to announce the opening of our new STEM laboratory equipped with the latest technology and resources to enhance learning experiences.', CURRENT_DATE(), 2, TRUE);

-- Insert sample events
INSERT INTO events (title, description, date, time, location, display_order, is_active) VALUES
('Parent-Teacher Conference', 'Meet with teachers to discuss student progress', DATE_ADD(CURRENT_DATE(), INTERVAL 14 DAY), '13:00:00', 'Main Auditorium', 1, TRUE),
('Science Fair', 'Annual science fair showcasing student projects', DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY), '09:00:00', 'School Gymnasium', 2, TRUE);
