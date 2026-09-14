<?php



class AuthController

{

    public function showLogin()

    {

        if (auth_check()) {

            redirect(url('dashboard'));

        }

        $azure = new AzureAdAuthService();
        $allowLocal = config('app')['allow_local_login'];
        render('auth.login', [
            'title' => 'Login',
            'azureSsoEnabled' => $azure->isConfigured(),
            'allowLocalLogin' => $allowLocal || !$azure->isConfigured(),
        ]);

    }



    public function login()

    {

        verify_csrf();

        $azure = new AzureAdAuthService();

        if ($azure->isConfigured() && !config('app')['allow_local_login']) {

            flash('error', 'Please sign in with Microsoft.');

            redirect(url('login'));

        }

        $email = trim(isset($_POST['email']) ? $_POST['email'] : '');

        $password = isset($_POST['password']) ? $_POST['password'] : '';



        $user = db()->fetch('SELECT * FROM users WHERE email = ? AND is_active = 1', [$email]);

        if (!$user || empty($user['password_hash']) || !password_verify($password, $user['password_hash'])) {

            flash('error', 'Invalid email or password.');

            $_SESSION['old_input'] = ['email' => $email];

            redirect(url('login'));

        }



        $this->startSession($user);

        redirect(url('dashboard'));

    }



    public function azureRedirect()

    {

        $azure = new AzureAdAuthService();

        if (!$azure->isConfigured()) {

            flash('error', 'Microsoft sign-in is not configured.');

            redirect(url('login'));

        }

        $state = bin2hex(random_bytes(16));

        $_SESSION['oauth_state'] = $state;

        redirect($azure->getAuthorizationUrl($state));

    }



    public function azureCallback()

    {

        $azure = new AzureAdAuthService();

        $state = $_GET['state'] ?? '';

        if (empty($_SESSION['oauth_state']) || !hash_equals($_SESSION['oauth_state'], $state)) {

            flash('error', 'Invalid sign-in state. Please try again.');

            redirect(url('login'));

        }

        unset($_SESSION['oauth_state']);



        $code = $_GET['code'] ?? '';

        if (!$code) {

            flash('error', 'Microsoft sign-in was cancelled.');

            redirect(url('login'));

        }



        try {

            $token = $azure->exchangeCode($code);

            $profile = $azure->getUserProfile($token['access_token']);

            $user = $azure->findProvisionedUser($profile);

        } catch (Exception $e) {

            flash('error', 'Microsoft sign-in failed. Contact your administrator.');

            redirect(url('login'));

        }



        if (!$user) {

            flash('error', 'Your account is not provisioned. Ask an administrator to add you in Team Management.');

            redirect(url('login'));

        }



        $this->startSession($user);

        redirect(url('dashboard'));

    }



    public function logout()

    {

        session_destroy();

        redirect(url('login'));

    }



    private function startSession($user)

    {

        db()->update('users', ['last_login_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $user['id']]);

        unset($user['password_hash']);

        $_SESSION['user'] = $user;

    }

}


