<?php
session_start();

// Include database connection
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Get dynamic content if available
try {
    // Get program content
    $stmt = $pdo->query("SELECT * FROM landing_page_content WHERE section_name LIKE '%program%' AND is_active = 1");
    $programContent = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organize content by section
    $contentSections = [];
    foreach ($programContent as $content) {
        $contentSections[$content['section_name']] = $content;
    }
} catch (PDOException $e) {
    // Fallback to static content if database is not available
    $contentSections = [
        'elementary' => [
            'title' => 'Elementary',
            'subtitle' => 'Kindergarten - Grade 6',
            'content' => 'Foundation years focusing on basic literacy, numeracy, and character development through engaging and interactive learning methods.'
        ],
        'junior_high' => [
            'title' => 'Junior High School',
            'subtitle' => 'Grade 7 - Grade 10',
            'content' => 'Comprehensive curriculum preparing students for senior high school with emphasis on critical thinking and problem-solving skills.'
        ],
        'senior_high' => [
            'title' => 'Senior High School',
            'subtitle' => 'Grade 11 - Grade 12',
            'content' => 'Specialized tracks including STEM, ABM, HUMSS, and TVL to prepare students for college and career readiness.'
        ]
    ];
}

// Core subjects by level
$coreSubjects = [
    'elementary' => ['English', 'Filipino', 'Mathematics', 'Science', 'Araling Panlipunan', 'MAPEH (Music, Arts, PE, Health)', 'ESP (Edukasyon sa Pagpapakatao)'],
    'junior_high' => ['English', 'Filipino', 'Mathematics', 'Science', 'Araling Panlipunan', 'Technology and Livelihood Education (TLE)', 'MAPEH', 'ESP'],
    'senior_high' => [
        'core' => ['English', 'Filipino', 'Mathematics', 'Science', 'Araling Panlipunan', 'Technology and Livelihood Education (TLE)', 'MAPEH', 'ESP'],
        'tracks' => [
            'STEM' => [
                'title' => 'STEM',
                'full_name' => 'Science, Technology, Engineering, and Mathematics',
                'description' => 'For students interested in science, technology, engineering, and mathematics careers.'
            ],
            'ABM' => [
                'title' => 'ABM',
                'full_name' => 'Accountancy, Business, and Management',
                'description' => 'For students planning to pursue business, entrepreneurship, or management courses.'
            ],
            'HUMSS' => [
                'title' => 'HUMSS',
                'full_name' => 'Humanities and Social Sciences',
                'description' => 'For students interested in social sciences, humanities, and communication arts.'
            ],
            'TVL' => [
                'title' => 'TVL',
                'full_name' => 'Technical-Vocational-Livelihood',
                'description' => 'For students who want to acquire technical skills and enter the workforce immediately.'
            ]
        ]
    ]
];

// Key features by level
$keyFeatures = [
    'elementary' => [
        'Child-centered learning approach',
        'Strong foundation in reading, writing, and mathematics',
        'Character formation and values education',
        'Creative arts and physical education',
        'Science and technology integration'
    ],
    'junior_high' => [
        'Comprehensive academic curriculum',
        'Critical thinking and problem-solving focus',
        'Leadership development programs',
        'Extracurricular activities',
        'Career guidance and counseling'
    ],
    'senior_high' => [
        'Specialized academic tracks',
        'College and career preparation',
        'Industry partnerships',
        'Research and innovation projects',
        'Work immersion programs'
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Programs - EduManage School System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f8fafc;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Navigation */
        .navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
        }

        .nav-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.5rem;
            font-weight: bold;
            color: #3b82f6;
            text-decoration: none;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            color: #64748b;
            font-weight: 500;
            transition: color 0.3s;
        }

        .nav-links a:hover {
            color: #3b82f6;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
        }

        .btn-primary:hover {
            background: #2563eb;
            transform: translateY(-2px);
        }

        .btn-outline {
            border: 2px solid #3b82f6;
            color: #3b82f6;
            background: transparent;
        }

        .btn-outline:hover {
            background: #3b82f6;
            color: white;
        }

        /* Header */
        .header {
            padding-top: 6rem;
            text-align: center;
            margin-bottom: 3rem;
        }

        .header-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #3b82f6, #10b981);
            color: white;
            font-size: 2.5rem;
            border-radius: 50%;
            margin-bottom: 1.5rem;
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.3);
        }

        .header h1 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #1e40af 0%, #7c3aed 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .header p {
            font-size: 1.2rem;
            color: #64748b;
            max-width: 700px;
            margin: 0 auto;
        }

        /* Program Cards */
        .program-section {
            padding: 4rem 0;
            position: relative;
        }

        .program-section:nth-child(even) {
            background-color: white;
        }

        .program-container {
            display: flex;
            flex-direction: column;
            gap: 3rem;
        }

        .program-header {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .program-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 64px;
            height: 64px;
            border-radius: 50%;
            color: white;
            font-size: 1.8rem;
            flex-shrink: 0;
        }

        .elementary-icon {
            background: linear-gradient(135deg, #3b82f6, #1e40af);
        }

        .junior-high-icon {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        }

        .senior-high-icon {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .program-title h2 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            color: #1e293b;
        }

        .program-title p {
            color: #64748b;
            font-size: 1.1rem;
        }

        .program-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            align-items: center;
        }

        .program-info {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .program-description {
            font-size: 1.1rem;
            color: #475569;
            line-height: 1.7;
        }

        .program-features {
            margin-top: 1rem;
        }

        .feature-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            color: #1e293b;
            font-weight: 600;
        }

        .feature-list {
            list-style: none;
        }

        .feature-item {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
        }

        .feature-icon {
            color: #10b981;
            margin-top: 0.25rem;
        }

        .program-image {
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .program-image img {
            width: 100%;
            height: auto;
            display: block;
        }

        /* Core Subjects */
        .subjects-section {
            margin-top: 2rem;
        }

        .subjects-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            color: #1e293b;
            font-weight: 600;
        }

        .subjects-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 1rem;
        }

        .subject-item {
            background: white;
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-size: 0.9rem;
            color: #1e293b;
            border: 1px solid #e2e8f0;
            transition: all 0.3s;
        }

        .subject-item:hover {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
            transform: translateY(-2px);
        }

        /* Specialized Tracks */
        .tracks-section {
            margin-top: 3rem;
        }

        .tracks-title {
            margin-bottom: 1.5rem;
            color: #1e293b;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .tracks-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        .track-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            transition: all 0.3s;
        }

        .track-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
            border-color: rgba(59, 130, 246, 0.3);
        }

        .track-title {
            color: #10b981;
            font-weight: 700;
            margin-bottom: 0.25rem;
            font-size: 1.25rem;
        }

        .track-subtitle {
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .track-description {
            color: #475569;
            font-size: 0.95rem;
            line-height: 1.6;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .program-content {
                grid-template-columns: 1fr;
            }

            .tracks-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }

            .header h1 {
                font-size: 2rem;
            }

            .program-header {
                flex-direction: column;
                text-align: center;
            }

            .program-title h2 {
                font-size: 1.75rem;
            }
        }

        /* Footer */
        .footer {
            background: #1e293b;
            color: white;
            padding: 3rem 0 1rem;
            margin-top: 4rem;
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .footer-section h4 {
            font-size: 1.25rem;
            font-weight: bold;
            margin-bottom: 1rem;
        }

        .footer-section p,
        .footer-section a {
            color: #94a3b8;
            text-decoration: none;
            margin-bottom: 0.5rem;
            display: block;
        }

        .footer-section a:hover {
            color: white;
        }

        .footer-bottom {
            border-top: 1px solid #334155;
            padding-top: 1rem;
            text-align: center;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="container">
            <div class="nav-content">
                <a href="index.php" class="logo">
                    <i class="fas fa-graduation-cap"></i>
                    EduManage
                </a>
                <div class="nav-links">
                    <a href="index.php">Home</a>
                    <a href="programs.php" class="active">Programs</a>
                    <a href="index.php#features">Features</a>
                    <a href="enrollment/index.php">Enrollment</a>
                    <a href="index.php#contact">Contact</a>
                    <a href="auth/login.php" class="btn btn-outline">Login</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-icon">
                <i class="fas fa-book-open"></i>
            </div>
            <h1>Academic Programs</h1>
            <p>Discover our comprehensive academic programs designed to nurture students from kindergarten through senior high school, preparing them for success in higher education and their chosen careers.</p>
        </div>
    </header>

    <!-- Elementary Section -->
    <section id="elementary" class="program-section">
        <div class="container">
            <div class="program-container">
                <div class="program-header">
                    <div class="program-icon elementary-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="program-title">
                        <h2>Elementary</h2>
                        <p>Kindergarten - Grade 6</p>
                    </div>
                </div>

                <div class="program-content">
                    <div class="program-info">
                        <p class="program-description">
                            <?= htmlspecialchars($contentSections['elementary']['content'] ?? 'Foundation years focusing on basic literacy, numeracy, and character development through engaging and interactive learning methods.') ?>
                        </p>

                        <div class="program-features">
                            <div class="feature-title">
                                <i class="fas fa-star"></i>
                                <span>Key Features</span>
                            </div>
                            <ul class="feature-list">
                                <?php foreach ($keyFeatures['elementary'] as $feature): ?>
                                <li class="feature-item">
                                    <span class="feature-icon"><i class="fas fa-check-circle"></i></span>
                                    <span><?= htmlspecialchars($feature) ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                        <div class="subjects-section">
                            <div class="subjects-title">
                                <i class="fas fa-book"></i>
                                <span>Core Subjects</span>
                            </div>
                            <div class="subjects-list">
                                <?php foreach ($coreSubjects['elementary'] as $subject): ?>
                                <span class="subject-item"><?= htmlspecialchars($subject) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="program-image">
                        <img src="https://images.unsplash.com/photo-1503676260728-1c00da094a0b?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&h=800" alt="Elementary Education">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Junior High School Section -->
    <section id="junior-high" class="program-section">
        <div class="container">
            <div class="program-container">
                <div class="program-header">
                    <div class="program-icon junior-high-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="program-title">
                        <h2>Junior High School</h2>
                        <p>Grade 7 - Grade 10</p>
                    </div>
                </div>

                <div class="program-content">
                    <div class="program-image">
                        <img src="https://images.unsplash.com/photo-1509062522246-3755977927d7?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&h=800" alt="Junior High School Education">
                    </div>

                    <div class="program-info">
                        <p class="program-description">
                            <?= htmlspecialchars($contentSections['junior_high']['content'] ?? 'Comprehensive curriculum preparing students for senior high school with emphasis on critical thinking and problem-solving skills.') ?>
                        </p>

                        <div class="program-features">
                            <div class="feature-title">
                                <i class="fas fa-star"></i>
                                <span>Key Features</span>
                            </div>
                            <ul class="feature-list">
                                <?php foreach ($keyFeatures['junior_high'] as $feature): ?>
                                <li class="feature-item">
                                    <span class="feature-icon"><i class="fas fa-check-circle"></i></span>
                                    <span><?= htmlspecialchars($feature) ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                        <div class="subjects-section">
                            <div class="subjects-title">
                                <i class="fas fa-book"></i>
                                <span>Core Subjects</span>
                            </div>
                            <div class="subjects-list">
                                <?php foreach ($coreSubjects['junior_high'] as $subject): ?>
                                <span class="subject-item"><?= htmlspecialchars($subject) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Senior High School Section -->
    <section id="senior-high" class="program-section">
        <div class="container">
            <div class="program-container">
                <div class="program-header">
                    <div class="program-icon senior-high-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="program-title">
                        <h2>Senior High School</h2>
                        <p>Grade 11 - Grade 12</p>
                    </div>
                </div>

                <div class="program-content">
                    <div class="program-info">
                        <p class="program-description">
                            <?= htmlspecialchars($contentSections['senior_high']['content'] ?? 'Specialized tracks including STEM, ABM, HUMSS, and TVL to prepare students for college and career readiness.') ?>
                        </p>

                        <div class="program-features">
                            <div class="feature-title">
                                <i class="fas fa-star"></i>
                                <span>Key Features</span>
                            </div>
                            <ul class="feature-list">
                                <?php foreach ($keyFeatures['senior_high'] as $feature): ?>
                                <li class="feature-item">
                                    <span class="feature-icon"><i class="fas fa-check-circle"></i></span>
                                    <span><?= htmlspecialchars($feature) ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                        <div class="subjects-section">
                            <div class="subjects-title">
                                <i class="fas fa-book"></i>
                                <span>Core Subjects</span>
                            </div>
                            <div class="subjects-list">
                                <?php foreach ($coreSubjects['senior_high']['core'] as $subject): ?>
                                <span class="subject-item"><?= htmlspecialchars($subject) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="program-image">
                        <img src="https://images.unsplash.com/photo-1523050854058-8df90110c9f1?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&h=800" alt="Senior High School Education">
                    </div>
                </div>

                <div class="tracks-section">
                    <div class="tracks-title">
                        <i class="fas fa-route"></i>
                        <span>Specialized Tracks</span>
                    </div>
                    <div class="tracks-grid">
                        <?php foreach ($coreSubjects['senior_high']['tracks'] as $track): ?>
                        <div class="track-card">
                            <div class="track-title"><?= htmlspecialchars($track['title']) ?></div>
                            <div class="track-subtitle"><?= htmlspecialchars($track['full_name']) ?></div>
                            <div class="track-description"><?= htmlspecialchars($track['description']) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h4>EduManage</h4>
                    <p>A comprehensive school management system designed to streamline educational processes.</p>
                </div>
                <div class="footer-section">
                    <h4>Quick Links</h4>
                    <a href="index.php">Home</a>
                    <a href="programs.php">Academic Programs</a>
                    <a href="enrollment/index.php">Enrollment</a>
                    <a href="auth/login.php">Login</a>
                </div>
                <div class="footer-section">
                    <h4>Contact Us</h4>
                    <p><i class="fas fa-map-marker-alt"></i> 123 Education St., Manila</p>
                    <p><i class="fas fa-phone"></i> (02) 8123-4567</p>
                    <p><i class="fas fa-envelope"></i> info@edumanage.edu.ph</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> EduManage School System. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // Add active class to current navigation item
        document.addEventListener('DOMContentLoaded', function() {
            const currentLocation = location.href;
            const navLinks = document.querySelectorAll('.nav-links a');
            navLinks.forEach(link => {
                if (link.href === currentLocation) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>
