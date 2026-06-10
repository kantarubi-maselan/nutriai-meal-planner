<?php
require_once __DIR__ . '/includes/config.php';
requireLogin();

$db      = getDB();
$userId  = currentUserId();

// Load user profile for pre-fill
$stmt = $db->prepare("SELECT * FROM user_profiles WHERE user_id = ?");
$stmt->execute([$userId]);
$profile = $stmt->fetch();

$pageTitle  = 'Generate Meal Plan';
$activePage = 'generator';
require 'includes/header.php';
?>

<style>
/* ── Generator Layout ───────────────────────────────────────── */
.gen-wrap { max-width: 860px; margin: 0 auto; }

.gen-hero {
  background: linear-gradient(135deg, var(--sage-light) 0%, var(--lav-light) 100%);
  border-radius: var(--radius-lg); padding: 2.5rem; margin-bottom: 2rem;
  position: relative; overflow: hidden;
}
.gen-hero::before {
  content:'🥗'; position:absolute; right:2rem; top:50%; transform:translateY(-50%);
  font-size:5rem; opacity:.15;
}
.gen-hero h1 { font-size: 1.8rem; margin-bottom: .4rem; }
.gen-hero p  { color: var(--text-mid); font-size: .95rem; }

/* Steps indicator */
.steps-indicator { display: flex; align-items: center; gap: 0; margin-bottom: 2rem; }
.step-dot {
  display: flex; align-items: center; justify-content: center;
  width: 36px; height: 36px; border-radius: 50%;
  background: var(--border); color: var(--text-soft);
  font-size: .82rem; font-weight: 700; flex-shrink: 0;
  transition: var(--transition);
}
.step-dot.active   { background: var(--sage-dark); color: var(--white); }
.step-dot.complete { background: var(--sage-light); color: var(--sage-dark); }
.step-line { flex: 1; height: 2px; background: var(--border); transition: var(--transition); }
.step-line.complete { background: var(--sage-dark); }
.step-label { font-size: .72rem; font-weight: 600; color: var(--text-soft); margin-top: .3rem; white-space: nowrap; }

/* Form steps */
.form-step { display: none; animation: fadeUp .35s ease; }
.form-step.active { display: block; }

/* Option cards (Goal, Diet) */
.option-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: .85rem; margin-bottom: 1.5rem; }
.option-card {
  border: 2px solid var(--border); border-radius: var(--radius-md);
  padding: 1.25rem 1rem; text-align: center; cursor: pointer;
  transition: var(--transition); background: var(--white);
  position: relative;
}
.option-card:hover { border-color: var(--sage); background: var(--cream); }
.option-card.selected { border-color: var(--sage-dark); background: var(--sage-light); }
.option-card input[type=radio] { position: absolute; opacity: 0; width: 0; height: 0; }
.option-icon  { font-size: 2rem; margin-bottom: .6rem; }
.option-title { font-weight: 600; font-size: .9rem; color: var(--text-dark); }
.option-desc  { font-size: .75rem; color: var(--text-soft); margin-top: .2rem; }
.option-check {
  position: absolute; top: .6rem; right: .6rem;
  width: 20px; height: 20px; border-radius: 50%;
  background: var(--sage-dark); color: var(--white);
  display: none; align-items: center; justify-content: center; font-size: .75rem;
}
.option-card.selected .option-check { display: flex; }

/* Ingredients tag input */
.tag-input-wrap {
  border: 1.5px solid var(--border); border-radius: var(--radius-sm);
  padding: .5rem; background: var(--cream); min-height: 56px;
  display: flex; flex-wrap: wrap; gap: .4rem; align-items: center;
  cursor: text; transition: var(--transition);
}
.tag-input-wrap:focus-within { border-color: var(--sage); background: var(--white); box-shadow: 0 0 0 3px rgba(168,197,160,.2); }
.tag {
  display: inline-flex; align-items: center; gap: .3rem;
  background: var(--sage-light); color: var(--sage-dark);
  padding: .25rem .7rem; border-radius: 50px; font-size: .82rem; font-weight: 500;
}
.tag-remove { background: none; border: none; color: var(--sage-dark); cursor: pointer; font-size: .9rem; line-height: 1; padding: 0; }
.tag-input {
  border: none; background: transparent; outline: none;
  font-family: var(--font-body); font-size: .92rem; flex: 1; min-width: 120px;
  color: var(--text-dark);
}
.tag-suggestions { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: .6rem; }
.tag-suggest-btn {
  border: 1px solid var(--border); border-radius: 50px; padding: .2rem .7rem;
  font-size: .78rem; background: var(--white); cursor: pointer; color: var(--text-mid);
  transition: var(--transition);
}
.tag-suggest-btn:hover { background: var(--sage-light); border-color: var(--sage); color: var(--sage-dark); }

/* Calorie slider */
.calorie-slider { -webkit-appearance: none; width: 100%; height: 6px; border-radius: 3px; background: var(--border); outline: none; }
.calorie-slider::-webkit-slider-thumb {
  -webkit-appearance: none; width: 22px; height: 22px; border-radius: 50%;
  background: var(--sage-dark); cursor: pointer; border: 3px solid var(--white);
  box-shadow: 0 2px 8px rgba(0,0,0,.2);
}
.calorie-display {
  display: flex; justify-content: space-between; align-items: center;
  background: var(--sage-light); border-radius: var(--radius-sm);
  padding: .75rem 1.25rem; margin-top: .75rem;
}
.calorie-num { font-family: var(--font-display); font-size: 1.8rem; font-weight: 700; color: var(--sage-dark); }

/* Duration toggle */
.duration-toggle { display: flex; gap: .5rem; }
.dur-btn {
  flex: 1; padding: .65rem; border-radius: var(--radius-sm);
  border: 1.5px solid var(--border); background: var(--white);
  font-size: .88rem; font-weight: 600; color: var(--text-soft); cursor: pointer;
  transition: var(--transition);
}
.dur-btn.active { border-color: var(--sage-dark); background: var(--sage-light); color: var(--sage-dark); }

/* Review panel */
.review-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem; }
.review-item { background: var(--cream); border-radius: var(--radius-sm); padding: 1rem; }
.review-label { font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--text-soft); margin-bottom: .3rem; }
.review-value { font-weight: 600; font-size: .95rem; }

/* AI loading */
.ai-loading {
  text-align: center; padding: 3rem;
  display: none;
}
.ai-loading-spinner {
  width: 60px; height: 60px; border-radius: 50%;
  border: 4px solid var(--sage-light); border-top-color: var(--sage-dark);
  animation: spin .8s linear infinite; margin: 0 auto 1.5rem;
}
.ai-dots { display: flex; justify-content: center; gap: .4rem; margin-top: 1rem; }
.ai-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--sage-light); animation: pulse 1.4s ease infinite; }
.ai-dot:nth-child(2) { animation-delay: .2s; }
.ai-dot:nth-child(3) { animation-delay: .4s; }
@keyframes pulse { 0%,80%,100%{background:var(--sage-light)} 40%{background:var(--sage-dark)} }

/* Navigation buttons */
.form-nav { display: flex; justify-content: space-between; align-items: center; margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border); }
</style>

<div class="gen-wrap">

  <!-- Hero -->
  <div class="gen-hero">
    <h1>✨ AI Meal Plan Generator</h1>
    <p>Tell us about your goals and we'll create a personalised meal plan powered by AI — in seconds.</p>
  </div>

  <!-- Steps Indicator -->
  <div style="display:flex;flex-direction:column;margin-bottom:2rem">
    <div class="steps-indicator" id="stepsIndicator">
      <div style="display:flex;flex-direction:column;align-items:center">
        <div class="step-dot active" id="dot-1">1</div>
        <div class="step-label">Goal</div>
      </div>
      <div class="step-line" id="line-1"></div>
      <div style="display:flex;flex-direction:column;align-items:center">
        <div class="step-dot" id="dot-2">2</div>
        <div class="step-label">Diet</div>
      </div>
      <div class="step-line" id="line-2"></div>
      <div style="display:flex;flex-direction:column;align-items:center">
        <div class="step-dot" id="dot-3">3</div>
        <div class="step-label">Calories</div>
      </div>
      <div class="step-line" id="line-3"></div>
      <div style="display:flex;flex-direction:column;align-items:center">
        <div class="step-dot" id="dot-4">4</div>
        <div class="step-label">Ingredients</div>
      </div>
      <div class="step-line" id="line-4"></div>
      <div style="display:flex;flex-direction:column;align-items:center">
        <div class="step-dot" id="dot-5">5</div>
        <div class="step-label">Review</div>
      </div>
    </div>
  </div>

  <!-- Generator Form -->
  <div class="card" id="generatorCard">
    <form id="generatorForm" method="POST" action="api/generate-plan.php">

      <!-- ── STEP 1: Goal ────────────────────────────────── -->
      <div class="form-step active" id="step-1">
        <h2 style="font-size:1.3rem;margin-bottom:.4rem">What's your main goal?</h2>
        <p style="font-size:.9rem;margin-bottom:1.5rem">This helps us tailor calorie counts and meal compositions.</p>
        <div class="option-grid">
          <?php
          $goals = [
            ['val'=>'weight_loss', 'icon'=>'⬇️', 'title'=>'Lose Weight',   'desc'=>'Calorie deficit with high protein'],
            ['val'=>'weight_gain', 'icon'=>'⬆️', 'title'=>'Gain Weight',   'desc'=>'Calorie surplus for muscle growth'],
            ['val'=>'maintain',    'icon'=>'⚖️', 'title'=>'Maintain',      'desc'=>'Balanced nutrition to stay on track'],
            ['val'=>'muscle_build','icon'=>'💪', 'title'=>'Build Muscle',   'desc'=>'High protein, strength-focused meals'],
          ];
          foreach ($goals as $g): ?>
          <label class="option-card" onclick="selectOption(this,'goal')">
            <input type="radio" name="goal" value="<?= $g['val'] ?>"
                   <?= ($profile['fitness_goal'] ?? '') === $g['val'] ? 'checked' : '' ?>>
            <div class="option-check">✓</div>
            <div class="option-icon"><?= $g['icon'] ?></div>
            <div class="option-title"><?= $g['title'] ?></div>
            <div class="option-desc"><?= $g['desc'] ?></div>
          </label>
          <?php endforeach; ?>
        </div>
        <div class="form-nav">
          <div></div>
          <button type="button" class="btn btn-primary" onclick="nextStep(1)">Next: Diet Type →</button>
        </div>
      </div>

      <!-- ── STEP 2: Diet Type ───────────────────────────── -->
      <div class="form-step" id="step-2">
        <h2 style="font-size:1.3rem;margin-bottom:.4rem">Choose your diet type</h2>
        <p style="font-size:.9rem;margin-bottom:1.5rem">We'll respect your dietary requirements in every meal.</p>
        <div class="option-grid">
          <?php
          $diets = [
            ['val'=>'normal',      'icon'=>'🍽️', 'title'=>'Normal',       'desc'=>'No restrictions'],
            ['val'=>'halal',       'icon'=>'🕌', 'title'=>'Halal',         'desc'=>'Halal-certified ingredients'],
            ['val'=>'vegetarian',  'icon'=>'🥦', 'title'=>'Vegetarian',    'desc'=>'No meat or fish'],
            ['val'=>'vegan',       'icon'=>'🌱', 'title'=>'Vegan',         'desc'=>'100% plant-based'],
            ['val'=>'keto',        'icon'=>'🥑', 'title'=>'Keto',          'desc'=>'Low carb, high fat'],
            ['val'=>'paleo',       'icon'=>'🥩', 'title'=>'Paleo',         'desc'=>'Whole foods only'],
          ];
          foreach ($diets as $d): ?>
          <label class="option-card" onclick="selectOption(this,'diet_type')">
            <input type="radio" name="diet_type" value="<?= $d['val'] ?>"
                   <?= ($profile['diet_type'] ?? '') === $d['val'] ? 'checked' : '' ?>>
            <div class="option-check">✓</div>
            <div class="option-icon"><?= $d['icon'] ?></div>
            <div class="option-title"><?= $d['title'] ?></div>
            <div class="option-desc"><?= $d['desc'] ?></div>
          </label>
          <?php endforeach; ?>
        </div>

        <!-- Allergies -->
        <div class="form-group" style="margin-top:1.5rem">
          <label class="form-label">Any allergies or foods to avoid?</label>
          <input class="form-control" type="text" name="allergies" placeholder="e.g. nuts, shellfish, dairy"
                 value="<?= sanitize($profile['allergies'] ?? '') ?>">
          <div class="form-hint">Separate with commas. Leave blank if none.</div>
        </div>

        <div class="form-nav">
          <button type="button" class="btn btn-ghost" onclick="prevStep(2)">← Back</button>
          <button type="button" class="btn btn-primary" onclick="nextStep(2)">Next: Calories →</button>
        </div>
      </div>

      <!-- ── STEP 3: Calories & Duration ────────────────── -->
      <div class="form-step" id="step-3">
        <h2 style="font-size:1.3rem;margin-bottom:.4rem">Calorie target & plan duration</h2>
        <p style="font-size:.9rem;margin-bottom:1.5rem">Drag the slider or let our AI choose the perfect amount for your goal.</p>

        <div class="form-group">
          <label class="form-label">Daily Calorie Target</label>
          <input type="range" class="calorie-slider" id="calorieSlider" name="target_calories"
                 min="1000" max="4000" step="50"
                 value="<?= $profile['daily_calories'] ?? 2000 ?>"
                 oninput="updateCalorie(this.value)">
          <div class="calorie-display">
            <div>
              <div class="calorie-num" id="calorieNum"><?= $profile['daily_calories'] ?? 2000 ?></div>
              <div style="font-size:.78rem;color:var(--text-soft)">calories per day</div>
            </div>
            <div style="text-align:right">
              <div style="font-size:.82rem;color:var(--text-mid)" id="calorieHint">Balanced</div>
              <div style="font-size:.72rem;color:var(--text-soft)">estimated intake type</div>
            </div>
          </div>
        </div>

        <div class="form-group" style="margin-top:1.5rem">
          <label class="form-label">Plan Duration</label>
          <div class="duration-toggle" id="durationToggle">
            <button type="button" class="dur-btn active" data-val="1"  onclick="setDuration(this)">1 Day</button>
            <button type="button" class="dur-btn"        data-val="3"  onclick="setDuration(this)">3 Days</button>
            <button type="button" class="dur-btn"        data-val="7"  onclick="setDuration(this)">1 Week</button>
          </div>
          <input type="hidden" name="duration" id="durationInput" value="1">
        </div>

        <div class="form-group" style="margin-top:1rem">
          <label class="form-label">Meals per day</label>
          <div class="duration-toggle">
            <button type="button" class="dur-btn active" data-val="3" onclick="setMeals(this)">3 meals</button>
            <button type="button" class="dur-btn"        data-val="4" onclick="setMeals(this)">3 + snack</button>
            <button type="button" class="dur-btn"        data-val="5" onclick="setMeals(this)">3 + 2 snacks</button>
          </div>
          <input type="hidden" name="meals_per_day" id="mealsInput" value="3">
        </div>

        <div class="form-nav">
          <button type="button" class="btn btn-ghost" onclick="prevStep(3)">← Back</button>
          <button type="button" class="btn btn-primary" onclick="nextStep(3)">Next: Ingredients →</button>
        </div>
      </div>

      <!-- ── STEP 4: Ingredients ────────────────────────── -->
      <div class="form-step" id="step-4">
        <h2 style="font-size:1.3rem;margin-bottom:.4rem">What ingredients do you have?</h2>
        <p style="font-size:.9rem;margin-bottom:1.5rem">Optional — our AI will build your plan around these. Leave blank for full suggestions.</p>

        <div class="form-group">
          <label class="form-label">Available Ingredients</label>
          <div class="tag-input-wrap" id="tagWrap" onclick="document.getElementById('tagInput').focus()">
            <input id="tagInput" class="tag-input" type="text" placeholder="Type ingredient and press Enter…">
          </div>
          <input type="hidden" name="ingredients" id="ingredientsHidden">
          <div class="form-hint" style="margin-top:.5rem">Press <kbd style="background:var(--border);padding:.1rem .35rem;border-radius:4px;font-size:.75rem">Enter</kbd> or comma after each ingredient</div>
        </div>

        <div style="margin-top:.75rem">
          <div style="font-size:.8rem;color:var(--text-soft);margin-bottom:.5rem">Quick add:</div>
          <div class="tag-suggestions" id="tagSuggestions">
            <?php
            $common = ['Chicken','Rice','Eggs','Tofu','Salmon','Broccoli','Spinach','Oats','Sweet potato','Avocado','Tomatoes','Onion','Garlic','Olive oil','Lentils'];
            foreach ($common as $c): ?>
            <button type="button" class="tag-suggest-btn" onclick="addTag('<?= $c ?>')"><?= $c ?></button>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="form-group" style="margin-top:1.5rem">
          <label class="form-label">Additional notes for the AI <span style="color:var(--text-soft);font-weight:400">(optional)</span></label>
          <textarea class="form-control" name="notes" rows="3"
                    placeholder="e.g. I prefer simple 20-minute meals, I dislike spicy food, budget-friendly please…"></textarea>
        </div>

        <div class="form-nav">
          <button type="button" class="btn btn-ghost" onclick="prevStep(4)">← Back</button>
          <button type="button" class="btn btn-primary" onclick="nextStep(4)">Review & Generate →</button>
        </div>
      </div>

      <!-- ── STEP 5: Review ─────────────────────────────── -->
      <div class="form-step" id="step-5">
        <h2 style="font-size:1.3rem;margin-bottom:.4rem">Review your preferences</h2>
        <p style="font-size:.9rem;margin-bottom:1.5rem">Everything look good? Hit generate and our AI will build your plan!</p>

        <div class="review-grid" id="reviewGrid">
          <div class="review-item">
            <div class="review-label">Goal</div>
            <div class="review-value" id="rev-goal">—</div>
          </div>
          <div class="review-item">
            <div class="review-label">Diet Type</div>
            <div class="review-value" id="rev-diet">—</div>
          </div>
          <div class="review-item">
            <div class="review-label">Calorie Target</div>
            <div class="review-value" id="rev-cal">—</div>
          </div>
          <div class="review-item">
            <div class="review-label">Duration</div>
            <div class="review-value" id="rev-dur">—</div>
          </div>
          <div class="review-item" style="grid-column:1/-1">
            <div class="review-label">Ingredients</div>
            <div class="review-value" id="rev-ing">None specified (AI will suggest)</div>
          </div>
        </div>

        <!-- AI Loading State (hidden initially) -->
        <div class="ai-loading" id="aiLoading">
          <div class="ai-loading-spinner"></div>
          <h3 style="font-size:1.1rem;margin-bottom:.5rem">AI is crafting your plan…</h3>
          <p style="font-size:.88rem;color:var(--text-soft)" id="aiStatus">Analysing your goals and preferences…</p>
          <div class="ai-dots">
            <div class="ai-dot"></div>
            <div class="ai-dot"></div>
            <div class="ai-dot"></div>
          </div>
        </div>

        <div class="form-nav" id="reviewNav">
          <button type="button" class="btn btn-ghost" onclick="prevStep(5)">← Back</button>
          <button type="submit" class="btn btn-primary btn-lg" id="generateBtn">
            ✨ Generate My Meal Plan
          </button>
        </div>
      </div>

    </form>
  </div>

</div>

<script>
let currentStep = 1;
const totalSteps = 5;
const tags = [];

// ── Step navigation ──────────────────────────────────────────
function nextStep(from) {
  if (from === 1 && !document.querySelector('input[name="goal"]:checked')) {
    alert('Please select a goal to continue.'); return;
  }
  if (from === 2 && !document.querySelector('input[name="diet_type"]:checked')) {
    alert('Please select a diet type to continue.'); return;
  }
  if (from === 4) updateReview();
  goToStep(from + 1);
}
function prevStep(from) { goToStep(from - 1); }

function goToStep(n) {
  document.getElementById('step-' + currentStep).classList.remove('active');
  currentStep = n;
  document.getElementById('step-' + n).classList.add('active');
  updateDots();
  window.scrollTo({top: 0, behavior:'smooth'});
}

function updateDots() {
  for (let i = 1; i <= totalSteps; i++) {
    const dot  = document.getElementById('dot-' + i);
    const line = document.getElementById('line-' + i);
    dot.classList.remove('active','complete');
    if (i < currentStep)  { dot.classList.add('complete'); dot.innerHTML='✓'; }
    if (i === currentStep){ dot.classList.add('active'); dot.innerHTML=i; }
    if (i > currentStep)  { dot.innerHTML = i; }
    if (line) line.classList.toggle('complete', i < currentStep);
  }
}

// ── Option cards ────────────────────────────────────────────
function selectOption(card, name) {
  document.querySelectorAll(`.option-card input[name="${name}"]`).forEach(inp => {
    inp.closest('.option-card').classList.remove('selected');
  });
  card.classList.add('selected');
  card.querySelector('input').checked = true;
}
// Pre-select if profile has values
document.querySelectorAll('.option-card').forEach(card => {
  if (card.querySelector('input:checked')) card.classList.add('selected');
});

// ── Calorie slider ───────────────────────────────────────────
function updateCalorie(val) {
  document.getElementById('calorieNum').textContent = Number(val).toLocaleString();
  const hints = { 1000:'Very low calorie', 1500:'Low calorie', 2000:'Balanced', 2500:'Active lifestyle', 3000:'High energy', 3500:'Performance', 4000:'Athlete level' };
  let hint = 'Balanced';
  for (const [k,v] of Object.entries(hints)) { if (val >= k) hint = v; }
  document.getElementById('calorieHint').textContent = hint;
}

// ── Duration & Meals toggles ─────────────────────────────────
function setDuration(btn) {
  document.querySelectorAll('#durationToggle .dur-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('durationInput').value = btn.dataset.val;
}
function setMeals(btn) {
  btn.closest('.duration-toggle').querySelectorAll('.dur-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('mealsInput').value = btn.dataset.val;
}

// ── Tag ingredient input ─────────────────────────────────────
function addTag(val) {
  val = val.trim();
  if (!val || tags.includes(val.toLowerCase())) return;
  tags.push(val.toLowerCase());
  const wrap = document.getElementById('tagWrap');
  const tag  = document.createElement('div');
  tag.className = 'tag';
  tag.innerHTML = `${val} <button class="tag-remove" onclick="removeTag(this,'${val.toLowerCase()}')" type="button">×</button>`;
  wrap.insertBefore(tag, document.getElementById('tagInput'));
  document.getElementById('tagInput').value = '';
  updateTagsHidden();
}
function removeTag(btn, val) {
  const idx = tags.indexOf(val);
  if (idx > -1) tags.splice(idx, 1);
  btn.closest('.tag').remove();
  updateTagsHidden();
}
function updateTagsHidden() {
  document.getElementById('ingredientsHidden').value = tags.join(', ');
}

document.getElementById('tagInput').addEventListener('keydown', e => {
  if (e.key === 'Enter' || e.key === ',') {
    e.preventDefault();
    addTag(e.target.value.replace(',',''));
  }
});

// ── Review panel ────────────────────────────────────────────
function updateReview() {
  const goalEl = document.querySelector('input[name="goal"]:checked');
  const dietEl = document.querySelector('input[name="diet_type"]:checked');
  const cal    = document.getElementById('calorieSlider').value;
  const dur    = document.getElementById('durationInput').value;
  const ingStr = tags.length ? tags.join(', ') : 'None specified (AI will suggest)';

  const goalLabels = {weight_loss:'⬇️ Lose Weight',weight_gain:'⬆️ Gain Weight',maintain:'⚖️ Maintain',muscle_build:'💪 Build Muscle'};
  const dietLabels = {normal:'🍽️ Normal',halal:'🕌 Halal',vegetarian:'🥦 Vegetarian',vegan:'🌱 Vegan',keto:'🥑 Keto',paleo:'🥩 Paleo'};
  const durLabels  = {1:'1 Day',3:'3 Days',7:'1 Week'};

  document.getElementById('rev-goal').textContent = goalEl ? (goalLabels[goalEl.value] || goalEl.value) : '—';
  document.getElementById('rev-diet').textContent = dietEl ? (dietLabels[dietEl.value] || dietEl.value) : '—';
  document.getElementById('rev-cal').textContent  = Number(cal).toLocaleString() + ' kcal/day';
  document.getElementById('rev-dur').textContent  = durLabels[dur] || dur + ' days';
  document.getElementById('rev-ing').textContent  = ingStr;
}

// ── Form submit → show loading ───────────────────────────────
document.getElementById('generatorForm').addEventListener('submit', function(e) {
  e.preventDefault();

  document.getElementById('reviewNav').style.display = 'none';
  document.getElementById('reviewGrid').style.display = 'none';
  document.getElementById('aiLoading').style.display  = 'block';

  const statuses = [
    'Analysing your goals and preferences…',
    'Calculating optimal calorie distribution…',
    'Crafting breakfast, lunch and dinner ideas…',
    'Adding snacks and recipe steps…',
    'Compiling your grocery list…',
    'Almost done — polishing your plan…'
  ];
  let si = 0;
  const statusEl = document.getElementById('aiStatus');
  const interval = setInterval(() => {
    si = (si + 1) % statuses.length;
    statusEl.textContent = statuses[si];
  }, 2200);

  const formData = new FormData(this);

  fetch('api/generate-plan.php', {
  method: 'POST',
  body: formData
})
.then(async r => {
  const text = await r.text();
  console.log("RAW RESPONSE:", text);

  try {
    return JSON.parse(text);
  } catch (e) {
    console.error("JSON parse failed:", text);
    throw new Error("Invalid JSON response from server");
  }
})
.then(data => {
  clearInterval(interval);

  if (data.success) {
    window.location.href = 'results.php?plan=' + data.plan_id;
  } else {
    alert('Error: ' + (data.error || 'Something went wrong.'));
  }
})
.catch((err) => {
  clearInterval(interval);
  console.error(err);

  alert('Server error (not network). Check console.');

  document.getElementById('reviewNav').style.display  = 'flex';
  document.getElementById('reviewGrid').style.display = 'grid';
  document.getElementById('aiLoading').style.display  = 'none';
});
});
</script>

<?php require 'includes/footer.php'; ?>