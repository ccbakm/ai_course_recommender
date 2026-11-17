<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!is_logged_in()) {
    json_response(['error' => 'You must be logged in to save plans', 'auth_required' => true], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

$title = trim($input['title'] ?? '');
$domain = trim($input['domain'] ?? '');
$skill_level = trim($input['skill_level'] ?? '');
$weekly_hours = trim($input['weekly_hours'] ?? '');
$goal = trim($input['goal'] ?? '');
$plan_data = $input['plan_data'] ?? null;

// Validation
if (empty($title)) {
    json_response(['error' => 'Plan title is required'], 400);
}

if (empty($plan_data)) {
    json_response(['error' => 'Plan data is required'], 400);
}

try {
    // Check if user wants to set this as active plan
    $is_active = $input['is_active'] ?? false;
    
    // If setting as active, deactivate all other plans
    if ($is_active) {
        $stmt = $db->prepare("UPDATE learning_plans SET is_active = 0 WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    }
    
    // Insert the new plan
    $stmt = $db->prepare("
        INSERT INTO learning_plans 
        (user_id, title, domain, skill_level, weekly_hours, goal, plan_data, is_active) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $_SESSION['user_id'],
        $title,
        $domain,
        $skill_level,
        $weekly_hours,
        $goal,
        json_encode($plan_data),
        $is_active ? 1 : 0
    ]);
    
    $plan_id = $db->lastInsertId();
    
    json_response([
        'success' => true,
        'message' => 'Learning plan saved successfully!',
        'plan_id' => $plan_id
    ], 201);
    
} catch (PDOException $e) {
    error_log("Error saving plan: " . $e->getMessage());
    json_response(['error' => 'Failed to save plan. Please try again.'], 500);
}