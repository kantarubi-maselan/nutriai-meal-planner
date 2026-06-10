<?php
require_once 'includes/config.php';
requireLogin();

$db     = getDB();
$userId = currentUserId();

// ── Handle AJAX actions ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');

    // Update checkbox state
    if ($_POST['ajax'] === 'toggle') {
        $listId  = (int)$_POST['list_id'];
        $itemIdx = (int)$_POST['item_idx'];
        $checked = $_POST['checked'] === '1';

        $stmt = $db->prepare("SELECT items_json FROM grocery_lists WHERE id=? AND user_id=?");
        $stmt->execute([$listId, $userId]);
        $row = $stmt->fetch();
        if ($row) {
            $items = json_decode($row['items_json'], true);
            if (isset($items[$itemIdx])) {
                $items[$itemIdx]['checked'] = $checked;
                $db->prepare("UPDATE grocery_lists SET items_json=?, updated_at=NOW() WHERE id=?")
                   ->execute([json_encode($items), $listId]);
                echo json_encode(['success'=>true]);
            }
        }
        exit;
    }

    // Clear all checks
    if ($_POST['ajax'] === 'clear_checks') {
        $listId = (int)$_POST['list_id'];
        $stmt = $db->prepare("SELECT items_json FROM grocery_lists WHERE id=? AND user_id=?");
        $stmt->execute([$listId, $userId]);
        $row = $stmt->fetch();
        if ($row) {
            $items = json_decode($row['items_json'], true);
            foreach ($items as &$item) $item['checked'] = false;
            $db->prepare("UPDATE grocery_lists SET items_json=? WHERE id=?")->execute([json_encode($items), $listId]);
            echo json_encode(['success'=>true]);
        }
        exit;
    }

    echo json_encode(['success'=>false]);
    exit;
}

// ── Load grocery lists (latest first) ────────────────────────
$listId = isset($_GET['list']) ? (int)$_GET['list'] : 0;

$stmt = $db->prepare("
    SELECT gl.*, mp.title as plan_title, mp.diet_type
    FROM grocery_lists gl
    LEFT JOIN meal_plans mp ON mp.id = gl.plan_id
    WHERE gl.user_id = ?
    ORDER BY gl.created_at DESC
    LIMIT 10
");
$stmt->execute([$userId]);
$allLists = $stmt->fetchAll();

// Active list
$activeList = null;
if ($listId) {
    foreach ($allLists as $l) { if ($l['id'] == $listId) { $activeList = $l; break; } }
}
if (!$activeList && $allLists) { $activeList = $allLists[0]; }

$items = $activeList ? json_decode($activeList['items_json'], true) : [];

// Group by category
$grouped = [];
foreach ($items as $idx => $item) {
    $cat = $item['category'] ?? 'Other';
    $grouped[$cat][] = array_merge($item, ['_idx' => $idx]);
}

$categoryIcons = [
    'Protein'     => '🥩',
    'Vegetables'  => '🥦',
    'Carbs'       => '🌾',
    'Dairy & Eggs'=> '🥚',
    'Pantry'      => '🫙',
    'Fruits'      => '🍎',
    'Other'       => '🛒',
];

$totalItems   = count($items);
$checkedCount = count(array_filter($items, fn($i) => $i['checked']));

$pageTitle  = 'Grocery List';
$activePage = 'grocery';
require 'includes/header.php';
?>

<style>
.grocery-layout { display: grid; grid-template-columns: 280px 1fr; gap: 1.5rem; }

/* Sidebar list picker */
.list-picker { display: flex; flex-direction: column; gap: .5rem; }
.list-item {
  padding: .85rem 1rem; border-radius: var(--radius-sm);
  border: 1.5px solid var(--border); background: var(--white);
  cursor: pointer; transition: var(--transition); text-decoration: none; color: inherit;
  display: block;
}
.list-item:hover  { border-color: var(--sage); background: var(--cream); }
.list-item.active { border-color: var(--sage-dark); background: var(--sage-light); }
.list-item-title  { font-weight: 600; font-size: .9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.list-item-meta   { font-size: .76rem; color: var(--text-soft); margin-top: .15rem; }

/* Progress bar */
.progress-bar { height: 8px; background: var(--border); border-radius: 4px; overflow: hidden; margin: .75rem 0; }
.progress-fill { height: 100%; background: var(--sage-dark); border-radius: 4px; transition: width .5s ease; }

/* Category section */
.category-section { margin-bottom: 1.75rem; }
.category-header {
  display: flex; align-items: center; gap: .6rem;
  font-size: .82rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em;
  color: var(--text-soft); margin-bottom: .75rem; padding-bottom: .5rem;
  border-bottom: 1px solid var(--border);
}
.category-icon { font-size: 1rem; }

/* Grocery items */
.grocery-item {
  display: flex; align-items: center; gap: .85rem;
  padding: .75rem 1rem; border-radius: var(--radius-sm);
  border: 1px solid var(--border); background: var(--white);
  margin-bottom: .5rem; transition: var(--transition); cursor: pointer;
}
.grocery-item:hover   { border-color: var(--sage); }
.grocery-item.checked { background: var(--cream); opacity: .65; }
.grocery-item.checked .grocery-name { text-decoration: line-through; color: var(--text-soft); }

.grocery-checkbox {
  width: 20px; height: 20px; border-radius: 6px;
  border: 2px solid var(--border); appearance: none; cursor: pointer;
  flex-shrink: 0; transition: var(--transition); position: relative;
}
.grocery-checkbox:checked { background: var(--sage-dark); border-color: var(--sage-dark); }
.grocery-checkbox:checked::after {
  content: '✓'; position: absolute; top: 50%; left: 50%;
  transform: translate(-50%,-50%); color: white; font-size: .72rem; font-weight: 700;
}
.grocery-name { flex: 1; font-size: .92rem; font-weight: 500; }

/* Action toolbar */
.grocery-toolbar {
  display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .75rem;
  padding: 1rem 1.25rem; background: var(--white);
  border-radius: var(--radius-md); border: 1px solid var(--border);
  margin-bottom: 1.5rem;
}
.toolbar-progress { display: flex; align-items: center; gap: .75rem; }
.toolbar-actions  { display: flex; gap: .5rem; }

/* Empty state */
.grocery-empty { text-align: center; padding: 4rem 2rem; }
.grocery-empty .icon { font-size: 3.5rem; margin-bottom: 1rem; }

/* Export modal */
.modal-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,.35); z-index: 200;
  display: flex; align-items: center; justify-content: center;
  opacity: 0; pointer-events: none; transition: opacity .25s;
}
.modal-overlay.open { opacity: 1; pointer-events: all; }
.modal-box {
  background: var(--white); border-radius: var(--radius-lg);
  padding: 2rem; width: 90%; max-width: 480px;
  transform: translateY(20px); transition: transform .25s;
}
.modal-overlay.open .modal-box { transform: none; }

@media (max-width:900px) {
  .grocery-layout { grid-template-columns: 1fr; }
  .list-picker { flex-direction: row; overflow-x: auto; padding-bottom: .5rem; }
  .list-item   { min-width: 180px; }
}
</style>

<!-- ── Page Header ─────────────────────────────────────────── -->
<div class="section-header" style="margin-bottom:1.5rem">
  <div>
    <h1 style="font-size:1.6rem;margin-bottom:.2rem">🛒 Grocery List</h1>
    <p style="font-size:.88rem">Auto-generated from your meal plans</p>
  </div>
  <div style="display:flex;gap:.75rem">
    <?php if ($activeList): ?>
    <button class="btn btn-outline btn-sm" onclick="openExport()">⬇️ Export List</button>
    <button class="btn btn-ghost btn-sm" onclick="clearChecks()">↺ Reset checks</button>
    <?php endif; ?>
    <a href="generator.php" class="btn btn-primary btn-sm">✨ New Plan</a>
  </div>
</div>

<?php if (empty($allLists)): ?>
<!-- ── Empty: No Lists ──────────────────────────────────────── -->
<div class="card grocery-empty">
  <div class="icon">🛒</div>
  <h2 style="font-size:1.3rem;margin-bottom:.5rem">No grocery lists yet</h2>
  <p style="margin-bottom:1.5rem">Generate a meal plan and we'll auto-create your shopping list.</p>
  <a href="generator.php" class="btn btn-primary">✨ Generate Meal Plan</a>
</div>

<?php else: ?>
<div class="grocery-layout">

  <!-- ── List Picker Sidebar ──────────────────────────────────── -->
  <div>
    <div class="card" style="padding:1.25rem;margin-bottom:1rem">
      <div style="font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text-soft);margin-bottom:.75rem">Your Lists</div>
      <div class="list-picker">
        <?php foreach ($allLists as $lst):
          $lstItems   = json_decode($lst['items_json'], true) ?: [];
          $lstChecked = count(array_filter($lstItems, fn($i) => $i['checked']));
        ?>
        <a href="?list=<?= $lst['id'] ?>"
           class="list-item <?= $activeList && $activeList['id'] == $lst['id'] ? 'active' : '' ?>">
          <div class="list-item-title"><?= sanitize($lst['title'] ?? 'Grocery List') ?></div>
          <div class="list-item-meta">
            <?= $lstChecked ?>/<?= count($lstItems) ?> items ·
            <?= date('d M', strtotime($lst['created_at'])) ?>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if ($activeList): ?>
    <!-- Summary card -->
    <div class="card" style="padding:1.25rem">
      <div style="font-size:.82rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text-soft);margin-bottom:.75rem">Progress</div>
      <div style="font-family:var(--font-display);font-size:1.6rem;font-weight:700">
        <?= $checkedCount ?><span style="font-size:1rem;color:var(--text-soft);font-family:var(--font-body)"> / <?= $totalItems ?></span>
      </div>
      <div style="font-size:.8rem;color:var(--text-soft);margin-bottom:.5rem">items collected</div>
      <div class="progress-bar">
        <div class="progress-fill" id="mainProgress"
             style="width:<?= $totalItems ? round($checkedCount/$totalItems*100) : 0 ?>%"></div>
      </div>
      <div style="font-size:.78rem;color:var(--text-soft)"><?= $totalItems ? round($checkedCount/$totalItems*100) : 0 ?>% complete</div>

      <hr class="divider" style="margin:1rem 0">

      <?php foreach (array_keys($grouped) as $cat):
        $catItems   = $grouped[$cat];
        $catChecked = count(array_filter($catItems, fn($i) => $i['checked']));
      ?>
      <div style="display:flex;justify-content:space-between;font-size:.8rem;color:var(--text-mid);margin-bottom:.35rem">
        <span><?= $categoryIcons[$cat] ?? '🛒' ?> <?= sanitize($cat) ?></span>
        <span style="color:var(--text-soft)"><?= $catChecked ?>/<?= count($catItems) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- ── Main Grocery List ────────────────────────────────────── -->
  <div>
    <?php if ($activeList): ?>

    <!-- Toolbar -->
    <div class="grocery-toolbar">
      <div class="toolbar-progress">
        <span style="font-size:.9rem;font-weight:600">
          <?= $totalItems - $checkedCount ?> items remaining
        </span>
        <?php if ($checkedCount === $totalItems && $totalItems > 0): ?>
        <span class="badge badge-sage">✓ All done! 🎉</span>
        <?php endif; ?>
      </div>
      <div class="toolbar-actions">
        <button class="btn btn-ghost btn-sm" onclick="checkAll()">✓ Check all</button>
        <button class="btn btn-ghost btn-sm" onclick="uncheckAll()">✗ Uncheck all</button>
      </div>
    </div>

    <!-- Items by category -->
    <?php if (empty($grouped)): ?>
    <div class="card grocery-empty">
      <div class="icon">🥕</div>
      <p>This list appears to be empty.</p>
    </div>
    <?php else: ?>
    <?php foreach ($grouped as $cat => $catItems): ?>
    <div class="category-section">
      <div class="category-header">
        <span class="category-icon"><?= $categoryIcons[$cat] ?? '🛒' ?></span>
        <?= sanitize($cat) ?>
        <span style="margin-left:auto;font-size:.75rem;font-weight:400;text-transform:none;letter-spacing:0">
          <?= count(array_filter($catItems, fn($i) => $i['checked'])) ?>/<?= count($catItems) ?>
        </span>
      </div>

      <?php foreach ($catItems as $item):
        $idx = $item['_idx'];
        $checked = (bool)$item['checked'];
      ?>
      <div class="grocery-item <?= $checked ? 'checked' : '' ?>"
           id="item-<?= $idx ?>"
           onclick="toggleItem(<?= $activeList['id'] ?>, <?= $idx ?>, this)">
        <input type="checkbox" class="grocery-checkbox"
               <?= $checked ? 'checked' : '' ?>
               onclick="event.stopPropagation(); toggleItem(<?= $activeList['id'] ?>, <?= $idx ?>, this.closest('.grocery-item'))">
        <span class="grocery-name"><?= sanitize($item['name']) ?></span>
        <?php if (!empty($item['qty'])): ?>
        <span style="font-size:.78rem;color:var(--text-soft)"><?= sanitize($item['qty']) ?> <?= sanitize($item['unit'] ?? '') ?></span>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php else: ?>
    <div class="card grocery-empty">
      <div class="icon">🛒</div>
      <p>Select a list from the sidebar to view items.</p>
    </div>
    <?php endif; ?>
  </div>

</div><!-- .grocery-layout -->
<?php endif; ?>

<!-- ── Export Modal ───────────────────────────────────────────── -->
<div class="modal-overlay" id="exportModal" onclick="if(event.target===this)closeExport()">
  <div class="modal-box">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem">
      <h3>⬇️ Export Grocery List</h3>
      <button onclick="closeExport()" style="background:none;border:none;font-size:1.3rem;cursor:pointer;color:var(--text-soft)">×</button>
    </div>
    <p style="font-size:.9rem;margin-bottom:1.5rem">Choose how to export your grocery list:</p>
    <div style="display:flex;flex-direction:column;gap:.75rem">
      <button class="btn btn-outline" onclick="exportText()">📄 Copy as Plain Text</button>
      <button class="btn btn-outline" onclick="exportCSV()">📊 Download as CSV</button>
      <button class="btn btn-outline" onclick="window.print()">🖨️ Print List</button>
    </div>
  </div>
</div>

<script>
const listId = <?= $activeList ? $activeList['id'] : 0 ?>;
let items = <?= json_encode($items) ?>;

// ── Toggle single item ────────────────────────────────────────
async function toggleItem(lid, idx, el) {
  const cb  = el.querySelector('.grocery-checkbox') || el;
  const isChecked = el.classList.contains('checked') ? false : true;

  el.classList.toggle('checked', isChecked);
  const checkbox = el.querySelector('.grocery-checkbox');
  if (checkbox) checkbox.checked = isChecked;

  items[idx].checked = isChecked;
  updateProgress();

  await fetch('grocery.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: `ajax=toggle&list_id=${lid}&item_idx=${idx}&checked=${isChecked ? 1 : 0}`
  });
}

// ── Check / Uncheck all ───────────────────────────────────────
function checkAll() {
  document.querySelectorAll('.grocery-item').forEach(el => {
    if (!el.classList.contains('checked')) toggleItem(listId, parseInt(el.id.split('-')[1]), el);
  });
}
function uncheckAll() {
  document.querySelectorAll('.grocery-item.checked').forEach(el => {
    toggleItem(listId, parseInt(el.id.split('-')[1]), el);
  });
}

// ── Clear checks (server) ─────────────────────────────────────
async function clearChecks() {
  if (!confirm('Reset all checkboxes?')) return;
  await fetch('grocery.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: `ajax=clear_checks&list_id=${listId}`
  });
  location.reload();
}

// ── Progress bar ──────────────────────────────────────────────
function updateProgress() {
  const total   = items.length;
  const checked = items.filter(i => i.checked).length;
  const pct     = total ? Math.round(checked/total*100) : 0;
  const bar = document.getElementById('mainProgress');
  if (bar) bar.style.width = pct + '%';
}

// ── Export ────────────────────────────────────────────────────
function openExport()  { document.getElementById('exportModal').classList.add('open'); }
function closeExport() { document.getElementById('exportModal').classList.remove('open'); }

function exportText() {
  const byCategory = {};
  items.forEach(item => {
    const cat = item.category || 'Other';
    if (!byCategory[cat]) byCategory[cat] = [];
    byCategory[cat].push((item.checked ? '✓ ' : '☐ ') + item.name);
  });
  let text = '🛒 GROCERY LIST\n' + '='.repeat(30) + '\n\n';
  for (const [cat, list] of Object.entries(byCategory)) {
    text += cat.toUpperCase() + '\n';
    text += list.join('\n') + '\n\n';
  }
  navigator.clipboard.writeText(text).then(() => {
    alert('Copied to clipboard! ✓');
    closeExport();
  });
}

function exportCSV() {
  let csv = 'Item,Category,Checked\n';
  items.forEach(item => {
    csv += `"${item.name}","${item.category || 'Other'}","${item.checked ? 'Yes' : 'No'}"\n`;
  });
  const blob = new Blob([csv], {type:'text/csv'});
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'grocery-list.csv';
  a.click();
  closeExport();
}
</script>

<style>
@media print {
  .sidebar, .grocery-toolbar, .list-picker, .modal-overlay,
  .btn, nav, .pub-nav { display: none !important; }
  .grocery-layout { grid-template-columns: 1fr !important; }
  .grocery-item.checked { opacity: 1 !important; }
}
</style>

<?php require 'includes/footer.php'; ?>