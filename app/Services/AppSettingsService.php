<?php

class AppSettingsService
{
    public static function get($key, $default = '')
    {
        try {
            $row = db()->fetch('SELECT setting_value FROM app_settings WHERE setting_key = ?', [$key]);
            return $row ? $row['setting_value'] : $default;
        } catch (Exception $e) {
            return $default;
        }
    }

    public static function getBool($key, $default = false)
    {
        $val = strtolower((string) self::get($key, $default ? '1' : '0'));
        return in_array($val, ['1', 'true', 'yes', 'on'], true);
    }

    public static function getInt($key, $default = 0)
    {
        return (int) self::get($key, (string) $default);
    }

    public static function set($key, $value)
    {
        $existing = db()->fetch('SELECT setting_key FROM app_settings WHERE setting_key = ?', [$key]);
        if ($existing) {
            db()->update('app_settings', [
                'setting_value' => $value,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'setting_key = :k', ['k' => $key]);
        } else {
            db()->insert('app_settings', [
                'setting_key' => $key,
                'setting_value' => $value,
            ]);
        }
    }
}
