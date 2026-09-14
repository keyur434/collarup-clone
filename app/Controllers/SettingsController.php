<?php



class SettingsController

{

    private static $types = [

        'departments' => ['table' => 'departments', 'label' => 'Departments', 'field' => 'name'],

        'seniority' => ['table' => 'seniority_levels', 'label' => 'Seniority Levels', 'field' => 'name', 'order' => 'sort_order'],

        'work-modes' => ['table' => 'work_modes', 'label' => 'Work Modes', 'field' => 'name'],

        'sources' => ['table' => 'candidate_sources', 'label' => 'Candidate Sources', 'field' => 'name'],

        'experience' => ['table' => 'experience_bands', 'label' => 'Experience Bands', 'field' => 'name', 'order' => 'sort_order'],

        'countries' => ['table' => 'countries', 'label' => 'Countries', 'field' => 'name', 'extra' => ['code']],

    ];



    private function requireAdmin()

    {

        if (!PermissionService::isAdminRole()) {

            http_response_code(403);

            die('Access denied.');

        }

    }



    public function index()

    {

        $this->requireAdmin();

        $type = isset($_GET['type']) ? $_GET['type'] : 'departments';

        if (!isset(self::$types[$type]) && $type !== 'locations' && $type !== 'integrations') {

            $type = 'departments';

        }



        $data = [

            'title' => 'Settings',

            'activeNav' => 'settings',

            'type' => $type,

            'types' => self::$types,

        ];



        if ($type === 'locations') {

            $data['countries'] = db()->fetchAll('SELECT * FROM countries ORDER BY name');

            $data['states'] = db()->fetchAll(

                'SELECT s.*, c.name as country_name FROM states s JOIN countries c ON c.id = s.country_id ORDER BY c.name, s.name'

            );

            $data['cities'] = db()->fetchAll(

                'SELECT ci.*, s.name as state_name, c.name as country_name

                 FROM cities ci JOIN states s ON s.id = ci.state_id JOIN countries c ON c.id = s.country_id

                 ORDER BY c.name, s.name, ci.name'

            );

        } elseif ($type === 'integrations') {
            $data['settings'] = [
                'retention_days_completed' => AppSettingsService::getInt('retention_days_completed', 365),
                'retention_auto_archive' => AppSettingsService::getBool('retention_auto_archive', true),
                'notify_report_ready' => AppSettingsService::getBool('notify_report_ready', true),
                'notify_decision_email' => AppSettingsService::getBool('notify_decision_email', true),
                'keka_last_sync_at' => AppSettingsService::get('keka_last_sync_at', ''),
            ];
            $data['keka_count'] = 0;
            try {
                $row = db()->fetch('SELECT COUNT(*) as c FROM keka_employees');
                $data['keka_count'] = (int) $row['c'];
            } catch (Exception $e) {
            }
            $data['notification_log'] = [];
            try {
                $data['notification_log'] = db()->fetchAll(
                    'SELECT TOP 20 notification_type, recipient, subject, status, created_at FROM notification_log ORDER BY id DESC'
                );
            } catch (Exception $e) {
            }
        } else {

            $meta = self::$types[$type];

            $order = isset($meta['order']) ? $meta['order'] : 'name';

            $data['items'] = db()->fetchAll('SELECT * FROM ' . $meta['table'] . ' ORDER BY ' . $order);

            $data['meta'] = $meta;

        }



        render('settings.index', $data);

    }



    public function store()

    {

        $this->requireAdmin();

        verify_csrf();

        $type = $_POST['type'] ?? '';

        if (!isset(self::$types[$type])) {

            flash('error', 'Invalid type.');

            redirect(url('settings'));

        }



        $meta = self::$types[$type];

        $name = trim($_POST['name'] ?? '');

        if ($name === '') {

            flash('error', 'Name is required.');

            redirect(url('settings?type=' . $type));

        }



        $row = ['name' => $name];

        if ($type === 'countries') {

            $row['code'] = strtoupper(trim($_POST['code'] ?? substr($name, 0, 2)));

        }

        if ($type === 'seniority' || $type === 'experience') {

            $row['sort_order'] = (int) ($_POST['sort_order'] ?? 99);

        }

        if ($type === 'experience') {

            $row['min_years'] = (float) ($_POST['min_years'] ?? 0);

            $row['max_years'] = $_POST['max_years'] !== '' ? (float) $_POST['max_years'] : null;

        }

        if (in_array($type, ['departments', 'work-modes', 'sources'], true)) {

            $row['is_active'] = 1;

        }



        db()->insert($meta['table'], $row);

        flash('success', $meta['label'] . ' item added.');

        redirect(url('settings?type=' . $type));

    }



    public function storeLocation()

    {

        $this->requireAdmin();

        verify_csrf();

        $kind = $_POST['kind'] ?? '';



        if ($kind === 'state') {

            $countryId = (int) $_POST['country_id'];

            $name = trim($_POST['name'] ?? '');

            if ($countryId && $name) {

                db()->insert('states', ['country_id' => $countryId, 'name' => $name]);

                flash('success', 'State added.');

            }

        } elseif ($kind === 'city') {

            $stateId = (int) $_POST['state_id'];

            $name = trim($_POST['name'] ?? '');

            if ($stateId && $name) {

                db()->insert('cities', ['state_id' => $stateId, 'name' => $name]);

                flash('success', 'City added.');

            }

        } elseif ($kind === 'country') {

            $name = trim($_POST['name'] ?? '');

            if ($name) {

                db()->insert('countries', [

                    'name' => $name,

                    'code' => strtoupper(trim($_POST['code'] ?? substr($name, 0, 2))),

                ]);

                flash('success', 'Country added.');

            }

        }



        redirect(url('settings?type=locations'));

    }



    public function storeIntegrations()
    {
        $this->requireAdmin();
        verify_csrf();

        AppSettingsService::set('retention_days_completed', (string) max(30, (int) ($_POST['retention_days_completed'] ?? 365)));
        AppSettingsService::set('retention_auto_archive', !empty($_POST['retention_auto_archive']) ? '1' : '0');
        AppSettingsService::set('notify_report_ready', !empty($_POST['notify_report_ready']) ? '1' : '0');
        AppSettingsService::set('notify_decision_email', !empty($_POST['notify_decision_email']) ? '1' : '0');

        if (!empty($_POST['run_keka_sync'])) {
            try {
                $keka = new KekaService();
                $result = $keka->syncEmployees();
                flash('success', 'Keka sync complete: ' . (int) $result['synced'] . ' employees.');
            } catch (Exception $e) {
                flash('error', 'Keka sync failed: ' . $e->getMessage());
            }
        } else {
            flash('success', 'Integration settings saved.');
        }

        redirect(url('settings?type=integrations'));
    }

    public function delete($type, $id)

    {

        $this->requireAdmin();

        verify_csrf();

        if (!isset(self::$types[$type])) {

            redirect(url('settings'));

        }

        $meta = self::$types[$type];

        db()->query('DELETE FROM ' . $meta['table'] . ' WHERE id = ?', [$id]);

        flash('success', 'Deleted.');

        redirect(url('settings?type=' . $type));

    }

}


