<?php
require_once 'includes/config.php';
requireLogin();

$db     = getDB();
$userId = currentUserId();

// ── Handle POST ───────────────────────────────────────────────
$errors  = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $section = $_POST['section'] ?? '';

    // ── Personal Info ─────────────────────────────────────────
    if ($section === 'personal') {
        $name   = trim($_POST['name'] ?? '');
        $email  = trim($_POST['email'] ?? '');

        if (!$name || !$email) { $errors[] = 'Name and email are required.'; }
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Invalid email.'; }
        else {
            // Check email unique
            $stmt = $db->prepare("SELECT id FROM users WHERE email=? AND id!=?");
            $stmt->execute([$email, $userId]);
            if ($stmt->fetch()) { $errors[] = 'This email is already in use.'; }
            else {
                $db->prepare("UPDATE users SET name=?, email=?, updated_at=NOW() WHERE id=?")
                   ->execute([$name, $email, $userId]);
                $_SESSION['user_name'] = $name;
                $success = 'Personal info updated!';
            }
        }
    }

    // ── Change Password ───────────────────────────────────────
    if ($section === 'password') {
        $current = $_POST['current_password'] ?? '';
        $newPw   = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $stmt = $db->prepare("SELECT password_hash FROM users WHERE id=?");
        $stmt->execute([$userId]);
        $hash = $stmt->fetchColumn();

        if (!password_verify($current, $hash)) { $errors[] = 'Current password is incorrect.'; }
        elseif (strlen($newPw) < 8)            { $errors[] = 'New password must be at least 8 characters.'; }
        elseif ($newPw !== $confirm)            { $errors[] = 'Passwords do not match.'; }
        else {
            $db->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([password_hash($newPw,PASSWORD_BCRYPT,['cost'=>12]), $userId]);
            $success = 'Password changed successfully!';
        }
    }

    // ── Health Profile ────────────────────────────────────────
    if ($section === 'health') {
        $age           = (int)($_POST['age'] ?? 0);
        $gender        = $_POST['gender'] ?? '';
        $heightCm      = (float)($_POST['height_cm'] ?? 0);
        $weightKg      = (float)($_POST['weight_kg'] ?? 0);
        $goalWeightKg  = (float)($_POST['goal_weight_kg'] ?? 0);
        $activityLevel = $_POST['activity_level'] ?? 'moderate';
        $fitnessGoal   = $_POST['fitness_goal']   ?? 'maintain';
        $dietType      = $_POST['diet_type']       ?? 'normal';
        $dailyCalories = (int)($_POST['daily_calories'] ?? 0);
        $allergies     = trim($_POST['allergies']    ?? '');
        $excludedFoods = trim($_POST['excluded_foods'] ?? '');

        $db->prepare("
            INSERT INTO user_profiles
                (user_id,age,gender,height_cm,weight_kg,goal_weight_kg,activity_level,fitness_goal,diet_type,daily_calories,allergies,excluded_foods)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
                age=VALUES(age), gender=VALUES(gender), height_cm=VALUES(height_cm),
                weight_kg=VALUES(weight_kg), goal_weight_kg=VALUES(goal_weight_kg),
                activity_level=VALUES(activity_level), fitness_goal=VALUES(fitness_goal),
                diet_type=VALUES(diet_type), daily_calories=VALUES(daily_calories),
                allergies=VALUES(allergies), excluded_foods=VALUES(excluded_foods),
                updated_at=NOW()
        ")->execute([$userId,$age,$gender,$heightCm,$weightKg,$goalWeightKg,$activityLevel,$fitnessGoal,$dietType,$dailyCalories,$allergies,$excludedFoods]);
        $success = 'Health profile saved!';
    }

    if ($success) flashSet('success', $success);
    if ($errors)  flashSet('error',   implode(' ', $errors));
    header("Location: profile.php#" . ($section ?? '')); exit;
}

// ── Load user data ────────────────────────────────────────────
$stmt = $db->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$stmt = $db->prepare("SELECT * FROM user_profiles WHERE user_id=?");
$stmt->execute([$userId]);
$profile = $stmt->fetch() ?: [];

// ── BMI calculation ───────────────────────────────────────────
$bmi = null; $bmiLabel = ''; $bmiColor = 'var(--sage-dark)';
if (!empty($profile['height_cm']) && !empty($profile['weight_kg'])) {
    $hm  = $profile['height_cm'] / 100;
    $bmi = round($profile['weight_kg'] / ($hm * $hm), 1);
    if ($bmi < 18.5)      { $bmiLabel = 'Underweight'; $bmiColor = 'var(--sky)'; }
    elseif ($bmi < 25)    { $bmiLabel = 'Normal';      $bmiColor = 'var(--sage-dark)'; }
    elseif ($bmi < 30)    { $bmiLabel = 'Overweight';  $bmiColor = 'var(--peach)'; }
    else                  { $bmiLabel = 'Obese';        $bmiColor = '#e74c3c'; }
}

// ── TDEE estimate ─────────────────────────────────────────────
$tdee = null;
if (!empty($profile['weight_kg']) && !empty($profile['height_cm']) && !empty($profile['age'])) {
    $bmr = $profile['gender'] === 'female'
        ? 655 + (9.563 * $profile['weight_kg']) + (1.850 * $profile['height_cm']) - (4.676 * $profile['age'])
        : 88.362 + (13.397 * $profile['weight_kg']) + (4.799 * $profile['height_cm']) - (5.677 * $profile['age']);
    $factors = ['sedentary'=>1.2,'light'=>1.375,'moderate'=>1.55,'active'=>1.725,'very_active'=>1.9];
    $tdee = round($bmr * ($factors[$profile['activity_level'] ?? 'moderate'] ?? 1.55));
}

// ── Stats ─────────────────────────────────────────────────────
$stmt = $db->prepare("SELECT COUNT(*) FROM meal_plans WHERE user_id=?");
$stmt->execute([$userId]);
$planCount = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM meal_plans WHERE user_id=? AND is_saved=1");
$stmt->execute([$userId]);
$savedCount = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT created_at FROM users WHERE id=?");
$stmt->execute([$userId]);
$joinDate = $stmt->fetchColumn();

$pageTitle  = 'Profile & Preferences';
$activePage = 'profile';
require 'includes/header.php';
?>

<style>
/* ── Profile Layout ─────────────────────────────────────────── */
.profile-layout { display: grid; grid-template-columns: 280px 1fr; gap: 1.5rem; align-items: start; }

/* Avatar card */
.avatar-card { text-align: center; }
.avatar-circle {
  width: 90px; height: 90px; border-radius: 50%;
  background: linear-gradient(135deg, var(--sage-light), var(--lav-light));
  display: flex; align-items: center; justify-content: center;
  font-size: 2.2rem; margin: 0 auto 1rem; border: 3px solid var(--white);
  box-shadow: var(--shadow-md);
}
.profile-name { font-family: var(--font-display); font-size: 1.2rem; font-weight: 700; }
.profile-email { font-size: .82rem; color: var(--text-soft); margin-top: .2rem; }
.profile-join  { font-size: .78rem; color: var(--text-soft); margin-top: .5rem; }
.profile-stats { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; margin-top: 1.25rem; }
.profile-stat  { background: var(--cream); border-radius: var(--radius-sm); padding: .85rem; text-align: center; }
.profile-stat-val { font-family: var(--font-display); font-size: 1.3rem; font-weight: 700; }
.profile-stat-lbl { font-size: .7rem; color: var(--text-soft); text-transform: uppercase; letter-spacing: .04em; }

/* BMI card */
.bmi-card { margin-top: 1rem; }
.bmi-gauge { position: relative; margin: .75rem 0; }
.bmi-track { height: 8px; border-radius: 4px; background: linear-gradient(to right, var(--sky), var(--sage-dark), var(--peach), #e74c3c); }
.bmi-marker {
  position: absolute; top: -4px; width: 16px; height: 16px; border-radius: 50%;
  background: var(--text-dark); border: 2px solid var(--white); box-shadow: var(--shadow-sm);
  transform: translateX(-50%); transition: left .5s ease;
}
.bmi-val { font-family: var(--font-display); font-size: 1.6rem; font-weight: 700; }

/* Nav tabs for sections */
.section-nav { display: flex; gap: .35rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
.section-nav-btn {
  padding: .5rem 1.1rem; border-radius: var(--radius-sm);
  border: 1.5px solid var(--border); background: var(--white);
  font-size: .85rem; font-weight: 600; color: var(--text-soft);
  cursor: pointer; transition: var(--transition);
}
.section-nav-btn:hover  { border-color: var(--sage); color: var(--sage-dark); }
.section-nav-btn.active { border-color: var(--sage-dark); background: var(--sage-dark); color: var(--white); }

.profile-section { display: none; animation: fadeUp .3s ease; }
.profile-section.active { display: block; }

/* Activity level cards */
.activity-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px,1fr)); gap: .75rem; margin-bottom: 1rem; }
.activity-card {
  border: 1.5px solid var(--border); border-radius: var(--radius-sm);
  padding: 1rem .75rem; text-align: center; cursor: pointer; transition: var(--transition);
  background: var(--white);
}
.activity-card:hover   { border-color: var(--sage); }
.activity-card.sel     { border-color: var(--sage-dark); background: var(--sage-light); }
.activity-card input   { display: none; }
.activity-emoji { font-size: 1.5rem; margin-bottom: .35rem; }
.activity-title { font-size: .8rem; font-weight: 700; }
.activity-desc  { font-size: .7rem; color: var(--text-soft); margin-top: .15rem; }

/* Diet type grid */
.diet-grid { display: grid; grid-template-columns: repeat(auto-fill,minmax(120px,1fr)); gap: .75rem; margin-bottom: 1rem; }
.diet-card {
  border: 1.5px solid var(--border); border-radius: var(--radius-sm);
  padding: .85rem .5rem; text-align: center; cursor: pointer; transition: var(--transition);
  background: var(--white);
}
.diet-card:hover { border-color: var(--sage); }
.diet-card.sel   { border-color: var(--sage-dark); background: var(--sage-light); }
.diet-card input { display: none; }

/* Two-col form grid */
.form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }

/* TDEE banner */
.tdee-banner {
  background: linear-gradient(135deg, var(--sage-light), var(--lav-light));
  border-radius: var(--radius-md); padding: 1.25rem 1.5rem;
  display: flex; align-items: center; gap: 1.25rem; margin-bottom: 1.5rem;
}
.tdee-num { font-family: var(--font-display); font-size: 2rem; font-weight: 700; color: var(--sage-dark); }
.tdee-lbl { font-size: .82rem; color: var(--text-mid); }

@media (max-width:900px) {
  .profile-layout { grid-template-columns: 1fr; }
  .form-grid-2    { grid-template-columns: 1fr; }
}
</style>

<!-- ── Page Header ─────────────────────────────────────────── -->
<div class="section-header" style="margin-bottom:1.5rem">
  <h1 style="font-size:1.6rem">⚙️ Profile & Preferences</h1>
</div>

<div class="profile-layout">

  <!-- ── Left: Avatar + Stats ──────────────────────────────── -->
  <div>
    <div class="card avatar-card">
      <div class="avatar-circle">
        <?= mb_substr($user['name'] ?? '?', 0, 1) ?>
      </div>
      <div class="profile-name"><?= sanitize($user['name']) ?></div>
      <div class="profile-email"><?= sanitize($user['email']) ?></div>
      <div class="profile-join">Member since <?= date('F Y', strtotime($joinDate)) ?></div>
      <div class="profile-stats">
        <div class="profile-stat">
          <div class="profile-stat-val"><?= $planCount ?></div>
          <div class="profile-stat-lbl">Plans</div>
        </div>
        <div class="profile-stat">
          <div class="profile-stat-val"><?= $savedCount ?></div>
          <div class="profile-stat-lbl">Saved</div>
        </div>
      </div>
    </div>

    <?php if ($bmi): ?>
    <div class="card bmi-card" style="margin-top:1rem;padding:1.25rem">
      <div style="font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text-soft);margin-bottom:.5rem">BMI</div>
      <div class="bmi-val" style="color:<?= $bmiColor ?>"><?= $bmi ?></div>
      <div style="font-size:.82rem;color:var(--text-mid);margin-bottom:.75rem"><?= $bmiLabel ?></div>
      <div class="bmi-gauge">
        <div class="bmi-track"></div>
        <?php $pct = min(100, max(0, ($bmi - 15) / (40-15) * 100)); ?>
        <div class="bmi-marker" style="left:<?= $pct ?>%"></div>
      </div>
      <div style="display:flex;justify-content:space-between;font-size:.68rem;color:var(--text-soft);margin-top:.3rem">
        <span>15</span><span>18.5</span><span>25</span><span>30</span><span>40+</span>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($tdee): ?>
    <div class="card" style="margin-top:1rem;padding:1.25rem">
      <div style="font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text-soft);margin-bottom:.5rem">Estimated TDEE</div>
      <div style="font-family:var(--font-display);font-size:1.6rem;font-weight:700;color:var(--sage-dark)"><?= number_format($tdee) ?></div>
      <div style="font-size:.8rem;color:var(--text-soft)">kcal/day maintenance</div>
      <hr class="divider" style="margin:.75rem 0">
      <div style="font-size:.78rem;color:var(--text-mid)">
        🎯 Lose weight: <strong><?= number_format($tdee - 500) ?></strong> kcal<br>
        ⚖️ Maintain: <strong><?= number_format($tdee) ?></strong> kcal<br>
        📈 Gain weight: <strong><?= number_format($tdee + 300) ?></strong> kcal
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- ── Right: Sectioned Forms ──────────────────────────── -->
  <div>
    <!-- Section Tabs -->
    <div class="section-nav">
      <button class="section-nav-btn active" onclick="showSection('personal',this)">👤 Personal</button>
      <button class="section-nav-btn"        onclick="showSection('health',this)">🏃 Health</button>
      <button class="section-nav-btn"        onclick="showSection('diet',this)">🥗 Diet</button>
      <button class="section-nav-btn"        onclick="showSection('security',this)">🔒 Security</button>
    </div>

    <!-- ── PERSONAL ──────────────────────────────────────── -->
    <div class="card profile-section active" id="sec-personal">
      <h3 style="margin-bottom:1.5rem;font-size:1.1rem">👤 Personal Information</h3>
      <form method="POST">
        <input type="hidden" name="section" value="personal">
        <div class="form-grid-2">
          <div class="form-group">
            <label class="form-label">Full Name</label>
            <input class="form-control" type="text" name="name"
                   value="<?= sanitize($user['name'] ?? '') ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Email Address</label>
            <input class="form-control" type="email" name="email"
                   value="<?= sanitize($user['email'] ?? '') ?>" required>
          </div>
        </div>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </form>
    </div>

    <!-- ── HEALTH ────────────────────────────────────────── -->
    <div class="card profile-section" id="sec-health">
      <h3 style="margin-bottom:1.25rem;font-size:1.1rem">🏃 Health & Body Stats</h3>
      <form method="POST" onchange="recalcTDEE()">
        <input type="hidden" name="section" value="health">
        <div class="form-grid-2">
          <div class="form-group">
            <label class="form-label">Age</label>
            <input class="form-control" type="number" name="age" min="10" max="120"
                   value="<?= (int)($profile['age'] ?? '') ?>" placeholder="e.g. 28">
          </div>
          <div class="form-group">
            <label class="form-label">Gender</label>
            <select class="form-control" name="gender">
              <option value="">— Select —</option>
              <?php foreach(['male'=>'Male','female'=>'Female','other'=>'Other'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= ($profile['gender'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Height (cm)</label>
            <input class="form-control" type="number" name="height_cm" min="100" max="250" step="0.1"
                   value="<?= $profile['height_cm'] ?? '' ?>" placeholder="e.g. 170">
          </div>
          <div class="form-group">
            <label class="form-label">Current Weight (kg)</label>
            <input class="form-control" type="number" name="weight_kg" min="20" max="300" step="0.1"
                   value="<?= $profile['weight_kg'] ?? '' ?>" placeholder="e.g. 65">
          </div>
          <div class="form-group">
            <label class="form-label">Goal Weight (kg) <span style="color:var(--text-soft);font-weight:400">optional</span></label>
            <input class="form-control" type="number" name="goal_weight_kg" min="20" max="300" step="0.1"
                   value="<?= $profile['goal_weight_kg'] ?? '' ?>" placeholder="e.g. 60">
          </div>
          <div class="form-group">
            <label class="form-label">Fitness Goal</label>
            <select class="form-control" name="fitness_goal">
              <?php foreach(['weight_loss'=>'⬇️ Lose Weight','weight_gain'=>'⬆️ Gain Weight','maintain'=>'⚖️ Maintain','muscle_build'=>'💪 Build Muscle'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= ($profile['fitness_goal'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Activity Level</label>
          <div class="activity-grid">
            <?php
            $activities = [
              ['val'=>'sedentary', 'emoji'=>'🪑','title'=>'Sedentary','desc'=>'Desk job, little exercise'],
              ['val'=>'light',     'emoji'=>'🚶','title'=>'Light','desc'=>'1–3 days/week exercise'],
              ['val'=>'moderate',  'emoji'=>'🏃','title'=>'Moderate','desc'=>'3–5 days/week exercise'],
              ['val'=>'active',    'emoji'=>'🏋️','title'=>'Active','desc'=>'6–7 days/week exercise'],
              ['val'=>'very_active','emoji'=>'⚡','title'=>'Very Active','desc'=>'Athlete / physical job'],
            ];
            foreach ($activities as $a):
              $sel = ($profile['activity_level'] ?? 'moderate') === $a['val'];
            ?>
            <label class="activity-card <?= $sel ? 'sel' : '' ?>" onclick="this.classList.toggle('sel',true);document.querySelectorAll('.activity-card').forEach(c=>c!==this&&c.classList.remove('sel'))">
              <input type="radio" name="activity_level" value="<?= $a['val'] ?>" <?= $sel ? 'checked' : '' ?>>
              <div class="activity-emoji"><?= $a['emoji'] ?></div>
              <div class="activity-title"><?= $a['title'] ?></div>
              <div class="activity-desc"><?= $a['desc'] ?></div>
            </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Daily Calorie Target <span style="color:var(--text-soft);font-weight:400">(leave 0 to use TDEE)</span></label>
          <input class="form-control" type="number" name="daily_calories" min="0" max="6000"
                 value="<?= (int)($profile['daily_calories'] ?? 0) ?>" placeholder="e.g. 1800">
        </div>

        <button type="submit" class="btn btn-primary">Save Health Profile</button>
      </form>
    </div>

    <!-- ── DIET ──────────────────────────────────────────── -->
    <div class="card profile-section" id="sec-diet">
      <h3 style="margin-bottom:1.25rem;font-size:1.1rem">🥗 Diet & Food Preferences</h3>
      <form method="POST">
        <input type="hidden" name="section" value="health">
        <!-- Hidden fields to carry over health data -->
        <input type="hidden" name="age"            value="<?= (int)($profile['age'] ?? 0) ?>">
        <input type="hidden" name="gender"         value="<?= sanitize($profile['gender'] ?? '') ?>">
        <input type="hidden" name="height_cm"      value="<?= (float)($profile['height_cm'] ?? 0) ?>">
        <input type="hidden" name="weight_kg"      value="<?= (float)($profile['weight_kg'] ?? 0) ?>">
        <input type="hidden" name="goal_weight_kg" value="<?= (float)($profile['goal_weight_kg'] ?? 0) ?>">
        <input type="hidden" name="activity_level" value="<?= sanitize($profile['activity_level'] ?? 'moderate') ?>">
        <input type="hidden" name="fitness_goal"   value="<?= sanitize($profile['fitness_goal'] ?? 'maintain') ?>">
        <input type="hidden" name="daily_calories" value="<?= (int)($profile['daily_calories'] ?? 0) ?>">

        <div class="form-group">
          <label class="form-label">Diet Type</label>
          <div class="diet-grid">
            <?php
            $diets = ['normal'=>['🍽️','Normal'],'halal'=>['🕌','Halal'],'vegetarian'=>['🥦','Vegetarian'],'vegan'=>['🌱','Vegan'],'keto'=>['🥑','Keto'],'paleo'=>['🥩','Paleo']];
            foreach ($diets as $v=>[$emoji,$label]):
              $sel = ($profile['diet_type'] ?? 'normal') === $v;
            ?>
            <label class="diet-card <?= $sel ? 'sel' : '' ?>"
                   onclick="document.querySelectorAll('.diet-card').forEach(c=>c.classList.remove('sel'));this.classList.add('sel')">
              <input type="radio" name="diet_type" value="<?= $v ?>" <?= $sel ? 'checked' : '' ?>>
              <div style="font-size:1.5rem;margin-bottom:.35rem"><?= $emoji ?></div>
              <div style="font-size:.82rem;font-weight:600"><?= $label ?></div>
            </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Allergies <span style="color:var(--text-soft);font-weight:400">optional</span></label>
          <input class="form-control" type="text" name="allergies"
                 value="<?= sanitize($profile['allergies'] ?? '') ?>"
                 placeholder="e.g. nuts, shellfish, dairy, gluten">
          <div class="form-hint">Separate with commas. The AI will strictly avoid these.</div>
        </div>

        <div class="form-group">
          <label class="form-label">Foods to Avoid <span style="color:var(--text-soft);font-weight:400">optional</span></label>
          <textarea class="form-control" name="excluded_foods" rows="2"
                    placeholder="e.g. pork, mushrooms, raw onion"><?= sanitize($profile['excluded_foods'] ?? '') ?></textarea>
          <div class="form-hint">Personal dislikes — AI will avoid including these in your plans.</div>
        </div>

        <button type="submit" class="btn btn-primary">Save Diet Preferences</button>
      </form>
    </div>

    <!-- ── SECURITY ───────────────────────────────────────── -->
    <div class="card profile-section" id="sec-security">
      <h3 style="margin-bottom:1.5rem;font-size:1.1rem">🔒 Security</h3>
      <form method="POST">
        <input type="hidden" name="section" value="password">
        <div class="form-group">
          <label class="form-label">Current Password</label>
          <div class="password-toggle">
            <input class="form-control" type="password" name="current_password" placeholder="••••••••" required>
            <button type="button" class="toggle-eye">👁</button>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">New Password</label>
          <div class="password-toggle">
            <input class="form-control" type="password" name="new_password" placeholder="Min. 8 characters" required>
            <button type="button" class="toggle-eye">👁</button>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Confirm New Password</label>
          <div class="password-toggle">
            <input class="form-control" type="password" name="confirm_password" placeholder="Repeat new password" required>
            <button type="button" class="toggle-eye">👁</button>
          </div>
        </div>
        <button type="submit" class="btn btn-primary">Change Password</button>
      </form>

      <hr class="divider">

      <div>
        <h4 style="font-size:.92rem;margin-bottom:.5rem;color:#c0392b">⚠️ Danger Zone</h4>
        <p style="font-size:.85rem;color:var(--text-soft);margin-bottom:1rem">Permanently delete your account and all data. This cannot be undone.</p>
        <button class="btn btn-ghost btn-sm" style="border:1.5px solid #e74c3c;color:#c0392b"
                onclick="if(confirm('Delete your account permanently? This cannot be undone.'))alert('Contact support to complete deletion.')">
          Delete Account
        </button>
      </div>
    </div>

  </div><!-- right col -->
</div><!-- .profile-layout -->

<script>
function showSection(id, btn) {
  document.querySelectorAll('.profile-section').forEach(s => s.classList.remove('active'));
  document.querySelectorAll('.section-nav-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('sec-' + id).classList.add('active');
  btn.classList.add('active');
}

// Auto-open section from hash
const hash = location.hash.replace('#','');
const hashMap = {personal:'personal',health:'health',diet:'diet',security:'security'};
if (hashMap[hash]) {
  const btn = [...document.querySelectorAll('.section-nav-btn')].find(b => b.textContent.toLowerCase().includes(hash));
  if (btn) showSection(hash, btn);
}
</script>

<?php require 'includes/footer.php'; ?>