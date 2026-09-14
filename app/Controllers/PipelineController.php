<?php



class PipelineController

{

    public function index()

    {

        $jobId = isset($_GET['job_id']) ? $_GET['job_id'] : (isset($_GET['jobId']) ? $_GET['jobId'] : '');

        $jobs = db()->fetchAll("SELECT id, title FROM jobs WHERE status = 'active' ORDER BY title");

        if (!$jobId && !empty($jobs)) {

            $jobId = $jobs[0]['id'];

        }



        $columns = [];

        if ($jobId) {

            PermissionService::requireJobAccess($jobId);



            $stages = db()->fetchAll(

                'SELECT * FROM job_pipeline_stages WHERE job_id = ? AND is_active = 1 ORDER BY sort_order',

                [$jobId]

            );



            foreach ($stages as $stage) {

                $candidates = db()->fetchAll(

                    "SELECT a.status, c.first_name, c.last_name, c.email, j.title as job_title,

                            i.scheduled_date, i.status as interview_status

                     FROM applications a

                     JOIN candidates c ON c.id = a.candidate_id

                     JOIN jobs j ON j.id = a.job_id

                     LEFT JOIN interviews i ON i.application_id = a.id AND i.stage_id = a.current_stage_id

                     WHERE a.job_id = ? AND a.current_stage_id = ?

                     ORDER BY a.updated_at DESC",

                    [$jobId, $stage['id']]

                );



                foreach ($candidates as &$row) {

                    $row['status'] = $row['interview_status'] ?: $row['status'];

                }

                unset($row);



                $columns[] = [

                    'name' => $stage['name'],

                    'candidates' => $candidates,

                ];

            }

        }



        render('pipeline.index', [

            'title' => 'Pipeline',

            'jobs' => $jobs,

            'jobId' => $jobId,

            'columns' => $columns,

            'activeNav' => 'pipeline',

        ]);

    }

}


