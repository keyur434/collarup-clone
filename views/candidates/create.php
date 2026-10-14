<?php
$trailJob = null;
if (!empty($selectedJobId)) {
    foreach ($jobs as $j) {
        if ($j['id'] === $selectedJobId) {
            $trailJob = $j;
            break;
        }
    }
}
$breadcrumbs = $trailJob
    ? nav_trail_job(['id' => $trailJob['id'], 'title' => $trailJob['title']], 'Add Candidate')
    : nav_trail_dashboard('Candidates', url('candidates'), 'Add Candidate');
?>
<div class="page-header">
    <div>
        <h1>Add Candidate</h1>
        <p>Assign to a job role and pipeline stage.</p>
    </div>
</div>

<form method="POST" action="<?= url('candidates/create') ?>" enctype="multipart/form-data" class="card form-card" id="candidateCreateForm">
    <?= csrf_field() ?>
    <input type="hidden" name="tab" value="resume" id="active_tab">
    <div class="form-grid cols-3">
        <div class="form-group"><label>Job Title *</label>
            <select name="job_id" id="job_id" required onchange="loadStages(this.value)">
                <option value="">Select job</option>
                <?php foreach ($jobs as $j): ?>
                <option value="<?= $j['id'] ?>" <?= $selectedJobId === $j['id'] ? 'selected' : '' ?>><?= e(job_select_option_label($j)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group"><label>Pipeline Stage *</label>
            <select name="pipeline_id" id="pipeline_id" required>
                <option value="">Select stage</option>
            </select>
        </div>
        <div class="form-group"><label>Source</label>
            <select name="source_id" id="candidate_source_id">
                <option value="">Select source</option>
                <?php foreach ($sources as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="tabs" style="margin-top:8px">
        <button type="button" class="tab-btn active" data-tab="resume">Resume Upload</button>
        <button type="button" class="tab-btn" data-tab="individual">Manual entry</button>
        <button type="button" class="tab-btn" data-tab="bulk">Bulk CSV</button>
    </div>

    <div class="tab-panel active" id="tab-resume">
        <p class="hint">PDF, DOC, or DOCX — max <?= (int) config('app')['resume_max_mb'] ?> MB each.</p>
        <ul class="hint" style="margin:0 0 12px 1.1rem;font-size:0.82rem;line-height:1.45">
            <li><strong>One file + details below</strong> — review parsed fields, then save (single candidate).</li>
            <li><strong>Several files or compiled PDF</strong> — click <strong>Preview import</strong>, edit any empty fields, then <strong>Add candidates</strong>.</li>
            <li><strong>One CV only</strong> — single file: fields below fill automatically; edit, then Add Candidate.</li>
        </ul>
        <div class="upload-zone">
            <p style="font-weight:600;margin-bottom:8px">Drop files here or browse</p>
            <input type="file" name="resumes[]" id="resumes_multi" multiple accept=".pdf,.doc,.docx">
        </div>
        <label class="filter-select" style="display:flex;align-items:center;gap:8px;margin-top:10px;cursor:pointer;font-size:0.82rem">
            <input type="checkbox" name="split_compiled_pdf" value="1">
            Split compiled PDFs into separate candidates (uses tools/poppler/bin — see tools/poppler/README.md)
        </label>
    </div>
    <div class="tab-panel" id="tab-individual">
        <p class="hint">Enter details below, or attach a resume to parse into the same fields.</p>
        <div class="upload-zone" style="margin-bottom:12px">
            <input type="file" name="resume" id="resume_single" accept=".pdf,.doc,.docx">
            <p class="hint" style="margin-top:8px;margin-bottom:0">Optional resume</p>
        </div>
    </div>
    <div class="tab-panel" id="tab-bulk">
        <p class="hint">Columns: First Name, Last Name, Email, Country Code, Phone Number, Source, Resume URL</p>
        <div class="upload-zone"><input type="file" name="csv" accept=".csv"></div>
    </div>

    <?php include BASE_PATH . '/views/partials/bulk_import_preview.php'; ?>

    <div id="candidateDetailsWrap">
        <?php $emailBlurHandler = 'checkCandidateEmail()'; include BASE_PATH . '/views/partials/candidate_details_card.php'; ?>
    </div>

    <div class="form-actions" style="flex-wrap:wrap;gap:8px">
        <a href="<?= url('candidates') ?>" class="btn btn-outline">Cancel</a>
        <button type="button" class="btn btn-outline" id="btnPreviewBulk" style="display:none">Preview import</button>
        <button type="submit" class="btn btn-primary" id="btnSubmitCandidate">Add Candidate</button>
    </div>
</form>
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($selectedJobId): ?>loadStages('<?= e($selectedJobId) ?>', '<?= e($selectedPipelineId) ?>');<?php endif; ?>
    syncCandidateCreateTab();
    initCandidateResumeParse({
        formId: 'candidateCreateForm',
        parseUrl: '<?= url('api/candidates/parse-resume') ?>',
        multiInputId: 'resumes_multi',
        singleInputId: 'resume_single',
        sourceSelectId: 'candidate_source_id',
        naukriSourceName: 'Naukri'
    });
    var form = document.getElementById('candidateCreateForm');
    if (form) {
        form.addEventListener('submit', onCandidateCreateSubmit);
    }
    initBulkImportPreview({
        formId: 'candidateCreateForm',
        previewUrl: '<?= url('api/candidates/preview-bulk-import') ?>',
        jobSelectId: 'job_id',
        pipelineSelectId: 'pipeline_id',
        fileInputId: 'resumes_multi',
        splitCheckboxName: 'split_compiled_pdf'
    });
});
document.addEventListener('click', function(e) {
    if (e.target.closest('#candidateCreateForm [data-tab]')) {
        setTimeout(syncCandidateCreateTab, 0);
    }
});
function syncCandidateCreateTab() {
    var form = document.getElementById('candidateCreateForm');
    if (!form) return;
    var tabBtn = form.querySelector('.tab-btn.active');
    var tab = tabBtn ? tabBtn.dataset.tab : 'resume';
    var hidden = document.getElementById('active_tab');
    if (hidden) hidden.value = tab;
    var wrap = document.getElementById('candidateDetailsWrap');
    if (wrap && tab !== 'resume') wrap.style.display = tab === 'bulk' ? 'none' : 'block';
    if (tab === 'resume' && typeof window.bulkImportRequiresPreview === 'function' && window.bulkImportRequiresPreview()) {
        if (wrap) wrap.style.display = 'none';
    }
    ['parsed_first_name', 'parsed_last_name', 'parsed_email'].forEach(function(id) {
        var el = document.getElementById(id);
        if (!el) return;
        if (tab === 'individual') el.setAttribute('required', 'required');
        else el.removeAttribute('required');
    });
}
function prepareCandidateCreateSubmit() {
    var form = document.getElementById('candidateCreateForm');
    if (!form) return;
    syncCandidateCreateTab();
    ['job_id', 'pipeline_id', 'candidate_source_id'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.disabled = false;
    });
    form.querySelectorAll('.tab-panel').forEach(function(panel) {
        var active = panel.classList.contains('active');
        panel.querySelectorAll('input, select, textarea').forEach(function(el) {
            if (el.id === 'bulk_items' || el.id === 'bulk_preview_confirmed') return;
            el.disabled = !active;
        });
    });
    var tab = document.getElementById('active_tab').value;
    if (tab === 'resume' || tab === 'individual') {
        var wrap = document.getElementById('candidateDetailsWrap');
        if (wrap && wrap.style.display !== 'none') {
            wrap.querySelectorAll('input').forEach(function(el) { el.disabled = false; });
        }
    }
}

function onCandidateCreateSubmit(e) {
    prepareCandidateCreateSubmit();
    var submitBtn = document.getElementById('btnSubmitCandidate');
    if (submitBtn && submitBtn.disabled) {
        e.preventDefault();
        alert(submitBtn.title || 'Complete Preview import before adding candidates.');
        return;
    }
    var tab = document.getElementById('active_tab').value;
    if (tab === 'resume' && window.bulkImportRequiresPreview && window.bulkImportRequiresPreview()) {
        if (!document.getElementById('bulk_preview_confirmed').value) {
            e.preventDefault();
            alert('Click “Preview import” first, review the table, then “Add candidates”.');
            return;
        }
        if (!syncBulkItemsHiddenField()) {
            e.preventDefault();
            alert('Preview is out of date — run Preview import again after changing files.');
            return;
        }
    }
}
function checkCandidateEmail() {
    var email = document.getElementById('parsed_email');
    var job = document.getElementById('job_id');
    var warn = document.getElementById('email-dup-warn');
    if (!email || !email.value || !job || !job.value) return;
    fetch('<?= url('api/candidates/check-email') ?>?email=' + encodeURIComponent(email.value) + '&job_id=' + encodeURIComponent(job.value))
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!warn) {
                warn = document.createElement('p');
                warn.id = 'email-dup-warn';
                warn.className = 'hint';
                email.parentNode.appendChild(warn);
            }
            if (!data.duplicate) { warn.textContent = ''; return; }
            if (data.on_job) {
                warn.innerHTML = 'Already on this job. <a href="<?= url('applications') ?>/' + data.application_id + '/manage">Open manage</a>';
                warn.style.color = 'var(--red)';
            } else {
                warn.textContent = 'Existing candidate "' + data.name + '" — submitting will add them to this job.';
                warn.style.color = 'var(--amber)';
            }
        });
}
</script>
