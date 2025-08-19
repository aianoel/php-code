-- Enrollment Applications Table for Workflow Management
-- This table tracks new student enrollment applications through different stages

CREATE TABLE IF NOT EXISTS enrollment_applications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    application_number VARCHAR(20) UNIQUE NOT NULL,
    
    -- Student Information
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    date_of_birth DATE NOT NULL,
    gender ENUM('male', 'female', 'other') NOT NULL,
    address TEXT,
    
    -- Academic Information
    grade_level VARCHAR(10) NOT NULL,
    strand VARCHAR(20),
    previous_school VARCHAR(100),
    gpa DECIMAL(3,2),
    
    -- Parent/Guardian Information
    parent_name VARCHAR(100),
    parent_email VARCHAR(100),
    parent_phone VARCHAR(20),
    parent_address TEXT,
    
    -- Application Status and Workflow
    status ENUM('pending', 'registrar_review', 'accounting_review', 'admin_approval', 'approved', 'rejected', 'enrolled') DEFAULT 'pending',
    
    -- Payment Information
    payment_status ENUM('pending', 'partial', 'paid', 'overdue') DEFAULT 'pending',
    total_fees DECIMAL(10,2) DEFAULT 0.00,
    paid_amount DECIMAL(10,2) DEFAULT 0.00,
    
    -- Document Status
    documents_submitted BOOLEAN DEFAULT FALSE,
    documents_verified BOOLEAN DEFAULT FALSE,
    
    -- Workflow Tracking
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    registrar_reviewed_at TIMESTAMP NULL,
    registrar_reviewed_by INT NULL,
    accounting_reviewed_at TIMESTAMP NULL,
    accounting_reviewed_by INT NULL,
    admin_approved_at TIMESTAMP NULL,
    admin_approved_by INT NULL,
    
    -- Notes and Comments
    registrar_notes TEXT,
    accounting_notes TEXT,
    admin_notes TEXT,
    rejection_reason TEXT,
    
    -- System Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign Keys
    FOREIGN KEY (registrar_reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (accounting_reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (admin_approved_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Add index for better performance
CREATE INDEX idx_enrollment_status ON enrollment_applications(status);
CREATE INDEX idx_enrollment_grade ON enrollment_applications(grade_level);
CREATE INDEX idx_enrollment_submitted ON enrollment_applications(submitted_at);
