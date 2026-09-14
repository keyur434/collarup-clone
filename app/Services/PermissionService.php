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



    public static function canAccessJob($jobId)

    {

        if (self::isAdminRole()) {

            return true;

        }

        $userId = auth_id();

        if (!$userId) {

            return false;

        }

        $row = db()->fetch(

            'SELECT TOP 1 1 as ok FROM job_hiring_managers WHERE job_id = ? AND user_id = ?

             UNION

             SELECT TOP 1 1 as ok FROM job_interviewers WHERE job_id = ? AND user_id = ?',

            [$jobId, $userId, $jobId, $userId]

        );

        return !empty($row);

    }



    public static function canEditReport($jobId)

    {

        if (self::isAdminRole()) {

            return true;

        }

        $userId = auth_id();

        if (!$userId) {

            return false;

        }

        $row = db()->fetch(

            'SELECT TOP 1 1 as ok FROM job_hiring_managers WHERE job_id = ? AND user_id = ?',

            [$jobId, $userId]

        );

        return !empty($row);

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

}


