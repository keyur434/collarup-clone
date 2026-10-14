<?php

class HiringDeskConfigService
{
    public static function defaultWeights()
    {
        return [
            'overall' => 25,
            'experience' => 25,
            'culture' => 25,
            'soft_skills' => 25,
            'criteria' => [],
        ];
    }

    public static function defaultMinimums()
    {
        return [
            'overall' => null,
            'experience' => null,
            'culture' => null,
            'soft_skills' => null,
        ];
    }

    public static function get($jobId)
    {
        try {
            $row = db()->fetch('SELECT * FROM job_hiring_desk_config WHERE job_id = ?', [$jobId]);
        } catch (Exception $e) {
            $row = null;
        }
        $weights = self::defaultWeights();
        $minimums = self::defaultMinimums();
        if ($row && !empty($row['weights_json'])) {
            $decoded = json_decode($row['weights_json'], true);
            if (is_array($decoded)) {
                $weights = array_merge($weights, $decoded);
            }
        }
        if ($row && !empty($row['minimums_json'])) {
            $decoded = json_decode($row['minimums_json'], true);
            if (is_array($decoded)) {
                $minimums = array_merge($minimums, $decoded);
            }
        }
        return ['weights' => $weights, 'minimums' => $minimums];
    }

    public static function save($jobId, array $weights, array $minimums)
    {
        PermissionService::requireJobAccess($jobId);
        $weightsJson = json_encode($weights, JSON_UNESCAPED_UNICODE);
        $minimumsJson = json_encode($minimums, JSON_UNESCAPED_UNICODE);
        $existing = db()->fetch('SELECT job_id FROM job_hiring_desk_config WHERE job_id = ?', [$jobId]);
        if ($existing) {
            db()->update('job_hiring_desk_config', [
                'weights_json' => $weightsJson,
                'minimums_json' => $minimumsJson,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'job_id = :jid', ['jid' => $jobId]);
        } else {
            db()->insert('job_hiring_desk_config', [
                'job_id' => $jobId,
                'weights_json' => $weightsJson,
                'minimums_json' => $minimumsJson,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * @return array{composite:?float,passes_bar:bool,bar_failures:array}
     */
    public static function assistScore(array $applicantRow, array $config, array $criterionNames = [])
    {
        $weights = $config['weights'];
        $minimums = $config['minimums'];
        $barFailures = [];

        foreach (['overall', 'experience', 'culture', 'soft_skills'] as $key) {
            $min = isset($minimums[$key]) && $minimums[$key] !== '' && $minimums[$key] !== null
                ? (float) $minimums[$key] : null;
            if ($min === null) {
                continue;
            }
            $field = $key === 'overall' ? 'overall_score' : $key . '_score';
            $val = isset($applicantRow[$field]) ? $applicantRow[$field] : null;
            if ($val === null || $val === '' || (float) $val < $min) {
                $barFailures[] = $key;
            }
        }

        $totalWeight = 0;
        $weighted = 0;

        $map = [
            'overall' => 'overall_score',
            'experience' => 'experience_score',
            'culture' => 'culture_score',
            'soft_skills' => 'soft_skills_score',
        ];
        foreach ($map as $wKey => $field) {
            $w = (float) ($weights[$wKey] ?? 0);
            if ($w <= 0) {
                continue;
            }
            $val = isset($applicantRow[$field]) ? $applicantRow[$field] : null;
            if ($val === null || $val === '') {
                continue;
            }
            $totalWeight += $w;
            $weighted += $w * (float) $val;
        }

        $criteriaWeights = isset($weights['criteria']) && is_array($weights['criteria']) ? $weights['criteria'] : [];
        $scoresByName = [];
        foreach ($applicantRow['criterion_scores'] ?? [] as $cs) {
            $scoresByName[strtolower(trim($cs['criterion_name']))] = (float) $cs['score'];
        }
        foreach ($criteriaWeights as $name => $w) {
            $w = (float) $w;
            if ($w <= 0) {
                continue;
            }
            $key = strtolower(trim($name));
            if (!isset($scoresByName[$key])) {
                continue;
            }
            $totalWeight += $w;
            $weighted += $w * $scoresByName[$key];
        }

        $composite = $totalWeight > 0 ? round($weighted / $totalWeight, 2) : null;

        return [
            'composite' => $composite,
            'passes_bar' => count($barFailures) === 0,
            'bar_failures' => $barFailures,
        ];
    }
}
