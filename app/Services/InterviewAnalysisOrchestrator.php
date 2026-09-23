<?php

/**
 * Coordinates multi-pass interview analysis and audit logging.
 * Phase 1: Q&A extraction (no judgment) + rubric scoring.
 */
class InterviewAnalysisOrchestrator
{
    const PROMPT_VERSION = 'v3-grounded';
    const PASS_QA = 'qa_extraction';
    const PASS_SCORING = 'scoring';
    const PASS_GROUNDING = 'grounding_audit';

    /** @var AzureOpenAIService */
    private $openai;

    public function __construct(AzureOpenAIService $openai = null)
    {
        $this->openai = $openai ?: new AzureOpenAIService();
    }

    /**
     * Run analysis pipeline and return merged report array.
     *
     * @param string $interviewId
     * @param array $context job_title, key_skills, qualifications, must_have, criteria, questions, transcript
     * @return array
     */
    public function analyze($interviewId, array $context)
    {
        $pipeline = $this->pipelineMode();
        if ($pipeline === 'legacy') {
            return $this->openai->analyzeInterview($context);
        }

        $runId = $this->startRun($interviewId);

        try {
            $qaResult = $this->executePass(
                $runId,
                $interviewId,
                self::PASS_QA,
                1,
                $this->openai->getExtractionDeployment(),
                function () use ($context) {
                    return $this->openai->extractQaPairs($context);
                }
            );

            $scoringContext = $context;
            $scoringContext['qa_pairs'] = isset($qaResult['qa_pairs']) ? $qaResult['qa_pairs'] : [];

            $scoringResult = $this->executePass(
                $runId,
                $interviewId,
                self::PASS_SCORING,
                2,
                $this->openai->getAnalysisDeployment(),
                function () use ($scoringContext) {
                    return $this->openai->analyzeScoring($scoringContext);
                }
            );

            $merged = $this->mergeResults($qaResult, $scoringResult);
            $passes = [self::PASS_QA, self::PASS_SCORING];

            if ($this->groundingPassEnabled()) {
                $auditContext = $context;
                $auditContext['qa_pairs'] = isset($qaResult['qa_pairs']) ? $qaResult['qa_pairs'] : [];
                $auditContext['scoring_result'] = $scoringResult;
                $auditResult = $this->executePass(
                    $runId,
                    $interviewId,
                    self::PASS_GROUNDING,
                    3,
                    $this->openai->getExtractionDeployment(),
                    function () use ($auditContext) {
                        return $this->openai->auditGrounding($auditContext);
                    }
                );
                $merged = $this->applyGroundingAudit($merged, $auditResult);
                $passes[] = self::PASS_GROUNDING;
            }

            $merged['_pipeline'] = [
                'mode' => 'multipass',
                'prompt_version' => self::PROMPT_VERSION,
                'run_id' => $runId,
                'passes' => $passes,
            ];

            $this->completeRun($runId);
            return $merged;
        } catch (Exception $e) {
            $this->failRun($runId, $e->getMessage());
            throw $e;
        }
    }

    private function pipelineMode()
    {
        $config = config('services')['azure_openai'];
        $mode = isset($config['analysis_pipeline']) ? strtolower($config['analysis_pipeline']) : 'multipass';
        return in_array($mode, ['legacy', 'multipass'], true) ? $mode : 'multipass';
    }

    private function groundingPassEnabled()
    {
        $config = config('services')['azure_openai'];
        return !isset($config['grounding_pass']) || !empty($config['grounding_pass']);
    }

    /**
     * Apply conservative adjustments from grounding audit (no silent score inflation).
     */
    private function applyGroundingAudit(array $merged, array $audit)
    {
        $merged['grounding_audit'] = $audit;
        $groundingScore = isset($audit['grounding_score']) ? (int) $audit['grounding_score'] : null;

        if (!empty($audit['criterion_flags']) && !empty($merged['criterion_scores'])) {
            foreach ($merged['criterion_scores'] as $idx => $cs) {
                foreach ($audit['criterion_flags'] as $flag) {
                    if (!empty($flag['name']) && $flag['name'] === $cs['name'] && isset($flag['suggested_max_score'])) {
                        $cap = (float) $flag['suggested_max_score'];
                        if (isset($cs['score']) && $cs['score'] > $cap) {
                            $merged['criterion_scores'][$idx]['score'] = $cap;
                            $merged['criterion_scores'][$idx]['score_capped_by_audit'] = true;
                        }
                    }
                }
            }
        }

        if ($groundingScore !== null && $groundingScore < 70 && isset($merged['overall_score'])) {
            $merged['overall_score'] = min((float) $merged['overall_score'], 6.5);
            $merged['overall_score_capped_by_audit'] = true;
        }

        if (!empty($audit['safe_summary'])) {
            $merged['grounding_safe_summary'] = $audit['safe_summary'];
        }

        if (!empty($audit['recommendation_note']) && isset($merged['overview']['recommendation'])) {
            $prefix = ($groundingScore !== null && $groundingScore < 70)
                ? '[Verify against transcript — limited grounding] '
                : '';
            $merged['overview']['recommendation'] = $prefix . $audit['recommendation_note'];
        } elseif ($groundingScore !== null && $groundingScore < 70 && isset($merged['overview']['recommendation'])) {
            $merged['overview']['recommendation'] = '[Verify against transcript — limited grounding] '
                . $merged['overview']['recommendation'];
        }

        return $merged;
    }

    private function mergeResults(array $qaResult, array $scoringResult)
    {
        $merged = $scoringResult;
        $merged['qa_notes'] = $this->qaPairsToNotes($qaResult);
        $merged['qa_extraction'] = [
            'qa_pairs' => isset($qaResult['qa_pairs']) ? $qaResult['qa_pairs'] : [],
            'extraction_notes' => isset($qaResult['extraction_notes']) ? $qaResult['extraction_notes'] : '',
        ];
        return $merged;
    }

    private function qaPairsToNotes(array $qaResult)
    {
        $pairs = isset($qaResult['qa_pairs']) ? $qaResult['qa_pairs'] : [];
        $notes = [];
        foreach ($pairs as $pair) {
            if (!is_array($pair)) {
                continue;
            }
            $question = isset($pair['question']) ? trim($pair['question']) : '';
            if ($question === '') {
                continue;
            }
            $answer = '';
            if (!empty($pair['answer_text'])) {
                $answer = $pair['answer_text'];
            } elseif (!empty($pair['answer_summary'])) {
                $answer = $pair['answer_summary'];
            }
            if (!empty($pair['transcript_quote']) && $answer !== '') {
                $answer .= "\n[Quote: " . $pair['transcript_quote'] . ']';
            }
            $notes[] = [
                'question' => $question,
                'answer_summary' => $answer,
            ];
        }
        return $notes;
    }

    private function executePass($runId, $interviewId, $passName, $passOrder, $modelName, callable $callback)
    {
        $passId = $this->startPass($runId, $interviewId, $passName, $passOrder, $modelName);
        try {
            $result = $callback();
            if (is_string($result)) {
                $result = json_decode($result, true);
            }
            if (!is_array($result)) {
                throw new RuntimeException('Pass ' . $passName . ' returned invalid JSON');
            }
            $this->completePass($passId, $result);
            return $result;
        } catch (Exception $e) {
            $this->failPass($passId, $e->getMessage());
            throw $e;
        }
    }

    private function startRun($interviewId)
    {
        if (!$this->analysisRunsTableExists()) {
            return null;
        }
        return (int) db()->insert('ai_analysis_runs', [
            'interview_id' => $interviewId,
            'status' => 'processing',
            'model_name' => $this->openai->getAnalysisDeployment(),
            'prompt_version' => self::PROMPT_VERSION,
            'started_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function completeRun($runId)
    {
        if (!$runId || !$this->analysisRunsTableExists()) {
            return;
        }
        db()->update('ai_analysis_runs', [
            'status' => 'completed',
            'completed_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $runId]);
    }

    private function failRun($runId, $message)
    {
        if (!$runId || !$this->analysisRunsTableExists()) {
            return;
        }
        db()->update('ai_analysis_runs', [
            'status' => 'failed',
            'error_message' => $message,
            'completed_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $runId]);
    }

    private function startPass($runId, $interviewId, $passName, $passOrder, $modelName)
    {
        if (!$this->analysisPassesTableExists() || !$runId) {
            return null;
        }
        return (int) db()->insert('ai_analysis_passes', [
            'run_id' => $runId ?: 0,
            'interview_id' => $interviewId,
            'pass_name' => $passName,
            'pass_order' => $passOrder,
            'status' => 'processing',
            'model_name' => $modelName,
            'prompt_version' => self::PROMPT_VERSION,
            'started_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function completePass($passId, array $output)
    {
        if (!$passId || !$this->analysisPassesTableExists()) {
            return;
        }
        db()->update('ai_analysis_passes', [
            'status' => 'completed',
            'output_json' => json_encode($output),
            'completed_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $passId]);
    }

    private function failPass($passId, $message)
    {
        if (!$passId || !$this->analysisPassesTableExists()) {
            return;
        }
        db()->update('ai_analysis_passes', [
            'status' => 'failed',
            'error_message' => $message,
            'completed_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $passId]);
    }

    private function analysisRunsTableExists()
    {
        return $this->tableExists('ai_analysis_runs');
    }

    private function analysisPassesTableExists()
    {
        return $this->tableExists('ai_analysis_passes');
    }

    private function tableExists($tableName)
    {
        static $cache = [];
        if (isset($cache[$tableName])) {
            return $cache[$tableName];
        }
        try {
            $row = db()->fetch(
                'SELECT 1 AS found FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = ?',
                [$tableName]
            );
            $cache[$tableName] = !empty($row);
        } catch (Exception $e) {
            $cache[$tableName] = false;
        }
        return $cache[$tableName];
    }
}
