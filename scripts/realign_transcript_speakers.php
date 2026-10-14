<?php
/**
 * Fix swapped Interviewer/Candidate labels on an existing transcript (no re-STT).
 *
 * Usage:
 *   php scripts/realign_transcript_speakers.php --interview=<uuid>
 *   php scripts/realign_transcript_speakers.php --interview=<uuid> --reanalyze
 */
require __DIR__ . '/../app/bootstrap.php';

$interviewId = null;
$reanalyze = false;
foreach ($argv as $arg) {
    if (strpos($arg, '--interview=') === 0) {
        $interviewId = substr($arg, 12);
    }
    if ($arg === '--reanalyze') {
        $reanalyze = true;
    }
}
if (!$interviewId) {
    fwrite(STDERR, "Usage: php scripts/realign_transcript_speakers.php --interview=<uuid> [--reanalyze]\n");
    exit(1);
}

$row = db()->fetch(
    'SELECT t.*, i.id as interview_id, a.candidate_id
     FROM transcripts t
     JOIN interviews i ON i.id = t.interview_id
     JOIN applications a ON a.id = i.application_id
     WHERE t.interview_id = ?',
    [$interviewId]
);
if (!$row) {
    fwrite(STDERR, "No transcript for interview {$interviewId}\n");
    exit(1);
}

$candidate = db()->fetch('SELECT first_name, last_name FROM candidates WHERE id = ?', [$row['candidate_id']]);
$context = [
    'candidate_first_name' => $candidate['first_name'] ?? '',
    'candidate_last_name' => $candidate['last_name'] ?? '',
];

$segments = json_decode($row['segments'], true);
if (!is_array($segments)) {
    $segments = [];
}
if (empty($segments) && !empty($row['full_text'])) {
    $parsed = TranscriptSpeakerService::parseLabeledTranscript($row['full_text']);
    $segments = $parsed['segments'];
}

$segments = TranscriptSpeakerService::assignRoles($segments, $context);
$fullText = TranscriptSpeakerService::buildFullText($segments);

db()->update('transcripts', [
    'full_text' => $fullText,
    'segments' => json_encode($segments),
], 'interview_id = :id', ['id' => $interviewId]);

echo "Updated speaker labels for interview {$interviewId}\n";

if ($reanalyze) {
    echo "Run: php scripts/analyze_transcript.php --interview-id={$interviewId} --reanalyze\n";
}
