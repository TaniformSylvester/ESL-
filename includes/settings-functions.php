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

/**
 * Saves settings, creating any key that doesn't exist yet (settings has a
 * unique key on setting_key), so new settings need no migration.
 *
 * @param array<string,string> $values setting_key => new value
 */
function update_settings(array $values): void
{
    $stmt = getDB()->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );

    foreach ($values as $key => $value) {
        $stmt->execute([$key, $value]);
    }
}
