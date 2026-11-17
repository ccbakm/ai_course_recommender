<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

if (!is_logged_in())                          json_response(['error' => 'Unauthorized'], 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST')    json_response(['error' => 'Method not allowed'], 405);

$input        = json_decode(file_get_contents('php://input'), true);
$weak_areas   = $input['weak_areas']   ?? [];
$strong_areas = $input['strong_areas'] ?? [];
$stats        = $input['stats']        ?? [];

if (($stats['total_answered'] ?? 0) < 5) {
    json_response(['success' => true, 'analysis' => null, 'message' => 'not_enough_data']);
}

// Build prompt sections
$weak_text = '';
foreach ($weak_areas as $i => $a) {
    $weak_text .= ($i + 1) . ". \"{$a['label']}\" ({$a['quiz_type']} quiz) — "
                . "{$a['wrong_pct']}% wrong ({$a['wrong']} / {$a['total']} questions answered across all attempts)\n";
}
if (!$weak_text) $weak_text = "No significantly weak areas detected yet.\n";

$strong_text = '';
foreach ($strong_areas as $i => $a) {
    $strong_text .= ($i + 1) . ". \"{$a['label']}\" ({$a['quiz_type']} quiz) — "
                  . "{$a['correct_pct']}% correct ({$a['total']} questions answered)\n";
}
if (!$strong_text) $strong_text = "No clearly strong areas detected yet.\n";

$total_answered = $stats['total_answered'] ?? 0;
$avg_score      = $stats['avg_score']      ?? 0;
$quizzes_taken  = $stats['quizzes_taken']  ?? 0;

$prompt = <<<PROMPT
You are an expert learning coach. Analyze a student's complete quiz history — every question they have ever answered, including retakes and regenerated quizzes.

Performance summary:
- Total questions ever answered: {$total_answered}
- Total quiz sessions: {$quizzes_taken}
- Overall average score: {$avg_score}%

Areas where the student STRUGGLES (≥35% wrong, sorted worst first):
{$weak_text}

Areas where the student EXCELS (≤25% wrong, ≥5 questions answered, sorted best first):
{$strong_text}

Provide a concise, honest, and encouraging analysis. Return ONLY this exact JSON (no markdown, no code blocks):
{
  "weak_areas": [
    {
      "area": "Short area name",
      "wrong_pct": 75,
      "issue": "One sentence explaining the likely knowledge gap or misconception",
      "tip": "One specific, actionable study tip to fix it"
    }
  ],
  "strong_areas": [
    {
      "area": "Short area name",
      "correct_pct": 90,
      "insight": "One sentence on why this strength is valuable and how to keep building on it"
    }
  ],
  "overall_insight": "2-3 sentences summarising the student's overall learning pattern based on all their answers",
  "top_recommendation": "The single most impactful thing this student should do right now to improve"
}

Limit weak_areas to top 5. Limit strong_areas to top 3. Be specific — reference the actual area names.
PROMPT;

$url      = "https://generativelanguage.googleapis.com/v1beta/models/{$GEMINI_MODEL}:generateContent?key=" . urlencode($GEMINI_API_KEY);
$payload  = ['contents' => [['parts' => [['text' => $prompt]]]]];
$CA_BUNDLE = 'C:\xampp\php\extras\ssl\cacert.pem';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST           => 1,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_TIMEOUT        => 45,
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
    error_log("Weakpoint analysis error: HTTP $code, cURL: $err");
    json_response(['error' => 'Failed to reach analysis service. Please try again.'], 500);
}

$data = json_decode($res, true);
$text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
$jt   = trim(preg_replace('/^```(?:json)?|```$/m', '', $text));

$parsed = json_decode($jt, true);
if (!is_array($parsed)) {
    error_log("Weakpoint analysis: bad JSON: $jt");
    json_response(['error' => 'Unexpected response. Please try again.'], 500);
}

json_response(['success' => true, 'analysis' => $parsed]);
