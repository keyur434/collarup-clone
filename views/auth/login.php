<div class="auth-page">

    <div class="auth-left">

        <div class="auth-left-content">

            <div class="auth-logo">

                <svg width="32" height="32" viewBox="0 0 26 26" fill="none">

                    <rect width="26" height="26" rx="7" fill="rgba(255,255,255,0.2)"/>

                    <circle cx="13" cy="9" r="3.5" stroke="#fff" stroke-width="1.8"/>

                    <path d="M6 21c0-3.314 3.134-6 7-6s7 2.686 7 6" stroke="#fff" stroke-width="1.8" stroke-linecap="round"/>

                </svg>

                CollarUp

            </div>

            <div class="auth-card-quote">

                <h2>AI-powered online interviews.</h2>

                <p>Simplify <span style="color:#c4b5fd;font-weight:600">Interviewing,</span></p>

            </div>

        </div>

    </div>



    <div class="auth-right">

        <div class="auth-form-wrap">

            <h1>Sign In</h1>

            <?php if ($msg = flash('error')): ?>

            <div class="alert alert-error" style="margin-top:12px"><?= e($msg) ?></div>

            <?php endif; ?>



            <?php if (!empty($azureSsoEnabled)): ?>

            <a href="<?= url('login/azure') ?>" class="btn btn-primary btn-block" style="margin-top:24px;padding:11px;display:flex;align-items:center;justify-content:center;gap:8px">

                <svg width="18" height="18" viewBox="0 0 23 23"><rect x="1" y="1" width="10" height="10" fill="#f25022"/><rect x="12" y="1" width="10" height="10" fill="#7fba00"/><rect x="1" y="12" width="10" height="10" fill="#00a4ef"/><rect x="12" y="12" width="10" height="10" fill="#ffb900"/></svg>

                Sign in with Microsoft

            </a>

            <p class="auth-hint" style="margin-top:16px">Use your company Microsoft account. You must be added in Team Management first.</p>

            <?php endif; ?>



            <?php if (!empty($allowLocalLogin)): ?>

            <?php if (!empty($azureSsoEnabled)): ?>

            <div style="margin:20px 0;text-align:center;color:var(--gray-500);font-size:0.8rem">or local admin</div>

            <?php endif; ?>

            <form method="POST" action="<?= url('login') ?>" style="margin-top:<?= !empty($azureSsoEnabled) ? '0' : '24px' ?>">

                <?= csrf_field() ?>

                <div class="form-group">

                    <label class="form-label">Email</label>

                    <input type="email" name="email" class="form-control" value="<?= e(old('email')) ?>" required autocomplete="email">

                </div>

                <div class="form-group">

                    <label class="form-label">Password</label>

                    <input type="password" name="password" class="form-control" required autocomplete="current-password">

                </div>

                <button type="submit" class="btn btn-outline btn-block" style="margin-top:8px;padding:11px">Sign In (local)</button>

            </form>

            <?php elseif (empty($azureSsoEnabled)): ?>

            <form method="POST" action="<?= url('login') ?>" style="margin-top:24px">

                <?= csrf_field() ?>

                <div class="form-group">

                    <label class="form-label">Email</label>

                    <input type="email" name="email" class="form-control" value="<?= e(old('email')) ?>" required>

                </div>

                <div class="form-group">

                    <label class="form-label">Password</label>

                    <input type="password" name="password" class="form-control" required>

                </div>

                <button type="submit" class="btn btn-primary btn-block" style="margin-top:8px;padding:11px">Sign In</button>

            </form>

            <p class="auth-hint" style="margin-top:16px">Demo: <code>admin@company.local</code> / <code>admin123</code></p>

            <?php endif; ?>

        </div>

    </div>

</div>


