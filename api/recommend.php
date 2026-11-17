<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

// YouTube API Configuration
// YouTube API key is read from config.php ($YOUTUBE_API_KEY). Leave it empty to disable YouTube suggestions.
$YOUTUBE_API_ENABLED = !empty($YOUTUBE_API_KEY);

// Function to search YouTube for a specific topic
function searchYouTube($topic, $apiKey) {
  $query = urlencode($topic . " tutorial");
  $url = "https://www.googleapis.com/youtube/v3/search?part=snippet&type=video&videoDuration=medium&order=relevance&maxResults=1&q={$query}&key={$apiKey}";
  
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($ch, CURLOPT_TIMEOUT, 10);
  
  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $curlError = curl_error($ch);
  curl_close($ch);
  
  // Log errors for debugging
  if ($httpCode !== 200 || !$response) {
    error_log("YouTube API Error for topic '$topic': HTTP $httpCode, Error: $curlError");
    return null;
  }
  
  $data = json_decode($response, true);
  
  // Check for API errors
  if (isset($data['error'])) {
    error_log("YouTube API Error: " . json_encode($data['error']));
    return null;
  }
  
  if (empty($data['items'])) {
    error_log("YouTube API: No results for topic '$topic'");
    return null;
  }
  
  $video = $data['items'][0];
  $videoId = $video['id']['videoId'];
  
  return [
    'title' => $video['snippet']['title'],
    'url' => "https://www.youtube.com/watch?v={$videoId}",
    'channel' => $video['snippet']['channelTitle'],
    'thumbnail' => $video['snippet']['thumbnails']['medium']['url'] ?? ''
  ];
}

// --- read body
$raw  = file_get_contents('php://input');
$body = json_decode($raw ?: "{}", true);

$interests = isset($body['interests']) ? trim($body['interests']) : '';
$tags      = isset($body['tags']) ? trim($body['tags']) : '';
$level     = isset($body['level']) ? trim($body['level']) : 'Beginner';
$hours     = isset($body['hours']) ? trim($body['hours']) : '6-8';
$goal      = isset($body['goal'])  ? trim($body['goal'])  : '';

// Sample fallback (also used on errors)
function sample_payload($msg = null) {
  global $interests, $level, $hours, $goal;
  return [
    "summary" => "This is a sample learning path for " . $interests . " at " . $level . " level, with " . $hours . " hours per week to achieve: " . $goal,
    "list" => [
      [
        "title" => "HTML & CSS Fundamentals",
        "level" => $level,
        "category" => "Core Skills",
        "reason" => "Foundation of web development - essential for building any website",
        "outline" => "Master the building blocks of web pages",
        "duration" => "2-3 weeks",
        "topics" => [
          ["text" => "HTML5 semantic elements and document structure", "video" => ["title" => "HTML Document Structure Explained", "url" => "https://www.youtube.com/watch?v=UB1O30fR-EE", "channel" => "Traversy Media", "thumbnail" => ""]],
          ["text" => "CSS selectors, box model, and positioning", "video" => ["title" => "CSS Box Model Tutorial", "url" => "https://www.youtube.com/watch?v=rIO5326FgPE", "channel" => "Web Dev Simplified", "thumbnail" => ""]],
          ["text" => "Flexbox and Grid layouts for responsive design", "video" => ["title" => "Learn Flexbox in 15 Minutes", "url" => "https://www.youtube.com/watch?v=fYq5PXgSsbE", "channel" => "Web Dev Simplified", "thumbnail" => ""]],
          ["text" => "Forms, inputs, and accessibility basics", "video" => ["title" => "HTML Forms Tutorial", "url" => "https://www.youtube.com/watch?v=2O8pkybH6po", "channel" => "Traversy Media", "thumbnail" => ""]]
        ],
        "resources" => [
          ["name" => "MDN Web Docs - HTML", "url" => "https://developer.mozilla.org/en-US/docs/Web/HTML"],
          ["name" => "freeCodeCamp - Responsive Web Design", "url" => "https://www.freecodecamp.org/learn/2022/responsive-web-design/"]
        ],
        "videos" => [
          ["title" => "HTML Crash Course For Absolute Beginners", "url" => "https://www.youtube.com/watch?v=UB1O30fR-EE", "channel" => "Traversy Media"],
          ["title" => "CSS Tutorial - Zero to Hero", "url" => "https://www.youtube.com/watch?v=1Rs2ND1ryYc", "channel" => "freeCodeCamp"]
        ]
      ],
      [
        "title" => "JavaScript Fundamentals",
        "level" => $level,
        "category" => "Programming",
        "reason" => "Add interactivity and logic to your websites",
        "outline" => "Learn core programming concepts with JavaScript",
        "duration" => "3-4 weeks",
        "topics" => [
          ["text" => "Variables, data types, and operators", "video" => ["title" => "JavaScript Variables Explained", "url" => "https://www.youtube.com/watch?v=edlFjlzxkSI", "channel" => "Programming with Mosh", "thumbnail" => ""]],
          ["text" => "Functions, scope, and closures", "video" => ["title" => "JavaScript Functions Tutorial", "url" => "https://www.youtube.com/watch?v=N8ap4k_1QEQ", "channel" => "Programming with Mosh", "thumbnail" => ""]],
          ["text" => "DOM manipulation and event handling", "video" => ["title" => "JavaScript DOM Crash Course", "url" => "https://www.youtube.com/watch?v=0ik6X4DJKCc", "channel" => "Traversy Media", "thumbnail" => ""]],
          ["text" => "ES6+ features: arrow functions, destructuring, modules", "video" => ["title" => "ES6 Tutorial", "url" => "https://www.youtube.com/watch?v=NCwa_xi0Uuc", "channel" => "Traversy Media", "thumbnail" => ""]]
        ],
        "resources" => [
          ["name" => "JavaScript.info", "url" => "https://javascript.info/"],
          ["name" => "Eloquent JavaScript (free book)", "url" => "https://eloquentjavascript.net/"]
        ],
        "videos" => [
          ["title" => "JavaScript Tutorial for Beginners", "url" => "https://www.youtube.com/watch?v=W6NZfCO5SIk", "channel" => "Programming with Mosh"],
          ["title" => "Learn JavaScript - Full Course for Beginners", "url" => "https://www.youtube.com/watch?v=PkZNo7MFNFg", "channel" => "freeCodeCamp"]
        ]
      ],
      [
        "title" => "Build a Portfolio Project",
        "level" => $level,
        "category" => "Project",
        "reason" => "Apply your skills and create something for your portfolio",
        "outline" => "Create a complete responsive website from scratch",
        "duration" => "2 weeks",
        "topics" => [
          ["text" => "Project planning and wireframing", "video" => ["title" => "How to Plan a Web Project", "url" => "https://www.youtube.com/watch?v=_w-in1F59rI", "channel" => "Traversy Media", "thumbnail" => ""]],
          ["text" => "Responsive design implementation", "video" => ["title" => "Responsive Design Tutorial", "url" => "https://www.youtube.com/watch?v=srvUrASNj0s", "channel" => "Traversy Media", "thumbnail" => ""]],
          ["text" => "Interactive features with JavaScript", "video" => ["title" => "JavaScript Project Tutorial", "url" => "https://www.youtube.com/watch?v=3PHXvlpOkf4", "channel" => "Web Dev Simplified", "thumbnail" => ""]],
          ["text" => "Deployment to GitHub Pages or Netlify", "video" => ["title" => "Deploy Website to GitHub Pages", "url" => "https://www.youtube.com/watch?v=SKXkC4SqtRk", "channel" => "Traversy Media", "thumbnail" => ""]]
        ],
        "resources" => [
          ["name" => "GitHub Pages Guide", "url" => "https://pages.github.com/"],
          ["name" => "Netlify Deployment Docs", "url" => "https://docs.netlify.com/"]
        ],
        "videos" => [
          ["title" => "Build a Portfolio Website", "url" => "https://www.youtube.com/watch?v=xV_Zcm4lD4w", "channel" => "Traversy Media"],
          ["title" => "Responsive Portfolio Website Tutorial", "url" => "https://www.youtube.com/watch?v=27JtRAI3QO8", "channel" => "Web Dev Simplified"]
        ]
      ]
    ]
  ];
}

// If no key → return sample immediately
if (empty($GEMINI_API_KEY)) {
  echo json_encode(sample_payload("no API key set"));
  exit;
}

// ---- IMPROVED PROMPT for clean, structured output ----
$prompt = <<<PROMPT
You are an expert learning path architect. Create a comprehensive, well-structured learning roadmap.

**User Profile:**
- Interests: {$interests}
- Current Level: {$level}
- Weekly Time: {$hours} hours
- Goal: {$goal}

**Instructions:**
1. Create 5-7 progressive learning steps
2. Each step should build on previous ones
3. Include 5-8 specific, actionable topics per step
4. Each topic should be a focused skill that can be learned in 10-20 minutes
5. Provide real learning resources (documentation, articles) when possible
6. Make it specific to the user's interests and goals

**CRITICAL: Return ONLY valid JSON with NO markdown formatting, NO code blocks, NO backticks.**

**Required JSON Structure:**
{
  "summary": "A clear 1-2 sentence overview of this learning path tailored to the user's goal",
  "list": [
    {
      "title": "Clear, specific course/topic name",
      "level": "Beginner/Intermediate/Advanced",
      "category": "e.g., Fundamentals, Tools, Project, Advanced Topic",
      "reason": "Why this step is important for achieving the user's goal",
      "outline": "Brief description of what this step covers",
      "duration": "Estimated time to complete (e.g., '2-3 weeks')",
      "topics": [
        "Specific focused topic 1 (e.g., HTML Document Structure)",
        "Specific focused topic 2 (e.g., CSS Selectors - type, class, ID)",
        "Specific focused topic 3 (e.g., Flexbox for Layouts)",
        "Specific focused topic 4",
        "Specific focused topic 5"
      ],
      "resources": [
        {"name": "Resource name", "url": "https://example.com"},
        {"name": "Another resource", "url": "https://example.com"}
      ]
    }
  ]
}

**Important Rules:**
- Use plain text only - no special characters, backticks, or code formatting
- Topics should be VERY specific and focused (not broad like "Learn CSS")
- Each topic should be learnable in 10-20 minutes of video content
- Duration should be realistic based on weekly hours
- Make each step actionable and measurable

Return ONLY the JSON object, nothing else.
PROMPT;

// ---- cURL request
$url = "https://generativelanguage.googleapis.com/v1beta/models/{$GEMINI_MODEL}:generateContent?key=" . urlencode($GEMINI_API_KEY);
$payload = [ "contents" => [[ "parts" => [[ "text" => $prompt ]] ]] ];

// Point to a CA bundle (fixes Windows SSL issues). Create this file if missing.
$CA_BUNDLE = 'C:\xampp\php\extras\ssl\cacert.pem';

$opts = [
  CURLOPT_POST            => 1,
  CURLOPT_POSTFIELDS      => json_encode($payload),
  CURLOPT_HTTPHEADER      => ['Content-Type: application/json'],
  CURLOPT_RETURNTRANSFER  => true,
  CURLOPT_CONNECTTIMEOUT  => 15,   // connect timeout
  CURLOPT_TIMEOUT         => 60,   // overall timeout
];

$ch = curl_init($url);
curl_setopt_array($ch, $opts);

// Prefer secure verification with CA bundle if available
if (file_exists($CA_BUNDLE)) {
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
  curl_setopt($ch, CURLOPT_CAINFO, $CA_BUNDLE);
} else {
  // TEMPORARY: disable verify to test connectivity; remove once CA bundle is set.
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
}

$res  = curl_exec($ch);
$err  = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);

// On network/HTTP error → return sample + message
if ($err) {
  echo json_encode(sample_payload("network error: ".$err));
  exit;
}
if ($code < 200 || $code >= 300) {
  echo json_encode(sample_payload("Gemini HTTP $code"));
  exit;
}

// Parse Gemini response
$data = json_decode($res, true);
$text = $data["candidates"][0]["content"]["parts"][0]["text"] ?? "";

// Strip code fences and markdown if present
$jt = trim($text);
$jt = preg_replace('/^```(?:json)?/mi', '', $jt);
$jt = preg_replace('/```$/m', '', $jt);
$jt = trim($jt);

$parsed = json_decode($jt, true);

// Valid JSON? return it. Else sample with note.
if (is_array($parsed) && isset($parsed["summary"]) && isset($parsed["list"])) {
  
  // Add YouTube videos for each topic if API is enabled
  if ($YOUTUBE_API_ENABLED && is_array($parsed["list"])) {
    foreach ($parsed["list"] as &$step) {
      if (isset($step["topics"]) && is_array($step["topics"])) {
        $newTopics = [];
        foreach ($step["topics"] as $topic) {
          $topicText = is_string($topic) ? $topic : $topic;
          
          // Try to get video from YouTube API
          $video = searchYouTube($topicText, $YOUTUBE_API_KEY);
          
          // If YouTube API fails, use a sample video
          if (!$video) {
            $video = [
              'title' => $topicText . " - Tutorial",
              'url' => "https://www.youtube.com/results?search_query=" . urlencode($topicText . " tutorial"),
              'channel' => "Search YouTube",
              'thumbnail' => ''
            ];
          }
          
          // Convert topic to object format
          $newTopics[] = [
            "text" => $topicText,
            "video" => $video
          ];
        }
        $step["topics"] = $newTopics;
      }
    }
  } else {
    // No YouTube API - add sample video links
    foreach ($parsed["list"] as &$step) {
      if (isset($step["topics"]) && is_array($step["topics"])) {
        $newTopics = [];
        foreach ($step["topics"] as $topic) {
          $topicText = is_string($topic) ? $topic : $topic;
          $newTopics[] = [
            "text" => $topicText,
            "video" => [
              'title' => $topicText . " - Tutorial",
              'url' => "https://www.youtube.com/results?search_query=" . urlencode($topicText . " tutorial"),
              'channel' => "Search YouTube",
              'thumbnail' => ''
            ]
          ];
        }
        $step["topics"] = $newTopics;
      }
    }
  }
  
  echo json_encode($parsed);
} else {
  echo json_encode(sample_payload("AI responded but format was invalid"));
}