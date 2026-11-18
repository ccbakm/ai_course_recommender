<?php 
require_once __DIR__.'/config.php';
require_auth();

$user = get_user_data();

// Fetch user's learning plans
$stmt = $db->prepare("
    SELECT lp.*
    FROM learning_plans lp
    WHERE lp.user_id = ?
    ORDER BY lp.is_active DESC, lp.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$plans = $stmt->fetchAll();

// Calculate stats for each plan
foreach ($plans as &$plan) {
    $plan_data = json_decode($plan['plan_data'], true);
    
    // Count actual topics in the plan
    $total_topics = 0;
    if (isset($plan_data['list']) && is_array($plan_data['list'])) {
        foreach ($plan_data['list'] as $step) {
            if (isset($step['topics']) && is_array($step['topics'])) {
                $total_topics += count($step['topics']);
            }
        }
    }
    
    // Get completed count from progress table
    $stmt_progress = $db->prepare("
        SELECT COUNT(*) as completed
        FROM progress
        WHERE plan_id = ? AND completed = 1
    ");
    $stmt_progress->execute([$plan['id']]);
    $progress_result = $stmt_progress->fetch();
    $completed_topics = $progress_result['completed'];
    
    $plan['total_topics'] = $total_topics;
    $plan['completed_topics'] = $completed_topics;
}
unset($plan);

// Calculate total stats
$total_plans = count($plans);
$total_topics = array_sum(array_column($plans, 'total_topics'));
$completed_topics = array_sum(array_column($plans, 'completed_topics'));
$completion_rate = $total_topics > 0 ? round(($completed_topics / $total_topics) * 100, 1) : 0;

include __DIR__.'/partials/header.php';
?>

<section class="my-plans-section">
    <div class="plans-container">
        <!-- Header -->
        <div class="plans-header">
            <div>
                <h1>My Learning Plans</h1>
                <p class="subtitle">Track your progress and manage your learning journey</p>
            </div>
            <a href="<?php echo url_path('/recommender.php'); ?>" class="btn">
                ➕ Create New Plan
            </a>
        </div>

        <!-- Stats Overview -->
        <div class="stats-grid">
            <div class="stat-card card">
                <div class="stat-icon">📚</div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $total_plans; ?></div>
                    <div class="stat-label">Total Plans</div>
                </div>
            </div>
            <div class="stat-card card">
                <div class="stat-icon">🎯</div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $total_topics; ?></div>
                    <div class="stat-label">Total Topics</div>
                </div>
            </div>
            <div class="stat-card card">
                <div class="stat-icon">✅</div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $completed_topics; ?></div>
                    <div class="stat-label">Completed</div>
                </div>
            </div>
            <div class="stat-card card">
                <div class="stat-icon">📊</div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $completion_rate; ?>%</div>
                    <div class="stat-label">Completion Rate</div>
                </div>
            </div>
        </div>

        <!-- Plans List -->
        <?php if (empty($plans)): ?>
            <div class="empty-state card">
                <div class="empty-icon">📭</div>
                <h3>No Learning Plans Yet</h3>
                <p>Create your first personalized learning plan to get started!</p>
                <a href="<?php echo url_path('/recommender.php'); ?>" class="btn btn-primary">
                    Create Your First Plan
                </a>
            </div>
        <?php else: ?>

<!-- Search learning plans -->
<div class="plans-search">
    <label for="plan-search">Search Learning Plans</label>


<div class="search-input-wrapper">
    <span class="search-icon" aria-hidden="true">🔍</span>

    <input type="search"
           id="plan-search"
           placeholder="Search by plan title or domain..."
           autocomplete="off">

    <!-- Clear Search Button -->
    <button type="button"
            id="clear-plan-search"
            class="clear-search-btn"
            aria-label="Clear search"
            hidden>
        <svg viewBox="0 0 24 24"
             width="18" height="18"
             fill="none"
             stroke="currentColor"
             stroke-width="2"
             stroke-linecap="round">
            <path d="M18 6L6 18M6 6l12 12"/>
        </svg>
    </button>
</div>
</div> <!-- Close plans-search, keeping cards outside the search container -->

            <div class="plans-list">
                <?php foreach ($plans as $plan): 
                    $plan_data = json_decode($plan['plan_data'], true);
                    $progress_percent = $plan['total_topics'] > 0 
                        ? round(($plan['completed_topics'] / $plan['total_topics']) * 100, 1) 
                        : 0;
                ?>
                    <div class="plan-card card <?php echo $plan['is_active'] ? 'active-plan' : ''; ?>">
                        <div class="plan-card-header">
                            <div class="plan-title-section">
                                <?php if ($plan['is_active']): ?>
                                    <span class="active-badge">🔥 Active</span>
                                <?php endif; ?>
                                <h3><?php echo e($plan['title']); ?></h3>
                                <div class="plan-meta">
                                    <span class="meta-item">🎯 <?php echo e($plan['domain']); ?></span>
                                    <span class="meta-item">📊 <?php echo e($plan['skill_level']); ?></span>
                                    <span class="meta-item">⏰ <?php echo e($plan['weekly_hours']); ?> hrs/week</span>
                                </div>
                            </div>
                            <div class="plan-actions">
                                <button class="icon-btn" onclick="viewPlan(<?php echo $plan['id']; ?>)" title="View Plan">
                                    👁️
                                </button>
                                
<button class="icon-btn"
        onclick="toggleActive(<?php echo $plan['id']; ?>, <?php echo $plan['is_active'] ? 'true' : 'false'; ?>)"
        title="<?php echo $plan['is_active'] ? 'Deactivate' : 'Set as Active'; ?>">
    <?php echo $plan['is_active'] ? '⭐' : '☆'; ?>
</button>

                                <button class="icon-btn danger" onclick="deletePlan(<?php echo $plan['id']; ?>)" title="Delete Plan">
                                    🗑️
                                </button>
                            </div>
                        </div>

                        <div class="plan-goal">
                            <strong>Goal:</strong> <?php echo e($plan['goal']); ?>
                        </div>

                        <div class="plan-progress">
                            <div class="progress-header">
                                <span>Progress</span>
                                <span><?php echo $plan['completed_topics']; ?> / <?php echo $plan['total_topics']; ?> topics</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress" style="width: <?php echo $progress_percent; ?>%"></div>
                            </div>
                            <div class="progress-text"><?php echo $progress_percent; ?>% Complete</div>
                        </div>

                        <div class="plan-footer">
                            <span class="plan-date">Created <?php echo date('M j, Y', strtotime($plan['created_at'])); ?></span>
                            <div style="display:flex;gap:8px;">
                                <a href="<?php echo url_path('/dashboard.php?plan_id=' . $plan['id']); ?>" class="btn btn-sm" style="background:transparent;border:1px solid var(--brand);color:var(--brand);box-shadow:none;">
                                    📊 Analytics
                                </a>
                                <a href="<?php echo url_path('/plan-details.php?id=' . $plan['id']); ?>" class="btn btn-sm">
                                    View Details →
                                </a>
                            </div>
                        </div>
                    </div>
                
                <?php endforeach; ?>
            </div>

            <!-- Message displayed when search finds no plans -->
            <div id="search-no-results"
                 class="empty-state card"
                 style="display: none;"
                 role="status"
                 aria-live="polite">

                <div class="empty-icon">🔍</div>
                <h3>No Matching Plans Found</h3>
                <p>Try a different search term to find your learning plans.</p>

            </div>

        <?php endif; ?>

    </div>
</section>

<style>
.my-plans-section {
    padding: 100px 20px 40px;
    min-height: calc(100vh - 60px);
}

.plans-container {
    max-width: 1200px;
    margin: 0 auto;
}

.plans-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 40px;
    gap: 20px;
}

.plans-header h1 {
    margin-bottom: 8px;
}

.subtitle {
    color: var(--text-muted);
    font-size: 14px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

.stat-card {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px;
}

.stat-icon {
    font-size: 32px;
}

.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1;
}

.stat-label {
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 4px;
}

.empty-state {
    text-align: center;
    padding: 60px 40px;
}

.empty-icon {
    font-size: 64px;
    margin-bottom: 16px;
}

.empty-state h3 {
    margin-bottom: 8px;
}

.empty-state p {
    color: var(--text-muted);
    margin-bottom: 24px;
}


/* Learning Plans Search */

.plans-search {
    margin-bottom: 28px;
}


.plans-search label {
    display: block;
    margin-bottom: 10px;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
}


.search-input-wrapper {
    position: relative;
    max-width: 500px;
}


.search-input-wrapper input {
    width: 100%;
    padding: 14px 18px 14px 48px;
    background: var(--bg-darker);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    color: var(--text-primary);
    font-size: 14px;
    outline: none;
    transition: all 0.3s ease;
}

.search-input-wrapper input:focus {
    border-color: var(--brand);
    box-shadow: 0 0 0 3px rgba(124, 77, 255, 0.15);
}

.search-input-wrapper input::placeholder {
    color: var(--text-muted);
}

.search-icon {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 18px;
    pointer-events: none;
}


/* Clear Search Button */
.clear-search-btn {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    width: 30px;
    height: 30px;
    border: none;
    border-radius: 50%;
    background: transparent;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
}

.clear-search-btn:hover {
    background: rgba(124, 77, 255, 0.15);
    color: var(--brand);
}

.clear-search-btn:focus-visible {
    outline: 2px solid var(--brand);
    outline-offset: 2px;
}

/* Keep the button hidden when search is empty */
.clear-search-btn[hidden] {
    display: none;
}

/* Leave space inside the input for the button */
.search-input-wrapper input {
    padding-right: 50px;
}

/* Hide the browser's default clear button */
.search-input-wrapper input::-webkit-search-cancel-button {
    display: none;
}



.plans-list {
    display: grid;
    gap: 24px;
}

.plan-card {
    padding: 24px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.plan-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(124, 77, 255, 0.15);
}

.active-plan {
    border: 2px solid var(--brand);
    background: linear-gradient(135deg, rgba(124, 77, 255, 0.05) 0%, rgba(0, 229, 255, 0.03) 100%);
}

.plan-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 16px;
    gap: 20px;
}

.plan-title-section h3 {
    margin: 8px 0 12px;
}

.active-badge {
    display: inline-block;
    padding: 4px 12px;
    background: linear-gradient(90deg, var(--brand), var(--brand2));
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    color: white;
}

.plan-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}

.meta-item {
    font-size: 13px;
    color: var(--text-muted);
}

.plan-actions {
    display: flex;
    gap: 8px;
}

.icon-btn {
    width: 36px;
    height: 36px;
    border: 1px solid var(--border-color);
    background: var(--bg-darker);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 16px;
}

.icon-btn:hover {
    background: var(--bg-card);
    border-color: var(--brand);
}

.icon-btn.danger:hover {
    background: rgba(255, 68, 68, 0.1);
    border-color: #ff4444;
}

.plan-goal {
    padding: 12px;
    background: var(--bg-darker);
    border-radius: 8px;
    font-size: 14px;
    margin-bottom: 16px;
    color: var(--text-secondary);
}

.plan-progress {
    margin-bottom: 20px;
}

.progress-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    font-size: 13px;
    color: var(--text-muted);
}

.progress-bar {
    height: 8px;
    background: var(--bg-darker);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 4px;
}

.progress {
    height: 100%;
    background: linear-gradient(90deg, var(--brand), var(--brand2));
    transition: width 0.5s ease;
}

.progress-text {
    font-size: 12px;
    color: var(--text-muted);
    text-align: right;
}

.plan-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 16px;
    border-top: 1px solid var(--border-color);
}

.plan-date {
    font-size: 12px;
    color: var(--text-muted);
}

.btn-sm {
    padding: 8px 16px;
    font-size: 13px;
}

@media (max-width: 768px) {
    .plans-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .plan-card-header {
        flex-direction: column;
    }
    
    .plan-actions {
        width: 100%;
        justify-content: flex-end;
    }
}
</style>

<script>
// ===== STAT COUNTER ANIMATION =====
function animateCounter(el, duration = 1400) {
    const raw = el.textContent.trim();
    const suffix = raw.replace(/[\d.]/g, '');
    const target = parseFloat(raw);
    if (isNaN(target) || target === 0) return;
    const isFloat = raw.includes('.');
    const start = performance.now();
    el.textContent = '0' + suffix;
    const update = (now) => {
        const progress = Math.min((now - start) / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        const current = isFloat ? (eased * target).toFixed(1) : Math.round(eased * target);
        el.textContent = current + suffix;
        if (progress < 1) requestAnimationFrame(update);
    };
    requestAnimationFrame(update);
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.stat-value').forEach(el => animateCounter(el));
});

function viewPlan(planId) {
    window.location.href = '<?php echo url_path('/plan-details.php?id='); ?>' + planId;
}


function toggleActive(planId, isActive) {
    // Show the correct confirmation message
    const message = isActive
        ? 'Are you sure you want to deactivate this learning plan?'
        : 'Set this plan as your active learning plan?';

    if (!confirm(message)) {
        return;
    }

    fetch('<?php echo url_path('/api/toggle-active.php'); ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ plan_id: planId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Failed to update plan');
        }
    })
    .catch(error => {
        console.error('Error updating plan:', error);
        alert('Something went wrong. Please try again.');
    });
}


function deletePlan(planId) {
    if (confirm('Are you sure you want to delete this learning plan? This action cannot be undone.')) {
        fetch('<?php echo url_path('/api/delete-plan.php'); ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ plan_id: planId })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.error || 'Failed to delete plan');
            }
        });
    }
}




/* Live search for saved learning plans */
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('plan-search');
    const clearButton = document.getElementById('clear-plan-search');
    const planCards = document.querySelectorAll('.plans-list .plan-card');
    const noResults = document.getElementById('search-no-results');

    if (!searchInput || !clearButton) return;

    function filterPlans() {
        const searchText = searchInput.value.toLowerCase().trim();
        let visibleCount = 0;

        planCards.forEach(function (card) {
            const title = card.querySelector('.plan-title-section h3')
                              .textContent.toLowerCase();

            const domain = card.querySelector('.plan-meta .meta-item')
                               .textContent.toLowerCase();

            const matches = title.includes(searchText) ||
                            domain.includes(searchText);

            card.style.display = matches ? '' : 'none';

            if (matches) {
                visibleCount++;
            }
        });

        // Display message when no plans match
        if (noResults) {
            noResults.style.display =
                visibleCount === 0 ? 'block' : 'none';
        }

        // Show clear button only when search contains text
        clearButton.hidden = searchInput.value.length === 0;
    }

    // Filter plans as the user types
    searchInput.addEventListener('input', filterPlans);

    // Clear search when the X button is clicked
    clearButton.addEventListener('click', function () {
        searchInput.value = '';
        filterPlans();
        searchInput.focus();
    });

    // Initialize the search state
    filterPlans();
});



</script>

<?php include __DIR__.'/partials/footer.php'; ?>