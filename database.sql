-- ============================================================================
-- ESL Teacher Hub — Database Schema
-- Import this file through phpMyAdmin (or `mysql -u user -p dbname < database.sql`)
-- into the empty database you created in Hostinger hPanel.
-- Engine: InnoDB, Charset: utf8mb4 (full Unicode + emoji support)
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- users
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'teacher') NOT NULL DEFAULT 'teacher',
    school_name VARCHAR(150) NULL,
    country VARCHAR(100) NULL,
    phone VARCHAR(30) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    failed_login_attempts INT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- memberships (one row per user)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS memberships (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    status ENUM('inactive', 'pending', 'active', 'expired', 'cancelled') NOT NULL DEFAULT 'inactive',
    start_date DATE NULL,
    expiry_date DATE NULL,
    last_payment_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_memberships_user (user_id),
    KEY idx_memberships_status (status),
    KEY idx_memberships_expiry (expiry_date),
    CONSTRAINT fk_memberships_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- payments (manual submissions today; gateway-ready columns for later)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS payments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    currency VARCHAR(10) NOT NULL DEFAULT 'THB',
    method ENUM('bank_transfer', 'promptpay', 'manual_other', 'stripe', 'omise') NOT NULL DEFAULT 'bank_transfer',
    reference_number VARCHAR(150) NULL,
    payment_date DATE NULL,
    screenshot_path VARCHAR(255) NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    admin_note VARCHAR(255) NULL,
    reviewed_by INT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    gateway_reference VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_payments_user (user_id),
    KEY idx_payments_status (status),
    CONSTRAINT fk_payments_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_payments_reviewer FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE memberships
    ADD CONSTRAINT fk_memberships_last_payment FOREIGN KEY (last_payment_id) REFERENCES payments (id) ON DELETE SET NULL;

-- ----------------------------------------------------------------------------
-- categories
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    group_name VARCHAR(50) NOT NULL DEFAULT 'Teaching Resources',
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- resources
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS resources (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(220) NOT NULL,
    description TEXT NULL,
    resource_type ENUM(
        'Lesson Plan', 'Worksheet', 'PowerPoint', 'Flashcards',
        'Classroom Activity', 'Game', 'Test', 'Assessment', 'Poster', 'Teacher Resource'
    ) NOT NULL,
    category_id INT UNSIGNED NULL,
    grade_level VARCHAR(30) NULL,
    subject VARCHAR(100) NULL,
    topic VARCHAR(150) NULL,
    thumbnail VARCHAR(255) NULL,
    preview_image VARCHAR(255) NULL,
    file_path VARCHAR(255) NULL,
    file_name VARCHAR(255) NULL,
    file_size INT UNSIGNED NULL,
    file_type VARCHAR(20) NULL,
    is_free TINYINT(1) NOT NULL DEFAULT 0,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    download_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_resources_slug (slug),
    KEY idx_resources_category (category_id),
    KEY idx_resources_grade (grade_level),
    KEY idx_resources_type (resource_type),
    KEY idx_resources_published (is_published),
    CONSTRAINT fk_resources_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- favorites
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS favorites (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    resource_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_favorites_user_resource (user_id, resource_id),
    KEY idx_favorites_resource (resource_id),
    CONSTRAINT fk_favorites_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_favorites_resource FOREIGN KEY (resource_id) REFERENCES resources (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- downloads (audit trail; user_id nullable to allow free/guest downloads)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS downloads (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NULL,
    resource_id INT UNSIGNED NOT NULL,
    downloaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) NULL,
    PRIMARY KEY (id),
    KEY idx_downloads_resource (resource_id),
    KEY idx_downloads_user (user_id),
    CONSTRAINT fk_downloads_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_downloads_resource FOREIGN KEY (resource_id) REFERENCES resources (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- password_resets (tokens are stored hashed, never in plain text)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS password_resets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_password_resets_user (user_id),
    CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- settings (key/value store for admin-configurable branding & payment info)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_settings_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- contact_messages
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS contact_messages (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL,
    subject VARCHAR(200) NULL,
    message TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- admin_logs
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_logs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_id INT UNSIGNED NULL,
    action VARCHAR(150) NOT NULL,
    details TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_admin_logs_admin (admin_id),
    CONSTRAINT fk_admin_logs_admin FOREIGN KEY (admin_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- SEED DATA
-- ============================================================================

-- Categories: English Skills
INSERT INTO categories (name, slug, group_name, sort_order) VALUES
    ('Phonics', 'phonics', 'English Skills', 1),
    ('Vocabulary', 'vocabulary', 'English Skills', 2),
    ('Grammar', 'grammar', 'English Skills', 3),
    ('Reading', 'reading', 'English Skills', 4),
    ('Writing', 'writing', 'English Skills', 5),
    ('Speaking', 'speaking', 'English Skills', 6),
    ('Listening', 'listening', 'English Skills', 7),
    ('Pronunciation', 'pronunciation', 'English Skills', 8);

-- Categories: Teaching Resources
INSERT INTO categories (name, slug, group_name, sort_order) VALUES
    ('Lesson Plans', 'lesson-plans', 'Teaching Resources', 1),
    ('Worksheets', 'worksheets', 'Teaching Resources', 2),
    ('PowerPoints', 'powerpoints', 'Teaching Resources', 3),
    ('Flashcards', 'flashcards', 'Teaching Resources', 4),
    ('Games', 'games', 'Teaching Resources', 5),
    ('Classroom Activities', 'classroom-activities', 'Teaching Resources', 6),
    ('Tests', 'tests', 'Teaching Resources', 7),
    ('Assessments', 'assessments', 'Teaching Resources', 8);

-- Default settings. Bank/PromptPay fields are intentionally left blank —
-- fill these in later via /admin/settings.php. Do NOT put real account
-- details directly into this SQL file if it will be shared or committed publicly.
INSERT INTO settings (setting_key, setting_value) VALUES
    ('site_name', 'ESL Teacher Hub'),
    ('site_tagline', 'Save Time. Teach Better.'),
    ('subscription_price', '200'),
    ('contact_email', 'contact@example.com'),
    ('bank_name', ''),
    ('bank_account_name', ''),
    ('bank_account_number', ''),
    ('promptpay_number', ''),
    ('qr_code_image', ''),
    ('payment_instructions', 'Please transfer the membership fee to the bank account or PromptPay number shown above, then submit your payment details below for approval.'),
    ('logo_path', ''),
    ('favicon_path', ''),
    ('social_facebook', ''),
    ('social_line', ''),
    ('footer_text', 'Helping ESL teachers save time with ready-to-use classroom resources.');

-- ----------------------------------------------------------------------------
-- SAMPLE / DEMO RESOURCES
-- A few demo resources so the site isn't empty on first install. These
-- have no file attached yet (file_path is NULL) — visiting one shows a
-- "not currently available" message until you edit it in Admin > Resources
-- and upload a real file. Feel free to delete these once you add your own.
-- ----------------------------------------------------------------------------
INSERT INTO resources (title, slug, description, resource_type, category_id, grade_level, subject, topic, is_free, is_published) VALUES
    ('Numbers 1-10 Worksheet', 'numbers-1-10-worksheet', 'A simple worksheet practicing numbers one through ten.', 'Worksheet', (SELECT id FROM categories WHERE slug = 'vocabulary'), 'Grade 1', 'Vocabulary', 'Numbers', 1, 1),
    ('Classroom Objects Lesson Plan', 'classroom-objects-lesson-plan', 'A full lesson plan teaching classroom object vocabulary.', 'Lesson Plan', (SELECT id FROM categories WHERE slug = 'lesson-plans'), 'Grade 1', 'Vocabulary', 'Classroom Objects', 0, 1),
    ('Animals PowerPoint', 'animals-powerpoint', 'An engaging slideshow introducing animal vocabulary.', 'PowerPoint', (SELECT id FROM categories WHERE slug = 'powerpoints'), 'Grade 2', 'Vocabulary', 'Animals', 0, 1),
    ('Present Simple Worksheet', 'present-simple-worksheet', 'Practice exercises for the present simple tense.', 'Worksheet', (SELECT id FROM categories WHERE slug = 'grammar'), 'Grade 3', 'Grammar', 'Present Simple', 0, 1);

-- ----------------------------------------------------------------------------
-- FIRST ADMIN ACCOUNT
-- Do NOT insert a default admin with a known password into a live database.
-- Instead, after importing this file, visit /install/create-admin.php on
-- your site to create your admin account through a form (it refuses to
-- run again once an admin exists) — then delete the /install/ folder.
-- ----------------------------------------------------------------------------
