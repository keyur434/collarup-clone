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



    public function download($url, $destPath)

    {

        $token = $this->getAccessToken();

        $ch = curl_init($url);

        curl_setopt_array($ch, [

            CURLOPT_FOLLOWLOCATION => true,

            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_TIMEOUT => 1800,

            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],

        ]);

        $data = curl_exec($ch);

        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $err = curl_error($ch);

        curl_close($ch);



        if ($data === false || $code < 200 || $code >= 300) {

            throw new RuntimeException('Graph download failed: ' . ($err ?: 'HTTP ' . $code));

        }



        if (file_put_contents($destPath, $data) === false) {

            throw new RuntimeException('Could not write downloaded file.');

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

}


