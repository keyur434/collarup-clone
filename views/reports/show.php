<?php
$sl = score_label($report['overall_score']);
$initials = strtoupper(substr($report['first_name'],0,1) . substr($report['last_name']??'',0,1));
$scoreClass = $sl['class'] === 'score-good' ? 'good' : ($sl['class'] === 'score-average' ? 'avg' : 'poor');
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => url('dashboard')],
    ['label' => 'Candidate Report'],
];
?>

<!-- Report Header -->
<div class="report-header">
    <div class="report-header-left">
        <div class="report-avatar"><?= e($initials) ?></div>
        <div>
            <div class="report-name-row">
                <span class="report-name"><?= e($report['first_name'] . ' ' . $report['last_name']) ?></span>
                <?php if ($report['overall_score']): ?>
                <span class="score-badge <?= $scoreClass ?>"><?= e($report['overall_score']) ?> <?= e($sl['label']) ?></span>
                <?php endif; ?>
                <button type="button" class="integrity-btn" onclick="openIntegrityModal()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
                    Integrity Signals
                </button>
            </div>
        </div>
    </div>
    <div class="report-header-right">
        <select class="stage-select-inline" name="stage_id">
            <?php foreach ($stages as $s): ?>
            <option value="<?= $s['id'] ?>" <?= ($s['id'] === $report['pipeline_id'])?'selected':'' ?>><?= e($s['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if (!empty($canEditReport)): ?>
        <form method="POST" action="<?= url('jobs/' . $report['job_id'] . '/reports/' . $report['id'] . '/decision') ?>" style="display:contents">
            <?= csrf_field() ?>
            <button name="decision" value="reject" class="btn btn-reject btn-sm">Reject</button>
            <button name="decision" value="hold" class="btn btn-hold btn-sm">Hold</button>
            <button name="decision" value="advance" class="btn btn-advance btn-sm">Advance</button>
        </form>
        <?php endif; ?>
        <a href="<?= url('jobs/' . $report['job_id'] . '/reports/' . $report['id'] . '/export') ?>" target="_blank" class="btn btn-outline btn-sm">Export PDF</a>
        <button type="button" class="icon-btn">
            <svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg>
        </button>
    </div>
</div>

<!-- Report Layout -->
<div class="report-layout">
    <!-- LEFT: tabs -->
    <div class="report-left">
        <div class="report-tabs">
            <button type="button" class="report-tab active" data-rtab="overview">Overview</button>
            <button type="button" class="report-tab" data-rtab="scorecard">Scorecard</button>
            <button type="button" class="report-tab" data-rtab="ainotes">AI Notes</button>
            <button type="button" class="report-tab" data-rtab="transcript">Transcript</button>
        </div>

        <!-- Overview -->
        <div class="rtab-panel active" id="rtab-overview">
            <?php
            $sections = [
                ['icon' => '⚠', 'type' => 'strength', 'label' => 'Strengths', 'val' => $report['overview_strengths']],
                ['icon' => '⚠', 'type' => 'strength', 'label' => 'Weaknesses', 'val' => $report['overview_weaknesses']],
                ['icon' => '✕', 'type' => 'red', 'label' => 'Red Flags', 'val' => $report['overview_red_flags']],
                ['icon' => '⚠', 'type' => 'strength', 'label' => 'Team Fit', 'val' => $report['overview_team_fit']],
                ['icon' => '✕', 'type' => 'red', 'label' => 'Cheating Detection', 'val' => $report['overview_cheating_detection']],
                ['icon' => '⚠', 'type' => 'strength', 'label' => 'Qualification Match', 'val' => $report['overview_qualification_match']],
                ['icon' => '⚠', 'type' => 'strength', 'label' => 'Logistics', 'val' => $report['overview_logistics']],
                ['icon' => '✕', 'type' => 'red', 'label' => 'Follow-up Questions', 'val' => $report['overview_follow_up']],
                ['icon' => '⚠', 'type' => 'strength', 'label' => 'Recommended Hiring Decision', 'val' => $report['overview_recommendation']],
            ];
            foreach ($sections as $sec): ?>
            <div class="overview-item">
                <div class="overview-item-label">
                    <span class="overview-icon <?= $sec['type'] ?>"><?= $sec['icon'] ?></span>
                    <?= e($sec['label']) ?>
                </div>
                <p><?= nl2br(e($sec['val'] ?: 'No data available.')) ?></p>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Scorecard -->
        <div class="rtab-panel" id="rtab-scorecard">
            <?php
            function sc_class($s) {
                if (!$s) return 'poor';
                return ((float)$s >= 8) ? 'good' : (((float)$s >= 5) ? 'avg' : 'poor');
            }
            $sc_items = [
                ['label' => 'Overall Interview Score', 'score' => $report['overall_score'], 'desc' => 'AI-generated overall assessment.'],
                ['label' => 'Experience Score', 'score' => $report['experience_score'], 'desc' => 'Relevant experience and background evaluation.'],
                ['label' => 'Culture Score', 'score' => $report['culture_score'], 'desc' => 'Cultural alignment with company values.'],
                ['label' => 'Soft Skills Score', 'score' => $report['soft_skills_score'], 'desc' => 'Communication, teamwork, and interpersonal skills.'],
            ];
            foreach ($sc_items as $sc): ?>
            <div class="scorecard-section">
                <div class="scorecard-score-row">
                    <span class="scorecard-num <?= sc_class($sc['score']) ?>"><?= e($sc['score'] ?? '–') ?></span>
                    <span class="scorecard-title"><?= e($sc['label']) ?></span>
                </div>
                <p><?= e($sc['desc']) ?></p>
            </div>
            <?php endforeach;
            foreach ($criterionScores as $cs): ?>
            <div class="scorecard-section">
                <div class="scorecard-score-row">
                    <span class="scorecard-num <?= sc_class($cs['score']) ?>"><?= e($cs['score'] ?? '–') ?></span>
                    <span class="scorecard-title"><?= e($cs['criterion_name']) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- AI Notes -->
        <div class="rtab-panel" id="rtab-ainotes">
            <div class="ainotes-templates">
                <select>
                    <option>Questions &amp; Answers</option>
                    <option>Summary</option>
                </select>
            </div>
            <?php if (empty($qaNotes)): ?>
            <p class="text-muted" style="font-size:0.85rem">No AI notes generated yet.</p>
            <?php else: foreach ($qaNotes as $qa): ?>
            <div class="qa-item">
                <div class="qa-question"><?= e($qa['question_text']) ?></div>
                <div class="qa-answer"><?= e($qa['answer_summary']) ?></div>
            </div>
            <?php endforeach; endif; ?>
        </div>

        <!-- Transcript -->
        <div class="rtab-panel" id="rtab-transcript">
            <?php if ($transcript && $transcript['segments']):
                $segments = json_decode($transcript['segments'], true) ?: [];
                foreach ($segments as $seg): ?>
            <div class="transcript-line">
                <div class="transcript-time"><?= gmdate('i:s', (int)$seg['start']) ?> – <?= gmdate('i:s', (int)$seg['end']) ?></div>
                <div><span class="transcript-speaker"><?= e($seg['speaker']) ?></span></div>
                <div class="transcript-text"><?= e($seg['text']) ?></div>
            </div>
            <?php endforeach;
            elseif ($transcript && $transcript['full_text']): ?>
            <pre style="white-space:pre-wrap;font-size:0.83rem;color:var(--gray-600);line-height:1.6"><?= e($transcript['full_text']) ?></pre>
            <?php else: ?>
            <p class="text-muted" style="font-size:0.85rem">No transcript available yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- RIGHT: video + info tabs -->
    <div class="report-right">
        <!-- Video -->
        <div class="video-panel">
            <?php if ($videoUrl): ?>
            <video controls id="reportInterviewVideo" controlsList="nodownload" data-playback-api="<?= e($videoUrl) ?>"></video>
            <?php elseif (!empty($externalMeetingUrl)): ?>
            <div class="video-placeholder-box" style="padding:24px;text-align:center">
                <p style="margin-bottom:12px">Meeting / recording link saved</p>
                <a href="<?= e($externalMeetingUrl) ?>" target="_blank" rel="noopener" class="btn btn-primary btn-sm">Open Recording Link</a>
            </div>
            <?php else: ?>
            <div class="video-placeholder-box">No video uploaded yet</div>
            <?php endif; ?>
        </div>

        <!-- Right tabs -->
        <div class="right-tabs">
            <button type="button" class="right-tab active" data-rtab2="info">Information</button>
            <button type="button" class="right-tab" data-rtab2="chat">Team Chat</button>
            <button type="button" class="right-tab" data-rtab2="morgan" style="display:none">Ask Morgan</button>
        </div>

        <!-- Information -->
        <div class="right-panel-body rtab2-panel active" id="rtab2-info">
            <dl class="info-dl">
                <dt>Job Title</dt>
                <dd><?= e($report['job_title']) ?> <a href="<?= url('jobs/' . $report['job_id'] . '/interviews') ?>" style="font-size:0.75rem" title="Open job">↗</a></dd>
                <dt>Department</dt><dd><?= e($report['department_name']) ?></dd>
                <dt>Hiring Panel</dt><dd><?= e($hiringManager) ?></dd>
                <dt>Contact No.</dt><dd><?= e(trim(($report['phone_country_code'] ?? '') . ' ' . ($report['phone'] ?? ''))) ?: '–' ?></dd>
                <dt>Email ID</dt><dd><?= e($report['email']) ?></dd>
                <?php if (!empty($report['resume_path'])): ?>
                <dt>Resume</dt><dd>
                    <a href="<?= resume_url($report['application_id']) ?>">Download</a>
                    <?php if (strtolower(pathinfo($report['resume_path'], PATHINFO_EXTENSION)) === 'pdf'): ?>
                    · <a href="<?= resume_url($report['application_id'], true) ?>" target="_blank">Preview</a>
                    <?php endif; ?>
                </dd>
                <?php endif; ?>
            </dl>
            <div class="upload-form-box">
                <form method="POST" action="<?= url('interviews/' . $report['interview_id'] . '/upload') ?>" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <label>Upload Interview Video</label>
                    <input type="file" name="video" accept="video/*">
                    <input type="url" name="meeting_url" placeholder="Or paste Zoom / Teams / Meet link" class="form-control" style="margin-bottom:8px">
                    <button type="submit" class="btn btn-primary btn-sm btn-block">Upload &amp; Analyze</button>
                </form>
            </div>
        </div>

        <!-- Team Chat -->
        <div class="right-panel-body rtab2-panel" id="rtab2-chat" style="display:none">
            <div class="chat-messages-box">
                <?php if (empty($chatMessages)): ?>
                <p class="text-muted" style="font-size:0.82rem">No comments yet.</p>
                <?php else: foreach ($chatMessages as $msg): ?>
                <div class="chat-msg-item">
                    <strong><?= e($msg['first_name']) ?></strong>
                    <p><?= e($msg['message_text']) ?></p>
                    <time><?= format_date($msg['created_at'], 'd M h:i a') ?></time>
                </div>
                <?php endforeach; endif; ?>
            </div>
            <form method="POST" action="<?= url('jobs/' . $report['job_id'] . '/reports/' . $report['id'] . '/chat') ?>" class="chat-input-row">
                <?= csrf_field() ?>
                <textarea name="message" placeholder="Ask anything about this conversation…"></textarea>
                <button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-end">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13M22 2L15 22l-4-9-9-4 20-7z"/></svg>
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Integrity Modal -->
<div class="modal-overlay hidden" id="integrityModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Integrity Assessment</h3>
            <button type="button" class="icon-btn" onclick="closeIntegrityModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="integrity-cheating">
            <div class="label" style="color:var(--primary);font-weight:600;font-size:0.82rem;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:6px">Cheating Likelihood</div>
            <div class="value <?= e($report['cheating_likelihood'] ?? 'low') ?>"><?= e(ucfirst($report['cheating_likelihood'] ?? 'Low')) ?></div>
            <div class="desc">Derived from multiple interview signals to estimate the likelihood of unfair assistance.</div>
        </div>
        <div class="integrity-signals">
            <h4>Observed Signals</h4>
            <div class="integrity-signal-item">
                <div class="integrity-signal-row">
                    <span class="integrity-signal-name">External Display Activity</span>
                    <span class="integrity-signal-val not-detected"><?= e(ucwords(str_replace('_',' ',$report['external_display'] ?? 'Not Detected'))) ?></span>
                </div>
                <div class="integrity-signal-desc">An additional display or external monitor was active during the interview session.</div>
            </div>
            <div class="integrity-signal-item">
                <div class="integrity-signal-row">
                    <span class="integrity-signal-name">Interview Window Switching</span>
                    <span class="integrity-signal-val count"><?= (int)($report['window_switching'] ?? 0) ?></span>
                </div>
                <div class="integrity-signal-desc">The candidate switched away from interview window multiple times during the session.</div>
            </div>
            <div class="integrity-signal-item">
                <div class="integrity-signal-row">
                    <span class="integrity-signal-name">Interview Content Copied</span>
                    <span class="integrity-signal-val not-detected"><?= e(ucwords(str_replace('_',' ',$report['content_copied'] ?? 'Not Detected'))) ?></span>
                </div>
                <div class="integrity-signal-desc">Text from the interview questions was copied out of the interview environment.</div>
            </div>
            <div class="integrity-signal-item">
                <div class="integrity-signal-row">
                    <span class="integrity-signal-name">AI-Generated Answer Patterns</span>
                    <span class="integrity-signal-val not-detected"><?= e(ucwords(str_replace('_',' ',$report['ai_answer_patterns'] ?? 'Not Detected'))) ?></span>
                </div>
                <div class="integrity-signal-desc">Indicates that the response pattern matches AI-generated content.</div>
            </div>
        </div>
    </div>
</div>

<script>
function openIntegrityModal() { document.getElementById('integrityModal').classList.remove('hidden'); }
function closeIntegrityModal() { document.getElementById('integrityModal').classList.add('hidden'); }
document.getElementById('integrityModal').addEventListener('click', function(e) {
    if (e.target === this) closeIntegrityModal();
});

// Report left tabs
document.querySelectorAll('.report-tab').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.report-tab').forEach(function(b) { b.classList.remove('active'); });
        document.querySelectorAll('.rtab-panel').forEach(function(p) { p.classList.remove('active'); });
        this.classList.add('active');
        var panel = document.getElementById('rtab-' + this.dataset.rtab);
        if (panel) panel.classList.add('active');
    });
});
document.querySelectorAll('.rtab-panel').forEach(function(p) {
    if (!p.classList.contains('active')) p.style.display = 'none';
});
document.querySelectorAll('.report-tab').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.rtab-panel').forEach(function(p) { p.style.display = 'none'; });
        var panel = document.getElementById('rtab-' + this.dataset.rtab);
        if (panel) panel.style.display = 'block';
    });
});
// Right tabs
document.querySelectorAll('.right-tab').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.right-tab').forEach(function(b) { b.classList.remove('active'); });
        document.querySelectorAll('.rtab2-panel').forEach(function(p) { p.style.display = 'none'; });
        this.classList.add('active');
        var panel = document.getElementById('rtab2-' + this.dataset.rtab2);
        if (panel) panel.style.display = 'block';
    });
});
// Graph playback URL (short-lived OneDrive link)
(function() {
    var video = document.getElementById('reportInterviewVideo');
    if (!video || !video.dataset.playbackApi) return;
    fetch(video.dataset.playbackApi, { credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.url) video.src = data.url;
        })
        .catch(function() {});
})();
</script>
