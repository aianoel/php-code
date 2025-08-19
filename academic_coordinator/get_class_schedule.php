<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Ensure user is authenticated and has the correct role
require_role('academic_coordinator');

// Check if class_id is provided
if (!isset($_GET['class_id']) || empty($_GET['class_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Class ID is required']);
    exit;
}

$class_id = intval($_GET['class_id']);

try {
    // Get class schedule information
    $stmt = $pdo->prepare("
        SELECT schedule_days, schedule_time_start, schedule_time_end, room
        FROM classes
        WHERE id = ?
    ");
    $stmt->execute([$class_id]);
    $class = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$class) {
        http_response_code(404);
        echo json_encode(['error' => 'Class not found']);
        exit;
    }
    
    // Return class schedule data as JSON
    header('Content-Type: application/json');
    echo json_encode($class);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
