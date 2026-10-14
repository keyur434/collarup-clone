<?php



class MicrosoftGraphService

{

    private $tenantId;

    private $clientId;

    private $clientSecret;

    private $tokenCache = null;

    private $tokenExpiresAt = 0;



    public function __construct($app = 'sync')

    {

        $config = $app === 'login'

            ? config('services')['azure_ad_login']

            : config('services')['azure_ad_sync'];



        $this->tenantId = $config['tenant_id'];

        $this->clientId = $config['client_id'];

        $this->clientSecret = $config['client_secret'];

    }



    public function isConfigured()

    {

        return !empty($this->tenantId) && !empty($this->clientId) && !empty($this->clientSecret);

    }



    public function getAccessToken()

    {

        if ($this->tokenCache && time() < ($this->tokenExpiresAt - 60)) {

            return $this->tokenCache;

        }



        $url = 'https://login.microsoftonline.com/' . $this->tenantId . '/oauth2/v2.0/token';

        $body = http_build_query([

            'client_id' => $this->clientId,

            'client_secret' => $this->clientSecret,

            'scope' => 'https://graph.microsoft.com/.default',

            'grant_type' => 'client_credentials',

        ]);



        $response = $this->rawRequest('POST', $url, $body, null, false);

        if (empty($response['access_token'])) {

            throw new RuntimeException('Graph token request failed: ' . json_encode($response));

        }



        $this->tokenCache = $response['access_token'];

        $this->tokenExpiresAt = time() + (int) (isset($response['expires_in']) ? $response['expires_in'] : 3600);

        return $this->tokenCache;

    }



    public function request($method, $path, $body = null, $headers = [])

    {

        $token = $this->getAccessToken();

        $url = strpos($path, 'https://') === 0 ? $path : 'https://graph.microsoft.com/v1.0' . $path;

        $isJson = is_array($body);
        return $this->rawRequest($method, $url, $body, $token, $isJson, $headers);

    }



    public function uploadBinary($path, $content, $contentType = 'application/octet-stream')

    {

        $token = $this->getAccessToken();

        $url = 'https://graph.microsoft.com/v1.0' . $path;

        return $this->rawRequest('PUT', $url, $content, $token, false, ['Content-Type: ' . $contentType]);

    }



    /**
     * Download binary from a Graph API path (e.g. .../recordings/{id}/content). Follows redirects.
     */
    public function downloadContentPath($path, $destPath)
    {
        $url = strpos($path, 'https://') === 0 ? $path : 'https://graph.microsoft.com/v1.0' . $path;
        return $this->download($url, $destPath);
    }

    public function download($url, $destPath)

    {

        $token = $this->getAccessToken();

        $dir = dirname($destPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $fp = fopen($destPath, 'wb');
        if ($fp === false) {
            throw new RuntimeException('Could not open destination for download.');
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_FILE => $fp,
            CURLOPT_TIMEOUT => 7200,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        ]);

        $ok = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        fclose($fp);
        curl_close($ch);

        if ($ok === false || $code < 200 || $code >= 300) {
            if (is_file($destPath)) {
                @unlink($destPath);
            }
            $detail = $err ?: 'HTTP ' . $code;
            throw new RuntimeException('Graph download failed: ' . $detail);
        }



        return $destPath;

    }



    private function rawRequest($method, $url, $body, $token, $json = true, $extraHeaders = [])

    {

        $headers = $extraHeaders;

        if ($token) {

            $headers[] = 'Authorization: Bearer ' . $token;

        }

        if ($json && is_array($body)) {

            $body = json_encode($body);

            $headers[] = 'Content-Type: application/json';

        } elseif (!$json && is_string($body)) {

            $hasContentType = false;

            foreach ($headers as $h) {

                if (stripos($h, 'Content-Type:') === 0) {

                    $hasContentType = true;

                    break;

                }

            }

            if (!$hasContentType) {

                $headers[] = 'Content-Type: application/x-www-form-urlencoded';

            }

        }



        $ch = curl_init($url);

        curl_setopt_array($ch, [

            CURLOPT_CUSTOMREQUEST => $method,

            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_TIMEOUT => 120,

            CURLOPT_HTTPHEADER => $headers,

        ]);

        if ($body !== null) {

            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

        }



        $response = curl_exec($ch);

        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $err = curl_error($ch);

        curl_close($ch);



        if ($response === false) {

            throw new RuntimeException('Graph request failed: ' . $err);

        }



        $decoded = json_decode($response, true);

        if ($code >= 400) {

            $msg = isset($decoded['error']['message']) ? $decoded['error']['message'] : $response;

            throw new RuntimeException('Graph HTTP ' . $code . ': ' . $msg);

        }



        return $decoded !== null ? $decoded : $response;

    }

    /**
     * Search enabled Entra ID users (requires application permission User.Read.All).
     *
     * @return array<int, array{id:string,name:string,email:string,job_title?:string,department?:string}>
     */
    public function searchDirectoryUsers($query = '', $limit = 50)
    {
        $limit = max(1, min(100, (int) $limit));
        $select = 'id,displayName,givenName,surname,mail,userPrincipalName,jobTitle,department,accountEnabled';
        $query = trim($query);
        $fetchTop = min(999, max($limit * 25, 50));

        if ($query === '') {
            $base = '/users?$top=' . $fetchTop . '&$select=' . $select;
            try {
                $data = $this->request('GET', $base . '&$filter=' . rawurlencode('accountEnabled eq true'));
            } catch (RuntimeException $e) {
                if (strpos($e->getMessage(), '400') === false) {
                    throw $e;
                }
                $data = $this->request('GET', $base);
            }
        } else {
            $term = str_replace(['"', '\\'], '', $query);
            $path = '/users?$search=' . rawurlencode('"displayName:' . $term . '" OR "mail:' . $term . '"')
                . '&$top=' . $fetchTop
                . '&$select=' . $select;
            $data = $this->request('GET', $path, null, ['ConsistencyLevel: eventual']);
        }

        $out = $this->mapDirectoryUserRows(isset($data['value']) ? $data['value'] : []);
        usort($out, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        return array_slice($out, 0, $limit);
    }

    /**
     * Paginated full directory sync (for weekly employee cache).
     *
     * @return array<int, array{id:string,name:string,email:string,job_title?:string,department?:string}>
     */
    public function listAllDirectoryUsers($max = 15000)
    {
        $max = max(1, min(50000, (int) $max));
        $select = 'id,displayName,givenName,surname,mail,userPrincipalName,jobTitle,department,accountEnabled';
        $path = '/users?$top=999&$select=' . $select . '&$filter=' . rawurlencode('accountEnabled eq true');
        $filterFallback = false;

        $all = [];
        while ($path !== null && count($all) < $max) {
            try {
                $data = $this->request('GET', $path);
            } catch (RuntimeException $e) {
                if (!$filterFallback && strpos($e->getMessage(), '400') !== false && strpos($path, '$filter=') !== false) {
                    $filterFallback = true;
                    $path = '/users?$top=999&$select=' . $select;
                    continue;
                }
                throw $e;
            }
            $all = array_merge($all, $this->mapDirectoryUserRows(isset($data['value']) ? $data['value'] : []));
            $path = null;
            if (!empty($data['@odata.nextLink']) && count($all) < $max) {
                $path = $data['@odata.nextLink'];
            }
        }

        usort($all, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        return array_slice($all, 0, $max);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array{id:string,name:string,email:string,job_title?:string,department?:string}>
     */
    private function mapDirectoryUserRows(array $rows)
    {
        $out = [];
        foreach ($rows as $row) {
            if (array_key_exists('accountEnabled', $row) && $row['accountEnabled'] === false) {
                continue;
            }
            $email = !empty($row['mail']) ? $row['mail'] : (isset($row['userPrincipalName']) ? $row['userPrincipalName'] : '');
            $name = !empty($row['displayName']) ? $row['displayName'] : trim(($row['givenName'] ?? '') . ' ' . ($row['surname'] ?? ''));
            if ($email === '' && $name === '') {
                continue;
            }
            if ($name === '' && $email !== '') {
                $name = $email;
            }
            $out[] = [
                'id' => $row['id'],
                'name' => $name,
                'email' => strtolower($email),
                'job_title' => isset($row['jobTitle']) ? $row['jobTitle'] : '',
                'department' => isset($row['department']) ? $row['department'] : '',
            ];
        }
        return $out;
    }

    public function getDirectoryUser($userId)
    {
        $userId = trim($userId);
        if ($userId === '') {
            return null;
        }
        $select = 'id,displayName,givenName,surname,mail,userPrincipalName,jobTitle,department';
        $data = $this->request('GET', '/users/' . rawurlencode($userId) . '?$select=' . rawurlencode($select));
        $email = !empty($data['mail']) ? $data['mail'] : (isset($data['userPrincipalName']) ? $data['userPrincipalName'] : '');
        $name = !empty($data['displayName']) ? $data['displayName'] : trim(($data['givenName'] ?? '') . ' ' . ($data['surname'] ?? ''));
        return [
            'id' => $data['id'],
            'name' => $name,
            'email' => $email,
            'job_title' => isset($data['jobTitle']) ? $data['jobTitle'] : '',
            'department' => isset($data['department']) ? $data['department'] : '',
        ];
    }

}


