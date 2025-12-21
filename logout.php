<?php
/**
 * Logout Page
 * Handles user logout
 */

require_once 'auth.php';

// Logout the user
logoutUser();

// Redirect to login page
header('Location: login.php');
exit;
?>
