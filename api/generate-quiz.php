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
$plan_id     = intval($input['plan_id'] ?? 0);
$quiz_type   = $input['quiz_type'] ?? 'step';
$step_index  = isset($input['step_index'])  ? intval($input['step_index'])  : -1;
$topic_index = isset($input['topic_index']) ? intval($input['topic_index']) : -1;
$force_regen = !empty($input['force_regenerate']);

if (!$plan_id) json_response(['error' => 'Plan ID required'], 400);
if (!in_array($quiz_type, ['topic','step','plan'])) json_response(['error' => 'Invalid quiz type'], 400);
if ($quiz_type === 'topic' && ($step_index < 0 || $topic_index < 0)) json_response(['error' => 'step_index and topic_index required for topic quiz'], 400);
if ($quiz_type === 'step'  && $step_index < 0) json_response(['error' => 'step_index required for step quiz'], 400);

try {
    // Verify plan belongs to user
    $stmt = $db->prepare("SELECT plan_data, title, domain, skill_level FROM learning_plans WHERE id = ? AND user_id = ?");
    $stmt->execute([$plan_id, $_SESSION['user_id']]);
    $plan = $stmt->fetch();
    if (!$plan) json_response(['error' => 'Plan not found'], 404);

    // Check for cached quiz
    $stmt = $db->prepare("SELECT id, questions FROM quizzes WHERE plan_id = ? AND quiz_type = ? AND step_index = ? AND topic_index = ?");
    $stmt->execute([$plan_id, $quiz_type, $step_index, $topic_index]);
    $existing = $stmt->fetch();

    // If cached and not forcing regen, return it directly
    if ($existing && !$force_regen) {
        $stmt = $db->prepare("SELECT score, total_questions, completed_at FROM quiz_attempts WHERE quiz_id = ? AND user_id = ? ORDER BY score DESC LIMIT 1");
        $stmt->execute([$existing['id'], $_SESSION['user_id']]);
        $attempt = $stmt->fetch();
        json_response([
            'success'      => true,
            'quiz_id'      => $existing['id'],
            'questions'    => json_decode($existing['questions'], true),
            'best_attempt' => $attempt ?: null,
        ]);
    }

    // --- Build Gemini prompt ---
    $plan_data   = json_decode($plan['plan_data'], true);
    $plan_title  = $plan['title'];
    $domain      = $plan['domain'];
    $skill_level = $plan['skill_level'];

    if ($quiz_type === 'topic') {
        $step  = $plan_data['list'][$step_index] ?? null;
        if (!$step) json_response(['error' => 'Step not found'], 404);
        $topic = $step['topics'][$topic_index] ?? null;
        if (!$topic) json_response(['error' => 'Topic not found'], 404);

        $topic_text = is_string($topic) ? $topic : ($topic['text'] ?? '');
        $step_title = $step['title'] ?? ('Step ' . ($step_index + 1));
        $num_q      = 5;
        $min_q      = 3;

        $prompt = <<<PROMPT
You are a quiz generator. Generate exactly 5 multiple-choice questions to test understanding of this specific topic.

**Topic:** {$topic_text}
**Part of step:** {$step_title}
**Domain:** {$domain} ({$skill_level} level)

Requirements:
- Exactly 5 questions focused only on the topic above
- Test conceptual understanding, not just definitions
- 4 answer options each, exactly one correct
- Include a one-sentence explanation for the correct answer

Return ONLY valid JSON, no markdown, no code blocks:
{
  "questions": [
    {
      "question": "Question text?",
      "options": ["Option A", "Option B", "Option C", "Option D"],
      "correct": 0,
      "explanation": "Why this is correct."
    }
  ]
}
PROMPT;

    } elseif ($quiz_type === 'step') {
        $step = $plan_data['list'][$step_index] ?? null;
        if (!$step) json_response(['error' => 'Step not found'], 404);

        $step_title = $step['title'] ?? ('Step ' . ($step_index + 1));
        $topics     = [];
        foreach (($step['topics'] ?? []) as $t) {
            $topics[] = '- ' . (is_string($t) ? $t : ($t['text'] ?? ''));
        }
        $topics_str = implode("\n", $topics);
        $num_q      = 10;
        $min_q      = 5;

        $prompt = <<<PROMPT
You are a quiz generator. Generate exactly 10 multiple-choice questions covering all topics in this learning step.

**Step:** {$step_title}
**Domain:** {$domain} ({$skill_level} level)
**Topics covered:**
{$topics_str}

Requirements:
- Exactly 10 questions distributed across all topics
- Test conceptual understanding
- 4 answer options each, exactly one correct
- Include a one-sentence explanation for the correct answer

Return ONLY valid JSON, no markdown, no code blocks:
{
  "questions": [
    {
      "question": "Question text?",
      "options": ["Option A", "Option B", "Option C", "Option D"],
      "correct": 0,
      "explanation": "Why this is correct."
    }
  ]
}
PROMPT;

    } else {
        // plan-level
        $step_index  = -1;
        $topic_index = -1;
        $num_q       = 50;
        $min_q       = 20;

        $all_steps_str = '';
        foreach (($plan_data['list'] ?? []) as $si => $step) {
            $all_steps_str .= 'Step ' . ($si + 1) . ': ' . ($step['title'] ?? '') . "\n";
            foreach (($step['topics'] ?? []) as $t) {
                $txt = is_string($t) ? $t : ($t['text'] ?? '');
                $all_steps_str .= "  - {$txt}\n";
            }
        }

        $prompt = <<<PROMPT
You are a quiz generator. Generate exactly 50 multiple-choice questions as a comprehensive final exam covering the entire learning plan.

**Plan:** {$plan_title}
**Domain:** {$domain} ({$skill_level} level)
**Full curriculum:**
{$all_steps_str}

Requirements:
- Exactly 50 questions distributed proportionally across all steps
- Mix of easy, medium and hard questions
- 4 answer options each, exactly one correct
- Include a one-sentence explanation for the correct answer

Return ONLY valid JSON, no markdown, no code blocks:
{
  "questions": [
    {
      "question": "Question text?",
      "options": ["Option A", "Option B", "Option C", "Option D"],
      "correct": 0,
      "explanation": "Why this is correct."
    }
  ]
}
PROMPT;
    }

    // --- Call Gemini (BEFORE any DB changes) ---
    $url     = "https://generativelanguage.googleapis.com/v1beta/models/{$GEMINI_MODEL}:generateContent?key=" . urlencode($GEMINI_API_KEY);
    $payload = ['contents' => [['parts' => [['text' => $prompt]]]]];
    $CA_BUNDLE = 'C:\xampp\php\extras\ssl\cacert.pem';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => 1,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT        => 120,
    ]);
    if (file_exists($CA_BUNDLE)) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_CAINFO, $CA_BUNDLE);
    } else {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    }

    $res  = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($err || $code < 200 || $code >= 300) {
        error_log("Quiz generation error: HTTP $code, cURL: $err");
        json_response(['error' => 'Failed to reach AI. Please try again.'], 500);
    }

    $data = json_decode($res, true);
    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

    $jt = trim($text);
    $jt = preg_replace('/^```(?:json)?/mi', '', $jt);
    $jt = preg_replace('/```$/m', '', $jt);
    $jt = trim($jt);

    $parsed = json_decode($jt, true);

    if (!is_array($parsed) || empty($parsed['questions']) || count($parsed['questions']) < $min_q) {
        error_log("Quiz generation: got " . count($parsed['questions'] ?? []) . " questions, needed $min_q");
        json_response(['error' => 'AI returned too few questions. Please try again.'], 500);
    }

    // --- Gemini succeeded: now safely update the DB ---
    // Delete old quiz only after generation succeeds (prevents data loss on failure)
    if ($existing && $force_regen) {
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("DELETE FROM quizzes WHERE id = ?");
            $stmt->execute([$existing['id']]);

            $stmt = $db->prepare("INSERT INTO quizzes (plan_id, quiz_type, step_index, topic_index, questions) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$plan_id, $quiz_type, $step_index, $topic_index, json_encode($parsed['questions'])]);
            $quiz_id = $db->lastInsertId();

            $db->commit();
        } catch (PDOException $e) {
            $db->rollBack();
            error_log("Quiz regen DB error: " . $e->getMessage());
            json_response(['error' => 'Database error during regeneration. Your previous quiz is intact.'], 500);
        }
    } else {
        $stmt = $db->prepare("INSERT INTO quizzes (plan_id, quiz_type, step_index, topic_index, questions) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$plan_id, $quiz_type, $step_index, $topic_index, json_encode($parsed['questions'])]);
        $quiz_id = $db->lastInsertId();
    }

    json_response([
        'success'      => true,
        'quiz_id'      => $quiz_id,
        'questions'    => $parsed['questions'],
        'best_attempt' => null,
    ]);

} catch (PDOException $e) {
    error_log("Quiz DB error: " . $e->getMessage());
    json_response(['error' => 'Database error'], 500);
}
