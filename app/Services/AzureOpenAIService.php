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
    private $foundryProject;

    public function __construct()
    {
        $config = config('services')['azure_openai'];
        $this->endpoint = rtrim($config['endpoint'], '/');
        $this->endpointFallbacks = isset($config['endpoint_fallbacks']) ? $config['endpoint_fallbacks'] : [];
        $this->apiKey = $config['api_key'];
        $this->deployment = $config['deployment'];
        $this->deploymentId = isset($config['deployment_id']) ? $config['deployment_id'] : '';
        $this->foundryProject = isset($config['foundry_project']) ? trim($config['foundry_project']) : '';
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
     * Hiring desk preview — comparative brief across applicants on one job (not per-interview analysis).
     */
    public function generateJobPoolBrief(array $context)
    {
        $prompt = $this->buildJobPoolBriefPrompt($context);
        $response = $this->chat(
            $prompt,
            true,
            $this->deployment,
            0.1,
            9000,
            $this->systemMessagePoolBrief()
        );
        return $this->parseAnalysisJson($response);
    }

    public function analyzeResumeJdMatch(array $context)
    {
        $prompt = $this->buildResumeJdMatchPrompt($context);
        $response = $this->chat(
            $prompt,
            true,
            $this->deployment,
            0.0,
            3000,
            $this->systemMessagePoolBrief()
        );
        return $this->parseAnalysisJson($response);
    }

    /**
     * Suggest key skills from a pasted job description / responsibilities text.
     */
    public function extractSkillsFromJobDescription(array $context)
    {
        $prompt = $this->buildJobSkillsExtractionPrompt($context);
        $response = $this->chat(
            $prompt,
            true,
            $this->extractionDeployment,
            0.1,
            2500,
            $this->systemMessageSkillsExtraction()
        );
        return $this->parseAnalysisJson($response);
    }

    public function analyzePoolCompareBatch(array $context)
    {
        $prompt = $this->buildPoolCompareBatchPrompt($context);
        $response = $this->chat(
            $prompt,
            true,
            $this->deployment,
            0.1,
            5000,
            $this->systemMessagePoolBrief()
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
- Preserve [Interviewer]: and [Candidate]: prefixes if present, exactly as given — do not attempt to reassign or correct speaker labels here; that is handled downstream
- If a word could be a real word OR a misheard error, and you are not certain, LEAVE IT AS-IS. Do not guess.

Examples of correct fixes:
- "Elematic representitves" → "Elematic representatives" (spelling fix, safe)
- "we did post tension beems" → "we did post-tension beams" (spelling fix, safe)
- "kya kya equipments" — leave as-is (Hinglish, not an error)
- If unsure whether a company name spelling is correct, leave exactly as transcribed — do not standardize.

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
            ['role' => 'system', 'content' => sanitize_utf8_string($systemMessage)],
            ['role' => 'user', 'content' => sanitize_utf8_string($prompt)],
        ];

        $errors = [];
        foreach ($this->buildChatAttempts($deployment) as $attempt) {
            $body = sanitize_utf8_deep([
                'messages' => $messages,
                'temperature' => (float) $temperature,
                'max_tokens' => (int) $maxTokens,
            ]);
            if ($attempt['mode'] === 'v1') {
                $body['model'] = $attempt['deployment'];
            }
            if ($jsonMode) {
                $body['response_format'] = ['type' => 'json_object'];
            }

            $jsonFlags = JSON_UNESCAPED_UNICODE;
            if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
                $jsonFlags |= JSON_INVALID_UTF8_SUBSTITUTE;
            }
            $payload = json_encode($body, $jsonFlags);
            if ($payload === false) {
                throw new RuntimeException(
                    'Azure OpenAI request could not be encoded as JSON: ' . json_last_error_msg()
                );
            }

            foreach ($this->buildAuthHeaders() as $authHeaders) {
                $headers = array_merge(['Content-Type: application/json'], $authHeaders);
                $ch = curl_init($attempt['url']);
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $payload,
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

        throw new RuntimeException(self::formatConnectFailure($errors, $this->deployment));
    }

    /**
     * Short, actionable message for HR-facing errors (full detail still in logs / debug).
     */
    public static function formatConnectFailure(array $errors, $configuredDeployment = '')
    {
        $joined = implode(' | ', array_slice($errors, 0, 4));
        $hint = 'Set AZURE_OPENAI_DEPLOYMENT to the exact deployment name from Azure Portal → your resource → Deployments '
            . '(not the model SKU like gpt-4.1). On Foundry (*.services.ai.azure.com) use v1 with that name, or set AZURE_OPENAI_FOUNDRY_PROJECT if deployments are project-scoped. '
            . 'Run: php scripts/test_integrations.php openai';
        if ($configuredDeployment !== '') {
            $hint .= ' Currently configured: AZURE_OPENAI_DEPLOYMENT=' . $configuredDeployment . '.';
        }
        if (preg_match('/Missed model deployment|DeploymentNotFound/i', $joined)) {
            return 'Azure OpenAI: no matching model deployment on this resource. ' . $hint;
        }

        return 'Azure OpenAI failed after all endpoint/auth attempts. ' . $hint . ' Detail: ' . $joined;
    }

    /**
     * Lightweight connectivity test for CLI scripts.
     */
    public function ping($deployment = null)
    {
        $deployment = $deployment ?: $this->deployment;
        return $this->chat('Reply with JSON: {"ok":true}', true, $deployment, 0.0, 50);
    }

    /**
     * List deployment names on the resource (classic management API). Empty if unreachable.
     *
     * @return string[]
     */
    public function listDeploymentNames()
    {
        if (!$this->isConfigured()) {
            return [];
        }
        $bases = array_values(array_unique(array_filter(array_merge(
            [$this->endpoint],
            $this->endpointFallbacks
        ))));
        foreach ($bases as $base) {
            $base = $this->normalizeOpenAiBase($base);
            if (strpos($base, 'services.ai.azure.com') !== false) {
                continue;
            }
            $url = $base . '/openai/deployments?api-version=' . rawurlencode($this->apiVersion);
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_HTTPHEADER => ['api-key: ' . $this->apiKey],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
            ]);
            $response = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($code < 200 || $code >= 300) {
                continue;
            }
            $data = json_decode($response, true);
            if (empty($data['data']) || !is_array($data['data'])) {
                continue;
            }
            $names = [];
            foreach ($data['data'] as $row) {
                if (!empty($row['id'])) {
                    $names[] = $row['id'];
                } elseif (!empty($row['model'])) {
                    $names[] = $row['model'];
                }
            }

            return array_values(array_unique($names));
        }

        return [];
    }

    private function buildChatAttempts($deployment)
    {
        $deployments = $this->deploymentNamesForAttempts($deployment);

        $bases = array_values(array_unique(array_filter(array_merge(
            [$this->endpoint],
            $this->endpointFallbacks
        ))));

        $attempts = [];
        $seen = [];
        foreach ($bases as $base) {
            $base = $this->normalizeOpenAiBase($base);
            $foundryHost = strpos($base, 'services.ai.azure.com') !== false;

            if ($this->foundryProject !== '') {
                $projectBase = $base . '/api/projects/' . rawurlencode($this->foundryProject);
                foreach ($deployments as $dep) {
                    $this->pushAttempt($attempts, $seen, $this->v1Attempt($projectBase, $dep, 'foundry-project'));
                }
            }

            foreach ($deployments as $dep) {
                $modes = $this->apiModesForBase($foundryHost);
                foreach ($modes as $mode) {
                    if ($mode === 'classic') {
                        $this->pushAttempt($attempts, $seen, $this->classicAttempt($base, $dep));
                    } else {
                        $this->pushAttempt($attempts, $seen, $this->v1Attempt($base, $dep));
                    }
                }
            }
        }

        return $attempts;
    }

    private function deploymentNamesForAttempts($deployment)
    {
        $names = array_values(array_unique(array_filter([trim((string) $deployment), trim($this->deployment)])));
        $alt = trim($this->deploymentId);
        if ($alt !== '' && !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $alt)) {
            $names[] = $alt;
        }

        return array_values(array_unique($names));
    }

    private function apiModesForBase($foundryServicesHost)
    {
        if ($this->apiMode === 'classic') {
            return $foundryServicesHost ? [] : ['classic'];
        }
        if ($this->apiMode === 'v1') {
            return ['v1'];
        }
        if ($foundryServicesHost) {
            return ['v1'];
        }

        return ['classic', 'v1'];
    }

    private function pushAttempt(array &$attempts, array &$seen, array $attempt)
    {
        $key = $attempt['url'] . '|' . $attempt['mode'] . '|' . $attempt['deployment'];
        if (isset($seen[$key])) {
            return;
        }
        $seen[$key] = true;
        $attempts[] = $attempt;
    }

    private function normalizeOpenAiBase($base)
    {
        $base = rtrim($base, '/');
        if (preg_match('#/api/projects/[^/]+$#', $base)) {
            $base = preg_replace('#/api/projects/[^/]+$#', '', $base);
        }
        return rtrim($base, '/');
    }

    private function v1Attempt($base, $deployment, $tag = 'v1')
    {
        return [
            'mode' => 'v1',
            'deployment' => $deployment,
            'url' => rtrim($base, '/') . '/openai/v1/chat/completions',
            'label' => $tag . ' ' . $base . ' model=' . $deployment,
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
        return 'You extract interview Q&A from transcripts. No scoring or judgment. Every answer must be traceable to the transcript. Speaker labels in the raw transcript may be wrong — infer actual speaker role from content per grounding rules. Return valid JSON only.';
    }

    private function systemMessageScoring()
    {
        return 'You score job interviews using ONLY explicit transcript evidence. If evidence is missing, say "Not discussed in interview" or use null scores. Never infer resume facts or assume skills not demonstrated. Weight low-confidence QA pairs as weaker evidence, not equal to high-confidence pairs. Calibrate depth expected for strong (8-9) scores to seniority implied by job_title and qualifications. Return valid JSON only.';
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
9. SPEAKER LABELS MAY BE WRONG. [Interviewer]: and [Candidate]: prefixes come from automated diarization and are frequently mislabeled or swapped. Determine actual speaker role from CONTENT, not the label: the person answering career/experience questions in first person is the candidate. The person asking questions and steering topics is the interviewer. If a label contradicts its content, treat content as authoritative and note in extraction_notes / analysis_metadata.
10. MUST-HAVE ENFORCEMENT: must_have items are pass/fail gates. If a must_have was not addressed, contradicted, or clearly unmet from transcript evidence, overall_score MUST NOT exceed 5. State this explicitly in recommendation as the primary driver when applicable.

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
- confidence: high | medium | low (low if audio unclear, overlapping speakers, OR speaker label had to be inferred/corrected against the raw prefix)
- If no clear answer: answer_text="", confidence="low", note in extraction_notes
- Do NOT invent questions that are not in the transcript
- MERGING: if the interviewer asks a brief clarifying follow-up on the SAME topic immediately after the candidate's answer, treat follow-up and answer as part of the SAME qa_pair unless it introduces a clearly distinct topic.
- SPLITTING: if the candidate's single turn answers two or more DIFFERENT planned questions, split into separate qa_pairs with separate transcript_quotes.
- speaker_label_corrected: boolean — true if actual speaker role differs from the raw [Interviewer]/[Candidate] prefix in this segment

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
      "answered_by": "candidate",
      "speaker_label_corrected": false
    }
  ],
  "planned_questions_asked": ["list planned questions that were actually asked"],
  "planned_questions_not_asked": ["planned questions with no evidence in transcript"],
  "extraction_notes": "unclear audio, overlaps, gaps, or speaker-label corrections made"
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
- For any QA pair with confidence=low, do NOT treat it as strong evidence — note low-confidence source in evidence_notes
- integrity: only flag cheating if specific patterns appear in transcript (e.g. reading scripted answers, third-party voice) — default low
- information_completeness_score: % of planned questions actually covered in transcript
- topic_adherence_score: how well interview stayed on role-related topics (from transcript only). If roughly >15% of content was off-topic for evaluating this role, note in analysis_metadata and reflect in topic_adherence_score.
- recommendation: Must | Maybe | No — with one-sentence reason tied to evidence
- SCORE-TONE CONSISTENCY: if overall_score is 7+, recommendation must not primarily hedge ("suggests caution", "requires further clarification") — lower the score instead.
- SHORT/ABORTED INTERVIEW: if information_completeness_score would be below 20%, set overall_score, experience_score, culture_score, soft_skills_score to null and recommendation to "Insufficient data — interview too short/incomplete to evaluate".

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
      "evidence_notes": "what supports this score, or why evidence is missing, or low-confidence-source caveat"
    }
  ],
  "must_have_check": [
    {
      "requirement": "must-have item text",
      "status": "met|unmet|unclear",
      "evidence": "transcript evidence or Not discussed in interview"
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
    "recommendation": "Must | Maybe | No — reason, consistent in tone with overall_score"
  },
  "conversation_phases": [{"phase": "opening|technical|behavioral|closing", "summary": "brief factual summary"}],
  "key_questions_captured": ["themes actually discussed"],
  "planned_questions_missed": ["planned but not asked or not answered"],
  "topic_adherence_score": 0-100,
  "information_completeness_score": 0-100,
  "analysis_metadata": {
    "transcript_quality": "good|fair|poor",
    "evidence_strength": "strong|moderate|weak",
    "hallucination_risk": "low|medium|high",
    "off_topic_content_flag": "none|minor|substantial — note what off-topic content was if substantial"
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

    private function systemMessagePoolBrief()
    {
        return 'You are a senior talent advisor helping HR compare candidates for ONE job opening. '
            . 'You produce comparative insights only from the evidence provided. '
            . 'You never auto-hire or reject. Return valid JSON only.';
    }

    private function systemMessageSkillsExtraction()
    {
        return 'You extract concise skill labels from job descriptions for an ATS skill pool. '
            . 'Return valid JSON only. Skills must be short (1–4 words), no sentences.';
    }

    private function buildJobSkillsExtractionPrompt(array $context)
    {
        $jd = mb_substr((string) ($context['job_description'] ?? ''), 0, 14000);
        $title = $context['job_title'] ?? '';
        $quals = mb_substr((string) ($context['qualifications'] ?? ''), 0, 4000);

        return <<<PROMPT
Read this job description and list the most important technical and professional skills for hiring.

RULES:
- Return 8–20 distinct skills, ordered by importance for the role
- Each skill: short label (e.g. "React", "Project management", "SAP FICO") — not full sentences
- Include tools, frameworks, domains, certifications, and soft skills only if clearly required
- Do not invent skills not supported by the text
- Prefer standard industry wording

Return JSON:
{
  "reasoning": "brief",
  "skills": ["skill one", "skill two"]
}

Job title: {$title}
Qualifications (if any): {$quals}

Job description / responsibilities:
{$jd}
PROMPT;
    }

    private function buildResumeJdMatchPrompt(array $context)
    {
        $resume = $context['resume_text'] ?? '';
        return <<<PROMPT
Compare this resume to the job requirements. Use resume text only.

Return JSON:
{
  "reasoning": "brief",
  "match_score": 0-10,
  "match_grade": "A|B|C",
  "strengths_vs_jd": ["bullet"],
  "gaps_vs_jd": ["bullet"],
  "one_line_summary": "for HR"
}

Job: {$context['job_title']}
Skills: {$context['key_skills']}
Qualifications: {$context['qualifications']}
Must have: {$context['must_have']}

Resume:
{$resume}
PROMPT;
    }

    private function buildPoolCompareBatchPrompt(array $context)
    {
        $json = json_encode($context['candidates'], JSON_UNESCAPED_UNICODE);
        return <<<PROMPT
Listwise rank these candidates for the same job (batch {$context['batch_index']} of {$context['batch_total']}). Compare relative fit, not absolute scores alone.

Return JSON:
{
  "reasoning": "step-by-step comparison",
  "pool_narrative": "1-2 sentences on this batch",
  "ranking": [
    {
      "application_id": "uuid",
      "report_id": "uuid",
      "rank_in_batch": 1,
      "headline": "short",
      "why": "one sentence with evidence"
    }
  ]
}

Job: {$context['job_title']}
Must have: {$context['must_have']}
Skills: {$context['key_skills']}

Candidates:
{$json}
PROMPT;
    }

    private function buildJobPoolBriefPrompt(array $context)
    {
        $candidatesJson = json_encode($context['candidates'], JSON_UNESCAPED_UNICODE);
        $rubricJson = json_encode($context['rubric'], JSON_UNESCAPED_UNICODE);
        $distJson = json_encode($context['score_distribution'], JSON_UNESCAPED_UNICODE);
        $truncNote = (int) ($context['pool_truncated_count'] ?? 0) > 0
            ? 'Note: ' . (int) $context['pool_truncated_count'] . ' additional reported candidates were omitted from this prompt (lowest scores). Mention uncertainty if relevant.'
            : '';

        return <<<PROMPT
Compare ALL candidates in this applicant pool for the same job. Produce ORIGINAL comparative insight — not a copy of individual report summaries.

RULES:
- Use only facts from the candidate bundles (scores, qa_sample, evidence_by_criterion, must_have_check, overview_snippets).
- Every comparative claim must cite application_id and report_id in evidence_refs.
- suggested_shortlist is a PROPOSAL for HR review (max 5). Do not say "hire" or "reject".
- head_to_head: 1-3 pairs among top contenders with clear tradeoffs for THIS role.
- If evidence is thin, say so and lower confidence.
- {$truncNote}

Return JSON with this shape (reasoning first):
{
  "reasoning": "internal step-by-step comparison across the pool (plain text)",
  "disclaimer": "Assistive brief for HR review — not an automated hiring decision",
  "confidence": "high|medium|low",
  "cohort_summary": "2-4 sentences: what this pool looks like vs the role bar",
  "pool_interpretation": "how to read score spread / who stands out / where scores cluster",
  "suggested_shortlist": [
    {
      "application_id": "uuid",
      "report_id": "uuid",
      "rank": 1,
      "headline": "short label",
      "why_consider": ["bullet"],
      "risks_or_gaps": ["bullet"],
      "evidence_refs": [{"application_id":"uuid","report_id":"uuid","quote":"short quote or score fact"}]
    }
  ],
  "head_to_head": [
    {
      "application_id_a": "uuid",
      "application_id_b": "uuid",
      "summary": "who is stronger for what on this role",
      "tradeoffs": ["A better at X", "B better at Y"],
      "evidence_refs": [{"application_id":"uuid","report_id":"uuid","quote":"..."}]
    }
  ],
  "committee_questions": ["questions HR should ask before advancing anyone"],
  "coverage_gaps": ["role requirements weakly evidenced across the pool"],
  "integrity_or_risk_notes": ["pool-level red flags worth HR judgment"]
}

Job Title: {$context['job_title']}
Key skills: {$context['key_skills']}
Qualifications: {$context['qualifications']}
Must have: {$context['must_have']}
Responsibilities: {$context['key_responsibility']}
Rubric: {$rubricJson}
Pool size (with reports): {$context['pool_size_with_reports']}
Score distribution: {$distJson}

Candidates:
{$candidatesJson}
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
- Verify must_have_check statuses against transcript — flag if any "met" lacks evidence
- Verify overall_score and recommendation tone agree — flag if score is 7+ but recommendation is hedged, or score is low but recommendation is confident
- safe_summary: 2-3 sentences using ONLY verified transcript facts

Return JSON:
{
  "grounding_score": 0-100,
  "unsupported_claims": [
    {
      "location": "overview.strengths | criterion_scores[0] | must_have_check[0] | etc",
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
  "score_tone_mismatch": {
    "detected": false,
    "detail": "explanation if detected"
  },
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
