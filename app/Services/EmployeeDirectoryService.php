<?php

/**
 * Company employee search: portal users, Keka cache, Microsoft Entra ID (Graph).
 */
class EmployeeDirectoryService
{
    public function search($query = '', $limit = 60)
    {
        $cache = new EmployeeDirectoryCacheService();
        if ($cache->isAvailable()) {
            try {
                $cache->ensureFresh();
                return $cache->search($query, $limit);
            } catch (Exception $e) {
            }
        }

        return $this->searchLive($query, $limit);
    }

    /**
     * Full merged directory for weekly cache rebuild.
     *
     * @return array<int, array{value:string,label:string,email?:string}>
     */
    public function buildFullDirectory()
    {
        $items = [];
        $seen = [];

        foreach ($this->portalUsers('') as $row) {
            $this->appendDirectoryRow($items, $seen, $row);
        }
        foreach ($this->kekaEmployeesAll() as $row) {
            $this->appendDirectoryRow($items, $seen, $row);
        }
        foreach ($this->graphUsersAll() as $row) {
            $this->appendDirectoryRow($items, $seen, $row);
        }

        usort($items, function ($a, $b) {
            return strcasecmp($a['label'], $b['label']);
        });

        return $items;
    }

    private function appendDirectoryRow(array &$items, array &$seen, array $row)
    {
        $key = strtolower($row['email'] ?? '');
        if ($key && isset($seen[$key])) {
            return;
        }
        if ($key) {
            $seen[$key] = true;
        }
        $items[] = $row;
    }

    private function searchLive($query = '', $limit = 60)
    {
        $q = trim($query);
        $items = [];
        $seen = [];

        foreach ($this->portalUsers($q) as $row) {
            $this->appendDirectoryRow($items, $seen, $row);
        }

        foreach ($this->kekaEmployees($q, $limit) as $row) {
            $this->appendDirectoryRow($items, $seen, $row);
        }

        foreach ($this->graphUsers($q, $limit) as $row) {
            $this->appendDirectoryRow($items, $seen, $row);
        }

        return ['items' => array_slice($items, 0, $limit)];
    }

    /**
     * Attach a picker token to a job as hiring manager or interviewer.
     */
    public function attachToJob($jobId, $token, $role)
    {
        $token = trim($token);
        if ($token === '' || !in_array($role, ['hiring_manager', 'interviewer'], true)) {
            return;
        }

        if (strpos($token, 'user:') === 0) {
            $uid = (int) substr($token, 5);
            if ($uid > 0) {
                $this->insertJobUser($jobId, $uid, $role);
            }
            return;
        }

        if (strpos($token, 'keka:') === 0) {
            $kid = (int) substr($token, 5);
            $emp = db()->fetch('SELECT * FROM keka_employees WHERE id = ?', [$kid]);
            if ($emp) {
                $this->attachByEmail($jobId, $role, $emp['email'], trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? '')));
            }
            return;
        }

        if (strpos($token, 'graph:') === 0) {
            $gid = substr($token, 6);
            $person = $this->graphUserById($gid);
            if ($person) {
                $this->attachByEmail($jobId, $role, $person['email'], $person['name']);
            }
            return;
        }

        if (strpos($token, 'ext:') === 0) {
            $email = substr($token, 4);
            $this->attachByEmail($jobId, $role, $email, $email);
            return;
        }

        if (strpos($token, 'extn:') === 0) {
            $name = rawurldecode(substr($token, 5));
            $name = trim($name);
            if ($name === '') {
                return;
            }
            try {
                db()->insert('job_external_contacts', [
                    'job_id' => $jobId,
                    'contact_role' => $role,
                    'name' => $name,
                    'email' => null,
                ]);
            } catch (Exception $e) {
            }
        }
    }

    private function portalUsers($q)
    {
        $out = [];
        foreach (db()->fetchAll('SELECT id, first_name, last_name, email FROM users WHERE is_active = 1 ORDER BY first_name') as $u) {
            $label = trim($u['first_name'] . ' ' . $u['last_name']) . ' (' . $u['email'] . ') — Portal';
            if ($q !== '' && stripos($label, $q) === false) {
                continue;
            }
            $out[] = ['value' => 'user:' . $u['id'], 'label' => $label, 'email' => $u['email']];
        }
        return $out;
    }

    private function kekaEmployeesAll()
    {
        $out = [];
        try {
            foreach (db()->fetchAll(
                'SELECT id, first_name, last_name, email, job_title, department FROM keka_employees ORDER BY first_name, last_name'
            ) as $e) {
                $name = trim(($e['first_name'] ?? '') . ' ' . ($e['last_name'] ?? ''));
                $meta = trim(($e['job_title'] ?? '') . ($e['department'] ? ' · ' . $e['department'] : ''));
                $out[] = [
                    'value' => 'keka:' . $e['id'],
                    'label' => $name . ($e['email'] ? ' (' . $e['email'] . ')' : '') . ($meta ? ' — ' . $meta : ' — Keka'),
                    'email' => $e['email'],
                ];
            }
        } catch (Exception $e) {
        }
        return $out;
    }

    private function graphUsersAll()
    {
        $out = [];
        try {
            $graph = new MicrosoftGraphService('sync');
            if (!$graph->isConfigured()) {
                return $out;
            }
            foreach ($graph->listAllDirectoryUsers() as $u) {
                $email = $u['email'];
                if ($email === '') {
                    continue;
                }
                $meta = trim(($u['job_title'] ?? '') . ($u['department'] ? ' · ' . $u['department'] : ''));
                $out[] = [
                    'value' => 'graph:' . $u['id'],
                    'label' => $u['name'] . ' (' . $email . ')' . ($meta ? ' — ' . $meta : ' — Microsoft 365'),
                    'email' => $email,
                ];
            }
        } catch (Exception $e) {
        }
        return $out;
    }

    private function kekaEmployees($q, $limit)
    {
        $out = [];
        try {
            $params = [];
            $sql = 'SELECT TOP ' . (int) $limit . ' id, first_name, last_name, email, job_title, department FROM keka_employees WHERE 1=1';
            if ($q !== '') {
                $like = '%' . $q . '%';
                $sql .= ' AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR job_title LIKE ? OR department LIKE ?)';
                $params = [$like, $like, $like, $like, $like];
            }
            $sql .= ' ORDER BY first_name, last_name';
            foreach (db()->fetchAll($sql, $params) as $e) {
                $name = trim(($e['first_name'] ?? '') . ' ' . ($e['last_name'] ?? ''));
                $meta = trim(($e['job_title'] ?? '') . ($e['department'] ? ' · ' . $e['department'] : ''));
                $out[] = [
                    'value' => 'keka:' . $e['id'],
                    'label' => $name . ($e['email'] ? ' (' . $e['email'] . ')' : '') . ($meta ? ' — ' . $meta : ' — Keka'),
                    'email' => $e['email'],
                ];
            }
        } catch (Exception $e) {
        }
        return $out;
    }

    private function graphUsers($q, $limit)
    {
        $out = [];
        try {
            $graph = new MicrosoftGraphService('sync');
            if (!$graph->isConfigured()) {
                return $out;
            }
            foreach ($graph->searchDirectoryUsers($q, $limit) as $u) {
                $email = $u['email'];
                if ($email === '') {
                    continue;
                }
                $meta = trim(($u['job_title'] ?? '') . ($u['department'] ? ' · ' . $u['department'] : ''));
                $out[] = [
                    'value' => 'graph:' . $u['id'],
                    'label' => $u['name'] . ' (' . $email . ')' . ($meta ? ' — ' . $meta : ' — Microsoft 365'),
                    'email' => $email,
                ];
            }
        } catch (Exception $e) {
        }
        return $out;
    }

    public function graphUserById($id)
    {
        try {
            $graph = new MicrosoftGraphService('sync');
            if (!$graph->isConfigured()) {
                return null;
            }
            return $graph->getDirectoryUser($id);
        } catch (Exception $e) {
            return null;
        }
    }

    private function attachByEmail($jobId, $role, $email, $displayName)
    {
        $email = trim((string) $email);
        if ($email === '') {
            return;
        }
        $user = db()->fetch('SELECT id FROM users WHERE LOWER(email) = LOWER(?) AND is_active = 1', [$email]);
        if ($user) {
            $this->insertJobUser($jobId, (int) $user['id'], $role);
            return;
        }
        db()->insert('job_external_contacts', [
            'job_id' => $jobId,
            'contact_role' => $role,
            'name' => $displayName !== '' ? $displayName : $email,
            'email' => $email,
        ]);
    }

    private function insertJobUser($jobId, $userId, $role)
    {
        if ($role === 'hiring_manager') {
            db()->insert('job_hiring_managers', ['job_id' => $jobId, 'user_id' => $userId]);
        } else {
            db()->insert('job_interviewers', ['job_id' => $jobId, 'user_id' => $userId]);
        }
    }
}
