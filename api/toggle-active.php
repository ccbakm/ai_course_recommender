
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
    // Verify that the plan belongs to the logged-in user
    $stmt = $db->prepare("
        SELECT is_active
        FROM learning_plans
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$plan_id, $_SESSION['user_id']]);
    $plan = $stmt->fetch();

    if (!$plan) {
        json_response(['error' => 'Plan not found'], 404);
    }

    $currently_active = (int)$plan['is_active'] === 1;

    $db->beginTransaction();

    if ($currently_active) {
        // Deactivate the selected plan
        $stmt = $db->prepare("
            UPDATE learning_plans
            SET is_active = 0
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$plan_id, $_SESSION['user_id']]);

        $message = 'Plan deactivated successfully';
        $new_status = false;

    } else {
        // Only one plan can be active at a time
        $stmt = $db->prepare("
            UPDATE learning_plans
            SET is_active = 0
            WHERE user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);

        // Activate the selected plan
        $stmt = $db->prepare("
            UPDATE learning_plans
            SET is_active = 1
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$plan_id, $_SESSION['user_id']]);

        $message = 'Plan activated successfully';
        $new_status = true;
    }

    $db->commit();

    json_response([
        'success' => true,
        'message' => $message,
        'is_active' => $new_status
    ]);

} catch (PDOException $e) {
    if ($db && $db->inTransaction()) {
        $db->rollBack();
    }

    error_log('Error toggling active plan: ' . $e->getMessage());
    json_response(['error' => 'Failed to update plan'], 500);
}
