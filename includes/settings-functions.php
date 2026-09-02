<?php
/**
 * Reads and writes admin-configurable values from the settings table
 * (currently: bank/PromptPay details and payment instructions, used by
 * member/subscription.php and edited via /admin/settings.php). Branding
 * fields like site name stay in config/config.php as the source of
 * truth — see that file's header comment.
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

/** @param array<string,string> $values setting_key => new value */
function update_settings(array $values): void
{
    $stmt = getDB()->prepare('UPDATE settings SET setting_value = ? WHERE setting_key = ?');

    foreach ($values as $key => $value) {
        $stmt->execute([$value, $key]);
    }
}
