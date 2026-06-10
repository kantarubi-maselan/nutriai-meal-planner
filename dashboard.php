<?php
require_once 'includes/config.php';
requireLogin();

$db     = getDB();
$userId = currentUserId();
$name   = $_SESSION['user_name'] ?? 'there';
$hour   = (int)date('H');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

// ── Today's active plan ──────────────────────────────────────
$today = date('Y-m-d');
$stmt  = $db->prepare("
    SELECT 
    mp.*,
    COUNT(m.id) AS meal_count
FROM meal_plans mp
LEFT JOIN meals m 
    ON m.plan_id = mp.id 
    AND m.day_number = 1
WHERE mp.user_id = ? 
  AND mp.is_active = 1
GROUP BY mp.id
ORDER BY mp.created_at DESC
LIMIT 1;
");
$stmt->execute([$userId]);
$activePlan = $stmt->fetch();

// ── Today's meals (day 1 of active plan) ────────────────────
$todayMeals = [];
if ($activePlan) {
    $stmt = $db->prepare("
        SELECT * FROM meals WHERE plan_id = ? AND day_number = 1
        ORDER BY FIELD(meal_type,'breakfast','lunch','dinner','snack')
    ");
    $stmt->execute([$activePlan['id']]);
    $todayMeals = $stmt->fetchAll();
}

// ── Today's nutrition log ────────────────────────────────────
$stmt = $db->prepare("SELECT * FROM nutrition_logs WHERE user_id = ? AND log_date = ?");
$stmt->execute([$userId, $today]);
$todayLog = $stmt->fetch();

// ── User profile ─────────────────────────────────────────────
$stmt = $db->prepare("SELECT * FROM user_profiles WHERE user_id = ?");
$stmt->execute([$userId]);
$profile = $stmt->fetch();

// ── Saved plans count ────────────────────────────────────────
$stmt = $db->prepare("SELECT COUNT(*) FROM meal_plans WHERE user_id = ? AND is_saved = 1");
$stmt->execute([$userId]);
$savedCount = $stmt->fetchColumn();

// ── Total plans generated ────────────────────────────────────
$stmt = $db->prepare("SELECT COUNT(*) FROM meal_plans WHERE user_id = ?");
$stmt->execute([$userId]);
$totalPlans = $stmt->fetchColumn();

// ── Weekly nutrition (last 7 days) ───────────────────────────
$stmt = $db->prepare("
    SELECT log_date, calories, protein_g, carbs_g, fat_g
    FROM nutrition_logs
    WHERE user_id = ? AND log_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    ORDER BY log_date ASC
");
$stmt->execute([$userId]);
$weeklyLogs = $stmt->fetchAll();

// Calorie target
$calorieTarget = $profile['daily_calories'] ?? ($activePlan['target_calories'] ?? 2000);
$todayCalories = $todayLog['calories'] ?? array_sum(array_column($todayMeals, 'calories'));
$caloriePercent = $calorieTarget > 0 ? min(100, round($todayCalories / $calorieTarget * 100)) : 0;

// Macros
$todayProtein = $todayLog['protein_g'] ?? array_sum(array_column($todayMeals, 'protein_g'));
$todayCarbs   = $todayLog['carbs_g']   ?? array_sum(array_column($todayMeals, 'carbs_g'));
$todayFat     = $todayLog['fat_g']     ?? array_sum(array_column($todayMeals, 'fat_g'));

$mealIcons = ['breakfast'=>'🍳','lunch'=>'🥗','dinner'=>'🍽️','snack'=>'🍎'];
$mealColors = ['breakfast'=>'peach','lunch'=>'sage','dinner'=>'lavender','snack'=>'sky'];

$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
require 'includes/header.php';
?>

<style>
.dash-header {
  display: flex; align-items: flex-start; justify-content: space-between;
  margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;
}
.dash-greeting { font-family: var(--font-display); font-size: 1.8rem; font-weight: 700; }
.dash-date     { font-size: .88rem; color: var(--text-soft); margin-top: .2rem; }

/* Stats row */
.stats-row { display: grid; grid-template-columns: repeat(4,1fr); gap: 1.25rem; margin-bottom: 2rem; }
.stat-card { position: relative; overflow: hidden; }
.stat-card::before {
  content:''; position:absolute; top:-20px; right:-20px;
  width:80px; height:80px; border-radius:50%; opacity:.12;
}
.stat-sage::before   { background: var(--sage-dark); }
.stat-peach::before  { background: var(--peach); }
.stat-lav::before    { background: var(--lavender); }
.stat-sky::before    { background: var(--sky); }
.stat-icon { font-size: 1.6rem; margin-bottom: .5rem; }

/* Progress ring */
.progress-ring-wrap { display: flex; justify-content: center; margin: 1rem 0; }
.ring-svg { transform: rotate(-90deg); }
.ring-bg   { fill: none; stroke: var(--border); stroke-width: 8; }
.ring-fill { fill: none; stroke: var(--sage-dark); stroke-width: 8; stroke-linecap: round;
             transition: stroke-dashoffset .8s ease; }
.ring-label { text-align: center; margin-top: .5rem; }
.ring-label .big { font-family: var(--font-display); font-size: 1.4rem; font-weight: 700; }
.ring-label .small { font-size: .78rem; color: var(--text-soft); }

/* Macro bars */
.macro-bar-wrap { display: flex; flex-direction: column; gap: .75rem; margin-top: 1rem; }
.macro-row { display: flex; align-items: center; gap: .75rem; }
.macro-label { font-size: .8rem; font-weight: 600; color: var(--text-mid); width: 60px; }
.macro-track { flex: 1; height: 8px; background: var(--border); border-radius: 4px; overflow: hidden; }
.macro-fill  { height: 100%; border-radius: 4px; transition: width .8s ease; }
.macro-val   { font-size: .78rem; color: var(--text-soft); width: 50px; text-align: right; }

/* Meal cards */
.meals-grid { display: grid; grid-template-columns: repeat(2,1fr); gap: 1rem; }
.meal-card  {
  background: var(--white); border-radius: var(--radius-md);
  padding: 1.25rem; border: 1px solid var(--border);
  display: flex; align-items: flex-start; gap: 1rem;
  transition: var(--transition);
}
.meal-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
.meal-emoji { font-size: 1.8rem; line-height: 1; flex-shrink: 0; }
.meal-info  { flex: 1; min-width: 0; }
.meal-type  { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; margin-bottom: .2rem; }
.meal-name  { font-weight: 600; font-size: .95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.meal-kcal  { font-size: .8rem; color: var(--text-soft); margin-top: .2rem; }

/* Quick actions */
.actions-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 1rem; }
.action-card {
  background: var(--white); border-radius: var(--radius-md);
  padding: 1.5rem; border: 1px solid var(--border);
  display: flex; flex-direction: column; align-items: center; text-align: center; gap: .75rem;
  cursor: pointer; transition: var(--transition); text-decoration: none; color: inherit;
}
.action-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); }
.action-icon { font-size: 2rem; }
.action-title { font-weight: 600; font-size: .92rem; }
.action-desc  { font-size: .78rem; color: var(--text-soft); }

/* Empty state */
.empty-state {
  text-align: center; padding: 3rem 1.5rem;
  background: linear-gradient(135deg, var(--cream) 0%, var(--sage-light) 100%);
  border-radius: var(--radius-md); border: 1px dashed var(--sage);
}
.empty-state .icon { font-size: 3rem; margin-bottom: 1rem; }

/* 2-col layout */
.dash-grid { display: grid; grid-template-columns: 1fr 340px; gap: 1.5rem; margin-bottom: 2rem; }
.dash-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem; }

/* Mini week chart */
.week-chart { display: flex; align-items: flex-end; gap: .4rem; height: 70px; margin-top: 1rem; }
.week-bar-wrap { flex: 1; display: flex; flex-direction: column; align-items: center; gap: .3rem; }
.week-bar-bg { width: 100%; flex: 1; background: var(--border); border-radius: 4px; position: relative; overflow: hidden; }
.week-bar-fill { position: absolute; bottom: 0; left: 0; right: 0; border-radius: 4px; background: var(--sage-dark); transition: height .6s ease; }
.week-day { font-size: .68rem; color: var(--text-soft); }
.week-bar-bg.today .week-bar-fill { background: var(--peach); }

@media (max-width:1100px) {
  .stats-row { grid-template-columns: repeat(2,1fr); }
  .dash-grid  { grid-template-columns: 1fr; }
  .meals-grid { grid-template-columns: 1fr; }
  .actions-grid { grid-template-columns: repeat(2,1fr); }
}
</style>

<!-- ── Dashboard Header ───────────────────────────────────────── -->
<div class="dash-header">
  <div>
    <div class="dash-greeting"><?= $greeting ?>, <?= sanitize($name) ?> 👋</div>
    <div class="dash-date"><?= date('l, j F Y') ?></div>
  </div>
  <a href="generator.php" class="btn btn-primary">✨ Generate New Plan</a>
</div>

<!-- ── Stats Row ─────────────────────────────────────────────── -->
<div class="stats-row">
  <div class="card stat-card stat-sage">
    <div class="stat-icon">🔥</div>
    <div class="stat-label">Today's Calories</div>
    <div class="stat-value"><?= number_format($todayCalories) ?></div>
    <div class="stat-sub">of <?= number_format($calorieTarget) ?> target</div>
  </div>
  <div class="card stat-card stat-peach">
    <div class="stat-icon">🥗</div>
    <div class="stat-label">Today's Meals</div>
    <div class="stat-value"><?= count($todayMeals) ?></div>
    <div class="stat-sub"><?= $activePlan ? sanitize($activePlan['diet_type']) . ' plan' : 'No active plan' ?></div>
  </div>
  <div class="card stat-card stat-lav">
    <div class="stat-icon">💾</div>
    <div class="stat-label">Saved Plans</div>
    <div class="stat-value"><?= $savedCount ?></div>
    <div class="stat-sub"><a href="saved-plans.php" style="color:var(--sage-dark)">View all →</a></div>
  </div>
  <div class="card stat-card stat-sky">
    <div class="stat-icon">🧠</div>
    <div class="stat-label">Plans Generated</div>
    <div class="stat-value"><?= $totalPlans ?></div>
    <div class="stat-sub">Total AI plans</div>
  </div>
</div>

<!-- ── Main Grid (Today's Plan + Nutrition Ring) ──────────────── -->
<div class="dash-grid">

  <!-- Today's Meals -->
  <div class="card">
    <div class="section-header">
      <div class="section-title">🍽️ Today's Meals</div>
      <?php if ($activePlan): ?>
      <a href="results.php?plan=<?= $activePlan['id'] ?>" class="btn btn-ghost btn-sm">View full plan →</a>
      <?php endif; ?>
    </div>

    <?php if (empty($todayMeals)): ?>
    <div class="empty-state">
      <div class="icon">🌱</div>
      <h3 style="font-size:1.1rem;margin-bottom:.5rem">No meal plan yet</h3>
      <p style="font-size:.9rem;margin-bottom:1.5rem">Let our AI create a personalised plan for you in seconds.</p>
      <a href="generator.php" class="btn btn-primary">✨ Generate My Plan</a>
    </div>
    <?php else: ?>
    <div class="meals-grid">
      <?php foreach ($todayMeals as $meal): 
        $type  = $meal['meal_type'];
        $color = $mealColors[$type] ?? 'sage';
        $icon  = $mealIcons[$type] ?? '🍴';
      ?>
      <div class="meal-card">
        <div class="meal-emoji"><?= $icon ?></div>
        <div class="meal-info">
          <div class="meal-type badge badge-<?= $color ?>"><?= ucfirst($type) ?></div>
          <div class="meal-name"><?= sanitize($meal['name']) ?></div>
          <?php if ($meal['calories']): ?>
          <div class="meal-kcal">🔥 <?= $meal['calories'] ?> kcal
            <?php if ($meal['protein_g']): ?> · 💪 <?= $meal['protein_g'] ?>g protein<?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Nutrition Ring -->
  <div class="card" style="text-align:center">
    <div class="section-title" style="margin-bottom:.25rem">🔥 Calorie Progress</div>
    <div style="font-size:.82rem;color:var(--text-soft)">Today</div>

    <?php
    $r = 54; $circ = 2 * M_PI * $r;
    $offset = $circ - ($caloriePercent / 100 * $circ);
    ?>
    <div class="progress-ring-wrap">
      <div style="position:relative;width:130px;height:130px">
        <svg class="ring-svg" width="130" height="130" viewBox="0 0 130 130">
          <circle class="ring-bg"   cx="65" cy="65" r="<?= $r ?>"/>
          <circle class="ring-fill" cx="65" cy="65" r="<?= $r ?>"
            stroke-dasharray="<?= round($circ,2) ?>"
            stroke-dashoffset="<?= round($offset,2) ?>"/>
        </svg>
        <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center">
          <div style="font-family:var(--font-display);font-size:1.5rem;font-weight:700"><?= $caloriePercent ?>%</div>
          <div style="font-size:.7rem;color:var(--text-soft)">of goal</div>
        </div>
      </div>
    </div>
    <div style="font-size:.85rem;color:var(--text-mid)">
      <strong><?= number_format($todayCalories) ?></strong> / <?= number_format($calorieTarget) ?> kcal
    </div>

    <hr class="divider">

    <!-- Macros -->
    <div style="text-align:left">
      <div style="font-size:.82rem;font-weight:600;color:var(--text-mid);margin-bottom:.75rem">Macronutrients</div>
      <div class="macro-bar-wrap">
        <?php
        $macros = [
          ['label'=>'Protein','val'=>$todayProtein,'max'=>150,'color'=>'#a8c5a0'],
          ['label'=>'Carbs',  'val'=>$todayCarbs,  'max'=>250,'color'=>'#f2a07b'],
          ['label'=>'Fat',    'val'=>$todayFat,     'max'=>70, 'color'=>'#c5b8e8'],
        ];
        foreach ($macros as $m):
          $pct = $m['max'] > 0 ? min(100, round($m['val'] / $m['max'] * 100)) : 0;
        ?>
        <div class="macro-row">
          <div class="macro-label"><?= $m['label'] ?></div>
          <div class="macro-track">
            <div class="macro-fill" style="width:<?= $pct ?>%;background:<?= $m['color'] ?>"></div>
          </div>
          <div class="macro-val"><?= round($m['val']) ?>g</div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

</div>

<!-- ── Weekly Overview ────────────────────────────────────────── -->
<?php
$days = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $days[] = ['date'=>$date,'label'=>date('D',$i==0?time():strtotime("-{$i} days")),'calories'=>0,'isToday'=>$i===0];
}
foreach ($weeklyLogs as $log) {
    foreach ($days as &$d) {
        if ($d['date'] === $log['log_date']) { $d['calories'] = (int)$log['calories']; break; }
    }
}
$maxCal = max(array_column($days,'calories') ?: [1]);
?>
<div class="card" style="margin-bottom:2rem">
  <div class="section-header">
    <div class="section-title">📊 Weekly Calories</div>
    <a href="nutrition.php" class="btn btn-ghost btn-sm">Full insights →</a>
  </div>
  <div class="week-chart">
    <?php foreach ($days as $d):
      $h = $maxCal > 0 ? max(4, round($d['calories'] / $maxCal * 100)) : 4;
    ?>
    <div class="week-bar-wrap">
      <div class="week-bar-bg <?= $d['isToday'] ? 'today' : '' ?>">
        <div class="week-bar-fill" style="height:<?= $h ?>%"></div>
      </div>
      <div class="week-day"><?= $d['label'] ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <div style="display:flex;justify-content:space-between;margin-top:.75rem;font-size:.78rem;color:var(--text-soft)">
    <span>Target: <?= number_format($calorieTarget) ?> kcal/day</span>
    <span style="display:flex;align-items:center;gap:.4rem"><span style="width:10px;height:10px;border-radius:2px;background:var(--peach);display:inline-block"></span>Today</span>
  </div>
</div>

<!-- ── Quick Actions ──────────────────────────────────────────── -->
<div class="section-header">
  <div class="section-title">⚡ Quick Actions</div>
</div>
<div class="actions-grid" style="margin-bottom:2rem">
  <?php
  $actions = [
    ['icon'=>'🧠','title'=>'Generate Plan','desc'=>'Create a new AI meal plan','href'=>'generator.php','bg'=>'var(--lav-light)'],
    ['icon'=>'📅','title'=>'Weekly Planner','desc'=>'View your 7-day calendar','href'=>'weekly.php','bg'=>'var(--sage-light)'],
    ['icon'=>'🛒','title'=>'Grocery List','desc'=>'See what to buy','href'=>'grocery.php','bg'=>'var(--peach-light)'],
    ['icon'=>'📊','title'=>'Nutrition','desc'=>'Charts & insights','href'=>'nutrition.php','bg'=>'var(--sky-light)'],
    ['icon'=>'💾','title'=>'Saved Plans','desc'=>'Your plan history','href'=>'saved-plans.php','bg'=>'var(--rose-light)'],
    ['icon'=>'🤖','title'=>'AI Assistant','desc'=>'Ask anything about food','href'=>'chat.php','bg'=>'var(--lav-light)'],
  ];
  foreach ($actions as $a): ?>
  <a href="<?= $a['href'] ?>" class="action-card">
    <div class="action-icon" style="background:<?= $a['bg'] ?>;width:54px;height:54px;border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:center"><?= $a['icon'] ?></div>
    <div class="action-title"><?= $a['title'] ?></div>
    <div class="action-desc"><?= $a['desc'] ?></div>
  </a>
  <?php endforeach; ?>
</div>

<?php require 'includes/footer.php'; ?>