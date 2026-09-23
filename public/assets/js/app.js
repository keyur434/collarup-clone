document.addEventListener('DOMContentLoaded', function() {
    initThemeToggle();
    initSidebar();
    autoHideAlerts();
});

/* ── Light / dark theme ── */
function initThemeToggle() {
    var root = document.documentElement;
    var buttons = document.querySelectorAll('#themeToggle, .theme-toggle');
    if (!buttons.length) return;

    function isDark() {
        return root.getAttribute('data-theme') === 'dark';
    }

    function apply(theme) {
        var dark = theme === 'dark';
        if (dark) {
            root.setAttribute('data-theme', 'dark');
        } else {
            root.removeAttribute('data-theme');
        }
        try {
            localStorage.setItem('collarup-theme', dark ? 'dark' : 'light');
        } catch (e) {}
        buttons.forEach(function(btn) {
            btn.setAttribute('aria-pressed', dark ? 'true' : 'false');
            btn.title = dark ? 'Switch to light mode' : 'Switch to dark mode';
        });
    }

    apply(isDark() ? 'dark' : 'light');

    buttons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            apply(isDark() ? 'light' : 'dark');
        });
    });
}

/* ── Sidebar (mobile toggle) ── */
function initSidebar() {
    var sidebar = document.getElementById('sidebar');
    var backdrop = document.getElementById('sidebarBackdrop');
    var toggle = document.getElementById('menuToggle');
    if (!sidebar || !toggle) return;

    function openSidebar() {
        sidebar.classList.add('open');
        if (backdrop) backdrop.classList.add('visible');
        document.body.style.overflow = 'hidden';
    }
    function closeSidebar() {
        sidebar.classList.remove('open');
        if (backdrop) backdrop.classList.remove('visible');
        document.body.style.overflow = '';
    }
    toggle.addEventListener('click', function() {
        sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
    });
    if (backdrop) backdrop.addEventListener('click', closeSidebar);
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 1024) closeSidebar();
    });
}

/* ── Auto-hide alerts after 5s ── */
function autoHideAlerts() {
    document.querySelectorAll('.alert').forEach(function(el) {
        setTimeout(function() {
            el.style.transition = 'opacity 0.3s';
            el.style.opacity = '0';
            setTimeout(function() { el.remove(); }, 300);
        }, 5000);
    });
}

/* ── Location selects (country → state → city) ── */
function initLocationSelects(defaults) {
    var country = document.getElementById('country_id');
    var state = document.getElementById('state_id');
    var city = document.getElementById('city_id');
    if (!country) return;

    function loadStates(countryId, thenCity) {
        fetch(getBase() + '/api/master/states?country_id=' + countryId)
            .then(function(r) { return r.json(); })
            .then(function(states) {
                state.innerHTML = '<option value="">Select State</option>';
                states.forEach(function(s) {
                    var opt = document.createElement('option');
                    opt.value = s.id; opt.textContent = s.name;
                    if (defaults && defaults.state == s.id) opt.selected = true;
                    state.appendChild(opt);
                });
                if (thenCity) loadCities(state.value, defaults && defaults.city);
            });
    }
    function loadCities(stateId, selectedCity) {
        fetch(getBase() + '/api/master/cities?state_id=' + stateId)
            .then(function(r) { return r.json(); })
            .then(function(cities) {
                city.innerHTML = '<option value="">Select City</option>';
                cities.forEach(function(c) {
                    var opt = document.createElement('option');
                    opt.value = c.id; opt.textContent = c.name;
                    if (selectedCity == c.id) opt.selected = true;
                    city.appendChild(opt);
                });
            });
    }
    country.addEventListener('change', function() { loadStates(this.value, false); });
    state.addEventListener('change', function() { loadCities(this.value); });
    if (defaults && defaults.country) loadStates(defaults.country, true);
}

/* ── Load stages for a job (candidate create) ── */
function loadStages(jobId, selectedId) {
    var select = document.getElementById('pipeline_id');
    if (!select || !jobId) return;
    fetch(getBase() + '/api/jobs/' + jobId + '/stages')
        .then(function(r) { return r.json(); })
        .then(function(stages) {
            select.innerHTML = '<option value="">Select Stage</option>';
            stages.forEach(function(s) {
                var opt = document.createElement('option');
                opt.value = s.id; opt.textContent = s.name;
                if (selectedId && selectedId === s.id) opt.selected = true;
                select.appendChild(opt);
            });
        });
}

/* ── Questions wizard ── */
function addQuestion() {
    var list = document.getElementById('questions-list');
    var div = document.createElement('div');
    div.className = 'form-group';
    div.innerHTML = '<textarea name="questions[]" class="form-control" rows="3" placeholder="Interview question"></textarea>';
    list.appendChild(div);
    div.querySelector('textarea').focus();
}

/* ── Interview drawer ── */
function openInterviewDrawer(id) {
    fetch(getBase() + '/interviews/' + id + '/details')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var iv = data.interview;
            var form = document.getElementById('drawer-form');
            if (form) form.action = getBase() + '/interviews/' + id + '/details';
            setVal('d_first_name', iv.first_name);
            setVal('d_last_name', iv.last_name);
            setVal('d_email', iv.email);
            setVal('d_meeting_url', iv.meeting_url);
            setVal('d_date', iv.scheduled_date ? String(iv.scheduled_date).split(' ')[0] : '');
            setVal('d_start', iv.scheduled_start);
            setVal('d_end', iv.scheduled_end);
            setVal('d_stage_id', iv.stage_id);
            setVal('d_phone', iv.phone);

            var sendCb = document.getElementById('d_send_invite');
            var submitBtn = document.getElementById('d_schedule_submit');
            function syncInviteLabel() {
                if (submitBtn && sendCb) {
                    submitBtn.textContent = sendCb.checked ? 'Save & send invite' : 'Save without email';
                }
            }
            if (sendCb) {
                sendCb.checked = true;
                sendCb.onchange = syncInviteLabel;
                syncInviteLabel();
            }

            var overlay = document.getElementById('drawerOverlay');
            if (overlay) overlay.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        });
}

function closeDrawer() {
    var overlay = document.getElementById('drawerOverlay');
    if (overlay) overlay.classList.add('hidden');
    document.body.style.overflow = '';
}

function openUploadDrawer(id) {
    var form = document.getElementById('upload-drawer-form');
    if (form) form.action = getBase() + '/interviews/' + id + '/upload';
    var overlay = document.getElementById('uploadDrawerOverlay');
    if (overlay) {
        overlay.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function closeUploadDrawer() {
    var overlay = document.getElementById('uploadDrawerOverlay');
    if (overlay) overlay.classList.add('hidden');
    document.body.style.overflow = '';
}

function openRegisterTeamsDrawer(id) {
    fetch(getBase() + '/interviews/' + id + '/details')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var iv = data.interview;
            var form = document.getElementById('register-teams-form');
            if (form) form.action = getBase() + '/interviews/' + id + '/register-teams';
            setVal('r_meeting_url', iv.meeting_url);
            setVal('r_date', iv.scheduled_date ? String(iv.scheduled_date).split(' ')[0] : '');
            setVal('r_start', iv.scheduled_start);
            setVal('r_end', iv.scheduled_end);
            if (iv.default_organizer_email) {
                setVal('r_organizer_email', iv.default_organizer_email);
            }
            var overlay = document.getElementById('registerTeamsOverlay');
            if (overlay) overlay.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        });
}

function closeRegisterTeamsDrawer() {
    var overlay = document.getElementById('registerTeamsOverlay');
    if (overlay) overlay.classList.add('hidden');
    document.body.style.overflow = '';
}

function setVal(id, val) {
    var el = document.getElementById(id);
    if (el) el.value = val || '';
}

/* ── Tab system (generic, used by candidate create wizard) ── */
document.addEventListener('click', function(e) {
    var btn = e.target.closest('[data-tab]');
    if (!btn || btn.type === 'submit') return;
    var tab = btn.dataset.tab;
    var scope = btn.closest('form') || document;
    scope.querySelectorAll('[data-tab]').forEach(function(b) { b.classList.remove('active'); });
    scope.querySelectorAll('.tab-panel').forEach(function(p) { p.classList.remove('active'); });
    btn.classList.add('active');
    var panel = scope.querySelector('#tab-' + tab) || document.getElementById('tab-' + tab);
    if (panel) panel.classList.add('active');
    var hidden = scope.querySelector('#active_tab');
    if (hidden) hidden.value = tab;
});

/* ── Resume parse → candidate details card ── */
function initCandidateResumeParse(opts) {
    var form = document.getElementById(opts.formId);
    if (!form) return;
    var statusEl = document.getElementById('resumeParseStatus');
    var tokenInput = form.querySelector('input[name="_token"]');

    function setStatus(msg, ok) {
        if (!statusEl) return;
        statusEl.textContent = msg;
        statusEl.style.color = ok ? 'var(--green, #16a34a)' : '';
    }

    function fillParsed(parsed, filename) {
        if (!parsed) return;
        setVal('parsed_first_name', parsed.first_name);
        setVal('parsed_last_name', parsed.last_name);
        setVal('parsed_email', parsed.email);
        setVal('parsed_phone', parsed.phone);
        setVal('parsed_years_experience', parsed.years_experience != null ? parsed.years_experience : '');
        setVal('parsed_current_company', parsed.current_company);
        if (filename && opts.sourceSelectId && opts.naukriSourceName && filename.toLowerCase().indexOf('naukri') !== -1) {
            var sel = document.getElementById(opts.sourceSelectId);
            if (sel) {
                for (var i = 0; i < sel.options.length; i++) {
                    if (sel.options[i].text === opts.naukriSourceName) {
                        sel.selectedIndex = i;
                        break;
                    }
                }
            }
        }
        setStatus('Details filled from resume — review before saving.', true);
    }

    function uploadAndParse(file) {
        if (!file) return;
        setStatus('Parsing resume…');
        var fd = new FormData();
        fd.append('resume', file);
        if (tokenInput) fd.append('_token', tokenInput.value);
        fetch(opts.parseUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.error) {
                    setStatus(data.error);
                    return;
                }
                fillParsed(data.parsed, data.filename || file.name);
            })
            .catch(function() { setStatus('Could not parse resume.'); });
    }

    if (opts.multiInputId) {
        var multi = document.getElementById(opts.multiInputId);
        if (multi) {
            multi.addEventListener('change', function() {
                var files = multi.files;
                if (!files || !files.length) return;
                if (files.length === 1) {
                    uploadAndParse(files[0]);
                } else {
                    setStatus(files.length + ' resumes — bulk mode (details parsed per file on save).', true);
                }
            });
        }
    }
    if (opts.singleInputId) {
        var single = document.getElementById(opts.singleInputId);
        if (single) {
            single.addEventListener('change', function() {
                if (single.files && single.files[0]) uploadAndParse(single.files[0]);
            });
        }
    }
}

/* ── Helpers ── */
function getBase() {
    var path = window.location.pathname;
    var idx = path.indexOf('/public');
    if (idx !== -1) return path.substring(0, idx + 7);
    return '';
}
