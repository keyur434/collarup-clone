<div class="candidate-parse-card" id="candidateDetailsCard" style="margin-top:16px;padding:16px;border:1px solid var(--border);border-radius:var(--radius,8px);background:var(--surface-elevated,var(--card-bg))">
    <h3 style="font-size:0.95rem;margin:0 0 8px">Candidate details</h3>
    <p class="hint" id="resumeParseStatus" style="margin-bottom:12px">Upload a resume to auto-fill these fields. You can edit before saving.</p>
    <div class="form-grid cols-2">
        <div class="form-group"><label>First Name</label><input name="first_name" id="parsed_first_name" placeholder="First name" autocomplete="off"></div>
        <div class="form-group"><label>Last Name</label><input name="last_name" id="parsed_last_name" placeholder="Last name" autocomplete="off"></div>
        <div class="form-group"><label>Email</label><input type="email" name="email" id="parsed_email" placeholder="email@example.com" autocomplete="off"<?= !empty($emailBlurHandler) ? ' onblur="' . e($emailBlurHandler) . '"' : '' ?>></div>
        <div class="form-group"><label>Phone</label>
            <div style="display:flex;gap:8px">
                <input name="phone_country_code" id="parsed_phone_cc" value="+91" style="width:72px;flex-shrink:0">
                <input name="phone" id="parsed_phone" placeholder="9876543210" style="flex:1">
            </div>
        </div>
        <div class="form-group"><label>Years of experience</label><input type="number" step="0.1" min="0" name="years_experience" id="parsed_years_experience" placeholder="e.g. 5"></div>
        <div class="form-group"><label>Current company</label><input name="current_company" id="parsed_current_company" placeholder="Employer"></div>
    </div>
</div>
