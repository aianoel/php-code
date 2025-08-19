<?php
require_once 'includes/config.php';

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
    echo "Landing page content table created successfully.\n";
    
    // Insert default content
    $defaultContent = [
        [
            'section_name' => 'hero',
            'title' => 'Excellence in Education',
            'subtitle' => 'Empowering minds for a brighter future with modern technology and dedicated faculty',
            'content' => '',
            'image_url' => '',
            'button_text' => 'Login to Portal',
            'button_url' => 'auth/login.php',
            'display_order' => 1
        ],
        [
            'section_name' => 'about',
            'title' => 'About EduManage',
            'subtitle' => '',
            'content' => 'EduManage is a comprehensive school management system designed to streamline educational processes and enhance communication between all stakeholders. Our platform brings together students, teachers, parents, and administrators in a unified digital environment that promotes collaboration, transparency, and academic excellence.',
            'image_url' => '',
            'button_text' => '',
            'button_url' => '',
            'display_order' => 2
        ],
        [
            'section_name' => 'elementary',
            'title' => 'Elementary',
            'subtitle' => '',
            'content' => 'Foundation years focusing on basic literacy, numeracy, and character development through engaging and interactive learning methods.',
            'image_url' => '',
            'button_text' => '',
            'button_url' => '',
            'display_order' => 3
        ],
        [
            'section_name' => 'junior_high',
            'title' => 'Junior High School',
            'subtitle' => '',
            'content' => 'Comprehensive curriculum preparing students for senior high school with emphasis on critical thinking and problem-solving skills.',
            'image_url' => '',
            'button_text' => '',
            'button_url' => '',
            'display_order' => 4
        ],
        [
            'section_name' => 'senior_high',
            'title' => 'Senior High School',
            'subtitle' => '',
            'content' => 'Specialized tracks including STEM, ABM, HUMSS, and TVL to prepare students for college and career readiness.',
            'image_url' => '',
            'button_text' => '',
            'button_url' => '',
            'display_order' => 5
        ]
    ];
    
    $stmt = $pdo->prepare("INSERT INTO landing_page_content (section_name, title, subtitle, content, image_url, button_text, button_url, display_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($defaultContent as $content) {
        $stmt->execute([
            $content['section_name'],
            $content['title'],
            $content['subtitle'],
            $content['content'],
            $content['image_url'],
            $content['button_text'],
            $content['button_url'],
            $content['display_order']
        ]);
    }
    
    echo "Default landing page content inserted successfully.\n";
    
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
    echo "Announcements table created successfully.\n";
    
    // Insert default announcements
    $defaultAnnouncements = [
        'Enrollment for School Year 2024-2025 is now open! Apply online today.',
        'New STEM laboratory facilities now available for all students.',
        'Parent-Teacher Conference scheduled for March 15-16, 2024.',
        'School Achievement Awards ceremony on April 20, 2024.'
    ];
    
    $stmt = $pdo->prepare("INSERT INTO announcements (message, display_order) VALUES (?, ?)");
    
    foreach ($defaultAnnouncements as $index => $announcement) {
        $stmt->execute([$announcement, $index + 1]);
    }
    
    echo "Default announcements inserted successfully.\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
