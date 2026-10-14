<?php

/**
 * Teams recording fetch diagnostics.
 *
 *   php scripts/diagnose_teams_recording.php
 *   php scripts/diagnose_teams_recording.php --recent
 *   php scripts/diagnose_teams_recording.php --email=hr@yourcompany.com
 *   php scripts/diagnose_teams_recording.php --interview=<uuid> --reset-stuck --queue
 */

require __DIR__ . '/../app/bootstrap.php';

$opts = getopt('', ['email::', 'interview::', 'recent', 'reset-stuck', 'queue']);
$emailFilter = isset($opts['email']) ? trim($opts['email']) : '';
$interviewId = isset($opts['interview']) ? trim($opts['interview']) : '';
$showRecent = isset($opts['recent']) || ($emailFilter === '' && $interviewId === '');
$resetStuck = isset($opts['reset-stuck']);
$forceQueue = isset($opts['queue']);

$dbName = config('database')['database'];
$dbHost = config('database')['host'];
$teams = new TeamsRecordingService();

echo 'Database: ' . $dbHost . ' / ' . $dbName . PHP_EOL;
echo 'Graph sync configured: ' . ($teams->graphReady() ? 'yes' : 'NO — check AZURE_AD_SYNC_* in .env') . PHP_EOL;
echo 'App now (' . config('app')['timezone'] . '): ' . (new DateTime('now', new DateTimeZone(config('app')['timezone'])))->format('Y-m-d H:i:s') . PHP_EOL;

$counts = db()->fetch(
    "SELECT
        (SELECT COUNT(*) FROM interviews) AS total_interviews,
        (SELECT COUNT(*) FROM interviews WHERE meeting_url IS NOT NULL AND LTRIM(RTRIM(meeting_url)) <> '') AS with_meeting_url,
        (SELECT COUNT(*) FROM interviews WHERE recording_fetch_state IN ('waiting','retry','fetching')) AS pending_fetch"
);
echo 'Interviews: ' . (int) $counts['total_interviews'] . ' total, '
    . (int) $counts['with_meeting_url'] . ' with meeting_url, '
    . (int) $counts['pending_fetch'] . ' pending recording fetch' . PHP_EOL;
echo PHP_EOL;

if ($showRecent && $interviewId === '') {
    echo "=== Recent interviews (last 15) ===" . PHP_EOL;
    $recent = db()->fetchAll(
        "SELECT TOP 15 i.id, i.status, i.scheduled_date, i.scheduled_start, i.scheduled_end,
                CASE WHEN i.meeting_url IS NULL OR LTRIM(RTRIM(i.meeting_url)) = '' THEN '(empty)' ELSE LEFT(i.meeting_url, 60) END AS meeting_url_short,
                i.recording_fetch_state, i.recording_fetch_after, i.teams_organizer_user_id,
                c.first_name, c.last_name, c.email AS candidate_email, j.title AS job_title
         FROM interviews i
         JOIN applications a ON a.id = i.application_id
         JOIN candidates c ON c.id = a.candidate_id
         JOIN jobs j ON j.id = a.job_id
         ORDER BY COALESCE(i.scheduled_date, CAST(i.created_at AS DATE)) DESC, i.created_at DESC"
    );
    if (empty($recent)) {
        echo "No interviews in this database.\n";
        exit(0);
    }
    foreach ($recent as $r) {
        echo sprintf(
            "- %s | %s %s | %s | %s %s–%s | url: %s | fetch: %s\n",
            $r['id'],
            $r['first_name'],
            $r['last_name'],
            $r['job_title'],
            $r['scheduled_date'] ?: 'no-date',
            $r['scheduled_start'] ?: '',
            $r['scheduled_end'] ?: '',
            $r['meeting_url_short'],
            $r['recording_fetch_state'] ?: '(null)'
        );
    }
    echo PHP_EOL;
    if ((int) $counts['with_meeting_url'] === 0) {
        echo ">>> No meeting_url in ANY interview. The portal never stored a Teams link in THIS database.\n";
        echo ">>> Use the same machine/.env as the site you open in the browser, or re-save: Job → Interviews → Link Teams meeting.\n";
        echo ">>> If the UI shows Join Teams but this script shows (empty), you are on a different DB than the website.\n";
    }
    echo PHP_EOL;
}

$sql = "SELECT TOP 25 i.id, i.status, i.scheduled_date, i.scheduled_start, i.scheduled_end,
               i.meeting_url, i.teams_meeting_id, i.teams_organizer_user_id,
               i.recording_fetch_state, i.recording_fetch_after, i.recording_fetch_attempts,
               c.first_name, c.last_name, c.email AS candidate_email
        FROM interviews i
        JOIN applications a ON a.id = i.application_id
        JOIN candidates c ON c.id = a.candidate_id
        WHERE 1=1";
$params = [];
if ($interviewId !== '') {
    $sql .= ' AND i.id = ?';
    $params[] = $interviewId;
} elseif ($emailFilter !== '') {
    $sql .= " AND (
        i.teams_organizer_user_id LIKE ?
        OR c.email LIKE ?
        OR EXISTS (SELECT 1 FROM users u WHERE u.email LIKE ?)
    )";
    $params[] = '%' . $emailFilter . '%';
    $params[] = '%' . $emailFilter . '%';
    $params[] = '%' . $emailFilter . '%';
} else {
    $sql .= " AND i.meeting_url IS NOT NULL AND LTRIM(RTRIM(i.meeting_url)) <> ''";
}
$sql .= ' ORDER BY i.scheduled_date DESC, i.created_at DESC';

$rows = db()->fetchAll($sql, $params);
if (empty($rows) && ($interviewId !== '' || $emailFilter !== '')) {
    echo "No matching interviews for that filter.\n";
    exit(0);
}
if (empty($rows)) {
    exit(0);
}

$now = (new DateTime('now', new DateTimeZone(config('app')['timezone'])))->format('Y-m-d H:i:s');

foreach ($rows as $r) {
    echo str_repeat('-', 72) . PHP_EOL;
    echo 'Interview: ' . $r['id'] . PHP_EOL;
    echo 'Candidate: ' . $r['first_name'] . ' ' . $r['last_name'] . ' (' . $r['candidate_email'] . ')' . PHP_EOL;
    echo 'When: ' . ($r['scheduled_date'] ?: '—') . ' ' . ($r['scheduled_start'] ?: '') . '–' . ($r['scheduled_end'] ?: '') . PHP_EOL;
    echo 'Status: ' . $r['status'] . ' | fetch_state: ' . ($r['recording_fetch_state'] ?: '(null)') . PHP_EOL;
    echo 'fetch_after: ' . ($r['recording_fetch_after'] ?: '(null)') . ' | now: ' . $now . PHP_EOL;
    echo 'meeting_url: ' . ($r['meeting_url'] ? substr($r['meeting_url'], 0, 80) . '…' : 'MISSING') . PHP_EOL;
    echo 'teams_meeting_id: ' . ($r['teams_meeting_id'] ? 'set' : 'MISSING (OK if join URL + organizer set)') . PHP_EOL;
    echo 'organizer: ' . ($r['teams_organizer_user_id'] ?: 'MISSING') . PHP_EOL;
    echo 'fetch_attempts: ' . (int) ($r['recording_fetch_attempts'] ?? 0) . PHP_EOL;

    $media = db()->fetch('SELECT COUNT(*) AS c FROM interview_media WHERE interview_id = ?', [$r['id']]);
    echo 'interview_media rows: ' . (int) $media['c'] . PHP_EOL;

    $jobs = db()->fetchAll(
        "SELECT TOP 5 id, status, attempts, error_message, created_at, completed_at
         FROM job_queue
         WHERE job_type = 'FetchTeamsRecording' AND payload LIKE ?
         ORDER BY id DESC",
        ['%' . $r['id'] . '%']
    );
    if (!empty($jobs)) {
        echo 'Recent FetchTeamsRecording jobs:' . PHP_EOL;
        foreach ($jobs as $j) {
            echo '  #' . $j['id'] . ' ' . $j['status'] . ' attempts=' . $j['attempts'];
            if (!empty($j['error_message'])) {
                echo ' — ' . substr($j['error_message'], 0, 120);
            }
            echo ' (' . $j['created_at'] . ')' . PHP_EOL;
        }
    } else {
        echo 'FetchTeamsRecording jobs: none queued for this interview' . PHP_EOL;
    }

    $reasons = [];
    if (!$teams->graphReady()) {
        $reasons[] = 'Graph sync app not configured';
    }
    if (empty($r['meeting_url'])) {
        $reasons[] = 'meeting_url empty — use Link Teams meeting in the portal';
    }
    if (empty($r['teams_organizer_user_id']) && empty($r['teams_meeting_id'])) {
        $reasons[] = 'No organizer on interview — re-save link with host email';
    }
    if ($r['recording_fetch_state'] === 'done') {
        $reasons[] = 'Already fetched (check report / interview_media)';
    } elseif ($r['recording_fetch_state'] === 'failed') {
        $reasons[] = 'Fetch failed after max retries — re-save link or --reset-stuck';
    } elseif ($r['recording_fetch_state'] === 'fetching') {
        $reasons[] = 'State is fetching — job may be stuck; --reset-stuck then --queue';
    } elseif ($r['recording_fetch_state'] === null || $r['recording_fetch_state'] === '') {
        $reasons[] = 'recording_fetch_state not set — re-save Teams link';
    } elseif (in_array($r['recording_fetch_state'], ['waiting', 'retry'], true)) {
        if (!empty($r['recording_fetch_after']) && $r['recording_fetch_after'] > $now) {
            $reasons[] = 'fetch_after still in the future (set correct end time or re-save after deploy)';
        }
    }
    $pendingOk = $r['status'] === 'pending'
        && !empty($r['scheduled_date'])
        && !empty($r['meeting_url']);
    if (!in_array($r['status'], ['scheduled', 'processing', 'ongoing', 'incomplete', 'completed'], true) && !$pendingOk) {
        $reasons[] = 'Interview status not eligible for auto-fetch: ' . $r['status'];
    } elseif ($pendingOk) {
        $reasons[] = 'Status is pending (poll will promote to scheduled after deploy fix)';
    }

    if (empty($reasons)) {
        echo 'Poll should pick this up on next queue_worker run.' . PHP_EOL;
    } else {
        echo 'Blockers:' . PHP_EOL;
        foreach ($reasons as $reason) {
            echo '  - ' . $reason . PHP_EOL;
        }
    }

    if ($resetStuck && ($interviewId === '' || $interviewId === $r['id'])) {
        $teams->resetStuckRecordingFetch($r['id']);
        echo 'Reset fetching → waiting (if was fetching).' . PHP_EOL;
    }
    if ($forceQueue && ($interviewId === '' || $interviewId === $r['id'])) {
        try {
            if ($teams->forceQueueRecordingFetch($r['id'])) {
                echo 'Queued FetchTeamsRecording job (immediate).' . PHP_EOL;
            } else {
                echo 'Could not queue — check meeting_url, organizer, and interview_media.' . PHP_EOL;
            }
        } catch (Exception $e) {
            echo 'Queue failed: ' . $e->getMessage() . PHP_EOL;
        }
    }

    $lastErr = db()->fetch(
        "SELECT TOP 1 status, error_message, created_at FROM job_queue
         WHERE job_type = 'FetchTeamsRecording' AND payload LIKE ? AND error_message IS NOT NULL AND LTRIM(error_message) <> ''
         ORDER BY id DESC",
        ['%' . $r['id'] . '%']
    );
    if ($lastErr && !empty($lastErr['error_message']) && (int) $media['c'] === 0) {
        echo 'Last fetch error (' . $lastErr['status'] . '): ' . $lastErr['error_message'] . PHP_EOL;
    }
}

echo PHP_EOL . 'Next: php cron/queue_worker.php' . PHP_EOL;
