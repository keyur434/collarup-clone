<?php

class DashboardController
{
    public function index()
    {
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
            'pending' => $pending,
            'scheduled' => $scheduled,
            'completed' => $completed,
            'activeNav' => 'dashboard',
        ]);
    }
}
