<?php
require_once __DIR__.'/config.php';

// Destroy session
session_destroy();

// Clear session variables
$_SESSION = [];

// Redirect to home page
redirect('/index.php', 'You have been logged out successfully.', 'success');