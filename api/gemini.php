<?php
// api/gemini.php — Gemini AI proxy for the chat assistant
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Not authenticated']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']); exit;
}

$input   = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? '');

if (!$message) {
    echo json_encode(['error' => 'Empty message']); exit;
}

$db     = getDB();
$userId = currentUserId();

// ── Load user profile for context ────────────────────────────
$stmt = $db->prepare("SELECT * FROM user_profiles WHERE user_id=?");
$stmt->execute([$userId]);
$profile = $stmt->fetch() ?: [];

$stmt = $db->prepare("
    SELECT title, goal, diet_type, target_calories
    FROM meal_plans WHERE user_id=? AND is_active=1
    ORDER BY created_at DESC LIMIT 1
");
$stmt->execute([$userId]);
$activePlan = $stmt->fetch();

// ── Build context string ──────────────────────────────────────
$profileParts = [];
if (!empty($profile['diet_type']))      $profileParts[] = "Diet: {$profile['diet_type']}";
if (!empty($profile['fitness_goal']))   $profileParts[] = "Goal: {$profile['fitness_goal']}";
if (!empty($profile['weight_kg']))      $profileParts[] = "Weight: {$profile['weight_kg']}kg";
if (!empty($profile['daily_calories'])) $profileParts[] = "Calorie target: {$profile['daily_calories']}kcal";
if (!empty($profile['allergies']))      $profileParts[] = "Allergies: {$profile['allergies']}";
$profileCtx = $profileParts ? implode(', ', $profileParts) : 'No profile set';

$planCtx = $activePlan
    ? "Active meal plan: {$activePlan['title']} ({$activePlan['diet_type']}, {$activePlan['goal']}, {$activePlan['target_calories']}kcal target)."
    : 'No active meal plan.';

// ── Load recent chat history for context ─────────────────────
$stmt = $db->prepare("
    SELECT role, message FROM chat_history
    WHERE user_id=?
    ORDER BY created_at DESC LIMIT 16
");
$stmt->execute([$userId]);
$recentHistory = array_reverse($stmt->fetchAll());

// ── System instruction ────────────────────────────────────────
$systemInstruction =
    "You are NutriAI, a warm, knowledgeable, and encouraging AI nutritionist. " .
    "You speak in a friendly, conversational tone like a personal dietitian who genuinely cares about the user's health.\n\n" .
    "User profile: {$profileCtx}\n" .
    "{$planCtx}\n\n" .
    "Guidelines:\n" .
    "- Give practical, actionable nutrition and meal advice\n" .
    "- Always respect the user's diet type (halal, vegetarian, etc.) and allergies\n" .
    "- When suggesting meals, include rough calorie and protein estimates\n" .
    "- Keep responses concise but complete — use short paragraphs and bullet points when listing options\n" .
    "- Occasionally encourage and motivate the user toward their health goals\n" .
    "- If asked for a recipe, include ingredients and brief steps\n" .
    "- Never give medical diagnoses — suggest consulting a doctor for health conditions\n" .
    "- Format responses clearly with emojis sparingly for warmth";

// ── Build Gemini contents array ───────────────────────────────
// Gemini uses 'user' and 'model' roles (not 'assistant')
$contents = [];
foreach ($recentHistory as $h) {
    $contents[] = [
        'role'  => $h['role'] === 'assistant' ? 'model' : 'user',
        'parts' => [['text' => $h['message']]]
    ];
}
// Append current user message
$contents[] = [
    'role'  => 'user',
    'parts' => [['text' => $message]]
];

// ── Call Gemini API ───────────────────────────────────────────
$apiKey = GEMINI_API_KEY;
$model  = GEMINI_MODEL;
$url    = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

$requestBody = json_encode([
    'system_instruction' => [
        'parts' => [['text' => $systemInstruction]]
    ],
    'contents'         => $contents,
    'generationConfig' => [
        'temperature'     => 0.8,
        'maxOutputTokens' => 1024,
        'topP'            => 0.95,
    ],
    'safetySettings' => [
        ['category' => 'HARM_CATEGORY_HARASSMENT',        'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
        ['category' => 'HARM_CATEGORY_HATE_SPEECH',       'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
        ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
        ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
    ]
]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $requestBody,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 30,
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// ── Handle errors ─────────────────────────────────────────────
if ($curlError) {
    echo json_encode(['error' => 'Network error: ' . $curlError]); exit;
}

if ($httpCode !== 200) {
    $errData = json_decode($response, true);
    $errMsg  = $errData['error']['message'] ?? "Gemini API error (HTTP {$httpCode})";
    echo json_encode(['error' => $errMsg]); exit;
}

// ── Return raw Gemini JSON to frontend ────────────────────────
// Frontend reads: data.candidates[0].content.parts[0].text
echo $response;