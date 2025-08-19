<?php
// Simple setup script to create landing page tables
require_once 'includes/config.php';

echo "<h1>Landing Page Database Setup</h1>";
echo "<style>body { font-family: Arial, sans-serif; margin: 20px; } .success { color: green; } .error { color: red; }</style>";

try {
    // Create landing_page_content table
    $sql = "CREATE TABLE IF NOT EXISTS landing_page_content (
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
    echo "<p class='success'>✅ Created landing_page_content table</p>";
    
    // Create announcements table
    $sql = "CREATE TABLE IF NOT EXISTS announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        message TEXT NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        display_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    $pdo->exec($sql);
    echo "<p class='success'>✅ Created announcements table</p>";
    
    // Create news table
    $sql = "CREATE TABLE IF NOT EXISTS news (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        excerpt TEXT,
        content TEXT,
        image_url VARCHAR(500),
        date DATE NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        display_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    $pdo->exec($sql);
    echo "<p class='success'>✅ Created news table</p>";
    
    // Create events table
    $sql = "CREATE TABLE IF NOT EXISTS events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        date DATE NOT NULL,
        time TIME,
        location VARCHAR(255),
        image_url VARCHAR(500),
        is_active BOOLEAN DEFAULT TRUE,
        display_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    $pdo->exec($sql);
    echo "<p class='success'>✅ Created events table</p>";
    
    // Insert default landing page content
    $defaultContent = [
        ['hero', 'Excellence in Education', 'Empowering minds for a brighter future with modern technology and dedicated faculty', '', '', 'Login to Portal', 'auth/login.php', 1],
        ['about', 'About EduManage', '', 'EduManage is a comprehensive school management system designed to streamline educational processes and enhance communication between all stakeholders. Our platform brings together students, teachers, parents, and administrators in a unified digital environment that promotes collaboration, transparency, and academic excellence.', '', '', '', 2],
        ['elementary', 'Elementary', '', 'Foundation years focusing on basic literacy, numeracy, and character development through engaging and interactive learning methods.', '', '', '', 3],
        ['junior_high', 'Junior High School', '', 'Comprehensive curriculum preparing students for senior high school with emphasis on critical thinking and problem-solving skills.', '', '', '', 4],
        ['senior_high', 'Senior High School', '', 'Specialized tracks including STEM, ABM, HUMSS, and TVL to prepare students for college and career readiness.', '', '', '', 5]
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO landing_page_content (section_name, title, subtitle, content, image_url, button_text, button_url, display_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
    $insertedContent = 0;
    foreach ($defaultContent as $content) {
        if ($stmt->execute($content)) {
            $insertedContent++;
        }
    }
    
    echo "<p class='success'>✅ Inserted {$insertedContent} default content sections</p>";
    
    // Insert default announcements
    $defaultAnnouncements = [
        'Enrollment for School Year 2024-2025 is now open! Apply online today.',
        'New STEM laboratory facilities now available for all students.',
        'Parent-Teacher Conference scheduled for March 15-16, 2024.',
        'School Achievement Awards ceremony on April 20, 2024.'
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO announcements (message, display_order) VALUES (?, ?)");
    
    $insertedAnnouncements = 0;
    foreach ($defaultAnnouncements as $index => $announcement) {
        if ($stmt->execute([$announcement, $index + 1])) {
            $insertedAnnouncements++;
        }
    }
    
    echo "<p class='success'>✅ Inserted {$insertedAnnouncements} default announcements</p>";
    
    // Insert default news
    $defaultNews = [
        ['New Science Laboratory Opens', 'State-of-the-art science facilities now available for student use.', 'Our new science laboratory features the latest equipment and technology to enhance student learning in physics, chemistry, and biology.', '2024-01-15', 1],
        ['Academic Excellence Awards', 'Celebrating our top-performing students and their achievements.', 'We are proud to recognize students who have demonstrated outstanding academic performance throughout the year.', '2024-01-10', 2],
        ['Sports Championship Victory', 'Our basketball team wins the regional championship tournament.', 'After months of training and dedication, our basketball team has achieved victory in the regional championship.', '2024-01-05', 3]
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO news (title, excerpt, content, date, display_order) VALUES (?, ?, ?, ?, ?)");
    
    $insertedNews = 0;
    foreach ($defaultNews as $article) {
        if ($stmt->execute($article)) {
            $insertedNews++;
        }
    }
    
    echo "<p class='success'>✅ Inserted {$insertedNews} default news articles</p>";
    
    // Insert default events
    $defaultEvents = [
        ['Science Fair 2024', 'Annual science fair showcasing student projects and innovations.', '2024-03-20', '09:00:00', 'Main Auditorium', 1],
        ['Career Guidance Seminar', 'Professional guidance for students planning their future careers.', '2024-03-25', '14:00:00', 'Conference Hall', 2],
        ['Arts Festival', 'Celebration of student creativity in music, dance, and visual arts.', '2024-04-10', '10:00:00', 'School Grounds', 3]
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO events (title, description, date, time, location, display_order) VALUES (?, ?, ?, ?, ?, ?)");
    
    $insertedEvents = 0;
    foreach ($defaultEvents as $event) {
        if ($stmt->execute($event)) {
            $insertedEvents++;
        }
    }
    
    echo "<p class='success'>✅ Inserted {$insertedEvents} default events</p>";
    
    // Verify table creation
    $stmt = $pdo->query("SHOW TABLES LIKE 'landing_page_content'");
    $landingExists = $stmt->rowCount() > 0;
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'announcements'");
    $announcementsExists = $stmt->rowCount() > 0;
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'news'");
    $newsExists = $stmt->rowCount() > 0;
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'events'");
    $eventsExists = $stmt->rowCount() > 0;
    
    echo "<h2>Database Status:</h2>";
    echo "<p>landing_page_content: " . ($landingExists ? "✅ EXISTS" : "❌ MISSING") . "</p>";
    echo "<p>announcements: " . ($announcementsExists ? "✅ EXISTS" : "❌ MISSING") . "</p>";
    echo "<p>news: " . ($newsExists ? "✅ EXISTS" : "❌ MISSING") . "</p>";
    echo "<p>events: " . ($eventsExists ? "✅ EXISTS" : "❌ MISSING") . "</p>";
    
    if ($landingExists && $announcementsExists && $newsExists && $eventsExists) {
        echo "<h2 class='success'>🎉 Setup Complete!</h2>";
        echo "<p><strong>You can now access:</strong></p>";
        echo "<ul>";
        echo "<li><a href='admin/landing-page-management.php'>Landing Page Management</a></li>";
        echo "<li><a href='admin/news-events-management.php'>News & Events Management</a></li>";
        echo "<li><a href='index.php'>View Landing Page</a></li>";
        echo "</ul>";
    } else {
        echo "<h2 class='error'>❌ Setup Incomplete</h2>";
        echo "<p>Some tables were not created successfully. Please check database permissions.</p>";
    }
    
} catch (PDOException $e) {
    echo "<p class='error'>❌ Database Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please check your database connection and permissions.</p>";
}
?>
