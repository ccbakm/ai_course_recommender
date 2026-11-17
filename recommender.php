<?php include __DIR__.'/partials/header.php'; ?>


<section class="hero" style="text-align:center; margin-top:80px; padding:40px 20px 20px;">
  <h1>
    <span style="background:linear-gradient(90deg,var(--brand),var(--brand2));-webkit-background-clip:text;background-clip:text;color:transparent">
      AI-Powered
    </span>
    Learning Paths
  </h1>
  <p class="sub" style="font-size:18px; color:var(--text-secondary); max-width:700px; margin:10px auto 0;">
    Share your interests and goals, and we will builds a clean, tailored roadmap just for you.
  </p>
</section>

<!-- ===== MAIN CONTAINER ===== -->
<div id="mainContainer">

  <!-- ===== FORM PANEL (Centered) ===== -->
  <div id="formPanel" class="form-panel-centered">
    <div class="card form-card">
      <h2 style="margin-bottom:24px; text-align:center;">Your Details</h2>

      <form id="promptForm">
        <div>
          <label for="interests">Primary Interests / Domain</label>
          <input id="interests" class="input" type="text" placeholder="e.g., Web Development, Data Science" required>
        </div>

        <div>
          <label for="level">Proficiency Level</label>
          <select id="level" required>
            <option value="Beginner">Beginner</option>
            <option value="Intermediate">Intermediate</option>
            <option value="Advanced">Advanced</option>
          </select>
        </div>

        <div>
          <label for="hours">Weekly Hours Available</label>
          <select id="hours" required>
            <option value="3-5">3–5 hours</option>
            <option value="6-8">6–8 hours</option>
            <option value="9-12">9–12 hours</option>
            <option value="12+">12+ hours</option>
          </select>
        </div>

        <div>
          <label for="goal">Your Goal</label>
          <textarea id="goal" rows="4" placeholder="e.g., Internship-ready in 8 weeks, Land a job, Build portfolio projects" required></textarea>
        </div>

        <button type="submit" id="generateBtn" class="btn btn-primary">
          Generate Plan
        </button>
      </form>
    </div>
  </div>

  <!-- ===== LOADING SCREEN ===== -->
  <div id="loadingScreen" class="loading-screen">
    <div class="loading-content">
      <div class="logo-loader-container">
        <img src="<?php echo url_path('/assets/logo.png'); ?>" alt="AI Course Recommender" class="logo-loader">
      </div>
      <h3 class="loading-title">Analyzing Your Profile</h3>
      <p class="loading-subtitle">Our AI is crafting your personalized learning path...</p>
      <div class="loading-dots">
        <span></span><span></span><span></span>
      </div>
    </div>
  </div>

  <!-- ===== RESULT PANEL (Fullscreen) ===== -->
  <div id="resultPanel" class="result-panel-fullscreen">
    <div class="result-container">
      <div id="result"></div>
    </div>
  </div>
</div>

<?php include __DIR__.'/partials/footer.php'; ?>