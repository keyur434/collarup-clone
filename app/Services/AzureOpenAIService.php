<?php

class AzureOpenAIService
{
    private $endpoint;
    private $endpointFallbacks;
    private $apiKey;
    private $deployment;
    private $deploymentId;
    private $extractionDeployment;
    private $apiVersion;
    private $apiMode;
    private $useBearer;
    private $refineDeployment;
    private $refineEnabled;

    public function __construct()
    {
        $config = config('services')['azure_openai'];
        $this->endpoint = rtrim($config['endpoint'], '/');
        $this->endpointFallbacks = isset($config['endpoint_fallbacks']) ? $config['endpoint_fallbacks'] : [];
        $this->apiKey = $config['api_key'];
        $this->deployment = $config['deployment'];
        $this->deploymentId = isset($config['deployment_id']) ? $config['deployment_id'] : '';
        $this->extractionDeployment = $config['extraction_deployment'];
        $this->apiVersion = $config['api_version'];
        $this->apiMode = isset($config['api_mode']) ? strtolower($config['api_mode']) : 'auto';
        $this->useBearer = !empty($config['use_bearer']);
        $this->refineDeployment = $config['refine_deployment'];
        $this->refineEnabled = $config['refine_enabled'];
    }

    public function isConfigured()
    {
        return !empty($this->endpoint) && !empty($this->apiKey);
    }

    public function getAnalysisDeployment()
    {
        return $this->deployment;
    }

    public function getExtractionDeployment()
    {
        return $this->extractionDeployment;
    }

    /**
     * Legacy single-pass analysis (ANALYSIS_PIPELINE=legacy).
     */
    public function analyzeInterview($context)
    {
        $prompt = $this->buildLegacyAnalysisPrompt($context);
        $response = $this->chat($prompt, true, $this->deployment, 0.1, 6000);
        return $this->parseAnalysisJson($response);
    }

    /**
     * Pass 1 — extract Q&A structure only. No scoring or judgment.
     */
    public function extractQaPairs($context)
    {
        $prompt = $this->buildQaExtractionPrompt($context);
        $response = $this->chat(
            $prompt,
            true,
            $this->extractionDeployment,
            0.0,
            8000,
            $this->systemMessageExtraction()
        );
        return $this->parseAnalysisJson($response);
    }

    /**
     * Pass 2 — rubric scoring, overview, integrity, phases. Uses pre-extracted Q&A as context.
     */
    public function analyzeScoring($context)
    {
        $prompt = $this->buildScoringPrompt($context);
        $response = $this->chat(
            $prompt,
            true,
            $this->deployment,
            0.0,
            7000,
            $this->systemMessageScoring()
        );
        return $this->parseAnalysisJson($response);
    }

    /**
     * Pass 3 — audit scoring claims against transcript; flag unsupported assertions.
     */
    public function auditGrounding($context)
    {
        $prompt = $this->buildGroundingAuditPrompt($context);
        $response = $this->chat(
            $prompt,
            true,
            $this->extractionDeployment,
            0.0,
            4000,
            $this->systemMessageGrounding()
        );
        return $this->parseAnalysisJson($response);
    }

    /**
     * GPT cleanup pass on raw STT output.
     */
    public function refineTranscript($rawTranscript, $jobContext = [])
    {
        if (!$this->refineEnabled || trim($rawTranscript) === '') {
            return $rawTranscript;
        }
        if (!$this->isConfigured()) {
            return $rawTranscript;
        }

        $jobTitle = isset($jobContext['job_title']) ? $jobContext['job_title'] : 'the role';
        $skills = isset($jobContext['key_skills']) ? $jobContext['key_skills'] : '';

        $prompt = <<<PROMPT
You fix speech-to-text output from a job interview for {$jobTitle}.

The raw transcript may mix Hindi, Gujarati, and English (Hinglish). Key skills/context: {$skills}

STRICT rules:
- Fix obvious misheard words and romanization only
- Keep the same languages — do NOT translate Hindi/Gujarati to English
- Do NOT add, remove, or summarize lines — same number of speaker turns as raw
- Do NOT add facts not clearly present in the raw text
- Do NOT write phrases like "removed for clarity" or markdown labels like **Agent:**
- Preserve [Interviewer]: and [Candidate]: prefixes if present

Return ONLY the corrected transcript. No preamble.

Raw transcript:
{$rawTranscript}
PROMPT;

        try {
            $refined = $this->chat($prompt, false, $this->refineDeployment, 0.0, 16000, $this->systemMessageRefine());
            $refined = $this->stripCodeFences($refined);
            if ($this->refinementRejected($rawTranscript, $refined)) {
                return $rawTranscript;
            }
            return $refined;
        } catch (Exception $e) {
            return $rawTranscript;
        }
    }

    public function chat($prompt, $jsonMode = false, $deployment = null, $temperature = 0.3, $maxTokens = 4000, $systemMessage = null)
    {
        if (!$this->isConfigured()) {
            return $this->mockAnalysis();
        }

        $deployment = $deployment ?: $this->deployment;
        if ($systemMessage === null) {
            $systemMessage = 'You are an expert HR interview analyst. Return valid JSON only when requested. Never invent facts not present in the transcript.';
        }
        $messages = [
            ['role' => 'system', 'content' => $systemMessage],
            ['role' => 'user', 'content' => $prompt],
        ];

        $errors = [];
        foreach ($this->buildChatAttempts($deployment) as $attempt) {
            $body = [
                'messages' => $messages,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
            ];
            if ($attempt['mode'] === 'v1') {
                $body['model'] = $attempt['deployment'];
            }
            if ($jsonMode) {
                $body['response_format'] = ['type' => 'json_object'];
            }

            foreach ($this->buildAuthHeaders() as $authHeaders) {
                $headers = array_merge(['Content-Type: application/json'], $authHeaders);
                $ch = curl_init($attempt['url']);
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode($body),
                    CURLOPT_HTTPHEADER => $headers,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 300,
                ]);
                $response = curl_exec($ch);
                $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode >= 200 && $httpCode < 300) {
                    $data = json_decode($response, true);
                    if (!empty($data['choices'][0]['message']['content'])) {
                        return $data['choices'][0]['message']['content'];
                    }
                    $errors[] = $attempt['label'] . ': empty response';
                    continue;
                }

                $errors[] = $attempt['label'] . ' HTTP ' . $httpCode . ': ' . substr((string) $response, 0, 400);
            }
        }

        throw new RuntimeException('Azure OpenAI failed after all endpoint/auth attempts. ' . implode(' | ', $errors));
    }

    /**
     * Lightweight connectivity test for CLI scripts.
     */
    public function ping($deployment = null)
    {
        $deployment = $deployment ?: $this->deployment;
        return $this->chat('Reply with JSON: {"ok":true}', true, $deployment, 0.0, 50);
    }

    private function buildChatAttempts($deployment)
    {
        $deployments = array_values(array_unique(array_filter([
            $deployment,
            $this->deploymentId,
        ])));

        $bases = array_values(array_unique(array_filter(array_merge(
            [$this->endpoint],
            $this->endpointFallbacks
        ))));

        $attempts = [];
        foreach ($bases as $base) {
            $base = $this->normalizeOpenAiBase($base);
            foreach ($deployments as $dep) {
                if ($this->apiMode === 'classic') {
                    $attempts[] = $this->classicAttempt($base, $dep);
                    continue;
                }
                if ($this->apiMode === 'v1') {
                    $attempts[] = $this->v1Attempt($base, $dep);
                    continue;
                }
                $attempts[] = $this->v1Attempt($base, $dep);
                $attempts[] = $this->classicAttempt($base, $dep);
            }
        }
        return $attempts;
    }

    private function normalizeOpenAiBase($base)
    {
        $base = rtrim($base, '/');
        if (preg_match('#/api/projects/[^/]+$#', $base)) {
            $base = preg_replace('#/api/projects/[^/]+$#', '', $base);
        }
        return rtrim($base, '/');
    }

    private function v1Attempt($base, $deployment)
    {
        return [
            'mode' => 'v1',
            'deployment' => $deployment,
            'url' => $base . '/openai/v1/chat/completions',
            'label' => 'v1 ' . $base . ' model=' . $deployment,
        ];
    }

    private function classicAttempt($base, $deployment)
    {
        return [
            'mode' => 'classic',
            'deployment' => $deployment,
            'url' => $base . '/openai/deployments/' . rawurlencode($deployment)
                . '/chat/completions?api-version=' . rawurlencode($this->apiVersion),
            'label' => 'classic ' . $base . ' deployment=' . $deployment,
        ];
    }

    private function buildAuthHeaders()
    {
        $variants = [];
        if ($this->useBearer) {
            $variants[] = ['Authorization: Bearer ' . $this->apiKey];
        }
        $variants[] = ['api-key: ' . $this->apiKey];
        if (!$this->useBearer) {
            $variants[] = ['Authorization: Bearer ' . $this->apiKey];
        }
        return $variants;
    }

    private function systemMessageRefine()
    {
        return 'You correct speech-to-text errors only. Never translate, summarize, or add content. Output plain transcript text only.';
    }

    private function systemMessageExtraction()
    {
        return 'You extract interview Q&A from transcripts. No scoring or judgment. Every answer must be traceable to the transcript. Return valid JSON only.';
    }

    private function systemMessageScoring()
    {
        return 'You score job interviews using ONLY explicit transcript evidence. If evidence is missing, say "Not discussed in interview" or use null scores. Never infer resume facts or assume skills not demonstrated. Return valid JSON only.';
    }

    private function systemMessageGrounding()
    {
        return 'You audit interview analysis for unsupported claims. Be strict: flag anything not directly supported by transcript quotes. Return valid JSON only.';
    }

    private function groundingRulesBlock()
    {
        return <<<'RULES'
GROUNDING RULES (mandatory):
1. The transcript is the ONLY source of truth. Job description fields are for context — not evidence the candidate demonstrated those skills.
2. Do NOT use resume knowledge, stereotypes, or assumptions about the role.
3. Every factual claim must be supportable by a direct quote or clear paraphrase from the transcript.
4. If a topic was not discussed, write exactly: "Not discussed in interview" — never guess.
5. Hinglish answers are valid — do not penalize mixed Hindi/Gujarati/English.
6. When audio is unclear, say "Unclear from transcript" instead of inventing content.
7. Prefer lower scores over confident guesses when evidence is weak.
8. Planned questions listed below may NOT have been asked — only credit what appears in the transcript.

SCORING ANCHORS (use these consistently):
- 0-3: No evidence or clear gap vs requirement
- 4-5: Limited or vague evidence
- 6-7: Adequate evidence with some gaps
- 8-9: Strong, specific evidence with examples from the interview
- 10: Exceptional, repeated strong evidence (rare — use sparingly)

RULES;
    }

    private function formatRubricBlock($context)
    {
        if (!empty($context['criteria_rubric']) && is_array($context['criteria_rubric'])) {
            $lines = [];
            foreach ($context['criteria_rubric'] as $row) {
                $name = isset($row['name']) ? $row['name'] : '';
                $desc = isset($row['description']) ? trim($row['description']) : '';
                if ($name === '') {
                    continue;
                }
                $lines[] = $desc !== '' ? "- {$name}: {$desc}" : "- {$name}";
            }
            if (!empty($lines)) {
                return implode("\n", $lines);
            }
        }
        $names = isset($context['criteria']) ? $context['criteria'] : [];
        return is_array($names) ? implode(', ', $names) : (string) $names;
    }

    private function buildQaExtractionPrompt($context)
    {
        $questions = isset($context['questions']) ? $context['questions'] : '';
        $rules = $this->groundingRulesBlock();
        return <<<PROMPT
Extract question-and-answer pairs from this job interview transcript.

{$rules}

EXTRACTION RULES (judgment-free):
- Do NOT score, rank, or evaluate the candidate
- Do NOT summarize quality — capture what was said
- Preserve Hinglish / mixed language in answer_text when spoken that way
- question: use the interviewer's words from the transcript when possible
- answer_text: candidate's words; paraphrase only if verbatim is unreadable
- transcript_quote: REQUIRED — shortest verbatim snippet from transcript supporting the answer (1-2 lines)
- confidence: high | medium | low (low if audio unclear or overlapping speakers)
- If no clear answer: answer_text="", confidence="low", note in extraction_notes
- Do NOT invent questions that are not in the transcript

Return JSON:
{
  "qa_pairs": [
    {
      "order": 1,
      "question": "interviewer question",
      "answer_text": "candidate response",
      "transcript_quote": "verbatim snippet from transcript",
      "confidence": "high|medium|low",
      "asked_by": "interviewer",
      "answered_by": "candidate"
    }
  ],
  "planned_questions_asked": ["list planned questions that were actually asked"],
  "planned_questions_not_asked": ["planned questions with no evidence in transcript"],
  "extraction_notes": "unclear audio, overlaps, or gaps"
}

Job Title: {$context['job_title']}

Planned interview questions (mark which were/were not asked):
{$questions}

Transcript:
{$context['transcript']}
PROMPT;
    }

    private function buildScoringPrompt($context)
    {
        $rubric = $this->formatRubricBlock($context);
        $rules = $this->groundingRulesBlock();
        $qaJson = json_encode(
            isset($context['qa_pairs']) ? $context['qa_pairs'] : [],
            JSON_UNESCAPED_UNICODE
        );

        return <<<PROMPT
Score this job interview using the rubric. Pre-extracted Q&A pairs are provided — do NOT re-extract Q&A.

{$rules}

OUTPUT RULES:
- Every overview field must cite transcript-backed facts only
- criterion_scores: include evidence_quotes[] (1-3 short quotes per criterion)
- If insufficient_evidence is true, set score to null and explain in evidence_notes
- integrity: only flag cheating if specific patterns appear in transcript (e.g. reading scripted answers, third-party voice) — default low
- information_completeness_score: % of planned questions actually covered in transcript
- topic_adherence_score: how well interview stayed on role-related topics (from transcript only)
- recommendation: Must | Maybe | No — with one-sentence reason tied to evidence

Return JSON:
{
  "overall_score": 0-10 or null,
  "experience_score": 0-10 or null,
  "culture_score": 0-10 or null,
  "soft_skills_score": 0-10 or null,
  "criterion_scores": [
    {
      "name": "criterion name",
      "score": 0-10 or null,
      "insufficient_evidence": false,
      "evidence_quotes": ["quote from transcript"],
      "evidence_notes": "what supports this score, or why evidence is missing"
    }
  ],
  "overview": {
    "strengths": "bullet facts from transcript only, or Not discussed in interview",
    "weaknesses": "bullet facts from transcript only, or Not discussed in interview",
    "red_flags": "only concrete concerns from transcript, or None identified",
    "team_fit": "evidence-based or Not discussed in interview",
    "cheating_detection": "evidence-based or No signs detected in transcript",
    "qualification_match": "based on what candidate SAID in interview vs JD — not resume",
    "logistics": "salary, location, notice period if discussed, else Not discussed in interview",
    "follow_up": "suggested follow-up questions for areas not covered",
    "recommendation": "Must | Maybe | No — reason"
  },
  "conversation_phases": [{"phase": "opening|technical|behavioral|closing", "summary": "brief factual summary"}],
  "key_questions_captured": ["themes actually discussed"],
  "planned_questions_missed": ["planned but not asked or not answered"],
  "topic_adherence_score": 0-100,
  "information_completeness_score": 0-100,
  "analysis_metadata": {
    "transcript_quality": "good|fair|poor",
    "evidence_strength": "strong|moderate|weak",
    "hallucination_risk": "low|medium|high"
  },
  "integrity": {
    "cheating_likelihood": "low|medium|high",
    "ai_answer_patterns": "not_detected|detected|na",
    "integrity_notes": "specific transcript evidence or none"
  }
}

Job Title: {$context['job_title']}
Key Skills: {$context['key_skills']}
Qualifications: {$context['qualifications']}
Must Have: {$context['must_have']}

Scoring rubric (score ONLY against evidence in transcript):
{$rubric}

Interview Questions (planned):
{$context['questions']}

Pre-extracted Q&A pairs:
{$qaJson}

Full Transcript:
{$context['transcript']}
PROMPT;
    }

    private function buildGroundingAuditPrompt($context)
    {
        $scoringJson = json_encode(
            isset($context['scoring_result']) ? $context['scoring_result'] : [],
            JSON_UNESCAPED_UNICODE
        );

        return <<<PROMPT
Audit the interview SCORING OUTPUT against the transcript. Your job is to catch hallucinations and unsupported claims.

RULES:
- Compare every strength, weakness, score, and integrity claim to the transcript
- Mark claims as supported only if a direct quote exists
- Suggest lowering scores that lack evidence
- Do NOT add new candidate facts
- safe_summary: 2-3 sentences using ONLY verified transcript facts

Return JSON:
{
  "grounding_score": 0-100,
  "unsupported_claims": [
    {
      "location": "overview.strengths | criterion_scores[0] | etc",
      "claim": "the questionable claim",
      "verdict": "unsupported|partial|supported",
      "transcript_quote": "quote if supported, else empty"
    }
  ],
  "criterion_flags": [
    {
      "name": "criterion name",
      "issue": "why score may be too high",
      "suggested_max_score": 0-10
    }
  ],
  "missing_planned_questions": ["planned questions not found in transcript"],
  "transcript_quality_issues": ["gaps, unclear audio, etc"],
  "safe_summary": "conservative summary for HR",
  "recommendation_adjustment": "no_change|strengthen_warning|downgrade",
  "recommendation_note": "final recommendation text for HR — conservative, evidence-based"
}

Job Title: {$context['job_title']}

Scoring output to audit:
{$scoringJson}

Transcript:
{$context['transcript']}
PROMPT;
    }

    private function buildLegacyAnalysisPrompt($context)
    {
        $criteria = implode(', ', $context['criteria']);
        return <<<PROMPT
Analyze this job interview transcript and return JSON with this exact structure:
{
  "overall_score": 0-10,
  "experience_score": 0-10,
  "culture_score": 0-10,
  "soft_skills_score": 0-10,
  "criterion_scores": [{"name": "criterion name", "score": 0-10}],
  "overview": {
    "strengths": "text",
    "weaknesses": "text",
    "red_flags": "text",
    "team_fit": "text",
    "cheating_detection": "text",
    "qualification_match": "text",
    "logistics": "text",
    "follow_up": "text",
    "recommendation": "text"
  },
  "qa_notes": [{"question": "question asked", "answer_summary": "summary of answer"}],
  "conversation_phases": [{"phase": "opening|technical|behavioral|closing", "summary": "brief summary"}],
  "key_questions_captured": ["important question themes covered"],
  "topic_adherence_score": 0-100,
  "information_completeness_score": 0-100,
  "integrity": {
    "cheating_likelihood": "low|medium|high",
    "ai_answer_patterns": "not_detected|detected|na"
  }
}

Job Title: {$context['job_title']}
Key Skills: {$context['key_skills']}
Qualifications: {$context['qualifications']}
Must Have: {$context['must_have']}
Scoring Criteria: {$criteria}

Interview Questions:
{$context['questions']}

Transcript:
{$context['transcript']}
PROMPT;
    }

    private function parseAnalysisJson($response)
    {
        $response = $this->stripCodeFences($response);
        $data = json_decode($response, true);
        if (!$data) {
            throw new RuntimeException('Failed to parse AI analysis JSON');
        }
        return $data;
    }

    private function stripCodeFences($text)
    {
        $text = trim((string) $text);
        if (strpos($text, '```') === false) {
            return $text;
        }
        $parts = explode('```', $text);
        if (count($parts) < 2) {
            return $text;
        }
        $inner = trim($parts[1]);
        if (strpos($inner, "\n") !== false) {
            $firstLine = strtok($inner, "\n");
            if (in_array($firstLine, ['json', 'text'], true)) {
                $inner = trim(substr($inner, strlen($firstLine)));
            }
        }
        return $inner;
    }

    private function refinementRejected($raw, $refined)
    {
        if (trim($refined) === '') {
            return true;
        }
        $forbidden = [
            'removed for clarity',
            'removed for brevity',
            '[audio unclear',
            '**customer:**',
            '**sales agent:**',
            '**agent:**',
        ];
        $low = strtolower($refined);
        foreach ($forbidden as $phrase) {
            if (strpos($low, $phrase) !== false) {
                return true;
            }
        }
        if (strlen(trim($raw)) > 50 && strlen($refined) > strlen($raw) * 1.35) {
            return true;
        }
        return false;
    }

    private function mockAnalysis()
    {
        return json_encode([
            'overall_score' => 6.5,
            'experience_score' => 6.8,
            'culture_score' => 6.3,
            'soft_skills_score' => 6.5,
            'criterion_scores' => [],
            'overview' => [
                'strengths' => 'Demonstrated relevant experience and communication skills.',
                'weaknesses' => 'Some technical depth gaps noted.',
                'red_flags' => 'None identified.',
                'team_fit' => 'Shows collaborative approach.',
                'cheating_detection' => 'No signs detected.',
                'qualification_match' => 'Moderate match for role requirements.',
                'logistics' => 'Availability and location discussed.',
                'follow_up' => 'Candidate asked relevant questions.',
                'recommendation' => 'Maybe - proceed with caution.',
            ],
            'qa_pairs' => [
                [
                    'order' => 1,
                    'question' => 'Tell me about your experience',
                    'answer_text' => 'Candidate described relevant background.',
                    'asked_by' => 'interviewer',
                    'answered_by' => 'candidate',
                ],
            ],
            'conversation_phases' => [
                ['phase' => 'opening', 'summary' => 'Introductions and role overview.'],
            ],
            'key_questions_captured' => ['Experience', 'Technical skills'],
            'topic_adherence_score' => 75,
            'information_completeness_score' => 70,
            'integrity' => [
                'cheating_likelihood' => 'low',
                'ai_answer_patterns' => 'not_detected',
            ],
        ]);
    }
}
