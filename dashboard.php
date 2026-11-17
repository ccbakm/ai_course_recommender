<?php
require_once __DIR__ . '/config.php';
require_auth();

$plan_id = intval($_GET['plan_id'] ?? 0);
if (!$plan_id) redirect('/my-plans.php', 'No plan selected', 'error');

// Verify plan belongs to this user
$stmt = $db->prepare("SELECT title FROM learning_plans WHERE id = ? AND user_id = ?");
$stmt->execute([$plan_id, $_SESSION['user_id']]);
$plan = $stmt->fetch();
if (!$plan) redirect('/my-plans.php', 'Plan not found', 'error');

include __DIR__ . '/partials/header.php';
?>

<style>
.dash-section {
    padding: 100px 20px 60px;
    min-height: calc(100vh - 60px);
}

.dash-container { max-width: 1200px; margin: 0 auto; }

.dash-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 36px;
    gap: 20px;
    flex-wrap: wrap;
}

.dash-header-text h1 { margin-bottom: 6px; }
.dash-header-text p  { color: var(--text-muted); font-size: 14px; }

/* ── Stat cards ── */
.dash-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
}

.dash-stat {
    display: flex;
    align-items: center;
    gap: 18px;
    padding: 22px 24px;
}

.dash-stat-icon {
    font-size: 28px;
    width: 52px;
    height: 52px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(124, 77, 255, 0.1);
    flex-shrink: 0;
}

.dash-stat-value {
    font-size: 30px;
    font-weight: 800;
    background: linear-gradient(90deg, var(--brand), var(--brand2));
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    line-height: 1.1;
}

.dash-stat-label { font-size: 13px; color: var(--text-muted); margin-top: 4px; }

/* ── Chart layout ── */
.dash-charts-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.dash-chart-card { padding: 24px; }
.dash-chart-card h3 { font-size: 16px; margin-bottom: 4px; }
.chart-sub { font-size: 13px; color: var(--text-muted); margin-bottom: 20px; }

.chart-wrap      { position: relative; height: 240px; }
.chart-wrap-tall { position: relative; height: 300px; }

.chart-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 200px;
    color: var(--text-muted);
    font-size: 14px;
    gap: 8px;
}
.chart-empty span { font-size: 32px; }

/* ── Full-width card ── */
.dash-full-card { padding: 24px; margin-bottom: 20px; }
.dash-full-card h3 { font-size: 16px; margin-bottom: 4px; }

/* ── Weak area bars ── */
.weak-bar-list { display: grid; gap: 12px; margin-top: 20px; }

.weak-bar-item {
    display: grid;
    grid-template-columns: 210px 1fr 56px;
    align-items: center;
    gap: 14px;
}

.weak-bar-label {
    font-size: 13px;
    color: var(--text-secondary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.weak-bar-track {
    height: 10px;
    background: rgba(255, 255, 255, 0.06);
    border-radius: 6px;
    overflow: hidden;
}

.weak-bar-fill {
    height: 100%;
    border-radius: 6px;
    transition: width 1s cubic-bezier(0.4, 0, 0.2, 1);
}

.weak-bar-pct { font-size: 13px; font-weight: 700; text-align: right; }

/* ── Quiz type circles ── */
.type-avg-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-top: 20px;
}

.type-avg-item {
    text-align: center;
    padding: 18px 12px;
    background: var(--bg-darker);
    border-radius: 10px;
}

.type-avg-circle {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    border: 4px solid;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    margin: 0 auto 12px;
}

.type-avg-num   { font-size: 20px; font-weight: 800; line-height: 1; }
.type-avg-sym   { font-size: 11px; opacity: 0.7; }
.type-avg-label { font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }

/* ── Insights panel ── */
.insights-panel { padding: 28px; margin-bottom: 20px; }

.insights-panel-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
    gap: 16px;
    flex-wrap: wrap;
}

.insights-panel-header h3 { margin-bottom: 4px; }
.insights-panel-header p  { font-size: 13px; color: var(--text-muted); }

#generateInsightsBtn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    background: linear-gradient(135deg, var(--brand), var(--brand2));
    color: white;
    transition: all 0.2s;
    white-space: nowrap;
    flex-shrink: 0;
}

#generateInsightsBtn:hover    { transform: translateY(-1px); box-shadow: 0 4px 16px rgba(124,77,255,0.4); }
#generateInsightsBtn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

.insights-placeholder {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-muted);
    font-size: 14px;
    line-height: 1.7;
}

.insights-placeholder-icon { font-size: 40px; margin-bottom: 12px; }

.insights-loading {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 28px;
    color: var(--text-muted);
    font-size: 14px;
}

.insights-spinner {
    width: 28px;
    height: 28px;
    border: 3px solid var(--border-color);
    border-top-color: var(--brand);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    flex-shrink: 0;
}

@keyframes spin { to { transform: rotate(360deg); } }

/* Insight result blocks */
.insights-result { display: grid; gap: 20px; }

.insight-box {
    padding: 18px 20px;
    border-radius: 10px;
    background: rgba(124, 77, 255, 0.06);
    border: 1px solid rgba(124, 77, 255, 0.15);
    font-size: 15px;
    color: var(--text-secondary);
    line-height: 1.7;
}

.insight-box.strength {
    background: rgba(34, 197, 94, 0.06);
    border-color: rgba(34, 197, 94, 0.2);
}

.insight-box.recommendation {
    background: rgba(0, 229, 255, 0.06);
    border-color: rgba(0, 229, 255, 0.2);
}

.insight-box-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: var(--brand);
    margin-bottom: 8px;
}

.insight-box.strength        .insight-box-label { color: #22c55e; }
.insight-box.recommendation  .insight-box-label { color: var(--brand2); }

.weak-items-list { display: grid; gap: 12px; }

.weak-item {
    padding: 16px 18px;
    background: var(--bg-darker);
    border-radius: 10px;
    border-left: 3px solid;
    display: grid;
    gap: 6px;
}

.weak-item-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
}

.weak-item-name { font-size: 14px; font-weight: 600; color: var(--text-primary); }

.weak-item-badge {
    font-size: 12px;
    font-weight: 700;
    padding: 2px 10px;
    border-radius: 20px;
    white-space: nowrap;
}

.weak-item-issue { font-size: 13px; color: var(--text-muted); line-height: 1.5; }

.weak-item-tip {
    font-size: 13px;
    color: var(--brand2);
    display: flex;
    align-items: flex-start;
    gap: 6px;
}

.weak-item-tip::before { content: '💡'; flex-shrink: 0; }

.no-data-msg {
    text-align: center;
    padding: 32px;
    color: var(--text-muted);
    font-size: 14px;
}

@media (max-width: 768px) {
    .dash-charts-row  { grid-template-columns: 1fr; }
    .type-avg-grid    { gap: 10px; }
    .weak-bar-item    { grid-template-columns: 120px 1fr 46px; }
    .dash-header      { flex-direction: column; }
    #areasGrid        { grid-template-columns: 1fr !important; }
}
</style>

<section class="dash-section">
<div class="dash-container">

    <div class="dash-header">
        <div class="dash-header-text">
            <h1 id="planTitleHeading">Analytics</h1>
            <p id="planTitleSub">Loading plan data…</p>
        </div>
        <a href="<?php echo url_path('/plan-details.php?id=' . $plan_id); ?>" class="btn" style="padding:10px 20px;">
            ← Back to Plan
        </a>
    </div>

    <!-- Stats -->
    <div class="dash-stats">
        <div class="card dash-stat">
            <div class="dash-stat-icon">✅</div>
            <div>
                <div class="dash-stat-value" id="statTopics">–</div>
                <div class="dash-stat-label">Topics Completed</div>
            </div>
        </div>
        <div class="card dash-stat">
            <div class="dash-stat-icon">🧠</div>
            <div>
                <div class="dash-stat-value" id="statQuizzes">–</div>
                <div class="dash-stat-label">Quizzes Taken</div>
            </div>
        </div>
        <div class="card dash-stat">
            <div class="dash-stat-icon">📊</div>
            <div>
                <div class="dash-stat-value" id="statAvg">–</div>
                <div class="dash-stat-label">Avg Quiz Score</div>
            </div>
        </div>
        <div class="card dash-stat">
            <div class="dash-stat-icon">🔥</div>
            <div>
                <div class="dash-stat-value" id="statStreak">–</div>
                <div class="dash-stat-label">Day Streak</div>
            </div>
        </div>
    </div>

    <!-- Charts row -->
    <div class="dash-charts-row">
        <div class="card dash-chart-card">
            <h3>Topics Completed</h3>
            <p class="chart-sub">Weekly activity — last 8 weeks</p>
            <div class="chart-wrap">
                <canvas id="weeklyChart"></canvas>
            </div>
        </div>
        <div class="card dash-chart-card">
            <h3>Quiz Score Trend</h3>
            <p class="chart-sub">All attempts over time</p>
            <div class="chart-wrap" id="scoreTrendWrap">
                <canvas id="scoreTrendChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Quiz type performance -->
    <div class="card dash-full-card">
        <h3>Performance by Quiz Type</h3>
        <p class="chart-sub">Average score per quiz level</p>
        <div class="type-avg-grid">
            <div class="type-avg-item">
                <div class="type-avg-circle" style="border-color:#7c4dff;color:#7c4dff;">
                    <span class="type-avg-num" id="avgTopic">–</span>
                    <span class="type-avg-sym">%</span>
                </div>
                <div class="type-avg-label">Topic Quizzes</div>
            </div>
            <div class="type-avg-item">
                <div class="type-avg-circle" style="border-color:#00e5ff;color:#00e5ff;">
                    <span class="type-avg-num" id="avgStep">–</span>
                    <span class="type-avg-sym">%</span>
                </div>
                <div class="type-avg-label">Step Quizzes</div>
            </div>
            <div class="type-avg-item">
                <div class="type-avg-circle" style="border-color:#f59e0b;color:#f59e0b;">
                    <span class="type-avg-num" id="avgPlan">–</span>
                    <span class="type-avg-sym">%</span>
                </div>
                <div class="type-avg-label">Final Exams</div>
            </div>
        </div>
    </div>

    <!-- Weak & Strong areas side by side -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;" id="areasGrid">
        <div class="card dash-full-card" style="margin-bottom:0;">
            <h3>⚠️ Weak Areas</h3>
            <p class="chart-sub">Based on every question ever answered — including retakes &amp; regenerated quizzes</p>
            <div id="weakAreasList" class="weak-bar-list"></div>
        </div>
        <div class="card dash-full-card" style="margin-bottom:0;">
            <h3>✅ Strong Areas</h3>
            <p class="chart-sub">Areas where you consistently get questions right</p>
            <div id="strongAreasList" class="weak-bar-list"></div>
        </div>
    </div>

    <!-- Learning Insights -->
    <div class="card insights-panel">
        <div class="insights-panel-header">
            <div>
                <h3>Learning Insights</h3>
                <p>Analyses your quiz patterns to explain your weak spots and give personalised advice</p>
            </div>
            <button id="generateInsightsBtn" disabled>Generate Insights</button>
        </div>
        <div id="insightsContent">
            <div class="insights-placeholder">
                <div class="insights-placeholder-icon">📡</div>
                Loading your data…
            </div>
        </div>
    </div>

</div>
</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
const PLAN_ID = <?php echo $plan_id; ?>;
const BRAND   = '#7c4dff';
const CYAN    = '#00e5ff';
const AMBER   = '#f59e0b';
const MUTED   = '#94a3b8';
const GRID    = 'rgba(255,255,255,0.05)';

Chart.defaults.color       = MUTED;
Chart.defaults.borderColor = GRID;
Chart.defaults.font.family = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";

function countUp(el, target, suffix = '', duration = 1200) {
    const isFloat = !Number.isInteger(target);
    const start   = performance.now();
    const run = (now) => {
        const p = Math.min((now - start) / duration, 1);
        const e = 1 - Math.pow(1 - p, 3);
        el.textContent = (isFloat ? (e * target).toFixed(1) : Math.round(e * target)) + suffix;
        if (p < 1) requestAnimationFrame(run);
    };
    requestAnimationFrame(run);
}

function wrongColor(pct) {
    if (pct >= 70) return '#ef4444';
    if (pct >= 50) return '#f59e0b';
    if (pct >= 30) return '#7c4dff';
    return '#22c55e';
}

function renderAreaBars(containerId, areas, valueKey, color, emptyMsg) {
    const el = document.getElementById(containerId);
    if (!areas || areas.length === 0) {
        el.innerHTML = `<div class="no-data-msg">${emptyMsg}</div>`;
        return;
    }
    el.innerHTML = areas.map(a => {
        const pct = a[valueKey];
        const c   = typeof color === 'function' ? color(a.wrong_pct) : color;
        return `
        <div class="weak-bar-item">
            <div class="weak-bar-label" title="${a.label}">${a.label}</div>
            <div class="weak-bar-track">
                <div class="weak-bar-fill" style="width:0%;background:${c};" data-pct="${pct}"></div>
            </div>
            <div class="weak-bar-pct" style="color:${c};">${pct}%</div>
        </div>`;
    }).join('');
    setTimeout(() => {
        el.querySelectorAll('.weak-bar-fill').forEach(b => b.style.width = b.dataset.pct + '%');
    }, 80);
}

function renderInsights(analysis) {
    const box = document.getElementById('insightsContent');
    if (!analysis) {
        box.innerHTML = `<div class="insights-placeholder">
            <div class="insights-placeholder-icon">📊</div>
            Not enough quiz data yet. Take a few more quizzes and try again.
        </div>`;
        return;
    }

    const weakHtml = (analysis.weak_areas || []).map(w => {
        const c = wrongColor(w.wrong_pct || 50);
        return `
        <div class="weak-item" style="border-color:${c};">
            <div class="weak-item-top">
                <div class="weak-item-name">${w.area}</div>
                <div class="weak-item-badge" style="background:${c}20;color:${c};">${w.wrong_pct ?? '?'}% wrong</div>
            </div>
            <div class="weak-item-issue">${w.issue}</div>
            <div class="weak-item-tip">${w.tip}</div>
        </div>`;
    }).join('');

    const strongHtml = (analysis.strong_areas || []).map(s => `
        <div class="weak-item" style="border-color:#22c55e;">
            <div class="weak-item-top">
                <div class="weak-item-name">${s.area}</div>
                <div class="weak-item-badge" style="background:#22c55e20;color:#22c55e;">${s.correct_pct ?? '?'}% correct</div>
            </div>
            <div class="weak-item-issue" style="color:#22c55e99;">${s.insight}</div>
        </div>`
    ).join('');

    box.innerHTML = `
    <div class="insights-result">
        <div class="insight-box">
            <div class="insight-box-label">Overview</div>
            ${analysis.overall_insight || ''}
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div>
                <h4 style="font-size:14px;margin-bottom:12px;color:var(--text-primary);">⚠️ Areas to Work On</h4>
                <div class="weak-items-list">${weakHtml || '<p style="color:var(--text-muted);font-size:13px;">No significant weak areas detected.</p>'}</div>
            </div>
            <div>
                <h4 style="font-size:14px;margin-bottom:12px;color:var(--text-primary);">✅ Where You Excel</h4>
                <div class="weak-items-list">${strongHtml || '<p style="color:var(--text-muted);font-size:13px;">Keep answering quizzes to identify strong areas.</p>'}</div>
            </div>
        </div>

        ${analysis.top_recommendation ? `
        <div class="insight-box recommendation">
            <div class="insight-box-label">🎯 What to Do Next</div>
            ${analysis.top_recommendation}
        </div>` : ''}
    </div>`;
}

// ── Load dashboard data ────────────────────────────────────────────────────
let savedWeakAreas   = [];
let savedStrongAreas = [];
let savedStats       = {};

async function loadDashboard() {
    let data;
    try {
        const res = await fetch(`<?php echo url_path('/api/dashboard-data.php'); ?>?plan_id=${PLAN_ID}`);
        data = await res.json();
    } catch {
        document.getElementById('insightsContent').innerHTML =
            '<div class="insights-placeholder">Failed to load data. Please refresh.</div>';
        return;
    }
    if (data.error) {
        document.getElementById('insightsContent').innerHTML =
            `<div class="insights-placeholder">${data.error}</div>`;
        return;
    }

    // Plan title
    document.getElementById('planTitleHeading').textContent = data.plan_title || 'Analytics';
    document.getElementById('planTitleSub').textContent     = 'Plan-specific progress and quiz analysis';

    savedStats       = data.stats        || {};
    savedWeakAreas   = data.weak_areas   || [];
    savedStrongAreas = data.strong_areas || [];

    // Stats
    countUp(document.getElementById('statTopics'),  savedStats.topics_completed || 0);
    countUp(document.getElementById('statQuizzes'), savedStats.quizzes_taken    || 0);
    countUp(document.getElementById('statAvg'),     savedStats.avg_score        || 0, '%');
    countUp(document.getElementById('statStreak'),  savedStats.streak           || 0);

    // Quiz type averages
    const ta = data.quiz_type_avg || {};
    document.getElementById('avgTopic').textContent = ta.topic !== null && ta.topic !== undefined ? ta.topic : '–';
    document.getElementById('avgStep').textContent  = ta.step  !== null && ta.step  !== undefined ? ta.step  : '–';
    document.getElementById('avgPlan').textContent  = ta.plan  !== null && ta.plan  !== undefined ? ta.plan  : '–';

    // Weekly chart
    const weekly = data.weekly_progress || [];
    new Chart(document.getElementById('weeklyChart'), {
        type: 'bar',
        data: {
            labels: weekly.map(w => w.week),
            datasets: [{
                label: 'Topics',
                data: weekly.map(w => w.completed),
                backgroundColor: 'rgba(124,77,255,0.55)',
                borderColor: BRAND,
                borderWidth: 2,
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: GRID }, ticks: { color: MUTED, font: { size: 11 } } },
                y: { grid: { color: GRID }, ticks: { color: MUTED, precision: 0 }, beginAtZero: true }
            }
        }
    });

    // Score trend chart
    const trend  = data.score_trend || [];
    const wrapEl = document.getElementById('scoreTrendWrap');
    if (trend.length === 0) {
        wrapEl.innerHTML = '<div class="chart-empty"><span>📋</span>No quiz attempts yet</div>';
    } else {
        const typeColor = { topic: BRAND, step: CYAN, plan: AMBER };
        new Chart(document.getElementById('scoreTrendChart'), {
            type: 'line',
            data: {
                labels: trend.map(d => d.date),
                datasets: [{
                    label: 'Score %',
                    data: trend.map(d => d.score_pct),
                    borderColor: BRAND,
                    backgroundColor: 'rgba(124,77,255,0.08)',
                    pointBackgroundColor: trend.map(d => typeColor[d.quiz_type] || BRAND),
                    pointBorderColor: 'rgba(0,0,0,0)',
                    pointRadius: 6,
                    pointHoverRadius: 8,
                    tension: 0.35,
                    fill: true,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => {
                                const t = trend[ctx.dataIndex];
                                return ` ${t.score_pct}%  (${t.quiz_type} quiz)`;
                            }
                        }
                    }
                },
                scales: {
                    x: { grid: { color: GRID }, ticks: { color: MUTED, font: { size: 11 } } },
                    y: { grid: { color: GRID }, ticks: { color: MUTED, callback: v => v + '%' }, min: 0, max: 100 }
                }
            }
        });
    }

    // Responsive areas grid on mobile
    if (window.innerWidth < 768) {
        document.getElementById('areasGrid').style.gridTemplateColumns = '1fr';
    }

    // Weak & strong areas bars
    renderAreaBars('weakAreasList',   savedWeakAreas,   'wrong_pct',   wrongColor,  'Take some quizzes to see your weak areas here.');
    renderAreaBars('strongAreasList', savedStrongAreas, 'correct_pct', '#22c55e',   'Keep answering quizzes — strong areas will appear once you have ≥5 answers with ≥75% correct in an area.');

    // Unlock insights button
    const btn = document.getElementById('generateInsightsBtn');
    btn.disabled = false;
    document.getElementById('insightsContent').innerHTML = `
        <div class="insights-placeholder">
            <div class="insights-placeholder-icon">📋</div>
            Click <strong>Generate Insights</strong> to get a breakdown of your weak spots and what to focus on next.
        </div>`;
}

// ── Insights button ────────────────────────────────────────────────────────
document.getElementById('generateInsightsBtn').addEventListener('click', async function () {
    this.disabled    = true;
    this.textContent = 'Analysing…';
    document.getElementById('insightsContent').innerHTML = `
        <div class="insights-loading">
            <div class="insights-spinner"></div>
            Analysing your quiz patterns…
        </div>`;

    try {
        const res = await fetch('<?php echo url_path('/api/analyze-weakpoints.php'); ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ weak_areas: savedWeakAreas, strong_areas: savedStrongAreas, stats: savedStats })
        });
        const data = await res.json();
        if (data.error) throw new Error(data.error);
        renderInsights(data.message === 'not_enough_data' ? null : data.analysis);
    } catch (err) {
        document.getElementById('insightsContent').innerHTML = `
            <div class="insights-placeholder" style="color:#ff6b6b;">
                <div class="insights-placeholder-icon">⚠️</div>
                ${err.message || 'Failed to generate insights. Please try again.'}
            </div>`;
    }

    this.disabled    = false;
    this.textContent = 'Regenerate';
});

loadDashboard();
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
