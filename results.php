<?php
require_once 'includes/config.php';
requireLogin();

$db     = getDB();
$userId = currentUserId();
$planId = (int)($_GET['plan'] ?? 0);

if (!$planId) redirect('dashboard.php');

// ── Load plan ────────────────────────────────────────────────
$stmt = $db->prepare("SELECT * FROM meal_plans WHERE id = ? AND user_id = ?");
$stmt->execute([$planId, $userId]);
$plan = $stmt->fetch();
if (!$plan) redirect('dashboard.php');

// ── Handle save / unsave ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['toggle_save'])) {
        $newSaved = $plan['is_saved'] ? 0 : 1;
        $db->prepare("UPDATE meal_plans SET is_saved=? WHERE id=?")->execute([$newSaved, $planId]);
        $plan['is_saved'] = $newSaved;
        flashSet('success', $newSaved ? 'Plan saved to your collection!' : 'Plan removed from saved.');
        header("Location: results.php?plan={$planId}"); exit;
    }
    if (isset($_POST['set_active'])) {
        $db->prepare("UPDATE meal_plans SET is_active=0 WHERE user_id=?")->execute([$userId]);
        $db->prepare("UPDATE meal_plans SET is_active=1 WHERE id=?")->execute([$planId]);
        flashSet('success', 'This is now your active meal plan!');
        header("Location: results.php?plan={$planId}"); exit;
    }
}

// ── Load meals grouped by day ─────────────────────────────────
$stmt = $db->prepare("
    SELECT * FROM meals WHERE plan_id = ?
    ORDER BY day_number ASC, FIELD(meal_type,'breakfast','lunch','dinner','snack')
");
$stmt->execute([$planId]);
$allMeals = $stmt->fetchAll();

$days = [];
foreach ($allMeals as $m) {
    $days[$m['day_number']][$m['meal_type']][] = $m;
}

// ── Nutrition totals ─────────────────────────────────────────
$planData = json_decode($plan['plan_json'], true);
$summary  = $planData['daily_summary'] ?? [];

$totalCal     = $summary['avg_calories']  ?? array_sum(array_column($allMeals,'calories'));
$totalProtein = $summary['avg_protein_g'] ?? array_sum(array_column($allMeals,'protein_g'));
$totalCarbs   = $summary['avg_carbs_g']   ?? array_sum(array_column($allMeals,'carbs_g'));
$totalFat     = $summary['avg_fat_g']     ?? array_sum(array_column($allMeals,'fat_g'));
$totalFiber   = $summary['avg_fiber_g']   ?? array_sum(array_column($allMeals,'fiber_g'));

// ── Grocery list link ─────────────────────────────────────────
$stmt = $db->prepare("SELECT id FROM grocery_lists WHERE plan_id=? AND user_id=? LIMIT 1");
$stmt->execute([$planId, $userId]);
$groceryId = $stmt->fetchColumn();

$mealIcons  = ['breakfast'=>'🍳','lunch'=>'🥗','dinner'=>'🍽️','snack'=>'🍎'];
$mealColors = ['breakfast'=>'peach','lunch'=>'sage','dinner'=>'lavender','snack'=>'sky'];
$dietBadge  = ['normal'=>'🍽️ Normal','halal'=>'🕌 Halal','vegetarian'=>'🥦 Vegetarian',
               'vegan'=>'🌱 Vegan','keto'=>'🥑 Keto','paleo'=>'🥩 Paleo'];
$goalBadge  = ['weight_loss'=>'⬇️ Lose Weight','weight_gain'=>'⬆️ Gain Weight',
               'maintain'=>'⚖️ Maintain','muscle_build'=>'💪 Build Muscle'];

$dayCount = count($days);

$pageTitle  = sanitize($plan['title']);
$activePage = 'generator';
require 'includes/header.php';
?>

<style>
/* ── Results Hero ─────────────────────────────────────────── */
.results-hero {
  background: linear-gradient(135deg, var(--sage-light) 0%, var(--lav-light) 60%, var(--peach-light) 100%);
  border-radius: var(--radius-lg); padding: 2.5rem; margin-bottom: 2rem;
  position: relative; overflow: hidden;
}
.results-hero::after {
  content: '✨'; position: absolute; right: 2rem; top: 50%;
  transform: translateY(-50%); font-size: 5rem; opacity: .12;
}
.results-hero-top { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; }
.results-title { font-family: var(--font-display); font-size: 1.9rem; font-weight: 700; margin-bottom: .4rem; }
.results-meta  { display: flex; gap: .5rem; flex-wrap: wrap; }
.results-actions { display: flex; gap: .75rem; flex-wrap: wrap; }

/* ── AI Explanation ──────────────────────────────────────── */
.ai-box {
  background: var(--white); border-radius: var(--radius-md);
  padding: 1.5rem 1.75rem; margin-bottom: 2rem;
  border-left: 4px solid var(--sage-dark);
  display: flex; gap: 1rem; align-items: flex-start;
}
.ai-box-icon { font-size: 1.5rem; flex-shrink: 0; margin-top: .1rem; }
.ai-box-text { font-size: .93rem; color: var(--text-mid); line-height: 1.7; }

/* ── Nutrition Summary Cards ─────────────────────────────── */
.nutrition-strip {
  display: grid; grid-template-columns: repeat(5,1fr); gap: 1rem; margin-bottom: 2rem;
}
.nut-card {
  background: var(--white); border-radius: var(--radius-md);
  padding: 1.25rem; text-align: center; border: 1px solid var(--border);
  transition: var(--transition);
}
.nut-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
.nut-icon  { font-size: 1.4rem; margin-bottom: .4rem; }
.nut-val   { font-family: var(--font-display); font-size: 1.5rem; font-weight: 700; color: var(--text-dark); }
.nut-unit  { font-size: .72rem; color: var(--text-soft); }
.nut-label { font-size: .78rem; font-weight: 600; color: var(--text-mid); margin-top: .2rem; }

/* ── Macro Donut ─────────────────────────────────────────── */
.macro-summary {
  background: var(--white); border-radius: var(--radius-md); border: 1px solid var(--border);
  padding: 1.5rem; margin-bottom: 2rem;
  display: grid; grid-template-columns: auto 1fr; gap: 2rem; align-items: center;
}
.donut-wrap { position: relative; width: 120px; height: 120px; flex-shrink: 0; }
.donut-label {
  position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%);
  text-align: center;
}
.macro-bars { display: flex; flex-direction: column; gap: .85rem; }
.macro-bar-row { display: flex; align-items: center; gap: .75rem; }
.macro-bar-label { font-size: .82rem; font-weight: 600; color: var(--text-mid); width: 65px; }
.macro-bar-track { flex: 1; height: 10px; background: var(--border); border-radius: 5px; overflow: hidden; }
.macro-bar-fill  { height: 100%; border-radius: 5px; transition: width .8s ease; }
.macro-bar-val   { font-size: .8rem; color: var(--text-soft); width: 55px; text-align: right; }

/* ── Day Tabs ────────────────────────────────────────────── */
.day-tabs {
  display: flex; gap: .4rem; margin-bottom: 1.5rem;
  overflow-x: auto; padding-bottom: .25rem;
}
.day-tab {
  padding: .55rem 1.25rem; border-radius: 50px;
  border: 1.5px solid var(--border); background: var(--white);
  font-size: .88rem; font-weight: 600; color: var(--text-soft);
  cursor: pointer; transition: var(--transition); white-space: nowrap;
  flex-shrink: 0;
}
.day-tab:hover  { border-color: var(--sage); color: var(--sage-dark); }
.day-tab.active { border-color: var(--sage-dark); background: var(--sage-dark); color: var(--white); }

/* ── Day Panel ───────────────────────────────────────────── */
.day-panel { display: none; animation: fadeUp .3s ease; }
.day-panel.active { display: block; }

/* ── Meal Card ───────────────────────────────────────────── */
.meal-result-card {
  background: var(--white); border-radius: var(--radius-md);
  border: 1px solid var(--border); margin-bottom: 1.25rem;
  overflow: hidden; transition: var(--transition);
}
.meal-result-card:hover { box-shadow: var(--shadow-md); }
.meal-card-header {
  display: flex; align-items: center; gap: 1rem;
  padding: 1.25rem 1.5rem; cursor: pointer;
  border-bottom: 1px solid transparent; transition: var(--transition);
}
.meal-card-header:hover { background: var(--cream); }
.meal-card-header.open  { border-bottom-color: var(--border); }
.meal-type-pill {
  display: inline-flex; align-items: center; gap: .35rem;
  padding: .3rem .85rem; border-radius: 50px;
  font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em;
  flex-shrink: 0;
}
.meal-name-big  { font-family: var(--font-display); font-size: 1.15rem; font-weight: 600; flex: 1; }
.meal-kcal-chip { font-size: .82rem; color: var(--text-soft); white-space: nowrap; }
.meal-expand    { color: var(--text-soft); transition: transform .25s; flex-shrink: 0; }
.meal-card-header.open .meal-expand { transform: rotate(180deg); }

.meal-card-body { display: none; padding: 1.5rem; animation: fadeUp .25s ease; }
.meal-card-body.open { display: block; }

.meal-desc { font-size: .9rem; color: var(--text-mid); margin-bottom: 1.25rem; font-style: italic; }

.meal-detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
.meal-ingredients h4, .meal-recipe h4 {
  font-size: .8rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em;
  color: var(--text-soft); margin-bottom: .75rem;
}
.ingredient-list { list-style: none; display: flex; flex-direction: column; gap: .35rem; }
.ingredient-list li {
  display: flex; align-items: center; gap: .5rem;
  font-size: .88rem; color: var(--text-mid);
}
.ingredient-list li::before { content: '•'; color: var(--sage-dark); font-size: 1.1rem; }
.recipe-steps   { list-style: none; display: flex; flex-direction: column; gap: .6rem; counter-reset: step; }
.recipe-steps li {
  display: flex; gap: .75rem; font-size: .88rem; color: var(--text-mid);
  counter-increment: step;
}
.recipe-steps li::before {
  content: counter(step); flex-shrink: 0;
  width: 22px; height: 22px; border-radius: 50%;
  background: var(--sage-light); color: var(--sage-dark);
  display: flex; align-items: center; justify-content: center;
  font-size: .72rem; font-weight: 700; margin-top: .15rem;
}
.meal-macros-mini {
  display: flex; gap: .5rem; flex-wrap: wrap; margin-top: 1.25rem; padding-top: 1rem;
  border-top: 1px solid var(--border);
}
.macro-chip {
  padding: .3rem .75rem; border-radius: 50px; font-size: .78rem; font-weight: 600;
  background: var(--cream); color: var(--text-mid); border: 1px solid var(--border);
}

/* ── Actions bar ─────────────────────────────────────────── */
.sticky-actions {
  position: sticky; bottom: 1.5rem; z-index: 50;
  background: var(--white); border: 1px solid var(--border);
  border-radius: var(--radius-lg); padding: 1rem 1.5rem;
  box-shadow: var(--shadow-lg);
  display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;
  margin-top: 2rem;
}

@media (max-width:900px) {
  .nutrition-strip { grid-template-columns: repeat(3,1fr); }
  .macro-summary   { grid-template-columns: 1fr; }
  .meal-detail-grid { grid-template-columns: 1fr; }
}
@media (max-width:600px) {
  .nutrition-strip { grid-template-columns: repeat(2,1fr); }
}
</style>

<!-- ── Results Hero ───────────────────────────────────────────── -->
<div class="results-hero">
  <div class="results-hero-top">
    <div>
      <div class="results-title"><?= sanitize($plan['title']) ?></div>
      <div class="results-meta">
        <span class="badge badge-sage"><?= $goalBadge[$plan['goal']] ?? $plan['goal'] ?></span>
        <span class="badge badge-lavender"><?= $dietBadge[$plan['diet_type']] ?? $plan['diet_type'] ?></span>
        <span class="badge badge-sky">📅 <?= $dayCount ?> day<?= $dayCount > 1 ? 's' : '' ?></span>
        <span class="badge badge-peach">🔥 <?= number_format($plan['target_calories']) ?> kcal target</span>
      </div>
    </div>
    <div class="results-actions">
      <form method="POST" style="display:inline">
        <input type="hidden" name="set_active">
        <button class="btn btn-outline btn-sm" <?= $plan['is_active'] ? 'disabled' : '' ?>>
          <?= $plan['is_active'] ? '✓ Active Plan' : '⚡ Set as Active' ?>
        </button>
      </form>
      <form method="POST" style="display:inline">
        <input type="hidden" name="toggle_save">
        <button class="btn <?= $plan['is_saved'] ? 'btn-secondary' : 'btn-primary' ?> btn-sm">
          <?= $plan['is_saved'] ? '💾 Saved' : '💾 Save Plan' ?>
        </button>
      </form>
      <?php if ($groceryId): ?>
      <a href="grocery.php?list=<?= $groceryId ?>" class="btn btn-outline btn-sm">🛒 Grocery List</a>
      <?php endif; ?>
    </div>
  </div>
  <div style="margin-top:1rem;font-size:.82rem;color:var(--text-mid)">
    Generated <?= date('j F Y, g:i a', strtotime($plan['created_at'])) ?>
  </div>
</div>

<!-- ── AI Explanation ─────────────────────────────────────────── -->
<?php if ($plan['ai_explanation']): ?>
<div class="ai-box">
  <div class="ai-box-icon">🤖</div>
  <div>
    <div style="font-weight:700;font-size:.88rem;color:var(--sage-dark);margin-bottom:.3rem;text-transform:uppercase;letter-spacing:.05em">Why this plan works for you</div>
    <div class="ai-box-text"><?= nl2br(sanitize($plan['ai_explanation'])) ?></div>
  </div>
</div>
<?php endif; ?>

<!-- ── Nutrition Summary ───────────────────────────────────────── -->
<div class="nutrition-strip">
  <div class="nut-card">
    <div class="nut-icon">🔥</div>
    <div class="nut-val"><?= number_format($totalCal) ?></div>
    <div class="nut-unit">kcal</div>
    <div class="nut-label">Avg. Calories</div>
  </div>
  <div class="nut-card">
    <div class="nut-icon">💪</div>
    <div class="nut-val"><?= round($totalProtein) ?>g</div>
    <div class="nut-unit">per day</div>
    <div class="nut-label">Protein</div>
  </div>
  <div class="nut-card">
    <div class="nut-icon">🌾</div>
    <div class="nut-val"><?= round($totalCarbs) ?>g</div>
    <div class="nut-unit">per day</div>
    <div class="nut-label">Carbohydrates</div>
  </div>
  <div class="nut-card">
    <div class="nut-icon">🫒</div>
    <div class="nut-val"><?= round($totalFat) ?>g</div>
    <div class="nut-unit">per day</div>
    <div class="nut-label">Fats</div>
  </div>
  <div class="nut-card">
    <div class="nut-icon">🌿</div>
    <div class="nut-val"><?= round($totalFiber) ?>g</div>
    <div class="nut-unit">per day</div>
    <div class="nut-label">Fibre</div>
  </div>
</div>

<!-- ── Macro Breakdown ────────────────────────────────────────── -->
<?php
$macroTotal = $totalProtein + $totalCarbs + $totalFat;
$pProt = $macroTotal > 0 ? round($totalProtein / $macroTotal * 100) : 0;
$pCarb = $macroTotal > 0 ? round($totalCarbs   / $macroTotal * 100) : 0;
$pFat  = $macroTotal > 0 ? round($totalFat     / $macroTotal * 100) : 0;

// SVG donut segments
$r = 45; $cx = 60; $cy = 60; $circ = 2 * M_PI * $r;
function donutArc($pct, $offset, $color, $r, $cx, $cy, $circ) {
    $dash = $pct / 100 * $circ;
    $gap  = $circ - $dash;
    return "<circle r='{$r}' cx='{$cx}' cy='{$cy}' fill='none' stroke='{$color}' stroke-width='12'
             stroke-dasharray='{$dash} {$gap}' stroke-dashoffset='-{$offset}' transform='rotate(-90 {$cx} {$cy})'/>";
}
$off1 = 0; $off2 = $pProt / 100 * $circ; $off3 = ($pProt + $pCarb) / 100 * $circ;
?>
<div class="macro-summary">
  <div class="donut-wrap">
    <svg viewBox="0 0 120 120" width="120" height="120">
      <circle r="<?= $r ?>" cx="<?= $cx ?>" cy="<?= $cy ?>" fill="none" stroke="var(--border)" stroke-width="12"/>
      <?= donutArc($pProt, $off1, '#a8c5a0', $r, $cx, $cy, $circ) ?>
      <?= donutArc($pCarb, $off2, '#f2a07b', $r, $cx, $cy, $circ) ?>
      <?= donutArc($pFat,  $off3, '#c5b8e8', $r, $cx, $cy, $circ) ?>
    </svg>
    <div class="donut-label">
      <div style="font-family:var(--font-display);font-size:1rem;font-weight:700"><?= $totalCal ?></div>
      <div style="font-size:.62rem;color:var(--text-soft)">kcal</div>
    </div>
  </div>
  <div>
    <div style="font-weight:700;font-size:.88rem;margin-bottom:1rem">Daily Macro Breakdown</div>
    <div class="macro-bars">
      <?php
      $macroRows = [
        ['label'=>'Protein','val'=>$totalProtein,'pct'=>$pProt,'color'=>'#a8c5a0','max'=>200],
        ['label'=>'Carbs',  'val'=>$totalCarbs,  'pct'=>$pCarb,'color'=>'#f2a07b','max'=>400],
        ['label'=>'Fat',    'val'=>$totalFat,    'pct'=>$pFat, 'color'=>'#c5b8e8','max'=>150],
        ['label'=>'Fibre',  'val'=>$totalFiber,  'pct'=>min(100,round($totalFiber/40*100)),'color'=>'#a8d4e8','max'=>40],
      ];
      foreach ($macroRows as $mr): ?>
      <div class="macro-bar-row">
        <div class="macro-bar-label"><?= $mr['label'] ?></div>
        <div class="macro-bar-track">
          <div class="macro-bar-fill" style="width:<?= $mr['pct'] ?>%;background:<?= $mr['color'] ?>"></div>
        </div>
        <div class="macro-bar-val"><?= round($mr['val']) ?>g<?= $mr['label']==='Protein'||$mr['label']==='Carbs'||$mr['label']==='Fat' ? " ({$mr['pct']}%)" : '' ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- ── Day Tabs ───────────────────────────────────────────────── -->
<div class="section-header">
  <div class="section-title">🍽️ Meal Plan</div>
  <?php if ($groceryId): ?>
  <a href="grocery.php?list=<?= $groceryId ?>" class="btn btn-outline btn-sm">🛒 View Grocery List</a>
  <?php endif; ?>
</div>

<?php if (count($days) > 1): ?>
<div class="day-tabs" id="dayTabs">
  <?php foreach ($days as $dayNum => $dayMeals): ?>
  <button class="day-tab <?= $dayNum === array_key_first($days) ? 'active' : '' ?>"
          onclick="showDay(<?= $dayNum ?>)">
    Day <?= $dayNum ?>
  </button>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ── Meals by Day ───────────────────────────────────────────── -->
<?php foreach ($days as $dayNum => $dayMeals): ?>
<div class="day-panel <?= $dayNum === array_key_first($days) ? 'active' : '' ?>" id="day-<?= $dayNum ?>">

  <?php
  $mealOrder = ['breakfast','lunch','dinner','snack'];
  foreach ($mealOrder as $mType):
    if (!isset($dayMeals[$mType])) continue;
    foreach ($dayMeals[$mType] as $meal):
      $color = $mealColors[$mType] ?? 'sage';
      $icon  = $mealIcons[$mType] ?? '🍴';
      $ingredients = json_decode($meal['ingredients'] ?? '[]', true) ?: [];
      $steps       = json_decode($meal['recipe_steps'] ?? '[]', true) ?: [];
      $cardId      = "card-{$dayNum}-{$meal['id']}";
  ?>
  <div class="meal-result-card">
    <div class="meal-card-header" onclick="toggleCard('<?= $cardId ?>')" id="hdr-<?= $cardId ?>">
      <span class="meal-type-pill pill-<?= $mType ?>"><?= $icon ?> <?= ucfirst($mType) ?></span>
      <span class="meal-name-big"><?= sanitize($meal['name']) ?></span>
      <div style="display:flex;align-items:center;gap:.75rem">
        <?php if ($meal['calories']): ?>
        <span class="meal-kcal-chip">🔥 <?= $meal['calories'] ?> kcal</span>
        <?php endif; ?>
        <span class="meal-expand">▾</span>
      </div>
    </div>
    <div class="meal-card-body" id="<?= $cardId ?>">
      <?php if ($meal['description']): ?>
      <p class="meal-desc"><?= sanitize($meal['description']) ?></p>
      <?php endif; ?>

      <div class="meal-detail-grid">
        <?php if ($ingredients): ?>
        <div class="meal-ingredients">
          <h4>🛒 Ingredients</h4>
          <ul class="ingredient-list">
            <?php foreach ($ingredients as $ing): ?>
            <li><?= sanitize($ing) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>

        <?php if ($steps): ?>
        <div class="meal-recipe">
          <h4>👩‍🍳 Recipe Steps</h4>
          <ol class="recipe-steps">
            <?php foreach ($steps as $step): ?>
            <li><?= sanitize($step) ?></li>
            <?php endforeach; ?>
          </ol>
        </div>
        <?php endif; ?>
      </div>

      <?php if ($meal['protein_g'] || $meal['carbs_g'] || $meal['fat_g']): ?>
      <div class="meal-macros-mini">
        <?php if ($meal['calories'])  echo "<span class='macro-chip'>🔥 {$meal['calories']} kcal</span>"; ?>
        <?php if ($meal['protein_g']) echo "<span class='macro-chip'>💪 {$meal['protein_g']}g protein</span>"; ?>
        <?php if ($meal['carbs_g'])   echo "<span class='macro-chip'>🌾 {$meal['carbs_g']}g carbs</span>"; ?>
        <?php if ($meal['fat_g'])     echo "<span class='macro-chip'>🫒 {$meal['fat_g']}g fat</span>"; ?>
        <?php if ($meal['fiber_g'])   echo "<span class='macro-chip'>🌿 {$meal['fiber_g']}g fibre</span>"; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; endforeach; ?>
</div>
<?php endforeach; ?>

<!-- ── Sticky Actions ─────────────────────────────────────────── -->
<div class="sticky-actions">
  <div>
    <div style="font-weight:600;font-size:.92rem">Ready to cook? 🍳</div>
    <div style="font-size:.8rem;color:var(--text-soft)"><?= $dayCount ?> day plan · <?= count($allMeals) ?> meals total</div>
  </div>
  <div style="display:flex;gap:.75rem;flex-wrap:wrap">
    <a href="generator.php" class="btn btn-ghost btn-sm">↺ Regenerate</a>
    <?php if ($groceryId): ?>
    <a href="grocery.php?list=<?= $groceryId ?>" class="btn btn-outline btn-sm">🛒 Grocery List</a>
    <?php endif; ?>
    <a href="weekly.php" class="btn btn-outline btn-sm">📅 Weekly Planner</a>
    <form method="POST" style="display:inline">
      <input type="hidden" name="toggle_save">
      <button class="btn btn-primary btn-sm"><?= $plan['is_saved'] ? '💾 Saved ✓' : '💾 Save Plan' ?></button>
    </form>
  </div>
</div>

<script>
function showDay(n) {
  document.querySelectorAll('.day-panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.day-tab').forEach(t => t.classList.remove('active'));
  document.getElementById('day-' + n).classList.add('active');
  event.target.classList.add('active');
}
function toggleCard(id) {
  const body = document.getElementById(id);
  const hdr  = document.getElementById('hdr-' + id);
  body.classList.toggle('open');
  hdr.classList.toggle('open');
}
// Auto-open first meal of first day
document.addEventListener('DOMContentLoaded', () => {
  const first = document.querySelector('.meal-card-body');
  const firstHdr = document.querySelector('.meal-card-header');
  if (first && firstHdr) { first.classList.add('open'); firstHdr.classList.add('open'); }
});
</script>

<?php require 'includes/footer.php'; ?>