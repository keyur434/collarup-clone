<?php

function env_load($path)
{
    if (!file_exists($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (!array_key_exists($name, $_ENV)) {
            putenv("$name=$value");
            $_ENV[$name] = $value;
        }
    }
}

function config($key)
{
    static $configs = [];
    $parts = explode('.', $key);
    $file = $parts[0];
    if (!isset($configs[$file])) {
        $configs[$file] = require BASE_PATH . '/config/' . $file . '.php';
    }
    if (count($parts) === 1) {
        return $configs[$file];
    }
    return isset($configs[$file][$parts[1]]) ? $configs[$file][$parts[1]] : null;
}

function uuid()
{
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function redirect($url)
{
    header('Location: ' . $url);
    exit;
}

function url($path = '')
{
    $base = rtrim(config('app')['url'], '/');
    return $base . '/' . ltrim($path, '/');
}

function resume_url($applicationId, $preview = false)
{
    $q = $preview ? '?preview=1' : '';
    return url('applications/' . $applicationId . '/resume' . $q);
}

function php_ini_bytes($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return 0;
    }
    $unit = strtolower(substr($value, -1));
    $number = (float) $value;
    switch ($unit) {
        case 'g':
            return (int) ($number * 1024 * 1024 * 1024);
        case 'm':
            return (int) ($number * 1024 * 1024);
        case 'k':
            return (int) ($number * 1024);
        default:
            return (int) $number;
    }
}

function php_upload_limit_bytes()
{
    $upload = php_ini_bytes(ini_get('upload_max_filesize'));
    $post = php_ini_bytes(ini_get('post_max_size'));
    if ($upload <= 0) {
        return $post;
    }
    if ($post <= 0) {
        return $upload;
    }
    return min($upload, $post);
}

function php_upload_limit_mb()
{
    $bytes = php_upload_limit_bytes();
    return $bytes > 0 ? round($bytes / 1024 / 1024, 1) : 0;
}

function upload_error_message($code)
{
    $limitMb = php_upload_limit_mb();
    $limitNote = $limitMb > 0 ? ' (server limit: ' . $limitMb . ' MB)' : '';

    $messages = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds PHP upload_max_filesize' . $limitNote . '. Raise limits in php.ini or use a smaller file.',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds the form upload size limit.',
        UPLOAD_ERR_PARTIAL => 'Upload was interrupted. Try again.',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server temp folder missing. Contact your administrator.',
        UPLOAD_ERR_CANT_WRITE => 'Server could not write the uploaded file to disk.',
        UPLOAD_ERR_EXTENSION => 'A PHP extension blocked this upload.',
    ];

    if (isset($messages[$code])) {
        return $messages[$code];
    }

    return 'Upload failed (error ' . (int) $code . ').';
}

function asset($path)
{
    $file = BASE_PATH . '/public/assets/' . ltrim($path, '/');
    $v = file_exists($file) ? filemtime($file) : time();
    return url('assets/' . ltrim($path, '/')) . '?v=' . $v;
}

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function app_cookie_path()
{
    $urlPath = parse_url(config('app')['url'], PHP_URL_PATH);
    return $urlPath ? rtrim($urlPath, '/') . '/' : '/';
}

function app_is_https()
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    return !empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https';
}

function session_configure()
{
    $path = app_cookie_path();
    $secure = app_is_https();
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $path,
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        session_set_cookie_params(0, $path, '', $secure, true);
    }
}

function php_post_limit_bytes()
{
    return php_ini_bytes(ini_get('post_max_size'));
}

function request_content_length()
{
    return isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
}

function post_body_likely_truncated()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return false;
    }
    $length = request_content_length();
    $limit = php_post_limit_bytes();
    return $length > 0 && $limit > 0 && $length > $limit;
}

function csrf_sync_cookie($token)
{
    if (headers_sent()) {
        return;
    }
    if (!isset($_COOKIE['XSRF-TOKEN']) || !hash_equals((string) $_COOKIE['XSRF-TOKEN'], $token)) {
        setcookie('XSRF-TOKEN', $token, 0, app_cookie_path(), '', app_is_https(), false);
    }
}

function csrf_token_from_request()
{
    if (!empty($_POST['_token'])) {
        return (string) $_POST['_token'];
    }
    if (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        return (string) $_SERVER['HTTP_X_CSRF_TOKEN'];
    }
    if (!empty($_COOKIE['XSRF-TOKEN'])) {
        return (string) $_COOKIE['XSRF-TOKEN'];
    }
    return '';
}

function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    csrf_sync_cookie($_SESSION['csrf_token']);
    return $_SESSION['csrf_token'];
}

function csrf_field()
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf()
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return;
    }

    if (post_body_likely_truncated()) {
        http_response_code(413);
        die('Upload too large. Server post_max_size is ' . ini_get('post_max_size') . '. Increase PHP limits for interview video uploads.');
    }

    $token = csrf_token_from_request();
    if (empty($_SESSION['csrf_token']) || $token === '' || !hash_equals($_SESSION['csrf_token'], $token)) {
        if ($token === '' && request_content_length() > 1024) {
            http_response_code(413);
            die('Upload may have exceeded server limits (form data not received). post_max_size=' . ini_get('post_max_size') . ', upload_max_filesize=' . ini_get('upload_max_filesize'));
        }
        http_response_code(419);
        die('CSRF token mismatch. Refresh the page and try again.');
    }
}

function score_label($score)
{
    if ($score === null || $score === '') {
        return ['label' => '-', 'class' => ''];
    }
    $labels = config('app')['score_labels'];
    foreach ($labels as $item) {
        if ((float) $score >= $item['min']) {
            return $item;
        }
    }
    return ['label' => 'Poor', 'class' => 'score-poor'];
}

function initials($first, $last = '')
{
    $f = strtoupper(substr(trim($first), 0, 1));
    $l = strtoupper(substr(trim($last), 0, 1));
    return $f . $l;
}

function flash($key, $message = null)
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return;
    }
    if (isset($_SESSION['flash'][$key])) {
        $msg = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
    return null;
}

function json_response($data, $code = 200)
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function view($name, $data = [])
{
    extract($data);
    $viewFile = BASE_PATH . '/views/' . str_replace('.', '/', $name) . '.php';
    if (!file_exists($viewFile)) {
        throw new RuntimeException("View not found: $name");
    }
    ob_start();
    include $viewFile;
    return ob_get_clean();
}

function render($name, $data = [], $layout = 'layouts.app')
{
    $content = view($name, $data);
    echo view($layout, array_merge($data, ['content' => $content]));
}

function auth_user()
{
    return isset($_SESSION['user']) ? $_SESSION['user'] : null;
}

function auth_check()
{
    return auth_user() !== null;
}

function auth_id()
{
    $user = auth_user();
    return $user ? $user['id'] : null;
}

function require_auth()
{
    if (!auth_check()) {
        redirect(url('login'));
    }
}

function old($key, $default = '')
{
    return isset($_SESSION['old_input'][$key]) ? $_SESSION['old_input'][$key] : $default;
}

function format_date($date, $format = 'd M, Y')
{
    if (empty($date)) {
        return '';
    }
    return date($format, strtotime($date));
}

function format_time($time)
{
    if (empty($time)) {
        return '';
    }
    return date('h:i a', strtotime($time));
}

/** MSSQL pagination: requires ORDER BY in $sql */
function sql_page($limit, $offset)
{
    return ' OFFSET ' . (int) $offset . ' ROWS FETCH NEXT ' . (int) $limit . ' ROWS ONLY';
}

/** MSSQL TOP n shorthand */
function sql_top($n)
{
    return (int) $n;
}
