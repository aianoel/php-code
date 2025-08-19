-- Document Review System Tables
-- This system handles document submission, review, and approval workflows

CREATE TABLE IF NOT EXISTS document_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    required_for_role VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS document_submissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    document_type_id INT NOT NULL,
    submitted_by INT NOT NULL,
    student_id INT NULL, -- For documents related to specific students
    title VARCHAR(200) NOT NULL,
    description TEXT,
    file_path VARCHAR(500),
    file_name VARCHAR(255),
    file_size INT,
    mime_type VARCHAR(100),
    
    -- Review workflow
    status ENUM('pending', 'under_review', 'approved', 'rejected', 'revision_required') DEFAULT 'pending',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    
    -- Review details
    reviewed_by INT NULL,
    reviewed_at TIMESTAMP NULL,
    review_notes TEXT,
    rejection_reason TEXT,
    
    -- Timestamps
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    due_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign Keys
    FOREIGN KEY (document_type_id) REFERENCES document_types(id) ON DELETE CASCADE,
    FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS document_review_comments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    document_id INT NOT NULL,
    user_id INT NOT NULL,
    comment TEXT NOT NULL,
    is_internal BOOLEAN DEFAULT FALSE, -- Internal comments only visible to reviewers
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (document_id) REFERENCES document_submissions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert default document types
INSERT INTO document_types (name, description, required_for_role) VALUES
('Lesson Plan', 'Weekly or daily lesson plans for review', 'teacher'),
('Student Report', 'Academic progress and behavior reports', 'teacher'),
('Curriculum Proposal', 'New curriculum or course proposals', 'academic_coordinator'),
('Assessment Results', 'Student assessment and evaluation results', 'teacher'),
('Incident Report', 'Disciplinary or behavioral incident documentation', 'guidance'),
('Academic Policy', 'Academic policies and procedures for review', 'admin'),
('Budget Proposal', 'Financial budget requests and proposals', 'accounting'),
('Research Paper', 'Student research submissions for review', 'student'),
('Project Documentation', 'Student project submissions', 'student'),
('Enrollment Documents', 'Student enrollment and registration documents', 'registrar');

-- Add indexes for better performance
CREATE INDEX idx_document_status ON document_submissions(status);
CREATE INDEX idx_document_priority ON document_submissions(priority);
CREATE INDEX idx_document_submitted_by ON document_submissions(submitted_by);
CREATE INDEX idx_document_reviewed_by ON document_submissions(reviewed_by);
CREATE INDEX idx_document_due_date ON document_submissions(due_date);
CREATE INDEX idx_document_type ON document_submissions(document_type_id);
