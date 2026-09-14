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
        $perPage = 15;
        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $offset = ($page - 1) * $perPage;
        $total = db()->fetch('SELECT COUNT(*) as c FROM users')['c'];
        $members = db()->fetchAll('SELECT * FROM users ORDER BY first_name' . sql_page($perPage, $offset));

        render('team.index', [
            'title' => 'Team Management',
            'members' => $members,
            'page' => $page,
            'totalPages' => max(1, ceil($total / $perPage)),
            'activeNav' => 'team',
        ]);
    }

    public function create()
    {
        $this->requireTeamAdmin();
        render('team.create', ['title' => 'Add Team Member', 'member' => null, 'activeNav' => 'team']);
    }

    public function store()
    {
        $this->requireTeamAdmin();
        verify_csrf();
        $first = trim($_POST['first_name']);
        $last = trim($_POST['last_name']);
        db()->insert('users', [
            'first_name' => $first,
            'last_name' => $last,
            'email' => trim($_POST['email']),
            'password_hash' => !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null,
            'role' => $_POST['role'],
            'initials' => initials($first, $last),
        ]);
        flash('success', 'Team member added.');
        redirect(url('team'));
    }

    public function edit($id)
    {
        $this->requireTeamAdmin();
        $member = db()->fetch('SELECT * FROM users WHERE id = ?', [$id]);
        if (!$member) {
            http_response_code(404);
            die('Not found');
        }
        unset($member['password_hash']);
        render('team.create', ['title' => 'Edit Team Member', 'member' => $member, 'activeNav' => 'team']);
    }

    public function update($id)
    {
        $this->requireTeamAdmin();
        verify_csrf();
        $data = [
            'first_name' => trim($_POST['first_name']),
            'last_name' => trim($_POST['last_name']),
            'email' => trim($_POST['email']),
            'role' => $_POST['role'],
            'initials' => initials($_POST['first_name'], $_POST['last_name']),
        ];
        if (!empty($_POST['password'])) {
            $data['password_hash'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }
        db()->update('users', $data, 'id = :id', ['id' => $id]);
        flash('success', 'Team member updated.');
        redirect(url('team'));
    }

    public function delete($id)
    {
        $this->requireTeamAdmin();
        verify_csrf();
        if ((int) $id !== (int) auth_id()) {
            db()->update('users', ['is_active' => 0], 'id = :id', ['id' => $id]);
        }
        flash('success', 'Team member removed.');
        redirect(url('team'));
    }
}
