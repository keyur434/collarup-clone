<?php

class TeamMemberService
{
    public static function allowedRoles()
    {
        return ['owner', 'hr_head', 'member'];
    }

    /**
     * @return array{first_name:string,last_name:string,email:string,role:string,initials:string,password_hash?:string|null}
     */
    public static function validatedInput(array $post, $requirePassword = false)
    {
        $first = trim($post['first_name'] ?? '');
        $last = trim($post['last_name'] ?? '');
        $email = strtolower(trim($post['email'] ?? ''));
        $role = $post['role'] ?? 'member';

        if ($first === '' || $last === '') {
            throw new InvalidArgumentException('First and last name are required.');
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid email address is required.');
        }
        if (!in_array($role, self::allowedRoles(), true)) {
            throw new InvalidArgumentException('Invalid role selected.');
        }

        $row = [
            'first_name' => $first,
            'last_name' => $last,
            'email' => $email,
            'role' => $role,
            'initials' => initials($first, $last),
        ];

        $password = isset($post['password']) ? (string) $post['password'] : '';
        if ($requirePassword && $password === '') {
            throw new InvalidArgumentException('Password is required.');
        }
        if ($password !== '') {
            if (strlen($password) < 8) {
                throw new InvalidArgumentException('Password must be at least 8 characters.');
            }
            $row['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        return $row;
    }

    public static function createOrReactivate(array $row)
    {
        $existing = db()->fetch('SELECT * FROM users WHERE LOWER(LTRIM(RTRIM(email))) = ?', [$row['email']]);

        if ($existing) {
            if ((int) $existing['is_active'] === 1) {
                throw new InvalidArgumentException('A team member with this email already exists.');
            }
            $update = [
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'email' => $row['email'],
                'role' => $row['role'],
                'initials' => $row['initials'],
                'is_active' => 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            if (array_key_exists('password_hash', $row)) {
                $update['password_hash'] = $row['password_hash'];
            } elseif (!self::passwordColumnNullable()) {
                throw new RuntimeException('Database still requires a password. Run migration 007_users_sso_password_null.sql or set a password.');
            } else {
                $update['password_hash'] = null;
            }
            db()->update('users', $update, 'id = :id', ['id' => $existing['id']]);
            return (int) $existing['id'];
        }

        if (!array_key_exists('password_hash', $row)) {
            if (!self::passwordColumnNullable()) {
                throw new InvalidArgumentException('Password is required until migration 007 is applied on the database.');
            }
            $row['password_hash'] = null;
        }

        $row['is_active'] = 1;
        return (int) db()->insert('users', $row);
    }

    public static function updateMember($id, array $row)
    {
        $update = [
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'email' => $row['email'],
            'role' => $row['role'],
            'initials' => $row['initials'],
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if (array_key_exists('password_hash', $row)) {
            $update['password_hash'] = $row['password_hash'];
        }

        $other = db()->fetch(
            'SELECT id FROM users WHERE LOWER(LTRIM(RTRIM(email))) = ? AND id <> ? AND is_active = 1',
            [$row['email'], $id]
        );
        if ($other) {
            throw new InvalidArgumentException('Another active user already has this email.');
        }

        db()->update('users', $update, 'id = :id', ['id' => $id]);
    }

    public static function passwordColumnNullable()
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        try {
            $row = db()->fetch(
                "SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_NAME = 'users' AND COLUMN_NAME = 'password_hash'"
            );
            $cached = $row && strtoupper($row['IS_NULLABLE']) === 'YES';
        } catch (Exception $e) {
            $cached = true;
        }
        return $cached;
    }
}
