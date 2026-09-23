<div class="drawer-overlay hidden" id="registerTeamsOverlay">
    <div class="drawer-backdrop-layer" onclick="closeRegisterTeamsDrawer()"></div>
    <div class="drawer-panel">
        <div class="drawer-head">
            <h3>Link Teams meeting</h3>
            <button type="button" class="icon-btn" onclick="closeRegisterTeamsDrawer()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="drawer-body">
            <p class="hint" style="margin-bottom:14px">Schedule in <strong>Keka</strong> as usual. Paste the <strong>Teams join link</strong> from that invite here — recording and report run automatically after the call.</p>
            <form id="register-teams-form" method="POST">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label class="form-label">Teams join link *</label>
                    <input type="url" class="form-control" name="meeting_url" id="r_meeting_url" required placeholder="https://teams.microsoft.com/l/meetup-join/...">
                    <p class="hint" style="margin-top:6px">Not the “meeting recap” / recording link.</p>
                </div>
                <div class="form-grid-3">
                    <div class="form-group"><label class="form-label">Interview date *</label><input type="date" class="form-control" name="scheduled_date" id="r_date" required></div>
                    <div class="form-group"><label class="form-label">Start</label><input type="time" class="form-control" name="scheduled_start" id="r_start"></div>
                    <div class="form-group"><label class="form-label">End</label><input type="time" class="form-control" name="scheduled_end" id="r_end"></div>
                </div>
                <div class="form-group">
                    <label class="form-label">Meeting host (work email)</label>
                    <input type="email" class="form-control" name="organizer_email" id="r_organizer_email" placeholder="recruiter@company.com" value="<?= e(auth_user()['email'] ?? '') ?>">
                    <p class="hint" style="margin-top:6px">Who scheduled the meeting in Keka / Teams (must match cloud recording owner).</p>
                </div>
                <div class="form-actions" style="border:none;padding-top:0;margin-top:16px">
                    <button type="button" class="btn btn-outline" onclick="closeRegisterTeamsDrawer()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save link</button>
                </div>
            </form>
        </div>
    </div>
</div>
