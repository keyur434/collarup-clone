<div class="drawer-overlay hidden" id="uploadDrawerOverlay">
    <div class="drawer-backdrop-layer" onclick="closeUploadDrawer()"></div>
    <div class="drawer-panel">
        <div class="drawer-head">
            <h3>Upload Interview</h3>
            <button type="button" class="icon-btn" onclick="closeUploadDrawer()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="drawer-body">
            <p class="hint" style="margin-bottom:14px">Upload a recorded interview video for AI analysis, or save a Zoom / Teams / Meet link.</p>
            <form id="upload-drawer-form" method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label class="form-label">Interview Video</label>
                    <input type="file" class="form-control" name="video" accept="video/mp4,video/webm,video/quicktime,.mp4,.webm,.mov,.mkv">
                    <p class="hint" style="margin-top:6px">MP4, MOV, WebM — max <?= (int) config('app')['upload_max_mb'] ?> MB<?php $phpMb = php_upload_limit_mb(); if ($phpMb > 0): ?> (PHP server limit: <?= $phpMb ?> MB)<?php endif; ?></p>
                </div>
                <div class="form-group">
                    <label class="form-label">Meeting / Recording Link</label>
                    <input type="url" class="form-control" name="meeting_url" id="upload_meeting_url" placeholder="https://zoom.us/... or direct .mp4 link">
                </div>
                <div class="form-actions" style="border:none;padding-top:0;margin-top:16px">
                    <button type="button" class="btn btn-outline" onclick="closeUploadDrawer()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload &amp; Analyze</button>
                </div>
            </form>
        </div>
    </div>
</div>
