<?php
/**
 * Reports Redirect
 * 
 * This file redirects from reports.php to reports-analytics.php
 * to maintain compatibility with sidebar navigation links.
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set a flash message to inform the user about the redirect
$_SESSION['flash_message'] = [
    'type' => 'info',
    'message' => 'You have been redirected to the Reports & Analytics page.'
];

// Redirect to the correct reports page
header('Location: reports-analytics.php');
exit;
?>
