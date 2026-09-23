<?php



class ActivityLogService

{

    public static function log($action, $entityType, $entityId, $meta = [])

    {

        db()->insert('activity_logs', [

            'user_id' => auth_id(),

            'entity_type' => $entityType,

            'entity_id' => (string) $entityId,

            'action' => $action,

            'meta' => $meta ? json_encode($meta) : null,

        ]);

    }

}


