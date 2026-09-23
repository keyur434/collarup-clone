<?php

class TeamsRecordingService
{
    private $graph;
    private $storage;
    private $defaultOrganizer;

    public function __construct()
    {
        $config = config('services')['onedrive'];
        $this->graph = new MicrosoftGraphService('sync');
        $this->storage = new OneDriveStorageService();
        $this->defaultOrganizer = trim((string) $config['teams_organizer_user_id']);
    }

    public function graphReady()
    {
        return $this->graph->isConfigured();
    }

    /** @deprecated use graphReady() + per-interview organizer */
    public function isConfigured()
    {
        return $this->graphReady();
    }

    public function autoCreateEnabled()
    {
        return config('app')['teams_auto_create_meetings'] && $this->graphReady();
    }

    /**
     * Who owns the Teams meeting in Graph (for create + recording fetch).
     */
    public function organizerForMeetingCreate()
    {
        if (config('app')['teams_shared_organizer'] && $this->defaultOrganizer !== '') {
            return $this->defaultOrganizer;
        }
        $user = auth_user();
        if ($user && !empty($user['email'])) {
            return trim($user['email']);
        }
        return $this->defaultOrganizer;
    }

    public function usesSharedOrganizer()
    {
        return config('app')['teams_shared_organizer'] && $this->defaultOrganizer !== '';
    }

    public function createMeetingForInterview($interviewId)
    {
        if (!$this->graphReady()) {
            throw new RuntimeException('Microsoft Graph sync app is not configured.');
        }

        $row = db()->fetch(
            'SELECT i.*, c.first_name, c.last_name, c.email as candidate_email,
                    j.title as job_title
             FROM interviews i
             JOIN applications a ON a.id = i.application_id
             JOIN candidates c ON c.id = a.candidate_id
             JOIN jobs j ON j.id = a.job_id
             WHERE i.id = ?',
            [$interviewId]
        );
        if (!$row || empty($row['scheduled_date'])) {
            throw new RuntimeException('Interview must have a scheduled date.');
        }

        $organizer = $this->organizerForMeetingCreate();
        if ($organizer === '') {
            throw new RuntimeException('Set TEAMS_ORGANIZER_USER_ID (e.g. hr@yourcompany.com) or sign in with a work Microsoft account.');
        }

        $userId = $this->resolveUserId($organizer);
        $start = $this->buildDateTime($row['scheduled_date'], $row['scheduled_start'], '09:00:00');
        $end = $this->buildDateTime($row['scheduled_date'], $row['scheduled_end'], null);
        if ($end <= $start) {
            $end = clone $start;
            $end->modify('+45 minutes');
        }

        $subject = 'Interview — ' . trim($row['first_name'] . ' ' . $row['last_name']) . ' — ' . $row['job_title'];
        $body = [
            'startDateTime' => $start->format('c'),
            'endDateTime' => $end->format('c'),
            'subject' => mb_substr($subject, 0, 250),
        ];

        $meeting = $this->graph->request(
            'POST',
            '/users/' . rawurlencode($userId) . '/onlineMeetings',
            $body
        );

        if (empty($meeting['id']) || empty($meeting['joinWebUrl'])) {
            throw new RuntimeException('Teams did not return a meeting link. Check OnlineMeetings.ReadWrite.All on the sync app.');
        }

        $fetchAfter = clone $end;
        $fetchAfter->modify('+10 minutes');

        db()->update('interviews', [
            'meeting_url' => $meeting['joinWebUrl'],
            'teams_meeting_id' => $meeting['id'],
            'teams_organizer_user_id' => $userId,
            'recording_fetch_state' => 'waiting',
            'recording_fetch_after' => $fetchAfter->format('Y-m-d H:i:s'),
            'recording_fetch_attempts' => 0,
        ], 'id = :id', ['id' => $interviewId]);

        return $meeting;
    }

    public function fetchForInterview($interviewId, $meetingUrl = null, $mediaId = null)
    {
        if (!$this->graphReady()) {
            throw new RuntimeException('Teams recording fetch is not configured (Graph sync app).');
        }

        $interview = db()->fetch('SELECT * FROM interviews WHERE id = ?', [$interviewId]);
        if (!$interview) {
            throw new RuntimeException('Interview not found');
        }

        $organizerRef = !empty($interview['teams_organizer_user_id'])
            ? $interview['teams_organizer_user_id']
            : $this->organizerForMeetingCreate();
        if ($organizerRef === '') {
            throw new RuntimeException('No Teams organizer on this interview.');
        }

        $userId = $this->resolveUserId($organizerRef);
        $meeting = null;

        if (!empty($interview['teams_meeting_id'])) {
            try {
                $meeting = $this->graph->request(
                    'GET',
                    '/users/' . rawurlencode($userId) . '/onlineMeetings/' . rawurlencode($interview['teams_meeting_id'])
                );
            } catch (Exception $e) {
                $meeting = null;
            }
        }

        $joinUrl = $meetingUrl ?: ($interview['meeting_url'] ?? '');
        if (!$meeting && $joinUrl !== '') {
            $meeting = $this->findMeetingByJoinUrl($userId, $joinUrl);
        }
        if (!$meeting && $joinUrl !== '') {
            $meeting = $this->findMeetingViaCalendar($userId, $interview, $joinUrl);
        }

        $recording = null;
        $onlineMeetingId = null;

        if ($meeting && !empty($meeting['id'])) {
            $onlineMeetingId = $meeting['id'];
            $recording = $this->pickLatestRecordingFromMeeting($userId, $onlineMeetingId);
        }

        if (!$recording) {
            $recording = $this->findRecordingViaGetAll($userId, $interview, $joinUrl);
            if ($recording && !empty($recording['meetingId'])) {
                $onlineMeetingId = $recording['meetingId'];
            }
        }

        if (!$recording || $onlineMeetingId === null || $onlineMeetingId === '') {
            throw new RecordingNotReadyException(
                'Teams meeting or recording not found. Use the Outlook/Keka meetup-join link, confirm cloud recording exists, '
                . 'and ensure the Graph sync app has an application access policy for the organizer (Teams admin).'
            );
        }

        if (empty($interview['teams_meeting_id']) || $interview['teams_meeting_id'] !== $onlineMeetingId) {
            db()->update('interviews', [
                'teams_meeting_id' => $onlineMeetingId,
            ], 'id = :id', ['id' => $interviewId]);
        }

        $tempDir = BASE_PATH . '/storage/temp/';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        $tempFile = $tempDir . $interviewId . '_teams.mp4';
        $this->downloadTeamsRecordingToFile($userId, $onlineMeetingId, $recording, $tempFile);

        $year = date('Y');
        $month = date('m');
        $remotePath = $year . '/' . $month . '/' . $interviewId . '.mp4';
        $fileSize = file_exists($tempFile) ? filesize($tempFile) : null;
        $duration = null;
        try {
            $ffmpeg = new FFmpegService();
            $duration = $ffmpeg->getDuration($tempFile);
        } catch (Exception $e) {
            $duration = null;
        }
        $upload = $this->storage->upload($tempFile, $remotePath);
        InterviewWorkflowService::purgeInterviewTempFiles($interviewId);

        $storedUrl = ($meeting && !empty($meeting['joinWebUrl'])) ? $meeting['joinWebUrl'] : $joinUrl;
        $update = [
            'storage_provider' => $upload['storage_provider'],
            'drive_item_id' => $upload['drive_item_id'],
            'drive_path' => $upload['drive_path'],
            'blob_container' => $upload['blob_container'],
            'blob_name' => $upload['blob_name'],
            'file_size_bytes' => $fileSize ?: null,
            'duration_seconds' => $duration,
            'playback_mode' => 'blob',
            'processing_status' => 'ingested',
            'external_url' => $storedUrl,
        ];

        if ($mediaId) {
            db()->update('interview_media', $update, 'id = :id', ['id' => $mediaId]);
        } else {
            db()->insert('interview_media', array_merge($update, [
                'interview_id' => $interviewId,
                'media_type' => 'teams',
                'original_filename' => $interviewId . '.mp4',
            ]));
        }

        db()->update('interviews', [
            'recording_fetch_state' => 'done',
            'recording_fetch_after' => null,
        ], 'id = :id', ['id' => $interviewId]);

        return $upload;
    }

    public function deferRecordingFetch($interviewId, $minutes = 30, $maxAttempts = 72)
    {
        $row = db()->fetch('SELECT recording_fetch_attempts FROM interviews WHERE id = ?', [$interviewId]);
        $attempts = $row ? (int) $row['recording_fetch_attempts'] + 1 : 1;
        $state = $attempts >= $maxAttempts ? 'failed' : 'waiting';
        db()->update('interviews', [
            'recording_fetch_attempts' => $attempts,
            'recording_fetch_state' => $state,
            'recording_fetch_after' => date('Y-m-d H:i:s', time() + ($minutes * 60)),
        ], 'id = :id', ['id' => $interviewId]);
    }

    public function markRecordingFetchQueued($interviewId)
    {
        db()->update('interviews', [
            'recording_fetch_state' => 'fetching',
        ], 'id = :id', ['id' => $interviewId]);
    }

    /**
     * Queue fetch jobs for interviews past their fetch window (runs from queue_worker).
     */
    public function pollAndQueueDueRecordings($limit = 15)
    {
        if (!$this->graphReady()) {
            return 0;
        }

        $limit = max(1, min(50, (int) $limit));
        $now = $this->appNowString();
        $rows = db()->fetchAll(
            "SELECT TOP {$limit} i.id
             FROM interviews i
             WHERE (
                   i.teams_meeting_id IS NOT NULL
                   OR (
                       i.meeting_url IS NOT NULL AND LTRIM(RTRIM(i.meeting_url)) <> ''
                       AND i.teams_organizer_user_id IS NOT NULL AND LTRIM(RTRIM(i.teams_organizer_user_id)) <> ''
                   )
               )
               AND i.recording_fetch_state IN ('waiting', 'retry')
               AND (i.recording_fetch_after IS NULL OR i.recording_fetch_after <= ?)
               AND i.status IN ('scheduled', 'processing', 'ongoing', 'incomplete')
               AND NOT EXISTS (
                   SELECT 1 FROM interview_media im
                   WHERE im.interview_id = i.id
                     AND im.processing_status IN ('ingested', 'ready', 'transcribing', 'analyzing', 'uploading')
               )
             ORDER BY i.recording_fetch_after ASC",
            [$now]
        );

        return $this->queueRecordingFetchRows($rows);
    }

    /**
     * After saving a Teams link, queue immediately if the fetch window has already passed.
     */
    public function queueRecordingFetchIfDue($interviewId)
    {
        if (!$this->graphReady()) {
            return false;
        }

        $now = $this->appNowString();
        $row = db()->fetch(
            "SELECT i.id
             FROM interviews i
             WHERE i.id = ?
               AND (
                   i.teams_meeting_id IS NOT NULL
                   OR (
                       i.meeting_url IS NOT NULL AND LTRIM(RTRIM(i.meeting_url)) <> ''
                       AND i.teams_organizer_user_id IS NOT NULL AND LTRIM(RTRIM(i.teams_organizer_user_id)) <> ''
                   )
               )
               AND i.recording_fetch_state IN ('waiting', 'retry')
               AND (i.recording_fetch_after IS NULL OR i.recording_fetch_after <= ?)
               AND i.status IN ('scheduled', 'processing', 'ongoing', 'incomplete')
               AND NOT EXISTS (
                   SELECT 1 FROM interview_media im
                   WHERE im.interview_id = i.id
                     AND im.processing_status IN ('ingested', 'ready', 'transcribing', 'analyzing', 'uploading')
               )",
            [$interviewId, $now]
        );
        if (!$row) {
            return false;
        }
        return $this->queueRecordingFetchRows([$row]) > 0;
    }

    /**
     * Reset stuck "fetching" state (worker died after marking queued).
     */
    public function resetStuckRecordingFetch($interviewId)
    {
        db()->update('interviews', [
            'recording_fetch_state' => 'waiting',
            'recording_fetch_after' => $this->appNowString(),
        ], 'id = :id AND recording_fetch_state = :st', [
            'id' => $interviewId,
            'st' => 'fetching',
        ]);
    }

    private function queueRecordingFetchRows(array $rows)
    {
        $queue = new QueueService();
        $queued = 0;
        foreach ($rows as $row) {
            $queue->push('FetchTeamsRecording', ['interview_id' => $row['id']]);
            $this->markRecordingFetchQueued($row['id']);
            $queued++;
        }
        return $queued;
    }

    private function appNowString()
    {
        $tz = new DateTimeZone(config('app')['timezone']);
        return (new DateTime('now', $tz))->format('Y-m-d H:i:s');
    }

    public function ensureRecordingPolling($interviewId, $scheduledDate, $scheduledEnd)
    {
        if (empty($scheduledDate)) {
            return;
        }
        $end = $this->buildDateTime($scheduledDate, $scheduledEnd, null);
        $fetchAfter = clone $end;
        $fetchAfter->modify('+10 minutes');
        db()->update('interviews', [
            'recording_fetch_state' => 'waiting',
            'recording_fetch_after' => $fetchAfter->format('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $interviewId]);
    }

    public function registerManualTeamsLink($interviewId, $joinUrl, $scheduledDate, $scheduledEnd, $organizerEmail = null)
    {
        if (!$this->graphReady()) {
            throw new RuntimeException('Microsoft Graph sync app is not configured.');
        }

        $organizer = $organizerEmail !== null && trim($organizerEmail) !== ''
            ? trim($organizerEmail)
            : $this->organizerForMeetingCreate();
        if ($organizer === '') {
            throw new RuntimeException('Enter the Teams meeting host work email (who scheduled in Keka).');
        }

        $userId = $this->resolveUserId($organizer);
        $teamsMeetingId = null;
        try {
            $meeting = $this->findMeetingByJoinUrl($userId, $joinUrl);
            if ($meeting && !empty($meeting['id'])) {
                $teamsMeetingId = $meeting['id'];
            }
        } catch (Exception $e) {
            // Link may not be indexed yet; fetch by URL later
        }

        $update = [
            'meeting_url' => $joinUrl,
            'teams_organizer_user_id' => $userId,
            'recording_fetch_state' => 'waiting',
            'recording_fetch_attempts' => 0,
        ];
        if ($teamsMeetingId) {
            $update['teams_meeting_id'] = $teamsMeetingId;
        }
        db()->update('interviews', $update, 'id = :id', ['id' => $interviewId]);
        $this->ensureRecordingPolling($interviewId, $scheduledDate, $scheduledEnd);
    }

    private function buildDateTime($date, $time, $defaultTime)
    {
        $tz = new DateTimeZone(config('app')['timezone']);
        $timePart = $time ? $time : $defaultTime;
        if (!$timePart) {
            $timePart = '23:59:00';
        }
        if (strlen($timePart) === 5) {
            $timePart .= ':00';
        }
        return new DateTime($date . ' ' . $timePart, $tz);
    }

    public function resolveUserId($identifier)
    {
        if (strpos($identifier, '@') !== false) {
            $user = $this->graph->request('GET', '/users/' . rawurlencode($identifier));
            return $user['id'];
        }
        return $identifier;
    }

    private function findMeetingByJoinUrl($userId, $joinUrl)
    {
        $joinUrl = trim($joinUrl);
        if ($joinUrl === '') {
            return null;
        }

        try {
            $filter = "JoinWebUrl eq '" . str_replace("'", "''", $joinUrl) . "'";
            $result = $this->graph->request(
                'GET',
                '/users/' . rawurlencode($userId) . '/onlineMeetings?$filter=' . rawurlencode($filter)
            );
            $meetings = isset($result['value']) ? $result['value'] : [];
            if (!empty($meetings)) {
                return $meetings[0];
            }
        } catch (Exception $e) {
            // fall through to token / list lookup
        }

        foreach ($this->teamsUrlLookupTokens($joinUrl) as $token) {
            if (strlen($token) < 8) {
                continue;
            }
            try {
                $filter = "contains(joinWebUrl,'" . str_replace("'", "''", $token) . "')";
                $result = $this->graph->request(
                    'GET',
                    '/users/' . rawurlencode($userId) . '/onlineMeetings?$filter=' . rawurlencode($filter)
                );
                $meetings = isset($result['value']) ? $result['value'] : [];
                if (!empty($meetings)) {
                    return $meetings[0];
                }
            } catch (Exception $e) {
                // contains may be unsupported; try list scan below
            }
        }

        return $this->findMeetingByListingOnlineMeetings($userId, $joinUrl);
    }

    private function teamsUrlLookupTokens($joinUrl)
    {
        $tokens = [trim($joinUrl)];
        if (preg_match('#/meet/(\d+)#i', $joinUrl, $m)) {
            $tokens[] = $m[1];
        }
        if (preg_match('#meetup-join/([^?&]+)#i', $joinUrl, $m)) {
            $tokens[] = rawurldecode($m[1]);
        }
        if (preg_match('#[?&]p=([^&]+)#i', $joinUrl, $m)) {
            $tokens[] = $m[1];
        }
        return array_values(array_unique(array_filter($tokens)));
    }

    private function findMeetingByListingOnlineMeetings($userId, $joinUrl)
    {
        $tokens = $this->teamsUrlLookupTokens($joinUrl);
        try {
            $result = $this->graph->request(
                'GET',
                '/users/' . rawurlencode($userId) . '/onlineMeetings?$top=100'
            );
        } catch (Exception $e) {
            return null;
        }
        $meetings = isset($result['value']) ? $result['value'] : [];
        foreach ($meetings as $meeting) {
            $jw = isset($meeting['joinWebUrl']) ? $meeting['joinWebUrl'] : '';
            foreach ($tokens as $token) {
                if ($token !== '' && stripos($jw, $token) !== false) {
                    return $meeting;
                }
            }
        }
        return null;
    }

    private function findMeetingViaCalendar($userId, $interview, $joinUrl)
    {
        if (empty($interview['scheduled_date'])) {
            return null;
        }
        $range = $this->scheduledDayUtcRange($interview['scheduled_date']);
        if (!$range) {
            return null;
        }
        $path = '/users/' . rawurlencode($userId) . '/calendar/calendarView'
            . '?startDateTime=' . rawurlencode($range['start'])
            . '&endDateTime=' . rawurlencode($range['end'])
            . '&$top=50';
        try {
            $result = $this->graph->request('GET', $path);
        } catch (Exception $e) {
            return null;
        }
        $tokens = $this->teamsUrlLookupTokens($joinUrl);
        foreach (isset($result['value']) ? $result['value'] : [] as $event) {
            $om = isset($event['onlineMeeting']) ? $event['onlineMeeting'] : [];
            $eventJoin = isset($om['joinUrl']) ? $om['joinUrl'] : '';
            if ($eventJoin === '') {
                continue;
            }
            foreach ($tokens as $token) {
                if ($token !== '' && (stripos($eventJoin, $token) !== false || stripos($joinUrl, $token) !== false)) {
                    $resolved = $this->findMeetingByJoinUrl($userId, $eventJoin);
                    if ($resolved) {
                        return $resolved;
                    }
                }
            }
        }
        return null;
    }

    private function pickLatestRecordingFromMeeting($userId, $onlineMeetingId)
    {
        try {
            $recordings = $this->graph->request(
                'GET',
                '/users/' . rawurlencode($userId) . '/onlineMeetings/' . rawurlencode($onlineMeetingId) . '/recordings'
            );
        } catch (Exception $e) {
            return null;
        }
        $items = isset($recordings['value']) ? $recordings['value'] : [];
        if (empty($items)) {
            return null;
        }
        return $items[count($items) - 1];
    }

    private function findRecordingViaGetAll($userId, $interview, $joinUrl)
    {
        if (empty($interview['scheduled_date'])) {
            return null;
        }
        $range = $this->scheduledDayUtcRange($interview['scheduled_date']);
        if (!$range) {
            return null;
        }
        $safeUser = str_replace("'", "''", $userId);
        $path = '/users/' . rawurlencode($userId)
            . "/onlineMeetings/getAllRecordings(meetingOrganizerUserId='{$safeUser}',"
            . 'startDateTime=' . $range['start'] . ',endDateTime=' . $range['end'] . ')';

        $items = [];
        while ($path !== null && $path !== '') {
            $result = $this->graph->request('GET', $path);
            $chunk = isset($result['value']) ? $result['value'] : [];
            $items = array_merge($items, $chunk);
            $path = !empty($result['@odata.nextLink']) ? $result['@odata.nextLink'] : null;
        }
        if (empty($items)) {
            return null;
        }
        return $this->pickBestRecordingForInterview($items, $interview);
    }

    private function pickBestRecordingForInterview(array $recordings, $interview)
    {
        $target = $this->interviewTargetUtc($interview);
        $best = null;
        $bestScore = null;
        foreach ($recordings as $rec) {
            $when = isset($rec['createdDateTime']) ? $rec['createdDateTime'] : (isset($rec['endDateTime']) ? $rec['endDateTime'] : null);
            if (!$when) {
                continue;
            }
            try {
                $dt = new DateTime($when);
            } catch (Exception $e) {
                continue;
            }
            if ($target === null) {
                return $rec;
            }
            $score = abs($dt->getTimestamp() - $target->getTimestamp());
            if ($bestScore === null || $score < $bestScore) {
                $bestScore = $score;
                $best = $rec;
            }
        }
        return $best ?: $recordings[count($recordings) - 1];
    }

    private function interviewTargetUtc($interview)
    {
        if (empty($interview['scheduled_date'])) {
            return null;
        }
        $tz = new DateTimeZone(config('app')['timezone']);
        $utc = new DateTimeZone('UTC');
        $day = $interview['scheduled_date'];
        if (strpos($day, ' ') !== false) {
            $day = substr($day, 0, 10);
        }
        $time = !empty($interview['scheduled_start']) ? $interview['scheduled_start'] : '10:00:00';
        if (strlen($time) === 5) {
            $time .= ':00';
        }
        try {
            $dt = new DateTime($day . ' ' . $time, $tz);
            $dt->setTimezone($utc);
            return $dt;
        } catch (Exception $e) {
            return null;
        }
    }

    private function scheduledDayUtcRange($scheduledDate)
    {
        $tz = new DateTimeZone(config('app')['timezone']);
        $utc = new DateTimeZone('UTC');
        $day = $scheduledDate;
        if (strpos($day, ' ') !== false) {
            $day = substr($day, 0, 10);
        }
        try {
            $start = new DateTime($day . ' 00:00:00', $tz);
            $end = new DateTime($day . ' 23:59:59', $tz);
            $start->setTimezone($utc);
            $end->setTimezone($utc);
            return [
                'start' => $start->format('Y-m-d\TH:i:s\Z'),
                'end' => $end->format('Y-m-d\TH:i:s\Z'),
            ];
        } catch (Exception $e) {
            return null;
        }
    }

    private function downloadTeamsRecordingToFile($userId, $onlineMeetingId, $recording, $tempFile)
    {
        $recordingId = isset($recording['id']) ? $recording['id'] : '';
        $meta = $recording;
        if ($recordingId !== '' && $onlineMeetingId !== '') {
            try {
                $meta = $this->graph->request(
                    'GET',
                    '/users/' . rawurlencode($userId)
                    . '/onlineMeetings/' . rawurlencode($onlineMeetingId)
                    . '/recordings/' . rawurlencode($recordingId)
                );
            } catch (Exception $e) {
                $meta = $recording;
            }
        }

        $errors = [];
        $contentUrl = isset($meta['recordingContentUrl']) ? trim($meta['recordingContentUrl']) : '';
        if ($contentUrl !== '' && strpos($contentUrl, '$metadata') === false) {
            try {
                $this->downloadFromRecordingContentUrl($contentUrl, $tempFile);
                return;
            } catch (Exception $e) {
                $errors[] = 'recordingContentUrl: ' . $e->getMessage();
            }
        }

        if ($recordingId !== '' && $onlineMeetingId !== '') {
            $paths = [
                '/users/' . rawurlencode($userId)
                    . '/onlineMeetings/' . rawurlencode($onlineMeetingId)
                    . '/recordings/' . rawurlencode($recordingId)
                    . '/content',
                '/users/' . rawurlencode($userId) . '/'
                    . $this->odataEntityPath('onlineMeetings', $onlineMeetingId) . '/'
                    . $this->odataEntityPath('recordings', $recordingId) . '/content',
            ];
            foreach ($paths as $path) {
                try {
                    $this->graph->downloadContentPath($path, $tempFile);
                    return;
                } catch (Exception $e) {
                    $errors[] = $path . ': ' . $e->getMessage();
                }
            }
        }

        $msg = 'Recording has no downloadable content.';
        if (!empty($errors)) {
            $msg .= ' Tried: ' . implode(' | ', $errors);
        }
        throw new RuntimeException($msg);
    }

    private function odataEntityPath($collection, $key)
    {
        $quoted = "'" . str_replace("'", "''", $key) . "'";
        return $collection . '(' . rawurlencode($quoted) . ')';
    }

    /**
     * Teams may store recordings in OneDrive; recordingContentUrl then uses /drives/.../items/.../content.
     */
    private function downloadFromRecordingContentUrl($contentUrl, $tempFile)
    {
        if (strpos($contentUrl, '/drives/') !== false || strpos($contentUrl, 'drive/items') !== false) {
            $path = $contentUrl;
            if (strpos($path, 'https://graph.microsoft.com/v1.0') === 0) {
                $path = substr($path, strlen('https://graph.microsoft.com/v1.0'));
            } elseif (strpos($path, 'https://graph.microsoft.com/') === 0) {
                $path = preg_replace('#^https://graph\.microsoft\.com/v1\.0#', '', $path);
            }
            $this->graph->downloadContentPath($path, $tempFile);
            return;
        }
        if (strpos($contentUrl, 'graph.microsoft.com') !== false) {
            $this->graph->download($contentUrl, $tempFile);
            return;
        }
        $this->graph->downloadContentPath($contentUrl, $tempFile);
    }
}
