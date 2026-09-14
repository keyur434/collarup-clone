<div class="page-header">
    <div>
        <h1>Add Candidate</h1>
        <p>Assign to a job role and pipeline stage.</p>
    </div>
</div>

<form method="POST" action="<?= url('candidates/create') ?>" enctype="multipart/form-data" class="card form-card">
    <?= csrf_field() ?>
    <div class="form-grid cols-3">
        <div class="form-group"><label>Job Title *</label>
            <select name="job_id" id="job_id" required onchange="loadStages(this.value)">
                <option value="">Select job</option>
                <?php foreach ($jobs as $j): ?>
                <option value="<?= $j['id'] ?>" <?= $selectedJobId === $j['id'] ? 'selected' : '' ?>><?= e($j['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group"><label>Pipeline Stage *</label>
            <select name="pipeline_id" id="pipeline_id" required>
                <option value="">Select stage</option>
            </select>
        </div>
        <div class="form-group"><label>Source</label>
            <select name="source_id">
                <option value="">Select source</option>
                <?php foreach ($sources as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="tabs" style="margin-top:8px">
        <button type="button" class="tab-btn active" data-tab="resume">Resume Upload</button>
        <button type="button" class="tab-btn" data-tab="individual">Individual</button>
        <button type="button" class="tab-btn" data-tab="bulk">Bulk CSV</button>
    </div>

    <div class="tab-panel active" id="tab-resume">
        <input type="hidden" name="tab" value="resume" id="active_tab">
        <p class="hint">PDF, DOC, or DOCX — max 10 MB each</p>
        <div class="upload-zone">
            <p style="font-weight:600;margin-bottom:8px">Drop files here or browse</p>
            <input type="file" name="resumes[]" multiple accept=".pdf,.doc,.docx">
        </div>
    </div>
    <div class="tab-panel" id="tab-individual">
        <div class="form-grid cols-2">
            <div class="form-group"><label>First Name *</label><input name="first_name" placeholder="Jane"></div>
            <div class="form-group"><label>Last Name *</label><input name="last_name" placeholder="Doe"></div>
            <div class="form-group"><label>Email *</label><input type="email" name="email" id="candidate_email" placeholder="jane@email.com" required onblur="checkCandidateEmail()"></div>
            <div class="form-group"><label>Phone</label><div style="display:flex;gap:8px"><input name="phone_country_code" value="+91" style="width:72px;flex-shrink:0"><input name="phone" placeholder="9876543210" style="flex:1"></div></div>
        </div>
        <div class="upload-zone"><input type="file" name="resume" accept=".pdf"><p class="hint" style="margin-top:8px;margin-bottom:0">Optional resume (PDF)</p></div>
    </div>
    <div class="tab-panel" id="tab-bulk">
        <p class="hint">Columns: First Name, Last Name, Email, Country Code, Phone Number, Source, Resume URL</p>
        <div class="upload-zone"><input type="file" name="csv" accept=".csv"></div>
    </div>

    <div class="form-actions">
        <a href="<?= url('candidates') ?>" class="btn btn-outline">Cancel</a>
        <button type="submit" class="btn btn-primary">Add Candidate</button>
    </div>
</form>
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($selectedJobId): ?>loadStages('<?= e($selectedJobId) ?>', '<?= e($selectedPipelineId) ?>');<?php endif; ?>
});
function checkCandidateEmail() {
    var email = document.getElementById('candidate_email');
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
