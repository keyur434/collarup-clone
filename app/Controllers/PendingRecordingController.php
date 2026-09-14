<?php



class PendingRecordingController

{

    public function index()

    {

        if (!PermissionService::canAssignPending()) {

            http_response_code(403);

            die('Access denied.');

        }



        $recordings = db()->fetchAll(

            "SELECT pr.*, c.first_name, c.last_name, j.title as job_title

             FROM pending_recordings pr

             LEFT JOIN interviews i ON i.id = pr.interview_id

             LEFT JOIN applications a ON a.id = i.application_id

             LEFT JOIN candidates c ON c.id = a.candidate_id

             LEFT JOIN jobs j ON j.id = a.job_id

             WHERE pr.status IN ('pending_assignment','failed')

             ORDER BY pr.created_at DESC"

        );



        render('pending.index', [

            'title' => 'Pending Recordings',

            'recordings' => $recordings,

            'jobs' => db()->fetchAll("SELECT id, title FROM jobs WHERE status = 'active' ORDER BY title"),

            'activeNav' => 'pending',

        ]);

    }



    public function assign($id)

    {

        verify_csrf();

        if (!PermissionService::canAssignPending()) {

            http_response_code(403);

            die('Access denied.');

        }



        $pending = db()->fetch('SELECT * FROM pending_recordings WHERE id = ?', [$id]);

        if (!$pending || $pending['status'] !== 'pending_assignment') {

            flash('error', 'Recording not found or already assigned.');

            redirect(url('pending-recordings'));

        }



        $applicationId = trim($_POST['application_id'] ?? '');

        $interviewId = trim($_POST['interview_id'] ?? '');



        if (!$applicationId && !$interviewId) {

            flash('error', 'Select a candidate interview to assign.');

            redirect(url('pending-recordings'));

        }



        if ($interviewId) {

            $interview = db()->fetch('SELECT * FROM interviews WHERE id = ?', [$interviewId]);

        } else {

            $interview = db()->fetch(

                'SELECT TOP 1 * FROM interviews WHERE application_id = ? ORDER BY created_at DESC',

                [$applicationId]

            );

        }



        if (!$interview) {

            flash('error', 'Interview not found.');

            redirect(url('pending-recordings'));

        }



        $mediaId = db()->insert('interview_media', [

            'interview_id' => $interview['id'],

            'media_type' => 'teams',

            'original_filename' => $pending['original_filename'],

            'drive_item_id' => $pending['drive_item_id'],

            'drive_path' => $pending['drive_path'],

            'storage_provider' => 'onedrive',

            'blob_container' => 'onedrive',

            'blob_name' => basename($pending['drive_path'] ?: $pending['original_filename']),

            'file_size_bytes' => $pending['file_size_bytes'],

            'duration_seconds' => $pending['duration_seconds'],

            'playback_mode' => 'blob',

            'processing_status' => 'ready',

            'external_url' => $pending['meeting_url'],

        ]);



        db()->update('pending_recordings', [

            'status' => 'assigned',

            'interview_id' => $interview['id'],

            'assigned_by' => auth_id(),

            'assigned_at' => date('Y-m-d H:i:s'),

        ], 'id = :id', ['id' => $id]);



        InterviewWorkflowService::markProcessing($interview['id']);

        $queue = new QueueService();

        $queue->push('AnalyzeInterview', ['interview_id' => $interview['id']]);



        ActivityLogService::log('assign_recording', 'pending_recording', $id, [

            'interview_id' => $interview['id'],

            'media_id' => $mediaId,

        ]);



        flash('success', 'Recording assigned. Analysis queued.');

        redirect(url('pending-recordings'));

    }



    public function searchInterviews()

    {

        if (!PermissionService::canAssignPending()) {

            json_response(['error' => 'Forbidden'], 403);

        }



        $jobId = $_GET['job_id'] ?? '';

        $q = trim($_GET['q'] ?? '');

        $where = "i.status IN ('pending','scheduled','processing','ongoing','incomplete')";

        $params = [];

        if ($jobId) {

            $where .= ' AND a.job_id = ?';

            $params[] = $jobId;

        }

        if ($q) {

            $where .= ' AND (c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ?)';

            $params[] = '%' . $q . '%';

            $params[] = '%' . $q . '%';

            $params[] = '%' . $q . '%';

        }



        $rows = db()->fetchAll(

            "SELECT TOP 20 i.id as interview_id, a.id as application_id,

                    c.first_name, c.last_name, c.email, j.title as job_title

             FROM interviews i

             JOIN applications a ON a.id = i.application_id

             JOIN candidates c ON c.id = a.candidate_id

             JOIN jobs j ON j.id = a.job_id

             WHERE $where

             ORDER BY i.created_at DESC",

            $params

        );



        json_response($rows);

    }

}


