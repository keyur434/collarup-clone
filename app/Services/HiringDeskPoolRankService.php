<?php

class HiringDeskPoolRankService
{
    const PROMPT_VERSION = 'pool-rank-v1';
    const BATCH_SIZE = 6;

    public static function generate($jobId, $userId = null)
    {
        PermissionService::requireJobAccess($jobId);
        $openai = new AzureOpenAIService();
        if (!$openai->isConfigured()) {
            throw new RuntimeException('Azure OpenAI is not configured.');
        }

        $pool = HiringDeskInsightService::loadPoolForInsight($jobId);
        $candidates = $pool['candidates'];
        if (count($candidates) < 3) {
            throw new RuntimeException('At least 3 candidates with reports are required for pool ranking.');
        }

        $batches = array_chunk($candidates, self::BATCH_SIZE);
        $allRankings = [];
        $lastNarrative = '';
        foreach ($batches as $batchIndex => $batch) {
            $compact = array_map(function ($c) {
                return $c['compact'];
            }, $batch);
            $result = $openai->analyzePoolCompareBatch([
                'job_title' => $pool['job']['title'],
                'must_have' => $pool['job']['must_have_criteria'],
                'key_skills' => $pool['job']['key_skills'],
                'batch_index' => $batchIndex + 1,
                'batch_total' => count($batches),
                'candidates' => $compact,
            ]);
            if (!empty($result['ranking']) && is_array($result['ranking'])) {
                foreach ($result['ranking'] as $item) {
                    $allRankings[] = $item;
                }
            }
            if (!empty($result['pool_narrative'])) {
                $lastNarrative = $result['pool_narrative'];
            }
        }

        $merged = self::mergeRankings($allRankings);
        $payload = [
            'prompt_version' => self::PROMPT_VERSION,
            'generated_at' => date('c'),
            'ranking' => $merged,
            'narrative' => $lastNarrative,
        ];

        self::storePoolCompare($jobId, $payload, $userId, $openai->getAnalysisDeployment());
        return $payload;
    }

    /**
     * @return array|null
     */
    public static function getStored($jobId)
    {
        try {
            $row = db()->fetch('SELECT pool_compare_json, updated_at FROM job_hiring_insights WHERE job_id = ?', [$jobId]);
            if (!$row || empty($row['pool_compare_json'])) {
                return null;
            }
            $data = json_decode($row['pool_compare_json'], true);
            return is_array($data) ? $data : null;
        } catch (Exception $e) {
            return null;
        }
    }

    private static function mergeRankings(array $items)
    {
        $byApp = [];
        foreach ($items as $item) {
            $id = $item['application_id'] ?? '';
            if ($id === '') {
                continue;
            }
            $rank = (int) ($item['rank_in_batch'] ?? 99);
            if (!isset($byApp[$id]) || $rank < $byApp[$id]['rank_in_batch']) {
                $byApp[$id] = $item;
            }
        }
        usort($byApp, function ($a, $b) {
            return ($a['rank_in_batch'] ?? 99) <=> ($b['rank_in_batch'] ?? 99);
        });
        $pos = 1;
        foreach ($byApp as &$row) {
            $row['pool_rank'] = $pos++;
        }
        unset($row);
        return array_values($byApp);
    }

    private static function storePoolCompare($jobId, array $payload, $userId, $model)
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $existing = db()->fetch('SELECT job_id FROM job_hiring_insights WHERE job_id = ?', [$jobId]);
        $row = [
            'pool_compare_json' => $json,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($existing) {
            db()->update('job_hiring_insights', $row, 'job_id = :jid', ['jid' => $jobId]);
        } else {
            db()->insert('job_hiring_insights', array_merge($row, [
                'job_id' => $jobId,
                'input_hash' => HiringDeskInsightService::computeInputHash($jobId),
                'prompt_version' => HiringDeskInsightService::PROMPT_VERSION,
                'status' => 'pending',
                'generated_by' => $userId,
                'created_at' => date('Y-m-d H:i:s'),
            ]));
        }
    }
}
