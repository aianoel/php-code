<?php
session_start();
require_once '../includes/config.php';

// Initialize chat session if not exists
if (!isset($_SESSION['chat_id'])) {
    $_SESSION['chat_id'] = uniqid('chat_');
}

// Get the chat ID
$chatId = $_SESSION['chat_id'];

// Handle incoming message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $userMessage = trim($_POST['message']);
    $timestamp = date('Y-m-d H:i:s');
    
    // Store user message in database
    try {
        $stmt = $pdo->prepare("INSERT INTO chat_messages (chat_id, sender, message, timestamp) VALUES (?, 'user', ?, ?)");
        $stmt->execute([$chatId, $userMessage, $timestamp]);
        
        // Generate AI response
        $aiResponse = generateAIResponse($userMessage);
        $timestamp = date('Y-m-d H:i:s');
        
        // Store AI response in database
        $stmt = $pdo->prepare("INSERT INTO chat_messages (chat_id, sender, message, timestamp) VALUES (?, 'ai', ?, ?)");
        $stmt->execute([$chatId, $aiResponse, $timestamp]);
        
        // Return both messages as JSON
        echo json_encode([
            'status' => 'success',
            'user_message' => [
                'text' => $userMessage,
                'timestamp' => $timestamp
            ],
            'ai_response' => [
                'text' => $aiResponse,
                'timestamp' => $timestamp
            ]
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
    exit;
}

// Get chat history
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'history') {
    try {
        $stmt = $pdo->prepare("SELECT sender, message, timestamp FROM chat_messages WHERE chat_id = ? ORDER BY timestamp ASC");
        $stmt->execute([$chatId]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'status' => 'success',
            'messages' => $messages
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
    exit;
}

/**
 * Generate an AI response based on user input
 * 
 * @param string $userMessage The user's message
 * @return string The AI response
 */
function generateAIResponse($userMessage) {
    // Convert to lowercase for easier matching
    $message = strtolower($userMessage);
    
    // Common greetings
    if (preg_match('/(hello|hi|hey|greetings)/i', $message)) {
        return "Hello! I'm EduBot, your virtual assistant. How can I help you with enrollment, programs, or school information today?";
    }
    
    // Enrollment questions
    if (strpos($message, 'enroll') !== false || strpos($message, 'admission') !== false || strpos($message, 'apply') !== false) {
        return "Our enrollment process is simple! You can start by visiting our Enrollment page where you'll complete a 5-step process: Personal Information, Academic Information, Document Upload, Payment, and Review. Would you like me to guide you through any specific step?";
    }
    
    // Program questions
    if (strpos($message, 'program') !== false || strpos($message, 'course') !== false || strpos($message, 'curriculum') !== false) {
        return "We offer comprehensive programs for Elementary (K-6), Junior High School (7-10), and Senior High School (11-12) with specialized tracks including STEM, ABM, HUMSS, and TVL. Which level are you interested in learning more about?";
    }
    
    // Elementary specific
    if (strpos($message, 'elementary') !== false) {
        return "Our Elementary program (K-6) focuses on building strong foundations in literacy, numeracy, and character development through engaging and interactive learning methods. Core subjects include English, Filipino, Mathematics, Science, Araling Panlipunan, MAPEH, and ESP.";
    }
    
    // Junior High specific
    if (strpos($message, 'junior') !== false) {
        return "Our Junior High School program (Grades 7-10) provides a comprehensive curriculum preparing students for senior high school with emphasis on critical thinking and problem-solving skills. Students take core subjects while developing leadership skills through various extracurricular activities.";
    }
    
    // Senior High specific
    if (strpos($message, 'senior') !== false || strpos($message, 'shs') !== false) {
        return "Our Senior High School program (Grades 11-12) offers specialized tracks: STEM (Science, Technology, Engineering, Mathematics), ABM (Accountancy, Business, Management), HUMSS (Humanities and Social Sciences), and TVL (Technical-Vocational-Livelihood). Each track prepares students for specific college courses and career paths.";
    }
    
    // Specific tracks
    if (strpos($message, 'stem') !== false) {
        return "The STEM track focuses on advanced concepts in Science, Technology, Engineering, and Mathematics. It's ideal for students planning to pursue careers in engineering, medicine, architecture, IT, and other science-related fields.";
    }
    
    if (strpos($message, 'abm') !== false) {
        return "The ABM track covers Accountancy, Business, and Management. Students learn business concepts, financial management, and entrepreneurship skills, preparing them for business-related college courses and careers.";
    }
    
    if (strpos($message, 'humss') !== false) {
        return "The HUMSS track focuses on Humanities and Social Sciences, covering subjects like literature, history, politics, and behavioral sciences. It's ideal for students interested in law, teaching, communication arts, and social sciences.";
    }
    
    if (strpos($message, 'tvl') !== false) {
        return "The TVL track provides Technical-Vocational-Livelihood training that gives students practical skills they can use for immediate employment after graduation. We offer various specializations with industry partnerships for work immersion.";
    }
    
    // Fees and payments
    if (strpos($message, 'fee') !== false || strpos($message, 'tuition') !== false || strpos($message, 'cost') !== false || strpos($message, 'payment') !== false) {
        return "Tuition fees vary by grade level. For Elementary, fees range from ₱50,000-70,000 annually. Junior High School is approximately ₱60,000-80,000, and Senior High School is ₱70,000-90,000 depending on the track. We offer installment payment options and scholarships for qualifying students. For detailed information, please contact our Accounting Office.";
    }
    
    // Requirements
    if (strpos($message, 'requirement') !== false || strpos($message, 'document') !== false || strpos($message, 'need to submit') !== false) {
        return "For enrollment, you'll need to submit: 1) Birth Certificate, 2) Report Card/Form 138 from previous school, 3) Certificate of Good Moral Character, 4) 2x2 ID pictures, 5) Proof of residency, and 6) Medical certificate. Additional requirements may apply for transferees and foreign students.";
    }
    
    // Contact information
    if (strpos($message, 'contact') !== false || strpos($message, 'phone') !== false || strpos($message, 'email') !== false || strpos($message, 'address') !== false) {
        return "You can reach us at (02) 8123-4567 or email info@edumanage.edu.ph. Our campus is located at 123 Education St., Manila. Our office hours are Monday to Friday, 8:00 AM to 5:00 PM.";
    }
    
    // Schedule and calendar
    if (strpos($message, 'schedule') !== false || strpos($message, 'calendar') !== false || strpos($message, 'school year') !== false || strpos($message, 'class hours') !== false) {
        return "Our school year typically runs from August to May. Classes are held Monday to Friday, 7:30 AM to 4:30 PM. We follow the Department of Education's academic calendar with scheduled breaks for Christmas, Holy Week, and summer vacation.";
    }
    
    // Facilities
    if (strpos($message, 'facilities') !== false || strpos($message, 'campus') !== false || strpos($message, 'building') !== false) {
        return "Our campus features modern facilities including air-conditioned classrooms, science and computer laboratories, library, gymnasium, cafeteria, and outdoor sports areas. We also have specialized rooms for music, arts, and technical-vocational subjects.";
    }
    
    // Faculty
    if (strpos($message, 'teacher') !== false || strpos($message, 'faculty') !== false || strpos($message, 'staff') !== false || strpos($message, 'professor') !== false) {
        return "Our faculty consists of licensed teachers with advanced degrees and specialized training in their respective fields. Our teacher-to-student ratio is maintained at 1:25 to ensure quality education and personalized attention.";
    }
    
    // Extracurricular
    if (strpos($message, 'extracurricular') !== false || strpos($message, 'club') !== false || strpos($message, 'activity') !== false || strpos($message, 'sport') !== false) {
        return "We offer various extracurricular activities including sports (basketball, volleyball, swimming), academic clubs (math, science, robotics), performing arts (choir, dance, theater), and special interest groups. These activities help develop students' talents and social skills.";
    }
    
    // Default response for unrecognized queries
    return "Thank you for your question. I'm still learning and may not have all the answers. For specific information about our school, programs, or enrollment process, please contact our office at (02) 8123-4567 or email info@edumanage.edu.ph. Is there anything else I can help with?";
}
?>
