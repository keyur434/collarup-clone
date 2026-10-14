<?php

class TeamController
{
    private function requireTeamAdmin()
    {
        if (!PermissionService::canManageTeam()) {
            http_response_code(403);
            die('Only owners can manage team members.');
        }
    }

    public function index()
    {
        $perPage = isset($_GET['perPage']) ? max(1, (int) $_GET['perPage']) : 15;
        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $offset = ($page - 1) * $perPage;
        $total = db()->fetch('SELECT COUNT(*) as c FROM users WHERE is_active = 1')['c'];
        $members = db()->fetchAll('SELECT * FROM users WHERE is_active = 1 ORDER BY first_name' . sql_page($perPage, $offset));

        render('team.index', [
            'title' => 'Team Management',
            'members' => $members,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => max(1, ceil($total / $perPage)),
            'activeNav' => 'team',
        ]);
    }

    public function create()
    {
        $this->requireTeamAdmin();
        $azure = new AzureAdAuthService();
        render('team.create', [
            'title' => 'Add Team Member',
            'member' => null,
            'ssoEnabled' => $azure->isConfigured(),
            'activeNav' => 'team',
        ]);
    }

    public function store()
    {
        $this->requireTeamAdmin();
        verify_csrf();
        try {
            $row = TeamMemberService::validatedInput($_POST);
            TeamMemberService::createOrReactivate($row);
            $azure = new AzureAdAuthService();
            $hint = $azure->isConfigured()
                ? ' They must sign in with Microsoft using exactly: ' . $row['email'] . '.'
                : ' They can sign in with email and password.';
            flash('success', 'Team member added.' . $hint);
        } catch (InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            redirect(url('team/create'));
        } catch (Exception $e) {
            flash('error', 'Could not add team member: ' . $e->getMessage());
            redirect(url('team/create'));
        }
        redirect(url('team'));
    }

    public function edit($id)
    {
        $this->requireTeamAdmin();
        $member = db()->fetch('SELECT * FROM users WHERE id = ? AND is_active = 1', [$id]);
        if (!$member) {
            http_response_code(404);
            die('Not found');
        }
        unset($member['password_hash']);
        $azure = new AzureAdAuthService();
        render('team.create', [
            'title' => 'Edit Team Member',
            'member' => $member,
            'ssoEnabled' => $azure->isConfigured(),
            'activeNav' => 'team',
        ]);
    }

    public function update($id)
    {
        $this->requireTeamAdmin();
        verify_csrf();
        $member = db()->fetch('SELECT * FROM users WHERE id = ? AND is_active = 1', [$id]);
        if (!$member) {
            flash('error', 'Team member not found.');
            redirect(url('team'));
        }
        try {
            $row = TeamMemberService::validatedInput($_POST);
            TeamMemberService::updateMember($id, $row);
            if ((int) $id === (int) auth_id()) {
                $_SESSION['user'] = array_merge($_SESSION['user'], [
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                    'email' => $row['email'],
                    'role' => $row['role'],
                    'initials' => $row['initials'],
                ]);
            }
            flash('success', 'Team member updated. Role is now: ' . ucwords(str_replace('_', ' ', $row['role'])) . '.');
        } catch (InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            redirect(url('team/edit/' . $id));
        } catch (Exception $e) {
            flash('error', 'Could not update team member: ' . $e->getMessage());
            redirect(url('team/edit/' . $id));
        }
        redirect(url('team'));
    }

    public function delete($id)
    {
        $this->requireTeamAdmin();
        verify_csrf();

        $member = db()->fetch('SELECT * FROM users WHERE id = ? AND is_active = 1', [$id]);
        if (!$member) {
            flash('error', 'Team member not found or already removed.');
            redirect(url('team'));
        }

        if ((int) $id === (int) auth_id()) {
            flash('error', 'You cannot remove your own account while signed in.');
            redirect(url('team'));
        }

        if ($member['role'] === 'owner') {
            $owners = db()->fetch('SELECT COUNT(*) as c FROM users WHERE role = ? AND is_active = 1', ['owner']);
            if ((int) $owners['c'] <= 1) {
                flash('error', 'Cannot remove the only active owner. Add another owner first.');
                redirect(url('team'));
            }
        }

        db()->update('users', [
            'is_active' => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $id]);

        flash('success', 'Team member removed. They can no longer sign in.');
        redirect(url('team'));
    }
}
