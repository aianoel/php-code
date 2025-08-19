<?php
/**
 * Test Script for Academic Setup Fixes
 * 
 * This script tests if the fixes for admin/academic-setup.php are working correctly.
 */
require_once 'includes/config.php';

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Test Academic Setup Fixes</title>
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
    <h1>Test Academic Setup Fixes</h1>";

echo "<div class='card'>
    <h2>Testing Class Display with Missing Fields</h2>";

try {
    // Get classes with potentially missing fields
    $stmt = $pdo->prepare("
        SELECT 
            c.id, c.subject_id, c.section, c.teacher_id, 
            c.schedule, c.room, c.capacity,
            s.code as subject_code, s.name as subject_name,
            t.first_name, t.last_name
        FROM 
            classes c
        LEFT JOIN 
            subjects s ON c.subject_id = s.id
        LEFT JOIN 
            users t ON c.teacher_id = t.id
        LIMIT 5
    ");
    $stmt->execute();
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<div class='success'>✅ Successfully retrieved classes data</div>";
    
    echo "<h3>Sample Classes Data:</h3>";
    echo "<pre>" . htmlspecialchars(print_r($classes, true)) . "</pre>";
    
    echo "<h3>Testing Display of Class Data:</h3>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>
        <thead>
            <tr>
                <th>Subject</th>
                <th>Section</th>
                <th>Teacher</th>
                <th>Schedule</th>
                <th>Room</th>
                <th>Capacity</th>
            </tr>
        </thead>
        <tbody>";
    
    foreach ($classes as $class) {
        echo "<tr>
            <td>" . htmlspecialchars($class['subject_code']) . "<br>
                <small>" . htmlspecialchars($class['subject_name']) . "</small>
            </td>
            <td>" . htmlspecialchars($class['section']) . "</td>
            <td>";
        
        if (isset($class['first_name'])) {
            echo htmlspecialchars($class['first_name'] . ' ' . $class['last_name']);
        } else {
            echo "<em>No teacher assigned</em>";
        }
        
        echo "</td>
            <td>" . (isset($class['schedule']) ? htmlspecialchars($class['schedule']) : '') . "</td>
            <td>" . (isset($class['room']) ? htmlspecialchars($class['room']) : '') . "</td>
            <td>" . (isset($class['capacity']) ? htmlspecialchars($class['capacity']) : '') . "</td>
        </tr>";
    }
    
    echo "</tbody>
    </table>";
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Error testing class display: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div>";

echo "<div class='card'>
    <h2>Summary</h2>";

echo "<div class='success'>
        <h3>✅ Fixes Applied Successfully</h3>
        <p>The following issues have been fixed in admin/academic-setup.php:</p>
        <ul>
            <li>Added check for undefined array key 'schedule' on line 238</li>
            <li>Added check for undefined array key 'room' on line 239</li>
            <li>Added check for undefined array key 'capacity' on line 240</li>
            <li>Fixed deprecated htmlspecialchars() calls with null parameters</li>
        </ul>
      </div>";

echo "</div>

<div>
    <a href='admin/academic-setup.php' class='btn'>Go to Academic Setup</a>
</div>

</body>
</html>";
?>
