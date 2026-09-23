<div class="drawer-overlay hidden" id="drawerOverlay">
    <div class="drawer-backdrop-layer" onclick="closeDrawer()"></div>
    <div class="drawer-panel">
        <div class="drawer-head">
            <h3>Schedule Interview</h3>
            <button type="button" class="icon-btn" onclick="closeDrawer()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="drawer-body">
            <?php if (config('app')['teams_auto_create_meetings']): ?>
            <p class="hint" style="margin-bottom:14px">Pick date and time — we create the <strong>Teams meeting<?= config('app')['teams_shared_organizer'] ? ' (company HR account)' : ' under your login' ?></strong> and email the candidate. After the interview, the recording is picked up automatically (no upload).</p>
            <?php else: ?>
            <p class="hint" style="margin-bottom:14px">Set date and time, then add a meeting link if needed.</p>
            <?php endif; ?>
            <form id="drawer-form" method="POST">
                <?= csrf_field() ?>
                <div class="form-grid-2">
                    <div class="form-group"><label class="form-label">First Name</label><input class="form-control" name="first_name" id="d_first_name"></div>
                    <div class="form-group"><label class="form-label">Last Name</label><input class="form-control" name="last_name" id="d_last_name"></div>
                </div>
                <div class="form-group"><label class="form-label">Email *</label><input type="email" class="form-control" name="email" id="d_email" required></div>
                <div class="form-grid-3">
                    <div class="form-group"><label class="form-label">Date *</label><input type="date" class="form-control" name="scheduled_date" id="d_date" required></div>
                    <div class="form-group"><label class="form-label">Start</label><input type="time" class="form-control" name="scheduled_start" id="d_start"></div>
                    <div class="form-group"><label class="form-label">End</label><input type="time" class="form-control" name="scheduled_end" id="d_end"></div>
                </div>
                <?php if (!config('app')['teams_auto_create_meetings']): ?>
                <div class="form-group"><label class="form-label">Meeting Link</label><input class="form-control" name="meeting_url" id="d_meeting_url" placeholder="Teams / Zoom / Meet URL"></div>
                <?php else: ?>
                <details style="margin-top:8px;font-size:0.82rem">
                    <summary style="cursor:pointer;color:var(--text-muted)">Use an external meeting link instead</summary>
                    <div class="form-group" style="margin-top:10px">
                        <input class="form-control" name="meeting_url" id="d_meeting_url" placeholder="Paste Teams link only if not using auto-create">
                    </div>
                </details>
                <?php endif; ?>
                <input type="hidden" name="stage_id" id="d_stage_id">
                <input type="hidden" name="phone" id="d_phone">
                <div class="form-group" style="margin-top:12px">
                    <label><input type="checkbox" name="send_invite_email" value="1" id="d_send_invite" checked> Send invite email to candidate</label>
                </div>
                <div class="form-actions" style="border:none;padding-top:0;margin-top:16px">
                    <button type="button" class="btn btn-outline" onclick="closeDrawer()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="d_schedule_submit">Save &amp; send invite</button>
                </div>
            </form>
        </div>
    </div>
</div>
