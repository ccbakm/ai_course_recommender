<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    json_response(['error' => 'Unauthorized'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$input   = json_decode(file_get_contents('php://input'), true);
$quiz_id = intval($input['quiz_id'] ?? 0);
$answers = $input['answers'] ?? [];

if (!$quiz_id || !is_array($answers)) {
    json_response(['error' => 'Quiz ID and answers required'], 400);
}

try {
    // Fetch quiz + metadata needed for answer_log
    $stmt = $db->prepare("
        SELECT q.id, q.questions, q.plan_id, q.quiz_type, q.step_index, q.topic_index
        FROM quizzes q
        JOIN learning_plans lp ON q.plan_id = lp.id
        WHERE q.id = ? AND lp.user_id = ?
    ");
    $stmt->execute([$quiz_id, $_SESSION['user_id']]);
    $quiz = $stmt->fetch();

    if (!$quiz) {
        json_response(['error' => 'Quiz not found'], 404);
    }

    $questions = json_decode($quiz['questions'], true);
    $total     = count($questions);
    $score     = 0;
    $results   = [];

    foreach ($questions as $i => $q) {
        $user_answer = array_key_exists($i, $answers) ? intval($answers[$i]) : -1;
        $correct     = intval($q['correct']);
        $is_correct  = ($user_answer === $correct);
        if ($is_correct) $score++;

        $results[] = [
            'question'    => $q['question'],
            'options'     => $q['options'],
            'user_answer' => $user_answer,
            'correct'     => $correct,
            'is_correct'  => $is_correct,
            'explanation' => $q['explanation'] ?? '',
        ];
    }

    // Save attempt
    $stmt = $db->prepare("
        INSERT INTO quiz_attempts (quiz_id, user_id, answers, score, total_questions)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$quiz_id, $_SESSION['user_id'], json_encode($answers), $score, $total]);

    // Write every question answer to answer_log (permanent — survives quiz regeneration)
    $logStmt = $db->prepare("
        INSERT INTO answer_log
            (user_id, plan_id, quiz_type, step_index, topic_index, question_text, correct_answer, user_answer, is_correct)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($questions as $i => $q) {
        $ua = array_key_exists($i, $answers) ? intval($answers[$i]) : -1;
        $ic = ($ua === intval($q['correct'])) ? 1 : 0;
        $logStmt->execute([
            $_SESSION['user_id'],
            $quiz['plan_id'],
            $quiz['quiz_type'],
            $quiz['step_index'],
            $quiz['topic_index'],
            $q['question'],
            intval($q['correct']),
            $ua,
            $ic,
        ]);
    }

    $percentage = $total > 0 ? round(($score / $total) * 100) : 0;

    json_response([
        'success'    => true,
        'score'      => $score,
        'total'      => $total,
        'percentage' => $percentage,
        'results'    => $results,
        'passed'     => $percentage >= 60,
    ]);

} catch (PDOException $e) {
    error_log("Quiz submit error: " . $e->getMessage());
    json_response(['error' => 'Failed to submit quiz'], 500);
}
