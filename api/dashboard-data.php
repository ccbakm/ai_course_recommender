<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

if (!is_logged_in()) json_response(['error' => 'Unauthorized'], 401);

$uid     = $_SESSION['user_id'];
$plan_id = intval($_GET['plan_id'] ?? 0);

if (!$plan_id) json_response(['error' => 'plan_id required'], 400);

try {
    // Verify plan belongs to user
    $stmt = $db->prepare("SELECT id, title FROM learning_plans WHERE id = ? AND user_id = ?");
    $stmt->execute([$plan_id, $uid]);
    $plan = $stmt->fetch();
    if (!$plan) json_response(['error' => 'Plan not found'], 404);

    // ── 1. Basic stats ────────────────────────────────────────────────────────
    $stmt = $db->prepare("SELECT COUNT(*) FROM progress WHERE user_id = ? AND plan_id = ? AND completed = 1");
    $stmt->execute([$uid, $plan_id]);
    $topics_completed = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("
        SELECT COUNT(*) FROM quiz_attempts qa
        JOIN quizzes q ON qa.quiz_id = q.id
        WHERE qa.user_id = ? AND q.plan_id = ?
    ");
    $stmt->execute([$uid, $plan_id]);
    $quizzes_taken = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("
        SELECT AVG(qa.score / qa.total_questions * 100)
        FROM quiz_attempts qa
        JOIN quizzes q ON qa.quiz_id = q.id
        WHERE qa.user_id = ? AND q.plan_id = ?
    ");
    $stmt->execute([$uid, $plan_id]);
    $avg_score = round((float)($stmt->fetchColumn() ?? 0), 1);

    // Total questions ever answered (from answer_log)
    $stmt = $db->prepare("SELECT COUNT(*) FROM answer_log WHERE user_id = ? AND plan_id = ?");
    $stmt->execute([$uid, $plan_id]);
    $total_answered = (int)$stmt->fetchColumn();

    // ── 2. Streak ─────────────────────────────────────────────────────────────
    $stmt = $db->prepare("
        SELECT DISTINCT DATE(completed_at) as d
        FROM progress
        WHERE user_id = ? AND plan_id = ? AND completed = 1 AND completed_at IS NOT NULL
        ORDER BY d DESC
    ");
    $stmt->execute([$uid, $plan_id]);
    $active_dates = array_column($stmt->fetchAll(), 'd');

    $streak     = 0;
    $check_date = date('Y-m-d');
    if (!in_array($check_date, $active_dates)) {
        $check_date = date('Y-m-d', strtotime('-1 day'));
    }
    foreach ($active_dates as $d) {
        if ($d === $check_date) {
            $streak++;
            $check_date = date('Y-m-d', strtotime('-1 day', strtotime($check_date)));
        } elseif ($d < $check_date) {
            break;
        }
    }

    // ── 3. Weekly progress ────────────────────────────────────────────────────
    $stmt = $db->prepare("
        SELECT
            DATE_SUB(DATE(completed_at), INTERVAL WEEKDAY(DATE(completed_at)) DAY) AS week_start,
            COUNT(*) AS completed
        FROM progress
        WHERE user_id = ? AND plan_id = ? AND completed = 1
          AND completed_at IS NOT NULL
          AND completed_at >= DATE_SUB(CURDATE(), INTERVAL 56 DAY)
        GROUP BY week_start ORDER BY week_start
    ");
    $stmt->execute([$uid, $plan_id]);
    $raw_weekly = [];
    foreach ($stmt->fetchAll() as $r) $raw_weekly[$r['week_start']] = (int)$r['completed'];

    $weekly_progress = [];
    for ($w = 7; $w >= 0; $w--) {
        $monday = date('Y-m-d', strtotime("monday this week -{$w} weeks"));
        $weekly_progress[] = [
            'week'      => date('M j', strtotime($monday)),
            'completed' => $raw_weekly[$monday] ?? 0,
        ];
    }

    // ── 4. Score trend ────────────────────────────────────────────────────────
    $stmt = $db->prepare("
        SELECT qa.completed_at, qa.score, qa.total_questions, q.quiz_type
        FROM quiz_attempts qa
        JOIN quizzes q ON qa.quiz_id = q.id
        WHERE qa.user_id = ? AND q.plan_id = ?
        ORDER BY qa.completed_at ASC
    ");
    $stmt->execute([$uid, $plan_id]);
    $score_trend = [];
    foreach ($stmt->fetchAll() as $r) {
        $score_trend[] = [
            'date'      => date('M j', strtotime($r['completed_at'])),
            'score_pct' => $r['total_questions'] > 0
                            ? round(($r['score'] / $r['total_questions']) * 100) : 0,
            'quiz_type' => $r['quiz_type'],
        ];
    }

    // ── 5. Quiz type averages ─────────────────────────────────────────────────
    $stmt = $db->prepare("
        SELECT q.quiz_type, AVG(qa.score / qa.total_questions * 100) AS avg_pct
        FROM quiz_attempts qa
        JOIN quizzes q ON qa.quiz_id = q.id
        WHERE qa.user_id = ? AND q.plan_id = ?
        GROUP BY q.quiz_type
    ");
    $stmt->execute([$uid, $plan_id]);
    $type_avgs = ['topic' => null, 'step' => null, 'plan' => null];
    foreach ($stmt->fetchAll() as $r) {
        $type_avgs[$r['quiz_type']] = round((float)$r['avg_pct'], 1);
    }

    // ── 6. Weak & strong areas from answer_log (every question ever answered) ─
    $stmt = $db->prepare("
        SELECT
            quiz_type, step_index, topic_index,
            COUNT(*)                                          AS total,
            SUM(CASE WHEN is_correct = 0 THEN 1 ELSE 0 END) AS wrong_count,
            SUM(is_correct)                                   AS correct_count
        FROM answer_log
        WHERE user_id = ? AND plan_id = ?
        GROUP BY quiz_type, step_index, topic_index
        HAVING COUNT(*) >= 3
        ORDER BY SUM(CASE WHEN is_correct = 0 THEN 1 ELSE 0 END) / COUNT(*) DESC
    ");
    $stmt->execute([$uid, $plan_id]);
    $area_rows = $stmt->fetchAll();

    // Fetch plan_data once for labels
    $stmt = $db->prepare("SELECT plan_data FROM learning_plans WHERE id = ?");
    $stmt->execute([$plan_id]);
    $pd = json_decode($stmt->fetchColumn(), true);

    $weak_areas   = [];
    $strong_areas = [];

    foreach ($area_rows as $row) {
        $qtype     = $row['quiz_type'];
        $si        = (int)$row['step_index'];
        $ti        = (int)$row['topic_index'];
        $total     = (int)$row['total'];
        $wrong     = (int)$row['wrong_count'];
        $wrong_pct = round(($wrong / $total) * 100);

        // Build label from plan data
        if ($qtype === 'plan') {
            $label = 'Final Exam';
        } elseif ($qtype === 'step') {
            $step_title = $pd['list'][$si]['title'] ?? ('Step ' . ($si + 1));
            $label = 'Step ' . ($si + 1) . ': ' . $step_title;
        } else {
            $t_raw      = $pd['list'][$si]['topics'][$ti] ?? null;
            $topic_text = $t_raw ? (is_string($t_raw) ? $t_raw : ($t_raw['text'] ?? 'Topic')) : 'Topic';
            if (strlen($topic_text) > 45) $topic_text = substr($topic_text, 0, 42) . '…';
            $label = 'S' . ($si + 1) . ' › ' . $topic_text;
        }

        $entry = [
            'label'      => $label,
            'quiz_type'  => $qtype,
            'wrong_pct'  => $wrong_pct,
            'correct_pct'=> 100 - $wrong_pct,
            'wrong'      => $wrong,
            'total'      => $total,
        ];

        if ($wrong_pct >= 35) {
            $weak_areas[] = $entry;
        } elseif ($wrong_pct <= 25 && $total >= 5) {
            $strong_areas[] = $entry;
        }
    }

    // Weak: worst first | Strong: best first
    usort($weak_areas,   fn($a, $b) => $b['wrong_pct']   - $a['wrong_pct']);
    usort($strong_areas, fn($a, $b) => $a['wrong_pct']   - $b['wrong_pct']);
    $weak_areas   = array_slice($weak_areas,   0, 12);
    $strong_areas = array_slice($strong_areas, 0, 8);

    json_response([
        'plan_title'      => $plan['title'],
        'stats'           => [
            'topics_completed' => $topics_completed,
            'quizzes_taken'    => $quizzes_taken,
            'avg_score'        => $avg_score,
            'streak'           => $streak,
            'total_answered'   => $total_answered,
        ],
        'weekly_progress' => $weekly_progress,
        'score_trend'     => $score_trend,
        'quiz_type_avg'   => $type_avgs,
        'weak_areas'      => $weak_areas,
        'strong_areas'    => $strong_areas,
    ]);

} catch (PDOException $e) {
    error_log("Dashboard data error: " . $e->getMessage());
    json_response(['error' => 'Database error'], 500);
}
