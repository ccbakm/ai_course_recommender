<?php
require_once __DIR__.'/config.php';
require_auth();

$plan_id = intval($_GET['id'] ?? 0);
if (!$plan_id) redirect('/my-plans.php', 'Invalid plan ID', 'error');

$stmt = $db->prepare("SELECT lp.* FROM learning_plans lp WHERE lp.id = ? AND lp.user_id = ?");
$stmt->execute([$plan_id, $_SESSION['user_id']]);
$plan = $stmt->fetch();
if (!$plan) redirect('/my-plans.php', 'Plan not found', 'error');

// Progress
$stmt = $db->prepare("SELECT step_index, topic_index, completed FROM progress WHERE plan_id = ? AND user_id = ?");
$stmt->execute([$plan_id, $_SESSION['user_id']]);
$progress_records = $stmt->fetchAll();

$progress_map = [];
foreach ($progress_records as $record) {
    $progress_map[$record['step_index'] . '_' . $record['topic_index']] = (bool)$record['completed'];
}

$plan_data = json_decode($plan['plan_data'], true);
$total_topics = 0;
$completed_topics = 0;

if (isset($plan_data['list'])) {
    foreach ($plan_data['list'] as $si => $step) {
        foreach (($step['topics'] ?? []) as $ti => $t) {
            $total_topics++;
            if (!empty($progress_map["{$si}_{$ti}"])) $completed_topics++;
        }
    }
}

// Clean orphaned progress records
if (!empty($progress_records)) {
    $valid = [];
    foreach (($plan_data['list'] ?? []) as $si => $step) {
        foreach (($step['topics'] ?? []) as $ti => $t) $valid[] = "{$si}_{$ti}";
    }
    foreach ($progress_records as $r) {
        if (!in_array($r['step_index'].'_'.$r['topic_index'], $valid)) {
            $stmt = $db->prepare("DELETE FROM progress WHERE plan_id = ? AND step_index = ? AND topic_index = ?");
            $stmt->execute([$plan_id, $r['step_index'], $r['topic_index']]);
        }
    }
}

$progress_percent = $total_topics > 0 ? round(($completed_topics / $total_topics) * 100, 1) : 0;
$all_done         = ($total_topics > 0 && $completed_topics === $total_topics);

// Notes
$db->exec("CREATE TABLE IF NOT EXISTS topic_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plan_id INT NOT NULL,
    step_index INT NOT NULL,
    topic_index INT NOT NULL,
    note TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_note (user_id, plan_id, step_index, topic_index)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$stmt = $db->prepare("SELECT step_index, topic_index, note FROM topic_notes WHERE plan_id = ? AND user_id = ?");
$stmt->execute([$plan_id, $_SESSION['user_id']]);
$notes_map = [];
foreach ($stmt->fetchAll() as $row) {
    $notes_map[$row['step_index'] . '_' . $row['topic_index']] = $row['note'];
}

// Load all best quiz attempts for this plan
$stmt = $db->prepare("
    SELECT q.quiz_type, q.step_index, q.topic_index, qa.score, qa.total_questions
    FROM quizzes q
    JOIN quiz_attempts qa ON q.id = qa.quiz_id
    WHERE q.plan_id = ? AND qa.user_id = ?
    ORDER BY qa.score DESC
");
$stmt->execute([$plan_id, $_SESSION['user_id']]);
$quiz_map = [];
foreach ($stmt->fetchAll() as $row) {
    if ($row['quiz_type'] === 'plan') {
        if (!isset($quiz_map['plan'])) $quiz_map['plan'] = $row;
    } elseif ($row['quiz_type'] === 'step') {
        $k = 'step_' . $row['step_index'];
        if (!isset($quiz_map[$k])) $quiz_map[$k] = $row;
    } else {
        $k = 'topic_' . $row['step_index'] . '_' . $row['topic_index'];
        if (!isset($quiz_map[$k])) $quiz_map[$k] = $row;
    }
}

include __DIR__.'/partials/header.php';
?>

<style>
/* ===== QUIZ BUTTONS ===== */
.quiz-btn-topic {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;
    cursor: pointer; border: 1px solid var(--brand);
    background: rgba(124,77,255,0.08); color: var(--brand);
    transition: all 0.2s; white-space: nowrap;
}
.quiz-btn-topic:hover { background: rgba(124,77,255,0.18); transform: translateY(-1px); }

.regen-btn {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;
    cursor: pointer; border: 1px dashed var(--border-color);
    background: transparent; color: var(--text-muted);
    transition: all 0.2s; white-space: nowrap;
}
.regen-btn:hover { border-color: var(--brand); color: var(--brand); background: rgba(124,77,255,0.06); }

.quiz-btn-step {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 600;
    cursor: pointer; border: none; transition: all 0.2s;
}
.quiz-btn-step {
    background: linear-gradient(135deg, #7c4dff, #00e5ff);
    color: white;
}
.quiz-btn-step:hover { transform: translateY(-2px); box-shadow: 0 4px 16px rgba(124,77,255,0.4); }

.quiz-btn-plan {
    display: inline-flex; align-items: center; gap: 10px;
    padding: 14px 32px; border-radius: 10px; font-size: 16px; font-weight: 700;
    cursor: pointer; border: none; transition: all 0.2s;
}
.quiz-btn-plan {
    background: linear-gradient(135deg, #f59e0b, #ef4444);
    color: white;
}
.quiz-btn-plan:hover { transform: translateY(-2px); box-shadow: 0 6px 24px rgba(239,68,68,0.4); }

.quiz-score-badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 600;
}
.quiz-score-badge.passed { background: rgba(34,197,94,0.12); color: #22c55e; border: 1px solid rgba(34,197,94,0.3); }
.quiz-score-badge.failed { background: rgba(255,68,68,0.1); color: #ff6b6b; border: 1px solid rgba(255,68,68,0.25); }

/* ===== QUIZ MODAL ===== */
.quiz-overlay {
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.75); backdrop-filter: blur(4px);
    z-index: 2000; display: flex; align-items: center; justify-content: center; padding: 20px;
    opacity: 0; visibility: hidden; transition: opacity 0.25s, visibility 0.25s;
}
.quiz-overlay.active { opacity: 1; visibility: visible; }

.quiz-modal {
    background: var(--bg-card); border: 1px solid var(--border-color);
    border-radius: 16px; width: 100%; max-width: 700px; max-height: 90vh;
    display: flex; flex-direction: column;
    transform: translateY(20px); transition: transform 0.25s;
}
.quiz-overlay.active .quiz-modal { transform: translateY(0); }

.quiz-modal-header {
    padding: 22px 28px 18px;
    border-bottom: 1px solid var(--border-color);
    display: flex; justify-content: space-between; align-items: center;
    flex-shrink: 0;
}
.quiz-modal-body  { padding: 24px 28px; flex: 1; overflow-y: auto; scrollbar-width: thin; }
.quiz-modal-footer {
    padding: 18px 28px; border-top: 1px solid var(--border-color);
    display: flex; gap: 12px; justify-content: space-between; align-items: center;
    flex-shrink: 0;
}
.quiz-download-area { display: flex; align-items: center; gap: 10px; }
#downloadPdfBtn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 9px 16px; border-radius: 8px; font-size: 13px; font-weight: 600;
    cursor: pointer; border: 1px solid var(--border-color);
    background: var(--bg-darker); color: var(--text-secondary); transition: all 0.2s;
}
#downloadPdfBtn:hover { border-color: var(--brand); color: var(--brand); background: rgba(124,77,255,0.07); }
.include-answers-label {
    display: inline-flex; align-items: center; gap: 6px;
    align-self: center; margin: 0;
    font-size: 13px; color: var(--text-secondary); cursor: pointer; white-space: nowrap;
}

.quiz-close-btn {
    background: none; border: none; cursor: pointer;
    font-size: 20px; color: var(--text-muted); padding: 4px; transition: color 0.2s;
}
.quiz-close-btn:hover { color: var(--text-primary); }

/* Loading */
.quiz-loading { text-align: center; padding: 48px 0; }
.quiz-spinner {
    width: 40px; height: 40px;
    border: 3px solid var(--border-color); border-top-color: var(--brand);
    border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 16px;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* Progress bar */
.quiz-prog-wrap { height: 4px; background: var(--bg-darker); border-radius: 2px; margin-bottom: 24px; overflow: hidden; }
.quiz-prog-fill { height: 100%; background: linear-gradient(90deg, var(--brand), var(--brand2)); transition: width 0.3s; }

/* Question */
.quiz-q-block { margin-bottom: 24px; }
.quiz-q-num { font-size: 11px; font-weight: 600; color: var(--brand); text-transform: uppercase; letter-spacing: 0.6px; margin-bottom: 6px; }
.quiz-q-text { font-size: 15px; font-weight: 600; color: var(--text-primary); margin-bottom: 14px; line-height: 1.5; }

.quiz-option {
    display: flex; align-items: center; gap: 12px;
    padding: 11px 16px; border: 1px solid var(--border-color); border-radius: 8px;
    margin-bottom: 8px; cursor: pointer; transition: all 0.15s; background: var(--bg-darker);
}
.quiz-option:hover { border-color: var(--brand); background: rgba(124,77,255,0.06); }
.quiz-option input[type="radio"] { accent-color: var(--brand); width: 16px; height: 16px; flex-shrink: 0; }
.quiz-option label { cursor: pointer; font-size: 14px; color: var(--text-secondary); flex: 1; }
.quiz-option.correct { border-color: #22c55e; background: rgba(34,197,94,0.08); }
.quiz-option.wrong   { border-color: #ff4444; background: rgba(255,68,68,0.08); }
.quiz-option.correct label { color: #22c55e; font-weight: 600; }
.quiz-option.wrong   label { color: #ff6b6b; }

.quiz-explanation {
    margin-top: 10px; padding: 10px 14px;
    background: rgba(124,77,255,0.07); border-left: 3px solid var(--brand);
    border-radius: 0 6px 6px 0; font-size: 13px; color: var(--text-secondary); line-height: 1.5;
}

/* Score circle */
.quiz-score-circle {
    width: 110px; height: 110px; border-radius: 50%;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    margin: 0 auto 20px; font-weight: 700; border: 4px solid;
}
.quiz-score-circle.pass { border-color: #22c55e; color: #22c55e; background: rgba(34,197,94,0.1); }
.quiz-score-circle.fail { border-color: #ff4444; color: #ff6b6b; background: rgba(255,68,68,0.08); }
.quiz-score-circle .snum { font-size: 30px; line-height: 1; }
.quiz-score-circle .slbl { font-size: 11px; opacity: 0.8; margin-top: 3px; }

/* ===== NOTES ===== */
.note-toggle {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;
    cursor: pointer; border: 1px solid var(--border-color);
    background: transparent; color: var(--text-muted);
    transition: all 0.2s; margin-top: 8px;
}
.note-toggle:hover { border-color: #f59e0b; color: #f59e0b; background: rgba(245,158,11,0.08); }
.note-toggle.has-note { border-color: #f59e0b; color: #f59e0b; background: rgba(245,158,11,0.06); }
.note-body {
    margin-top: 8px;
    background: rgba(245,158,11,0.05);
    border: 1px solid rgba(245,158,11,0.2);
    border-radius: 8px;
    overflow: hidden;
}
.note-textarea {
    width: 100%; padding: 10px 12px; border: none;
    background: transparent; color: var(--text-primary);
    font-size: 13px; line-height: 1.6; resize: vertical;
    outline: none; font-family: inherit; min-height: 70px;
    box-sizing: border-box;
}
.note-textarea::placeholder { color: var(--text-muted); }
.note-save-status { font-size: 11px; padding: 2px 10px 6px; display: inline-block; }
</style>

<section style="padding: 100px 20px 40px; min-height: calc(100vh - 60px);">
<div style="max-width: 1200px; margin: 0 auto;">

    <!-- Header -->
    <div style="margin-bottom: 40px;">
        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 16px;">
            <div>
                <h1 style="margin-bottom: 8px;"><?php echo e($plan['title']); ?></h1>
                <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                    <span style="color:var(--text-muted);font-size:14px;">🎯 <?php echo e($plan['domain']); ?></span>
                    <span style="color:var(--text-muted);font-size:14px;">📊 <?php echo e($plan['skill_level']); ?></span>
                    <span style="color:var(--text-muted);font-size:14px;">⏰ <?php echo e($plan['weekly_hours']); ?> hrs/week</span>
                    <?php if ($plan['is_active']): ?>
                        <span style="padding:4px 12px;background:linear-gradient(90deg,var(--brand),var(--brand2));border-radius:12px;font-size:11px;font-weight:600;color:white;">🔥 Active</span>
                    <?php endif; ?>
                </div>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="<?php echo url_path('/my-plans.php'); ?>" class="btn" style="padding:10px 20px;">← Back to Plans</a>
                <a href="<?php echo url_path('/dashboard.php?plan_id=' . $plan_id); ?>" class="btn" style="padding:10px 20px;background:transparent;border:1px solid var(--brand);color:var(--brand);box-shadow:none;">📊 Analytics</a>
                <button onclick="downloadPlanPDF()" class="btn" style="padding:10px 20px;background:transparent;border:1px solid rgba(0,229,255,0.45);color:var(--brand2);box-shadow:none;">📄 Export PDF</button>
            </div>
        </div>

        <div style="padding:16px;background:var(--bg-darker);border-radius:8px;margin-bottom:20px;">
            <strong style="color:var(--text-primary);">Goal:</strong>
            <span style="color:var(--text-secondary);margin-left:8px;"><?php echo e($plan['goal']); ?></span>
        </div>

        <div style="padding:20px;background:var(--bg-card);border:1px solid var(--border-color);border-radius:12px;">
            <div style="display:flex;justify-content:space-between;margin-bottom:12px;">
                <span style="font-weight:600;">Overall Progress</span>
                <span id="progressText"><?php echo $completed_topics; ?> / <?php echo $total_topics; ?> topics completed</span>
            </div>
            <div style="height:12px;background:var(--bg-darker);border-radius:6px;overflow:hidden;">
                <div id="progressBar" style="height:100%;background:linear-gradient(90deg,var(--brand),var(--brand2));width:<?php echo $progress_percent; ?>%;transition:width 0.5s ease;"></div>
            </div>
            <div style="text-align:right;margin-top:8px;font-size:14px;color:var(--text-muted);">
                <span id="progressPercent"><?php echo $progress_percent; ?>%</span> Complete
            </div>
        </div>
    </div>

    <!-- Steps -->
    <h2 style="margin-bottom: 24px;">Learning Steps</h2>

    <?php if (isset($plan_data['summary'])): ?>
        <div style="padding:20px;background:rgba(124,77,255,0.05);border:1px solid rgba(124,77,255,0.2);border-radius:12px;margin-bottom:32px;">
            <p style="color:var(--text-secondary);line-height:1.6;"><?php echo e($plan_data['summary']); ?></p>
        </div>
    <?php endif; ?>

    <?php if (empty($plan_data['list'])): ?>
        <div class="card" style="padding:40px;text-align:center;">
            <p style="color:var(--text-muted);">No detailed steps available for this plan.</p>
        </div>
    <?php else: ?>
    <div style="display:grid;gap:24px;" id="stepsGrid">
        <?php foreach ($plan_data['list'] as $step_index => $step):
            $step_topics    = $step['topics'] ?? [];
            $step_total     = count($step_topics);
            $step_completed = 0;
            foreach ($step_topics as $ti => $t) {
                if (!empty($progress_map["{$step_index}_{$ti}"])) $step_completed++;
            }
            $step_done  = ($step_total > 0 && $step_completed === $step_total);
            $step_best  = $quiz_map['step_' . $step_index] ?? null;
            $step_bpct  = $step_best ? round(($step_best['score'] / $step_best['total_questions']) * 100) : null;
        ?>
        <div class="card" style="padding:24px;" data-step-card="<?php echo $step_index; ?>">
            <div style="display:flex;gap:16px;align-items:start;">
                <div style="width:40px;height:40px;background:linear-gradient(135deg,var(--brand),var(--brand2));border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-weight:700;color:white;">
                    <?php echo $step_index + 1; ?>
                </div>
                <div style="flex:1;">
                    <h3 style="margin-bottom:8px;"><?php echo e($step['title'] ?? 'Step '.($step_index+1)); ?></h3>

                    <?php if (!empty($step['level']) || !empty($step['duration'])): ?>
                    <div style="display:flex;gap:12px;margin-bottom:12px;">
                        <?php if (!empty($step['level'])): ?>
                        <span style="padding:4px 12px;background:var(--bg-darker);border-radius:6px;font-size:12px;color:var(--text-secondary);">📊 <?php echo e($step['level']); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($step['duration'])): ?>
                        <span style="padding:4px 12px;background:var(--bg-darker);border-radius:6px;font-size:12px;color:var(--text-secondary);">⏱️ <?php echo e($step['duration']); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($step['reason'])): ?>
                    <p style="color:var(--text-secondary);margin-bottom:16px;line-height:1.6;"><?php echo e($step['reason']); ?></p>
                    <?php endif; ?>

                    <!-- Topics -->
                    <?php if (!empty($step['topics'])): ?>
                    <div style="margin-top:16px;">
                        <h4 style="font-size:14px;margin-bottom:12px;color:var(--text-primary);">📚 Topics to Learn:</h4>
                        <div style="display:grid;gap:10px;" data-step-topics="<?php echo $step_index; ?>">
                            <?php foreach ($step['topics'] as $topic_index => $topic):
                                $topicText   = is_string($topic) ? $topic : ($topic['text'] ?? 'Topic');
                                $video       = (!is_string($topic) && isset($topic['video'])) ? $topic['video'] : null;
                                $pKey        = "{$step_index}_{$topic_index}";
                                $isCompleted = !empty($progress_map[$pKey]);
                                $topicBest   = $quiz_map["topic_{$step_index}_{$topic_index}"] ?? null;
                                $tBpct       = $topicBest ? round(($topicBest['score'] / $topicBest['total_questions']) * 100) : null;
                            ?>
                            <div class="topic-item" style="padding:12px;background:var(--bg-darker);border:1px solid var(--border-color);border-radius:8px;<?php echo $isCompleted ? 'opacity:0.65;' : ''; ?>">
                                <div style="display:flex;align-items:start;gap:12px;">
                                    <label style="display:flex;align-items:center;cursor:pointer;flex-shrink:0;margin-top:2px;">
                                        <input type="checkbox" class="topic-checkbox"
                                            data-plan-id="<?php echo $plan_id; ?>"
                                            data-step-index="<?php echo $step_index; ?>"
                                            data-topic-index="<?php echo $topic_index; ?>"
                                            <?php echo $isCompleted ? 'checked' : ''; ?>
                                            style="width:20px;height:20px;cursor:pointer;accent-color:var(--brand);" />
                                    </label>
                                    <div style="flex:1;">
                                        <div style="color:var(--text-primary);font-size:14px;margin-bottom:8px;<?php echo $isCompleted ? 'text-decoration:line-through;' : ''; ?>">
                                            <?php echo e($topicText); ?>
                                        </div>
                                        <!-- Links + topic quiz row -->
                                        <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
                                            <?php if ($video && isset($video['url'])): ?>
                                            <a href="<?php echo e($video['url']); ?>" target="_blank" rel="noopener noreferrer"
                                               style="display:inline-flex;align-items:center;gap:6px;padding:6px 11px;background:rgba(255,0,0,0.1);border:1px solid rgba(255,0,0,0.3);border-radius:6px;color:#ff0000;text-decoration:none;font-size:12px;">
                                                📺 <?php echo e($video['title'] ?? 'Watch Tutorial'); ?>
                                                <?php if (isset($video['channel'])): ?><span style="font-size:11px;color:var(--text-muted);">by <?php echo e($video['channel']); ?></span><?php endif; ?>
                                            </a>
                                            <?php endif; ?>

                                            <!-- Topic Quiz Button -->
                                            <button class="quiz-btn-topic"
                                                    id="tq-<?php echo $step_index; ?>-<?php echo $topic_index; ?>"
                                                    data-plan-id="<?php echo $plan_id; ?>"
                                                    data-quiz-type="topic"
                                                    data-step-index="<?php echo $step_index; ?>"
                                                    data-topic-index="<?php echo $topic_index; ?>"
                                                    data-title="<?php echo e($topicText); ?>">
                                                📝 <?php echo $topicBest ? 'Retake' : 'Quiz'; ?>
                                            </button>

                                            <?php if ($topicBest): ?>
                                            <span class="quiz-score-badge <?php echo $tBpct >= 60 ? 'passed' : 'failed'; ?>">
                                                <?php echo $tBpct >= 60 ? '✓' : '✗'; ?> <?php echo $topicBest['score']; ?>/<?php echo $topicBest['total_questions']; ?>
                                            </span>
                                            <button class="regen-btn"
                                                    data-for="tq-<?php echo $step_index; ?>-<?php echo $topic_index; ?>"
                                                    data-plan-id="<?php echo $plan_id; ?>"
                                                    data-quiz-type="topic"
                                                    data-step-index="<?php echo $step_index; ?>"
                                                    data-topic-index="<?php echo $topic_index; ?>"
                                                    data-title="<?php echo e($topicText); ?>">🔄</button>
                                            <?php endif; ?>
                                        </div>
                                        <!-- Note -->
                                        <?php $existingNote = $notes_map["{$step_index}_{$topic_index}"] ?? ''; ?>
                                        <div class="topic-note-wrap" data-si="<?php echo $step_index; ?>" data-ti="<?php echo $topic_index; ?>">
                                            <button class="note-toggle<?php echo $existingNote ? ' has-note' : ''; ?>">📝 <span class="note-label"><?php echo $existingNote ? 'Edit note' : 'Add note'; ?></span></button>
                                            <div class="note-body" style="display:none;">
                                                <textarea class="note-textarea" placeholder="Write your notes here..."><?php echo e($existingNote); ?></textarea>
                                                <div style="text-align:right;"><span class="note-save-status"></span></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Step Quiz -->
                    <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border-color);display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
                        <button class="quiz-btn-step"
                                id="sq-<?php echo $step_index; ?>"
                                data-plan-id="<?php echo $plan_id; ?>"
                                data-quiz-type="step"
                                data-step-index="<?php echo $step_index; ?>"
                                data-title="<?php echo e($step['title'] ?? 'Step '.($step_index+1)); ?>">
                            📋 <?php echo $step_best ? 'Retake Step Quiz' : 'Step Quiz'; ?>
                        </button>

                        <?php if ($step_best): ?>
                        <span class="quiz-score-badge <?php echo $step_bpct >= 60 ? 'passed' : 'failed'; ?>">
                            <?php echo $step_bpct >= 60 ? '✓' : '✗'; ?>
                            Best: <?php echo $step_best['score']; ?>/<?php echo $step_best['total_questions']; ?> (<?php echo $step_bpct; ?>%)
                        </span>
                        <button class="regen-btn"
                                data-for="sq-<?php echo $step_index; ?>"
                                data-plan-id="<?php echo $plan_id; ?>"
                                data-quiz-type="step"
                                data-step-index="<?php echo $step_index; ?>"
                                data-title="<?php echo e($step['title'] ?? 'Step '.($step_index+1)); ?>">🔄 New Questions</button>
                        <?php endif; ?>
                    </div>

                    <!-- Resources -->
                    <?php if (!empty($step['resources'])): ?>
                    <div style="margin-top:16px;">
                        <h4 style="font-size:14px;margin-bottom:12px;color:var(--text-primary);">🔗 Resources:</h4>
                        <div style="display:flex;flex-wrap:wrap;gap:8px;">
                            <?php foreach ($step['resources'] as $resource): ?>
                            <a href="<?php echo e($resource['url']); ?>" target="_blank" rel="noopener noreferrer"
                               style="padding:8px 16px;background:var(--bg-darker);border:1px solid var(--border-color);border-radius:8px;color:var(--brand);text-decoration:none;font-size:13px;transition:all 0.2s;">
                                <?php echo e($resource['name']); ?> ↗
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Final Exam Card (50 Q) -->
    <?php
        $plan_best = $quiz_map['plan'] ?? null;
        $plan_bpct = $plan_best ? round(($plan_best['score'] / $plan_best['total_questions']) * 100) : null;
    ?>
    <div style="margin-top:32px;padding:32px;background:var(--bg-card);border:1px solid var(--border-color);border-radius:16px;text-align:center;">
        <div style="font-size:36px;margin-bottom:12px;">🏆</div>
        <h3 style="margin-bottom:8px;">Final Exam</h3>
        <p style="color:var(--text-secondary);font-size:14px;margin-bottom:20px;max-width:480px;margin-left:auto;margin-right:auto;">
            50-question comprehensive exam covering every topic across all steps of your learning plan.
        </p>

        <div style="display:flex;justify-content:center;align-items:center;gap:16px;flex-wrap:wrap;">
            <button class="quiz-btn-plan"
                    id="planQuizBtn"
                    data-plan-id="<?php echo $plan_id; ?>"
                    data-quiz-type="plan"
                    data-title="Final Exam — <?php echo e($plan['title']); ?>">
                🏆 <?php echo $plan_best ? 'Retake Final Exam' : 'Take Final Exam'; ?>
            </button>

            <?php if ($plan_best): ?>
            <span class="quiz-score-badge <?php echo $plan_bpct >= 60 ? 'passed' : 'failed'; ?>" style="font-size:14px;padding:6px 16px;">
                <?php echo $plan_bpct >= 60 ? '✓' : '✗'; ?>
                Best: <?php echo $plan_best['score']; ?>/<?php echo $plan_best['total_questions']; ?> (<?php echo $plan_bpct; ?>%)
            </span>
            <button class="regen-btn"
                    data-for="planQuizBtn"
                    data-plan-id="<?php echo $plan_id; ?>"
                    data-quiz-type="plan"
                    data-title="Final Exam — <?php echo e($plan['title']); ?>">🔄 New Questions</button>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Actions -->
    <div style="margin-top:40px;padding-top:40px;border-top:1px solid var(--border-color);display:flex;gap:12px;justify-content:center;">
        <a href="<?php echo url_path('/my-plans.php'); ?>" class="btn">← Back to My Plans</a>
        <a href="<?php echo url_path('/recommender.php'); ?>" class="btn btn-primary">Generate New Plan</a>
    </div>

</div>
</section>

<!-- ===== QUIZ MODAL ===== -->
<div class="quiz-overlay" id="quizOverlay">
    <div class="quiz-modal">
        <div class="quiz-modal-header">
            <div>
                <div id="quizTypeLabel" style="font-size:11px;color:var(--brand);font-weight:600;text-transform:uppercase;letter-spacing:0.6px;margin-bottom:4px;"></div>
                <h3 id="quizModalTitle" style="margin:0;font-size:17px;"></h3>
            </div>
            <button class="quiz-close-btn" id="quizCloseBtn">✕</button>
        </div>
        <div class="quiz-modal-body" id="quizModalBody">
            <div class="quiz-loading" id="quizLoading">
                <div class="quiz-spinner"></div>
                <p id="quizLoadingText" style="color:var(--text-muted);font-size:14px;">Generating quiz with AI...</p>
            </div>
            <div id="quizForm" style="display:none;">
                <div class="quiz-prog-wrap"><div class="quiz-prog-fill" id="quizProgFill" style="width:0%"></div></div>
                <div id="quizQuestions"></div>
            </div>
            <div id="quizResults" style="display:none;"></div>
        </div>
        <div class="quiz-modal-footer" id="quizModalFooter" style="display:none;">
            <!-- Download section (left) -->
            <div class="quiz-download-area">
                <button id="downloadPdfBtn">📥 Download PDF</button>
                <label class="include-answers-label" for="includeAnswersCheck">
                    <input type="checkbox" id="includeAnswersCheck" style="accent-color:var(--brand);width:15px;height:15px;cursor:pointer;">
                    Include answers
                </label>
            </div>
            <!-- Action buttons (right) -->
            <div style="display:flex;gap:12px;">
                <button id="quizCancelBtn" style="width:130px;padding:12px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;background:var(--bg-darker);color:var(--text-secondary);border:1px solid var(--border-color);transition:all 0.2s;">Cancel</button>
                <button id="quizSubmitBtn" style="width:150px;padding:12px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;background:linear-gradient(135deg,var(--brand),var(--brand2));color:white;border:none;transition:all 0.2s;">Submit Answers</button>
            </div>
        </div>
    </div>
</div>

<style>
#quizCancelBtn:hover { background:var(--bg-card); color:var(--text-primary); border-color:var(--brand); transform:translateY(-1px); }
#quizSubmitBtn:hover { opacity:0.9; transform:translateY(-1px); box-shadow:0 4px 16px rgba(124,77,255,0.4); }
#quizSubmitBtn:disabled { opacity:0.6; cursor:not-allowed; transform:none; box-shadow:none; }
</style>

<script>
const PLAN_ID  = <?php echo $plan_id; ?>;
const API_BASE = '<?php echo url_path('/api'); ?>';
const PLAN_DATA = <?php echo json_encode([
    'title'        => $plan['title'],
    'domain'       => $plan['domain'],
    'skill_level'  => $plan['skill_level'],
    'weekly_hours' => $plan['weekly_hours'],
    'goal'         => $plan['goal'],
    'summary'      => $plan_data['summary'] ?? '',
    'list'         => $plan_data['list'] ?? []
], JSON_UNESCAPED_UNICODE); ?>;

// ===== Checkbox progress =====
document.querySelectorAll('.topic-checkbox').forEach(cb => {
    cb.addEventListener('change', async function() {
        const planId     = this.dataset.planId;
        const stepIndex  = parseInt(this.dataset.stepIndex);
        const topicIndex = this.dataset.topicIndex;
        const completed  = this.checked;
        this.disabled = true;

        try {
            const res = await fetch(API_BASE + '/update-progress.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ plan_id: planId, step_index: stepIndex, topic_index: topicIndex, completed })
            });
            const data = await res.json();
            if (data.success) {
                document.getElementById('progressText').textContent = data.completed_topics + ' / ' + data.total_topics + ' topics completed';
                document.getElementById('progressBar').style.width = data.completion_rate + '%';
                document.getElementById('progressPercent').textContent = data.completion_rate + '%';

                const topicItem = this.closest('.topic-item');
                const topicText = topicItem.querySelector('div[style*="color:var(--text-primary)"], div[style*="color: var(--text-primary)"]');
                topicItem.style.opacity = completed ? '0.65' : '1';
                if (topicText) topicText.style.textDecoration = completed ? 'line-through' : 'none';

            } else {
                alert('Failed to update: ' + (data.error || 'Unknown error'));
                this.checked = !completed;
            }
        } catch (e) {
            alert('Network error. Please try again.');
            this.checked = !completed;
        } finally {
            this.disabled = false;
        }
    });
});


// ===== Notes =====
const noteTimers = {};

document.querySelectorAll('.note-toggle').forEach(btn => {
    btn.addEventListener('click', function() {
        const wrap = this.closest('.topic-note-wrap');
        const body = wrap.querySelector('.note-body');
        if (body.style.display === 'none') {
            body.style.display = 'block';
            wrap.querySelector('.note-textarea').focus();
        } else {
            body.style.display = 'none';
        }
    });
});

document.querySelectorAll('.note-textarea').forEach(ta => {
    ta.addEventListener('input', function() {
        const wrap   = this.closest('.topic-note-wrap');
        const si     = parseInt(wrap.dataset.si);
        const ti     = parseInt(wrap.dataset.ti);
        const key    = si + '_' + ti;
        const status = wrap.querySelector('.note-save-status');
        status.textContent = 'Unsaved...';
        status.style.color = 'var(--text-muted)';
        clearTimeout(noteTimers[key]);
        noteTimers[key] = setTimeout(() => saveNote(si, ti, this.value, wrap), 1200);
    });
});

async function saveNote(si, ti, note, wrap) {
    const btn    = wrap.querySelector('.note-toggle');
    const label  = wrap.querySelector('.note-label');
    const status = wrap.querySelector('.note-save-status');
    try {
        const res  = await fetch(API_BASE + '/save-note.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ plan_id: PLAN_ID, step_index: si, topic_index: ti, note })
        });
        const data = await res.json();
        if (data.success) {
            status.textContent = 'Saved ✓';
            status.style.color = '#22c55e';
            setTimeout(() => { status.textContent = ''; }, 2500);
            if (note.trim()) {
                btn.classList.add('has-note');
                label.textContent = 'Edit note';
            } else {
                btn.classList.remove('has-note');
                label.textContent = 'Add note';
            }
        }
    } catch (e) {
        status.textContent = 'Failed to save';
        status.style.color = '#ff6b6b';
    }
}


// ===== Quiz Modal =====
let currentQuizId    = null;
let currentQuestions = [];
let currentContext   = {};

const overlay    = document.getElementById('quizOverlay');
const typeLabel  = document.getElementById('quizTypeLabel');
const modalTitle = document.getElementById('quizModalTitle');
const loading    = document.getElementById('quizLoading');
const loadingTxt = document.getElementById('quizLoadingText');
const quizForm   = document.getElementById('quizForm');
const quizRes    = document.getElementById('quizResults');
const footer     = document.getElementById('quizModalFooter');
const submitBtn  = document.getElementById('quizSubmitBtn');
const cancelBtn  = document.getElementById('quizCancelBtn');

function openModal()  { overlay.classList.add('active');    document.body.style.overflow = 'hidden'; }
function closeModal() { overlay.classList.remove('active'); document.body.style.overflow = ''; }

document.getElementById('quizCloseBtn').addEventListener('click', closeModal);
cancelBtn.addEventListener('click', closeModal);
overlay.addEventListener('click', e => { if (e.target === overlay) closeModal(); });

// Attach quiz buttons
document.querySelectorAll('.quiz-btn-topic, .quiz-btn-step, #planQuizBtn').forEach(btn => {
    btn.addEventListener('click', function() {
        if (this.disabled) return;
        const qt         = this.dataset.quizType;
        const planId     = parseInt(this.dataset.planId);
        const stepIndex  = this.dataset.stepIndex  !== undefined ? parseInt(this.dataset.stepIndex)  : -1;
        const topicIndex = this.dataset.topicIndex !== undefined ? parseInt(this.dataset.topicIndex) : -1;
        const title      = this.dataset.title || '';

        currentContext = { planId, qt, stepIndex, topicIndex, btn: this };
        startQuiz(planId, qt, stepIndex, topicIndex, title);
    });
});

// Attach regen buttons (call after page load and after dynamic insertion)
function attachRegenListeners() {
    document.querySelectorAll('.regen-btn:not([data-attached])').forEach(btn => {
        btn.dataset.attached = '1';
        btn.addEventListener('click', function() {
            const qt         = this.dataset.quizType;
            const planId     = parseInt(this.dataset.planId);
            const stepIndex  = this.dataset.stepIndex  !== undefined ? parseInt(this.dataset.stepIndex)  : -1;
            const topicIndex = this.dataset.topicIndex !== undefined ? parseInt(this.dataset.topicIndex) : -1;
            const title      = this.dataset.title || '';
            const quizBtn    = document.getElementById(this.dataset.for);
            currentContext   = { planId, qt, stepIndex, topicIndex, btn: quizBtn };
            startQuiz(planId, qt, stepIndex, topicIndex, title, true);
        });
    });
}
attachRegenListeners();

const TYPE_LABELS = { topic: 'Topic Quiz', step: 'Step Quiz', plan: 'Final Exam' };
const LOADING_MSGS = { topic: 'Generating 5-question topic quiz...', step: 'Generating 10-question step quiz...', plan: 'Generating 50-question final exam (this may take a moment)...' };

async function startQuiz(planId, qt, stepIndex, topicIndex, title, forceRegen = false) {
    // Reset
    typeLabel.textContent    = TYPE_LABELS[qt] || 'Quiz';
    modalTitle.textContent   = title;
    loadingTxt.textContent   = LOADING_MSGS[qt] || 'Generating quiz...';
    loading.style.display    = 'block';
    quizForm.style.display   = 'none';
    quizRes.style.display    = 'none';
    footer.style.display     = 'none';
    submitBtn.disabled       = false;
    submitBtn.textContent    = 'Submit Answers';
    submitBtn.style.display  = 'inline-flex';
    cancelBtn.textContent    = 'Cancel';
    openModal();

    const body = { plan_id: planId, quiz_type: qt };
    if (stepIndex >= 0)  body.step_index       = stepIndex;
    if (topicIndex >= 0) body.topic_index      = topicIndex;
    if (forceRegen)      body.force_regenerate = true;

    try {
        const res  = await fetch(API_BASE + '/generate-quiz.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.error || 'Failed to load quiz');

        currentQuizId    = data.quiz_id;
        currentQuestions = data.questions;

        renderQuestions(data.questions, data.best_attempt);
        loading.style.display  = 'none';
        quizForm.style.display = 'block';
        footer.style.display   = 'flex';
    } catch (err) {
        loading.innerHTML = `<p style="color:#ff6b6b;margin-bottom:16px;">⚠️ ${esc(err.message)}</p><button onclick="closeModal()" style="padding:8px 20px;border-radius:8px;background:var(--bg-darker);border:1px solid var(--border-color);color:var(--text-secondary);cursor:pointer;">Close</button>`;
    }
}

function renderQuestions(questions, bestAttempt) {
    const container = document.getElementById('quizQuestions');
    container.innerHTML = '';
    updateProg(0, questions.length);

    if (bestAttempt) {
        const pct = Math.round((bestAttempt.score / bestAttempt.total_questions) * 100);
        const info = document.createElement('div');
        info.style.cssText = 'padding:10px 14px;background:rgba(124,77,255,0.07);border-radius:8px;font-size:13px;color:var(--text-secondary);margin-bottom:18px;';
        info.textContent = `Previous best: ${bestAttempt.score}/${bestAttempt.total_questions} (${pct}%)`;
        container.appendChild(info);
    }

    questions.forEach((q, i) => {
        const block = document.createElement('div');
        block.className = 'quiz-q-block';
        block.innerHTML = `
            <div class="quiz-q-num">Question ${i+1} of ${questions.length}</div>
            <div class="quiz-q-text">${esc(q.question)}</div>
            ${q.options.map((opt, j) => `
                <div class="quiz-option" data-qi="${i}" data-oi="${j}">
                    <input type="radio" name="q${i}" id="q${i}o${j}" value="${j}">
                    <label for="q${i}o${j}">${esc(opt)}</label>
                </div>
            `).join('')}
        `;
        container.appendChild(block);
    });

    container.querySelectorAll('input[type="radio"]').forEach(r => {
        r.addEventListener('change', () => {
            const answered = new Set([...container.querySelectorAll('input[type="radio"]:checked')].map(x => x.name)).size;
            updateProg(answered, questions.length);
            const row = r.closest('.quiz-option');
            row.parentElement.querySelectorAll('.quiz-option').forEach(o => o.style.borderColor = '');
            row.style.borderColor = 'var(--brand)';
        });
    });
}

function updateProg(done, total) {
    document.getElementById('quizProgFill').style.width = (total > 0 ? (done/total)*100 : 0) + '%';
}

submitBtn.addEventListener('click', async () => {
    const answers = {};
    let allAnswered = true;
    currentQuestions.forEach((_, i) => {
        const checked = document.querySelector(`input[name="q${i}"]:checked`);
        if (checked) answers[i] = parseInt(checked.value);
        else allAnswered = false;
    });
    if (!allAnswered) { alert('Please answer all questions before submitting.'); return; }

    submitBtn.disabled = true;
    submitBtn.textContent = 'Submitting...';

    try {
        const res = await fetch(API_BASE + '/submit-quiz.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ quiz_id: currentQuizId, answers })
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.error || 'Failed to submit');
        renderResults(data);
        updateBadgeOnPage(data);
    } catch (err) {
        alert('Error submitting: ' + err.message);
        submitBtn.disabled = false;
        submitBtn.textContent = 'Submit Answers';
    }
});

function renderResults(data) {
    quizForm.style.display = 'none';
    quizRes.style.display  = 'block';
    footer.style.display   = 'flex';
    submitBtn.style.display = 'none';
    cancelBtn.textContent  = 'Close';

    const pass = data.passed;
    const msg  = pass ? 'Great job! You passed.' : 'Keep studying and try again!';

    let html = `
        <div style="text-align:center;margin-bottom:28px;">
            <div class="quiz-score-circle ${pass ? 'pass' : 'fail'}">
                <div class="snum">${data.percentage}%</div>
                <div class="slbl">${data.score}/${data.total}</div>
            </div>
            <div style="font-size:24px;margin-bottom:6px;">${pass ? '🎉' : '📖'}</div>
            <p style="color:var(--text-secondary);font-size:14px;">${msg}</p>
            <button id="regenQuestionsBtn" style="margin-top:10px;display:inline-flex;align-items:center;gap:7px;padding:9px 20px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;background:var(--bg-darker);color:var(--text-secondary);border:1px solid var(--border-color);transition:all 0.2s;"
                onmouseover="this.style.borderColor='var(--brand)';this.style.color='var(--brand)'"
                onmouseout="this.style.borderColor='var(--border-color)';this.style.color='var(--text-secondary)'">
                🔄 Generate New Questions
            </button>
        </div>
        <div style="font-weight:600;font-size:14px;margin-bottom:8px;">Answer Review</div>
    `;

    data.results.forEach((r, i) => {
        html += `
            <div class="quiz-q-block">
                <div style="display:flex;gap:8px;align-items:baseline;margin-bottom:6px;">
                    <span style="font-weight:600;color:${r.is_correct ? '#22c55e' : '#ff6b6b'}">${r.is_correct ? '✓' : '✗'}</span>
                    <div class="quiz-q-text" style="margin:0;">${esc(r.question)}</div>
                </div>
                ${r.options.map((opt, j) => `
                    <div class="quiz-option ${j === r.correct ? 'correct' : (j === r.user_answer && !r.is_correct ? 'wrong' : '')}" style="cursor:default;">
                        <label style="cursor:default;">${esc(opt)}</label>
                        ${j === r.correct ? '<span style="margin-left:auto;color:#22c55e;font-size:12px;font-weight:600;white-space:nowrap;">✓ Correct</span>' : ''}
                        ${j === r.user_answer && !r.is_correct ? '<span style="margin-left:auto;color:#ff6b6b;font-size:12px;white-space:nowrap;">Your answer</span>' : ''}
                    </div>
                `).join('')}
                ${r.explanation ? `<div class="quiz-explanation">💡 ${esc(r.explanation)}</div>` : ''}
            </div>
        `;
    });
    quizRes.innerHTML = html;

    document.getElementById('regenQuestionsBtn').addEventListener('click', () => {
        const { planId, qt, stepIndex, topicIndex } = currentContext;
        startQuiz(planId, qt, stepIndex, topicIndex, modalTitle.textContent, true);
    });
}

function updateBadgeOnPage(data) {
    const { qt, stepIndex, topicIndex, btn } = currentContext;
    if (!btn) return;
    const pct      = data.percentage;
    const badgeCls = pct >= 60 ? 'passed' : 'failed';
    const badgeTxt = qt === 'topic'
        ? `${pct >= 60 ? '✓' : '✗'} ${data.score}/${data.total}`
        : `${pct >= 60 ? '✓' : '✗'} Best: ${data.score}/${data.total} (${pct}%)`;

    // Update button label
    if (qt === 'topic') btn.textContent = '📝 Retake';
    else if (qt === 'step') btn.textContent = '📋 Retake Step Quiz';
    else btn.textContent = '🏆 Retake Final Exam';

    // Update or insert score badge
    let badge = btn.nextElementSibling;
    if (!badge || !badge.classList.contains('quiz-score-badge')) {
        badge = document.createElement('span');
        btn.after(badge);
    }
    badge.className = `quiz-score-badge ${badgeCls}`;
    badge.textContent = badgeTxt;

    // Insert regen button after badge if not already present
    const btnId = qt === 'topic' ? `tq-${stepIndex}-${topicIndex}`
                : qt === 'step'  ? `sq-${stepIndex}`
                : 'planQuizBtn';

    if (!document.querySelector(`.regen-btn[data-for="${btnId}"]`)) {
        const rb = document.createElement('button');
        rb.className = 'regen-btn';
        rb.dataset.for       = btnId;
        rb.dataset.planId    = String(currentContext.planId);
        rb.dataset.quizType  = qt;
        rb.dataset.title     = modalTitle.textContent;
        if (stepIndex  >= 0) rb.dataset.stepIndex  = String(stepIndex);
        if (topicIndex >= 0) rb.dataset.topicIndex = String(topicIndex);
        rb.textContent = qt === 'topic' ? '🔄' : '🔄 New Questions';
        badge.after(rb);
        attachRegenListeners();
    }
}

function esc(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ===== Preload logo for PDF =====
let _logoBase64 = null;
(function () {
    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload = () => {
        const canvas = document.createElement('canvas');
        canvas.width = img.naturalWidth;
        canvas.height = img.naturalHeight;
        canvas.getContext('2d').drawImage(img, 0, 0);
        _logoBase64 = canvas.toDataURL('image/png');
    };
    img.src = '<?php echo url_path('/assets/logo.png'); ?>';
})();

// ===== PDF Download =====
document.getElementById('downloadPdfBtn').addEventListener('click', () => {
    if (!currentQuestions.length) return;
    const includeAnswers = document.getElementById('includeAnswersCheck').checked;
    downloadQuizPDF(currentQuestions, modalTitle.textContent, typeLabel.textContent, includeAnswers);
});

function downloadQuizPDF(questions, title, subtitle, includeAnswers) {
    const { jsPDF } = window.jspdf;
    const doc  = new jsPDF({ unit: 'mm', format: 'a4' });
    const pw   = doc.internal.pageSize.width;
    const ph   = doc.internal.pageSize.height;
    const ml   = 14, mr = 14, maxW = pw - ml - mr;
    const lh   = 6;
    let y = 20;

    function checkPage(needed) {
        if (y + needed > ph - 16) { doc.addPage(); y = 16; }
    }

    // Header bar
    const headerH = 30;
    doc.setFillColor(22, 8, 60);
    doc.rect(0, 0, pw, headerH, 'F');
    doc.setFillColor(0, 229, 255);
    doc.rect(0, headerH - 2, pw, 2, 'F');

    if (_logoBase64) doc.addImage(_logoBase64, 'PNG', ml, 7, 16, 16);

    doc.setFontSize(12); doc.setFont('helvetica', 'bold');
    doc.setTextColor(255, 255, 255);
    doc.text('AI Course Recommender', ml + 22, 16);
    doc.setFontSize(8); doc.setFont('helvetica', 'normal');
    doc.setTextColor(180, 160, 255);
    doc.text('Personalized Learning Platform', ml + 22, 22);

    // Quiz type badge
    const qzBadge = subtitle.toUpperCase();
    doc.setFontSize(7.5); doc.setFont('helvetica', 'bold');
    const qzBw = doc.getTextWidth(qzBadge) + 12;
    const qzBx = pw - mr - qzBw;
    doc.setFillColor(0, 229, 255);
    doc.roundedRect(qzBx, 11, qzBw, 8, 2, 2, 'F');
    doc.setTextColor(22, 8, 60);
    doc.text(qzBadge, qzBx + qzBw / 2, 16.5, { align: 'center' });

    y = headerH + 10;
    doc.setFontSize(15); doc.setFont('helvetica', 'bold');
    doc.setTextColor(20, 15, 45);
    doc.text(title, ml, y); y += 8;

    doc.setFontSize(10); doc.setFont('helvetica', 'normal');
    doc.setTextColor(120, 110, 145);
    doc.text(includeAnswers ? 'Includes answers & explanations' : 'Answer sheet not included', ml, y);
    doc.setTextColor(0, 0, 0);
    y += 4;

    // Divider
    doc.setDrawColor(210, 200, 240);
    doc.line(ml, y, pw - mr, y); y += 8;

    const letters = ['A', 'B', 'C', 'D', 'E', 'F'];

    questions.forEach((q, i) => {
        checkPage(20);

        // Question
        doc.setFontSize(11); doc.setFont('helvetica', 'bold');
        doc.setTextColor(30, 30, 30);
        const qLines = doc.splitTextToSize(`Q${i + 1}.  ${q.question}`, maxW);
        checkPage(qLines.length * lh + 4);
        doc.text(qLines, ml, y);
        y += qLines.length * lh + 2;

        // Options
        doc.setFontSize(10); doc.setFont('helvetica', 'normal');
        q.options.forEach((opt, j) => {
            const isCorrect = includeAnswers && j === parseInt(q.correct);
            checkPage(lh + 2);
            const label = `   ${letters[j]}.  ${opt}`;
            const optLines = doc.splitTextToSize(label, maxW - 6);

            if (isCorrect) {
                doc.setTextColor(22, 163, 74);
                doc.setFont('helvetica', 'bold');
            } else {
                doc.setTextColor(60, 60, 60);
                doc.setFont('helvetica', 'normal');
            }
            doc.text(optLines, ml + 4, y);
            y += optLines.length * 5.5 + 1;
        });

        // Explanation
        if (includeAnswers && q.explanation) {
            checkPage(lh + 4);
            doc.setFontSize(9); doc.setFont('helvetica', 'italic');
            doc.setTextColor(100, 80, 200);
            const expLines = doc.splitTextToSize(`Explanation: ${q.explanation}`, maxW - 8);
            doc.text(expLines, ml + 6, y);
            y += expLines.length * 5 + 2;
        }

        doc.setTextColor(0, 0, 0);
        y += 6;

        // Separator between questions
        if (i < questions.length - 1) {
            checkPage(10);
            doc.setDrawColor(220, 215, 240);
            doc.line(ml, y, pw - mr, y);
            y += 7;
        }
    });

    // Footer on each page
    const totalPages = doc.internal.getNumberOfPages();
    for (let p = 1; p <= totalPages; p++) {
        doc.setPage(p);
        doc.setFontSize(8); doc.setFont('helvetica', 'normal');
        doc.setTextColor(160, 160, 160);
        doc.text(`Page ${p} of ${totalPages}`, pw / 2, ph - 8, { align: 'center' });
    }

    const filename = title.replace(/[^a-z0-9]/gi, '_').toLowerCase().slice(0, 40);
    doc.save(`${filename}.pdf`);
}

// ===== Plan PDF Export =====
function downloadPlanPDF() {
    if (!window.jspdf) { alert('PDF library is still loading. Please try again in a moment.'); return; }

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ unit: 'mm', format: 'a4' });
    const pw  = doc.internal.pageSize.width;
    const ph  = doc.internal.pageSize.height;
    const ml  = 18, mr = 18, maxW = pw - ml - mr;
    let y = 0;

    function clean(str) {
        if (!str) return '';
        const decoded = str.replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>')
                           .replace(/&quot;/g, '"').replace(/&#039;/g, "'");
        try {
            return decoded.replace(/\p{Extended_Pictographic}/gu, '').replace(/\s+/g, ' ').trim();
        } catch(e) {
            return decoded.replace(/[^\x20-\x7EÀ-ɏ]/g, '').trim();
        }
    }

    function checkPage(needed) {
        if (y + needed > ph - 18) { doc.addPage(); y = 22; }
    }

    // ── Header (first page) ──
    const hh = 30;
    // Deep purple bar
    doc.setFillColor(22, 8, 60);
    doc.rect(0, 0, pw, hh, 'F');
    // Cyan accent stripe
    doc.setFillColor(0, 229, 255);
    doc.rect(0, hh - 2, pw, 2, 'F');

    // Logo (shows well on dark background)
    if (_logoBase64) doc.addImage(_logoBase64, 'PNG', ml, 7, 16, 16);

    // Brand name
    doc.setFontSize(12); doc.setFont('helvetica', 'bold');
    doc.setTextColor(255, 255, 255);
    doc.text('AI Course Recommender', ml + 22, 16);
    doc.setFontSize(8); doc.setFont('helvetica', 'normal');
    doc.setTextColor(180, 160, 255);
    doc.text('Personalized Learning Platform', ml + 22, 22);

    // "LEARNING PLAN" pill badge
    const plBadge = 'LEARNING PLAN';
    doc.setFontSize(7.5); doc.setFont('helvetica', 'bold');
    const plBw = doc.getTextWidth(plBadge) + 12;
    const plBx = pw - mr - plBw;
    doc.setFillColor(0, 229, 255);
    doc.roundedRect(plBx, 11, plBw, 8, 2, 2, 'F');
    doc.setTextColor(22, 8, 60);
    doc.text(plBadge, plBx + plBw / 2, 16.5, { align: 'center' });

    y = hh + 12;

    // Plan title
    doc.setFontSize(22); doc.setFont('helvetica', 'bold');
    doc.setTextColor(20, 15, 40);
    const titleLines = doc.splitTextToSize(clean(PLAN_DATA.title), maxW);
    doc.text(titleLines, ml, y);
    y += titleLines.length * 9 + 3;

    // Meta row
    const metaParts = [
        PLAN_DATA.domain       ? 'Domain: '  + clean(PLAN_DATA.domain)      : null,
        PLAN_DATA.skill_level  ? 'Level: '   + clean(PLAN_DATA.skill_level) : null,
        PLAN_DATA.weekly_hours ? clean(PLAN_DATA.weekly_hours) + ' hrs/week' : null
    ].filter(Boolean);
    if (metaParts.length) {
        doc.setFontSize(10); doc.setFont('helvetica', 'normal');
        doc.setTextColor(120, 110, 150);
        doc.text(metaParts.join('   |   '), ml, y);
        y += 7;
    }

    doc.setDrawColor(210, 200, 240);
    doc.line(ml, y, pw - mr, y);
    y += 8;

    // Goal box
    if (PLAN_DATA.goal) {
        const goalLines = doc.splitTextToSize('Goal: ' + clean(PLAN_DATA.goal), maxW - 12);
        const boxH = goalLines.length * 5.5 + 12;
        checkPage(boxH + 10);
        doc.setFillColor(248, 245, 255);
        doc.setDrawColor(200, 185, 255);
        doc.roundedRect(ml, y, maxW, boxH, 3, 3, 'FD');
        doc.setFontSize(10); doc.setFont('helvetica', 'bold');
        doc.setTextColor(80, 50, 160);
        doc.text(goalLines, ml + 6, y + 8);
        y += boxH + 8;
    }

    // Summary
    if (PLAN_DATA.summary) {
        const sumLines = doc.splitTextToSize(clean(PLAN_DATA.summary), maxW);
        checkPage(sumLines.length * 5.5 + 8);
        doc.setFontSize(10); doc.setFont('helvetica', 'italic');
        doc.setTextColor(90, 85, 115);
        doc.text(sumLines, ml, y);
        y += sumLines.length * 5.5 + 10;
    }

    // ── Steps ──
    (PLAN_DATA.list || []).forEach((step, si) => {
        // Every step after the first starts on a fresh page
        if (si === 0) {
            checkPage(60);
        } else {
            doc.addPage();
            y = 22;
        }

        // Step header bar
        const stepTitle  = 'Step ' + (si + 1) + ':  ' + clean(step.title || '');
        const stepLines  = doc.splitTextToSize(stepTitle, maxW - 48);
        const barH       = Math.max(stepLines.length * 7 + 8, 14);
        doc.setFillColor(124, 77, 255);
        doc.roundedRect(ml, y, maxW, barH, 3, 3, 'F');
        doc.setFontSize(11); doc.setFont('helvetica', 'bold');
        doc.setTextColor(255, 255, 255);
        doc.text(stepLines, ml + 5, y + 8);

        const stepMeta = [step.duration, step.level].filter(Boolean).map(clean).join('  |  ');
        if (stepMeta) {
            doc.setFontSize(9); doc.setFont('helvetica', 'normal');
            doc.setTextColor(220, 210, 255);
            doc.text(stepMeta, pw - mr - 4, y + barH - 4, { align: 'right' });
        }
        y += barH + 6;

        // Reason
        if (step.reason) {
            const rLines = doc.splitTextToSize(clean(step.reason), maxW);
            checkPage(rLines.length * 5 + 6);
            doc.setFontSize(9.5); doc.setFont('helvetica', 'italic');
            doc.setTextColor(100, 90, 130);
            doc.text(rLines, ml, y);
            y += rLines.length * 5 + 8;
        }

        // Topics
        const topics = step.topics || [];
        if (topics.length) {
            checkPage(12);
            doc.setFontSize(10); doc.setFont('helvetica', 'bold');
            doc.setTextColor(60, 40, 120);
            doc.text('Topics to Learn', ml, y);
            y += 6;

            topics.forEach(topic => {
                const txt   = typeof topic === 'string' ? topic : (topic.text || '');
                const video = typeof topic !== 'string' ? (topic.video || null) : null;
                const tLines = doc.splitTextToSize(clean(txt), maxW - 7);
                const videoExtra = (video && video.url) ? 9 : 0;
                checkPage(tLines.length * 5.5 + videoExtra + 4);

                // Bullet
                doc.setFillColor(124, 77, 255);
                doc.circle(ml + 1.8, y - 1.5, 1.3, 'F');
                // Topic text
                doc.setFontSize(10); doc.setFont('helvetica', 'normal');
                doc.setTextColor(25, 20, 50);
                doc.text(tLines, ml + 5, y);
                y += tLines.length * 5.5;

                // YouTube link
                if (video && video.url) {
                    const vLabel = clean('Watch: ' + (video.title || 'Tutorial') + (video.channel ? '  -  ' + video.channel : ''));
                    const vLines = doc.splitTextToSize(vLabel, maxW - 12);
                    doc.setFontSize(8.5); doc.setFont('helvetica', 'normal');
                    doc.setTextColor(180, 30, 30);
                    doc.text(vLines, ml + 7, y + 1);
                    doc.link(ml + 7, y - 3, doc.getTextWidth(vLines[0]), vLines.length * 4.5, { url: video.url });
                    y += vLines.length * 4.5 + 2;
                }
                y += 3;
            });
        }

        // Resources
        const resources = step.resources || [];
        if (resources.length) {
            checkPage(14);
            doc.setFontSize(10); doc.setFont('helvetica', 'bold');
            doc.setTextColor(60, 40, 120);
            doc.text('Resources', ml, y);
            y += 6;
            resources.forEach(res => {
                checkPage(8);
                const rName = clean(res.name || res.url || '');
                doc.setFontSize(9); doc.setFont('helvetica', 'normal');
                doc.setTextColor(30, 90, 200);
                doc.text('- ' + rName, ml + 4, y);
                if (res.url) doc.link(ml + 4, y - 4, doc.getTextWidth('- ' + rName), 5, { url: res.url });
                y += 6;
            });
        }

        // No divider needed — next step always starts on a new page
    });

    // ── Footers on all pages ──
    const total = doc.internal.getNumberOfPages();
    for (let p = 1; p <= total; p++) {
        doc.setPage(p);
        doc.setDrawColor(210, 200, 240);
        doc.line(ml, ph - 12, pw - mr, ph - 12);
        doc.setFontSize(8); doc.setFont('helvetica', 'normal');
        doc.setTextColor(160, 150, 190);
        doc.text(clean(PLAN_DATA.title).slice(0, 45), ml, ph - 7);
        doc.text('Page ' + p + ' of ' + total, pw / 2, ph - 7, { align: 'center' });
        doc.text('AI Course Recommender', pw - mr, ph - 7, { align: 'right' });
    }

    const fname = clean(PLAN_DATA.title).replace(/[^a-z0-9\s]/gi, '').replace(/\s+/g, '_').toLowerCase().slice(0, 40);
    doc.save((fname || 'learning_plan') + '.pdf');
}
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
<?php include __DIR__.'/partials/footer.php'; ?>
