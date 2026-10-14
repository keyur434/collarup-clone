<?php
$sl = score_label($report['overall_score']);
$initials = strtoupper(substr($report['first_name'],0,1) . substr($report['last_name']??'',0,1));
$scoreClass = $sl['class'] === 'score-good' ? 'good' : ($sl['class'] === 'score-average' ? 'avg' : 'poor');
$showIntegrityUi = !empty(config('app')['show_integrity_ui']);
$hasScore = $report['overall_score'] !== null && $report['overall_score'] !== '';
$applicationDecision = $report['application_decision'] ?? 'none';
$isSharedReport = !empty($sharedReportView);
$reportUrl = $isSharedReport
    ? ($sharedReportUrl ?? url('jobs/' . $report['job_id'] . '/reports/' . $report['id']))
    : url('jobs/' . $report['job_id'] . '/reports/' . $report['id']);
$exportUrl = $isSharedReport
    ? ($sharedExportUrl ?? $reportUrl . '/export')
    : url('jobs/' . $report['job_id'] . '/reports/' . $report['id'] . '/export');
$aiSummaryText = '';
if (!empty($enhanced['grounding_safe_summary'])) {
    $aiSummaryText = $enhanced['grounding_safe_summary'];
} else {
    $parts = array_filter([
        $report['overview_strengths'] ?? '',
        $report['overview_weaknesses'] ?? '',
        $report['overview_recommendation'] ?? '',
    ]);
    $aiSummaryText = implode("\n\n", $parts);
}
$transcriptSegments = [];
if ($transcript && !empty($transcript['segments'])) {
    $decoded = json_decode($transcript['segments'], true);
    if (is_array($decoded)) {
        $transcriptSegments = $decoded;
    }
}
$breadcrumbs = $isSharedReport
    ? [
        ['label' => $report['job_title']],
        ['label' => trim($report['first_name'] . ' ' . ($report['last_name'] ?? ''))],
    ]
    : [
        ['label' => 'Dashboard', 'url' => url('dashboard')],
        ['label' => 'Job Roles', 'url' => url('jobs')],
        ['label' => $report['job_title'], 'url' => url('jobs/' . $report['job_id'] . '/interviews')],
        ['label' => trim($report['first_name'] . ' ' . ($report['last_name'] ?? ''))],
    ];
?>

<?php if ($isSharedReport): ?>
<div class="alert" role="status" style="margin-bottom:16px;background:var(--gray-50,#f8fafc);border:1px solid var(--gray-200);color:var(--text-muted);font-size:0.85rem">
    Shared interview report — read-only. Bookmark this page to return anytime.
</div>
<?php endif; ?>

<!-- Report Header -->
<div class="report-header">
    <div class="report-header-left">
        <div class="report-avatar"><?= e($initials) ?></div>
        <div>
            <div class="report-name-row">
                <span class="report-name"><?= e($report['first_name'] . ' ' . $report['last_name']) ?></span>
                <?php if ($hasScore): ?>
                <span class="score-badge <?= $scoreClass ?>"><?= e($report['overall_score']) ?> <?= e($sl['label']) ?></span>
                <?php endif; ?>
                <?php if ($showIntegrityUi): ?>
                <button type="button" class="integrity-btn" onclick="openIntegrityModal()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
                    Integrity Signals
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="report-header-right">
        <?php if (!$isSharedReport && !empty($canEditReport)): ?>
        <form method="POST" action="<?= url('jobs/' . $report['job_id'] . '/reports/' . $report['id'] . '/stage') ?>" id="reportStageForm">
            <?= csrf_field() ?>
            <select class="stage-select-inline" name="stage_id" onchange="this.form.submit()" title="Move to pipeline stage">
                <?php foreach ($stages as $s): ?>
                <option value="<?= e($s['id']) ?>" <?= ($s['id'] === ($report['current_stage_id'] ?? '')) ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php elseif (!$isSharedReport): ?>
        <select class="stage-select-inline" disabled title="Pipeline stage">
            <?php foreach ($stages as $s): ?>
            <option value="<?= e($s['id']) ?>" <?= ($s['id'] === ($report['current_stage_id'] ?? '')) ? 'selected' : '' ?>><?= e($s['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <?php if (!$isSharedReport && !empty($canEditReport)): ?>
        <form method="POST" action="<?= url('jobs/' . $report['job_id'] . '/reports/' . $report['id'] . '/decision') ?>" style="display:contents">
            <?= csrf_field() ?>
            <button name="decision" value="reject" class="btn btn-reject btn-sm<?= $applicationDecision === 'reject' ? ' active' : '' ?>">Reject</button>
            <button name="decision" value="hold" class="btn btn-hold btn-sm<?= $applicationDecision === 'hold' ? ' active' : '' ?>">Hold</button>
            <button name="decision" value="advance" class="btn btn-advance btn-sm<?= $applicationDecision === 'advance' ? ' active' : '' ?>">Advance</button>
            <?php if (!empty($hasOfferStage)): ?>
            <button name="decision" value="offer" class="btn btn-primary btn-sm<?= $applicationDecision === 'offer' ? ' active' : '' ?>">Offer</button>
            <?php endif; ?>
        </form>
        <?php endif; ?>
        <a href="<?= e($exportUrl) ?>" target="_blank" class="btn btn-outline btn-sm">Export PDF</a>
        <?php if (!$isSharedReport): ?>
        <div class="report-actions-wrap" style="position:relative;display:inline-block">
            <button type="button" class="icon-btn" onclick="toggleReportMenu(this)" title="More actions" aria-haspopup="true" aria-expanded="false">
                <svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg>
            </button>
            <div class="report-actions-menu hidden" role="menu">
                <a href="<?= url('applications/' . $report['application_id'] . '/manage') ?>" role="menuitem">Edit candidate</a>
                <a href="<?= url('jobs/' . $report['job_id'] . '/interviews') ?>" role="menuitem">Job interviews</a>
                <a href="<?= e($exportUrl) ?>" target="_blank" role="menuitem">Export PDF</a>
                <button type="button" role="menuitem" onclick="copyReportLink()">Copy report link</button>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($interviewRounds) && count($interviewRounds) > 0): ?>
<div class="hiring-rounds-strip" style="margin-bottom:16px;padding:12px 16px;background:var(--gray-50,#f8fafc);border:1px solid var(--gray-200);border-radius:8px">
    <div style="font-size:0.78rem;font-weight:600;color:var(--text-muted);margin-bottom:8px">Interview rounds for this candidate</div>
    <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
        <?php foreach ($interviewRounds as $round):
            $isThisReport = !empty($round['report_id']) && $round['report_id'] === $report['id'];
            $isCurrent = !empty($currentStageInterview['id']) && $round['interview_id'] === $currentStageInterview['id'];
        ?>
        <div style="display:flex;align-items:center;gap:6px;padding:6px 10px;border-radius:6px;border:1px solid <?= $isThisReport ? 'var(--primary)' : 'var(--gray-200)' ?>;background:<?= $isCurrent ? 'var(--surface)' : 'transparent' ?>;font-size:0.8rem">
            <span style="font-weight:600"><?= e($round['stage_name']) ?></span>
            <?php if ($round['status'] === 'completed' && !empty($round['report_id'])): ?>
            <a href="<?= url('jobs/' . $report['job_id'] . '/reports/' . $round['report_id']) ?>" style="color:var(--primary)"><?= e($round['overall_score'] ?? 'Report') ?></a>
            <?php elseif (in_array($round['status'], ['pending', 'scheduled'], true)): ?>
            <span style="color:var(--text-muted)"><?= e(ucfirst($round['status'])) ?></span>
            <?php if ($isCurrent && !empty($canEditReport)): ?>
            <a href="<?= url('jobs/' . $report['job_id'] . '/interviews?focus=' . urlencode($round['interview_id'])) ?>" style="color:var(--primary);font-weight:600">Schedule →</a>
            <?php endif; ?>
            <?php else: ?>
            <span style="color:var(--text-muted)"><?= e(ucfirst($round['status'] ?? 'pending')) ?></span>
            <?php endif; ?>
            <?php if ($isCurrent): ?><span class="badge" style="font-size:0.65rem">Current</span><?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php
    $nextIv = $currentStageInterview ?? null;
    if (!empty($canEditReport) && $nextIv && in_array($nextIv['status'] ?? '', ['pending', 'scheduled'], true)
        && ($nextIv['id'] ?? '') !== ($report['interview_id'] ?? '')): ?>
    <p style="margin:10px 0 0;font-size:0.82rem">
        Next step: <a href="<?= url('jobs/' . $report['job_id'] . '/interviews?focus=' . urlencode($nextIv['id'])) ?>">Add meeting link for the current round</a>, then sync recording after the call.
    </p>
    <?php endif; ?>
</div>
<?php endif; ?>

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
            $grounding = isset($enhanced['grounding_audit']) ? $enhanced['grounding_audit'] : [];
            $groundingScore = isset($grounding['grounding_score']) ? (int) $grounding['grounding_score'] : null;
            $meta = isset($enhanced['analysis_metadata']) ? $enhanced['analysis_metadata'] : [];
            if ($groundingScore !== null || !empty($enhanced['grounding_safe_summary'])): ?>
            <div class="overview-item" style="border-left:3px solid <?= $groundingScore !== null && $groundingScore < 70 ? '#dc2626' : '#16a34a' ?>;padding-left:12px;margin-bottom:16px">
                <div class="overview-item-label">AI Grounding (v4)</div>
                <?php if ($groundingScore !== null): ?>
                <p><strong>Grounding score:</strong> <?= (int) $groundingScore ?>/100
                <?php if (!empty($meta['evidence_strength'])): ?> · Evidence: <?= e($meta['evidence_strength']) ?><?php endif; ?>
                <?php if (!empty($meta['hallucination_risk'])): ?> · Risk: <?= e($meta['hallucination_risk']) ?><?php endif; ?>
                </p>
                <?php endif; ?>
                <?php if (!empty($enhanced['grounding_safe_summary'])): ?>
                <p><strong>Verified summary:</strong> <?= nl2br(e($enhanced['grounding_safe_summary'])) ?></p>
                <?php endif; ?>
                <?php if (!empty($grounding['unsupported_claims'])): ?>
                <p style="font-size:0.82rem;color:var(--gray-600)"><strong>Flagged claims:</strong> <?= count($grounding['unsupported_claims']) ?> — see raw JSON or re-check transcript.</p>
                <?php endif; ?>
            </div>
            <?php endif;
            $topicAdherence = isset($enhanced['topic_adherence_score']) ? $enhanced['topic_adherence_score'] : null;
            $infoComplete = isset($enhanced['information_completeness_score']) ? $enhanced['information_completeness_score'] : null;
            $offTopic = isset($meta['off_topic_content_flag']) ? $meta['off_topic_content_flag'] : '';
            $mustHaveCheck = !empty($enhanced['must_have_check']) && is_array($enhanced['must_have_check']) ? $enhanced['must_have_check'] : [];
            if ($topicAdherence !== null || $infoComplete !== null || $mustHaveCheck !== []): ?>
            <div class="overview-item" style="margin-bottom:16px;padding:12px;background:var(--gray-50, #f8fafc);border-radius:8px">
                <div class="overview-item-label">Interview coverage</div>
                <?php if ($topicAdherence !== null): ?>
                <p><strong>Topic adherence:</strong> <?= (int) $topicAdherence ?>/100</p>
                <?php endif; ?>
                <?php if ($infoComplete !== null): ?>
                <p><strong>Planned questions covered:</strong> <?= (int) $infoComplete ?>%</p>
                <?php endif; ?>
                <?php if ($offTopic !== '' && stripos($offTopic, 'none') === false): ?>
                <p><strong>Off-topic content:</strong> <?= e($offTopic) ?></p>
                <?php endif; ?>
                <?php if (!empty($mustHaveCheck)): ?>
                <p style="margin-top:8px"><strong>Must-have checklist</strong></p>
                <ul style="font-size:0.85rem;margin:4px 0 0 18px">
                    <?php foreach ($mustHaveCheck as $mh): ?>
                    <li><?= e($mh['requirement'] ?? '') ?> — <strong><?= e($mh['status'] ?? 'unclear') ?></strong>
                        <?php if (!empty($mh['evidence'])): ?><br><span style="color:var(--text-muted)"><?= e($mh['evidence']) ?></span><?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
            <?php endif;
            $sections = [
                ['icon' => '⚠', 'type' => 'strength', 'label' => 'Strengths', 'val' => $report['overview_strengths']],
                ['icon' => '⚠', 'type' => 'strength', 'label' => 'Weaknesses', 'val' => $report['overview_weaknesses']],
                ['icon' => '✕', 'type' => 'red', 'label' => 'Red Flags', 'val' => $report['overview_red_flags']],
                ['icon' => '⚠', 'type' => 'strength', 'label' => 'Team Fit', 'val' => $report['overview_team_fit']],
                ['icon' => '⚠', 'type' => 'strength', 'label' => 'Qualification Match', 'val' => $report['overview_qualification_match']],
                ['icon' => '⚠', 'type' => 'strength', 'label' => 'Logistics', 'val' => $report['overview_logistics']],
                ['icon' => '✕', 'type' => 'red', 'label' => 'Follow-up Questions', 'val' => $report['overview_follow_up']],
                ['icon' => '⚠', 'type' => 'strength', 'label' => 'Recommended Hiring Decision', 'val' => $report['overview_recommendation']],
            ];
            if ($showIntegrityUi) {
                array_splice($sections, 4, 0, [[
                    'icon' => '✕', 'type' => 'red', 'label' => 'Cheating Detection',
                    'val' => $report['overview_cheating_detection'],
                ]]);
            }
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
            $sc_items = [
                ['label' => 'Overall Interview Score', 'score' => $report['overall_score'], 'desc' => 'AI-generated overall assessment.'],
                ['label' => 'Experience Score', 'score' => $report['experience_score'], 'desc' => 'Relevant experience and background evaluation.'],
                ['label' => 'Culture Score', 'score' => $report['culture_score'], 'desc' => 'Cultural alignment with company values.'],
                ['label' => 'Soft Skills Score', 'score' => $report['soft_skills_score'], 'desc' => 'Communication, teamwork, and interpersonal skills.'],
            ];
            foreach ($sc_items as $sc): ?>
            <div class="scorecard-section">
                <div class="scorecard-score-row">
                    <span class="scorecard-num <?= scorecard_tone($sc['score']) ?>"><?= e($sc['score'] ?? '–') ?></span>
                    <span class="scorecard-title"><?= e($sc['label']) ?></span>
                </div>
                <p><?= e($sc['desc']) ?></p>
            </div>
            <?php endforeach;
            $enhancedCriteria = [];
            if (!empty($enhanced['criterion_scores']) && is_array($enhanced['criterion_scores'])) {
                foreach ($enhanced['criterion_scores'] as $ec) {
                    if (!empty($ec['name'])) {
                        $enhancedCriteria[$ec['name']] = $ec;
                    }
                }
            }
            foreach ($criterionScores as $cs):
                $ec = isset($enhancedCriteria[$cs['criterion_name']]) ? $enhancedCriteria[$cs['criterion_name']] : [];
            ?>
            <div class="scorecard-section">
                <div class="scorecard-score-row">
                    <span class="scorecard-num <?= scorecard_tone($cs['score']) ?>"><?= e($cs['score'] ?? '–') ?></span>
                    <span class="scorecard-title"><?= e($cs['criterion_name']) ?></span>
                </div>
                <?php if (!empty($ec['evidence_notes'])): ?>
                <p style="font-size:0.82rem;color:var(--gray-600)"><?= e($ec['evidence_notes']) ?></p>
                <?php endif; ?>
                <?php if (!empty($ec['evidence_quotes']) && is_array($ec['evidence_quotes'])): ?>
                <ul style="font-size:0.8rem;color:var(--gray-500);margin:6px 0 0 16px;padding:0">
                    <?php foreach ($ec['evidence_quotes'] as $q): ?>
                    <li style="margin-bottom:4px">"<?= e($q) ?>"</li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
            <?php endforeach;
            foreach ($enhancedCriteria as $name => $ec):
                if (empty($ec['insufficient_evidence'])) continue;
            ?>
            <div class="scorecard-section" style="opacity:0.85">
                <div class="scorecard-score-row">
                    <span class="scorecard-num poor">–</span>
                    <span class="scorecard-title"><?= e($name) ?> <span style="font-size:0.72rem;color:var(--gray-500)">(insufficient evidence)</span></span>
                </div>
                <?php if (!empty($ec['evidence_notes'])): ?>
                <p style="font-size:0.82rem;color:var(--gray-600)"><?= e($ec['evidence_notes']) ?></p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- AI Notes -->
        <div class="rtab-panel" id="rtab-ainotes">
            <div class="ainotes-templates">
                <select id="ainotesMode" aria-label="AI notes view">
                    <option value="qa">Questions &amp; Answers</option>
                    <option value="summary">Summary</option>
                </select>
            </div>
            <div id="ainotes-qa-view">
            <?php if (empty($qaNotes)): ?>
            <p class="text-muted" style="font-size:0.85rem">No AI notes generated yet.</p>
            <?php else: foreach ($qaNotes as $qa): ?>
            <div class="qa-item">
                <div class="qa-question"><?= e($qa['question_text']) ?></div>
                <div class="qa-answer"><?= e($qa['answer_summary']) ?></div>
            </div>
            <?php endforeach; endif; ?>
            </div>
            <div id="ainotes-summary-view" style="display:none">
            <?php if (trim($aiSummaryText) === ''): ?>
            <p class="text-muted" style="font-size:0.85rem">No summary available yet.</p>
            <?php else: ?>
            <div class="overview-item" style="margin:0">
                <p style="white-space:pre-wrap;font-size:0.85rem;line-height:1.6"><?= e($aiSummaryText) ?></p>
            </div>
            <?php endif; ?>
            </div>
        </div>

        <!-- Transcript -->
        <div class="rtab-panel" id="rtab-transcript">
            <?php if (!empty($transcriptSegments)):
                foreach ($transcriptSegments as $seg): ?>
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
            <div id="reportVideoFallback" class="hidden" style="padding:12px;text-align:center;font-size:0.82rem;color:var(--text-muted)">
                <p style="margin-bottom:8px">Could not play inline.</p>
                <a href="#" id="reportVideoOpenLink" target="_blank" rel="noopener" class="btn btn-primary btn-sm">Open recording in OneDrive</a>
            </div>
            <?php elseif (!empty($externalMeetingUrl)): ?>
            <div class="video-placeholder-box" style="padding:24px;text-align:center">
                <p style="margin-bottom:12px">Meeting / recording link saved</p>
                <a href="<?= e($externalMeetingUrl) ?>" target="_blank" rel="noopener" class="btn btn-primary btn-sm">Open Recording Link</a>
            </div>
            <?php else: ?>
            <div class="video-placeholder-box"><?= config('app')['teams_auto_create_meetings'] ? 'Recording will appear here after the Teams interview.' : 'No video yet.' ?></div>
            <?php endif; ?>
        </div>

        <!-- Right tabs -->
        <div class="right-tabs">
            <button type="button" class="right-tab active" data-rtab2="info">Information</button>
            <?php if (!$isSharedReport): ?>
            <button type="button" class="right-tab" data-rtab2="chat">Team Chat</button>
            <?php endif; ?>
            <button type="button" class="right-tab" data-rtab2="morgan" style="display:none">Ask Morgan</button>
        </div>

        <!-- Information -->
        <div class="right-panel-body rtab2-panel active" id="rtab2-info">
            <dl class="info-dl">
                <dt>Job Title</dt>
                <dd><?= e($report['job_title']) ?><?php if (!$isSharedReport): ?> <a href="<?= url('jobs/' . $report['job_id'] . '/interviews') ?>" style="font-size:0.75rem" title="Open job">↗</a><?php endif; ?></dd>
                <dt>Department</dt><dd><?= e($report['department_name'] ?: '–') ?></dd>
                <dt>Hiring Panel</dt><dd><?= e($hiringManager ?: '–') ?></dd>
                <dt>Contact No.</dt><dd><?= e(trim(($report['phone_country_code'] ?? '') . ' ' . ($report['phone'] ?? ''))) ?: '–' ?></dd>
                <dt>Email ID</dt><dd><?= e($report['email']) ?></dd>
                <?php if (!$isSharedReport && !empty($report['resume_path'])): ?>
                <dt>Resume</dt><dd>
                    <a href="<?= resume_url($report['application_id']) ?>">Download</a>
                    <?php if (strtolower(pathinfo($report['resume_path'], PATHINFO_EXTENSION)) === 'pdf'): ?>
                    · <a href="<?= resume_url($report['application_id'], true) ?>" target="_blank">Preview</a>
                    <?php endif; ?>
                </dd>
                <?php endif; ?>
            </dl>
            <?php if (!$isSharedReport && config('app')['show_manual_video_upload']): ?>
            <div class="upload-form-box">
                <form method="POST" action="<?= url('interviews/' . $report['interview_id'] . '/upload') ?>" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="redirect_to" value="<?= e($reportUrl) ?>">
                    <label>Upload video (support)</label>
                    <input type="file" name="video" accept="video/*">
                    <button type="submit" class="btn btn-primary btn-sm btn-block">Upload &amp; Analyze</button>
                </form>
            </div>
            <?php endif; ?>
        </div>

        <?php if (!$isSharedReport): ?>
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
        <?php endif; ?>
    </div>
</div>

<?php if ($showIntegrityUi): ?>
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
<?php endif; ?>

<script>
var reportPageUrl = <?= json_encode($reportUrl) ?>;

function toggleReportMenu(btn) {
    var menu = btn.nextElementSibling;
    var open = !menu.classList.contains('hidden');
    document.querySelectorAll('.report-actions-menu').forEach(function(m) { m.classList.add('hidden'); });
    document.querySelectorAll('.report-actions-wrap > .icon-btn').forEach(function(b) { b.setAttribute('aria-expanded', 'false'); });
    if (!open) {
        menu.classList.remove('hidden');
        btn.setAttribute('aria-expanded', 'true');
    }
}

function copyReportLink() {
    var url = reportPageUrl;
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(function() {
            alert('Report link copied.');
        }).catch(function() {
            window.prompt('Copy report link:', url);
        });
    } else {
        window.prompt('Copy report link:', url);
    }
    document.querySelectorAll('.report-actions-menu').forEach(function(m) { m.classList.add('hidden'); });
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.report-actions-wrap')) {
        document.querySelectorAll('.report-actions-menu').forEach(function(m) { m.classList.add('hidden'); });
        document.querySelectorAll('.report-actions-wrap > .icon-btn').forEach(function(b) { b.setAttribute('aria-expanded', 'false'); });
    }
});

<?php if ($showIntegrityUi): ?>
function openIntegrityModal() { document.getElementById('integrityModal').classList.remove('hidden'); }
function closeIntegrityModal() { document.getElementById('integrityModal').classList.add('hidden'); }
document.getElementById('integrityModal').addEventListener('click', function(e) {
    if (e.target === this) closeIntegrityModal();
});
<?php endif; ?>

function activateReportTab(selector) {
    var tabBtn = document.querySelector(selector);
    if (tabBtn) tabBtn.click();
}

document.querySelectorAll('.report-tab').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.report-tab').forEach(function(b) { b.classList.remove('active'); });
        document.querySelectorAll('.rtab-panel').forEach(function(p) {
            p.classList.remove('active');
            p.style.display = 'none';
        });
        this.classList.add('active');
        var panel = document.getElementById('rtab-' + this.dataset.rtab);
        if (panel) {
            panel.classList.add('active');
            panel.style.display = 'block';
        }
    });
});
document.querySelectorAll('.rtab-panel').forEach(function(p) {
    if (!p.classList.contains('active')) p.style.display = 'none';
});

document.querySelectorAll('.right-tab').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.right-tab').forEach(function(b) { b.classList.remove('active'); });
        document.querySelectorAll('.rtab2-panel').forEach(function(p) { p.style.display = 'none'; });
        this.classList.add('active');
        var panel = document.getElementById('rtab2-' + this.dataset.rtab2);
        if (panel) panel.style.display = 'block';
    });
});

var ainotesMode = document.getElementById('ainotesMode');
if (ainotesMode) {
    ainotesMode.addEventListener('change', function() {
        var qa = document.getElementById('ainotes-qa-view');
        var summary = document.getElementById('ainotes-summary-view');
        var showSummary = this.value === 'summary';
        if (qa) qa.style.display = showSummary ? 'none' : 'block';
        if (summary) summary.style.display = showSummary ? 'block' : 'none';
    });
}

(function() {
    var params = new URLSearchParams(window.location.search);
    var tab = params.get('tab');
    if (tab === 'chat') {
        activateReportTab('.right-tab[data-rtab2="chat"]');
    }
    var section = params.get('section');
    if (section && ['overview', 'scorecard', 'ainotes', 'transcript'].indexOf(section) !== -1) {
        activateReportTab('.report-tab[data-rtab="' + section + '"]');
    }
})();
// OneDrive-backed video streams via same-origin /api/video/{id}/stream
(function() {
    var video = document.getElementById('reportInterviewVideo');
    var fallback = document.getElementById('reportVideoFallback');
    var openLink = document.getElementById('reportVideoOpenLink');
    if (!video || !video.dataset.playbackApi) return;
    fetch(video.dataset.playbackApi, { credentials: 'same-origin' })
        .then(function(r) { return r.ok ? r.json() : Promise.reject(); })
        .then(function(data) {
            if (data.open_url && openLink) openLink.href = data.open_url;
            if (data.url) {
                video.src = data.url;
                video.addEventListener('error', function() {
                    if (fallback) fallback.classList.remove('hidden');
                });
            }
        })
        .catch(function() {
            if (fallback) fallback.classList.remove('hidden');
        });
})();
</script>
