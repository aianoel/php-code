<?php
session_start();
require_once '../includes/config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    $uploadDir = '../uploads/landing-page/';
    
    // Create upload directory if it doesn't exist
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $file = $_FILES['image'];
    $fileName = $file['name'];
    $fileTmpName = $file['tmp_name'];
    $fileSize = $file['size'];
    $fileError = $file['error'];
    $fileType = $file['type'];
    
    // Get file extension
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    if ($fileError === 0) {
        if (in_array($fileExt, $allowedExtensions)) {
            if ($fileSize < 5000000) { // 5MB limit
                // Generate unique filename
                $newFileName = uniqid('landing_', true) . '.' . $fileExt;
                $fileDestination = $uploadDir . $newFileName;
                
                if (move_uploaded_file($fileTmpName, $fileDestination)) {
                    // Return relative path for database storage
                    $relativePath = 'uploads/landing-page/' . $newFileName;
                    echo json_encode([
                        'success' => true,
                        'url' => $relativePath,
                        'message' => 'Image uploaded successfully'
                    ]);
                } else {
                    echo json_encode(['error' => 'Failed to move uploaded file']);
                }
            } else {
                echo json_encode(['error' => 'File size too large (max 5MB)']);
            }
        } else {
            echo json_encode(['error' => 'Invalid file type. Allowed: jpg, jpeg, png, gif, webp']);
        }
    } else {
        echo json_encode(['error' => 'Upload error occurred']);
    }
} else {
    echo json_encode(['error' => 'No file uploaded']);
}
?>
