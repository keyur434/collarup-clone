<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interview — <?= e($interview['job_title']) ?></title>
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body style="background:#f8fafc;min-height:100vh;padding:24px">
<div class="card form-card" style="max-width:640px;margin:40px auto">
    <h1 style="font-size:1.25rem;margin-bottom:8px">Hello, <?= e($interview['first_name']) ?></h1>
    <p class="hint" style="margin-bottom:16px">Interview for <strong><?= e($interview['job_title']) ?></strong></p>
    <?php if ($interview['scheduled_date']): ?>
    <p style="margin-bottom:12px">Scheduled: <?= e(format_date($interview['scheduled_date'], 'd M Y')) ?><?php if ($interview['scheduled_start']): ?> at <?= e(format_time($interview['scheduled_start'])) ?><?php endif; ?></p>
    <?php endif; ?>
    <?php if (!empty($interview['meeting_url'])): ?>
    <a href="<?= e($interview['meeting_url']) ?>" target="_blank" rel="noopener" class="btn btn-primary" id="joinMeetingBtn">Join meeting</a>
    <?php else: ?>
    <p class="hint">Your interviewer will share the meeting link separately.</p>
    <?php endif; ?>
    <p class="hint" style="margin-top:16px">Keep this tab open during the interview. We log tab switches for integrity review.</p>
</div>
<script>
(function() {
    var api = <?= json_encode($integrityApi) ?>;
    function ping(type, meta) {
        var body = new FormData();
        body.append('event_type', type);
        if (meta) body.append('meta_json', JSON.stringify(meta));
        fetch(api, { method: 'POST', body: body, credentials: 'same-origin' }).catch(function() {});
    }
    ping('session_start', { page: 'join' });
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) ping('window_switch', { hidden: true });
    });
    window.addEventListener('blur', function() { ping('window_switch', { blur: true }); });
    var btn = document.getElementById('joinMeetingBtn');
    if (btn) btn.addEventListener('click', function() { ping('meeting_link_opened', {}); });
})();
</script>
</body>
</html>
