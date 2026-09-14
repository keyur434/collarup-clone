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
        $response = $this->chat($prompt, true, $this->extractionDeployment, 0.0, 8000);
        return $this->parseAnalysisJson($response);
    }

    /**
     * Pass 2 — rubric scoring, overview, integrity, phases. Uses pre-extracted Q&A as context.
     */
    public function analyzeScoring($context)
    {
        $prompt = $this->buildScoringPrompt($context);
        $response = $this->chat($prompt, true, $this->deployment, 0.1, 6000);
        return $this->parseAnalysisJson($response);
    }

    /**
     * GPT cleanup pass on raw STT (same pattern as magicrete_call_pipeline_2.py).
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
            $refined = $this->chat($prompt, false, $this->refineDeployment, 0.1);
            $refined = $this->stripCodeFences($refined);
            if ($this->refinementRejected($rawTranscript, $refined)) {
                return $rawTranscript;
            }
            return $refined;
        } catch (Exception $e) {
            return $rawTranscript;
        }
    }

    public function chat($prompt, $jsonMode = false, $deployment = null, $temperature = 0.3, $maxTokens = 4000)
    {
        if (!$this->isConfigured()) {
            return $this->mockAnalysis();
        }

        $deployment = $deployment ?: $this->deployment;
        $messages = [
            ['role' => 'system', 'content' => 'You are an expert HR interview analyst. Return valid JSON only when requested.'],
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

    private function buildQaExtractionPrompt($context)
    {
        $questions = isset($context['questions']) ? $context['questions'] : '';
        return <<<PROMPT
Extract question-and-answer pairs from this job interview transcript.

RULES — judgment-free extraction only:
- Do NOT score, rank, or evaluate the candidate
- Do NOT summarize quality of answers — capture what was said
- Preserve Hinglish / mixed language in answer_text when spoken that way
- Include follow-up questions as separate pairs when the interviewer asks them
- If a question was implied but not stated verbatim, use the closest interviewer line as question
- If no clear answer, set answer_text to "" and note in extraction_notes

Return JSON:
{
  "qa_pairs": [
    {
      "order": 1,
      "question": "interviewer question as spoken or written",
      "answer_text": "candidate response, faithful to transcript",
      "asked_by": "interviewer",
      "answered_by": "candidate"
    }
  ],
  "extraction_notes": "optional notes on unclear audio or overlapping speech"
}

Job Title: {$context['job_title']}

Planned interview questions (for alignment only — also extract ad-hoc questions from transcript):
{$questions}

Transcript:
{$context['transcript']}
PROMPT;
    }

    private function buildScoringPrompt($context)
    {
        $criteria = implode(', ', $context['criteria']);
        $qaJson = json_encode(
            isset($context['qa_pairs']) ? $context['qa_pairs'] : [],
            JSON_UNESCAPED_UNICODE
        );

        return <<<PROMPT
Score this job interview using the rubric below. Pre-extracted Q&A pairs are provided for context — do NOT re-extract Q&A in your output.

Return JSON with this exact structure:
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

Interview Questions (planned):
{$context['questions']}

Pre-extracted Q&A pairs (reference):
{$qaJson}

Full Transcript:
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
