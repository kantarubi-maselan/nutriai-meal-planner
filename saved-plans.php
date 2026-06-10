<?php
require_once 'includes/config.php';
requireLogin();

$db     = getDB();
$userId = currentUserId();

// ── Handle actions ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $pid    = (int)($_POST['plan_id'] ?? 0);

    if ($action === 'delete' && $pid) {
        $db->prepare("DELETE FROM meal_plans WHERE id=? AND user_id=?")->execute([$pid, $userId]);
        flashSet('success', 'Plan deleted successfully.');
        redirect('saved-plans.php');
    }
    if ($action === 'unsave' && $pid) {
        $db->prepare("UPDATE meal_plans SET is_saved=0 WHERE id=? AND user_id=?")->execute([$pid, $userId]);
        flashSet('info', 'Plan removed from saved.');
        redirect('saved-plans.php');
    }
    if ($action === 'set_active' && $pid) {
        $db->prepare("UPDATE meal_plans SET is_active=0 WHERE user_id=?")->execute([$userId]);
        $db->prepare("UPDATE meal_plans SET is_active=1 WHERE id=? AND user_id=?")->execute([$pid, $userId]);
        flashSet('success', 'Plan set as your active meal plan!');
        redirect('saved-plans.php');
    }
    if ($action === 'duplicate' && $pid) {
        // Copy plan
        $stmt = $db->prepare("SELECT * FROM meal_plans WHERE id=? AND user_id=?");
        $stmt->execute([$pid, $userId]);
        $original = $stmt->fetch();
        if ($original) {
            $db->prepare("
                INSERT INTO meal_plans (user_id,title,goal,diet_type,target_calories,ingredients,plan_json,ai_explanation,week_start,is_active,is_saved)
                VALUES (?,?,?,?,?,?,?,?,CURDATE(),0,1)
            ")->execute([
                $userId,
                'Copy of ' . $original['title'],
                $original['goal'], $original['diet_type'],
                $original['target_calories'], $original['ingredients'],
                $original['plan_json'], $original['ai_explanation']
            ]);
            $newId = $db->lastInsertId();
            // Copy meals
            $meals = $db->prepare("SELECT * FROM meals WHERE plan_id=?");
            $meals->execute([$pid]);
            $mStmt = $db->prepare("INSERT INTO meals (plan_id,day_number,meal_type,name,description,calories,protein_g,carbs_g,fat_g,fiber_g,recipe_steps,ingredients) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
            foreach ($meals->fetchAll() as $m) {
                $mStmt->execute([$newId,$m['day_number'],$m['meal_type'],$m['name'],$m['description'],$m['calories'],$m['protein_g'],$m['carbs_g'],$m['fat_g'],$m['fiber_g'],$m['recipe_steps'],$m['ingredients']]);
            }
            flashSet('success', 'Plan duplicated to your saved collection!');
        }
        redirect('saved-plans.php');
    }
}

// ── Load plans ────────────────────────────────────────────────
$filter = $_GET['filter'] ?? 'saved'; // saved | all
$search = trim($_GET['q'] ?? '');

$where  = "WHERE mp.user_id = ?";
$params = [$userId];
if ($filter === 'saved') { $where .= " AND mp.is_saved = 1"; }
if ($search) { $where .= " AND mp.title LIKE ?"; $params[] = "%{$search}%"; }

$stmt = $db->prepare("
    SELECT mp.*,
           COUNT(DISTINCT m.id) as meal_count,
           COUNT(DISTINCT m.day_number) as day_count,
           SUM(m.calories) as total_cal
    FROM meal_plans mp
    LEFT JOIN meals m ON m.plan_id = mp.id
    {$where}
    GROUP BY mp.id
    ORDER BY mp.created_at DESC
");
$stmt->execute($params);
$plans = $stmt->fetchAll();

// Counts
$stmtSaved = $db->prepare("SELECT COUNT(*) FROM meal_plans WHERE user_id=? AND is_saved=1");
$stmtSaved->execute([$userId]);
$savedCount = $stmtSaved->fetchColumn();

$stmtAll = $db->prepare("SELECT COUNT(*) FROM meal_plans WHERE user_id=?");
$stmtAll->execute([$userId]);
$allCount = $stmtAll->fetchColumn();

$dietBadge = ['normal'=>'🍽️','halal'=>'🕌','vegetarian'=>'🥦','vegan'=>'🌱','keto'=>'🥑','paleo'=>'🥩'];
$goalBadge = ['weight_loss'=>'⬇️ Lose Weight','weight_gain'=>'⬆️ Gain Weight','maintain'=>'⚖️ Maintain','muscle_build'=>'💪 Build Muscle'];
$goalColor = ['weight_loss'=>'rose','weight_gain'=>'sky','maintain'=>'sage','muscle_build'=>'peach'];

$pageTitle  = 'Saved Plans';
$activePage = 'saved';
require 'includes/header.php';
?>

<style>
/* ── Plans Grid ─────────────────────────────────────────────── */
.plans-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.25rem; }

.plan-card {
  background: var(--white); border-radius: var(--radius-md);
  border: 1px solid var(--border); overflow: hidden;
  transition: var(--transition); display: flex; flex-direction: column;
}
.plan-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); }
.plan-card.active-plan { border-color: var(--sage-dark); border-width: 2px; }

.plan-card-top {
  padding: 1.5rem 1.5rem 1rem;
  background: linear-gradient(135deg, var(--cream) 0%, var(--white) 100%);
  flex: 1;
}
.plan-card-title { font-family: var(--font-display); font-size: 1.1rem; font-weight: 700; margin-bottom: .6rem; }
.plan-card-badges { display: flex; gap: .4rem; flex-wrap: wrap; margin-bottom: .85rem; }
.plan-card-stats {
  display: grid; grid-template-columns: repeat(3,1fr);
  gap: .5rem; background: var(--cream); border-radius: var(--radius-sm);
  padding: .75rem; margin-top: .75rem;
}
.plan-stat { text-align: center; }
.plan-stat-val { font-family: var(--font-display); font-size: 1.1rem; font-weight: 700; color: var(--text-dark); }
.plan-stat-lbl { font-size: .68rem; color: var(--text-soft); text-transform: uppercase; letter-spacing: .04em; }

.plan-card-bottom {
  padding: 1rem 1.5rem; border-top: 1px solid var(--border);
  display: flex; align-items: center; justify-content: space-between; gap: .5rem;
  background: var(--white); flex-wrap: wrap;
}
.plan-date { font-size: .78rem; color: var(--text-soft); }

/* Active badge on card */
.active-ribbon {
  background: var(--sage-dark); color: var(--white);
  font-size: .7rem; font-weight: 700; padding: .2rem .65rem;
  border-radius: 0 0 var(--radius-sm) var(--radius-sm);
  display: inline-block; margin-bottom: .5rem; text-transform: uppercase; letter-spacing: .05em;
}

/* Toolbar */
.plans-toolbar {
  display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;
  margin-bottom: 1.75rem;
}
.filter-tabs { display: flex; gap: .35rem; }
.filter-tab {
  padding: .5rem 1.1rem; border-radius: 50px; font-size: .85rem; font-weight: 600;
  border: 1.5px solid var(--border); background: var(--white); color: var(--text-soft);
  cursor: pointer; transition: var(--transition); text-decoration: none; display: inline-block;
}
.filter-tab:hover  { border-color: var(--sage); color: var(--sage-dark); }
.filter-tab.active { border-color: var(--sage-dark); background: var(--sage-dark); color: var(--white); }
.search-wrap { flex: 1; max-width: 280px; position: relative; }
.search-wrap input { width: 100%; padding-left: 2.2rem; }
.search-icon { position: absolute; left: .75rem; top: 50%; transform: translateY(-50%); color: var(--text-soft); font-size: .9rem; }

/* Empty state */
.empty-state {
  grid-column: 1/-1; text-align: center; padding: 4rem 2rem;
  background: var(--white); border-radius: var(--radius-md); border: 1px dashed var(--border);
}
.empty-icon { font-size: 3.5rem; margin-bottom: 1rem; }

/* Confirm dialog */
.confirm-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,.4); z-index: 300;
  display: flex; align-items: center; justify-content: center;
  opacity: 0; pointer-events: none; transition: opacity .2s;
}
.confirm-overlay.open { opacity: 1; pointer-events: all; }
.confirm-box {
  background: var(--white); border-radius: var(--radius-md); padding: 2rem;
  width: 90%; max-width: 360px; text-align: center;
  transform: scale(.95); transition: transform .2s;
}
.confirm-overlay.open .confirm-box { transform: scale(1); }
</style>

<!-- ── Page Header ─────────────────────────────────────────────── -->
<div class="section-header" style="margin-bottom:1.5rem">
  <div>
    <h1 style="font-size:1.6rem;margin-bottom:.2rem">💾 Saved Plans</h1>
    <p style="font-size:.88rem">Your generated meal plans and history</p>
  </div>
  <a href="generator.php" class="btn btn-primary">✨ New Plan</a>
</div>

<!-- ── Toolbar ─────────────────────────────────────────────────── -->
<div class="plans-toolbar">
  <div class="filter-tabs">
    <a href="?filter=saved" class="filter-tab <?= $filter==='saved' ? 'active':'' ?>">
      💾 Saved <span style="opacity:.7">(<?= $savedCount ?>)</span>
    </a>
    <a href="?filter=all" class="filter-tab <?= $filter==='all' ? 'active':'' ?>">
      📋 All Plans <span style="opacity:.7">(<?= $allCount ?>)</span>
    </a>
  </div>
  <form method="GET" class="search-wrap">
    <input type="hidden" name="filter" value="<?= $filter ?>">
    <span class="search-icon">🔍</span>
    <input class="form-control" type="text" name="q" placeholder="Search plans…"
           value="<?= sanitize($search) ?>" style="padding-left:2.2rem">
  </form>
</div>

<!-- ── Plans Grid ─────────────────────────────────────────────── -->
<div class="plans-grid">
  <?php if (empty($plans)): ?>
  <div class="empty-state">
    <div class="empty-icon">📂</div>
    <h2 style="font-size:1.3rem;margin-bottom:.5rem">
      <?= $search ? 'No plans match your search' : ($filter==='saved' ? 'No saved plans yet' : 'No plans generated yet') ?>
    </h2>
    <p style="margin-bottom:1.5rem">
      <?= $search ? 'Try a different search term.' : 'Generate your first AI meal plan to get started.' ?>
    </p>
    <?php if (!$search): ?>
    <a href="generator.php" class="btn btn-primary">✨ Generate Meal Plan</a>
    <?php endif; ?>
  </div>
  <?php else: ?>
  <?php foreach ($plans as $p):
    $dayCount  = (int)($p['day_count'] ?? 1);
    $mealCount = (int)($p['meal_count'] ?? 0);
    $avgCal    = $dayCount > 0 ? round(($p['total_cal'] ?? 0) / $dayCount) : ($p['target_calories'] ?? 0);
    $gColor    = $goalColor[$p['goal']] ?? 'sage';
  ?>
  <div class="plan-card <?= $p['is_active'] ? 'active-plan' : '' ?>">
    <div class="plan-card-top">
      <?php if ($p['is_active']): ?>
      <div class="active-ribbon">⚡ Active Plan</div>
      <?php endif; ?>

      <div class="plan-card-title"><?= sanitize($p['title']) ?></div>

      <div class="plan-card-badges">
        <span class="badge badge-<?= $gColor ?>"><?= $goalBadge[$p['goal']] ?? $p['goal'] ?></span>
        <span class="badge badge-lavender"><?= ($dietBadge[$p['diet_type']] ?? '🍽️') . ' ' . ucfirst($p['diet_type']) ?></span>
        <?php if ($p['is_saved']): ?>
        <span class="badge badge-sage">💾 Saved</span>
        <?php endif; ?>
      </div>

      <?php if ($p['ai_explanation']): ?>
      <p style="font-size:.82rem;color:var(--text-soft);line-height:1.55;-webkit-line-clamp:2;display:-webkit-box;-webkit-box-orient:vertical;overflow:hidden">
        <?= sanitize($p['ai_explanation']) ?>
      </p>
      <?php endif; ?>

      <div class="plan-card-stats">
        <div class="plan-stat">
          <div class="plan-stat-val"><?= $dayCount ?></div>
          <div class="plan-stat-lbl">Days</div>
        </div>
        <div class="plan-stat">
          <div class="plan-stat-val"><?= $mealCount ?></div>
          <div class="plan-stat-lbl">Meals</div>
        </div>
        <div class="plan-stat">
          <div class="plan-stat-val"><?= number_format($avgCal) ?></div>
          <div class="plan-stat-lbl">Avg kcal</div>
        </div>
      </div>
    </div>

    <div class="plan-card-bottom">
      <div class="plan-date">📅 <?= date('j M Y', strtotime($p['created_at'])) ?></div>
      <div style="display:flex;gap:.4rem;flex-wrap:wrap">
        <a href="results.php?plan=<?= $p['id'] ?>" class="btn btn-primary btn-sm">View →</a>

        <?php if (!$p['is_active']): ?>
        <form method="POST" style="display:inline">
          <input type="hidden" name="action" value="set_active">
          <input type="hidden" name="plan_id" value="<?= $p['id'] ?>">
          <button class="btn btn-outline btn-sm">⚡ Use</button>
        </form>
        <?php endif; ?>

        <button class="btn btn-ghost btn-sm" onclick="openMenu(<?= $p['id'] ?>)">⋯</button>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- ── Context Menu (hidden forms) ───────────────────────────── -->
<?php foreach ($plans as $p): ?>
<form method="POST" id="form-dup-<?= $p['id'] ?>" style="display:none">
  <input type="hidden" name="action" value="duplicate">
  <input type="hidden" name="plan_id" value="<?= $p['id'] ?>">
</form>
<form method="POST" id="form-unsave-<?= $p['id'] ?>" style="display:none">
  <input type="hidden" name="action" value="unsave">
  <input type="hidden" name="plan_id" value="<?= $p['id'] ?>">
</form>
<form method="POST" id="form-del-<?= $p['id'] ?>" style="display:none">
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="plan_id" value="<?= $p['id'] ?>">
</form>
<?php endforeach; ?>

<!-- ── Action Menu Modal ──────────────────────────────────────── -->
<div class="confirm-overlay" id="menuOverlay" onclick="if(event.target===this)closeMenu()">
  <div class="confirm-box">
    <div style="font-size:1.5rem;margin-bottom:.75rem" id="menuIcon">⋯</div>
    <h3 style="font-size:1.1rem;margin-bottom:1.25rem" id="menuTitle">Plan Options</h3>
    <div style="display:flex;flex-direction:column;gap:.6rem" id="menuActions"></div>
    <button onclick="closeMenu()" class="btn btn-ghost btn-sm" style="margin-top:1rem;width:100%">Cancel</button>
  </div>
</div>

<script>
let activePlanId = null;

function openMenu(planId) {
  activePlanId = planId;
  const isSaved = <?= json_encode(array_column($plans, 'is_saved', 'id')) ?>[planId];
  const title   = <?= json_encode(array_column($plans, 'title', 'id')) ?>[planId];

  document.getElementById('menuTitle').textContent = title || 'Plan Options';
  const actions = document.getElementById('menuActions');
  actions.innerHTML = `
    <a href="results.php?plan=${planId}" class="btn btn-primary" style="width:100%">👁 View Plan</a>
    <button onclick="submitForm('dup')" class="btn btn-outline" style="width:100%">📋 Duplicate Plan</button>
    ${isSaved ? `<button onclick="submitForm('unsave')" class="btn btn-ghost" style="width:100%">🗑 Remove from Saved</button>` : ''}
    <button onclick="confirmDelete()" class="btn btn-ghost" style="width:100%;color:#c0392b">🗑 Delete Plan</button>
  `;
  document.getElementById('menuOverlay').classList.add('open');
}
function closeMenu() { document.getElementById('menuOverlay').classList.remove('open'); }

function submitForm(type) {
  if (!activePlanId) return;
  document.getElementById(`form-${type}-${activePlanId}`).submit();
}
function confirmDelete() {
  if (confirm('Permanently delete this plan? This cannot be undone.')) {
    document.getElementById(`form-del-${activePlanId}`).submit();
  }
}
</script>

<?php require 'includes/footer.php'; ?>