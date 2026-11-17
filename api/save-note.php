<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    json_response(['error' => 'Unauthorized'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$input       = json_decode(file_get_contents('php://input'), true);
$plan_id     = intval($input['plan_id']     ?? 0);
$step_index  = intval($input['step_index']  ?? 0);
$topic_index = intval($input['topic_index'] ?? 0);
$note        = trim($input['note'] ?? '');

if (!$plan_id) {
    json_response(['error' => 'Plan ID required'], 400);
}

try {
    $stmt = $db->prepare("SELECT id FROM learning_plans WHERE id = ? AND user_id = ?");
    $stmt->execute([$plan_id, $_SESSION['user_id']]);
    if (!$stmt->fetch()) {
        json_response(['error' => 'Plan not found'], 404);
    }

    $db->exec("CREATE TABLE IF NOT EXISTS topic_notes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        plan_id INT NOT NULL,
        step_index INT NOT NULL,
        topic_index INT NOT NULL,
        note TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_note (user_id, plan_id, step_index, topic_index)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if ($note === '') {
        $stmt = $db->prepare("DELETE FROM topic_notes WHERE user_id = ? AND plan_id = ? AND step_index = ? AND topic_index = ?");
        $stmt->execute([$_SESSION['user_id'], $plan_id, $step_index, $topic_index]);
    } else {
        $stmt = $db->prepare("INSERT INTO topic_notes (user_id, plan_id, step_index, topic_index, note)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE note = VALUES(note), updated_at = NOW()");
        $stmt->execute([$_SESSION['user_id'], $plan_id, $step_index, $topic_index, $note]);
    }

    json_response(['success' => true]);

} catch (PDOException $e) {
    error_log("Error saving note: " . $e->getMessage());
    json_response(['error' => 'Failed to save note'], 500);
}
