<?php
// NOTE: copy this file to config.php and put your real keys there. config.php is git-ignored.

// ===== APPLICATION CONFIGURATION =====

// Application Settings
$APP_BASE = '/ai_course_recommender';
$APP_NAME = 'AI Course Recommender';

// Database Configuration
$DB_ENABLED = true;  // ✅ ENABLED NOW
$DB_HOST = 'localhost';
$DB_NAME = 'ai_courses';
$DB_USER = 'root';
$DB_PASS = '';

// API Keys
$GEMINI_API_KEY = 'YOUR_GEMINI_API_KEY_HERE'; // Get one at https://aistudio.google.com/apikey
$GEMINI_MODEL   = 'gemini-2.5-flash';
$YOUTUBE_API_KEY = ''; // Optional: YouTube Data API v3 key for video suggestions (leave empty to disable)

// Session Configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
session_start();

// ===== DATABASE CONNECTION =====
$db = null;
if ($DB_ENABLED) {
    try {
        $db = new PDO(
            "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
            $DB_USER,
            $DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        // Don't expose database errors to users
        if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Database connection failed. Please try again later.']);
            exit;
        }
    }
}

// ===== HELPER FUNCTIONS =====

/**
 * Generate URL path with app base
 */
function url_path($path) {
    global $APP_BASE;
    return rtrim($APP_BASE, '/') . $path;
}

/**
 * Check if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get logged in user data
 * RENAMED from get_current_user() to avoid PHP built-in function conflict
 */
function get_user_data() {
    global $db;
    if (!is_logged_in() || !$db) return null;
    
    $stmt = $db->prepare("SELECT id, email, name, skill_level, created_at FROM users WHERE id = ? AND is_active = 1");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

/**
 * Require authentication (redirect if not logged in)
 */
function require_auth($redirect = '/login.php') {
    if (!is_logged_in()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . url_path($redirect));
        exit;
    }
}

/**
 * Redirect helper
 */
function redirect($path, $message = null, $type = 'info') {
    if ($message) {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
    }
    header('Location: ' . url_path($path));
    exit;
}

/**
 * Get and clear flash message
 */
function get_flash() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        return ['message' => $message, 'type' => $type];
    }
    return null;
}

/**
 * Sanitize output for HTML
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Generate CSRF token
 */
function csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verify_csrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * JSON response helper
 */
function json_response($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}