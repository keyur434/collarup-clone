<?php

/**
 * Assign Interviewer vs Candidate on diarized segments (ElevenLabs speaker_0/1 is not reliable).
 */
class TranscriptSpeakerService
{
    public static function normalizeTranscript(array $transcriptData, array $context = [])
    {
        if (empty($transcriptData['segments']) || !is_array($transcriptData['segments'])) {
            return $transcriptData;
        }

        $segments = self::assignRoles($transcriptData['segments'], $context);
        $fullText = self::buildFullText($segments);

        $transcriptData['segments'] = $segments;
        $transcriptData['full_text'] = $fullText;
        if (empty($transcriptData['raw_full_text'])) {
            $transcriptData['raw_full_text'] = $fullText;
        }

        return $transcriptData;
    }

    /**
     * @return array<int, array{speaker:string,text:string,start:float,end:float}>
     */
    public static function assignRoles(array $segments, array $context = [])
    {
        $bySpeaker = [];
        foreach ($segments as $seg) {
            $sp = isset($seg['speaker']) ? trim((string) $seg['speaker']) : '';
            if ($sp === '' || $sp === 'Transcript') {
                return $segments;
            }
            if (!isset($bySpeaker[$sp])) {
                $bySpeaker[$sp] = [];
            }
            $bySpeaker[$sp][] = (string) ($seg['text'] ?? '');
        }

        $keys = array_keys($bySpeaker);
        if (count($keys) < 2) {
            return self::canonicalizeSpeakerNames($segments);
        }

        if (count($keys) > 2) {
            $keys = self::topTwoSpeakerKeys($bySpeaker);
        }

        $scores = [];
        foreach ($keys as $sp) {
            $joined = implode(' ', $bySpeaker[$sp]);
            $scores[$sp] = self::scoreInterviewerText($joined) - self::scoreCandidateText($joined, $context);
        }

        arsort($scores);
        $interviewerKey = (string) key($scores);
        $candidateKey = null;
        foreach ($keys as $sp) {
            if ($sp !== $interviewerKey) {
                $candidateKey = $sp;
                break;
            }
        }
        if ($candidateKey === null) {
            return self::canonicalizeSpeakerNames($segments);
        }

        $map = [
            $interviewerKey => 'Interviewer',
            $candidateKey => 'Candidate',
        ];

        foreach ($segments as &$seg) {
            $sp = isset($seg['speaker']) ? (string) $seg['speaker'] : '';
            if (isset($map[$sp])) {
                $seg['speaker'] = $map[$sp];
            }
        }
        unset($seg);

        return $segments;
    }

    public static function buildFullText(array $segments)
    {
        $lines = [];
        foreach ($segments as $seg) {
            $text = trim((string) ($seg['text'] ?? ''));
            $speaker = trim((string) ($seg['speaker'] ?? ''));
            if ($text === '' || $speaker === '') {
                continue;
            }
            $lines[] = '[' . $speaker . ']: ' . $text;
        }
        return implode("\n", $lines);
    }

    /**
     * Parse [Interviewer]: / [Candidate]: lines back into segments (after GPT refine).
     */
    public static function parseLabeledTranscript($text)
    {
        $text = trim((string) $text);
        if ($text === '') {
            return ['segments' => [], 'full_text' => ''];
        }

        $segments = [];
        $lines = preg_split('/\r\n|\r|\n/', $text);
        $currentSpeaker = null;
        $buffer = [];

        foreach ($lines as $line) {
            if (preg_match('/^\[(Interviewer|Candidate)\]:\s*(.*)$/u', $line, $m)) {
                if ($currentSpeaker !== null && !empty($buffer)) {
                    $segments[] = [
                        'speaker' => $currentSpeaker,
                        'text' => trim(implode("\n", $buffer)),
                        'start' => 0,
                        'end' => 0,
                    ];
                }
                $currentSpeaker = $m[1];
                $buffer = [$m[2]];
            } elseif ($currentSpeaker !== null) {
                $buffer[] = $line;
            }
        }
        if ($currentSpeaker !== null && !empty($buffer)) {
            $segments[] = [
                'speaker' => $currentSpeaker,
                'text' => trim(implode("\n", $buffer)),
                'start' => 0,
                'end' => 0,
            ];
        }

        if (empty($segments)) {
            return ['segments' => [], 'full_text' => $text];
        }

        return [
            'segments' => $segments,
            'full_text' => self::buildFullText($segments),
        ];
    }

    public static function mergeRefinedText(array $transcriptData, $refinedText, array $context = [])
    {
        $parsed = self::parseLabeledTranscript($refinedText);
        if (!empty($parsed['segments'])) {
            $transcriptData['segments'] = self::assignRoles($parsed['segments'], $context);
            $transcriptData['full_text'] = self::buildFullText($transcriptData['segments']);
        } else {
            $transcriptData['full_text'] = $refinedText;
        }
        return $transcriptData;
    }

    private static function canonicalizeSpeakerNames(array $segments)
    {
        foreach ($segments as &$seg) {
            $sp = strtolower(trim((string) ($seg['speaker'] ?? '')));
            if ($sp === 'interviewer' || strpos($sp, 'agent') !== false) {
                $seg['speaker'] = 'Interviewer';
            } elseif ($sp === 'candidate' || strpos($sp, 'customer') !== false) {
                $seg['speaker'] = 'Candidate';
            }
        }
        unset($seg);
        return $segments;
    }

    private static function topTwoSpeakerKeys(array $bySpeaker)
    {
        $sizes = [];
        foreach ($bySpeaker as $sp => $chunks) {
            $sizes[$sp] = strlen(implode(' ', $chunks));
        }
        arsort($sizes);
        return array_slice(array_keys($sizes), 0, 2);
    }

    private static function scoreInterviewerText($text)
    {
        $t = mb_strtolower($text);
        $score = min(30, substr_count($text, '?') * 4);
        $patterns = [
            '/\bwhat\b/u', '/\bwhy\b/u', '/\bhow\b/u', '/\bhow many\b/u', '/\btell me\b/u',
            '/\bsuppose\b/u', '/\bcan you\b/u', '/\byou want\b/u', '/\bi think i am done\b/u',
            '/\bif you want to ask\b/u', '/\banything else\b/u',
            '/क्या/u', '/कितने/u', '/कौन/u', '/बताओ/u', '/तुम/u', '/तुम्हारे/u', '/तुमको/u',
            '/तुम्हारा/u', '/आप\b/u', '/पूछना/u', '/पूछ/u',
        ];
        foreach ($patterns as $p) {
            $score += preg_match_all($p, $t) * 3;
        }
        return $score;
    }

    private static function scoreCandidateText($text, array $context)
    {
        $t = mb_strtolower($text);
        $score = 0;
        $patterns = [
            '/\bमैंने\b/u', '/\bमेरा\b/u', '/\bमैं\b/u', '/\bमुझे\b/u', '/\bमेरे\b/u',
            '/\bहमारा\b/u', '/\bjoined\b/u', '/\bi have\b/u', '/\bi joined\b/u',
            '/\bmy experience\b/u', '/\bcurrently\b/u', '/\bthank you sir\b/u',
            '/\bexperience in\b/u', '/\bpromotion\b/u', '/\breporting\b/u',
        ];
        foreach ($patterns as $p) {
            $score += preg_match_all($p, $t) * 3;
        }
        $score += min(20, (int) (mb_strlen($text) / 400));

        $fn = mb_strtolower(trim($context['candidate_first_name'] ?? ''));
        $ln = mb_strtolower(trim($context['candidate_last_name'] ?? ''));
        if ($fn !== '' && mb_strpos($t, $fn) !== false) {
            $score += 4;
        }
        if ($ln !== '' && mb_strpos($t, $ln) !== false) {
            $score += 4;
        }

        return $score;
    }
}
