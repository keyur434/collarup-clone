<?php
/**
 * Run AI analysis on an existing transcript (no video / no ElevenLabs).
 *
 * Usage:
 *   php scripts/analyze_transcript.php --list
 *   php scripts/analyze_transcript.php --interview-id=<uuid> --file=transcript.txt
 *   php scripts/analyze_transcript.php --interview-id=<uuid> --reanalyze
 *   php scripts/analyze_transcript.php --interview-id=<uuid> --file=transcript.txt --skip-refine
 */
require __DIR__ . '/../app/bootstrap.php';

$opts = getopt('', ['list', 'interview-id:', 'file:', 'reanalyze', 'skip-refine', 'help']);

if (isset($opts['help'])) {
    echo <<<HELP
Manual transcript analysis (v3-grounded prompts)

  --list                          Show recent interviews with job/candidate
  --interview-id=<uuid>           Required — interview to attach report to
  --file=<path>                   Transcript text file (.txt)
  --reanalyze                     Use transcript already saved in DB
  --skip-refine                   Skip GPT refine pass (use raw text as-is)

Examples:
  php scripts/analyze_transcript.php --list
  php scripts/analyze_transcript.php --interview-id=abc-123 --file=D:/transcripts/interview1.txt
  php scripts/analyze_transcript.php --interview-id=abc-123 --reanalyze

Open the printed report URL in the browser to review UI/UX.

HELP;
    exit(0);
}

if (isset($opts['list'])) {
    $rows = db()->fetchAll(
        "SELECT TOP 20 i.id as interview_id, i.status, j.id as job_id, j.title as job_title,
                c.first_name, c.last_name, c.email,
                (SELECT TOP 1 ir.id FROM interview_reports ir WHERE ir.interview_id = i.id ORDER BY ir.created_at DESC) as report_id
         FROM interviews i
         JOIN applications a ON a.id = i.application_id
         JOIN jobs j ON j.id = a.job_id
         JOIN candidates c ON c.id = a.candidate_id
         ORDER BY i.created_at DESC"
    );
    echo "Recent interviews:\n\n";
    foreach ($rows as $r) {
        $name = trim($r['first_name'] . ' ' . $r['last_name']);
        echo $r['interview_id'] . "\n";
        echo "  Job: " . $r['job_title'] . " (" . $r['job_id'] . ")\n";
        echo "  Candidate: {$name} <{$r['email']}>\n";
        echo "  Status: " . $r['status'];
        if ($r['report_id']) {
            echo " | Report: " . url('jobs/' . $r['job_id'] . '/reports/' . $r['report_id']);
        }
        echo "\n\n";
    }
    exit(0);
}

$interviewId = isset($opts['interview-id']) ? trim($opts['interview-id']) : '';
if ($interviewId === '') {
    fwrite(STDERR, "Error: --interview-id is required (or use --list).\n");
    exit(1);
}

$service = new InterviewReportService();
$skipRefine = isset($opts['skip-refine']);

if (isset($opts['reanalyze'])) {
    $transcript = db()->fetch('SELECT TOP 1 * FROM transcripts WHERE interview_id = ? ORDER BY id DESC', [$interviewId]);
    if (!$transcript || trim($transcript['full_text']) === '') {
        fwrite(STDERR, "Error: no transcript in DB for interview {$interviewId}. Use --file= instead.\n");
        exit(1);
    }
    $text = $transcript['full_text'];
    echo "Re-analyzing saved transcript (" . strlen($text) . " chars)...\n";
} elseif (isset($opts['file'])) {
    $path = $opts['file'];
    if (!is_readable($path)) {
        fwrite(STDERR, "Error: cannot read file: {$path}\n");
        exit(1);
    }
    $text = file_get_contents($path);
    echo "Loaded transcript from {$path} (" . strlen($text) . " chars)...\n";
} else {
    fwrite(STDERR, "Error: provide --file=<path> or --reanalyze\n");
    exit(1);
}

echo "Running analysis (refine=" . ($skipRefine ? 'off' : 'on') . ", grounding pass per .env)...\n";
$start = microtime(true);

try {
    $result = $service->analyzeFromTranscript($interviewId, $text, [
        'skip_refine' => $skipRefine,
        'provider' => isset($opts['reanalyze']) ? 'manual-reanalyze' : 'manual',
    ]);
} catch (Exception $e) {
    fwrite(STDERR, "Failed: " . $e->getMessage() . "\n");
    exit(1);
}

$elapsed = round(microtime(true) - $start, 1);
$analysis = $result['analysis'];

echo "\nDone in {$elapsed}s\n";
echo "Report ID: " . $result['report_id'] . "\n";
echo "Open in browser:\n  " . $result['report_url'] . "\n\n";

if (isset($analysis['overall_score'])) {
    echo "Overall score: " . $analysis['overall_score'] . "\n";
}
if (!empty($analysis['overview']['recommendation'])) {
    echo "Recommendation: " . $analysis['overview']['recommendation'] . "\n";
}
if (!empty($analysis['grounding_audit']['grounding_score'])) {
    echo "Grounding score: " . $analysis['grounding_audit']['grounding_score'] . "/100\n";
}
if (!empty($analysis['analysis_metadata'])) {
    echo "Evidence: " . (isset($analysis['analysis_metadata']['evidence_strength']) ? $analysis['analysis_metadata']['evidence_strength'] : 'n/a');
    echo " | Hallucination risk: " . (isset($analysis['analysis_metadata']['hallucination_risk']) ? $analysis['analysis_metadata']['hallucination_risk'] : 'n/a') . "\n";
}
if (!empty($analysis['grounding_safe_summary'])) {
    echo "\nSafe summary:\n" . $analysis['grounding_safe_summary'] . "\n";
}
