<?php

/**
 * Read env var: .env ($_ENV) first, then process environment.
 */
function env($key, $default = null)
{
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return $_ENV[$key];
    }
    $v = getenv($key);
    if ($v !== false && $v !== '') {
        return $v;
    }

    return $default;
}

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
        if ($value !== '' && strlen($value) >= 2) {
            $q = $value[0];
            if (($q === '"' || $q === "'") && substr($value, -1) === $q) {
                $value = substr($value, 1, -1);
            }
        }
        // .env must win over empty Apache/system defaults (fixes intermittent "Login failed for sa").
        $_ENV[$name] = $value;
        putenv($name . '=' . $value);
    }
}

/** Valid UTF-8 for json_encode and external APIs (strips bad bytes / control chars). */
function sanitize_utf8_string($value)
{
    if ($value === null || $value === '') {
        return '';
    }
    if (!is_string($value)) {
        $value = (string) $value;
    }
    if (function_exists('mb_convert_encoding')) {
        $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    } elseif (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
        if ($converted !== false) {
            $value = $converted;
        }
    }
    return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value);
}

function sanitize_utf8_deep($data)
{
    if (is_string($data)) {
        return sanitize_utf8_string($data);
    }
    if (is_array($data)) {
        foreach ($data as $key => $val) {
            $data[$key] = sanitize_utf8_deep($val);
        }
    }

    return $data;
}

function app_name()
{
    return config('app')['name'];
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

/** Sortable table header (requires table data-sortable + app.js initDataTableSort). */
function th_sort($label, $type = 'string', $defaultDir = null, $class = '')
{
    $extra = '';
    if ($defaultDir === 'asc' || $defaultDir === 'desc') {
        $extra = ' data-sort-default="' . $defaultDir . '"';
    }
    $classAttr = $class !== '' ? ' class="' . e($class) . '"' : '';
    return '<th data-sort-type="' . e($type) . '"' . $extra . $classAttr . '>'
        . '<button type="button" class="th-sort" title="' . e($label) . '">'
        . '<span class="th-sort-label">' . e($label) . '</span>'
        . '<span class="sort-arrows" aria-hidden="true"></span>'
        . '</button></th>';
}

function sort_data_val($value)
{
    if ($value === null || $value === '') {
        return '';
    }
    return e(is_numeric($value) ? (string) (float) $value : (string) $value);
}

/** Breadcrumb: Dashboard → section → optional leaf (no URL on leaf). Pass null $sectionUrl on section index. */
function nav_trail_dashboard($sectionLabel, $sectionUrl = null, $leafLabel = null)
{
    $trail = [
        ['label' => 'Dashboard', 'url' => url('dashboard')],
    ];
    $section = ['label' => $sectionLabel];
    if ($sectionUrl) {
        $section['url'] = $sectionUrl;
    }
    $trail[] = $section;
    if ($leafLabel !== null && $leafLabel !== '') {
        $trail[] = ['label' => $leafLabel];
    }
    return $trail;
}

/** Breadcrumb: Job Roles → job title → optional leaf. */
function nav_trail_job(array $job, $leafLabel = null, $leafUrl = null)
{
    $trail = [
        ['label' => 'Job Roles', 'url' => url('jobs')],
        ['label' => $job['title'], 'url' => url('jobs/' . $job['id'] . '/interviews')],
    ];
    if ($leafLabel !== null && $leafLabel !== '') {
        $item = ['label' => $leafLabel];
        if ($leafUrl) {
            $item['url'] = $leafUrl;
        }
        $trail[] = $item;
    }
    return $trail;
}

/** Path under APP_URL base (no trailing slash), e.g. /jobs/abc/interviews */
function app_request_path()
{
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($uri, PHP_URL_PATH) ?: '/';
    $basePath = parse_url(config('app')['url'], PHP_URL_PATH) ?: '';
    $basePath = rtrim($basePath, '/');
    if ($basePath !== '' && strpos($path, $basePath) === 0) {
        $path = substr($path, strlen($basePath)) ?: '/';
    }

    return '/' . ltrim($path, '/');
}

function app_route_path_from_url($fullUrl)
{
    $path = parse_url($fullUrl, PHP_URL_PATH) ?: '/';
    $basePath = parse_url(config('app')['url'], PHP_URL_PATH) ?: '';
    $basePath = rtrim($basePath, '/');
    if ($basePath !== '' && strpos($path, $basePath) === 0) {
        $path = substr($path, strlen($basePath)) ?: '/';
    }

    return '/' . ltrim($path, '/');
}

function nav_url_is_current_page($href)
{
    $norm = function ($p) {
        $p = rtrim($p, '/');
        return $p === '' ? '/' : $p;
    };

    if ($norm(app_request_path()) !== $norm(app_route_path_from_url($href))) {
        return false;
    }

    $currentQuery = [];
    parse_str(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY) ?: '', $currentQuery);
    $targetQuery = [];
    parse_str(parse_url($href, PHP_URL_QUERY) ?: '', $targetQuery);
    ksort($currentQuery);
    ksort($targetQuery);

    return $currentQuery === $targetQuery;
}

/**
 * Back target for the topbar arrow: nearest breadcrumb ancestor whose URL is not this page.
 */
function nav_resolve_back(array $breadcrumbs)
{
    $withUrl = [];
    $lastIndex = count($breadcrumbs) - 1;
    foreach ($breadcrumbs as $i => $bc) {
        if ($i === $lastIndex) {
            break;
        }
        if (!empty($bc['url'])) {
            $withUrl[] = $bc;
        }
    }
    for ($j = count($withUrl) - 1; $j >= 0; $j--) {
        if (!nav_url_is_current_page($withUrl[$j]['url'])) {
            return $withUrl[$j];
        }
    }

    return null;
}

function nav_normalize_breadcrumbs($breadcrumbs, $pageBack, $title)
{
    if (!empty($breadcrumbs)) {
        return $breadcrumbs;
    }
    if (!empty($pageBack)) {
        $trail = [
            ['label' => $pageBack['label'], 'url' => $pageBack['url']],
        ];
        if (!empty($title) && $title !== $pageBack['label']) {
            $trail[] = ['label' => $title];
        }
        return $trail;
    }
    if (!empty($title)) {
        return [['label' => $title]];
    }
    return [['label' => 'Dashboard', 'url' => url('dashboard')]];
}

/** Allow redirects only to URLs on this app (prevents open redirect). */
function safe_app_redirect($candidate, $fallback)
{
    if (!is_string($candidate) || trim($candidate) === '') {
        return $fallback;
    }
    $candidate = trim($candidate);
    $base = rtrim(config('app')['url'], '/');
    if (strpos($candidate, $base) !== 0) {
        return $fallback;
    }
    return $candidate;
}

function report_share_require_login()
{
    return !empty(config('app')['report_share_require_login']);
}

function auth_set_intended_url($absoluteUrl)
{
    $_SESSION['post_login_redirect'] = safe_app_redirect($absoluteUrl, url('dashboard'));
}

function auth_intended_url()
{
    if (empty($_SESSION['post_login_redirect'])) {
        return url('dashboard');
    }

    return safe_app_redirect($_SESSION['post_login_redirect'], url('dashboard'));
}

function auth_clear_intended_url()
{
    unset($_SESSION['post_login_redirect']);
}

function auth_redirect_after_login()
{
    $target = auth_intended_url();
    auth_clear_intended_url();
    redirect($target);
}

/** Store return URL and send user to Microsoft (or login page). */
function auth_require_login_for_url($intendedAbsoluteUrl)
{
    auth_set_intended_url($intendedAbsoluteUrl);
    $azure = new AzureAdAuthService();
    if ($azure->isConfigured()) {
        redirect(url('login/azure'));
    }
    redirect(url('login'));
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

/** Coerce AI/JSON values to a string safe for NVARCHAR columns. */
function db_text($value, $default = '')
{
    if ($value === null || $value === '') {
        return $default;
    }
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }
    if (is_scalar($value)) {
        return (string) $value;
    }
    if (!is_array($value)) {
        return (string) $value;
    }
    $lines = [];
    foreach ($value as $item) {
        if (is_array($item)) {
            $nested = db_text($item);
            if ($nested !== '') {
                $lines[] = $nested;
            }
        } else {
            $item = trim((string) $item);
            if ($item !== '') {
                $lines[] = $item;
            }
        }
    }
    return $lines ? implode("\n", $lines) : $default;
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

function scorecard_tone($score)
{
    if ($score === null || $score === '') {
        return 'poor';
    }
    return ((float) $score >= 8) ? 'good' : (((float) $score >= 5) ? 'avg' : 'poor');
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

/**
 * Render a view and return [html, nav vars set inside the view (breadcrumbs, pageBack)].
 */
function view_with_nav($name, $data = [])
{
    extract($data);
    $viewFile = BASE_PATH . '/views/' . str_replace('.', '/', $name) . '.php';
    if (!file_exists($viewFile)) {
        throw new RuntimeException("View not found: $name");
    }
    ob_start();
    include $viewFile;
    $html = ob_get_clean();
    $nav = [];
    if (isset($breadcrumbs)) {
        $nav['breadcrumbs'] = $breadcrumbs;
    }
    if (isset($pageBack)) {
        $nav['pageBack'] = $pageBack;
    }
    return [$html, $nav];
}

/** @return array{current:int,prev:?int,next:?int,pages:array}|null */
function pagination_meta($page, $totalPages)
{
    $page = max(1, (int) $page);
    $totalPages = max(1, (int) $totalPages);
    if ($totalPages <= 1) {
        return null;
    }
    $range = 2;
    $pages = [];
    for ($i = 1; $i <= $totalPages; $i++) {
        if ($i === 1 || $i === $totalPages || ($i >= $page - $range && $i <= $page + $range)) {
            $pages[] = $i;
        } elseif (end($pages) !== '...') {
            $pages[] = '...';
        }
    }
    return [
        'current' => $page,
        'prev' => $page > 1 ? $page - 1 : null,
        'next' => $page < $totalPages ? $page + 1 : null,
        'pages' => $pages,
    ];
}

/** Preserve current GET params for list pagination / per-page links. */
function page_query(array $merge = [], array $except = [])
{
    $q = $_GET;
    foreach ($except as $key) {
        unset($q[$key]);
    }
    $q = array_merge($q, $merge);
    foreach ($q as $k => $v) {
        if ($v === '' || $v === null) {
            unset($q[$k]);
        }
    }
    $qs = http_build_query($q);
    return $qs === '' ? '?' : '?' . $qs;
}

function render($name, $data = [], $layout = 'layouts.app')
{
    if (!isset($data['pagination']) && isset($data['page'], $data['totalPages'])) {
        $data['pagination'] = pagination_meta($data['page'], $data['totalPages']);
    }
    [$content, $nav] = view_with_nav($name, $data);
    echo view($layout, array_merge($data, $nav, ['content' => $content]));
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

function user_display_name($first, $last = '')
{
    $name = trim(trim((string) $first) . ' ' . trim((string) $last));
    return $name !== '' ? $name : '—';
}

/** City / state line for job list (e.g. "Pune, Maharashtra"). */
function job_location_label($cityName, $stateName)
{
    $city = trim((string) $cityName);
    $state = trim((string) $stateName);
    if ($city !== '' && $state !== '') {
        return $city . ', ' . $state;
    }
    if ($city !== '') {
        return $city;
    }
    if ($state !== '') {
        return $state;
    }

    return '—';
}

/** SQL fragment: non-archived applications only. */
function sql_active_applications_only($alias = 'a')
{
    $alias = preg_replace('/[^a-z_]/', '', $alias);

    return " AND ({$alias}.is_archived = 0 OR {$alias}.is_archived IS NULL)";
}

/** Active jobs for dropdowns (department, location, creator). */
function fetch_active_jobs_for_select()
{
    return db()->fetchAll(
        "SELECT j.id, j.title, d.name AS department_name,
                ci.name AS city_name, st.name AS state_name,
                creator.first_name AS creator_first_name, creator.last_name AS creator_last_name
         FROM jobs j
         LEFT JOIN departments d ON d.id = j.department_id
         LEFT JOIN cities ci ON ci.id = j.city_id
         LEFT JOIN states st ON st.id = j.state_id
         LEFT JOIN users creator ON creator.id = j.created_by
         WHERE j.status = 'active'
         ORDER BY j.title"
    );
}

/** Job title plus department, location, creator for select options. */
function job_select_option_label(array $job)
{
    $title = trim((string) ($job['title'] ?? ''));
    $meta = [];
    $dept = trim((string) ($job['department_name'] ?? ''));
    if ($dept !== '') {
        $meta[] = $dept;
    }
    $loc = job_location_label($job['city_name'] ?? '', $job['state_name'] ?? '');
    if ($loc !== '—') {
        $meta[] = $loc;
    }
    $creator = user_display_name($job['creator_first_name'] ?? '', $job['creator_last_name'] ?? '');
    if ($creator !== '—') {
        $meta[] = $creator;
    }
    if ($meta === []) {
        return $title;
    }

    return $title . ' (' . implode(' · ', $meta) . ')';
}

/** HH:MM for HTML time inputs (strips MSSQL fractional seconds). */
function normalize_time_input($time)
{
    if ($time === null || $time === '') {
        return '';
    }
    $time = trim((string) $time);
    if (preg_match('/^(\d{1,2}:\d{2})/', $time, $m)) {
        return $m[1];
    }
    return $time;
}

/** Store TIME column value from form POST. */
function normalize_time_store($time)
{
    $time = normalize_time_input($time);
    if ($time === '') {
        return null;
    }
    return strlen($time) === 5 ? $time . ':00' : $time;
}

function interview_default_end_time($startTime, $minutes = 45)
{
    $startTime = normalize_time_store($startTime);
    if (!$startTime) {
        return null;
    }
    $tz = new DateTimeZone(config('app')['timezone']);
    $dt = DateTime::createFromFormat('H:i:s', $startTime, $tz);
    if (!$dt) {
        return null;
    }
    $dt->modify('+' . (int) $minutes . ' minutes');
    return $dt->format('H:i:s');
}

function format_interview_time_range($start, $end = null)
{
    if (empty($start)) {
        return '';
    }
    $s = format_time($start);
    if (empty($end)) {
        return ' · ' . $s;
    }
    return ' · ' . $s . '–' . format_time($end);
}

/** job_rubric_criteria.name is NVARCHAR(150) — keep inserts safe. */
function rubric_criterion_fields($name, $description = '')
{
    $name = trim((string) $name);
    $description = trim((string) $description);
    $max = 150;
    if (mb_strlen($name) <= $max) {
        return ['name' => $name, 'description' => $description !== '' ? $description : null];
    }
    $short = mb_substr($name, 0, $max - 1) . '…';
    $full = $name;
    if ($description !== '') {
        $full = $name . "\n\n" . $description;
    }
    return ['name' => $short, 'description' => $full];
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

/** True when value is the old dev placeholder, not real company copy. */
function company_about_is_setup_instructions($text)
{
    $text = trim((string) $text);
    if ($text === '') {
        return false;
    }
    if (stripos($text, 'Replace this text in Settings') !== false) {
        return true;
    }
    if (stripos($text, '010_company_about_seed.php') !== false) {
        return true;
    }
    if (stripos($text, 'Describe your organization, products, and culture') !== false
        && stripos($text, 'on-brand') !== false) {
        return true;
    }
    return false;
}

/** Default About Company copy for job postings (Settings can override). */
function company_about_default()
{
    static $text = null;
    if ($text !== null) {
        return $text;
    }
    $path = BASE_PATH . '/database/defaults/company_about.txt';
    if (is_readable($path)) {
        $raw = trim(file_get_contents($path));
        $text = company_about_is_setup_instructions($raw) ? '' : $raw;
        return $text;
    }
    $text = '';
    return $text;
}

function company_about_effective()
{
    $stored = AppSettingsService::get('company_about', null);
    if ($stored !== null && trim($stored) !== '') {
        if (!company_about_is_setup_instructions($stored)) {
            return $stored;
        }
    }
    return company_about_default();
}

/** Normalize job/settings about text (strip legacy placeholder). */
function company_about_for_display($value)
{
    $value = trim((string) $value);
    if ($value === '' || company_about_is_setup_instructions($value)) {
        return company_about_effective();
    }
    return $value;
}

/**
 * Build SVG paths for dashboard success-rate chart (passed + rejected series).
 *
 * @param array<int, array{passed:int,rejected:int}> $points
 */
function dashboard_success_rate_chart_paths(array $points, int $width = 600, int $height = 130): array
{
    $n = count($points);
    if ($n === 0) {
        return ['passed_line' => '', 'passed_area' => '', 'rejected_line' => '', 'max' => 1];
    }

    $max = 1;
    foreach ($points as $pt) {
        $max = max($max, (int) ($pt['passed'] ?? 0), (int) ($pt['rejected'] ?? 0));
    }

    $top = 18;
    $bottom = 115;
    $plotH = $bottom - $top;
    $xStep = $n > 1 ? $width / ($n - 1) : 0;

    $toCoords = function (array $values) use ($n, $width, $max, $top, $plotH, $xStep) {
        $pts = [];
        for ($i = 0; $i < $n; $i++) {
            $x = $n > 1 ? ($i * $xStep) : ($width / 2);
            $v = (float) ($values[$i] ?? 0);
            $y = $top + $plotH * (1 - $v / $max);
            $pts[] = [$x, $y];
        }
        return $pts;
    };

    $linePath = function (array $pts) {
        if (count($pts) === 0) {
            return '';
        }
        $d = 'M' . round($pts[0][0], 1) . ' ' . round($pts[0][1], 1);
        for ($i = 1; $i < count($pts); $i++) {
            $d .= ' L' . round($pts[$i][0], 1) . ' ' . round($pts[$i][1], 1);
        }
        return $d;
    };

    $passedVals = array_map(function ($pt) {
        return (int) ($pt['passed'] ?? 0);
    }, $points);
    $rejectedVals = array_map(function ($pt) {
        return (int) ($pt['rejected'] ?? 0);
    }, $points);

    $pPts = $toCoords($passedVals);
    $rPts = $toCoords($rejectedVals);
    $pLine = $linePath($pPts);

    $area = '';
    if ($pLine !== '') {
        $last = $pPts[count($pPts) - 1];
        $first = $pPts[0];
        $area = $pLine . ' L' . round($last[0], 1) . ' ' . $bottom
            . ' L' . round($first[0], 1) . ' ' . $bottom . ' Z';
    }

    return [
        'passed_line' => $pLine,
        'passed_area' => $area,
        'rejected_line' => $linePath($rPts),
        'max' => $max,
    ];
}
