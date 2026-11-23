-- ============================================================================
-- TeachLuma: 3-step welcome drip emails + marketing opt-out
-- ============================================================================
-- Additive only: adds columns and two indexes to users. Nothing existing is
-- altered, dropped or deleted. Run once in phpMyAdmin (select the TeachLuma
-- database > SQL tab > paste > Go), BEFORE adding the cron job for
-- cron/send-drip-emails.php.
--
-- What it does
--   * users.drip_stage: 0 = no drip email sent yet, 1/2/3 = the last step
--     sent (Day 1, Day 3, Day 7). The cron only ever moves it forward.
--   * users.drip_step1_sent_at / drip_step2_sent_at / drip_step3_sent_at:
--     when each step went out (shown on Admin > Users > a user).
--   * users.marketing_opt_out (+ marketing_opt_out_at): 1 = never send this
--     account drip or other promotional email. Transactional email
--     (password resets, payment and membership notices) is NOT affected.
--   * users.email_unsubscribe_token: the secret in each drip email's
--     one-click unsubscribe link (/unsubscribe.php?token=...). Filled in
--     below for existing accounts; new accounts get one the first time a
--     drip email is prepared for them.
--
-- Existing accounts: the cron only emails accounts created within the last
-- DRIP_MAX_SIGNUP_AGE_DAYS days (config/config.php, default 14), so older
-- accounts are never sent a "Day 1" email weeks later. To exclude EVERY
-- account that exists today (only brand-new sign-ups get the drip), also run
-- the optional UPDATE at the bottom.
--
-- Rollback (removes the feature's data):
--   ALTER TABLE users
--     DROP INDEX uq_users_unsubscribe_token, DROP INDEX idx_users_drip,
--     DROP COLUMN email_unsubscribe_token, DROP COLUMN marketing_opt_out_at,
--     DROP COLUMN marketing_opt_out, DROP COLUMN drip_step3_sent_at,
--     DROP COLUMN drip_step2_sent_at, DROP COLUMN drip_step1_sent_at,
--     DROP COLUMN drip_stage;
-- ============================================================================

ALTER TABLE users
    ADD COLUMN drip_stage TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER pro_trial_credits,
    ADD COLUMN drip_step1_sent_at DATETIME NULL AFTER drip_stage,
    ADD COLUMN drip_step2_sent_at DATETIME NULL AFTER drip_step1_sent_at,
    ADD COLUMN drip_step3_sent_at DATETIME NULL AFTER drip_step2_sent_at,
    ADD COLUMN marketing_opt_out TINYINT(1) NOT NULL DEFAULT 0 AFTER drip_step3_sent_at,
    ADD COLUMN marketing_opt_out_at DATETIME NULL AFTER marketing_opt_out,
    ADD COLUMN email_unsubscribe_token CHAR(64) NULL AFTER marketing_opt_out_at,
    ADD UNIQUE KEY uq_users_unsubscribe_token (email_unsubscribe_token),
    ADD KEY idx_users_drip (drip_stage, marketing_opt_out, created_at);

-- A unique, unguessable token for every existing account (64 hex chars).
UPDATE users
SET email_unsubscribe_token = SHA2(CONCAT(id, '-', email, '-', UUID(), '-', RAND()), 256)
WHERE email_unsubscribe_token IS NULL;

-- OPTIONAL: exclude every account that exists today from the drip, so only
-- teachers who sign up after this migration receive it. Uncomment to run.
-- UPDATE users SET drip_stage = 3 WHERE drip_stage = 0;
