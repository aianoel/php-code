<?php
/**
 * Database Migration Script for SchoolEnroll-1
 * 
 * This script helps migrate your local database to Hostinger.
 * It handles:
 * 1. Exporting your local database
 * 2. Connecting to Hostinger database
 * 3. Importing data with conflict resolution
 */

// Set maximum execution time to 5 minutes for large databases
ini_set('max_execution_time', 300);
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Step 1: Define database connections
$local = [
    'host' => 'localhost',
    'db_name' => 'school_enrollment', // Update this if your local DB name is different
    'username' => 'root', // Update with your local MySQL username
    'password' => '', // Update with your local MySQL password
    'charset' => 'utf8mb4'
];

$hostinger = [
    'host' => 'localhost',
    'db_name' => 'u870495195_admission',
    'username' => 'u870495195_admission',
    'password' => '8uJs293cjJB',
    'charset' => 'utf8mb4'
];

// Step 2: Connect to both databases
try {
    // Local connection
    $local_dsn = "mysql:host={$local['host']};dbname={$local['db_name']};charset={$local['charset']}";
    $local_options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $local_pdo = new PDO($local_dsn, $local['username'], $local['password'], $local_options);
    
    // Hostinger connection
    $hostinger_dsn = "mysql:host={$hostinger['host']};dbname={$hostinger['db_name']};charset={$hostinger['charset']}";
    $hostinger_options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $hostinger_pdo = new PDO($hostinger_dsn, $hostinger['username'], $hostinger['password'], $hostinger_options);
    
    echo "✅ Connected to both databases successfully!\n";
} catch (PDOException $e) {
    die("❌ Database connection failed: " . $e->getMessage() . "\n");
}

// Step 3: Get all tables from local database
$tables = [];
try {
    $stmt = $local_pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    echo "📋 Found " . count($tables) . " tables in local database.\n";
} catch (PDOException $e) {
    die("❌ Failed to get tables: " . $e->getMessage() . "\n");
}

// Step 4: Process each table
foreach ($tables as $table) {
    echo "\n🔄 Processing table: $table\n";
    
    // Step 4.1: Check if table exists in Hostinger
    try {
        $stmt = $hostinger_pdo->query("SHOW TABLES LIKE '$table'");
        $table_exists = $stmt->rowCount() > 0;
        
        if (!$table_exists) {
            echo "  ➕ Table '$table' doesn't exist in Hostinger database. Creating...\n";
            
            // Get table creation SQL from local
            $stmt = $local_pdo->query("SHOW CREATE TABLE `$table`");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $create_table_sql = $row['Create Table'];
            
            // Create table in Hostinger
            $hostinger_pdo->exec($create_table_sql);
            echo "  ✅ Table '$table' created in Hostinger database.\n";
        } else {
            echo "  ℹ️ Table '$table' already exists in Hostinger database.\n";
        }
        
        // Step 4.2: Get data from local table
        $stmt = $local_pdo->query("SELECT * FROM `$table`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $row_count = count($rows);
        
        if ($row_count > 0) {
            echo "  📊 Found $row_count rows in local '$table' table.\n";
            
            // Step 4.3: Get column names
            $columns = array_keys($rows[0]);
            $column_list = "`" . implode("`, `", $columns) . "`";
            
            // Step 4.4: Insert data into Hostinger table with conflict resolution
            $inserted = 0;
            $updated = 0;
            $skipped = 0;
            
            foreach ($rows as $row) {
                // Check if row already exists in Hostinger
                $primary_key = getPrimaryKey($local_pdo, $table);
                
                if ($primary_key && isset($row[$primary_key])) {
                    $check_sql = "SELECT COUNT(*) FROM `$table` WHERE `$primary_key` = :pk_value";
                    $check_stmt = $hostinger_pdo->prepare($check_sql);
                    $check_stmt->execute(['pk_value' => $row[$primary_key]]);
                    $exists = $check_stmt->fetchColumn() > 0;
                    
                    if ($exists) {
                        // Update existing row
                        $update_parts = [];
                        $update_values = [];
                        
                        foreach ($columns as $column) {
                            if ($column != $primary_key) {
                                $update_parts[] = "`$column` = :$column";
                                $update_values[$column] = $row[$column];
                            }
                        }
                        
                        if (!empty($update_parts)) {
                            $update_sql = "UPDATE `$table` SET " . implode(", ", $update_parts) . 
                                         " WHERE `$primary_key` = :pk_value";
                            $update_values['pk_value'] = $row[$primary_key];
                            
                            $update_stmt = $hostinger_pdo->prepare($update_sql);
                            $update_stmt->execute($update_values);
                            $updated++;
                        }
                    } else {
                        // Insert new row
                        $placeholders = ":" . implode(", :", $columns);
                        $insert_sql = "INSERT INTO `$table` ($column_list) VALUES ($placeholders)";
                        $insert_stmt = $hostinger_pdo->prepare($insert_sql);
                        $insert_stmt->execute($row);
                        $inserted++;
                    }
                } else {
                    // No primary key or value, try direct insert with IGNORE
                    try {
                        $placeholders = ":" . implode(", :", $columns);
                        $insert_sql = "INSERT IGNORE INTO `$table` ($column_list) VALUES ($placeholders)";
                        $insert_stmt = $hostinger_pdo->prepare($insert_sql);
                        $insert_stmt->execute($row);
                        $inserted++;
                    } catch (PDOException $e) {
                        // Skip if insert fails
                        $skipped++;
                    }
                }
            }
            
            echo "  ✅ Processed data for '$table': $inserted inserted, $updated updated, $skipped skipped.\n";
        } else {
            echo "  ℹ️ No data found in local '$table' table.\n";
        }
        
    } catch (PDOException $e) {
        echo "  ❌ Error processing table '$table': " . $e->getMessage() . "\n";
    }
}

echo "\n✅ Database migration completed!\n";

/**
 * Helper function to get the primary key of a table
 */
function getPrimaryKey($pdo, $table) {
    try {
        $stmt = $pdo->query("SHOW KEYS FROM `$table` WHERE Key_name = 'PRIMARY'");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['Column_name'] : null;
    } catch (PDOException $e) {
        return null;
    }
}
?>
