<?php
require_once 'includes/config.php';
requireLogin();

$db     = getDB();
$userId = currentUserId();

// ── Handle log entry POST ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log_nutrition'])) {
    $logDate  = $_POST['log_date']  ?? date('Y-m-d');
    $calories = (int)($_POST['calories']  ?? 0);
    $protein  = (float)($_POST['protein_g'] ?? 0);
    $carbs    = (float)($_POST['carbs_g']   ?? 0);
    $fat      = (float)($_POST['fat_g']     ?? 0);
    $fiber    = (float)($_POST['fiber_g']   ?? 0);
    $water    = (int)($_POST['water_ml']  ?? 0);
    $notes    = trim($_POST['notes'] ?? '');

    $db->prepare("
        INSERT INTO nutrition_logs (user_id,log_date,calories,protein_g,carbs_g,fat_g,fiber_g,water_ml,notes)
        VALUES (?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
            calories=VALUES(calories), protein_g=VALUES(protein_g),
            carbs_g=VALUES(carbs_g),   fat_g=VALUES(fat_g),
            fiber_g=VALUES(fiber_g),   water_ml=VALUES(water_ml),
            notes=VALUES(notes)
    ")->execute([$userId,$logDate,$calories,$protein,$carbs,$fat,$fiber,$water,$notes]);
    flashSet('success', 'Nutrition logged for ' . date('j M', strtotime($logDate)) . '!');
    header('Location: nutrition.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log_weight'])) {
    $logDate   = $_POST['wt_date']  ?? date('Y-m-d');
    $weightKg  = (float)($_POST['weight_kg'] ?? 0);
    $note      = trim($_POST['wt_note'] ?? '');
    if ($weightKg > 0) {
        $db->prepare("
            INSERT INTO weight_logs (user_id,log_date,weight_kg,note)
            VALUES (?,?,?,?)
            ON DUPLICATE KEY UPDATE weight_kg=VALUES(weight_kg), note=VALUES(note)
        ")->execute([$userId,$logDate,$weightKg,$note]);
        // Also update profile
        $db->prepare("UPDATE user_profiles SET weight_kg=? WHERE user_id=?")->execute([$weightKg,$userId]);
        flashSet('success','Weight logged!');
    }
    header('Location: nutrition.php'); exit;
}

// ── Fetch last 30 days nutrition ──────────────────────────────
$range = (int)($_GET['range'] ?? 7);
$range = in_array($range,[7,14,30]) ? $range : 7;

$stmt = $db->prepare("
    SELECT log_date, calories, protein_g, carbs_g, fat_g, fiber_g, water_ml
    FROM nutrition_logs
    WHERE user_id = ? AND log_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
    ORDER BY log_date ASC
");
$stmt->execute([$userId, $range]);
$logs = $stmt->fetchAll();

// Build date-indexed map
$logMap = [];
foreach ($logs as $l) { $logMap[$l['log_date']] = $l; }

// Pad all days in range
$chartDates = []; $chartCal = []; $chartProt = []; $chartCarbs = []; $chartFat = [];
for ($i = $range - 1; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $l = $logMap[$d] ?? null;
    $chartDates[] = date('d M', strtotime($d));
    $chartCal[]   = $l ? (int)$l['calories']  : 0;
    $chartProt[]  = $l ? (float)$l['protein_g'] : 0;
    $chartCarbs[] = $l ? (float)$l['carbs_g']   : 0;
    $chartFat[]   = $l ? (float)$l['fat_g']     : 0;
}

// ── Averages ─────────────────────────────────────────────────
$loggedDays = count(array_filter($chartCal));
$avgCal   = $loggedDays ? round(array_sum($chartCal)   / $loggedDays) : 0;
$avgProt  = $loggedDays ? round(array_sum($chartProt)  / $loggedDays, 1) : 0;
$avgCarbs = $loggedDays ? round(array_sum($chartCarbs) / $loggedDays, 1) : 0;
$avgFat   = $loggedDays ? round(array_sum($chartFat)   / $loggedDays, 1) : 0;
$totalCal = array_sum($chartCal);

// ── Profile & target ─────────────────────────────────────────
$stmt = $db->prepare("SELECT * FROM user_profiles WHERE user_id=?");
$stmt->execute([$userId]);
$profile    = $stmt->fetch() ?: [];
$calTarget  = (int)($profile['daily_calories'] ?? 2000);
$goalWeight = (float)($profile['goal_weight_kg'] ?? 0);

// ── Today's data ──────────────────────────────────────────────
$todayLog = $logMap[date('Y-m-d')] ?? null;
$todayCal = $todayLog ? (int)$todayLog['calories'] : 0;
$calPct   = $calTarget > 0 ? min(100, round($todayCal / $calTarget * 100)) : 0;

// ── Weight history ────────────────────────────────────────────
$stmt = $db->prepare("
    SELECT log_date, weight_kg FROM weight_logs
    WHERE user_id=? AND log_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
    ORDER BY log_date ASC
");
$stmt->execute([$userId, $range]);
$weightLogs = $stmt->fetchAll();

$wtDates = []; $wtVals = [];
foreach ($weightLogs as $w) {
    $wtDates[] = date('d M', strtotime($w['log_date']));
    $wtVals[]  = (float)$w['weight_kg'];
}

// Current & change
$currentWt = !empty($wtVals) ? end($wtVals) : (float)($profile['weight_kg'] ?? 0);
$firstWt   = !empty($wtVals) ? $wtVals[0]   : $currentWt;
$wtChange  = round($currentWt - $firstWt, 1);

$pageTitle  = 'Nutrition Insights';
$activePage = 'nutrition';
require 'includes/header.php';
?>

<style>
/* ── Page layout ─────────────────────────────────────────────── */
.nut-layout { display: grid; grid-template-columns: 1fr 320px; gap: 1.5rem; align-items: start; }

/* ── Top stat row ────────────────────────────────────────────── */
.nut-stats { display: grid; grid-template-columns: repeat(4,1fr); gap: 1rem; margin-bottom: 1.5rem; }
.nstat {
  background: var(--white); border-radius: var(--radius-md); border: 1px solid var(--border);
  padding: 1.25rem; position: relative; overflow: hidden;
}
.nstat::before { content:''; position:absolute; top:-20px;right:-20px; width:70px;height:70px;border-radius:50%;opacity:.1; }
.nstat-cal::before  { background: var(--peach); }
.nstat-prot::before { background: var(--sage-dark); }
.nstat-carb::before { background: #f2a07b; }
.nstat-fat::before  { background: var(--lavender); }
.nstat-icon  { font-size: 1.3rem; margin-bottom: .4rem; }
.nstat-val   { font-family: var(--font-display); font-size: 1.7rem; font-weight: 700; line-height: 1; }
.nstat-label { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--text-soft); margin-top: .3rem; }
.nstat-sub   { font-size: .72rem; color: var(--text-soft); margin-top: .15rem; }

/* ── Today ring ──────────────────────────────────────────────── */
.today-ring-card { text-align: center; padding: 1.75rem; }
.ring-outer { position: relative; width: 140px; height: 140px; margin: 0 auto 1rem; }
.ring-svg   { transform: rotate(-90deg); }
.ring-bg-c  { fill:none; stroke:var(--border); stroke-width:10; }
.ring-fill-c { fill:none; stroke:var(--sage-dark); stroke-width:10; stroke-linecap:round; transition: stroke-dashoffset .8s ease; }
.ring-inner { position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center; }
.ring-pct   { font-family:var(--font-display);font-size:1.8rem;font-weight:700; }
.ring-sub   { font-size:.7rem;color:var(--text-soft); }

/* ── Chart containers ────────────────────────────────────────── */
.chart-card { background:var(--white);border-radius:var(--radius-md);border:1px solid var(--border);padding:1.5rem;margin-bottom:1.25rem; }
.chart-header { display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem; }
.chart-title  { font-family:var(--font-display);font-size:1.05rem;font-weight:600; }
canvas { max-width:100%;display:block; }

/* ── Range tabs ──────────────────────────────────────────────── */
.range-tabs { display:flex;gap:.35rem; }
.range-tab  {
  padding:.35rem .85rem; border-radius:50px; font-size:.8rem; font-weight:600;
  border:1.5px solid var(--border); background:var(--white); color:var(--text-soft);
  text-decoration:none; transition:var(--transition);
}
.range-tab:hover  { border-color:var(--sage); color:var(--sage-dark); }
.range-tab.active { border-color:var(--sage-dark);background:var(--sage-dark);color:var(--white); }

/* ── Log form ────────────────────────────────────────────────── */
.log-form-grid { display:grid;grid-template-columns:1fr 1fr;gap:.75rem; }
.log-section-title { font-size:.82rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text-soft);margin:.75rem 0 .5rem; }

/* ── Weight card ─────────────────────────────────────────────── */
.wt-display { display:flex;align-items:flex-end;gap:.4rem;margin:.5rem 0; }
.wt-kg      { font-family:var(--font-display);font-size:2.2rem;font-weight:700; }
.wt-unit    { font-size:.9rem;color:var(--text-soft);margin-bottom:.35rem; }
.wt-change  { font-size:.82rem;font-weight:600; }
.wt-change.down { color:var(--sage-dark); }
.wt-change.up   { color:var(--peach); }

/* ── Macro ring ──────────────────────────────────────────────── */
.macro-ring-row { display:flex;align-items:center;gap:1rem; }
.macro-ring-legend { flex:1; display:flex;flex-direction:column;gap:.5rem; }
.mrl-item { display:flex;align-items:center;gap:.5rem;font-size:.8rem; }
.mrl-dot  { width:10px;height:10px;border-radius:50%;flex-shrink:0; }
.mrl-label { color:var(--text-mid);flex:1; }
.mrl-val   { font-weight:600; }

@media (max-width:1000px) {
  .nut-layout  { grid-template-columns:1fr; }
  .nut-stats   { grid-template-columns:repeat(2,1fr); }
  .log-form-grid { grid-template-columns:1fr; }
}
@media (max-width:600px) {
  .nut-stats { grid-template-columns:1fr 1fr; }
}
</style>

<!-- Chart.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>

<!-- ── Page Header ─────────────────────────────────────────── -->
<div class="section-header" style="margin-bottom:1.5rem">
  <div>
    <h1 style="font-size:1.6rem;margin-bottom:.2rem">📊 Nutrition Insights</h1>
    <p style="font-size:.88rem">Track your daily intake and monitor progress</p>
  </div>
  <div class="range-tabs">
    <a href="?range=7"  class="range-tab <?= $range===7  ? 'active':'' ?>">7 days</a>
    <a href="?range=14" class="range-tab <?= $range===14 ? 'active':'' ?>">14 days</a>
    <a href="?range=30" class="range-tab <?= $range===30 ? 'active':'' ?>">30 days</a>
  </div>
</div>

<!-- ── Summary Stats ───────────────────────────────────────── -->
<div class="nut-stats">
  <div class="nstat nstat-cal">
    <div class="nstat-icon">🔥</div>
    <div class="nstat-val"><?= number_format($avgCal) ?></div>
    <div class="nstat-label">Avg. Daily Calories</div>
    <div class="nstat-sub">Target: <?= number_format($calTarget) ?> kcal</div>
  </div>
  <div class="nstat nstat-prot">
    <div class="nstat-icon">💪</div>
    <div class="nstat-val"><?= $avgProt ?>g</div>
    <div class="nstat-label">Avg. Protein</div>
    <div class="nstat-sub">per day</div>
  </div>
  <div class="nstat nstat-carb">
    <div class="nstat-icon">🌾</div>
    <div class="nstat-val"><?= $avgCarbs ?>g</div>
    <div class="nstat-label">Avg. Carbs</div>
    <div class="nstat-sub">per day</div>
  </div>
  <div class="nstat nstat-fat">
    <div class="nstat-icon">🫒</div>
    <div class="nstat-val"><?= $avgFat ?>g</div>
    <div class="nstat-label">Avg. Fat</div>
    <div class="nstat-sub">per day</div>
  </div>
</div>

<!-- ── Main 2-col Layout ───────────────────────────────────── -->
<div class="nut-layout">

  <!-- ── LEFT: Charts ──────────────────────────────────────── -->
  <div>

    <!-- Calorie Bar Chart -->
    <div class="chart-card">
      <div class="chart-header">
        <div class="chart-title">🔥 Daily Calorie Intake</div>
        <div style="font-size:.78rem;color:var(--text-soft)"><?= $loggedDays ?> days logged</div>
      </div>
      <canvas id="calChart" height="90"></canvas>
    </div>

    <!-- Macro Area Chart -->
    <div class="chart-card">
      <div class="chart-header">
        <div class="chart-title">🥗 Macronutrient Trend</div>
      </div>
      <canvas id="macroChart" height="90"></canvas>
    </div>

    <!-- Weight Line Chart -->
    <?php if (count($wtVals) >= 2): ?>
    <div class="chart-card">
      <div class="chart-header">
        <div class="chart-title">⚖️ Weight Progress</div>
        <div style="font-size:.78rem;color:var(--text-soft)"><?= count($wtVals) ?> entries</div>
      </div>
      <canvas id="weightChart" height="80"></canvas>
    </div>
    <?php endif; ?>

  </div>

  <!-- ── RIGHT: Sidebar cards ──────────────────────────────── -->
  <div style="display:flex;flex-direction:column;gap:1.25rem">

    <!-- Today's Progress Ring -->
    <div class="card today-ring-card">
      <div style="font-size:.82rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text-soft);margin-bottom:.75rem">Today's Calories</div>
      <?php
      $r = 55; $circ = 2 * M_PI * $r;
      $offset = $circ - ($calPct / 100 * $circ);
      ?>
      <div class="ring-outer">
        <svg class="ring-svg" width="140" height="140" viewBox="0 0 140 140">
          <circle class="ring-bg-c"   cx="70" cy="70" r="<?= $r ?>"/>
          <circle class="ring-fill-c" cx="70" cy="70" r="<?= $r ?>"
            stroke-dasharray="<?= round($circ,2) ?>"
            stroke-dashoffset="<?= round($offset,2) ?>"
            stroke="<?= $todayCal > $calTarget && $calTarget > 0 ? '#f2a07b' : '#6b9e62' ?>"/>
        </svg>
        <div class="ring-inner">
          <div class="ring-pct"><?= $calPct ?>%</div>
          <div class="ring-sub">of goal</div>
        </div>
      </div>
      <div style="font-size:.88rem;color:var(--text-mid)">
        <strong><?= number_format($todayCal) ?></strong> / <?= number_format($calTarget) ?> kcal
      </div>
      <?php if ($todayCal === 0): ?>
      <div style="font-size:.8rem;color:var(--text-soft);margin-top:.5rem">Log today's nutrition below →</div>
      <?php endif; ?>
    </div>

    <!-- Today's Macro Donut -->
    <?php if ($todayLog): ?>
    <div class="card" style="padding:1.5rem">
      <div style="font-size:.82rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text-soft);margin-bottom:1rem">Today's Macros</div>
      <?php
      $tp = (float)$todayLog['protein_g']; $tc = (float)$todayLog['carbs_g']; $tf = (float)$todayLog['fat_g'];
      $tm = $tp + $tc + $tf ?: 1;
      $pp = round($tp/$tm*100); $pc = round($tc/$tm*100); $pf = round($tf/$tm*100);
      ?>
      <div class="macro-ring-row">
        <canvas id="todayMacroDonut" width="90" height="90" style="flex-shrink:0"></canvas>
        <div class="macro-ring-legend">
          <div class="mrl-item"><div class="mrl-dot" style="background:#a8c5a0"></div><span class="mrl-label">Protein</span><span class="mrl-val"><?= $tp ?>g (<?= $pp ?>%)</span></div>
          <div class="mrl-item"><div class="mrl-dot" style="background:#f2a07b"></div><span class="mrl-label">Carbs</span><span class="mrl-val"><?= $tc ?>g (<?= $pc ?>%)</span></div>
          <div class="mrl-item"><div class="mrl-dot" style="background:#c5b8e8"></div><span class="mrl-label">Fat</span><span class="mrl-val"><?= $tf ?>g (<?= $pf ?>%)</span></div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Weight Card -->
    <div class="card" style="padding:1.5rem">
      <div style="font-size:.82rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text-soft);margin-bottom:.5rem">⚖️ Current Weight</div>
      <?php if ($currentWt > 0): ?>
      <div class="wt-display">
        <div class="wt-kg"><?= $currentWt ?></div>
        <div class="wt-unit">kg</div>
      </div>
      <?php if ($goalWeight > 0): ?>
      <div style="font-size:.82rem;color:var(--text-soft)">
        Goal: <?= $goalWeight ?>kg
        (<?= abs(round($currentWt - $goalWeight, 1)) ?>kg <?= $currentWt > $goalWeight ? 'to lose' : 'to gain' ?>)
      </div>
      <?php endif; ?>
      <?php if ($wtChange !== 0.0 && count($wtVals) >= 2): ?>
      <div class="wt-change <?= $wtChange < 0 ? 'down' : 'up' ?>" style="margin-top:.5rem">
        <?= $wtChange < 0 ? '↓' : '↑' ?> <?= abs($wtChange) ?>kg this period
      </div>
      <?php endif; ?>
      <?php else: ?>
      <div style="font-size:.88rem;color:var(--text-soft)">No weight logged yet</div>
      <?php endif; ?>

      <hr class="divider" style="margin:.85rem 0">

      <!-- Weight log form -->
      <form method="POST">
        <input type="hidden" name="log_weight">
        <div class="form-group" style="margin-bottom:.6rem">
          <label class="form-label" style="font-size:.8rem">Log Weight</label>
          <div style="display:flex;gap:.5rem">
            <input class="form-control" type="number" name="weight_kg" step="0.1" min="20" max="300"
                   placeholder="kg" style="font-size:.88rem" required>
            <input class="form-control" type="date" name="wt_date" value="<?= date('Y-m-d') ?>"
                   style="font-size:.88rem">
          </div>
        </div>
        <button class="btn btn-outline btn-sm" style="width:100%">Log Weight</button>
      </form>
    </div>

    <!-- Quick Log Nutrition -->
    <div class="card" style="padding:1.5rem">
      <div style="font-size:.82rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text-soft);margin-bottom:.75rem">📝 Log Nutrition</div>
      <form method="POST">
        <input type="hidden" name="log_nutrition">
        <div class="form-group">
          <label class="form-label" style="font-size:.8rem">Date</label>
          <input class="form-control" type="date" name="log_date"
                 value="<?= date('Y-m-d') ?>" style="font-size:.88rem">
        </div>
        <div class="log-form-grid">
          <div class="form-group">
            <label class="form-label" style="font-size:.78rem">Calories</label>
            <input class="form-control" type="number" name="calories" placeholder="kcal"
                   value="<?= $todayLog['calories'] ?? '' ?>" style="font-size:.88rem">
          </div>
          <div class="form-group">
            <label class="form-label" style="font-size:.78rem">Protein (g)</label>
            <input class="form-control" type="number" name="protein_g" step="0.1" placeholder="g"
                   value="<?= $todayLog['protein_g'] ?? '' ?>" style="font-size:.88rem">
          </div>
          <div class="form-group">
            <label class="form-label" style="font-size:.78rem">Carbs (g)</label>
            <input class="form-control" type="number" name="carbs_g" step="0.1" placeholder="g"
                   value="<?= $todayLog['carbs_g'] ?? '' ?>" style="font-size:.88rem">
          </div>
          <div class="form-group">
            <label class="form-label" style="font-size:.78rem">Fat (g)</label>
            <input class="form-control" type="number" name="fat_g" step="0.1" placeholder="g"
                   value="<?= $todayLog['fat_g'] ?? '' ?>" style="font-size:.88rem">
          </div>
          <div class="form-group">
            <label class="form-label" style="font-size:.78rem">Fibre (g)</label>
            <input class="form-control" type="number" name="fiber_g" step="0.1" placeholder="g"
                   value="<?= $todayLog['fiber_g'] ?? '' ?>" style="font-size:.88rem">
          </div>
          <div class="form-group">
            <label class="form-label" style="font-size:.78rem">Water (ml)</label>
            <input class="form-control" type="number" name="water_ml" placeholder="ml"
                   value="<?= $todayLog['water_ml'] ?? '' ?>" style="font-size:.88rem">
          </div>
        </div>
        <div class="form-group">
          <input class="form-control" type="text" name="notes" placeholder="Notes (optional)"
                 value="<?= sanitize($todayLog['notes'] ?? '') ?>" style="font-size:.88rem">
        </div>
        <button class="btn btn-primary btn-sm" style="width:100%">Save Log</button>
      </form>
    </div>

  </div><!-- right sidebar -->
</div><!-- .nut-layout -->

<script>
const chartDates  = <?= json_encode($chartDates) ?>;
const chartCal    = <?= json_encode($chartCal) ?>;
const chartProt   = <?= json_encode($chartProt) ?>;
const chartCarbs  = <?= json_encode($chartCarbs) ?>;
const chartFat    = <?= json_encode($chartFat) ?>;
const calTarget   = <?= $calTarget ?>;
const wtDates     = <?= json_encode($wtDates) ?>;
const wtVals      = <?= json_encode($wtVals) ?>;

// Shared options
const sharedFontDefaults = () => {
  Chart.defaults.font.family = "'DM Sans', sans-serif";
  Chart.defaults.color = '#718096';
};
sharedFontDefaults();

const gridColor = 'rgba(0,0,0,.05)';
const baseOpts  = {
  responsive: true,
  plugins: { legend: { display: false }, tooltip: { mode: 'index', intersect: false } },
  scales: {
    x: { grid: { color: gridColor }, ticks: { maxTicksLimit: 8, font: { size: 11 } } },
    y: { grid: { color: gridColor }, ticks: { font: { size: 11 } } }
  }
};

// ── Calorie Bar Chart ──────────────────────────────────────────
new Chart(document.getElementById('calChart'), {
  type: 'bar',
  data: {
    labels: chartDates,
    datasets: [
      {
        label: 'Calories',
        data: chartCal,
        backgroundColor: chartCal.map(v => v > calTarget && calTarget > 0 ? 'rgba(242,160,123,.75)' : 'rgba(107,158,98,.75)'),
        borderRadius: 6, borderSkipped: false,
      },
      {
        label: 'Target',
        data: Array(chartCal.length).fill(calTarget > 0 ? calTarget : null),
        type: 'line', borderColor: 'rgba(197,184,232,.8)', borderDash: [5,4],
        borderWidth: 2, pointRadius: 0, fill: false,
      }
    ]
  },
  options: { ...baseOpts, plugins: { ...baseOpts.plugins, legend: { display: true, position: 'bottom', labels: { font: { size: 11 } } } } }
});

// ── Macro Stacked Area Chart ───────────────────────────────────
new Chart(document.getElementById('macroChart'), {
  type: 'line',
  data: {
    labels: chartDates,
    datasets: [
      { label: 'Protein (g)',  data: chartProt,  fill: true, backgroundColor: 'rgba(168,197,160,.3)', borderColor: '#a8c5a0', tension: .4, pointRadius: 3 },
      { label: 'Carbs (g)',    data: chartCarbs, fill: true, backgroundColor: 'rgba(242,160,123,.25)', borderColor: '#f2a07b', tension: .4, pointRadius: 3 },
      { label: 'Fat (g)',      data: chartFat,   fill: true, backgroundColor: 'rgba(197,184,232,.25)', borderColor: '#c5b8e8', tension: .4, pointRadius: 3 },
    ]
  },
  options: {
    ...baseOpts,
    plugins: { ...baseOpts.plugins, legend: { display: true, position: 'bottom', labels: { font: { size: 11 } } } }
  }
});

// ── Weight Line Chart ──────────────────────────────────────────
if (document.getElementById('weightChart') && wtVals.length >= 2) {
  const goalWt = <?= $goalWeight ?: 'null' ?>;
  const datasets = [
    {
      label: 'Weight (kg)', data: wtVals,
      borderColor: '#6b9e62', backgroundColor: 'rgba(168,197,160,.15)',
      fill: true, tension: .4, pointRadius: 5, pointBackgroundColor: '#6b9e62',
    }
  ];
  if (goalWt) {
    datasets.push({
      label: 'Goal', data: Array(wtVals.length).fill(goalWt),
      borderColor: 'rgba(197,184,232,.8)', borderDash: [5,4],
      borderWidth: 2, pointRadius: 0, fill: false,
    });
  }
  new Chart(document.getElementById('weightChart'), {
    type: 'line', data: { labels: wtDates, datasets },
    options: {
      ...baseOpts,
      plugins: { ...baseOpts.plugins, legend: { display: !!goalWt, position: 'bottom', labels: { font: { size: 11 } } } },
      scales: { ...baseOpts.scales, y: { ...baseOpts.scales.y, beginAtZero: false } }
    }
  });
}

// ── Today Macro Donut ─────────────────────────────────────────
<?php if ($todayLog && ($todayLog['protein_g'] + $todayLog['carbs_g'] + $todayLog['fat_g']) > 0): ?>
new Chart(document.getElementById('todayMacroDonut'), {
  type: 'doughnut',
  data: {
    datasets: [{
      data: [<?= (float)$todayLog['protein_g'] ?>, <?= (float)$todayLog['carbs_g'] ?>, <?= (float)$todayLog['fat_g'] ?>],
      backgroundColor: ['#a8c5a0','#f2a07b','#c5b8e8'],
      borderWidth: 0, hoverOffset: 4,
    }]
  },
  options: {
    cutout: '65%', responsive: false,
    plugins: { legend: { display: false }, tooltip: { enabled: false } }
  }
});
<?php endif; ?>
</script>

<?php require 'includes/footer.php'; ?>