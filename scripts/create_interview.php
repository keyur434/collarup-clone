<?php
/**
 * Create candidate + application + interview for manual transcript testing.
 *
 * Usage:
 *   php scripts/create_interview.php --list-jobs
 *   php scripts/create_interview.php --job-id=<uuid> --first-name=Jane --last-name=Doe --email=jane@example.com
 */
require __DIR__ . '/../app/bootstrap.php';

$opts = getopt('', ['list-jobs', 'job-id:', 'first-name:', 'last-name:', 'email:', 'help']);

if (isset($opts['help']) || empty($opts)) {
    echo <<<HELP
Create interview shell (no video) for manual transcript analysis.

  --list-jobs                     Show jobs + first interview stage
  --job-id=<uuid>                 Job to attach candidate to
  --first-name=<name>             Candidate first name
  --last-name=<name>              Candidate last name
  --email=<email>                 Candidate email (unique per person)

Example — 3 candidates on job "test":
  php scripts/create_interview.php --job-id=a15e3ab8-ff4f-4785-a321-3f95e096677a --first-name=Candidate --last-name=One --email=c1@test.local
  php scripts/create_interview.php --job-id=a15e3ab8-ff4f-4785-a321-3f95e096677a --first-name=Candidate --last-name=Two --email=c2@test.local

Then analyze:
  php scripts/analyze_transcript.php --interview-id=<printed-uuid> --file=transcript1.txt

HELP;
    exit(isset($opts['help']) ? 0 : 1);
}

if (isset($opts['list-jobs'])) {
    $jobs = db()->fetchAll(
        "SELECT j.id, j.title, j.status,
                (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id AND a.is_archived = 0) as app_count
         FROM jobs j
         ORDER BY j.created_at DESC"
    );
    echo "Jobs:\n\n";
    foreach ($jobs as $j) {
        $stage = db()->fetch(
            "SELECT TOP 1 id, name FROM job_pipeline_stages
             WHERE job_id = ? AND is_active = 1 AND stage_type IN ('round','ai_interview')
             ORDER BY sort_order",
            [$j['id']]
        );
        echo $j['id'] . "\n";
        echo "  Title: " . $j['title'] . " (" . $j['status'] . ")\n";
        echo "  Active applications: " . $j['app_count'] . "\n";
        if ($stage) {
            echo "  Interview stage: " . $stage['name'] . " (" . $stage['id'] . ")\n";
        } else {
            echo "  Interview stage: NONE — add pipeline stages in job settings\n";
        }
        echo "\n";
    }
    exit(0);
}

$jobId = isset($opts['job-id']) ? trim($opts['job-id']) : '';
$firstName = isset($opts['first-name']) ? trim($opts['first-name']) : '';
$lastName = isset($opts['last-name']) ? trim($opts['last-name']) : '';
$email = isset($opts['email']) ? trim($opts['email']) : '';

if ($jobId === '' || $firstName === '' || $lastName === '' || $email === '') {
    fwrite(STDERR, "Error: --job-id, --first-name, --last-name, --email are required (or --list-jobs).\n");
    exit(1);
}

$job = db()->fetch('SELECT id, title FROM jobs WHERE id = ?', [$jobId]);
if (!$job) {
    fwrite(STDERR, "Error: job not found: {$jobId}\n");
    exit(1);
}

$stage = db()->fetch(
    "SELECT TOP 1 id, name FROM job_pipeline_stages
     WHERE job_id = ? AND is_active = 1 AND stage_type IN ('round','ai_interview')
     ORDER BY sort_order",
    [$jobId]
);
if (!$stage) {
    fwrite(STDERR, "Error: no interview stage on job \"{$job['title']}\". Open job settings and add a Round stage.\n");
    exit(1);
}

$existing = db()->fetch('SELECT id FROM candidates WHERE email = ?', [$email]);
if ($existing) {
    $dup = db()->fetch(
        'SELECT a.id FROM applications a WHERE a.candidate_id = ? AND a.job_id = ? AND a.is_archived = 0',
        [$existing['id'], $jobId]
    );
    if ($dup) {
        fwrite(STDERR, "Error: {$email} already has an application on this job.\n");
        exit(1);
    }
    $candidateId = $existing['id'];
    echo "Using existing candidate {$candidateId}\n";
} else {
    $candidateId = uuid();
    db()->insert('candidates', [
        'id' => $candidateId,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $email,
    ]);
    echo "Created candidate {$candidateId}\n";
}

$appId = uuid();
db()->insert('applications', [
    'id' => $appId,
    'candidate_id' => $candidateId,
    'job_id' => $jobId,
    'current_stage_id' => $stage['id'],
    'status' => 'pending',
    'application_date' => date('Y-m-d'),
]);

$interviewId = uuid();
db()->insert('interviews', [
    'id' => $interviewId,
    'application_id' => $appId,
    'stage_id' => $stage['id'],
    'status' => 'pending',
]);

echo "\nCreated interview for manual transcript:\n";
echo "  Job: {$job['title']} ({$jobId})\n";
echo "  Candidate: {$firstName} {$lastName} <{$email}>\n";
echo "  Stage: {$stage['name']}\n";
echo "  interview_id: {$interviewId}\n\n";
echo "Next:\n";
echo "  php scripts/analyze_transcript.php --interview-id={$interviewId} --file=path/to/transcript.txt\n";
