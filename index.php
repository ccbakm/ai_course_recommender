<?php require_once __DIR__.'/config.php'; ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/png" href="<?php echo url_path('/assets/logo.png'); ?>">
<title>AI Course Recommender - Your AI-Powered Learning Companion</title>
<link rel="stylesheet" href="<?php echo url_path('/assets/style.css') . '?v=' . time(); ?>">
<script src="<?php echo url_path('/assets/theme-toggle.js') . '?v=' . time(); ?>"></script>
</head>

<body>

<!-- Navbar -->
<nav class="navbar">
  <div class="container">
    <div class="nav-brand">
      <img src="<?php echo url_path('/assets/logo.png'); ?>" alt="Logo" class="logo">
      <span>AI Course Recommender</span>
    </div>
    <a href="./recommender.php" class="nav-cta">Try Now</a>
  </div>
</nav>

<!-- Hero Section -->
<section class="hero" style="margin-top: 60px; padding: 110px 20px 100px; text-align: center;">
  <div class="hero-dot-grid"></div>
  <div class="container">
    <h1 style="margin-bottom: 20px; font-size: 56px; letter-spacing: -1px;">
      Your <span class="gradient-text">AI-Powered</span><br>Learning Companion
    </h1>
    <p style="font-size: 18px; color: var(--text-secondary); max-width: 600px; margin: 0 auto 44px; line-height: 1.8;">
      Stop guessing. Start dominating.<br>
      Get razor-sharp course recommendations built for your goals, your hustle, and your upgrade.
    </p>
    <div class="hero-buttons">
      <a href="./recommender.php" class="btn btn-pulse">Start Learning</a>
      <a href="#features" class="btn btn-outline">Learn More</a>
    </div>
  </div>
</section>

<!-- Features Section -->
<section id="features" class="section-features" style="padding: 80px 20px; background: rgba(0, 0, 0, 0.18);">
  <div class="container">
    <h2 style="margin-bottom: 60px;">Why Choose Us?</h2>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px;">
      <div class="card" data-reveal-delay="1" style="text-align: center; padding: 30px;">
        <div style="font-size: 48px; margin-bottom: 16px;">🤖</div>
        <h3>AI Intelligence</h3>
        <p style="color: var(--text-muted); font-size: 14px;">We take your goals, tear them apart, and rebuild them into a laser-sharp learning roadmap in seconds.</p>
      </div>
      <div class="card" data-reveal-delay="2" style="text-align: center; padding: 30px;">
        <div style="font-size: 48px; margin-bottom: 16px;">⏱️</div>
        <h3>Save Time</h3>
        <p style="color: var(--text-muted); font-size: 14px;">Forget research paralysis. We turn your weekly hours into a clean, efficient learning plan.</p>
      </div>
      <div class="card" data-reveal-delay="3" style="text-align: center; padding: 30px;">
        <div style="font-size: 48px; margin-bottom: 16px;">🎯</div>
        <h3>Goal-Focused</h3>
        <p style="color: var(--text-muted); font-size: 14px;">Job? Internship? Glow-up in skills? Whatever your agenda is, we tailor the plan to your grind.</p>
      </div>
      <div class="card" data-reveal-delay="4" style="text-align: center; padding: 30px;">
        <div style="font-size: 48px; margin-bottom: 16px;">📈</div>
        <h3>Progressive Learning</h3>
        <p style="color: var(--text-muted); font-size: 14px;">From beginner to advanced, get paths suited to your current skill level.</p>
      </div>
      <div class="card" data-reveal-delay="5" style="text-align: center; padding: 30px;">
        <div style="font-size: 48px; margin-bottom: 16px;">💡</div>
        <h3>Resource Rich</h3>
        <p style="color: var(--text-muted); font-size: 14px;">Every course step? Packed with the resources you need, the time it’ll take, and exactly what to do next.</p>
      </div>
      <div class="card" data-reveal-delay="6" style="text-align: center; padding: 30px;">
        <div style="font-size: 48px; margin-bottom: 16px;">🔄</div>
        <h3>Refine Anytime</h3>
        <p style="color: var(--text-muted); font-size: 14px;">Generate fresh plans in seconds. Play with new goals and preferences like it’s nothing.</p>
      </div>
    </div>
  </div>
</section>

<!-- How It Works Section -->
<section style="padding: 80px 20px;">
  <div class="container">
    <h2 style="margin-bottom: 60px;">How It Works</h2>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 40px; align-items: center;">
      <div class="card" style="text-align: center; padding: 40px 30px;">
        <div class="step-number">1</div>
        <h3>Tell Us About You</h3>
        <p style="color: var(--text-muted); font-size: 14px;">Share your interests, skill level, available time, and learning goals.</p>
      </div>
      <div class="card" style="text-align: center; padding: 40px 30px;">
        <div class="step-number">2</div>
        <h3>AI Analyzes</h3>
        <p style="color: var(--text-muted); font-size: 14px;">Our AI processes your input and creates a personalized roadmap.</p>
      </div>
      <div class="card" style="text-align: center; padding: 40px 30px;">
        <div class="step-number">3</div>
        <h3>Get Your Plan</h3>
        <p style="color: var(--text-muted); font-size: 14px;">Receive a structured learning path with courses, resources, and timelines.</p>
      </div>
    </div>
  </div>
</section>

<!-- CTA Section -->
<section style="padding: 80px 20px; background: linear-gradient(135deg, rgba(124, 77, 255, 0.1) 0%, rgba(0, 229, 255, 0.05) 100%); text-align: center;">
  <div class="container">
    <h2 style="margin-bottom: 12px;">Ready to level up for real?</h2>
    <p style="font-size: 18px; color: var(--text-secondary); margin-bottom: 32px;">Your personalized plan is just one minute away.</p>
    <a href="./recommender.php" class="btn btn-large">Get Started Now</a>
  </div>
</section>

<!-- Footer -->
<footer style="background: var(--bg-darker); border-top: 1px solid var(--border-color); padding: 30px 20px; text-align: center; color: var(--text-muted); font-size: 13px;">
  <div class="container">
    <p>&copy; 2025 AI Course Recommender. Powered by Advanced AI Technology.</p>
  </div>
</footer>

<script src="<?php echo url_path('/assets/neural-aurora.js') . '?v=' . time(); ?>"></script>
</body>
</html>