<?php $breadcrumbs = nav_trail_job($job, 'Add Candidate(s)'); ?>
<div class="page-header">
    <div>
        <h1>Add Candidate(s)</h1>
        <p>Optional — upload resumes for <?= e($job['title']) ?>.</p>
    </div>
</div>
<?php include BASE_PATH . '/views/partials/wizard_steps.php'; ?>
<form method="POST" action="<?= url('jobs/create/candidates') ?>" enctype="multipart/form-data" class="card form-card" id="wizardCandidateForm">
    <?= csrf_field() ?>
    <input type="hidden" name="job_id" value="<?= e($job['id']) ?>" id="wizard_job_id">
    <input type="hidden" name="tab" value="resume" id="active_tab">
    <div class="form-grid cols-2">
        <div class="form-group"><label>Pipeline stage *</label>
            <select name="pipeline_id" required>
                <?php foreach ($pipelineStages as $s): ?>
                <option value="<?= $s['id'] ?>" <?= ($defaultStageId ?? '') === $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group"><label>Source</label>
            <select name="source_id" id="wizard_source_id">
                <option value="">Select source</option>
                <?php foreach ($sources as $s): ?>
                <option value="<?= $s['id'] ?>" <?= !empty($defaultSourceId) && (int) $defaultSourceId === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="tabs" style="margin-top:8px">
        <button type="button" class="tab-btn active" data-tab="resume">Resume upload</button>
        <button type="button" class="tab-btn" data-tab="bulk">Bulk CSV</button>
    </div>
    <div class="tab-panel active" id="tab-resume">
        <p class="hint">Multiple files = one candidate each. One compiled PDF with many CVs can be split automatically (several emails detected) or via the checkbox below.</p>
        <div class="upload-zone">
            <input type="file" name="resumes[]" id="wizard_resumes_multi" multiple accept=".pdf,.doc,.docx">
        </div>
        <label style="display:flex;align-items:center;gap:8px;margin-top:10px;cursor:pointer;font-size:0.82rem">
            <input type="checkbox" name="split_compiled_pdf" value="1">
            Split compiled PDF into separate candidates
        </label>
    </div>
    <div class="tab-panel" id="tab-bulk">
        <p class="hint">Columns: First Name, Last Name, Email, Country Code, Phone Number, Source, Resume URL</p>
        <div class="upload-zone"><input type="file" name="csv" accept=".csv"></div>
    </div>

    <?php include BASE_PATH . '/views/partials/bulk_import_preview.php'; ?>

    <div id="wizardDetailsWrap">
        <?php include BASE_PATH . '/views/partials/candidate_details_card.php'; ?>
    </div>

    <div class="form-actions" style="flex-wrap:wrap;gap:8px">
        <button type="submit" name="skip_candidates" value="1" class="btn btn-outline">Skip — finish job</button>
        <button type="button" class="btn btn-outline" id="btnPreviewBulk" style="display:none">Preview import</button>
        <button type="submit" class="btn btn-primary" id="btnSubmitCandidate">Add &amp; finish</button>
    </div>
</form>
<script>
document.addEventListener('DOMContentLoaded', function() {
    initCandidateResumeParse({
        formId: 'wizardCandidateForm',
        parseUrl: '<?= url('api/candidates/parse-resume') ?>',
        multiInputId: 'wizard_resumes_multi',
        sourceSelectId: 'wizard_source_id',
        naukriSourceName: 'Naukri'
    });
    var form = document.getElementById('wizardCandidateForm');
    form.querySelectorAll('.tab-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            form.querySelectorAll('.tab-btn').forEach(function(b) { b.classList.remove('active'); });
            form.querySelectorAll('.tab-panel').forEach(function(p) { p.classList.remove('active'); });
            btn.classList.add('active');
            var panel = document.getElementById('tab-' + btn.dataset.tab);
            if (panel) panel.classList.add('active');
            document.getElementById('active_tab').value = btn.dataset.tab;
            document.getElementById('wizardDetailsWrap').style.display = btn.dataset.tab === 'bulk' ? 'none' : 'block';
        });
    });
    initBulkImportPreview({
        formId: 'wizardCandidateForm',
        previewUrl: '<?= url('api/candidates/preview-bulk-import') ?>',
        jobSelectId: 'wizard_job_id',
        pipelineSelectId: 'pipeline_id',
        fileInputId: 'wizard_resumes_multi',
        splitCheckboxName: 'split_compiled_pdf'
    });
    form.addEventListener('submit', function(e) {
        if (e.submitter && e.submitter.name === 'skip_candidates') return;
        var tab = document.getElementById('active_tab').value;
        ['wizard_job_id', 'pipeline_id', 'wizard_source_id'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.disabled = false;
        });
        form.querySelectorAll('.tab-panel').forEach(function(panel) {
            var active = panel.classList.contains('active');
            panel.querySelectorAll('input, select').forEach(function(el) {
                if (el.id === 'bulk_items' || el.id === 'bulk_preview_confirmed') return;
                el.disabled = !active;
            });
        });
        if (tab !== 'bulk') {
            var w = document.getElementById('wizardDetailsWrap');
            if (w && w.style.display !== 'none') {
                w.querySelectorAll('input').forEach(function(el) { el.disabled = false; });
            }
        }
        if (tab === 'resume' && window.bulkImportRequiresPreview && window.bulkImportRequiresPreview()) {
            if (!document.getElementById('bulk_preview_confirmed').value) {
                e.preventDefault();
                alert('Click “Preview import” first, review the table, then submit.');
                return;
            }
            if (!syncBulkItemsHiddenField()) {
                e.preventDefault();
                alert('Preview is out of date — run Preview import again.');
            }
        }
    });
});
</script>
