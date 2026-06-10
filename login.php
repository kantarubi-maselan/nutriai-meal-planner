<?php
require_once 'includes/config.php';

if (isLoggedIn()) redirect('dashboard.php');

$db = getDB();
$errors  = [];
$success = '';

// ── Default tab from URL param
$defaultTab = (isset($_GET['tab']) && $_GET['tab'] === 'register') ? 'register' : 'login';

// ── Handle POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── LOGIN ────────────────────────────────────────────────
    if ($action === 'login') {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$email || !$password) {
            $errors[] = 'Please fill in all fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address.';
        } else {
            $stmt = $db->prepare("SELECT id, name, password_hash FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                flashSet('success', 'Welcome back, ' . $user['name'] . '!');
                redirect('dashboard.php');
            } else {
                $errors[] = 'Invalid email or password.';
            }
        }
        $defaultTab = 'login';
    }

    // ── REGISTER ─────────────────────────────────────────────
    if ($action === 'register') {
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        if (!$name || !$email || !$password || !$confirm) {
            $errors[] = 'Please fill in all fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address.';
        } elseif (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        } elseif ($password !== $confirm) {
            $errors[] = 'Passwords do not match.';
        } else {
            // Check duplicate email
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = 'An account with this email already exists.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $stmt = $db->prepare("INSERT INTO users (name, email, password_hash) VALUES (?,?,?)");
                $stmt->execute([$name, $email, $hash]);
                $userId = $db->lastInsertId();

                // Create empty profile
                $db->prepare("INSERT INTO user_profiles (user_id) VALUES (?)")->execute([$userId]);

                $_SESSION['user_id']   = $userId;
                $_SESSION['user_name'] = $name;
                flashSet('success', 'Account created! Let\'s set up your profile.');
                redirect('profile.php');
            }
        }
        $defaultTab = 'register';
    }

    // ── FORGOT PASSWORD (basic) ───────────────────────────────
    if ($action === 'forgot') {
        $email = trim($_POST['forgot_email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email.';
        } else {
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                $db->prepare("UPDATE users SET reset_token=?, reset_expires=? WHERE email=?")
                   ->execute([$token, $expires, $email]);
                // In production: send email with reset link
                $success = 'Password reset link sent! (Check your email)';
            } else {
                $success = 'If this email exists, a reset link has been sent.';
            }
        }
        $defaultTab = 'forgot';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login / Register — NutriAI</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
<style>
  .auth-feature-icon { font-size: 1rem; }
  .strength-bar { height: 4px; border-radius: 2px; background: var(--border); margin-top: .4rem; overflow: hidden; }
  .strength-fill { height: 100%; border-radius: 2px; width: 0; transition: width .3s, background .3s; }
  .error-list {
    background: var(--rose-light); border: 1px solid var(--rose); border-radius: var(--radius-sm);
    padding: .85rem 1rem; margin-bottom: 1.25rem;
    font-size: .88rem; color: #c0392b;
  }
  .error-list li { margin-left: 1rem; }
  .success-msg {
    background: var(--sage-light); border: 1px solid var(--sage); border-radius: var(--radius-sm);
    padding: .85rem 1rem; margin-bottom: 1.25rem;
    font-size: .88rem; color: var(--sage-dark); font-weight: 500;
  }
  .back-home { display:inline-flex;align-items:center;gap:.4rem;font-size:.85rem;color:var(--text-soft);margin-bottom:1.5rem; }
  .back-home:hover { color: var(--sage-dark); }
</style>
</head>
<body>

<div class="auth-page">

  <!-- ── Left panel ─────────────────────────────────────────── -->
  <div class="auth-left">
    <div class="auth-left-blob auth-left-blob-1"></div>
    <div class="auth-left-blob auth-left-blob-2"></div>
    <div class="auth-left-content">
      <div class="auth-left-logo">🥗</div>
      <h1>Your personal AI nutritionist</h1>
      <p>Plan smarter meals, hit your health goals, and enjoy food you love — powered by AI.</p>
      <ul class="auth-features">
        <li><span class="auth-feature-icon">✅</span> Halal, vegetarian & diet-aware plans</li>
        <li><span class="auth-feature-icon">✅</span> Instant grocery list generation</li>
        <li><span class="auth-feature-icon">✅</span> Calorie & macro tracking</li>
        <li><span class="auth-feature-icon">✅</span> AI chat assistant 24/7</li>
        <li><span class="auth-feature-icon">✅</span> Completely free to get started</li>
      </ul>
    </div>
  </div>

  <!-- ── Right panel ────────────────────────────────────────── -->
  <div class="auth-right">
    <div class="auth-box">

      <a href="index.php" class="back-home">← Back to home</a>

      <?php if ($errors): ?>
      <div class="error-list"><ul><?php foreach($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>

      <?php if ($success): ?>
      <div class="success-msg"><?= sanitize($success) ?></div>
      <?php endif; ?>

      <!-- Tabs -->
      <div class="auth-tabs">
        <button class="auth-tab <?= $defaultTab==='login'    ? 'active':'' ?>" data-target="form-login">Log In</button>
        <button class="auth-tab <?= $defaultTab==='register' ? 'active':'' ?>" data-target="form-register">Register</button>
        <button class="auth-tab <?= $defaultTab==='forgot'   ? 'active':'' ?>" data-target="form-forgot">Reset</button>
      </div>

      <!-- ── LOGIN FORM ─────────────────────────────────────── -->
      <div id="form-login" class="auth-form <?= $defaultTab==='login' ? 'active':'' ?>">
        <h2 style="font-size:1.6rem;margin-bottom:1.5rem">Welcome back 👋</h2>
        <form method="POST" action="">
          <input type="hidden" name="action" value="login">
          <div class="form-group">
            <label class="form-label" for="login_email">Email address</label>
            <input class="form-control" type="email" id="login_email" name="email"
                   placeholder="you@email.com" required
                   value="<?= sanitize($_POST['email'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label" for="login_password">
              Password
              <a href="#" class="forgot-link" style="float:right;font-weight:400" onclick="switchTab('form-forgot');return false">Forgot password?</a>
            </label>
            <div class="password-toggle">
              <input class="form-control" type="password" id="login_password" name="password" placeholder="••••••••" required>
              <button type="button" class="toggle-eye">👁</button>
            </div>
          </div>
          <div style="margin-bottom:1rem">
            <label style="display:flex;align-items:center;gap:.5rem;font-size:.88rem;color:var(--text-mid);cursor:pointer">
              <input type="checkbox" name="remember"> Remember me for 30 days
            </label>
          </div>
          <button type="submit" class="btn btn-primary" style="width:100%">Log In →</button>
        </form>
        <div class="auth-divider"><span>or</span></div>
        <p style="text-align:center;font-size:.88rem;color:var(--text-soft)">
          No account yet?
          <a href="#" style="color:var(--sage-dark);font-weight:600" onclick="switchTab('form-register');return false">Create one free</a>
        </p>
      </div>

      <!-- ── REGISTER FORM ──────────────────────────────────── -->
      <div id="form-register" class="auth-form <?= $defaultTab==='register' ? 'active':'' ?>">
        <h2 style="font-size:1.6rem;margin-bottom:1.5rem">Create your account 🌱</h2>
        <form method="POST" action="">
          <input type="hidden" name="action" value="register">
          <div class="form-group">
            <label class="form-label" for="reg_name">Full name</label>
            <input class="form-control" type="text" id="reg_name" name="name" placeholder="Ahmad Firdaus"
                   required value="<?= sanitize($_POST['name'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label" for="reg_email">Email address</label>
            <input class="form-control" type="email" id="reg_email" name="email"
                   placeholder="you@email.com" required
                   value="<?= sanitize($_POST['email'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label" for="reg_password">Password</label>
            <div class="password-toggle">
              <input class="form-control" type="password" id="reg_password" name="password"
                     placeholder="Min. 8 characters" required oninput="checkStrength(this.value)">
              <button type="button" class="toggle-eye">👁</button>
            </div>
            <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
            <span class="form-hint" id="strengthLabel">Enter a password</span>
          </div>
          <div class="form-group">
            <label class="form-label" for="reg_confirm">Confirm password</label>
            <div class="password-toggle">
              <input class="form-control" type="password" id="reg_confirm" name="confirm_password"
                     placeholder="Repeat password" required>
              <button type="button" class="toggle-eye">👁</button>
            </div>
          </div>
          <div style="margin-bottom:1.25rem">
            <label style="display:flex;align-items:flex-start;gap:.5rem;font-size:.82rem;color:var(--text-soft);cursor:pointer;line-height:1.5">
              <input type="checkbox" name="terms" required style="margin-top:.15rem">
              I agree to the <a href="#" style="color:var(--sage-dark)">Terms of Service</a> and <a href="#" style="color:var(--sage-dark)">Privacy Policy</a>
            </label>
          </div>
          <button type="submit" class="btn btn-primary" style="width:100%">Create Account →</button>
        </form>
        <div class="auth-divider"><span>or</span></div>
        <p style="text-align:center;font-size:.88rem;color:var(--text-soft)">
          Already have an account?
          <a href="#" style="color:var(--sage-dark);font-weight:600" onclick="switchTab('form-login');return false">Log in</a>
        </p>
      </div>

      <!-- ── FORGOT PASSWORD FORM ───────────────────────────── -->
      <div id="form-forgot" class="auth-form <?= $defaultTab==='forgot' ? 'active':'' ?>">
        <h2 style="font-size:1.6rem;margin-bottom:.5rem">Reset password 🔑</h2>
        <p style="font-size:.9rem;margin-bottom:1.5rem">Enter your email and we'll send you a reset link.</p>
        <form method="POST" action="">
          <input type="hidden" name="action" value="forgot">
          <div class="form-group">
            <label class="form-label" for="forgot_email">Email address</label>
            <input class="form-control" type="email" id="forgot_email" name="forgot_email"
                   placeholder="you@email.com" required>
          </div>
          <button type="submit" class="btn btn-primary" style="width:100%">Send Reset Link →</button>
        </form>
        <div style="margin-top:1.5rem;text-align:center">
          <a href="#" style="font-size:.88rem;color:var(--sage-dark)" onclick="switchTab('form-login');return false">← Back to login</a>
        </div>
      </div>

    </div><!-- .auth-box -->
  </div><!-- .auth-right -->
</div><!-- .auth-page -->

<script src="assets/js/main.js"></script>
<script>
function switchTab(targetId) {
  document.querySelectorAll('.auth-tab, .auth-form').forEach(el => el.classList.remove('active'));
  document.getElementById(targetId).classList.add('active');
  // activate matching tab button
  document.querySelectorAll('.auth-tab').forEach(btn => {
    if (btn.dataset.target === targetId) btn.classList.add('active');
  });
}

function checkStrength(pw) {
  const fill  = document.getElementById('strengthFill');
  const label = document.getElementById('strengthLabel');
  let score = 0;
  if (pw.length >= 8)  score++;
  if (/[A-Z]/.test(pw)) score++;
  if (/[0-9]/.test(pw)) score++;
  if (/[^A-Za-z0-9]/.test(pw)) score++;
  const colors = ['#f2b8c6','#f2d8a8','#a8d4e8','#a8c5a0'];
  const labels = ['Weak','Fair','Good','Strong'];
  const widths = ['25%','50%','75%','100%'];
  if (pw.length === 0) { fill.style.width = '0'; label.textContent = 'Enter a password'; return; }
  fill.style.width = widths[score-1] || '25%';
  fill.style.background = colors[score-1] || colors[0];
  label.textContent = 'Strength: ' + (labels[score-1] || 'Weak');
}
</script>
</body>
</html>