-- ============================================================================
-- TeachLuma — Database Schema
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
    google_id VARCHAR(190) NULL,
    role ENUM('admin', 'teacher') NOT NULL DEFAULT 'teacher',
    school_name VARCHAR(150) NULL,
    country VARCHAR(100) NULL,
    phone VARCHAR(30) NULL,
    current_session_token VARCHAR(64) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    failed_login_attempts INT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    -- Free-plan monthly download allowance (resources.is_free = 1 only).
    -- free_downloads_month is 'YYYY-MM'; a mismatch against the current
    -- calendar month means a lazy reset is due — see get_free_download_usage()
    -- and try_consume_free_download() in includes/download-functions.php.
    free_downloads_used INT UNSIGNED NOT NULL DEFAULT 0,
    free_downloads_month CHAR(7) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    UNIQUE KEY uq_users_google_id (google_id),
    KEY idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- memberships (one row per user)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS memberships (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    status ENUM('inactive', 'pending', 'active', 'expired', 'cancelled') NOT NULL DEFAULT 'inactive',
    plan ENUM('monthly', 'annual') NULL,
    start_date DATE NULL,
    expiry_date DATE NULL,
    last_payment_id INT UNSIGNED NULL,
    expiry_reminder_sent_at DATETIME NULL,
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
    plan ENUM('monthly', 'annual') NOT NULL DEFAULT 'monthly',
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
-- subjects — ESL / Math / Science, each with its own valid grade range
-- (min_grade/max_grade are labels from config.php's GRADE_LEVELS, sliced at
-- runtime via get_subject_grade_levels() rather than duplicating the list here)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS subjects (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    min_grade VARCHAR(30) NULL,
    max_grade VARCHAR(30) NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_subjects_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- categories
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    subject_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    group_name VARCHAR(50) NOT NULL DEFAULT 'Teaching Resources',
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_slug (slug),
    KEY idx_categories_subject (subject_id),
    CONSTRAINT fk_categories_subject FOREIGN KEY (subject_id) REFERENCES subjects (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- resources
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS resources (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(220) NOT NULL,
    description TEXT NULL,
    -- Optional admin SEO overrides — leave blank to use the automatically
    -- generated title/description (see includes/seo-functions.php). Only
    -- filled in when an admin needs manual control over a specific page.
    seo_title VARCHAR(255) NULL,
    meta_description VARCHAR(300) NULL,
    resource_type ENUM(
        'Lesson Plan', 'Worksheet', 'PowerPoint', 'Flashcards',
        'Classroom Activity', 'Game', 'Test', 'Assessment', 'Poster', 'Teacher Resource'
    ) NOT NULL,
    subject_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NULL,
    grade_level VARCHAR(30) NULL,
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
    KEY idx_resources_subject (subject_id),
    KEY idx_resources_category (category_id),
    KEY idx_resources_grade (grade_level),
    KEY idx_resources_type (resource_type),
    KEY idx_resources_published (is_published),
    CONSTRAINT fk_resources_subject FOREIGN KEY (subject_id) REFERENCES subjects (id),
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
-- reviews (one row per user per resource — enforced by the unique key so a
-- teacher can only ever have one review per resource; editing it updates
-- the same row and resets it to 'pending' for re-moderation)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS reviews (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    resource_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    review_text TEXT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    helpful_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_reviews_resource_user (resource_id, user_id),
    KEY idx_reviews_resource (resource_id),
    KEY idx_reviews_status (status),
    KEY idx_reviews_rating (rating),
    CONSTRAINT fk_reviews_resource FOREIGN KEY (resource_id) REFERENCES resources (id) ON DELETE CASCADE,
    CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- review_helpful (one row per user per review they've marked helpful)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS review_helpful (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    review_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_review_helpful_review_user (review_id, user_id),
    CONSTRAINT fk_review_helpful_review FOREIGN KEY (review_id) REFERENCES reviews (id) ON DELETE CASCADE,
    CONSTRAINT fk_review_helpful_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
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

-- Subjects
INSERT INTO subjects (name, slug, min_grade, max_grade, sort_order) VALUES
    ('ESL', 'esl', 'Kindergarten', 'Grade 10', 1),
    ('Math', 'math', 'Grade 1', 'Grade 6', 2),
    ('Science', 'science', 'Grade 1', 'Grade 6', 3);

-- Categories: ESL — English Skills
INSERT INTO categories (subject_id, name, slug, group_name, sort_order) VALUES
    ((SELECT id FROM subjects WHERE slug = 'esl'), 'Phonics', 'phonics', 'English Skills', 1),
    ((SELECT id FROM subjects WHERE slug = 'esl'), 'Vocabulary', 'vocabulary', 'English Skills', 2),
    ((SELECT id FROM subjects WHERE slug = 'esl'), 'Grammar', 'grammar', 'English Skills', 3),
    ((SELECT id FROM subjects WHERE slug = 'esl'), 'Reading', 'reading', 'English Skills', 4),
    ((SELECT id FROM subjects WHERE slug = 'esl'), 'Writing', 'writing', 'English Skills', 5),
    ((SELECT id FROM subjects WHERE slug = 'esl'), 'Speaking', 'speaking', 'English Skills', 6),
    ((SELECT id FROM subjects WHERE slug = 'esl'), 'Listening', 'listening', 'English Skills', 7),
    ((SELECT id FROM subjects WHERE slug = 'esl'), 'Pronunciation', 'pronunciation', 'English Skills', 8);

-- Categories: ESL — Teaching Resources
INSERT INTO categories (subject_id, name, slug, group_name, sort_order) VALUES
    ((SELECT id FROM subjects WHERE slug = 'esl'), 'Lesson Plans', 'lesson-plans', 'Teaching Resources', 1),
    ((SELECT id FROM subjects WHERE slug = 'esl'), 'Worksheets', 'worksheets', 'Teaching Resources', 2),
    ((SELECT id FROM subjects WHERE slug = 'esl'), 'PowerPoints', 'powerpoints', 'Teaching Resources', 3),
    ((SELECT id FROM subjects WHERE slug = 'esl'), 'Flashcards', 'flashcards', 'Teaching Resources', 4),
    ((SELECT id FROM subjects WHERE slug = 'esl'), 'Games', 'games', 'Teaching Resources', 5),
    ((SELECT id FROM subjects WHERE slug = 'esl'), 'Classroom Activities', 'classroom-activities', 'Teaching Resources', 6),
    ((SELECT id FROM subjects WHERE slug = 'esl'), 'Tests', 'tests', 'Teaching Resources', 7),
    ((SELECT id FROM subjects WHERE slug = 'esl'), 'Assessments', 'assessments', 'Teaching Resources', 8);

-- Categories: Math
INSERT INTO categories (subject_id, name, slug, group_name, sort_order) VALUES
    ((SELECT id FROM subjects WHERE slug = 'math'), 'Numbers & Counting', 'numbers-and-counting', 'Math Skills', 1),
    ((SELECT id FROM subjects WHERE slug = 'math'), 'Addition & Subtraction', 'addition-and-subtraction', 'Math Skills', 2),
    ((SELECT id FROM subjects WHERE slug = 'math'), 'Multiplication & Division', 'multiplication-and-division', 'Math Skills', 3),
    ((SELECT id FROM subjects WHERE slug = 'math'), 'Fractions & Decimals', 'fractions-and-decimals', 'Math Skills', 4),
    ((SELECT id FROM subjects WHERE slug = 'math'), 'Geometry & Shapes', 'geometry-and-shapes', 'Math Skills', 5),
    ((SELECT id FROM subjects WHERE slug = 'math'), 'Measurement', 'measurement', 'Math Skills', 6),
    ((SELECT id FROM subjects WHERE slug = 'math'), 'Time & Money', 'time-and-money', 'Math Skills', 7),
    ((SELECT id FROM subjects WHERE slug = 'math'), 'Word Problems', 'word-problems', 'Math Skills', 8),
    ((SELECT id FROM subjects WHERE slug = 'math'), 'Data & Graphs', 'data-and-graphs', 'Math Skills', 9),
    ((SELECT id FROM subjects WHERE slug = 'math'), 'Patterns & Algebra Basics', 'patterns-and-algebra-basics', 'Math Skills', 10);

-- Categories: Science
INSERT INTO categories (subject_id, name, slug, group_name, sort_order) VALUES
    ((SELECT id FROM subjects WHERE slug = 'science'), 'Living Things', 'living-things', 'Science Skills', 1),
    ((SELECT id FROM subjects WHERE slug = 'science'), 'Human Body', 'human-body', 'Science Skills', 2),
    ((SELECT id FROM subjects WHERE slug = 'science'), 'Plants', 'plants', 'Science Skills', 3),
    ((SELECT id FROM subjects WHERE slug = 'science'), 'Animals & Habitats', 'animals-and-habitats', 'Science Skills', 4),
    ((SELECT id FROM subjects WHERE slug = 'science'), 'Materials & Properties', 'materials-and-properties', 'Science Skills', 5),
    ((SELECT id FROM subjects WHERE slug = 'science'), 'Forces & Energy', 'forces-and-energy', 'Science Skills', 6),
    ((SELECT id FROM subjects WHERE slug = 'science'), 'Earth & Space', 'earth-and-space', 'Science Skills', 7),
    ((SELECT id FROM subjects WHERE slug = 'science'), 'Weather & Seasons', 'weather-and-seasons', 'Science Skills', 8),
    ((SELECT id FROM subjects WHERE slug = 'science'), 'Simple Experiments', 'simple-experiments', 'Science Skills', 9),
    ((SELECT id FROM subjects WHERE slug = 'science'), 'Environment & Conservation', 'environment-and-conservation', 'Science Skills', 10);

-- Default settings. Bank/PromptPay fields are intentionally left blank —
-- fill these in later via /admin/settings.php. Do NOT put real account
-- details directly into this SQL file if it will be shared or committed publicly.
INSERT INTO settings (setting_key, setting_value) VALUES
    ('site_name', 'TeachLuma'),
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
INSERT INTO resources (title, slug, description, resource_type, subject_id, category_id, grade_level, topic, is_free, is_published) VALUES
    ('Numbers 1-10 Worksheet', 'numbers-1-10-worksheet', 'A simple worksheet practicing numbers one through ten.', 'Worksheet', (SELECT id FROM subjects WHERE slug = 'esl'), (SELECT id FROM categories WHERE slug = 'vocabulary'), 'Grade 1', 'Numbers', 1, 1),
    ('Classroom Objects Lesson Plan', 'classroom-objects-lesson-plan', 'A full lesson plan teaching classroom object vocabulary.', 'Lesson Plan', (SELECT id FROM subjects WHERE slug = 'esl'), (SELECT id FROM categories WHERE slug = 'lesson-plans'), 'Grade 1', 'Classroom Objects', 0, 1),
    ('Animals PowerPoint', 'animals-powerpoint', 'An engaging slideshow introducing animal vocabulary.', 'PowerPoint', (SELECT id FROM subjects WHERE slug = 'esl'), (SELECT id FROM categories WHERE slug = 'powerpoints'), 'Grade 2', 'Animals', 0, 1),
    ('Present Simple Worksheet', 'present-simple-worksheet', 'Practice exercises for the present simple tense.', 'Worksheet', (SELECT id FROM subjects WHERE slug = 'esl'), (SELECT id FROM categories WHERE slug = 'grammar'), 'Grade 3', 'Present Simple', 0, 1);

-- ----------------------------------------------------------------------------
-- FIRST ADMIN ACCOUNT
-- Do NOT insert a default admin with a known password into a live database.
-- Instead, after importing this file, visit /install/create-admin.php on
-- your site to create your admin account through a form (it refuses to
-- run again once an admin exists) — then delete the /install/ folder.
-- ----------------------------------------------------------------------------
