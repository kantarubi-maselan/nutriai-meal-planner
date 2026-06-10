<?php
// includes/header.php — call after setting $pageTitle and $activePage
$flash = flashGet();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= sanitize($pageTitle ?? APP_NAME) ?> — <?= APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body>

<?php if (isLoggedIn()): ?>
<!-- Sidebar Nav (logged-in pages) -->
<aside class="sidebar">
    <div class="sidebar-logo">
        <span class="logo-icon">🥗</span>
        <span class="logo-text">NutriAI</span>
    </div>
    <nav class="sidebar-nav">
        <a href="dashboard.php"   class="nav-item <?= ($activePage==='dashboard')   ? 'active':'' ?>"><span>🏠</span> Dashboard</a>
        <a href="generator.php"   class="nav-item <?= ($activePage==='generator')   ? 'active':'' ?>"><span>🧠</span> Generate Plan</a>
        <a href="weekly.php"      class="nav-item <?= ($activePage==='weekly')      ? 'active':'' ?>"><span>📅</span> Weekly Planner</a>
        <a href="grocery.php"     class="nav-item <?= ($activePage==='grocery')     ? 'active':'' ?>"><span>🛒</span> Grocery List</a>
        <a href="nutrition.php"   class="nav-item <?= ($activePage==='nutrition')   ? 'active':'' ?>"><span>📊</span> Nutrition</a>
        <a href="saved-plans.php" class="nav-item <?= ($activePage==='saved')       ? 'active':'' ?>"><span>💾</span> Saved Plans</a>
        <a href="chat.php"        class="nav-item <?= ($activePage==='chat')        ? 'active':'' ?>"><span>🤖</span> AI Assistant</a>
        <a href="profile.php"     class="nav-item <?= ($activePage==='profile')     ? 'active':'' ?>"><span>⚙️</span> Profile</a>
    </nav>
    <a href="logout.php" class="sidebar-logout">Sign Out</a>
</aside>
<div class="main-wrap">
<?php endif; ?>

<?php if ($flash): ?>
<div class="flash flash--<?= $flash['type'] ?>"><?= sanitize($flash['message']) ?></div>
<?php endif; ?>