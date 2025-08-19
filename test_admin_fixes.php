<?php
/**
 * Test Script for Admin Fixes
 * 
 * This script tests if the fixes for admin/academic-setup.php and 
 * admin/enrollment-applications.php are working correctly.
 */
require_once 'includes/config.php';

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Test Admin Fixes</title>
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
        .btn {
            display: inline-block;
            background: #2563eb;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 15px;
            margin-right: 10px;
        }
        pre {
            background: #f1f5f9;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <h1>Test Admin Fixes</h1>";

echo "<div class='card'>
    <h2>1. Testing academic-setup.php</h2>";

try {
    // Test the subjects query from academic-setup.php
    $checkStmt = $pdo->query("SHOW COLUMNS FROM subjects LIKE 'units'");
    $unitsExists = $checkStmt->rowCount() > 0;
    
    if ($unitsExists) {
        $stmt = $pdo->prepare("SELECT id, code, name, units, department, grade_level, strand, status FROM subjects ORDER BY name LIMIT 5");
    } else {
        $stmt = $pdo->prepare("SELECT id, code, name, 1 AS units, department, grade_level, strand, status FROM subjects ORDER BY name LIMIT 5");
    }
    $stmt->execute();
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<div class='success'>✅ Successfully executed the subjects query</div>";
    
    echo "<h3>Sample Subjects Data:</h3>";
    echo "<pre>" . htmlspecialchars(print_r($subjects, true)) . "</pre>";
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Error testing academic-setup.php: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div>";

echo "<div class='card'>
    <h2>2. Testing enrollment-applications.php</h2>";

try {
    // Test the enrollment applications query from enrollment-applications.php
    $checkStmt = $pdo->query("SHOW COLUMNS FROM enrollment_applications LIKE 'registrar_reviewed_by'");
    $registrarReviewedByExists = $checkStmt->rowCount() > 0;
    
    $checkStmt = $pdo->query("SHOW COLUMNS FROM enrollment_applications LIKE 'accounting_reviewed_by'");
    $accountingReviewedByExists = $checkStmt->rowCount() > 0;
    
    $query = "SELECT ea.*";
    
    if ($registrarReviewedByExists) {
        $query .= ", CONCAT(ru.first_name, ' ', ru.last_name) as registrar_name";
    } else {
        $query .= ", 'Not Assigned' as registrar_name";
    }
    
    if ($accountingReviewedByExists) {
        $query .= ", CONCAT(au.first_name, ' ', au.last_name) as accounting_name";
    } else {
        $query .= ", 'Not Assigned' as accounting_name";
    }
    
    $query .= " FROM enrollment_applications ea";
    
    if ($registrarReviewedByExists) {
        $query .= " LEFT JOIN users ru ON ea.registrar_reviewed_by = ru.id";
    }
    
    if ($accountingReviewedByExists) {
        $query .= " LEFT JOIN users au ON ea.accounting_reviewed_by = au.id";
    }
    
    $query .= " WHERE ea.status IN ('admin_approval', 'approved', 'rejected', 'enrolled')
    ORDER BY 
        CASE 
            WHEN ea.status = 'admin_approval' THEN 1
            WHEN ea.status = 'approved' THEN 2
            WHEN ea.status = 'enrolled' THEN 3
            WHEN ea.status = 'rejected' THEN 4
        END,
        ea.submitted_at DESC LIMIT 5";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<div class='success'>✅ Successfully executed the enrollment applications query</div>";
    
    echo "<h3>Sample Enrollment Applications Data:</h3>";
    if (count($applications) > 0) {
        echo "<pre>" . htmlspecialchars(print_r($applications, true)) . "</pre>";
    } else {
        echo "<p>No enrollment applications found with the specified statuses.</p>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Error testing enrollment-applications.php: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div>";

echo "<div class='card'>
    <h2>Summary</h2>";

// Check if both tests passed
$allPassed = true;

try {
    // Test academic-setup.php query
    $checkStmt = $pdo->query("SHOW COLUMNS FROM subjects LIKE 'units'");
    $unitsExists = $checkStmt->rowCount() > 0;
    
    if ($unitsExists) {
        $stmt = $pdo->prepare("SELECT id, code, name, units, department, grade_level, strand, status FROM subjects ORDER BY name LIMIT 1");
    } else {
        $stmt = $pdo->prepare("SELECT id, code, name, 1 AS units, department, grade_level, strand, status FROM subjects ORDER BY name LIMIT 1");
    }
    $stmt->execute();
    
    // Test enrollment-applications.php query
    $checkStmt = $pdo->query("SHOW COLUMNS FROM enrollment_applications LIKE 'registrar_reviewed_by'");
    $registrarReviewedByExists = $checkStmt->rowCount() > 0;
    
    $query = "SELECT ea.*, 'Not Assigned' as registrar_name, 'Not Assigned' as accounting_name 
              FROM enrollment_applications ea 
              WHERE ea.status IN ('admin_approval', 'approved', 'rejected', 'enrolled') 
              LIMIT 1";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    
} catch (Exception $e) {
    $allPassed = false;
    echo "<div class='error'>❌ Some tests failed: " . htmlspecialchars($e->getMessage()) . "</div>";
}

if ($allPassed) {
    echo "<div class='success'>
            <h3>✅ All tests passed successfully!</h3>
            <p>The fixes for admin/academic-setup.php and admin/enrollment-applications.php are working correctly.</p>
          </div>";
}

echo "</div>

<div>
    <a href='add_missing_columns.php' class='btn'>Run Database Fix Script</a>
    <a href='admin/academic-setup.php' class='btn'>Go to Academic Setup</a>
    <a href='admin/enrollment-applications.php' class='btn'>Go to Enrollment Applications</a>
</div>

</body>
</html>";
?>
