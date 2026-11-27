-- ============================================================================
-- TeachLuma: Free-plan soft monthly download limit
-- ============================================================================
-- Additive only: adds ONE index to downloads. Nothing existing is altered,
-- dropped or backfilled. Run once in phpMyAdmin (select the TeachLuma
-- database > SQL tab > paste > Go).
--
-- No new table or column is needed. The limit reuses columns every
-- TeachLuma database already has (they are in database.sql, and
-- migrations/add_pro_trial_credits.sql adds its column AFTER them):
--   * users.free_downloads_used  INT UNSIGNED NOT NULL DEFAULT 0
--   * users.free_downloads_month CHAR(7) NULL   -- 'YYYY-MM', lazy monthly reset
-- The on/off switch and the monthly number are stored in the existing
-- settings table (keys free_download_limit_enabled, free_download_monthly_limit)
-- the first time /admin/settings.php is saved. Until then the defaults in
-- config/config.php apply (FREE_DOWNLOAD_LIMIT_ENABLED = false, so the
-- limit stays OFF after deploying until an admin turns it on).
--
-- What the index is for: has_downloaded_resource_this_month() in
-- includes/download-functions.php looks up (user_id, resource_id, this month)
-- on every free download and resource page view, so re-downloads in the same
-- month are never counted twice.
--
-- Rollback:
--   ALTER TABLE downloads DROP INDEX idx_downloads_user_resource_date;
--   DELETE FROM settings WHERE setting_key IN ('free_download_limit_enabled', 'free_download_monthly_limit');
-- ============================================================================

ALTER TABLE downloads
    ADD INDEX idx_downloads_user_resource_date (user_id, resource_id, downloaded_at);
