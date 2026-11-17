// ===== app.js (IMPROVED - Clean, structured course display + SAVE PLAN) =====
const form = document.getElementById("promptForm");
const formPanel = document.getElementById("formPanel");
const loadingScreen = document.getElementById("loadingScreen");
const resultPanel = document.getElementById("resultPanel");
const resultBox = document.getElementById("result");

// Store form data and plan data globally
let currentFormData = {};
let currentPlanData = {};

// Check for pending plan after login/registration
window.addEventListener('DOMContentLoaded', function() {
  // Only check for pending plan on recommender.php page
  const currentPage = window.location.pathname;
  if (currentPage.includes('recommender.php')) {
    checkAndSavePendingPlan();
  }
});

async function checkAndSavePendingPlan() {
  console.log('🔍 Checking for pending plan on page load...');
  
  try {
    const pendingPlanStr = localStorage.getItem('pendingPlan');
    
    if (!pendingPlanStr) {
      console.log('❌ No pending plan found in localStorage');
      return;
    }

    console.log('✅ Pending plan found in localStorage!');
    const pendingPlan = JSON.parse(pendingPlanStr);
    
    // Check if plan is not too old (24 hours)
    const hoursSinceCreated = (Date.now() - pendingPlan.timestamp) / (1000 * 60 * 60);
    console.log(`⏰ Plan age: ${hoursSinceCreated.toFixed(2)} hours`);
    
    if (hoursSinceCreated > 24) {
      console.log('⚠️ Plan expired (>24 hours) - removing');
      localStorage.removeItem('pendingPlan');
      return;
    }

    // Restore plan data
    currentFormData = pendingPlan.formData;
    currentPlanData = pendingPlan.planData;
    console.log('✅ Plan data restored to memory');
    console.log('📋 Form data:', currentFormData);
    console.log('📋 Plan data:', currentPlanData);

    // Show custom confirmation dialog first
    console.log('🎯 Showing confirmation dialog...');
    showPendingPlanConfirmation();
    
  } catch (err) {
    console.error('❌ Error checking pending plan:', err);
    localStorage.removeItem('pendingPlan');
  }
}

function showPendingPlanConfirmation() {
  const modal = document.createElement('div');
  modal.id = 'pendingPlanConfirmModal';
  modal.className = 'save-modal';
  
  modal.innerHTML = `
    <div class="save-modal-content">
      <div class="save-modal-header">
        <h2>🎉 Welcome Back!</h2>
        <button class="modal-close" onclick="dismissPendingPlan()">✕</button>
      </div>
      
      <div class="save-modal-body" style="text-align: center; padding: 30px;">
        <div style="font-size: 64px; margin-bottom: 20px;">📚</div>
        <h3 style="margin-bottom: 12px;">You have an unsaved learning plan!</h3>
        <p style="color: var(--text-muted); margin-bottom: 24px;">
          We found the <strong>${currentFormData.interests}</strong> plan you created earlier.
          Would you like to save it now?
        </p>
      </div>

      <div class="save-modal-footer">
        <button class="btn btn-secondary" onclick="dismissPendingPlan()">Not Now</button>
        <button class="btn btn-primary" onclick="proceedToSavePlan()">Yes, Save It!</button>
      </div>
    </div>
  `;

  document.body.appendChild(modal);
}

function dismissPendingPlan() {
  const modal = document.getElementById('pendingPlanConfirmModal');
  if (modal) modal.remove();
  
  // Clear pending plan from localStorage
  localStorage.removeItem('pendingPlan');
  console.log('✅ Pending plan dismissed');
}

function proceedToSavePlan() {
  const modal = document.getElementById('pendingPlanConfirmModal');
  if (modal) modal.remove();
  
  // Now show the save modal
  showSaveModal();
  
  // Clear from localStorage after showing modal
  localStorage.removeItem('pendingPlan');
  console.log('✅ Proceeding to save plan');
}

form?.addEventListener("submit", async (e) => {
  e.preventDefault();

  // Show loading screen
  showLoading();

  const data = {
    interests: document.getElementById("interests").value.trim(),
    level: document.getElementById("level").value,
    hours: document.getElementById("hours").value,
    goal: document.getElementById("goal").value.trim(),
  };

  // Store form data for saving later
  currentFormData = data;

  try {
    const res = await fetch("api/recommend.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(data),
    });

    const text = await res.text();
    let payload = null;
    try { payload = JSON.parse(text); } catch (_) {}

    if (!res.ok) {
      const msg = (payload && payload.error) || text.slice(0, 200);
      throw new Error(`Server error (${res.status}): ${msg}`);
    }
    if (!payload || typeof payload !== "object") {
      throw new Error("Invalid JSON received from server.");
    }

    // Store plan data for saving
    currentPlanData = payload;

    // Hide loading and show result
    hideLoading();
    renderResult(payload);
    showResult();

  } catch (err) {
    console.error(err);
    hideLoading();
    resultBox.innerHTML = `
      <div class="card" style="padding: 30px; text-align: center;">
        <h3 style="color: #ff4444; margin-bottom: 12px;">⚠️ Error Loading Plan</h3>
        <p class="helper" style="color: var(--text-muted);">${err.message || "Please try again."}</p>
        <button onclick="resetForm()" class="btn" style="margin-top: 20px;">Try Again</button>
      </div>
    `;
    showResult();
  }
});

// ---------- Loading & Result Functions ----------
function showLoading() {
  formPanel.classList.add('hide');
  loadingScreen.classList.add('active');
}

function hideLoading() {
  loadingScreen.classList.remove('active');
}

function showResult() {
  resultPanel.classList.add('show');
  
  // Add close button if not exists
  if (!document.getElementById('closeResultBtn')) {
    const closeBtn = document.createElement('button');
    closeBtn.id = 'closeResultBtn';
    closeBtn.className = 'close-result-btn';
    closeBtn.innerHTML = '← Back to Form';
    closeBtn.onclick = resetForm;
    document.body.appendChild(closeBtn);
  }

  // Add save button if not exists
  if (!document.getElementById('savePlanBtn')) {
    const saveBtn = document.createElement('button');
    saveBtn.id = 'savePlanBtn';
    saveBtn.className = 'save-plan-btn';
    saveBtn.innerHTML = '💾 Save This Plan';
    saveBtn.onclick = handleSavePlan;
    document.body.appendChild(saveBtn);
  }
}

function resetForm() {
  resultPanel.classList.remove('show');
  formPanel.classList.remove('hide');
  resultBox.innerHTML = '';
  form.reset();
  
  // Remove buttons
  const closeBtn = document.getElementById('closeResultBtn');
  if (closeBtn) closeBtn.remove();
  const saveBtn = document.getElementById('savePlanBtn');
  if (saveBtn) saveBtn.remove();
  
  // Clear stored data
  currentFormData = {};
  currentPlanData = {};
}

// ---------- Save Plan Functionality ----------
async function handleSavePlan() {
  const saveBtn = document.getElementById('savePlanBtn');
  
  // Disable button to prevent double-clicks
  saveBtn.disabled = true;
  saveBtn.innerHTML = '⏳ Saving...';

  try {
    // Create save modal
    showSaveModal();
  } catch (err) {
    console.error('Error showing save modal:', err);
    saveBtn.disabled = false;
    saveBtn.innerHTML = '💾 Save This Plan';
  }
}

function showSaveModal() {
  // Remove existing modal if any
  const existingModal = document.getElementById('savePlanModal');
  if (existingModal) existingModal.remove();

  const modal = document.createElement('div');
  modal.id = 'savePlanModal';
  modal.className = 'save-modal';
  
  modal.innerHTML = `
    <div class="save-modal-content">
      <div class="save-modal-header">
        <h2>💾 Save Learning Plan</h2>
        <button class="modal-close" onclick="closeSaveModal()">✕</button>
      </div>
      
      <div class="save-modal-body">
        <div class="form-group">
          <label for="planTitle">Plan Name</label>
          <input 
            type="text" 
            id="planTitle" 
            class="input" 
            placeholder="e.g., My Web Development Journey"
            value="${currentFormData.interests} Learning Path"
          />
        </div>

        <div class="form-group">
          <label>
            <input type="checkbox" id="setAsActive" checked>
            Set as active plan
          </label>
        </div>

        <div id="saveError" class="save-error" style="display: none;"></div>
      </div>

      <div class="save-modal-footer">
        <button class="btn btn-secondary" onclick="closeSaveModal()">Cancel</button>
        <button class="btn btn-primary" onclick="confirmSavePlan()">Save Plan</button>
      </div>
    </div>
  `;

  document.body.appendChild(modal);
  
  // Focus on title input
  setTimeout(() => {
    document.getElementById('planTitle').focus();
    document.getElementById('planTitle').select();
  }, 100);
}

function closeSaveModal() {
  const modal = document.getElementById('savePlanModal');
  if (modal) modal.remove();
  
  // Re-enable save button
  const saveBtn = document.getElementById('savePlanBtn');
  if (saveBtn) {
    saveBtn.disabled = false;
    saveBtn.innerHTML = '💾 Save This Plan';
  }
}

async function confirmSavePlan() {
  const title = document.getElementById('planTitle').value.trim();
  const setAsActive = document.getElementById('setAsActive').checked;
  const errorDiv = document.getElementById('saveError');

  if (!title) {
    errorDiv.textContent = 'Please enter a plan name';
    errorDiv.style.display = 'block';
    return;
  }

  errorDiv.style.display = 'none';

  try {
    const response = await fetch('api/save-plan.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        title: title,
        domain: currentFormData.interests,
        skill_level: currentFormData.level,
        weekly_hours: currentFormData.hours,
        goal: currentFormData.goal,
        plan_data: currentPlanData,
        is_active: setAsActive
      })
    });

    const result = await response.json();

    if (response.ok && result.success) {
      // Success - show message and redirect
      closeSaveModal();
      showSuccessMessage('Plan saved successfully! Redirecting...');
      setTimeout(() => {
        window.location.href = 'my-plans.php';
      }, 1500);
    } else if (result.auth_required) {
      // Not logged in - show login prompt
      closeSaveModal();
      showLoginPrompt();
    } else {
      // Other error
      errorDiv.textContent = result.error || 'Failed to save plan';
      errorDiv.style.display = 'block';
    }
  } catch (err) {
    console.error('Save plan error:', err);
    errorDiv.textContent = 'Network error. Please try again.';
    errorDiv.style.display = 'block';
  }
}

function showLoginPrompt() {
  console.log('🔐 Login required - storing plan data...');
  console.log('📋 Current form data:', currentFormData);
  console.log('📋 Current plan data:', currentPlanData);
  
  // IMPORTANT: Store plan data in localStorage before redirecting
  // This will survive the page redirect and login process
  try {
    const dataToStore = {
      formData: currentFormData,
      planData: currentPlanData,
      timestamp: Date.now()
    };
    
    localStorage.setItem('pendingPlan', JSON.stringify(dataToStore));
    console.log('✅ Plan data stored in localStorage successfully!');
    console.log('📦 Stored data:', dataToStore);
    
    // Verify it was stored
    const verify = localStorage.getItem('pendingPlan');
    if (verify) {
      console.log('✅ Verified: Data is in localStorage');
    } else {
      console.error('❌ ERROR: Data not found in localStorage after storing!');
    }
  } catch (err) {
    console.error('❌ Error storing plan data:', err);
  }

  const modal = document.createElement('div');
  modal.id = 'loginPromptModal';
  modal.className = 'save-modal';
  
  modal.innerHTML = `
    <div class="save-modal-content">
      <div class="save-modal-header">
        <h2>🔐 Login Required</h2>
        <button class="modal-close" onclick="closeLoginPrompt()">✕</button>
      </div>
      
      <div class="save-modal-body" style="text-align: center; padding: 30px;">
        <div style="font-size: 64px; margin-bottom: 20px;">📚</div>
        <h3 style="margin-bottom: 12px;">Save Your Learning Journey</h3>
        <p style="color: var(--text-muted); margin-bottom: 24px;">
          Create an account or login to save this plan, track your progress, and access it anytime!
        </p>
        <p style="color: var(--brand); font-size: 14px;">
          ✓ Your plan will be automatically saved after login
        </p>
      </div>

      <div class="save-modal-footer">
        <button class="btn btn-secondary" onclick="closeLoginPrompt()">Maybe Later</button>
        <a href="register.php?redirect=recommender.php" class="btn btn-primary">Create Account</a>
        <a href="login.php?redirect=recommender.php" class="btn">Login</a>
      </div>
    </div>
  `;

  document.body.appendChild(modal);
}

function closeLoginPrompt() {
  const modal = document.getElementById('loginPromptModal');
  if (modal) modal.remove();
  
  // Re-enable save button
  const saveBtn = document.getElementById('savePlanBtn');
  if (saveBtn) {
    saveBtn.disabled = false;
    saveBtn.innerHTML = '💾 Save This Plan';
  }
}

function showSuccessMessage(message) {
  const toast = document.createElement('div');
  toast.className = 'success-toast';
  toast.innerHTML = `✓ ${message}`;
  document.body.appendChild(toast);
  
  setTimeout(() => toast.classList.add('show'), 100);
  setTimeout(() => {
    toast.classList.remove('show');
    setTimeout(() => toast.remove(), 300);
  }, 3000);
}

// ---------- Render result cards with CLEAN structure ----------
function renderResult(payload) {
  resultBox.innerHTML = "";

  const summary = payload.summary || "Personalized plan generated by AI.";
  const list = Array.isArray(payload.list) ? payload.list : [];

  // Summary header
  const summaryEl = document.createElement("div");
  summaryEl.className = "plan-summary-header";
  
  // Calculate stats
  const totalSteps = list.length;
  const totalTopics = list.reduce((sum, step) => {
    const topics = step.topics || [];
    return sum + topics.length;
  }, 0);
  
  // Get today's date and target completion date
  const today = new Date();
  const todayStr = today.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  
  // Calculate target date (estimate from first step's duration or default to 6 months)
  const targetDate = new Date();
  targetDate.setMonth(targetDate.getMonth() + 6); // Default 6 months
  const targetStr = targetDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  
  summaryEl.innerHTML = `
    <h2>🚀 Your Path to Achieving Your Goal</h2>
    
    <div class="summary-meta-grid">
      <div class="summary-meta-item">
        <span class="meta-label">Start Date</span>
        <span class="meta-value">${todayStr}</span>
      </div>
      <div class="summary-meta-item">
        <span class="meta-label">Target Completion</span>
        <span class="meta-value">${targetStr}</span>
      </div>
      <div class="summary-meta-item">
        <span class="meta-label">Weekly Commitment</span>
        <span class="meta-value">${currentFormData.hours || '3-5'} hours</span>
      </div>
    </div>
    
    <div class="summary-included">
      <h3>📚 What's Included:</h3>
      <ul class="included-list">
        <li><strong>${totalSteps}</strong> Progressive Learning Steps</li>
        <li><strong>${totalTopics}</strong> Hands-on Topics</li>
        <li><strong>${totalTopics}</strong> Video Tutorials</li>
        <li><strong>Real-world</strong> Projects</li>
      </ul>
    </div>
    
    <p class="summary-description">${summary}</p>
  `;
  resultBox.appendChild(summaryEl);

  if (!list.length) {
    resultBox.innerHTML += `<div class="card"><p class="helper">No steps returned.</p></div>`;
    return;
  }

  list.forEach((step, i) => {
    const progressPercent = ((i + 1) / totalSteps) * 100;
    const gradientStart = `hsl(${220 + progressPercent / 2}, 100%, 60%)`;
    const gradientEnd = `hsl(${280 + progressPercent / 2}, 100%, 65%)`;

    const card = document.createElement("div");
    card.className = "plan-card";
    card.style.marginBottom = "24px";
    
    // Build topics HTML
    let topicsHTML = '';
    if (Array.isArray(step.topics) && step.topics.length > 0) {
      topicsHTML = `
        <div class="topics-section">
          <h4>📚 What You'll Learn</h4>
          <ul class="topics-list">
            ${step.topics.map(topic => {
              // Handle both old format (string) and new format (object with video)
              const topicText = typeof topic === 'string' ? topic : topic.text;
              const video = typeof topic === 'object' && topic.video ? topic.video : null;
              
              return `
                <li class="topic-item">
                  <div class="topic-content">
                    <span class="topic-text">${topicText}</span>
                    ${video ? `
                      <a href="${video.url}" target="_blank" rel="noopener noreferrer" class="topic-video-link">
                        <span class="video-icon">📺</span>
                        <span class="video-details">
                          <span class="video-mini-title">${video.title}</span>
                          <span class="video-mini-channel">${video.channel}</span>
                        </span>
                        <span class="play-mini-icon">▶</span>
                      </a>
                    ` : ''}
                  </div>
                </li>
              `;
            }).join('')}
          </ul>
        </div>
      `;
    }

    // Build resources HTML
    let resourcesHTML = '';
    if (Array.isArray(step.resources) && step.resources.length > 0) {
      resourcesHTML = `
        <div class="resources-section">
          <h4>🔗 Recommended Resources</h4>
          <ul class="resources-list">
            ${step.resources.map(r => `
              <li>
                <a href="${r.url}" target="_blank" rel="noopener noreferrer" class="resource-link">
                  ${r.name}
                  <span class="external-icon">↗</span>
                </a>
              </li>
            `).join('')}
          </ul>
        </div>
      `;
    }

    // Build duration badge
    const durationBadge = step.duration 
      ? `<span class="duration-badge">⏱️ ${step.duration}</span>` 
      : '';

    card.innerHTML = `
      <div class="card-header">
        <span class="step-number">${i + 1}</span>
        ${durationBadge}
        <h3>${step.title || "Course / Step"}</h3>
      </div>

      <div class="plan-meta">
        <span class="level-badge">${step.level || "Any"}</span>
        <span class="category-badge">${step.category || "General"}</span>
      </div>

      <div class="progress-bar goal-progress">
        <div class="progress"
             data-width="${progressPercent}"
             style="width:0%;
                    background: linear-gradient(90deg, ${gradientStart}, ${gradientEnd});
                    transition: width 1s cubic-bezier(0.4, 0, 0.2, 1);">
        </div>
      </div>

      <div class="plan-reason">${step.reason || ""}</div>
      
      ${step.outline ? `<div class="plan-outline">${step.outline}</div>` : ''}

      <button class="toggle-btn">Show Details ▽</button>
      
      <div class="plan-details">
        ${topicsHTML}
        ${resourcesHTML}
      </div>
    `;
    
    resultBox.appendChild(card);
  });

  // Animate progress bars with scroll observer
  setTimeout(() => {
    const bars = document.querySelectorAll(".progress");
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            const bar = entry.target;
            const targetWidth = bar.getAttribute("data-width");
            const index = Array.from(bars).indexOf(bar);
            const card = bar.closest(".plan-card");

            setTimeout(() => {
              bar.style.width = targetWidth + "%";
              bar.addEventListener(
                "transitionend",
                () => {
                  bar.classList.add("bloom-animate", "neon-pulse");
                  card.classList.add("card-pulse");
                  setTimeout(() => {
                    bar.classList.remove("bloom-animate", "neon-pulse");
                    card.classList.remove("card-pulse");
                  }, 1400);
                },
                { once: true }
              );
            }, index * 400);

            observer.unobserve(bar);
          }
        });
      },
      { threshold: 0.35 }
    );
    bars.forEach((bar) => observer.observe(bar));
  }, 300);

  // Toggle expand/collapse
  document.querySelectorAll(".toggle-btn").forEach((btn) => {
    btn.addEventListener("click", (e) => {
      const card = e.target.closest(".plan-card");
      const isOpen = card.classList.toggle("open");
      btn.textContent = isOpen ? "Hide Details ▲" : "Show Details ▽";
    });
  });
}

// Make functions global
window.resetForm = resetForm;
window.handleSavePlan = handleSavePlan;
window.closeSaveModal = closeSaveModal;
window.confirmSavePlan = confirmSavePlan;
window.closeLoginPrompt = closeLoginPrompt;
window.dismissPendingPlan = dismissPendingPlan;
window.proceedToSavePlan = proceedToSavePlan;