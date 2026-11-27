-- ============================================================================
-- TeachLuma: Free Pro Preview credits (lead capture)
-- ============================================================================
-- Additive only: adds one column to users, one column to downloads and one
-- new table. Nothing existing is altered or dropped. Run once in phpMyAdmin
-- (select the TeachLuma database > SQL tab > paste > Go).
--
-- What it does
--   * users.pro_trial_credits: each non-Pro account can download up to 3
--     members-only (Pro) resources for free. 1 credit = 1 resource.
--   * pro_trial_unlocks: which resources a user unlocked with a credit, so
--     re-downloading that resource, or any of its additional files (answer
--     key, slides...), never costs a second credit.
--   * downloads.via_pro_trial: marks the download-history row of the
--     download that spent the credit ("Pro Preview" in My Downloads).
--
-- Existing accounts: ADD COLUMN ... DEFAULT 3 gives every existing account
-- 3 credits as well as new ones. To give credits to NEW accounts only, run
-- the optional UPDATE at the bottom straight after this migration.
--
-- Rollback (removes the feature's data):
--   DROP TABLE pro_trial_unlocks;
--   ALTER TABLE downloads DROP COLUMN via_pro_trial;
--   ALTER TABLE users DROP COLUMN pro_trial_credits;
-- ============================================================================

ALTER TABLE users
    ADD COLUMN pro_trial_credits INT NOT NULL DEFAULT 3 AFTER free_downloads_month;

ALTER TABLE downloads
    ADD COLUMN via_pro_trial TINYINT(1) NOT NULL DEFAULT 0 AFTER ip_address;

CREATE TABLE IF NOT EXISTS pro_trial_unlocks (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    resource_id INT UNSIGNED NOT NULL,
    unlocked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pro_trial_unlocks_user_resource (user_id, resource_id),
    KEY idx_pro_trial_unlocks_resource (resource_id),
    CONSTRAINT fk_pro_trial_unlocks_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_pro_trial_unlocks_resource FOREIGN KEY (resource_id) REFERENCES resources (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Optional: new accounts only (existing accounts get 0 credits).
-- UPDATE users SET pro_trial_credits = 0;
