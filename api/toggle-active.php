<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    json_response(['error' => 'Unauthorized'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$plan_id = intval($input['plan_id'] ?? 0);

if (!$plan_id) {
    json_response(['error' => 'Plan ID is required'], 400);
}

try {
    // Verify plan belongs to user
    $stmt = $db->prepare("SELECT id FROM learning_plans WHERE id = ? AND user_id = ?");
    $stmt->execute([$plan_id, $_SESSION['user_id']]);
    
    if (!$stmt->fetch()) {
        json_response(['error' => 'Plan not found'], 404);
    }
    
    // Deactivate all plans
    $stmt = $db->prepare("UPDATE learning_plans SET is_active = 0 WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    
    // Activate this plan
    $stmt = $db->prepare("UPDATE learning_plans SET is_active = 1 WHERE id = ?");
    $stmt->execute([$plan_id]);
    
    json_response(['success' => true, 'message' => 'Plan activated successfully']);
    
} catch (PDOException $e) {
    error_log("Error toggling active plan: " . $e->getMessage());
    json_response(['error' => 'Failed to update plan'], 500);
}