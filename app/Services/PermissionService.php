<?php



class PermissionService

{

    public static function role()

    {

        $user = auth_user();

        return $user ? $user['role'] : null;

    }



    public static function isOwner()

    {

        return self::role() === 'owner';

    }



    public static function isHrHead()

    {

        return self::role() === 'hr_head';

    }



    public static function isAdminRole()

    {

        return in_array(self::role(), ['owner', 'hr_head'], true);

    }



    public static function canManageTeam()

    {

        return self::isOwner();

    }



    public static function isJobCreator($jobId)
    {
        $userId = auth_id();
        if (!$userId) {
            return false;
        }
        $row = db()->fetch('SELECT created_by FROM jobs WHERE id = ?', [$jobId]);
        return $row && (int) $row['created_by'] === (int) $userId;
    }

    /** Edit job role, pipeline, templates, status (members: creator only). */
    public static function canManageJob($jobId)
    {
        if (self::isAdminRole()) {
            return true;
        }
        return self::isJobCreator($jobId);
    }

    public static function requireManageJob($jobId)
    {
        if (!self::canManageJob($jobId)) {
            http_response_code(403);
            die('You do not have permission to manage this job role.');
        }
    }

    /** View job, interviews, reports (all active portal members). */
    public static function canAccessJob($jobId)
    {
        if (self::isAdminRole()) {
            return true;
        }
        return auth_id() > 0;
    }

    /** View a specific interview report (portal access, job stakeholder, or magic-link session). */
    public static function canViewReport($jobId, $reportId)
    {
        if (self::isAdminRole()) {
            return true;
        }
        if (auth_id() > 0 && self::canAccessJob($jobId)) {
            return true;
        }
        if (ReportShareService::sessionCanViewReport($reportId)) {
            return true;
        }

        return false;
    }

    public static function requireReportAccess($jobId, $reportId)
    {
        if (!self::canViewReport($jobId, $reportId)) {
            http_response_code(403);
            die('Access denied.');
        }
    }

    /** Recording playback for portal users or recipients with an active report share grant. */
    public static function canViewInterviewMedia($interviewId)
    {
        if (self::isAdminRole()) {
            return true;
        }

        $row = db()->fetch(
            'SELECT a.job_id, ir.id AS report_id
             FROM interviews i
             JOIN applications a ON a.id = i.application_id
             LEFT JOIN interview_reports ir ON ir.interview_id = i.id
             WHERE i.id = ?',
            [$interviewId]
        );
        if (!$row) {
            return false;
        }
        if (auth_id() > 0 && self::canAccessJob($row['job_id'])) {
            return true;
        }
        if (!empty($row['report_id']) && ReportShareService::sessionCanViewReport($row['report_id'])) {
            return true;
        }

        return false;
    }

    public static function requireInterviewMediaAccess($interviewId)
    {
        if (!self::canViewInterviewMedia($interviewId)) {
            http_response_code(403);
            die('Access denied.');
        }
    }



    /** Edit interview report content (members: job creator only). */
    public static function canEditReport($jobId)
    {
        if (self::isAdminRole()) {
            return true;
        }
        return self::isJobCreator($jobId);
    }



    public static function canAssignPending()

    {

        return self::isAdminRole();

    }



    public static function canManageCandidates()
    {
        return self::isAdminRole();
    }

    public static function requireManageCandidates()
    {
        if (!self::canManageCandidates()) {
            http_response_code(403);
            die('Access denied.');
        }
    }

    public static function requireJobAccess($jobId)
    {
        if (!self::canAccessJob($jobId)) {
            http_response_code(403);
            die('Access denied.');
        }
    }

    public static function requireEditReport($jobId)

    {

        if (!self::canEditReport($jobId)) {

            http_response_code(403);

            die('You do not have permission to edit this report.');

        }

    }

    /** @return array{0:string,1:array} Optional list filters (scope=mine), not access control. */
    public static function jobListAccessClause($jobAlias = 'j')
    {
        return ['', []];
    }

    /** @return array{0:string,1:array} */
    public static function applicationListAccessClause($jobAlias = 'j')
    {
        return ['', []];
    }
}


