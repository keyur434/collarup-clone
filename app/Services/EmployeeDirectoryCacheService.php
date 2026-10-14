<?php

/**
 * Cached company directory for HM / interviewer pickers (weekly refresh).
 */
class EmployeeDirectoryCacheService
{
    const SETTING_REFRESHED_AT = 'employee_directory_refreshed_at';

    public function isAvailable()
    {
        try {
            db()->fetch('SELECT TOP 1 picker_value FROM employee_directory_cache');
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function ttlSeconds()
    {
        $days = (int) (config('app')['employee_directory_cache_ttl_days'] ?? 7);
        if ($days < 1) {
            $days = 7;
        }
        return $days * 86400;
    }

    public function isStale()
    {
        $refreshed = AppSettingsService::get(self::SETTING_REFRESHED_AT, '');
        if ($refreshed === '') {
            return true;
        }
        $ts = strtotime($refreshed);
        if ($ts === false) {
            return true;
        }
        return (time() - $ts) > $this->ttlSeconds();
    }

    public function ensureFresh()
    {
        if (!$this->isStale()) {
            return;
        }
        $this->refresh();
    }

    public function refresh()
    {
        $dir = new EmployeeDirectoryService();
        $items = $dir->buildFullDirectory();
        $now = date('Y-m-d H:i:s');

        db()->query('DELETE FROM employee_directory_cache');
        foreach ($items as $row) {
            $label = (string) ($row['label'] ?? '');
            $value = (string) ($row['value'] ?? '');
            if ($value === '' || $label === '') {
                continue;
            }
            $email = isset($row['email']) ? (string) $row['email'] : '';
            $source = 'mixed';
            if (strpos($value, 'user:') === 0) {
                $source = 'portal';
            } elseif (strpos($value, 'keka:') === 0) {
                $source = 'keka';
            } elseif (strpos($value, 'graph:') === 0) {
                $source = 'graph';
            }
            db()->insert('employee_directory_cache', [
                'picker_value' => $value,
                'label' => $label,
                'email' => $email !== '' ? $email : null,
                'search_text' => mb_strtolower($label . ' ' . $email),
                'source' => $source,
                'refreshed_at' => $now,
            ]);
        }

        AppSettingsService::set(self::SETTING_REFRESHED_AT, $now);
    }

    public function search($query, $limit)
    {
        $q = trim($query);
        $limit = max(1, min(10000, (int) $limit));
        $params = [];
        $sql = 'SELECT picker_value AS value, label, email FROM employee_directory_cache WHERE 1=1';
        if ($q !== '') {
            $sql .= ' AND search_text LIKE ?';
            $params[] = '%' . mb_strtolower($q) . '%';
        }
        $sql .= ' ORDER BY label OFFSET 0 ROWS FETCH NEXT ' . (int) $limit . ' ROWS ONLY';

        $rows = db()->fetchAll($sql, $params);
        return ['items' => $rows];
    }
}
