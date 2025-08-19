<?php
/**
 * User Management Redirect
 * 
 * This file redirects from users.php to user-management.php
 * to maintain compatibility with sidebar navigation links.
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set a flash message to inform the user about the redirect
$_SESSION['flash_message'] = [
    'type' => 'info',
    'message' => 'You have been redirected to the User Management page.'
];

// Redirect to the correct user management page
header('Location: user-management.php');
exit;
?>
