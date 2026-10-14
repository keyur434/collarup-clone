<?php

class DashboardController
{
    public function index()
    {
        $successPeriod = $this->successRatePeriod();
        $successRate = $this->buildSuccessRateChart($successPeriod);

        $stats = [
            'total'        => db()->fetch('SELECT COUNT(*) as c FROM applications')['c'],
            'scheduled'    => db()->fetch("SELECT COUNT(*) as c FROM interviews WHERE status = 'scheduled'")['c'],
            'completed'    => db()->fetch("SELECT COUNT(*) as c FROM interviews WHERE status = 'completed'")['c'],
            'selected'     => db()->fetch("SELECT COUNT(*) as c FROM applications WHERE decision = 'advance'")['c'],
            'rejected'     => db()->fetch("SELECT COUNT(*) as c FROM applications WHERE decision = 'reject'")['c'],
            'iv_pending'   => db()->fetch("SELECT COUNT(*) as c FROM interviews WHERE status = 'pending'")['c'],
            'iv_scheduled' => db()->fetch("SELECT COUNT(*) as c FROM interviews WHERE status = 'scheduled'")['c'],
            'iv_completed' => db()->fetch("SELECT COUNT(*) as c FROM interviews WHERE status = 'completed'")['c'],
            'iv_processing' => db()->fetch("SELECT COUNT(*) as c FROM interviews WHERE status = 'processing'")['c'],
            'pending_recordings' => db()->fetch("SELECT COUNT(*) as c FROM pending_recordings WHERE status = 'pending_assignment'")['c'],
        ];

        $pending = db()->fetchAll(
            "SELECT TOP 5 i.id, i.scheduled_date, a.job_id, c.first_name, c.last_name,
                    j.title as job_title, d.name as department_name
             FROM interviews i
             JOIN applications a ON a.id = i.application_id
             JOIN candidates c ON c.id = a.candidate_id
             JOIN jobs j ON j.id = a.job_id
             LEFT JOIN departments d ON d.id = j.department_id
             WHERE i.status = 'pending' ORDER BY i.created_at DESC"
        );

        $scheduled = db()->fetchAll(
            "SELECT TOP 5 i.id, i.scheduled_date, i.scheduled_start, i.scheduled_end, a.job_id,
                    c.first_name, c.last_name, j.title as job_title, d.name as department_name
             FROM interviews i
             JOIN applications a ON a.id = i.application_id
             JOIN candidates c ON c.id = a.candidate_id
             JOIN jobs j ON j.id = a.job_id
             LEFT JOIN departments d ON d.id = j.department_id
             WHERE i.status = 'scheduled' ORDER BY i.scheduled_date ASC"
        );

        $completed = db()->fetchAll(
            "SELECT TOP 5 i.id, ir.id as report_id, a.job_id,
                    c.first_name, c.last_name, j.title as job_title,
                    i.completed_at, i.scheduled_date, i.scheduled_start, i.scheduled_end,
                    d.name as department_name
             FROM interviews i
             JOIN applications a ON a.id = i.application_id
             JOIN candidates c ON c.id = a.candidate_id
             JOIN jobs j ON j.id = a.job_id
             LEFT JOIN departments d ON d.id = j.department_id
             LEFT JOIN interview_reports ir ON ir.interview_id = i.id
             WHERE i.status = 'completed' ORDER BY i.completed_at DESC"
        );

        render('dashboard.index', [
            'title' => 'Dashboard',
            'stats' => $stats,
            'successRate' => $successRate,
            'successPeriod' => $successPeriod,
            'pending' => $pending,
            'scheduled' => $scheduled,
            'completed' => $completed,
            'activeNav' => 'dashboard',
        ]);
    }

    private function successRatePeriod()
    {
        $p = isset($_GET['success_period']) ? (string) $_GET['success_period'] : 'month';
        return in_array($p, ['month', 'week', 'year'], true) ? $p : 'month';
    }

    private function buildSuccessRateChart($period)
    {
        $tz = new DateTimeZone('UTC');
        $now = new DateTimeImmutable('now', $tz);

        if ($period === 'week') {
            $start = $now->modify('-6 days')->setTime(0, 0, 0);
            $end = $now->setTime(23, 59, 59);
        } elseif ($period === 'year') {
            $startYear = (int) $now->format('Y') - 6;
            $start = (new DateTimeImmutable($startYear . '-01-01 00:00:00', $tz));
            $end = $now->setTime(23, 59, 59);
        } else {
            $start = $now->modify('first day of -6 months')->setTime(0, 0, 0);
            $end = $now->setTime(23, 59, 59);
        }

        $bucketDefs = $this->successRateBucketDefs($period, $start, $now);
        $counts = [];
        foreach ($bucketDefs as $def) {
            $counts[$def['key']] = ['passed' => 0, 'rejected' => 0];
        }

        $rows = db()->fetchAll(
            "SELECT decision, COALESCE(updated_at, created_at) AS decided_at
             FROM applications
             WHERE is_archived = 0
               AND decision IN ('advance', 'offer', 'reject')
               AND COALESCE(updated_at, created_at) >= ?
               AND COALESCE(updated_at, created_at) <= ?",
            [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]
        );

        foreach ($rows as $row) {
            $dt = new DateTimeImmutable($row['decided_at'], $tz);
            $key = $this->successRateBucketKey($dt, $period);
            if (!isset($counts[$key])) {
                continue;
            }
            if ($row['decision'] === 'reject') {
                $counts[$key]['rejected']++;
            } else {
                $counts[$key]['passed']++;
            }
        }

        $points = [];
        foreach ($bucketDefs as $def) {
            $c = $counts[$def['key']];
            $points[] = [
                'label' => $def['label'],
                'passed' => $c['passed'],
                'rejected' => $c['rejected'],
            ];
        }

        return [
            'points' => $points,
            'paths' => dashboard_success_rate_chart_paths($points),
        ];
    }

    private function successRateBucketDefs($period, DateTimeImmutable $start, DateTimeImmutable $now)
    {
        $defs = [];
        if ($period === 'week') {
            for ($i = 0; $i < 7; $i++) {
                $d = $start->modify('+' . $i . ' days');
                $defs[] = ['key' => $d->format('Y-m-d'), 'label' => $d->format('d')];
            }
            return $defs;
        }
        if ($period === 'year') {
            $y0 = (int) $start->format('Y');
            for ($i = 0; $i < 7; $i++) {
                $y = $y0 + $i;
                $defs[] = ['key' => (string) $y, 'label' => (string) $y];
            }
            return $defs;
        }
        $cursor = $start;
        for ($i = 0; $i < 7; $i++) {
            $defs[] = ['key' => $cursor->format('Y-m'), 'label' => $cursor->format('M')];
            $cursor = $cursor->modify('+1 month');
        }
        return $defs;
    }

    private function successRateBucketKey(DateTimeImmutable $dt, $period)
    {
        if ($period === 'week') {
            return $dt->format('Y-m-d');
        }
        if ($period === 'year') {
            return $dt->format('Y');
        }
        return $dt->format('Y-m');
    }
}
