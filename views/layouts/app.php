<!DOCTYPE html>
<html lang="en">
<head>
    <script>
    (function () {
        try {
            var stored = localStorage.getItem('talentpulse-theme') || localStorage.getItem('collarup-theme');
            if (stored === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
            } else if (stored !== 'light' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        } catch (e) {}
    })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= e(isset($title) ? $title . ' — ' . app_name() : app_name()) ?></title>
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body>
<?php if (auth_check()):
    $u = auth_user();
    $nav = $activeNav ?? '';
    $initials = strtoupper(substr($u['first_name'], 0, 1) . substr($u['last_name'] ?? '', 0, 1));
?>
<div class="app-shell">
    <!-- Sidebar backdrop (mobile) -->
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <span class="brand-icon">
                <svg width="26" height="26" viewBox="0 0 26 26" fill="none">
                    <rect width="26" height="26" rx="7" fill="#7c3aed"/>
                    <circle cx="13" cy="9" r="3.5" stroke="#fff" stroke-width="1.8"/>
                    <path d="M6 21c0-3.314 3.134-6 7-6s7 2.686 7 6" stroke="#fff" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
            </span>
            <span class="brand-name"><?= e(app_name()) ?></span>
        </div>
        <nav class="sidebar-nav">
            <a href="<?= url('dashboard') ?>" class="nav-item <?= $nav === 'dashboard' ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
                    <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
                </svg>
                Dashboard
            </a>
            <a href="<?= url('jobs') ?>" class="nav-item <?= $nav === 'jobs' ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <rect x="2" y="7" width="20" height="14" rx="2"/>
                    <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>
                    <line x1="12" y1="12" x2="12" y2="16"/><line x1="10" y1="14" x2="14" y2="14"/>
                </svg>
                Job Roles
            </a>
            <a href="<?= url('candidates') ?>" class="nav-item <?= $nav === 'candidates' ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
                Candidates
            </a>
            <a href="<?= url('pipeline') ?>" class="nav-item <?= $nav === 'pipeline' ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M22 12H2"/><path d="M5 12V7a7 7 0 0 1 14 0v5"/><path d="M5 12v5a7 7 0 0 0 14 0v-5"/>
                </svg>
                Pipeline
            </a>
            <?php if (PermissionService::canAssignPending()): ?>
            <a href="<?= url('pending-recordings') ?>" class="nav-item <?= $nav === 'pending' ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>
                </svg>
                Pending Recordings
            </a>
            <?php endif; ?>
            <a href="<?= url('leaderboard') ?>" class="nav-item <?= $nav === 'leaderboard' ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M18 20V10M12 20V4M6 20v-6"/>
                </svg>
                Leaderboard
            </a>
            <?php if (HiringDeskService::enabled()): ?>
            <a href="<?= url('hiring-desk') ?>" class="nav-item <?= $nav === 'hiring-desk' ? 'active' : '' ?>" title="Preview — decision support">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/>
                    <rect x="9" y="3" width="6" height="4" rx="1"/>
                    <path d="M9 14l2 2 4-4"/>
                </svg>
                Hiring desk
                <span class="nav-beta-tag">Preview</span>
            </a>
            <?php endif; ?>
            <?php if (PermissionService::isAdminRole()): ?>
            <a href="<?= url('settings') ?>" class="nav-item <?= $nav === 'settings' ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <circle cx="12" cy="12" r="3"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/>
                </svg>
                Settings
            </a>
            <?php endif; ?>
            <a href="<?= url('team') ?>" class="nav-item <?= $nav === 'team' ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
                Team Management
            </a>
        </nav>
    </aside>

    <!-- Main content -->
    <div class="main-wrapper">
        <!-- Topbar -->
        <header class="topbar">
            <div class="topbar-left">
                <button type="button" class="menu-toggle" id="menuToggle" aria-label="Open menu">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <?php
                $breadcrumbs = nav_normalize_breadcrumbs($breadcrumbs ?? [], $pageBack ?? null, $title ?? null);
                $navBack = nav_resolve_back($breadcrumbs);
                ?>
                <?php if ($navBack): ?>
                <a href="<?= e($navBack['url']) ?>" class="topbar-back" aria-label="Back to <?= e($navBack['label']) ?>" title="Back to <?= e($navBack['label']) ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
                </a>
                <?php endif; ?>
                <nav class="topbar-breadcrumb" aria-label="Breadcrumb">
                    <?php foreach ($breadcrumbs as $i => $bc): ?>
                        <?php if ($i > 0): ?><span class="topbar-separator" aria-hidden="true">/</span><?php endif; ?>
                        <?php if (!empty($bc['url'])): ?>
                            <a href="<?= e($bc['url']) ?>"><?= e($bc['label']) ?></a>
                        <?php else: ?>
                            <span class="crumb-current"><?= e($bc['label']) ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </nav>
            </div>
            <div class="topbar-right">
                <button type="button" class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode" aria-pressed="false" title="Switch to dark mode">
                    <svg class="theme-icon theme-icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                    <svg class="theme-icon theme-icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
                </button>
                <div class="topbar-user-menu" id="topbarUserMenu">
                    <button type="button" class="topbar-user" id="topbarUserToggle" aria-haspopup="menu" aria-expanded="false" aria-controls="topbarUserDropdown">
                        <div class="user-avatar"><?= e($initials) ?></div>
                        <div class="user-info">
                            <span class="user-name"><?= e($u['first_name'] . ' ' . ($u['last_name'] ?? '')) ?></span>
                            <span class="user-role"><?= e(ucwords(str_replace('_', ' ', $u['role']))) ?></span>
                        </div>
                        <svg class="topbar-user-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 13a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm7 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2zM5 13a1 1 0 1 0 0-2 1 1 0 0 0 0 2z" fill="currentColor"/></svg>
                    </button>
                    <div class="topbar-user-dropdown" id="topbarUserDropdown" role="menu" hidden>
                        <?php if (!empty($u['email'])): ?>
                        <div class="topbar-user-dropdown-meta" role="presentation"><?= e($u['email']) ?></div>
                        <?php endif; ?>
                        <?php if (PermissionService::isAdminRole()): ?>
                        <a href="<?= url('settings') ?>" class="topbar-user-dropdown-item" role="menuitem">Settings</a>
                        <?php endif; ?>
                        <a href="<?= url('team') ?>" class="topbar-user-dropdown-item" role="menuitem">Team management</a>
                        <a href="<?= url('logout') ?>" class="topbar-user-dropdown-item" role="menuitem">Log out</a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Content -->
        <main class="content">
            <?php if ($msg = flash('success')): ?>
            <div class="alert alert-success" role="alert"><?= e($msg) ?></div>
            <?php endif; ?>
            <?php if ($msg = flash('error')): ?>
            <div class="alert alert-error" role="alert"><?= e($msg) ?></div>
            <?php endif; ?>
            <?= $content ?>
        </main>
    </div>
</div>
<?php else: ?>
    <?= $content ?>
<?php endif; ?>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
