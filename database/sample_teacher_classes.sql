-- Sample data for testing teacher classes page

-- Create a sample teacher user if not exists
INSERT IGNORE INTO users (id, username, email, password, first_name, last_name, role, status)
VALUES (100, 'teacher1', 'teacher1@edumanage.school', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John', 'Smith', 'teacher', 'active');

-- Create teacher record if not exists
INSERT IGNORE INTO teachers (id, user_id, employee_id, department, specialization, hire_date, status)
VALUES (100, 100, 'T0100', 'Science', 'Physics', '2023-01-15', 'active');

-- Create sample subjects if not exist
INSERT IGNORE INTO subjects (id, code, name, description, grade_level)
VALUES 
(100, 'PHYS101', 'Physics 101', 'Introduction to Physics', 'grade11'),
(101, 'CHEM101', 'Chemistry 101', 'Introduction to Chemistry', 'grade11'),
(102, 'MATH101', 'Mathematics 101', 'Advanced Mathematics', 'grade11');

-- Create sample classes assigned to the teacher
INSERT IGNORE INTO classes (id, subject_id, teacher_id, name, section, grade_level, school_year, schedule_days, schedule_time_start, schedule_time_end, room)
VALUES 
(100, 100, 100, 'Physics 101 - A', 'Section A', 'grade11', '2024-2025', 'MWF', '08:00:00', '09:30:00', 'Room 101'),
(101, 101, 100, 'Chemistry 101 - B', 'Section B', 'grade11', '2024-2025', 'TTh', '10:00:00', '11:30:00', 'Lab 201'),
(102, 102, 100, 'Mathematics 101 - C', 'Section C', 'grade11', '2024-2025', 'MWF', '13:00:00', '14:30:00', 'Room 305');

-- Create sample students
INSERT IGNORE INTO users (id, username, email, password, first_name, last_name, role, status)
VALUES 
(200, 'student1', 'student1@edumanage.school', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Alice', 'Johnson', 'student', 'active'),
(201, 'student2', 'student2@edumanage.school', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Bob', 'Williams', 'student', 'active'),
(202, 'student3', 'student3@edumanage.school', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Carol', 'Davis', 'student', 'active');

-- Create student records
INSERT IGNORE INTO students (id, user_id, student_id, grade_level, section)
VALUES 
(200, 200, '2024001', 'grade11', 'Section A'),
(201, 201, '2024002', 'grade11', 'Section B'),
(202, 202, '2024003', 'grade11', 'Section C');

-- Enroll students in classes
INSERT IGNORE INTO class_enrollments (student_id, class_id, status)
VALUES 
(200, 100, 'active'),
(200, 101, 'active'),
(201, 100, 'active'),
(201, 102, 'active'),
(202, 101, 'active'),
(202, 102, 'active');

-- Create sample assignments
INSERT IGNORE INTO assignments (class_id, title, description, due_date, max_score)
VALUES 
(100, 'Physics Homework 1', 'Complete problems 1-10 in Chapter 1', DATE_ADD(CURRENT_DATE, INTERVAL 7 DAY), 100),
(100, 'Physics Lab Report', 'Write a report on the pendulum experiment', DATE_ADD(CURRENT_DATE, INTERVAL 14 DAY), 100),
(101, 'Chemistry Quiz', 'Periodic table elements quiz', DATE_ADD(CURRENT_DATE, INTERVAL 5 DAY), 50),
(102, 'Math Problem Set', 'Complete the calculus problem set', DATE_ADD(CURRENT_DATE, INTERVAL 10 DAY), 100);
