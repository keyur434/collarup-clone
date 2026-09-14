<?php

/**
 * Coordinates multi-pass interview analysis and audit logging.
 * Phase 1: Q&A extraction (no judgment) + rubric scoring.
 */
class InterviewAnalysisOrchestrator
{
    const PROMPT_VERSION = 'v2-multipass';
    const PASS_QA = 'qa_extraction';
    const PASS_SCORING = 'scoring';

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
            $merged['_pipeline'] = [
                'mode' => 'multipass',
                'prompt_version' => self::PROMPT_VERSION,
                'run_id' => $runId,
                'passes' => [self::PASS_QA, self::PASS_SCORING],
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
