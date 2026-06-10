<?php
require_once 'includes/config.php';
// If already logged in, go to dashboard
if (isLoggedIn()) redirect('dashboard.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NutriAI — Eat Smart. Plan Faster. Live Healthier.</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
<style>
  /* Scroll reveal */
  .feature-card { opacity:0; transform: translateY(20px); transition: opacity .5s ease, transform .5s ease; }
  .feature-card.visible { opacity:1; transform: none; }
  .hero-image-wrap {
    margin-top: 3.5rem; animation: fadeUp .8s .5s ease both;
    background: var(--white); border-radius: var(--radius-lg);
    padding: 1.5rem; box-shadow: var(--shadow-lg);
    border: 1px solid var(--border); max-width: 720px; margin-inline: auto;
    margin-top: 3rem;
  }
  .mock-dashboard {
    display: grid; grid-template-columns: 1fr 1fr 1fr; gap: .75rem;
  }
  .mock-card {
    background: var(--cream); border-radius: var(--radius-sm);
    padding: 1rem; display: flex; flex-direction: column; gap: .4rem;
  }
  .mock-dot { width: 28px; height: 28px; border-radius: 50%; margin-bottom: .25rem; }
  .mock-line  { height: 8px; border-radius: 4px; background: var(--border); }
  .mock-line.w80 { width: 80%; }
  .mock-line.w60 { width: 60%; }
  .mock-line.w40 { width: 40%; }
  .testimonials { padding: 5rem 3rem; background: var(--white); }
  .testimonials-inner { max-width: 1100px; margin: 0 auto; }
  .testi-grid { display: grid; grid-template-columns: repeat(auto-fit,minmax(280px,1fr)); gap: 1.5rem; margin-top: 3rem; }
  .testi-card {
    background: var(--cream); border-radius: var(--radius-md);
    padding: 1.75rem; border: 1px solid var(--border);
  }
  .testi-stars { color: var(--peach); font-size: 1rem; margin-bottom: .75rem; }
  .testi-text  { font-size: .92rem; line-height: 1.7; color: var(--text-mid); font-style: italic; }
  .testi-author { display: flex; align-items: center; gap: .75rem; margin-top: 1.25rem; }
  .testi-avatar { width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
  .testi-name { font-weight: 600; font-size: .88rem; }
  .testi-role { font-size: .78rem; color: var(--text-soft); }
  .how-section { padding: 6rem 3rem; max-width: 1100px; margin: 0 auto; }
  .steps { display: grid; grid-template-columns: repeat(auto-fit,minmax(220px,1fr)); gap: 2rem; margin-top: 3rem; }
  .step { text-align: center; }
  .step-num {
    width: 52px; height: 52px; border-radius: 50%;
    background: var(--sage-dark); color: var(--white);
    font-family: var(--font-display); font-size: 1.3rem; font-weight: 700;
    display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem;
  }
</style>
</head>
<body class="landing-body">

<!-- ── Navigation ───────────────────────────────────────────── -->
<nav class="pub-nav">
  <div class="pub-nav-logo">
    <span style="font-size:1.5rem">🥗</span>
    <span style="font-family:var(--font-display);font-size:1.2rem;font-weight:700;color:var(--sage-dark)">NutriAI</span>
  </div>
  <div class="pub-nav-links">
    <a href="#features">Features</a>
    <a href="#how-it-works">How it works</a>
    <a href="#testimonials">Reviews</a>
  </div>
  <div class="pub-nav-cta">
    <a href="login.php" class="btn btn-ghost btn-sm">Log in</a>
    <a href="login.php?tab=register" class="btn btn-primary btn-sm">Get Started Free</a>
  </div>
</nav>

<!-- ── Hero ─────────────────────────────────────────────────── -->
<section class="hero">
  <div class="hero-bg"></div>
  <div class="hero-blob hero-blob-1"></div>
  <div class="hero-blob hero-blob-2"></div>
  <div class="hero-blob hero-blob-3"></div>

  <div class="hero-content">
    <div class="hero-badge">✨ Powered by AI</div>
    <h1 class="hero-title">
      Eat Smart.<br>
      Plan <em>Faster.</em><br>
      Live Healthier.
    </h1>
    <p class="hero-desc">
      Your personal AI nutritionist creates custom meal plans based on your goals, preferences, and pantry — in seconds. Halal, vegetarian, keto, and more.
    </p>
    <div class="hero-actions">
      <a href="login.php?tab=register" class="btn btn-primary btn-lg">🚀 Start for Free</a>
      <a href="#how-it-works" class="btn btn-outline btn-lg">See how it works</a>
    </div>

    <div class="hero-stats">
      <div class="hero-stat">
        <div class="hero-stat-num">50K+</div>
        <div class="hero-stat-label">Meal Plans Generated</div>
      </div>
      <div class="hero-stat">
        <div class="hero-stat-num">12K+</div>
        <div class="hero-stat-label">Happy Users</div>
      </div>
      <div class="hero-stat">
        <div class="hero-stat-num">98%</div>
        <div class="hero-stat-label">Satisfaction Rate</div>
      </div>
    </div>

    <!-- Mock UI Preview -->
    <div class="hero-image-wrap">
      <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:1rem;">
        <div style="width:10px;height:10px;border-radius:50%;background:#f2b8c6"></div>
        <div style="width:10px;height:10px;border-radius:50%;background:#f2d8a8"></div>
        <div style="width:10px;height:10px;border-radius:50%;background:#a8e8a0"></div>
        <span style="font-size:.78rem;color:var(--text-soft);margin-left:.5rem">NutriAI Dashboard</span>
      </div>
      <div class="mock-dashboard">
        <div class="mock-card">
          <div class="mock-dot" style="background:var(--peach-light)">🍳</div>
          <div style="font-size:.78rem;font-weight:600;color:var(--text-mid)">Breakfast</div>
          <div class="mock-line w80"></div>
          <div class="mock-line w60"></div>
          <span style="font-size:.72rem;color:var(--sage-dark);font-weight:600">380 kcal</span>
        </div>
        <div class="mock-card">
          <div class="mock-dot" style="background:var(--sage-light)">🥗</div>
          <div style="font-size:.78rem;font-weight:600;color:var(--text-mid)">Lunch</div>
          <div class="mock-line w80"></div>
          <div class="mock-line w40"></div>
          <span style="font-size:.72rem;color:var(--sage-dark);font-weight:600">520 kcal</span>
        </div>
        <div class="mock-card">
          <div class="mock-dot" style="background:var(--lav-light)">🍽️</div>
          <div style="font-size:.78rem;font-weight:600;color:var(--text-mid)">Dinner</div>
          <div class="mock-line w80"></div>
          <div class="mock-line w60"></div>
          <span style="font-size:.72rem;color:var(--sage-dark);font-weight:600">610 kcal</span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ── Features ──────────────────────────────────────────────── -->
<section class="features" id="features">
  <div class="features-header">
    <h2>Everything you need to eat well</h2>
    <p>From AI-generated meal plans to grocery lists and nutrition tracking — all in one beautiful app.</p>
  </div>
  <div class="features-grid">
    <?php
    $features = [
      ['icon'=>'🧠','color'=>'var(--lav-light)','title'=>'AI Meal Planning','desc'=>'AI generates personalized meal plans based on your goal, diet type, and available ingredients — in seconds.'],
      ['icon'=>'🕌','color'=>'var(--sage-light)','title'=>'Halal & Diet-Aware','desc'=>'Supports halal, vegetarian, vegan, keto, and paleo diets. Your preferences are always respected.'],
      ['icon'=>'🔥','color'=>'var(--peach-light)','title'=>'Calorie Control','desc'=>'Set your calorie target and our AI ensures every plan hits the mark with balanced macros.'],
      ['icon'=>'🛒','color'=>'var(--sky-light)','title'=>'Smart Grocery Lists','desc'=>'Auto-generate categorised shopping lists from your meal plan. Check off items as you shop.'],
      ['icon'=>'📊','color'=>'var(--rose-light)','title'=>'Nutrition Insights','desc'=>'Visual charts for daily calories, protein, carbs, and fat intake. Track your progress over time.'],
      ['icon'=>'🤖','color'=>'var(--lav-light)','title'=>'AI Chat Assistant','desc'=>"Ask anything — \"What's a cheap high-protein meal?\" or \"What can I make with chicken and rice?\""],
    ];
    foreach ($features as $i => $f): ?>
    <div class="feature-card" style="animation-delay:<?= $i * 0.08 ?>s">
      <div class="feature-icon" style="background:<?= $f['color'] ?>"><?= $f['icon'] ?></div>
      <h3><?= $f['title'] ?></h3>
      <p><?= $f['desc'] ?></p>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- ── How It Works ──────────────────────────────────────────── -->
<section class="how-section" id="how-it-works">
  <div style="text-align:center;margin-bottom:0">
    <h2>How it works</h2>
    <p style="margin-top:.75rem;color:var(--text-soft)">Get your personalised plan in 3 simple steps</p>
  </div>
  <div class="steps">
    <?php
    $steps = [
      ['num'=>'1','title'=>'Set Your Profile','desc'=>'Tell us your goals, diet preferences, weight, and allergies. One-time setup.'],
      ['num'=>'2','title'=>'Generate Your Plan','desc'=>'Our AI analyses your needs and creates a personalised meal plan with recipes.'],
      ['num'=>'3','title'=>'Cook & Track','desc'=>'Follow the plan, check off groceries, and track your nutrition progress.'],
      ['num'=>'4','title'=>'Refine & Repeat','desc'=>'Regenerate, save favourites, and adjust as your goals evolve.'],
    ];
    foreach ($steps as $step): ?>
    <div class="step">
      <div class="step-num"><?= $step['num'] ?></div>
      <h3 style="font-size:1.05rem;margin-bottom:.5rem"><?= $step['title'] ?></h3>
      <p style="font-size:.88rem"><?= $step['desc'] ?></p>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- ── Testimonials ──────────────────────────────────────────── -->
<section class="testimonials" id="testimonials">
  <div class="testimonials-inner">
    <div style="text-align:center">
      <h2>Loved by thousands</h2>
      <p style="margin-top:.75rem">Real people, real results</p>
    </div>
    <div class="testi-grid">
      <?php
      $testimonials = [
        ['stars'=>5,'text'=>'I lost 6kg in 2 months following NutriAI meal plans. The halal filter means I never have to worry about ingredients. Absolutely love it!','name'=>'Amirah Yusof','role'=>'Teacher, KL','emoji'=>'🧕'],
        ['stars'=>5,'text'=>'As a gym-goer, hitting my protein targets was always a struggle. NutriAI builds high-protein plans around food I actually enjoy. Game changer.','name'=>'Haziq Rahman','role'=>'Personal Trainer','emoji'=>'💪'],
        ['stars'=>5,'text'=>'The grocery list feature saves me so much time. Everything is grouped and I just tick items off as I go. My weekly shopping is so much easier now.','name'=>'Siti Norzahra','role'=>'Working Mum','emoji'=>'👩‍👧'],
      ];
      foreach ($testimonials as $t): ?>
      <div class="testi-card">
        <div class="testi-stars"><?= str_repeat('⭐', $t['stars']) ?></div>
        <p class="testi-text">"<?= $t['text'] ?>"</p>
        <div class="testi-author">
          <div class="testi-avatar" style="background:var(--sage-light)"><?= $t['emoji'] ?></div>
          <div>
            <div class="testi-name"><?= $t['name'] ?></div>
            <div class="testi-role"><?= $t['role'] ?></div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ── CTA ───────────────────────────────────────────────────── -->
<div style="padding: 0 3rem 4rem">
  <div class="cta-section">
    <h2>Ready to eat smarter?</h2>
    <p>Join thousands already using NutriAI to plan healthier, happier meals every day.</p>
    <a href="login.php?tab=register" class="btn btn-primary btn-lg">Create Free Account →</a>
  </div>
</div>

<!-- ── Footer ────────────────────────────────────────────────── -->
<footer class="pub-footer">
  <p>© <?= date('Y') ?> NutriAI. Made with 🥗 and AI.</p>
</footer>

<script src="assets/js/main.js"></script>
</body>
</html>