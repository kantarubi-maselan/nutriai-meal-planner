<?php
// api/generate-plan.php — Gemini AI meal plan generation endpoint
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success'=>false,'error'=>'Not authenticated']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'error'=>'Method not allowed']); exit;
}

$db     = getDB();
$userId = currentUserId();

// ── Sanitise inputs ──────────────────────────────────────────
$goal           = $_POST['goal']           ?? 'maintain';
$dietType       = $_POST['diet_type']      ?? 'normal';
$targetCalories = (int)($_POST['target_calories'] ?? 2000);
$duration       = min(7, max(1, (int)($_POST['duration'] ?? 1)));
$mealsPerDay    = min(5, max(3, (int)($_POST['meals_per_day'] ?? 3)));
$ingredients    = trim($_POST['ingredients'] ?? '');
$allergies      = trim($_POST['allergies']   ?? '');
$notes          = trim($_POST['notes']       ?? '');

// ── Load user profile ────────────────────────────────────────
$stmt = $db->prepare("SELECT * FROM user_profiles WHERE user_id = ?");
$stmt->execute([$userId]);
$profile = $stmt->fetch();

// ── Build Gemini prompt ──────────────────────────────────────
$goalLabels = [
    'weight_loss'  => 'weight loss (calorie deficit)',
    'weight_gain'  => 'weight gain (calorie surplus)',
    'maintain'     => 'weight maintenance',
    'muscle_build' => 'muscle building (high protein)',
];
$goalLabel = $goalLabels[$goal] ?? $goal;

$profileContext = '';
if ($profile) {
    $parts = [];
    if ($profile['age'])        $parts[] = "Age: {$profile['age']}";
    if ($profile['gender'])     $parts[] = "Gender: {$profile['gender']}";
    if ($profile['weight_kg'])  $parts[] = "Weight: {$profile['weight_kg']}kg";
    if ($profile['height_cm'])  $parts[] = "Height: {$profile['height_cm']}cm";
    if ($profile['activity_level']) $parts[] = "Activity level: {$profile['activity_level']}";
    if ($parts) $profileContext = "User profile: " . implode(', ', $parts) . ".";
}

$ingredientLine = $ingredients
    ? "The user has these ingredients available: {$ingredients}. Build meals primarily around these."
    : "The user has not specified ingredients. Suggest practical, common ingredients.";

$allergyLine = $allergies
    ? "IMPORTANT - Avoid these allergens/foods entirely: {$allergies}."
    : "";

$notesLine = $notes ? "Additional user notes: {$notes}" : "";

$snackInstruction = $mealsPerDay >= 4
    ? "Include " . ($mealsPerDay - 3) . " snack(s) per day."
    : "No snacks needed (3 meals only).";

$dayWord = $duration === 1 ? '1 day' : "{$duration} days";

$systemPrompt = <<<PROMPT
You are NutriAI, an expert nutritionist and meal planner. You create practical, delicious, and nutritionally balanced meal plans.
Always respond with ONLY valid JSON — no markdown, no code fences, no explanation text outside the JSON.
All meal names should be realistic, appetising, and culturally appropriate.
PROMPT;

$userPrompt = <<<PROMPT
Create a detailed {$dayWord} meal plan with the following requirements:

Goal: {$goalLabel}
Diet type: {$dietType}
Daily calorie target: {$targetCalories} kcal
Meals per day: {$mealsPerDay} (breakfast, lunch, dinner{$snackInstruction})
{$profileContext}
{$ingredientLine}
{$allergyLine}
{$notesLine}

Respond with ONLY this JSON structure (no other text):
{
  "plan_title": "string (creative plan name)",
  "ai_explanation": "string (2-3 sentences explaining why this plan suits the user's goal)",
  "daily_summary": {
    "avg_calories": number,
    "avg_protein_g": number,
    "avg_carbs_g": number,
    "avg_fat_g": number,
    "avg_fiber_g": number
  },
  "days": [
    {
      "day": 1,
      "meals": [
        {
          "meal_type": "breakfast|lunch|dinner|snack",
          "name": "Meal name",
          "description": "1-sentence description",
          "calories": number,
          "protein_g": number,
          "carbs_g": number,
          "fat_g": number,
          "fiber_g": number,
          "ingredients": ["ingredient 1", "ingredient 2"],
          "recipe_steps": ["Step 1", "Step 2", "Step 3"]
        }
      ]
    }
  ],
  "grocery_list": {
    "protein": ["item 1", "item 2"],
    "vegetables": ["item 1", "item 2"],
    "carbs": ["item 1", "item 2"],
    "dairy_eggs": ["item 1"],
    "pantry": ["item 1", "item 2"],
    "fruits": ["item 1"]
  }
}

Ensure ALL days are included (1 through {$duration}). Keep responses concise:
- Each meal: max 1 sentence description
- Recipe steps: maximum 3 steps only
- Nutritional values should be approximate (not overly detailed)
PROMPT;

// ── Call Gemini API ──────────────────────────────────────────
$requestBody = json_encode([
    "contents" => [
        [
            "role" => "user",
            "parts" => [
                ["text" => $systemPrompt . "\n\n" . $userPrompt]
            ]
        ]
    ],
    "generationConfig" => [
    "temperature" => 0.7,
    "maxOutputTokens" => 8192
]
]);

$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . GEMINI_API_KEY;
$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $requestBody,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json'
    ],
    CURLOPT_TIMEOUT => 120,
]);

$response = curl_exec($ch);

$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

curl_close($ch);


// NOW error handling
if ($curlError) {
    echo json_encode(['success'=>false,'error'=>'Network error: '.$curlError]);
    exit;
}

if ($httpCode !== 200) {
    echo json_encode(['success'=>false,'error'=>'API error','http'=>$httpCode]);
    exit;
}

/// ── Parse AI response ────────────────────────────────────
$apiData = json_decode($response, true);

// Get text safely
$content = $apiData['candidates'][0]['content']['parts'][0]['text'] ?? '';

// Remove markdown fences
$content = str_replace(['```json', '```'], '', $content);
$content = trim($content);

// 🧠 CHECK IF JSON IS COMPLETE
$firstBrace = strpos($content, '{');
$lastBrace  = strrpos($content, '}');

if ($firstBrace === false || $lastBrace === false) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid AI response (no JSON found)',
        'raw_output' => $content
    ]);
    exit;
}

// 🔥 CUT ONLY VALID JSON PORTION
$content = substr($content, $firstBrace, $lastBrace - $firstBrace + 1);

// Decode
$plan = json_decode($content, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        'success' => false,
        'error' => 'JSON decode failed',
        'json_error' => json_last_error_msg(),
        'raw_output' => $content
    ]);
    exit;
}

if (!$plan || !isset($plan['days'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to parse AI response',
        'raw' => $content
    ]);
    exit;
}


// ── Save plan to database ────────────────────────────────────
try {
    $db->beginTransaction();

    // Insert meal plan
    $stmt = $db->prepare("
        INSERT INTO meal_plans
            (user_id, title, goal, diet_type, target_calories, ingredients, plan_json, ai_explanation, week_start, is_active, is_saved)
        VALUES (?,?,?,?,?,?,?,?,CURDATE(),1,0)
    ");
    $stmt->execute([
        $userId,
        $plan['plan_title'] ?? 'My Meal Plan',
        $goal,
        $dietType,
        $targetCalories,
        $ingredients,
        $content,
        $plan['ai_explanation'] ?? '',
    ]);
    $planId = $db->lastInsertId();

    // Deactivate old plans
    $db->prepare("UPDATE meal_plans SET is_active = 0 WHERE user_id = ? AND id != ?")
       ->execute([$userId, $planId]);

    // Insert individual meals
    $stmt = $db->prepare("
        INSERT INTO meals
            (plan_id, day_number, meal_type, name, description, calories, protein_g, carbs_g, fat_g, fiber_g, recipe_steps, ingredients)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
    ");

    foreach ($plan['days'] as $day) {
        $dayNum = (int)($day['day'] ?? 1);
        foreach ($day['meals'] as $meal) {
            $stmt->execute([
                $planId,
                $dayNum,
                $meal['meal_type']  ?? 'meal',
                $meal['name']       ?? '',
                $meal['description'] ?? '',
                (int)($meal['calories']  ?? 0),
                (float)($meal['protein_g'] ?? 0),
                (float)($meal['carbs_g']   ?? 0),
                (float)($meal['fat_g']     ?? 0),
                (float)($meal['fiber_g']   ?? 0),
                json_encode($meal['recipe_steps']  ?? []),
                json_encode($meal['ingredients']   ?? []),
            ]);
        }
    }

    // Save grocery list if provided
    if (!empty($plan['grocery_list'])) {
        $items = [];
        $categoryMap = ['protein'=>'Protein','vegetables'=>'Vegetables','carbs'=>'Carbs',
                        'dairy_eggs'=>'Dairy & Eggs','pantry'=>'Pantry','fruits'=>'Fruits'];
        foreach ($plan['grocery_list'] as $cat => $itemList) {
            foreach ((array)$itemList as $item) {
                $items[] = [
                    'name'     => $item,
                    'category' => $categoryMap[$cat] ?? ucfirst($cat),
                    'checked'  => false,
                    'qty'      => '',
                    'unit'     => '',
                ];
            }
        }
        $db->prepare("
            INSERT INTO grocery_lists (user_id, plan_id, title, items_json)
            VALUES (?,?,?,?)
            ON DUPLICATE KEY UPDATE items_json=VALUES(items_json)
        ")->execute([$userId, $planId, ($plan['plan_title'] ?? 'Grocery List') . ' — Shopping List', json_encode($items)]);
    }

    $db->commit();
    echo json_encode(['success'=>true, 'plan_id'=>$planId]);

} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success'=>false,'error'=>'Database error: '.$e->getMessage()]);
}
