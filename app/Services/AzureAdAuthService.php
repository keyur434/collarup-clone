<?php



class AzureAdAuthService

{

    private $tenantId;

    private $clientId;

    private $clientSecret;

    private $redirectUri;



    public function __construct()

    {

        $config = config('services')['azure_ad_login'];

        $this->tenantId = $config['tenant_id'];

        $this->clientId = $config['client_id'];

        $this->clientSecret = $config['client_secret'];

        $this->redirectUri = $config['redirect_uri'] ?: url('login/callback');

    }



    public function isConfigured()

    {

        return !empty($this->tenantId) && !empty($this->clientId) && !empty($this->clientSecret);

    }



    public function getAuthorizationUrl($state)

    {

        $params = http_build_query([

            'client_id' => $this->clientId,

            'response_type' => 'code',

            'redirect_uri' => $this->redirectUri,

            'response_mode' => 'query',

            'scope' => 'openid profile email offline_access User.Read',

            'state' => $state,

        ]);

        return 'https://login.microsoftonline.com/' . $this->tenantId . '/oauth2/v2.0/authorize?' . $params;

    }



    public function exchangeCode($code)

    {

        $url = 'https://login.microsoftonline.com/' . $this->tenantId . '/oauth2/v2.0/token';

        $body = http_build_query([

            'client_id' => $this->clientId,

            'client_secret' => $this->clientSecret,

            'code' => $code,

            'redirect_uri' => $this->redirectUri,

            'grant_type' => 'authorization_code',

        ]);



        $ch = curl_init($url);

        curl_setopt_array($ch, [

            CURLOPT_POST => true,

            CURLOPT_POSTFIELDS => $body,

            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],

        ]);

        $response = curl_exec($ch);

        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);



        $data = json_decode($response, true);

        if ($code >= 400 || empty($data['access_token'])) {

            throw new RuntimeException('Azure AD token exchange failed.');

        }



        return $data;

    }



    public function getUserProfile($accessToken)

    {

        $ch = curl_init('https://graph.microsoft.com/v1.0/me');

        curl_setopt_array($ch, [

            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],

        ]);

        $response = curl_exec($ch);

        curl_close($ch);

        $profile = json_decode($response, true);

        if (empty($profile['id'])) {

            throw new RuntimeException('Could not load Azure AD profile.');

        }

        return $profile;

    }



    public function findProvisionedUser($profile)

    {

        $email = isset($profile['mail']) ? $profile['mail'] : (isset($profile['userPrincipalName']) ? $profile['userPrincipalName'] : '');

        $oid = $profile['id'];



        $user = db()->fetch(

            'SELECT TOP 1 * FROM users WHERE (azure_oid = ? OR email = ?) AND is_active = 1',

            [$oid, $email]

        );



        if ($user && empty($user['azure_oid'])) {

            db()->update('users', ['azure_oid' => $oid], 'id = :id', ['id' => $user['id']]);

            $user['azure_oid'] = $oid;

        }



        return $user;

    }

}


