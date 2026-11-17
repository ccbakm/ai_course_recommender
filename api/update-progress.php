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
$step_index = intval($input['step_index'] ?? 0);
$topic_index = intval($input['topic_index'] ?? 0);
$completed = isset($input['completed']) ? (bool)$input['completed'] : false;

if (!$plan_id) {
    json_response(['error' => 'Plan ID required'], 400);
}

try {
    // Verify plan belongs to user
    $stmt = $db->prepare("SELECT id FROM learning_plans WHERE id = ? AND user_id = ?");
    $stmt->execute([$plan_id, $_SESSION['user_id']]);
    
    if (!$stmt->fetch()) {
        json_response(['error' => 'Plan not found'], 404);
    }
    
    // Check if progress record exists
    $stmt = $db->prepare("
        SELECT id FROM progress 
        WHERE plan_id = ? AND step_index = ? AND topic_index = ?
    ");
    $stmt->execute([$plan_id, $step_index, $topic_index]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Update existing record
        $stmt = $db->prepare("
            UPDATE progress 
            SET completed = ?, completed_at = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $completed ? 1 : 0,
            $completed ? date('Y-m-d H:i:s') : null,
            $existing['id']
        ]);
    } else {
        // Insert new record
        $stmt = $db->prepare("
            INSERT INTO progress 
            (user_id, plan_id, step_index, topic_index, completed, completed_at) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $plan_id,
            $step_index,
            $topic_index,
            $completed ? 1 : 0,
            $completed ? date('Y-m-d H:i:s') : null
        ]);
    }
    
    // Get the plan data to count actual topics
    $stmt = $db->prepare("SELECT plan_data FROM learning_plans WHERE id = ?");
    $stmt->execute([$plan_id]);
    $plan = $stmt->fetch();
    $plan_data = json_decode($plan['plan_data'], true);
    
    // Count actual topics in the plan
    $total_topics = 0;
    if (isset($plan_data['list']) && is_array($plan_data['list'])) {
        foreach ($plan_data['list'] as $step) {
            if (isset($step['topics']) && is_array($step['topics'])) {
                $total_topics += count($step['topics']);
            }
        }
    }
    
    // Get completed count from progress table (only for topics that exist in plan)
    $stmt = $db->prepare("
        SELECT COUNT(*) as completed
        FROM progress
        WHERE plan_id = ? AND completed = 1
    ");
    $stmt->execute([$plan_id]);
    $result = $stmt->fetch();
    $completed_topics = $result['completed'];
    
    $completion_rate = $total_topics > 0 
        ? round(($completed_topics / $total_topics) * 100, 1) 
        : 0;
    
    json_response([
        'success' => true,
        'completed' => $completed,
        'total_topics' => $total_topics,
        'completed_topics' => $completed_topics,
        'completion_rate' => $completion_rate
    ]);
    
} catch (PDOException $e) {
    error_log("Error updating progress: " . $e->getMessage());
    json_response(['error' => 'Failed to update progress'], 500);
}