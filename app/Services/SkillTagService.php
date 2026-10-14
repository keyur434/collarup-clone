<?php

class SkillTagService
{
    public static function normalizeName($name)
    {
        $name = trim(preg_replace('/\s+/u', ' ', (string) $name));
        if ($name === '') {
            return '';
        }
        if (mb_strlen($name) > 100) {
            $name = mb_substr($name, 0, 100);
        }

        return $name;
    }

    /**
     * Ensure skill names exist in skill_tags (active). Returns canonical names saved.
     *
     * @param string[] $names
     * @return string[]
     */
    public static function ensureInPool(array $names)
    {
        $out = [];
        foreach ($names as $name) {
            $name = self::normalizeName($name);
            if ($name === '') {
                continue;
            }
            $key = mb_strtolower($name);
            if (isset($out[$key])) {
                continue;
            }

            try {
                $row = db()->fetch('SELECT id, name FROM skill_tags WHERE LOWER(name) = LOWER(?)', [$name]);
                if ($row) {
                    db()->update('skill_tags', ['is_active' => 1], 'id = :id', ['id' => $row['id']]);
                    $out[$key] = $row['name'];
                } else {
                    db()->insert('skill_tags', [
                        'name' => $name,
                        'is_active' => 1,
                        'sort_order' => 0,
                    ]);
                    $out[$key] = $name;
                }
            } catch (Exception $e) {
                $out[$key] = $name;
            }
        }

        return array_values($out);
    }
}
