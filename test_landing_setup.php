<?php
// Simple test script to verify landing page setup
require_once 'includes/config.php';

echo "<h2>Testing Landing Page Setup</h2>";

try {
    // Test if tables exist
    $stmt = $pdo->query("SHOW TABLES LIKE 'landing_page_content'");
    $landingTableExists = $stmt->rowCount() > 0;
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'announcements'");
    $announcementsTableExists = $stmt->rowCount() > 0;
    
    echo "<p><strong>Database Tables:</strong></p>";
    echo "<p>landing_page_content: " . ($landingTableExists ? "✅ EXISTS" : "❌ MISSING") . "</p>";
    echo "<p>announcements: " . ($announcementsTableExists ? "✅ EXISTS" : "❌ MISSING") . "</p>";
    
    if (!$landingTableExists || !$announcementsTableExists) {
        echo "<p><strong>Creating missing tables...</strong></p>";
        
        // Create landing_page_content table
        if (!$landingTableExists) {
            $sql = "CREATE TABLE landing_page_content (
                id INT AUTO_INCREMENT PRIMARY KEY,
                section_name VARCHAR(50) NOT NULL UNIQUE,
                title VARCHAR(255) NOT NULL,
                subtitle VARCHAR(255),
                content TEXT,
                image_url VARCHAR(500),
                button_text VARCHAR(100),
                button_url VARCHAR(255),
                is_active BOOLEAN DEFAULT TRUE,
                display_order INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )";
            $pdo->exec($sql);
            echo "<p>✅ Created landing_page_content table</p>";
        }
        
        // Create announcements table
        if (!$announcementsTableExists) {
            $sql = "CREATE TABLE announcements (
                id INT AUTO_INCREMENT PRIMARY KEY,
                message TEXT NOT NULL,
                is_active BOOLEAN DEFAULT TRUE,
                display_order INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )";
            $pdo->exec($sql);
            echo "<p>✅ Created announcements table</p>";
        }
        
        // Insert default content
        $defaultContent = [
            ['hero', 'Excellence in Education', 'Empowering minds for a brighter future with modern technology and dedicated faculty', '', '', 'Login to Portal', 'auth/login.php', 1],
            ['about', 'About EduManage', '', 'EduManage is a comprehensive school management system designed to streamline educational processes and enhance communication between all stakeholders. Our platform brings together students, teachers, parents, and administrators in a unified digital environment that promotes collaboration, transparency, and academic excellence.', '', '', '', 2],
            ['elementary', 'Elementary', '', 'Foundation years focusing on basic literacy, numeracy, and character development through engaging and interactive learning methods.', '', '', '', 3],
            ['junior_high', 'Junior High School', '', 'Comprehensive curriculum preparing students for senior high school with emphasis on critical thinking and problem-solving skills.', '', '', '', 4],
            ['senior_high', 'Senior High School', '', 'Specialized tracks including STEM, ABM, HUMSS, and TVL to prepare students for college and career readiness.', '', '', '', 5]
        ];
        
        $stmt = $pdo->prepare("INSERT IGNORE INTO landing_page_content (section_name, title, subtitle, content, image_url, button_text, button_url, display_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($defaultContent as $content) {
            $stmt->execute($content);
        }
        
        // Insert default announcements
        $defaultAnnouncements = [
            'Enrollment for School Year 2024-2025 is now open! Apply online today.',
            'New STEM laboratory facilities now available for all students.',
            'Parent-Teacher Conference scheduled for March 15-16, 2024.',
            'School Achievement Awards ceremony on April 20, 2024.'
        ];
        
        $stmt = $pdo->prepare("INSERT IGNORE INTO announcements (message, display_order) VALUES (?, ?)");
        
        foreach ($defaultAnnouncements as $index => $announcement) {
            $stmt->execute([$announcement, $index + 1]);
        }
        
        echo "<p>✅ Inserted default content</p>";
    }
    
    // Test data retrieval
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM landing_page_content");
    $contentCount = $stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM announcements");
    $announcementCount = $stmt->fetch()['count'];
    
    echo "<p><strong>Data Check:</strong></p>";
    echo "<p>Landing page sections: {$contentCount}</p>";
    echo "<p>Announcements: {$announcementCount}</p>";
    
    echo "<h3>✅ Landing Page System Ready!</h3>";
    echo "<p><a href='admin/landing-page-management.php'>Go to Admin Interface</a></p>";
    echo "<p><a href='index.php'>View Landing Page</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Database Error: " . $e->getMessage() . "</p>";
}
?>
