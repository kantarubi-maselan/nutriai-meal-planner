<?php
require_once 'includes/config.php';
requireLogin();

$db     = getDB();
$userId = currentUserId();

// ── Week navigation ───────────────────────────────────────────
$weekOffset = (int)($_GET['week'] ?? 0);
$monday     = new DateTime('monday this week');
$monday->modify("{$weekOffset} weeks");
$weekStart  = $monday->format('Y-m-d');
$weekEnd    = (clone $monday)->modify('+6 days')->format('Y-m-d');

// ── Active plan meals (map day_number → meals) ────────────────
$stmt = $db->prepare("
    SELECT mp.id as plan_id, mp.title, mp.diet_type, mp.goal, m.*
    FROM meal_plans mp
    JOIN meals m ON m.plan_id = mp.id
    WHERE mp.user_id = ? AND mp.is_active = 1
    ORDER BY m.day_number, FIELD(m.meal_type,'breakfast','lunch','dinner','snack')
");
$stmt->execute([$userId]);
$planMeals = $stmt->fetchAll();

// Map day_number → [meal_type => meals[]]
$mealsByDay = [];
$planTitle  = '';
$planId     = null;
foreach ($planMeals as $m) {
    $planTitle = $m['title'];
    $planId    = $m['plan_id'];
    $mealsByDay[$m['day_number']][$m['meal_type']][] = $m;
}

// Build 7-day array for this week
$days = [];
for ($i = 0; $i < 7; $i++) {
    $dt      = (clone $monday)->modify("+{$i} days");
    $dayNum  = $i + 1; // plan day_number 1–7
    $isToday = $dt->format('Y-m-d') === date('Y-m-d');
    $days[]  = [
        'date'    => $dt->format('Y-m-d'),
        'label'   => $dt->format('D'),
        'num'     => $dt->format('j'),
        'month'   => $dt->format('M'),
        'dayNum'  => $dayNum,
        'isToday' => $isToday,
        'meals'   => $mealsByDay[$dayNum] ?? [],
    ];
}

// ── Nutrition totals per day ──────────────────────────────────
$stmt = $db->prepare("
    SELECT log_date, calories, protein_g, carbs_g, fat_g
    FROM nutrition_logs
    WHERE user_id = ? AND log_date BETWEEN ? AND ?
");
$stmt->execute([$userId, $weekStart, $weekEnd]);
$nutLogs = [];
foreach ($stmt->fetchAll() as $r) { $nutLogs[$r['log_date']] = $r; }

// ── Profile calorie target ────────────────────────────────────
$stmt = $db->prepare("SELECT daily_calories FROM user_profiles WHERE user_id=?");
$stmt->execute([$userId]);
$calTarget = (int)($stmt->fetchColumn() ?: 2000);

$mealIcons  = ['breakfast'=>'🍳','lunch'=>'🥗','dinner'=>'🍽️','snack'=>'🍎'];
$mealColors = ['breakfast'=>'#fddec8','lunch'=>'#d4e8d0','dinner'=>'#ede8f9','snack'=>'#daf0fa'];
$mealText   = ['breakfast'=>'#c0622f','lunch'=>'#6b9e62','dinner'=>'#7c5cbf','snack'=>'#2980b9'];

$pageTitle  = 'Weekly Planner';
$activePage = 'weekly';
require 'includes/header.php';
?>

<style>
/* ── Week header ────────────────────────────────────────────── */
.week-header {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem;
}
.week-nav { display: flex; align-items: center; gap: .75rem; }
.week-nav-btn {
  width: 36px; height: 36px; border-radius: 50%; border: 1.5px solid var(--border);
  background: var(--white); cursor: pointer; font-size: 1rem; transition: var(--transition);
  display: flex; align-items: center; justify-content: center;
}
.week-nav-btn:hover { border-color: var(--sage-dark); background: var(--sage-light); }
.week-label { font-family: var(--font-display); font-size: 1.1rem; font-weight: 600; }

/* ── Calendar grid ─────────────────────────────────────────── */
.calendar-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: .85rem;
  margin-bottom: 2rem;
}

/* ── Day column ─────────────────────────────────────────────── */
.day-col {
  background: var(--white); border-radius: var(--radius-md);
  border: 1.5px solid var(--border); overflow: hidden;
  transition: var(--transition); display: flex; flex-direction: column;
  min-height: 380px;
}
.day-col:hover { box-shadow: var(--shadow-md); }
.day-col.today {
  border-color: var(--sage-dark); border-width: 2px;
  box-shadow: 0 0 0 3px rgba(107,158,98,.12);
}
.day-col.empty-day { opacity: .55; }

.day-header {
  padding: .85rem .75rem .6rem; text-align: center;
  border-bottom: 1px solid var(--border);
}
.day-header-label { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: var(--text-soft); }
.day-header-num {
  font-family: var(--font-display); font-size: 1.6rem; font-weight: 700;
  color: var(--text-dark); line-height: 1.1; margin: .1rem 0;
}
.day-col.today .day-header-num {
  background: var(--sage-dark); color: var(--white);
  width: 40px; height: 40px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  margin: .1rem auto;
}
.day-header-month { font-size: .7rem; color: var(--text-soft); }
.today-badge {
  display: inline-block; background: var(--sage-dark); color: var(--white);
  font-size: .6rem; font-weight: 700; padding: .1rem .45rem; border-radius: 50px;
  text-transform: uppercase; letter-spacing: .05em; margin-top: .2rem;
}

/* ── Calorie bar ─────────────────────────────────────────────── */
.day-cal-bar { padding: .5rem .75rem 0; }
.cal-bar-track { height: 5px; background: var(--border); border-radius: 3px; overflow: hidden; margin-bottom: .25rem; }
.cal-bar-fill  { height: 100%; border-radius: 3px; background: var(--sage-dark); transition: width .6s ease; }
.cal-bar-fill.over { background: var(--peach); }
.cal-bar-num { font-size: .68rem; color: var(--text-soft); text-align: right; }

/* ── Meal items in calendar ──────────────────────────────────── */
.day-meals { padding: .6rem .6rem; flex: 1; display: flex; flex-direction: column; gap: .45rem; }
.cal-meal-chip {
  border-radius: 8px; padding: .45rem .6rem;
  cursor: pointer; transition: var(--transition);
  border: 1px solid transparent;
}
.cal-meal-chip:hover { filter: brightness(.97); transform: translateX(2px); }
.cal-meal-type { font-size: .62rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; margin-bottom: .1rem; }
.cal-meal-name { font-size: .75rem; font-weight: 600; line-height: 1.3;
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.cal-meal-cal  { font-size: .65rem; margin-top: .15rem; opacity: .8; }

.day-empty-msg {
  flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: .4rem; padding: 1rem; text-align: center; color: var(--text-soft);
}
.day-empty-msg .icon { font-size: 1.5rem; opacity: .4; }
.day-empty-msg span  { font-size: .75rem; }

/* ── Week summary strip ──────────────────────────────────────── */
.week-summary {
  display: grid; grid-template-columns: repeat(4,1fr); gap: 1rem; margin-bottom: 2rem;
}
.wsumm-card {
  background: var(--white); border-radius: var(--radius-md); border: 1px solid var(--border);
  padding: 1.25rem; text-align: center;
}
.wsumm-icon  { font-size: 1.4rem; margin-bottom: .35rem; }
.wsumm-val   { font-family: var(--font-display); font-size: 1.5rem; font-weight: 700; }
.wsumm-label { font-size: .75rem; color: var(--text-soft); margin-top: .15rem; }

/* ── Meal detail drawer ──────────────────────────────────────── */
.drawer-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,.35); z-index: 200;
  opacity: 0; pointer-events: none; transition: opacity .25s;
}
.drawer-overlay.open { opacity: 1; pointer-events: all; }
.drawer {
  position: fixed; right: 0; top: 0; bottom: 0; width: 380px; max-width: 95vw;
  background: var(--white); box-shadow: var(--shadow-lg); z-index: 201;
  transform: translateX(100%); transition: transform .3s cubic-bezier(.4,0,.2,1);
  overflow-y: auto; padding: 2rem 1.75rem;
}
.drawer-overlay.open .drawer { transform: none; }
.drawer-close {
  position: absolute; top: 1.25rem; right: 1.25rem;
  background: var(--cream); border: none; border-radius: 50%;
  width: 32px; height: 32px; cursor: pointer; font-size: 1rem;
  display: flex; align-items: center; justify-content: center;
  color: var(--text-soft); transition: var(--transition);
}
.drawer-close:hover { background: var(--border); }
.drawer-meal-type { font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; margin-bottom: .4rem; }
.drawer-meal-name { font-family: var(--font-display); font-size: 1.4rem; font-weight: 700; margin-bottom: .75rem; line-height: 1.2; }
.drawer-macros    { display: flex; gap: .5rem; flex-wrap: wrap; margin-bottom: 1.25rem; }
.d-chip { background: var(--cream); border: 1px solid var(--border); border-radius: 50px; padding: .25rem .7rem; font-size: .78rem; font-weight: 600; color: var(--text-mid); }
.drawer-section   { margin-bottom: 1.25rem; }
.drawer-section h4 { font-size: .8rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--text-soft); margin-bottom: .6rem; }
.drawer-ingredients { list-style: none; display: flex; flex-direction: column; gap: .3rem; }
.drawer-ingredients li { font-size: .88rem; color: var(--text-mid); display: flex; align-items: center; gap: .5rem; }
.drawer-ingredients li::before { content:'•'; color: var(--sage-dark); font-size: 1rem; }
.drawer-steps { list-style: none; counter-reset: st; display: flex; flex-direction: column; gap: .6rem; }
.drawer-steps li { counter-increment: st; display: flex; gap: .65rem; font-size: .88rem; color: var(--text-mid); }
.drawer-steps li::before {
  content: counter(st); width: 22px; height: 22px; border-radius: 50%; flex-shrink: 0;
  background: var(--sage-light); color: var(--sage-dark);
  display: flex; align-items: center; justify-content: center;
  font-size: .72rem; font-weight: 700; margin-top: .15rem;
}

/* ── Legend ─────────────────────────────────────────────────── */
.legend { display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.25rem; }
.legend-item { display: flex; align-items: center; gap: .4rem; font-size: .78rem; color: var(--text-mid); }
.legend-dot  { width: 10px; height: 10px; border-radius: 3px; }

/* ── No plan state ───────────────────────────────────────────── */
.no-plan-card {
  text-align: center; padding: 3.5rem 2rem;
  background: linear-gradient(135deg, var(--cream), var(--sage-light));
  border-radius: var(--radius-lg); border: 1px dashed var(--sage);
}

@media (max-width: 1100px) {
  .calendar-grid { grid-template-columns: repeat(4,1fr); }
  .week-summary  { grid-template-columns: repeat(2,1fr); }
}
@media (max-width: 700px) {
  .calendar-grid { grid-template-columns: repeat(2,1fr); }
}
</style>

<!-- ── Week Header ─────────────────────────────────────────────── -->
<div class="week-header">
  <div>
    <h1 style="font-size:1.6rem;margin-bottom:.2rem">📅 Weekly Planner</h1>
    <p style="font-size:.88rem">
      <?php if ($planTitle): ?>
        Active plan: <strong><?= sanitize($planTitle) ?></strong>
      <?php else: ?>
        No active plan — generate one to populate your calendar
      <?php endif; ?>
    </p>
  </div>
  <div class="week-nav">
    <a href="?week=<?= $weekOffset - 1 ?>" class="week-nav-btn">‹</a>
    <div class="week-label">
      <?= $monday->format('j M') ?> – <?= (clone $monday)->modify('+6 days')->format('j M Y') ?>
    </div>
    <a href="?week=<?= $weekOffset + 1 ?>" class="week-nav-btn">›</a>
    <?php if ($weekOffset !== 0): ?>
    <a href="?week=0" class="btn btn-outline btn-sm">Today</a>
    <?php endif; ?>
  </div>
  <div style="display:flex;gap:.75rem">
    <?php if ($planId): ?>
    <a href="results.php?plan=<?= $planId ?>" class="btn btn-ghost btn-sm">View Plan →</a>
    <?php endif; ?>
    <a href="generator.php" class="btn btn-primary btn-sm">✨ New Plan</a>
  </div>
</div>

<!-- ── Legend ──────────────────────────────────────────────────── -->
<div class="legend">
  <?php foreach ($mealIcons as $type => $icon): ?>
  <div class="legend-item">
    <div class="legend-dot" style="background:<?= $mealColors[$type] ?>; border: 1px solid <?= $mealText[$type] ?>22"></div>
    <?= $icon ?> <?= ucfirst($type) ?>
  </div>
  <?php endforeach; ?>
  <div class="legend-item" style="margin-left:auto">
    <div class="legend-dot" style="background:var(--sage-dark)"></div> Within target
    <div class="legend-dot" style="background:var(--peach);margin-left:.5rem"></div> Over target
  </div>
</div>

<?php if (!$planTitle): ?>
<!-- ── No Plan ──────────────────────────────────────────────── -->
<div class="no-plan-card">
  <div style="font-size:3rem;margin-bottom:1rem">📅</div>
  <h2 style="font-size:1.3rem;margin-bottom:.5rem">Your calendar is empty</h2>
  <p style="margin-bottom:1.5rem;color:var(--text-soft)">Generate an AI meal plan and it'll automatically appear here for the week.</p>
  <a href="generator.php" class="btn btn-primary btn-lg">✨ Generate Meal Plan</a>
</div>

<?php else: ?>

<!-- ── Week Summary Strip ───────────────────────────────────── -->
<?php
$weekCal = 0; $weekProt = 0; $weekCarbs = 0; $weekFat = 0;
foreach ($days as $d) {
    $log = $nutLogs[$d['date']] ?? null;
    if ($log) {
        $weekCal   += $log['calories'];
        $weekProt  += $log['protein_g'];
        $weekCarbs += $log['carbs_g'];
        $weekFat   += $log['fat_g'];
    } else {
        // Estimate from plan meals
        foreach ($d['meals'] as $meals) {
            foreach ($meals as $m) { $weekCal += $m['calories'] ?? 0; }
        }
    }
}
?>
<div class="week-summary">
  <div class="wsumm-card">
    <div class="wsumm-icon">🔥</div>
    <div class="wsumm-val"><?= number_format($weekCal) ?></div>
    <div class="wsumm-label">Est. Weekly Calories</div>
  </div>
  <div class="wsumm-card">
    <div class="wsumm-icon">🍽️</div>
    <div class="wsumm-val"><?= count($planMeals) ?></div>
    <div class="wsumm-label">Planned Meals</div>
  </div>
  <div class="wsumm-card">
    <div class="wsumm-icon">⚖️</div>
    <div class="wsumm-val"><?= $calTarget > 0 ? number_format($calTarget) : '—' ?></div>
    <div class="wsumm-label">Daily Cal Target</div>
  </div>
  <div class="wsumm-card">
    <div class="wsumm-icon">✅</div>
    <?php $daysWithMeals = count(array_filter($days, fn($d) => !empty($d['meals']))); ?>
    <div class="wsumm-val"><?= $daysWithMeals ?>/7</div>
    <div class="wsumm-label">Days Planned</div>
  </div>
</div>

<!-- ── Calendar ─────────────────────────────────────────────── -->
<div class="calendar-grid">
  <?php foreach ($days as $day):
    $log      = $nutLogs[$day['date']] ?? null;
    $dayCal   = 0;
    if ($log) {
        $dayCal = (int)$log['calories'];
    } else {
        foreach ($day['meals'] as $meals) {
            foreach ($meals as $m) { $dayCal += (int)($m['calories'] ?? 0); }
        }
    }
    $calPct  = $calTarget > 0 ? min(100, round($dayCal / $calTarget * 100)) : 0;
    $calOver = $dayCal > $calTarget && $calTarget > 0;
    $hasAnyMeals = !empty($day['meals']);
  ?>
  <div class="day-col <?= $day['isToday'] ? 'today' : '' ?> <?= !$hasAnyMeals ? 'empty-day' : '' ?>">

    <!-- Day header -->
    <div class="day-header">
      <div class="day-header-label"><?= $day['label'] ?></div>
      <div class="day-header-num"><?= $day['num'] ?></div>
      <div class="day-header-month"><?= $day['month'] ?></div>
      <?php if ($day['isToday']): ?><div class="today-badge">Today</div><?php endif; ?>
    </div>

    <!-- Calorie bar -->
    <?php if ($dayCal > 0): ?>
    <div class="day-cal-bar">
      <div class="cal-bar-track">
        <div class="cal-bar-fill <?= $calOver ? 'over' : '' ?>" style="width:<?= $calPct ?>%"></div>
      </div>
      <div class="cal-bar-num"><?= number_format($dayCal) ?> kcal</div>
    </div>
    <?php endif; ?>

    <!-- Meals -->
    <div class="day-meals">
      <?php if (!$hasAnyMeals): ?>
      <div class="day-empty-msg">
        <span class="icon">🌿</span>
        <span>No meals planned</span>
      </div>
      <?php else: ?>
        <?php
        $mealOrder = ['breakfast','lunch','dinner','snack'];
        foreach ($mealOrder as $mType):
          if (!isset($day['meals'][$mType])) continue;
          foreach ($day['meals'][$mType] as $m):
            $bg   = $mealColors[$mType];
            $text = $mealText[$mType];
            $icon = $mealIcons[$mType];
            $enc  = htmlspecialchars(json_encode($m), ENT_QUOTES, 'UTF-8');
        ?>
        <div class="cal-meal-chip"
             style="background:<?= $bg ?>"
             onclick='openDrawer(<?= $enc ?>)'>
          <div class="cal-meal-type" style="color:<?= $text ?>"><?= $icon ?> <?= $mType ?></div>
          <div class="cal-meal-name" style="color:<?= $text ?>"><?= sanitize($m['name']) ?></div>
          <?php if ($m['calories']): ?>
          <div class="cal-meal-cal" style="color:<?= $text ?>">🔥 <?= $m['calories'] ?> kcal</div>
          <?php endif; ?>
        </div>
        <?php endforeach; endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<?php endif; ?>

<!-- ── Meal Detail Drawer ──────────────────────────────────────── -->
<div class="drawer-overlay" id="drawerOverlay" onclick="if(event.target===this)closeDrawer()">
  <div class="drawer" id="drawer">
    <button class="drawer-close" onclick="closeDrawer()">✕</button>
    <div id="drawerContent"><!-- filled by JS --></div>
  </div>
</div>

<script>
function openDrawer(meal) {
  const ingredients = typeof meal.ingredients === 'string'
    ? JSON.parse(meal.ingredients || '[]')
    : (meal.ingredients || []);
  const steps = typeof meal.recipe_steps === 'string'
    ? JSON.parse(meal.recipe_steps || '[]')
    : (meal.recipe_steps || []);

  const mealIcons = {breakfast:'🍳',lunch:'🥗',dinner:'🍽️',snack:'🍎'};
  const mealColors = {breakfast:'#c0622f',lunch:'#6b9e62',dinner:'#7c5cbf',snack:'#2980b9'};
  const icon  = mealIcons[meal.meal_type] || '🍴';
  const color = mealColors[meal.meal_type] || 'var(--sage-dark)';

  let html = `
    <div class="drawer-meal-type" style="color:${color}">${icon} ${meal.meal_type}</div>
    <div class="drawer-meal-name">${escHtml(meal.name)}</div>`;

  if (meal.description) {
    html += `<p style="font-size:.88rem;color:var(--text-soft);margin-bottom:1rem;font-style:italic">${escHtml(meal.description)}</p>`;
  }

  html += `<div class="drawer-macros">`;
  if (meal.calories)  html += `<span class="d-chip">🔥 ${meal.calories} kcal</span>`;
  if (meal.protein_g) html += `<span class="d-chip">💪 ${meal.protein_g}g protein</span>`;
  if (meal.carbs_g)   html += `<span class="d-chip">🌾 ${meal.carbs_g}g carbs</span>`;
  if (meal.fat_g)     html += `<span class="d-chip">🫒 ${meal.fat_g}g fat</span>`;
  if (meal.fiber_g)   html += `<span class="d-chip">🌿 ${meal.fiber_g}g fibre</span>`;
  html += `</div>`;

  if (ingredients.length) {
    html += `<div class="drawer-section"><h4>🛒 Ingredients</h4><ul class="drawer-ingredients">`;
    ingredients.forEach(i => { html += `<li>${escHtml(i)}</li>`; });
    html += `</ul></div>`;
  }
  if (steps.length) {
    html += `<div class="drawer-section"><h4>👩‍🍳 Recipe Steps</h4><ol class="drawer-steps">`;
    steps.forEach(s => { html += `<li>${escHtml(s)}</li>`; });
    html += `</ol></div>`;
  }

  document.getElementById('drawerContent').innerHTML = html;
  document.getElementById('drawerOverlay').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeDrawer() {
  document.getElementById('drawerOverlay').classList.remove('open');
  document.body.style.overflow = '';
}

function escHtml(str) {
  return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Keyboard close
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDrawer(); });
</script>

<?php require 'includes/footer.php'; ?>