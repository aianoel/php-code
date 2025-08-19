<?php
require_once 'includes/config.php';

try {
    // Check if department column exists in subjects table
    $stmt = $pdo->query("DESCRIBE subjects");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('department', $columns)) {
        echo "❌ Department column missing in subjects table. Adding it...\n";
        
        // Add department column to subjects table
        $pdo->exec("ALTER TABLE subjects ADD COLUMN department VARCHAR(50) DEFAULT 'General' AFTER description");
        echo "✅ Department column added to subjects table.\n";
    } else {
        echo "✅ Department column already exists in subjects table.\n";
    }
    
    // Update existing subjects with default department values
    $stmt = $pdo->prepare("UPDATE subjects SET department = ? WHERE department IS NULL OR department = ''");
    $stmt->execute(['General']);
    echo "✅ Updated existing subjects with default department.\n";
    
    // Add some sample departments if subjects table is empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM subjects");
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        echo "📝 Adding sample subjects with departments...\n";
        
        $sample_subjects = [
            ['MATH101', 'Mathematics I', 'Basic Mathematics', 3, 3, 'Mathematics'],
            ['ENG101', 'English I', 'Basic English', 3, 3, 'English'],
            ['SCI101', 'Science I', 'Basic Science', 3, 3, 'Science'],
            ['HIST101', 'History I', 'Basic History', 3, 3, 'Social Studies'],
            ['PE101', 'Physical Education', 'Physical Education', 2, 2, 'Physical Education'],
            ['ART101', 'Arts', 'Basic Arts', 2, 2, 'Arts'],
            ['COMP101', 'Computer Science', 'Basic Programming', 3, 3, 'Technology']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO subjects (code, name, description, credits, units, department) VALUES (?, ?, ?, ?, ?, ?)");
        
        foreach ($sample_subjects as $subject) {
            $stmt->execute($subject);
        }
        
        echo "✅ Added " . count($sample_subjects) . " sample subjects.\n";
    }
    
    // Verify the fix
    $stmt = $pdo->query("SELECT code, name, department FROM subjects LIMIT 5");
    $subjects = $stmt->fetchAll();
    
    echo "\n📋 Sample subjects with departments:\n";
    foreach ($subjects as $subject) {
        echo "- {$subject['code']}: {$subject['name']} ({$subject['department']})\n";
    }
    
    echo "\n✅ Department column issue fixed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error fixing department column: " . $e->getMessage() . "\n";
}
?>
