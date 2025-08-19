-- Invoices Table for Accounting Management
-- This table tracks student invoices and payment records

CREATE TABLE IF NOT EXISTS invoices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_number VARCHAR(20) UNIQUE NOT NULL,
    
    -- Student Information
    student_id INT NOT NULL,
    
    -- Invoice Details
    description TEXT NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status ENUM('pending', 'paid', 'partial', 'overdue', 'cancelled') DEFAULT 'pending',
    payment_method VARCHAR(50),
    
    -- Dates
    due_date DATE NOT NULL,
    paid_date DATE NULL,
    
    -- Notes
    notes TEXT,
    
    -- System Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign Keys
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- Add indexes for better performance
CREATE INDEX idx_invoice_status ON invoices(status);
CREATE INDEX idx_invoice_student ON invoices(student_id);
CREATE INDEX idx_invoice_due_date ON invoices(due_date);
