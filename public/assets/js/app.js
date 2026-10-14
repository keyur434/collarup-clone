document.addEventListener('DOMContentLoaded', function() {
    initThemeToggle();
    initSidebar();
    initTopbarUserMenu();
    initDataTableSort();
    autoHideAlerts();
    initHiringDeskAiForms();
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
            localStorage.setItem('talentpulse-theme', dark ? 'dark' : 'light');
            try { localStorage.removeItem('collarup-theme'); } catch (e2) {}
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

/* ── Topbar account menu ── */
function initTopbarUserMenu() {
    var menu = document.getElementById('topbarUserMenu');
    var toggle = document.getElementById('topbarUserToggle');
    var dropdown = document.getElementById('topbarUserDropdown');
    if (!menu || !toggle || !dropdown) return;

    function close() {
        dropdown.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
        menu.classList.remove('is-open');
    }
    function open() {
        dropdown.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');
        menu.classList.add('is-open');
    }

    toggle.addEventListener('click', function(e) {
        e.stopPropagation();
        if (dropdown.hidden) {
            open();
        } else {
            close();
        }
    });
    document.addEventListener('click', function(e) {
        if (!menu.contains(e.target)) {
            close();
        }
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            close();
        }
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

/* ── Sortable data tables (client-side, current page) ── */
function initDataTableSort() {
    document.querySelectorAll('table.data-table[data-sortable]').forEach(function(table) {
        var thead = table.tHead;
        if (!thead || !thead.rows.length) return;
        var headerRow = thead.rows[0];
        var sortableThs = [];
        for (var i = 0; i < headerRow.cells.length; i++) {
            (function(thEl) {
                if (!thEl.getAttribute('data-sort-type')) return;
                sortableThs.push(thEl);
                var btn = thEl.querySelector('.th-sort');
                if (!btn) return;
                btn.addEventListener('click', function(ev) {
                    ev.preventDefault();
                    var colIndex = thEl.cellIndex;
                    var type = thEl.getAttribute('data-sort-type') || 'string';
                    var dir = thEl.getAttribute('data-sort-dir') === 'asc' ? 'desc' : 'asc';
                    sortableThs.forEach(function(h) {
                        h.removeAttribute('data-sort-dir');
                    });
                    thEl.setAttribute('data-sort-dir', dir);
                    sortTableBody(table, colIndex, type, dir);
                });
            })(headerRow.cells[i]);
        }
        var defaultTh = headerRow.querySelector('th[data-sort-default]');
        if (defaultTh) {
            var colIndex = defaultTh.cellIndex;
            var type = defaultTh.getAttribute('data-sort-type') || 'string';
            var dir = defaultTh.getAttribute('data-sort-default') || 'asc';
            defaultTh.setAttribute('data-sort-dir', dir);
            sortTableBody(table, colIndex, type, dir);
        }
    });
}

function sortTableBody(table, colIndex, type, dir) {
    var tbody = table.tBodies[0];
    if (!tbody) return;
    var rows = Array.prototype.slice.call(tbody.rows).filter(function(row) {
        return !row.querySelector('.empty-state') && row.cells.length > colIndex;
    });
    var mult = dir === 'asc' ? 1 : -1;
    rows.sort(function(a, b) {
        var av = tableCellSortValue(a.cells[colIndex], type);
        var bv = tableCellSortValue(b.cells[colIndex], type);
        if (type === 'number') {
            var an = av === '' ? -Infinity : parseFloat(av);
            var bn = bv === '' ? -Infinity : parseFloat(bv);
            if (an === bn) return 0;
            return an < bn ? -1 * mult : 1 * mult;
        }
        if (type === 'date') {
            var ad = av ? Date.parse(av) : 0;
            var bd = bv ? Date.parse(bv) : 0;
            if (ad === bd) return 0;
            return ad < bd ? -1 * mult : 1 * mult;
        }
        av = (av || '').toLowerCase();
        bv = (bv || '').toLowerCase();
        if (av === bv) return 0;
        return av < bv ? -1 * mult : 1 * mult;
    });
    rows.forEach(function(row) {
        tbody.appendChild(row);
    });
}

function tableCellSortValue(cell, type) {
    if (!cell) return '';
    if (cell.hasAttribute('data-sort-value')) {
        return cell.getAttribute('data-sort-value');
    }
    return cell.textContent.replace(/\s+/g, ' ').trim();
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
    select.disabled = false;
    fetch(getBase() + '/api/jobs/' + jobId + '/stages')
        .then(function(r) { return r.json(); })
        .then(function(stages) {
            if (!Array.isArray(stages)) {
                stages = [];
            }
            select.innerHTML = '<option value="">Select Stage</option>';
            stages.forEach(function(s) {
                var opt = document.createElement('option');
                opt.value = s.id; opt.textContent = s.name;
                if (selectedId && String(selectedId) === String(s.id)) opt.selected = true;
                select.appendChild(opt);
            });
            if (!selectedId && stages.length === 1) {
                select.value = stages[0].id;
            }
        })
        .catch(function() {
            select.innerHTML = '<option value="">Could not load stages</option>';
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
        .then(function(r) {
            return r.json().then(function(data) {
                if (!r.ok) throw new Error((data && data.error) ? data.error : 'Could not load interview.');
                return data;
            });
        })
        .then(function(data) {
            var iv = data.interview;
            if (!iv) throw new Error('Interview not found.');
            var form = document.getElementById('drawer-form');
            if (form) form.action = getBase() + '/interviews/' + id + '/details';
            setVal('d_first_name', iv.first_name);
            setVal('d_last_name', iv.last_name);
            setVal('d_email', iv.email);
            setVal('d_meeting_url', iv.meeting_url);
            setVal('d_date', iv.scheduled_date ? String(iv.scheduled_date).split(' ')[0] : '');
            setVal('d_start', normalizeTimeInput(iv.scheduled_start));
            setVal('d_end', normalizeTimeInput(iv.scheduled_end));
            if (iv.default_organizer_email) {
                setVal('d_organizer_email', iv.default_organizer_email);
            }
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
        })
        .catch(function(err) {
            alert(err.message || 'Could not open interview details.');
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
            setVal('r_start', normalizeTimeInput(iv.scheduled_start));
            setVal('r_end', normalizeTimeInput(iv.scheduled_end));
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

function normalizeTimeInput(val) {
    if (!val) return '';
    var s = String(val).trim();
    var m = s.match(/^(\d{1,2}:\d{2})/);
    return m ? m[1] : s;
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

/* ── Searchable multi-select ── */
function initSearchableMultiselect(rootId) {
    var root = document.getElementById(rootId);
    if (!root) return;
    var dataEl = document.getElementById(rootId + '_data');
    var data = dataEl ? JSON.parse(dataEl.textContent || '{}') : {};
    var options = data.options || [];
    var selected = {};
    (data.selected || []).forEach(function(v) { selected[v] = true; });

    var chips = document.getElementById(rootId + '_chips');
    var input = document.getElementById(rootId + '_input');
    var dropdown = document.getElementById(rootId + '_dropdown');
    var hidden = document.getElementById(rootId + '_hidden');
    var fieldName = root.getAttribute('data-name');
    var searchUrl = root.getAttribute('data-search-url');

    function syncHidden() {
        if (!hidden) return;
        hidden.innerHTML = '';
        Object.keys(selected).forEach(function(val) {
            if (!selected[val]) return;
            var inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = fieldName;
            inp.value = val;
            hidden.appendChild(inp);
        });
    }

    function appendChip(label, value) {
        if (!chips) return;
        var chip = document.createElement('span');
        chip.className = 'searchable-ms-chip';
        var text = document.createElement('span');
        text.className = 'searchable-ms-chip-label';
        text.textContent = label;
        var remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'searchable-ms-chip-remove';
        remove.setAttribute('aria-label', 'Remove ' + label);
        remove.innerHTML = '&times;';
        remove.addEventListener('click', function() {
            selected[value] = false;
            renderChips();
            syncHidden();
        });
        chip.appendChild(text);
        chip.appendChild(remove);
        chips.appendChild(chip);
    }

    function renderChips() {
        if (!chips) return;
        chips.innerHTML = '';
        var shown = {};
        options.forEach(function(opt) {
            if (!selected[opt.value] || shown[opt.value]) return;
            shown[opt.value] = true;
            appendChip(opt.label, opt.value);
        });
        Object.keys(selected).forEach(function(val) {
            if (!selected[val] || shown[val]) return;
            appendChip(val, val);
        });
    }

    function addValues(names, replace) {
        if (replace) {
            Object.keys(selected).forEach(function(k) { selected[k] = false; });
        }
        (names || []).forEach(function(name) {
            name = (name || '').trim();
            if (!name) return;
            selected[name] = true;
            if (!options.some(function(o) { return o.value === name; })) {
                options.push({ value: name, label: name });
            }
        });
        renderChips();
        syncHidden();
    }

    function isActiveInWidget() {
        var ae = document.activeElement;
        return !!(root && ae && root.contains(ae));
    }

    function hideDropdown() {
        if (dropdown) dropdown.classList.add('hidden');
    }

    function showDropdown(list) {
        if (!dropdown) return;
        if (!isActiveInWidget()) {
            hideDropdown();
            return;
        }
        dropdown.innerHTML = '';
        list.forEach(function(opt) {
            if (selected[opt.value]) return;
            var row = document.createElement('button');
            row.type = 'button';
            row.className = 'searchable-ms-option';
            row.textContent = opt.label;
            row.style.cssText = 'display:block;width:100%;text-align:left;padding:8px 10px;border:none;background:transparent;cursor:pointer;font-size:0.85rem';
            row.addEventListener('mousedown', function(e) {
                e.preventDefault();
            });
            row.onclick = function() {
                selected[opt.value] = true;
                if (!options.some(function(o) { return o.value === opt.value; })) {
                    options.push(opt);
                }
                renderChips();
                syncHidden();
                hideDropdown();
                if (input) {
                    input.value = '';
                    input.focus();
                }
            };
            dropdown.appendChild(row);
        });
        dropdown.classList.toggle('hidden', list.length === 0);
    }

    function filterOptions(q) {
        q = (q || '').toLowerCase();
        return options.filter(function(o) {
            return !selected[o.value] && (!q || o.label.toLowerCase().indexOf(q) !== -1);
        }).slice(0, 40);
    }

    var directoryCacheStore = window.__employeeDirectoryByUrl || (window.__employeeDirectoryByUrl = {});

    function mergeOptions(items) {
        var byVal = {};
        options.forEach(function(o) { byVal[o.value] = o; });
        (items || []).forEach(function(o) { byVal[o.value] = o; });
        options = Object.keys(byVal).map(function(k) { return byVal[k]; });
    }

    function employeesFetchUrl(q) {
        var base = searchUrl;
        q = (q || '').trim();
        if (q === '') {
            return base;
        }
        var sep = base.indexOf('?') >= 0 ? '&' : '?';
        return base + sep + 'q=' + encodeURIComponent(q);
    }

    function loadRemote(q, forceShow) {
        if (!searchUrl) {
            if (forceShow || isActiveInWidget()) {
                showDropdown(filterOptions(q));
            }
            return;
        }
        q = (q || '').trim();
        if (q === '') {
            var cached = directoryCacheStore[searchUrl];
            if (cached && cached.loaded) {
                options = cached.items;
                if (forceShow || isActiveInWidget()) {
                    showDropdown(filterOptions(q));
                }
                return;
            }
        }
        fetch(employeesFetchUrl(q), { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var items = data.items || [];
                if (q === '') {
                    directoryCacheStore[searchUrl] = { loaded: true, items: items };
                    options = items;
                } else {
                    mergeOptions(items);
                }
                if (forceShow || isActiveInWidget()) {
                    var list = q === '' ? filterOptions(q) : items.filter(function(o) {
                        return !selected[o.value];
                    }).slice(0, 40);
                    showDropdown(list);
                }
            });
    }

    function prefetchRemoteDirectory() {
        if (!searchUrl) return;
        var cached = directoryCacheStore[searchUrl];
        if (cached && cached.loaded) {
            options = cached.items;
            return;
        }
        fetch(searchUrl, { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var items = data.items || [];
                directoryCacheStore[searchUrl] = { loaded: true, items: items };
                options = items;
            });
    }

    if (input) {
        input.addEventListener('focus', function() { loadRemote(input.value, true); });
        input.addEventListener('input', function() { loadRemote(input.value, true); });
        if (root.getAttribute('data-allow-custom') === '1' && !searchUrl) {
            input.addEventListener('keydown', function(e) {
                if (e.key !== 'Enter') return;
                e.preventDefault();
                var v = input.value.trim();
                if (!v) return;
                addValues([v], false);
                input.value = '';
                hideDropdown();
            });
        }
    }
    if (root) {
        root.addEventListener('focusout', function() {
            setTimeout(function() {
                if (!isActiveInWidget()) {
                    hideDropdown();
                }
            }, 0);
        });
    }
    if (searchUrl) {
        prefetchRemoteDirectory();
    } else {
        hideDropdown();
    }
    renderChips();
    syncHidden();

    window.searchableMultiselectRegistry = window.searchableMultiselectRegistry || {};
    window.searchableMultiselectRegistry[rootId] = { addValues: addValues };
}

function initJobSkillsFromJdSuggest() {
    var btn = document.getElementById('btnSuggestSkillsFromJd');
    if (!btn) return;
    var status = document.getElementById('jdSkillsSuggestStatus');
    var form = btn.closest('form');

    btn.addEventListener('click', function() {
        var jdEl = document.getElementById('key_responsibility');
        var jd = jdEl ? jdEl.value.trim() : '';
        if (jd.length < 40) {
            if (status) status.textContent = 'Paste a longer job description in Key Responsibility first.';
            return;
        }
        if (btn.disabled) return;
        var tokenInp = form ? form.querySelector('input[name="_token"]') : null;
        var meta = document.querySelector('meta[name="csrf-token"]');
        var token = tokenInp ? tokenInp.value : (meta ? meta.getAttribute('content') : '');
        if (!token) {
            if (status) status.textContent = 'Session expired — refresh the page.';
            return;
        }

        btn.disabled = true;
        btn.classList.add('is-loading');
        var loadMsg = btn.getAttribute('data-hd-loading-text') || 'Working…';
        var prevHtml = btn.innerHTML;
        btn.innerHTML = '<span class="btn-spinner" aria-hidden="true"></span>' + loadMsg;
        if (status) status.textContent = '';

        var fd = new FormData();
        fd.append('_token', token);
        fd.append('job_description', jd);
        var titleEl = document.getElementById('job_title_input');
        fd.append('job_title', titleEl ? titleEl.value.trim() : '');
        var quals = document.querySelector('[name="qualifications"]');
        fd.append('qualifications', quals ? quals.value.trim() : '');

        fetch(getBase() + '/api/jobs/suggest-skills', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(r) {
                return r.json().then(function(j) { return { ok: r.ok, j: j }; });
            })
            .then(function(res) {
                if (!res.ok) throw new Error((res.j && res.j.error) ? res.j.error : 'Could not suggest skills.');
                var reg = window.searchableMultiselectRegistry && window.searchableMultiselectRegistry.job_skills_ms;
                if (!reg) throw new Error('Skills field not ready — refresh the page.');
                var list = res.j.skills || [];
                reg.addValues(list, false);
                if (status) status.textContent = list.length ? ('Added ' + list.length + ' skill(s). Review and remove any you do not need.') : 'No skills returned.';
            })
            .catch(function(err) {
                if (status) status.textContent = err.message || 'Request failed.';
            })
            .finally(function() {
                btn.disabled = false;
                btn.classList.remove('is-loading');
                btn.innerHTML = prevHtml;
            });
    });
}

function initPipelineStageEditor(formId) {
    var form = document.getElementById(formId);
    if (!form) return;
    var list = document.getElementById('pipeline-stages');
    var deletedWrap = document.getElementById('pipeline-deleted-fields');
    if (!list) return;

    function reindexStageNames() {
        var rows = list.querySelectorAll('.pipeline-stage-row');
        rows.forEach(function(row, idx) {
            var idInput = row.querySelector('input[name*="[id]"]');
            var nameInput = row.querySelector('input.stage-input');
            if (idInput) idInput.name = 'stages[' + idx + '][id]';
            if (nameInput) nameInput.name = 'stages[' + idx + '][name]';
        });
    }

    list.addEventListener('click', function(e) {
        var up = e.target.closest('.pipeline-move-up');
        var down = e.target.closest('.pipeline-move-down');
        var del = e.target.closest('.pipeline-delete-round');
        var row = e.target.closest('.pipeline-stage-row');
        if (!row) return;
        if (up) {
            var prev = row.previousElementSibling;
            if (prev) list.insertBefore(row, prev);
            reindexStageNames();
        } else if (down) {
            var next = row.nextElementSibling;
            if (next) list.insertBefore(next, row);
            reindexStageNames();
        } else if (del) {
            var sid = row.getAttribute('data-stage-id');
            if (sid && deletedWrap) {
                var inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = 'deleted_stage_ids[]';
                inp.value = sid;
                deletedWrap.appendChild(inp);
            }
            row.remove();
            reindexStageNames();
        }
    });
}

/* ── Bulk resume import: preview then confirm ── */
function initBulkImportPreview(opts) {
    var form = document.getElementById(opts.formId);
    if (!form) return;
    var fileInput = document.getElementById(opts.fileInputId);
    var previewBtn = document.getElementById('btnPreviewBulk');
    var submitBtn = document.getElementById('btnSubmitCandidate');
    var previewPanel = document.getElementById('bulkImportPreview');
    var previewBody = document.getElementById('bulkPreviewBody');
    var clearBtn = document.getElementById('bulkPreviewClear');
    if (!fileInput || !previewBtn) return;

    function splitChecked() {
        var el = form.querySelector('input[name="' + opts.splitCheckboxName + '"]');
        return el && el.checked;
    }

    function fileCount() {
        return fileInput.files ? fileInput.files.length : 0;
    }

    window.bulkImportRequiresPreview = function() {
        var tab = document.getElementById('active_tab');
        if (!tab || tab.value !== 'resume') return false;
        var n = fileCount();
        if (n === 0) return false;
        var emailEl = document.getElementById('parsed_email');
        var hasEmail = emailEl && emailEl.value.trim() !== '';
        if (n === 1 && hasEmail) return false;
        if (n > 1) return true;
        return splitChecked();
    };

    function refreshBulkUi() {
        var need = window.bulkImportRequiresPreview();
        previewBtn.style.display = need ? 'inline-flex' : 'none';
        if (!need) {
            if (previewPanel) previewPanel.classList.add('hidden');
            document.getElementById('bulk_preview_confirmed').value = '';
            document.getElementById('bulk_items').value = '';
            if (submitBtn) {
                submitBtn.textContent = 'Add Candidate';
                submitBtn.title = '';
            }
            var wrap = document.getElementById('candidateDetailsWrap');
            if (wrap) wrap.style.display = 'block';
            return;
        }
        var wrap = document.getElementById('candidateDetailsWrap');
        if (wrap) wrap.style.display = 'none';
        if (submitBtn) {
            submitBtn.textContent = 'Add candidates';
            var needConfirm = !document.getElementById('bulk_preview_confirmed').value;
            submitBtn.title = needConfirm ? 'Run Preview import first' : '';
        }
    }

    fileInput.addEventListener('change', function() {
        document.getElementById('bulk_preview_confirmed').value = '';
        document.getElementById('bulk_items').value = '';
        if (previewPanel) previewPanel.classList.add('hidden');
        refreshBulkUi();
    });
    var splitEl = form.querySelector('input[name="' + opts.splitCheckboxName + '"]');
    if (splitEl) splitEl.addEventListener('change', refreshBulkUi);

    previewBtn.addEventListener('click', function() {
        var job = document.getElementById(opts.jobSelectId);
        var pipeline = document.getElementById(opts.pipelineSelectId);
        if (job) job.disabled = false;
        if (pipeline) pipeline.disabled = false;
        if (!job || !job.value) { alert('Select a job role first.'); return; }
        if (!pipeline || !pipeline.value) {
            alert('Select a pipeline stage first. If the list is empty, pick the job again or add pipeline stages under job settings.');
            return;
        }
        if (!fileInput.files || !fileInput.files.length) { alert('Choose resume file(s) first.'); return; }

        var fd = new FormData(form);
        fd.delete('bulk_items');
        fd.delete('bulk_preview_confirmed');
        var token = form.querySelector('input[name="_token"]');
        if (token) fd.set('_token', token.value);

        previewBtn.disabled = true;
        previewBtn.textContent = 'Parsing…';
        fetch(opts.previewUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                previewBtn.disabled = false;
                previewBtn.textContent = 'Preview import';
                if (data.error) { alert(data.error); return; }
                renderBulkPreviewTable(data);
                document.getElementById('bulk_preview_confirmed').value = '1';
                refreshBulkUi();
            })
            .catch(function() {
                previewBtn.disabled = false;
                previewBtn.textContent = 'Preview import';
                alert('Preview failed. Try again.');
            });
    });

    function renderBulkPreviewTable(data) {
        if (!previewBody || !previewPanel) return;
        previewBody.innerHTML = '';
        (data.items || []).forEach(function(item, rowIdx) {
            var tr = document.createElement('tr');
            if (item.parse_weak) tr.style.background = 'var(--amber-bg,#fef3c7)';
            if (item.already_on_job) tr.style.opacity = '0.65';
            var warn = item.already_on_job ? ' (already on job)' : (item.parse_weak ? ' (check fields)' : '');
            tr.innerHTML =
                '<td><input type="checkbox" class="bulk-inc" data-row="' + rowIdx + '" ' + (item.include ? 'checked' : '') + (item.already_on_job ? ' disabled' : '') + '></td>' +
                '<td style="font-size:0.78rem;max-width:140px">' + escHtml(item.label || item.file_name) + warn + '</td>' +
                '<td><input class="bulk-fn" data-row="' + rowIdx + '" value="' + escAttr(item.first_name || '') + '"></td>' +
                '<td><input class="bulk-ln" data-row="' + rowIdx + '" value="' + escAttr(item.last_name || '') + '"></td>' +
                '<td><input class="bulk-em" data-row="' + rowIdx + '" value="' + escAttr(item.email || '') + '"></td>' +
                '<td><input class="bulk-ph" data-row="' + rowIdx + '" value="' + escAttr(item.phone || '') + '"></td>' +
                '<td><input class="bulk-yx" data-row="' + rowIdx + '" value="' + escAttr(item.years_experience != null ? item.years_experience : '') + '" style="width:64px"></td>' +
                '<td><input class="bulk-co" data-row="' + rowIdx + '" value="' + escAttr(item.current_company || '') + '"></td>';
            tr.dataset.fileIndex = item.file_index;
            tr.dataset.phoneCc = item.phone_country_code || '+91';
            previewBody.appendChild(tr);
        });
        var sum = document.getElementById('bulkPreviewSummary');
        if (sum) {
            var inc = (data.items || []).filter(function(i) { return i.include; }).length;
            sum.textContent = data.candidate_count + ' profile(s) from ' + data.source_file_count + ' file(s)'
                + (data.split_applied ? ' (compiled PDF split)' : '')
                + ' — ' + inc + ' selected to add. Edit empty cells before saving.';
        }
        previewPanel.classList.remove('hidden');
        window._bulkPreviewItems = data.items || [];
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            document.getElementById('bulk_preview_confirmed').value = '';
            document.getElementById('bulk_items').value = '';
            previewPanel.classList.add('hidden');
        });
    }

    refreshBulkUi();
    document.addEventListener('click', function(e) {
        if (e.target.closest('#candidateCreateForm [data-tab]')) {
            setTimeout(refreshBulkUi, 0);
        }
    });
}

function syncBulkItemsHiddenField() {
    var body = document.getElementById('bulkPreviewBody');
    var hidden = document.getElementById('bulk_items');
    if (!body || !hidden || !window._bulkPreviewItems) return false;
    var rows = [];
    body.querySelectorAll('tr').forEach(function(tr) {
        var rowIdx = tr.querySelector('.bulk-inc');
        if (!rowIdx) return;
        var r = parseInt(rowIdx.getAttribute('data-row'), 10);
        var base = window._bulkPreviewItems[r] || {};
        rows.push({
            include: tr.querySelector('.bulk-inc').checked,
            file_index: parseInt(tr.dataset.fileIndex, 10),
            first_name: tr.querySelector('.bulk-fn').value,
            last_name: tr.querySelector('.bulk-ln').value,
            email: tr.querySelector('.bulk-em').value,
            phone: tr.querySelector('.bulk-ph').value,
            phone_country_code: tr.dataset.phoneCc || '+91',
            years_experience: tr.querySelector('.bulk-yx').value,
            current_company: tr.querySelector('.bulk-co').value,
            source_id: document.getElementById('candidate_source_id') ? document.getElementById('candidate_source_id').value : ''
        });
    });
    hidden.value = JSON.stringify(rows);
    return rows.length > 0;
}

function escHtml(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
}
function escAttr(s) {
    return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
}

/* ── Hiring desk: AI POST forms (full-page wait) ── */
function initHiringDeskAiForms() {
    var forms = document.querySelectorAll('.hiring-desk-beta form.hd-ai-form');
    if (!forms.length) return;

    function setLoading(btn, on) {
        if (!btn) return;
        if (on) {
            if (!btn.dataset.hdOriginalHtml) {
                btn.dataset.hdOriginalHtml = btn.innerHTML;
            }
            var msg = btn.getAttribute('data-hd-loading-text') || 'Working…';
            btn.disabled = true;
            btn.classList.add('is-loading');
            btn.setAttribute('aria-busy', 'true');
            btn.innerHTML = '<span class="btn-spinner" aria-hidden="true"></span>' + msg;
        } else {
            btn.disabled = false;
            btn.classList.remove('is-loading');
            btn.removeAttribute('aria-busy');
            if (btn.dataset.hdOriginalHtml) {
                btn.innerHTML = btn.dataset.hdOriginalHtml;
            }
        }
    }

    forms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            if (form.dataset.hdSubmitting === '1') {
                e.preventDefault();
                return;
            }
            form.dataset.hdSubmitting = '1';
            var submitBtn = form.querySelector('button[type="submit"]');
            setLoading(submitBtn, true);
            forms.forEach(function(other) {
                if (other === form) return;
                other.querySelectorAll('button[type="submit"]').forEach(function(b) {
                    b.disabled = true;
                });
            });
        });
    });

    window.addEventListener('pageshow', function(ev) {
        if (!ev.persisted) return;
        forms.forEach(function(form) {
            delete form.dataset.hdSubmitting;
            form.querySelectorAll('button[type="submit"]').forEach(function(btn) {
                setLoading(btn, false);
            });
        });
    });
}

/* ── Helpers ── */
function getBase() {
    var path = window.location.pathname;
    var idx = path.indexOf('/public');
    if (idx !== -1) return path.substring(0, idx + 7);
    return '';
}
