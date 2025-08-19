<?php
// Setup accounting tables and add sample data
require_once 'includes/config.php';

echo "<h1>Setting up Accounting Tables</h1>";
echo "<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px;'>";

// Check database connection
try {
    $pdo->query("SELECT 1");
    echo "<p style='color: green;'>✅ Database connection successful</p>";
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Database connection failed: " . $e->getMessage() . "</p>";
    exit;
}

// Create invoices table
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS invoices (
            id INT PRIMARY KEY AUTO_INCREMENT,
            invoice_number VARCHAR(20) UNIQUE NOT NULL,
            student_id INT NOT NULL,
            description TEXT NOT NULL,
            amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            status ENUM('pending', 'paid', 'partial', 'overdue', 'cancelled') DEFAULT 'pending',
            payment_method VARCHAR(50),
            due_date DATE NOT NULL,
            paid_date DATE NULL,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
        )
    ");
    
    // Create indexes
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_invoice_status ON invoices(status)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_invoice_student ON invoices(student_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_invoice_due_date ON invoices(due_date)");
    
    echo "✅ Invoices table created successfully.<br>";
} catch (PDOException $e) {
    echo "❌ Error creating invoices table: " . $e->getMessage() . "<br>";
}

// Add payment columns to enrollment_applications if they don't exist
try {
    // Check if columns exist
    $stmt = $pdo->query("DESCRIBE enrollment_applications");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('payment_status', $columns)) {
        $pdo->exec("ALTER TABLE enrollment_applications ADD COLUMN payment_status ENUM('pending', 'partial', 'paid', 'overdue') DEFAULT 'pending'");
        echo "✅ Added payment_status column to enrollment_applications.<br>";
    }
    
    if (!in_array('total_fees', $columns)) {
        $pdo->exec("ALTER TABLE enrollment_applications ADD COLUMN total_fees DECIMAL(10,2) DEFAULT 0.00");
        echo "✅ Added total_fees column to enrollment_applications.<br>";
    }
    
    if (!in_array('paid_amount', $columns)) {
        $pdo->exec("ALTER TABLE enrollment_applications ADD COLUMN paid_amount DECIMAL(10,2) DEFAULT 0.00");
        echo "✅ Added paid_amount column to enrollment_applications.<br>";
    }
} catch (PDOException $e) {
    echo "❌ Error adding payment columns: " . $e->getMessage() . "<br>";
}

// Add sample data to invoices table
try {
    // First check if students table exists and has data
    $studentCheck = $pdo->query("SELECT COUNT(*) FROM students");
    $hasStudents = $studentCheck->fetchColumn() > 0;
    
    if ($hasStudents) {
        // Get some student IDs to use for sample invoices
        $studentStmt = $pdo->query("SELECT id FROM students LIMIT 5");
        $studentIds = $studentStmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($studentIds)) {
            // Check if we already have invoices
            $invoiceCheck = $pdo->query("SELECT COUNT(*) FROM invoices");
            $hasInvoices = $invoiceCheck->fetchColumn() > 0;
            
            if (!$hasInvoices) {
                // Insert sample invoices
                $insertStmt = $pdo->prepare("
                    INSERT INTO invoices 
                    (invoice_number, student_id, description, amount, status, payment_method, due_date, paid_date) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $descriptions = [
                    'Tuition Fee - First Semester',
                    'Miscellaneous Fees',
                    'Laboratory Fee',
                    'Library Fee',
                    'Technology Fee'
                ];
                
                $statuses = ['paid', 'pending', 'partial', 'overdue'];
                $paymentMethods = ['Cash', 'Bank Transfer', 'Credit Card', 'Online Payment'];
                
                for ($i = 1; $i <= 20; $i++) {
                    $studentId = $studentIds[array_rand($studentIds)];
                    $amount = rand(1000, 10000);
                    $status = $statuses[array_rand($statuses)];
                    $paymentMethod = $status != 'pending' ? $paymentMethods[array_rand($paymentMethods)] : null;
                    $dueDate = date('Y-m-d', strtotime('+' . rand(1, 30) . ' days'));
                    $paidDate = $status == 'paid' ? date('Y-m-d', strtotime('-' . rand(1, 10) . ' days')) : null;
                    
                    $insertStmt->execute([
                        'INV-' . str_pad($i, 5, '0', STR_PAD_LEFT),
                        $studentId,
                        $descriptions[array_rand($descriptions)],
                        $amount,
                        $status,
                        $paymentMethod,
                        $dueDate,
                        $paidDate
                    ]);
                }
                
                echo "✅ Sample invoice data added.<br>";
            } else {
                echo "ℹ️ Invoices table already has data. No sample data added.<br>";
            }
        } else {
            echo "⚠️ No student IDs found. Sample invoice data not added.<br>";
        }
    } else {
        echo "⚠️ Students table not found or empty. Sample invoice data not added.<br>";
    }
} catch (PDOException $e) {
    echo "❌ Error adding sample invoice data: " . $e->getMessage() . "<br>";
}

// Update enrollment applications with payment data
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM enrollment_applications");
    $hasApplications = $stmt->fetchColumn() > 0;
    
    if ($hasApplications) {
        // Update applications with random payment data
        $pdo->exec("
            UPDATE enrollment_applications 
            SET 
                total_fees = FLOOR(RAND() * 10000) + 5000,
                payment_status = ELT(FLOOR(RAND() * 4) + 1, 'pending', 'partial', 'paid', 'overdue')
        ");
        
        // Update paid amounts based on payment status
        $pdo->exec("
            UPDATE enrollment_applications 
            SET paid_amount = 
                CASE 
                    WHEN payment_status = 'paid' THEN total_fees 
                    WHEN payment_status = 'partial' THEN FLOOR(total_fees * 0.5) 
                    ELSE 0 
                END
        ");
        
        echo "✅ Updated enrollment applications with payment data.<br>";
    } else {
        echo "⚠️ No enrollment applications found.<br>";
    }
} catch (PDOException $e) {
    echo "❌ Error updating enrollment applications: " . $e->getMessage() . "<br>";
}

echo "<br>✅ All accounting tables setup completed. You can now access the accounting pages with actual data.";
echo "<br><br><a href='accounting/index.php'>Go to Accounting Dashboard</a>";
?>
