<?php require_once __DIR__.'/../config.php'; ?>
<!doctype html><html lang="en"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/png" href="<?php echo url_path('/assets/logo.png'); ?>">
<title>AI Course Recommender</title>
<link rel="stylesheet" href="<?php echo url_path('/assets/style.css') . '?v=' . time(); ?>">
<link rel="stylesheet" href="<?php echo url_path('/assets/save-plan-styles.css') . '?v=' . time(); ?>">
<script src="<?php echo url_path('/assets/theme-toggle.js') . '?v=' . time(); ?>"></script>
</head><body>

<!-- Navbar -->
<nav class="navbar">
  <div class="container">
    <div class="nav-brand">
      <img src="<?php echo url_path('/assets/logo.png'); ?>" alt="Logo" class="logo">
      <a href="<?php echo url_path('/index.php'); ?>" style="text-decoration: none; color: inherit;">
        <span>AI Course Recommender</span>
      </a>
    </div>
    
    <div class="nav-menu">
      <?php if (is_logged_in()): ?>
        <a href="<?php echo url_path('/recommender.php'); ?>" class="nav-link">Generate Plan</a>
        <a href="<?php echo url_path('/my-plans.php'); ?>" class="nav-link">My Plans</a>
        <div class="user-dropdown">
          <button class="user-btn">
            <span class="user-icon">👤</span>
            <span class="user-name"><?php echo e($_SESSION['user_name'] ?? 'User'); ?></span>
            <span class="dropdown-arrow">▼</span>
          </button>
          <div class="dropdown-menu">
            <div class="dropdown-header">
              <div class="user-email"><?php echo e($_SESSION['user_email'] ?? ''); ?></div>
              <div class="user-level"><?php echo e($_SESSION['user_skill_level'] ?? 'Beginner'); ?> Level</div>
            </div>
            <div class="dropdown-divider"></div>
            <a href="<?php echo url_path('/profile.php'); ?>" class="dropdown-item">⚙️ Settings</a>
            <a href="<?php echo url_path('/logout.php'); ?>" class="dropdown-item">🚪 Logout</a>
          </div>
        </div>
      <?php else: ?>
        <a href="<?php echo url_path('/login.php'); ?>" class="nav-link">Login</a>
        <a href="<?php echo url_path('/register.php'); ?>" class="nav-cta">Get Started</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<!-- Flash Message -->
<?php if ($flash = get_flash()): ?>
  <div class="flash-message flash-<?php echo $flash['type']; ?>">
    <div class="container">
      <?php echo e($flash['message']); ?>
    </div>
  </div>
<?php endif; ?>

<div class="container">

<style>
.navbar {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  height: 60px;
  background: var(--bg-darker);
  border-bottom: 1px solid var(--border-color);
  backdrop-filter: blur(10px);
  z-index: 1000;
  transition: transform 0.3s ease;
}

.navbar .container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 0 20px;
  height: 100%;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.nav-brand {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 18px;
  font-weight: 600;
}

.logo {
  width: 40px;
  height: 40px;
  object-fit: contain;
}

.nav-menu {
  display: flex;
  align-items: center;
  gap: 24px;
}

.nav-link {
  color: var(--text-secondary);
  text-decoration: none;
  font-size: 14px;
  font-weight: 500;
  transition: color 0.2s;
}

.nav-link:hover {
  color: var(--text-primary);
}

.nav-cta {
  padding: 8px 20px;
  background: linear-gradient(90deg, var(--brand), var(--brand2));
  color: white;
  text-decoration: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  transition: transform 0.2s, box-shadow 0.2s;
}

.nav-cta:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(124, 77, 255, 0.3);
}

.user-dropdown {
  position: relative;
}

.user-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
  font-size: 14px;
  color: var(--text-primary);
}

.user-btn:hover {
  border-color: var(--brand);
  background: var(--bg-darker);
}

.user-icon {
  font-size: 16px;
}

.user-name {
  font-weight: 500;
}

.dropdown-arrow {
  font-size: 10px;
  opacity: 0.6;
  transition: transform 0.2s;
}

.user-dropdown:hover .dropdown-arrow {
  transform: rotate(180deg);
}

.dropdown-menu {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  min-width: 220px;
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 8px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
  opacity: 0;
  visibility: hidden;
  transform: translateY(-10px);
  transition: all 0.2s;
  z-index: 1001;
}

.user-dropdown:hover .dropdown-menu {
  opacity: 1;
  visibility: visible;
  transform: translateY(0);
}

.dropdown-header {
  padding: 12px 16px;
}

.user-email {
  font-size: 13px;
  color: var(--text-primary);
  font-weight: 500;
  margin-bottom: 4px;
}

.user-level {
  font-size: 12px;
  color: var(--text-muted);
}

.dropdown-divider {
  height: 1px;
  background: var(--border-color);
  margin: 4px 0;
}

.dropdown-item {
  display: block;
  padding: 10px 16px;
  color: var(--text-secondary);
  text-decoration: none;
  font-size: 14px;
  transition: all 0.2s;
}

.dropdown-item:hover {
  background: var(--bg-darker);
  color: var(--text-primary);
}

.flash-message {
  position: fixed;
  top: 60px;
  left: 0;
  right: 0;
  padding: 12px 0;
  text-align: center;
  font-size: 14px;
  font-weight: 500;
  z-index: 999;
  animation: slideDown 0.3s ease;
}

.flash-success {
  background: rgba(34, 197, 94, 0.1);
  border-bottom: 1px solid rgba(34, 197, 94, 0.3);
  color: #22c55e;
}

.flash-error {
  background: rgba(255, 68, 68, 0.1);
  border-bottom: 1px solid rgba(255, 68, 68, 0.3);
  color: #ff4444;
}

.flash-info {
  background: rgba(0, 229, 255, 0.1);
  border-bottom: 1px solid rgba(0, 229, 255, 0.3);
  color: #00e5ff;
}

@keyframes slideDown {
  from {
    transform: translateY(-100%);
    opacity: 0;
  }
  to {
    transform: translateY(0);
    opacity: 1;
  }
}

@media (max-width: 768px) {
  .nav-menu {
    gap: 12px;
  }
  
  .user-name {
    display: none;
  }
  
  .nav-link {
    font-size: 13px;
  }
}
</style>