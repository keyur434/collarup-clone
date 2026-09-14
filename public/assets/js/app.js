document.addEventListener('DOMContentLoaded', function() {
    initSidebar();
    autoHideAlerts();
});

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

/* ── Helpers ── */
function getBase() {
    var path = window.location.pathname;
    var idx = path.indexOf('/public');
    if (idx !== -1) return path.substring(0, idx + 7);
    return '';
}
