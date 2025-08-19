<?php
session_start();

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    // Redirect to appropriate dashboard based on role
    $role = $_SESSION['user_role'] ?? 'student';
    switch ($role) {
        case 'admin':
            header('Location: admin/index.php');
            break;
        case 'teacher':
            header('Location: teacher/index.php');
            break;
        case 'student':
            header('Location: student/index.php');
            break;
        case 'parent':
            header('Location: parent/index.php');
            break;
        case 'guidance':
            header('Location: guidance/index.php');
            break;
        case 'registrar':
            header('Location: registrar/index.php');
            break;
        case 'accounting':
            header('Location: accounting/index.php');
            break;
        case 'principal':
            header('Location: principal/index.php');
            break;
        default:
            header('Location: student/index.php');
    }
    exit();
}

// Include database connection
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Get dynamic landing page content from database
try {
    // Get landing page content
    $stmt = $pdo->query("SELECT * FROM landing_page_content WHERE is_active = 1 ORDER BY display_order");
    $landingContent = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organize content by section
    $contentSections = [];
    foreach ($landingContent as $content) {
        $contentSections[$content['section_name']] = $content;
    }
    
    // Get announcements
    $stmt = $pdo->query("SELECT message FROM announcements WHERE is_active = 1 ORDER BY display_order");
    $announcementRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $announcements = array_column($announcementRows, 'message');
    
} catch (PDOException $e) {
    // Fallback to static content if database is not available
    $contentSections = [
        'hero' => [
            'title' => 'Excellence in Education',
            'subtitle' => 'Empowering minds for a brighter future with modern technology and dedicated faculty',
            'button_text' => 'Login to Portal',
            'button_url' => 'auth/login.php'
        ],
        'about' => [
            'title' => 'About EduManage',
            'content' => 'EduManage is a comprehensive school management system designed to streamline educational processes and enhance communication between all stakeholders.'
        ],
        'elementary' => [
            'title' => 'Elementary',
            'content' => 'Foundation years focusing on basic literacy, numeracy, and character development through engaging and interactive learning methods.'
        ],
        'junior_high' => [
            'title' => 'Junior High School',
            'content' => 'Comprehensive curriculum preparing students for senior high school with emphasis on critical thinking and problem-solving skills.'
        ],
        'senior_high' => [
            'title' => 'Senior High School',
            'content' => 'Specialized tracks including STEM, ABM, HUMSS, and TVL to prepare students for college and career readiness.'
        ]
    ];
    
    $announcements = [
        'Enrollment for School Year 2024-2025 is now open! Apply online today.',
        'New STEM laboratory facilities now available for all students.',
        'Parent-Teacher Conference scheduled for March 15-16, 2024.',
        'School Achievement Awards ceremony on April 20, 2024.'
    ];
}

// Static hero images (these could also be made dynamic if needed)
$heroImages = [
    ['url' => 'https://images.unsplash.com/photo-1523050854058-8df90110c9f1?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&h=1080', 'title' => 'Excellence in Education', 'subtitle' => 'Empowering minds for a brighter future'],
    ['url' => 'https://images.unsplash.com/photo-1562774053-701939374585?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&h=1080', 'title' => 'Modern Learning Environment', 'subtitle' => 'State-of-the-art facilities and technology'],
    ['url' => 'https://images.unsplash.com/photo-1509062522246-3755977927d7?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&h=1080', 'title' => 'Dedicated Faculty', 'subtitle' => 'Experienced educators committed to your success']
];

// Get dynamic news and events
try {
    $stmt = $pdo->query("SELECT * FROM news WHERE is_active = 1 ORDER BY display_order, date DESC LIMIT 3");
    $news = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query("SELECT * FROM events WHERE is_active = 1 ORDER BY display_order, date ASC LIMIT 3");
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Fallback to static content
    $news = [
        ['title' => 'New Science Laboratory Opens', 'date' => '2024-01-15', 'excerpt' => 'State-of-the-art science facilities now available for student use.'],
        ['title' => 'Academic Excellence Awards', 'date' => '2024-01-10', 'excerpt' => 'Celebrating our top-performing students and their achievements.'],
        ['title' => 'Sports Championship Victory', 'date' => '2024-01-05', 'excerpt' => 'Our basketball team wins the regional championship tournament.']
    ];

    $events = [
        ['title' => 'Science Fair 2024', 'date' => '2024-03-20', 'time' => '9:00 AM', 'location' => 'Main Auditorium'],
        ['title' => 'Career Guidance Seminar', 'date' => '2024-03-25', 'time' => '2:00 PM', 'location' => 'Conference Hall'],
        ['title' => 'Arts Festival', 'date' => '2024-04-10', 'time' => '10:00 AM', 'location' => 'School Grounds']
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduManage - School Management System</title>
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

        /* Hero Section */
        .hero {
            height: 100vh;
            background: linear-gradient(135deg, #1e40af 0%, #7c3aed 50%, #10b981 100%);
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.2);
            z-index: 1;
        }

        .hero-slider {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
        }

        .hero-slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-size: cover;
            background-position: center;
            opacity: 0;
            transition: opacity 1.5s ease-in-out;
        }

        .hero-slide.active {
            opacity: 0.4;
        }

        .hero-content {
            position: relative;
            z-index: 2;
            text-align: center;
            color: white;
            animation: fadeInUp 1s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .hero h1 {
            font-size: 4rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
            text-shadow: 2px 2px 8px rgba(0, 0, 0, 0.5);
            background: linear-gradient(135deg, #ffffff 0%, #e2e8f0 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero p {
            font-size: 1.4rem;
            margin-bottom: 3rem;
            opacity: 0.95;
            text-shadow: 1px 1px 4px rgba(0, 0, 0, 0.3);
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .hero-buttons {
            display: flex;
            gap: 1.5rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .hero-buttons .btn {
            padding: 1rem 2rem;
            font-size: 1.1rem;
            border-radius: 50px;
            transition: all 0.4s ease;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        .hero-buttons .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.3);
        }

        /* Announcements Marquee */
        .announcements {
            background: #1e40af;
            color: white;
            padding: 1rem 0;
            overflow: hidden;
        }

        .marquee {
            white-space: nowrap;
            animation: scroll 30s linear infinite;
        }

        @keyframes scroll {
            0% { transform: translateX(100%); }
            100% { transform: translateX(-100%); }
        }

        /* Sections */
        .section {
            padding: 5rem 0;
        }

        .section-title {
            text-align: center;
            margin-bottom: 3rem;
        }

        .section-title h2 {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 1rem;
            color: #1e293b;
        }

        .section-title p {
            font-size: 1.125rem;
            color: #64748b;
        }

        /* Cards */
        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 2rem;
        }

        .card {
            background: white;
            border-radius: 1.5rem;
            padding: 2.5rem 2rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            transition: all 0.4s ease;
            text-align: center;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(226, 232, 240, 0.8);
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #3b82f6, #8b5cf6, #10b981);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .card:hover::before {
            transform: scaleX(1);
        }

        .card:hover {
            transform: translateY(-8px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            border-color: rgba(59, 130, 246, 0.2);
        }

        .card-icon {
            font-size: 3.5rem;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1.5rem;
            transition: transform 0.3s ease;
        }

        .card:hover .card-icon {
            transform: scale(1.1);
        }

        .card h3 {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: #1e293b;
        }

        .card p {
            color: #64748b;
            line-height: 1.6;
        }

        /* Enrollment Steps */
        .steps {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .step {
            text-align: center;
        }

        .step-number {
            width: 4rem;
            height: 4rem;
            background: #3b82f6;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            margin: 0 auto 1rem;
        }

        /* Organizational Chart */
        .org-chart {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2rem;
        }

        .org-level {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
            justify-content: center;
        }

        .org-card {
            background: white;
            border-radius: 0.5rem;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            min-width: 200px;
        }

        .org-card.principal {
            background: #dbeafe;
            border: 2px solid #3b82f6;
        }

        .org-card.vp {
            background: #dcfce7;
            border: 2px solid #16a34a;
        }

        .org-card.dept {
            background: #fef3c7;
            border: 2px solid #f59e0b;
        }

        /* Footer */
        .footer {
            background: #1e293b;
            color: white;
            padding: 3rem 0 1rem;
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

        /* Responsive */
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }

            .hero h1 {
                font-size: 2.5rem;
            }

            .hero-buttons {
                flex-direction: column;
                align-items: center;
            }

            .card-grid {
                grid-template-columns: 1fr;
            }
        }

        /* AI Chat Button */
        .chat-button {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 60px;
            height: 60px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 1.5rem;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
            transition: all 0.3s;
            z-index: 1000;
        }

        .chat-button:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.6);
        }
        
        /* Chat Container */
        .chat-container {
            position: fixed;
            bottom: 5rem;
            right: 2rem;
            width: 350px;
            height: 500px;
            background: white;
            border-radius: 1rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            z-index: 999;
            opacity: 0;
            transform: translateY(20px) scale(0.9);
            pointer-events: none;
            transition: all 0.3s ease;
        }
        
        .chat-container.active {
            opacity: 1;
            transform: translateY(0) scale(1);
            pointer-events: all;
        }
        
        .chat-header {
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            color: white;
            padding: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .chat-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
        }
        
        .chat-close {
            background: transparent;
            border: none;
            color: white;
            cursor: pointer;
            font-size: 1.2rem;
            transition: transform 0.3s;
        }
        
        .chat-close:hover {
            transform: scale(1.1);
        }
        
        .chat-body {
            flex: 1;
            padding: 1rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .chat-message {
            display: flex;
            flex-direction: column;
            max-width: 80%;
        }
        
        .chat-message.user {
            align-self: flex-end;
        }
        
        .chat-message.ai {
            align-self: flex-start;
        }
        
        .message-content {
            padding: 0.75rem 1rem;
            border-radius: 1rem;
            font-size: 0.95rem;
            line-height: 1.4;
            position: relative;
        }
        
        .chat-message.user .message-content {
            background: #3b82f6;
            color: white;
            border-bottom-right-radius: 0;
        }
        
        .chat-message.ai .message-content {
            background: #f3f4f6;
            color: #1f2937;
            border-bottom-left-radius: 0;
        }
        
        .message-time {
            font-size: 0.7rem;
            color: #6b7280;
            margin-top: 0.25rem;
            align-self: flex-end;
        }
        
        .chat-message.user .message-time {
            margin-right: 0.5rem;
        }
        
        .chat-message.ai .message-time {
            margin-left: 0.5rem;
        }
        
        .chat-footer {
            padding: 1rem;
            border-top: 1px solid #e5e7eb;
            display: flex;
            gap: 0.5rem;
        }
        
        .chat-input {
            flex: 1;
            padding: 0.75rem 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 2rem;
            font-size: 0.95rem;
            outline: none;
            transition: border-color 0.3s;
        }
        
        .chat-input:focus {
            border-color: #3b82f6;
        }
        
        .chat-send {
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .chat-send:hover {
            background: #2563eb;
        }
        
        .chat-typing {
            display: flex;
            gap: 0.3rem;
            padding: 0.5rem;
            align-self: flex-start;
        }
        
        .typing-dot {
            width: 8px;
            height: 8px;
            background: #6b7280;
            border-radius: 50%;
            opacity: 0.6;
            animation: typing 1.4s infinite both;
        }
        
        .typing-dot:nth-child(2) {
            animation-delay: 0.2s;
        }
        
        .typing-dot:nth-child(3) {
            animation-delay: 0.4s;
        }
        
        @keyframes typing {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-5px);
            }
        }

        /* Academic Programs Section */
        .programs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
        }

        .program-card {
            background: white;
            border-radius: 1.5rem;
            padding: 3rem 2rem;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
        }

        .program-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #3b82f6, #8b5cf6);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .program-card:hover::before {
            transform: scaleX(1);
        }

        .program-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .program-card.elementary .program-icon {
            background: linear-gradient(135deg, #3b82f6, #1e40af);
        }

        .program-card.junior-high .program-icon {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        }

        .program-card.senior-high .program-icon {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .program-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            color: white;
            font-size: 2rem;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .program-card h3 {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 1.5rem;
            color: #1e293b;
        }

        .program-card p {
            color: #64748b;
            line-height: 1.7;
            font-size: 1rem;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="container">
            <div class="nav-content">
                <div class="logo">
                    <i class="fas fa-graduation-cap"></i>
                    EduManage
                </div>
                <div class="nav-links">
                    <a href="#home">Home</a>
                    <a href="programs.php">Programs</a>
                    <a href="#features">Features</a>
                    <a href="#enrollment">Enrollment</a>
                    <a href="#org-chart">Org Chart</a>
                    <a href="#contact">Contact</a>
                    <a href="auth/login.php" class="btn btn-outline">Login</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero">
        <div class="hero-slider">
            <?php foreach ($heroImages as $index => $image): ?>
            <div class="hero-slide <?= $index === 0 ? 'active' : '' ?>" 
                 style="background-image: url('<?= $image['url'] ?>')"></div>
            <?php endforeach; ?>
        </div>
        <div class="container">
            <div class="hero-content">
                <h1><?= htmlspecialchars($contentSections['hero']['title'] ?? 'Excellence in Education') ?></h1>
                <p><?= htmlspecialchars($contentSections['hero']['subtitle'] ?? 'Empowering minds for a brighter future with modern technology and dedicated faculty') ?></p>
                <div class="hero-buttons">
                    <a href="<?= htmlspecialchars($contentSections['hero']['button_url'] ?? 'auth/login.php') ?>" class="btn btn-primary">
                        <?= htmlspecialchars($contentSections['hero']['button_text'] ?? 'Login to Portal') ?>
                    </a>
                    <a href="enrollment/index.php" class="btn btn-outline">Start Enrollment</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Announcements Marquee -->
    <div class="announcements">
        <div class="marquee">
            <?php foreach ($announcements as $announcement): ?>
                <span style="margin-right: 3rem;">📢 <?= htmlspecialchars($announcement) ?></span>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Academic Programs Section -->
    <section id="programs" class="section">
        <div class="container">
            <div class="section-title">
                <div style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; margin-bottom: 1rem;">
                    <i class="fas fa-graduation-cap" style="color: #10b981; font-size: 1.5rem;"></i>
                    <span style="color: #10b981; font-weight: 600; font-size: 1.1rem;">Academic Programs</span>
                </div>
                <h2 style="background: linear-gradient(135deg, #1e40af 0%, #7c3aed 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">Comprehensive Education Programs</h2>
                <p>Quality education from elementary through senior high school</p>
            </div>
            <div class="programs-grid">
                <div class="program-card elementary">
                    <div class="program-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <h3><?= htmlspecialchars($contentSections['elementary']['title'] ?? 'Elementary') ?></h3>
                    <p><?= htmlspecialchars($contentSections['elementary']['content'] ?? 'Foundation years focusing on basic literacy, numeracy, and character development through engaging and interactive learning methods.') ?></p>
                    <div style="margin-top: 1.5rem;">
                        <a href="programs.php#elementary" class="btn btn-outline" style="font-size: 0.9rem;">Learn More</a>
                    </div>
                </div>
                <div class="program-card junior-high">
                    <div class="program-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3><?= htmlspecialchars($contentSections['junior_high']['title'] ?? 'Junior High School') ?></h3>
                    <p><?= htmlspecialchars($contentSections['junior_high']['content'] ?? 'Comprehensive curriculum preparing students for senior high school with emphasis on critical thinking and problem-solving skills.') ?></p>
                    <div style="margin-top: 1.5rem;">
                        <a href="programs.php#junior-high" class="btn btn-outline" style="font-size: 0.9rem;">Learn More</a>
                    </div>
                </div>
                <div class="program-card senior-high">
                    <div class="program-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <h3><?= htmlspecialchars($contentSections['senior_high']['title'] ?? 'Senior High School') ?></h3>
                    <p><?= htmlspecialchars($contentSections['senior_high']['content'] ?? 'Specialized tracks including STEM, ABM, HUMSS, and TVL to prepare students for college and career readiness.') ?></p>
                    <div style="margin-top: 1.5rem;">
                        <a href="programs.php#senior-high" class="btn btn-outline" style="font-size: 0.9rem;">Learn More</a>
                    </div>
                </div>
            </div>
            <div style="text-align: center; margin-top: 3rem;">
                <a href="programs.php" class="btn btn-primary">View All Academic Programs</a>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="section" style="background: #f8fafc;">
        <div class="container">
            <div class="section-title">
                <h2>Key Features</h2>
                <p>Everything you need for modern school management</p>
            </div>
            <div class="card-grid">
                <div class="card">
                    <i class="fas fa-users card-icon"></i>
                    <h3>Multi-Role Portals</h3>
                    <p>Separate dashboards for students, teachers, parents, and staff</p>
                </div>
                <div class="card">
                    <i class="fas fa-user-check card-icon"></i>
                    <h3>Advanced Enrollment</h3>
                    <p>Complete online enrollment with document upload and payment</p>
                </div>
                <div class="card">
                    <i class="fas fa-book-open card-icon"></i>
                    <h3>Grades & Assignments</h3>
                    <p>Real-time grade tracking and assignment management</p>
                </div>
                <div class="card">
                    <i class="fas fa-comments card-icon"></i>
                    <h3>Chat & Meetings</h3>
                    <p>Integrated communication and virtual meetings</p>
                </div>
                <div class="card">
                    <i class="fas fa-file-alt card-icon"></i>
                    <h3>Document Sharing</h3>
                    <p>Secure file sharing and learning modules</p>
                </div>
                <div class="card">
                    <i class="fas fa-credit-card card-icon"></i>
                    <h3>Payment Tracking</h3>
                    <p>Automated payment processing and financial records</p>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="section" style="background: #f8fafc;">
        <div class="container">
            <div class="section-title">
                <h2><?= htmlspecialchars($contentSections['about']['title'] ?? 'About EduManage') ?></h2>
                <p><?= htmlspecialchars($contentSections['about']['content'] ?? 'EduManage is a comprehensive school management system designed to streamline educational processes and enhance communication between all stakeholders. Our platform brings together students, teachers, parents, and administrators in a unified digital environment that promotes collaboration, transparency, and academic excellence.') ?></p>
            </div>
        </div>
    </section>

    <!-- Enrollment Process -->
    <section id="enrollment" class="section">
        <div class="container">
            <div class="section-title">
                <h2>How to Enroll Online</h2>
                <p>Simple 4-step enrollment process</p>
            </div>
            <div class="steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <h3>Fill Application</h3>
                    <p>Complete the online enrollment form</p>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <h3>Upload Documents</h3>
                    <p>Submit required academic documents</p>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <h3>Payment</h3>
                    <p>Process enrollment fees securely</p>
                </div>
                <div class="step">
                    <div class="step-number">4</div>
                    <h3>Review</h3>
                    <p>Application review and approval</p>
                </div>
            </div>
            <div style="text-align: center;">
                <a href="enrollment/index.php" class="btn btn-primary">Start Enrollment</a>
            </div>
        </div>
    </section>

    <!-- Organizational Chart -->
    <section id="org-chart" class="section" style="background: #f8fafc;">
        <div class="container">
            <div class="section-title">
                <h2>Our Organizational Structure</h2>
                <p>Our school's leadership and staff work together to provide quality education and seamless operations.</p>
            </div>
            <div class="org-chart">
                <div class="org-level">
                    <div class="org-card principal">
                        <i class="fas fa-building card-icon"></i>
                        <h3>School Director / Principal</h3>
                        <p>Principal Office</p>
                    </div>
                </div>
                <div class="org-level">
                    <div class="org-card vp">
                        <i class="fas fa-users card-icon"></i>
                        <h4>Vice Principal</h4>
                        <p>Academic Affairs</p>
                    </div>
                    <div class="org-card vp">
                        <i class="fas fa-users card-icon"></i>
                        <h4>Vice Principal</h4>
                        <p>Administration</p>
                    </div>
                </div>
                <div class="org-level">
                    <div class="org-card dept">
                        <i class="fas fa-chalkboard-teacher card-icon"></i>
                        <h4>Academic Department</h4>
                        <p>Teachers & Department Heads</p>
                    </div>
                    <div class="org-card dept">
                        <i class="fas fa-file-alt card-icon"></i>
                        <h4>Registrar</h4>
                        <p>Student Records</p>
                    </div>
                    <div class="org-card dept">
                        <i class="fas fa-calculator card-icon"></i>
                        <h4>Accounting</h4>
                        <p>Financial Services</p>
                    </div>
                    <div class="org-card dept">
                        <i class="fas fa-heart card-icon"></i>
                        <h4>Guidance</h4>
                        <p>Student Support</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- News Section -->
    <section class="section">
        <div class="container">
            <div class="section-title">
                <h2>Latest News</h2>
                <p>Stay updated with school happenings</p>
            </div>
            <div class="card-grid">
                <?php foreach ($news as $article): ?>
                <div class="card">
                    <h3><?= htmlspecialchars($article['title']) ?></h3>
                    <p style="color: #3b82f6; margin-bottom: 1rem;"><?= date('F j, Y', strtotime($article['date'])) ?></p>
                    <p><?= htmlspecialchars($article['excerpt']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Events Section -->
    <section class="section" style="background: #f8fafc;">
        <div class="container">
            <div class="section-title">
                <h2>Upcoming Events</h2>
                <p>Don't miss these important school events</p>
            </div>
            <div class="card-grid">
                <?php foreach ($events as $event): ?>
                <div class="card">
                    <i class="fas fa-calendar-alt card-icon"></i>
                    <h3><?= htmlspecialchars($event['title']) ?></h3>
                    <p><strong>Date:</strong> <?= date('F j, Y', strtotime($event['date'])) ?></p>
                    <p><strong>Time:</strong> <?= htmlspecialchars($event['time']) ?></p>
                    <p><strong>Location:</strong> <?= htmlspecialchars($event['location']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="section">
        <div class="container">
            <div class="section-title">
                <h2>Contact Us</h2>
                <p>Get in touch with our school administration</p>
            </div>
            <div class="card-grid">
                <div class="card">
                    <i class="fas fa-phone card-icon"></i>
                    <h3>Phone</h3>
                    <p>+1 (555) 123-4567</p>
                </div>
                <div class="card">
                    <i class="fas fa-envelope card-icon"></i>
                    <h3>Email</h3>
                    <p>info@edumanage.school</p>
                </div>
                <div class="card">
                    <i class="fas fa-map-marker-alt card-icon"></i>
                    <h3>Address</h3>
                    <p>123 Education Street<br>Learning City, LC 12345</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <div class="logo" style="margin-bottom: 1rem;">
                        <i class="fas fa-graduation-cap"></i>
                        EduManage
                    </div>
                    <p>Empowering education through technology. Building the future of school management.</p>
                </div>
                <div class="footer-section">
                    <h4>Quick Links</h4>
                    <a href="#about">About Us</a>
                    <a href="#features">Features</a>
                    <a href="#enrollment">Enrollment</a>
                    <a href="#contact">Contact</a>
                </div>
                <div class="footer-section">
                    <h4>Follow Us</h4>
                    <a href="#">Facebook</a>
                    <a href="#">Twitter</a>
                    <a href="#">LinkedIn</a>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 EduManage. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- AI Chat Button -->
    <button class="chat-button" onclick="toggleChat()">
        <i class="fas fa-comments"></i>
    </button>
    
    <!-- Chat Container -->
    <div class="chat-container" id="chatContainer">
        <div class="chat-header">
            <div class="chat-title">
                <i class="fas fa-robot"></i>
                <span>EduBot Support</span>
            </div>
            <button class="chat-close" onclick="toggleChat()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="chat-body" id="chatBody">
            <div class="chat-message ai">
                <div class="message-content">
                    Hello! I'm EduBot, your virtual assistant. How can I help you with enrollment, programs, or school information today?
                </div>
                <div class="message-time">
                    Just now
                </div>
            </div>
        </div>
        <div class="chat-footer">
            <input type="text" class="chat-input" id="chatInput" placeholder="Type your message..." onkeypress="if(event.key === 'Enter') sendMessage()">
            <button class="chat-send" onclick="sendMessage()">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>

    <script>
        // Hero slider
        let currentSlide = 0;
        const slides = document.querySelectorAll('.hero-slide');
        
        function nextSlide() {
            slides[currentSlide].classList.remove('active');
            currentSlide = (currentSlide + 1) % slides.length;
            slides[currentSlide].classList.add('active');
        }
        
        setInterval(nextSlide, 5000);

        // Smooth scrolling
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Add active class to current navigation item
        document.addEventListener('DOMContentLoaded', function() {
            const currentLocation = location.href;
            const navLinks = document.querySelectorAll('.nav-links a');
            navLinks.forEach(link => {
                if (link.href === currentLocation) {
                    link.classList.add('active');
                }
            });
            
            // Load chat history if available
            loadChatHistory();
        });
        
        // Toggle chat container visibility
        function toggleChat() {
            const chatContainer = document.getElementById('chatContainer');
            chatContainer.classList.toggle('active');
            
            // If opening chat, focus on input
            if (chatContainer.classList.contains('active')) {
                document.getElementById('chatInput').focus();
                // Scroll to bottom of chat
                const chatBody = document.getElementById('chatBody');
                chatBody.scrollTop = chatBody.scrollHeight;
            }
        }
        
        // Send message to chat
        function sendMessage() {
            const chatInput = document.getElementById('chatInput');
            const message = chatInput.value.trim();
            
            // Don't send empty messages
            if (message === '') return;
            
            // Clear input
            chatInput.value = '';
            
            // Add user message to chat
            addMessage('user', message);
            
            // Show typing indicator
            showTypingIndicator();
            
            // Send message to server
            fetch('chat/process.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'message=' + encodeURIComponent(message)
            })
            .then(response => response.json())
            .then(data => {
                // Remove typing indicator
                removeTypingIndicator();
                
                if (data.status === 'success') {
                    // Add AI response to chat
                    addMessage('ai', data.ai_response.text);
                } else {
                    // Show error message
                    addMessage('ai', 'Sorry, I encountered an error. Please try again later.');
                }
            })
            .catch(error => {
                // Remove typing indicator
                removeTypingIndicator();
                
                // Show error message
                addMessage('ai', 'Sorry, I encountered an error. Please try again later.');
                console.error('Error:', error);
            });
        }
        
        // Add message to chat
        function addMessage(sender, message) {
            const chatBody = document.getElementById('chatBody');
            const messageElement = document.createElement('div');
            messageElement.className = `chat-message ${sender}`;
            
            const now = new Date();
            const hours = now.getHours().toString().padStart(2, '0');
            const minutes = now.getMinutes().toString().padStart(2, '0');
            const timeString = `${hours}:${minutes}`;
            
            messageElement.innerHTML = `
                <div class="message-content">${message}</div>
                <div class="message-time">${timeString}</div>
            `;
            
            chatBody.appendChild(messageElement);
            
            // Scroll to bottom of chat
            chatBody.scrollTop = chatBody.scrollHeight;
        }
        
        // Show typing indicator
        function showTypingIndicator() {
            const chatBody = document.getElementById('chatBody');
            const typingElement = document.createElement('div');
            typingElement.className = 'chat-typing';
            typingElement.id = 'typingIndicator';
            
            typingElement.innerHTML = `
                <div class="typing-dot"></div>
                <div class="typing-dot"></div>
                <div class="typing-dot"></div>
            `;
            
            chatBody.appendChild(typingElement);
            
            // Scroll to bottom of chat
            chatBody.scrollTop = chatBody.scrollHeight;
        }
        
        // Remove typing indicator
        function removeTypingIndicator() {
            const typingIndicator = document.getElementById('typingIndicator');
            if (typingIndicator) {
                typingIndicator.remove();
            }
        }
        
        // Load chat history
        function loadChatHistory() {
            fetch('chat/process.php?action=history')
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success' && data.messages.length > 0) {
                    const chatBody = document.getElementById('chatBody');
                    chatBody.innerHTML = ''; // Clear default message
                    
                    data.messages.forEach(msg => {
                        const messageElement = document.createElement('div');
                        messageElement.className = `chat-message ${msg.sender}`;
                        
                        // Format timestamp
                        const timestamp = new Date(msg.timestamp);
                        const hours = timestamp.getHours().toString().padStart(2, '0');
                        const minutes = timestamp.getMinutes().toString().padStart(2, '0');
                        const timeString = `${hours}:${minutes}`;
                        
                        messageElement.innerHTML = `
                            <div class="message-content">${msg.message}</div>
                            <div class="message-time">${timeString}</div>
                        `;
                        
                        chatBody.appendChild(messageElement);
                    });
                    
                    // Scroll to bottom of chat
                    chatBody.scrollTop = chatBody.scrollHeight;
                }
            })
            .catch(error => {
                console.error('Error loading chat history:', error);
            });
        }
        
        // Chat toggle function is defined above

        // Add scroll effect to navbar
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 100) {
                navbar.style.background = 'rgba(255, 255, 255, 0.98)';
            } else {
                navbar.style.background = 'rgba(255, 255, 255, 0.95)';
            }
        });
    </script>
</body>
</html>
