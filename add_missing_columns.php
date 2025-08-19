<?php
/**
 * Database Fix Script - Add Missing Columns
 * 
 * This script adds missing columns to the subjects and enrollment_applications tables
 * to fix errors in the admin section.
 */
require_once 'includes/config.php';

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Database Fix - Add Missing Columns</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f7fb;
        }
        h1, h2 { 
            color: #1e293b;
        }
        .card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        .success {
            color: #16a34a;
            background: #dcfce7;
            padding: 10px;
            border-radius: 4px;
            margin: 5px 0;
        }
        .error {
            color: #dc2626;
            background: #fee2e2;
            padding: 10px;
            border-radius: 4px;
            margin: 5px 0;
        }
        .info {
            color: #2563eb;
            background: #dbeafe;
            padding: 10px;
            border-radius: 4px;
            margin: 5px 0;
        }
        .btn {
            display: inline-block;
            background: #2563eb;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 15px;
        }
        .btn-secondary {
            background: #64748b;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        table, th, td {
            border: 1px solid #e2e8f0;
        }
        th, td {
            padding: 8px 12px;
            text-align: left;
        }
        th {
            background: #f1f5f9;
        }
    </style>
</head>
<body>
    <h1>Database Fix - Add Missing Columns</h1>
    <div class='card'>";

try {
    // Fix subjects table
    echo "<h2>Checking subjects table...</h2>";
    
    // Check if units column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM subjects LIKE 'units'");
    $unitsExists = $stmt->rowCount() > 0;
    
    if (!$unitsExists) {
        $pdo->exec("ALTER TABLE subjects ADD COLUMN units INT DEFAULT 1");
        echo "<div class='success'>✅ Added 'units' column to subjects table</div>";
    } else {
        echo "<div class='info'>ℹ️ 'units' column already exists in subjects table</div>";
    }
    
    // Check if department column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM subjects LIKE 'department'");
    $departmentExists = $stmt->rowCount() > 0;
    
    if (!$departmentExists) {
        $pdo->exec("ALTER TABLE subjects ADD COLUMN department VARCHAR(50)");
        echo "<div class='success'>✅ Added 'department' column to subjects table</div>";
    } else {
        echo "<div class='info'>ℹ️ 'department' column already exists in subjects table</div>";
    }
    
    // Update existing subjects with default values
    $pdo->exec("UPDATE subjects SET units = 1 WHERE units IS NULL");
    $pdo->exec("UPDATE subjects SET department = 'General' WHERE department IS NULL OR department = ''");
    echo "<div class='success'>✅ Updated existing subjects with default values</div>";
    
    // Fix enrollment_applications table
    echo "<h2>Checking enrollment_applications table...</h2>";
    
    // Check if registrar_reviewed_by column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM enrollment_applications LIKE 'registrar_reviewed_by'");
    $registrarReviewedByExists = $stmt->rowCount() > 0;
    
    if (!$registrarReviewedByExists) {
        $pdo->exec("ALTER TABLE enrollment_applications ADD COLUMN registrar_reviewed_by INT NULL");
        echo "<div class='success'>✅ Added 'registrar_reviewed_by' column to enrollment_applications table</div>";
    } else {
        echo "<div class='info'>ℹ️ 'registrar_reviewed_by' column already exists in enrollment_applications table</div>";
    }
    
    // Check if accounting_reviewed_by column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM enrollment_applications LIKE 'accounting_reviewed_by'");
    $accountingReviewedByExists = $stmt->rowCount() > 0;
    
    if (!$accountingReviewedByExists) {
        $pdo->exec("ALTER TABLE enrollment_applications ADD COLUMN accounting_reviewed_by INT NULL");
        echo "<div class='success'>✅ Added 'accounting_reviewed_by' column to enrollment_applications table</div>";
    } else {
        echo "<div class='info'>ℹ️ 'accounting_reviewed_by' column already exists in enrollment_applications table</div>";
    }
    
    // Check if admin_approved_by column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM enrollment_applications LIKE 'admin_approved_by'");
    $adminApprovedByExists = $stmt->rowCount() > 0;
    
    if (!$adminApprovedByExists) {
        $pdo->exec("ALTER TABLE enrollment_applications ADD COLUMN admin_approved_by INT NULL");
        echo "<div class='success'>✅ Added 'admin_approved_by' column to enrollment_applications table</div>";
    } else {
        echo "<div class='info'>ℹ️ 'admin_approved_by' column already exists in enrollment_applications table</div>";
    }
    
    // Add foreign key constraints if they don't exist
    try {
        $pdo->exec("ALTER TABLE enrollment_applications 
                   ADD CONSTRAINT fk_registrar_reviewed_by 
                   FOREIGN KEY (registrar_reviewed_by) REFERENCES users(id) ON DELETE SET NULL");
        echo "<div class='success'>✅ Added foreign key constraint for registrar_reviewed_by</div>";
    } catch (Exception $e) {
        echo "<div class='info'>ℹ️ Foreign key for registrar_reviewed_by already exists or could not be added</div>";
    }
    
    try {
        $pdo->exec("ALTER TABLE enrollment_applications 
                   ADD CONSTRAINT fk_accounting_reviewed_by 
                   FOREIGN KEY (accounting_reviewed_by) REFERENCES users(id) ON DELETE SET NULL");
        echo "<div class='success'>✅ Added foreign key constraint for accounting_reviewed_by</div>";
    } catch (Exception $e) {
        echo "<div class='info'>ℹ️ Foreign key for accounting_reviewed_by already exists or could not be added</div>";
    }
    
    try {
        $pdo->exec("ALTER TABLE enrollment_applications 
                   ADD CONSTRAINT fk_admin_approved_by 
                   FOREIGN KEY (admin_approved_by) REFERENCES users(id) ON DELETE SET NULL");
        echo "<div class='success'>✅ Added foreign key constraint for admin_approved_by</div>";
    } catch (Exception $e) {
        echo "<div class='info'>ℹ️ Foreign key for admin_approved_by already exists or could not be added</div>";
    }
    
    echo "<h2>Verification</h2>";
    
    // Verify subjects table
    echo "<h3>Subjects Table Structure</h3>";
    $stmt = $pdo->query("DESCRIBE subjects");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table>
            <tr>
                <th>Field</th>
                <th>Type</th>
                <th>Null</th>
                <th>Key</th>
                <th>Default</th>
            </tr>";
    
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Verify enrollment_applications table
    echo "<h3>Enrollment Applications Table Structure</h3>";
    $stmt = $pdo->query("DESCRIBE enrollment_applications");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table>
            <tr>
                <th>Field</th>
                <th>Type</th>
                <th>Null</th>
                <th>Key</th>
                <th>Default</th>
            </tr>";
    
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    echo "<div class='success' style='margin-top: 20px; font-weight: bold; font-size: 18px;'>
            ✅ Database update completed successfully!
          </div>";
    
} catch (Exception $e) {
    echo "<div class='error'>
            <strong>Error updating database:</strong> " . htmlspecialchars($e->getMessage()) . "
          </div>";
}

echo "</div>
    <div>
        <a href='admin/academic-setup.php' class='btn'>Go to Academic Setup</a>
        <a href='admin/enrollment-applications.php' class='btn'>Go to Enrollment Applications</a>
        <a href='admin/index.php' class='btn btn-secondary'>Admin Dashboard</a>
    </div>
</body>
</html>";
?>
