<?php
/**
 * Reads admin-configurable values from the settings table (bank details,
 * payment instructions, branding overrides, etc). Write access is added
 * alongside /admin/settings.php in a later stage.
 */

function get_setting(string $key, string $default = ''): string
{
    static $settings = null;

    if ($settings === null) {
        $settings = [];
        $stmt = getDB()->query('SELECT setting_key, setting_value FROM settings');
        foreach ($stmt->fetchAll() as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }

    $value = $settings[$key] ?? null;

    return ($value !== null && $value !== '') ? $value : $default;
}
