<?php
require_once 'includes/config.php';

try {
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
    echo "News table created successfully.\n";
    
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
    echo "Events table created successfully.\n";
    
    // Insert default news
    $defaultNews = [
        [
            'title' => 'New Science Laboratory Opens',
            'excerpt' => 'State-of-the-art science facilities now available for student use.',
            'content' => 'Our new science laboratory features the latest equipment and technology to enhance student learning in physics, chemistry, and biology.',
            'date' => '2024-01-15'
        ],
        [
            'title' => 'Academic Excellence Awards',
            'excerpt' => 'Celebrating our top-performing students and their achievements.',
            'content' => 'We are proud to recognize students who have demonstrated outstanding academic performance throughout the year.',
            'date' => '2024-01-10'
        ],
        [
            'title' => 'Sports Championship Victory',
            'excerpt' => 'Our basketball team wins the regional championship tournament.',
            'content' => 'After months of training and dedication, our basketball team has achieved victory in the regional championship.',
            'date' => '2024-01-05'
        ]
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO news (title, excerpt, content, date, display_order) VALUES (?, ?, ?, ?, ?)");
    
    foreach ($defaultNews as $index => $article) {
        $stmt->execute([
            $article['title'],
            $article['excerpt'],
            $article['content'],
            $article['date'],
            $index + 1
        ]);
    }
    
    echo "Default news inserted successfully.\n";
    
    // Insert default events
    $defaultEvents = [
        [
            'title' => 'Science Fair 2024',
            'description' => 'Annual science fair showcasing student projects and innovations.',
            'date' => '2024-03-20',
            'time' => '09:00:00',
            'location' => 'Main Auditorium'
        ],
        [
            'title' => 'Career Guidance Seminar',
            'description' => 'Professional guidance for students planning their future careers.',
            'date' => '2024-03-25',
            'time' => '14:00:00',
            'location' => 'Conference Hall'
        ],
        [
            'title' => 'Arts Festival',
            'description' => 'Celebration of student creativity in music, dance, and visual arts.',
            'date' => '2024-04-10',
            'time' => '10:00:00',
            'location' => 'School Grounds'
        ]
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO events (title, description, date, time, location, display_order) VALUES (?, ?, ?, ?, ?, ?)");
    
    foreach ($defaultEvents as $index => $event) {
        $stmt->execute([
            $event['title'],
            $event['description'],
            $event['date'],
            $event['time'],
            $event['location'],
            $index + 1
        ]);
    }
    
    echo "Default events inserted successfully.\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
