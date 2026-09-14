<?php

class KekaService
{
    private $config;
    private $tokenCache = ['token' => null, 'expires_at' => 0];

    public function __construct()
    {
        $this->config = config('services')['keka'];
        if (empty($this->config['api_base']) && !empty($this->config['tenant'])) {
            $this->config['api_base'] = 'https://' . $this->config['tenant'] . '.keka.com/api/v1';
        }
    }

    public function isConfigured()
    {
        return !empty($this->config['tenant'])
            && !empty($this->config['client_id'])
            && !empty($this->config['client_secret'])
            && !empty($this->config['api_key']);
    }

    public function getAccessToken()
    {
        if ($this->tokenCache['token'] && time() < $this->tokenCache['expires_at'] - 60) {
            return $this->tokenCache['token'];
        }

        $ch = curl_init('https://login.keka.com/connect/token');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'kekaapi',
                'scope' => 'kekaapi',
                'client_id' => $this->config['client_id'],
                'client_secret' => $this->config['client_secret'],
                'api_key' => $this->config['api_key'],
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);
        if ($code < 200 || $code >= 300 || empty($data['access_token'])) {
            throw new RuntimeException('Keka token failed: ' . $response);
        }

        $this->tokenCache['token'] = $data['access_token'];
        $this->tokenCache['expires_at'] = time() + (int) (isset($data['expires_in']) ? $data['expires_in'] : 3600);
        return $this->tokenCache['token'];
    }

    public function fetchEmployees($page = 1, $pageSize = 100)
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Keka is not configured');
        }

        $base = rtrim($this->config['api_base'], '/');
        $url = $base . '/hris/employees?page=' . (int) $page . '&page_size=' . (int) $pageSize;
        $token = $this->getAccessToken();

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Accept: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
        ]);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code < 200 || $code >= 300) {
            throw new RuntimeException('Keka employees API failed (HTTP ' . $code . '): ' . $response);
        }

        return json_decode($response, true);
    }

    public function syncEmployees()
    {
        if (!$this->isConfigured()) {
            return ['synced' => 0, 'message' => 'Keka not configured'];
        }

        $page = 1;
        $synced = 0;
        do {
            $payload = $this->fetchEmployees($page, 100);
            $items = isset($payload['data']) ? $payload['data'] : (isset($payload['employees']) ? $payload['employees'] : []);
            if (!is_array($items)) {
                $items = [];
            }
            foreach ($items as $emp) {
                $this->upsertEmployee($emp);
                $synced++;
            }
            $page++;
            $hasMore = count($items) >= 100;
        } while ($hasMore && $page <= 50);

        AppSettingsService::set('keka_last_sync_at', date('Y-m-d H:i:s'));
        return ['synced' => $synced, 'message' => 'OK'];
    }

    private function upsertEmployee($emp)
    {
        $kekaId = isset($emp['id']) ? (string) $emp['id'] : '';
        if ($kekaId === '') {
            return;
        }

        $personal = isset($emp['personal']) ? $emp['personal'] : [];
        $job = isset($emp['job']) ? $emp['job'] : [];
        $email = '';
        if (!empty($personal['email'])) {
            $email = $personal['email'];
        } elseif (!empty($emp['email'])) {
            $email = $emp['email'];
        }

        $existing = db()->fetch('SELECT id FROM keka_employees WHERE keka_id = ?', [$kekaId]);
        $row = [
            'employee_number' => isset($emp['employee_number']) ? $emp['employee_number'] : null,
            'email' => $email,
            'first_name' => isset($personal['first_name']) ? $personal['first_name'] : (isset($emp['first_name']) ? $emp['first_name'] : null),
            'last_name' => isset($personal['last_name']) ? $personal['last_name'] : (isset($emp['last_name']) ? $emp['last_name'] : null),
            'job_title' => isset($job['title']) ? $job['title'] : null,
            'department' => isset($job['department']) ? $job['department'] : null,
            'employment_status' => isset($job['employment_status']) ? $job['employment_status'] : null,
            'raw_json' => json_encode($emp),
            'synced_at' => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            db()->update('keka_employees', $row, 'id = :id', ['id' => $existing['id']]);
        } else {
            $row['keka_id'] = $kekaId;
            db()->insert('keka_employees', $row);
        }
    }
}
