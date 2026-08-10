# ESL Teacher Hub

A subscription resource platform for ESL/primary school teachers: browse lesson plans, worksheets, PowerPoints, flashcards, games and assessments, with a ฿200/month membership unlocking members-only downloads. Built as plain PHP + MySQL specifically so it can run on ordinary cPanel shared hosting (FastComet, Hostinger, or similar) — no VPS, no Node.js, no Docker required.

This README assumes no prior server-admin experience. Every step says exactly where to click.

---

## 1. Tech Stack

- PHP 8.1+ (procedural core, no framework)
- MySQL / MariaDB (via PDO, prepared statements throughout)
- HTML5 / CSS3, Bootstrap 5 and Font Awesome (loaded from CDN)
- Vanilla JavaScript (a few small scripts — no build step, no npm required to run the site)

Nothing here needs `composer install` or `npm install` to deploy. You just upload the files.

---

## 2. Project Structure

```
/                    Public pages (index.php, resources.php, resource.php, ...)
/config/             config.php (branding/pricing/etc) and database.php (DB credentials)
/includes/           Shared PHP logic (auth, membership, resources, payments, email, ...)
/member/             Logged-in teacher area (dashboard is at /dashboard.php, the rest here)
/admin/              Admin panel (separate login at /admin/login.php)
/api/                Small JSON endpoints (currently just favorites)
/install/            One-time first-admin setup — delete after use, see Section 6
/cron/               Optional scheduled cleanup script, see Section 7
/assets/             CSS/JS/images
/uploads/             Uploaded files — protected/ (resource files) and payment-proofs/ are
                     never served directly; thumbnails/ and previews/ are public images
/database.sql        Full schema + seed categories/settings/sample resources
/TESTING.md          Manual test checklist to run after deploying
```

---

## 3. Before You Start

You'll need, from FastComet (or any cPanel shared host with PHP 8.1+/MySQL):
- A hosting account with a domain pointed at it
- Access to **cPanel**
- Access to **File Manager** (in cPanel) or an FTP client (e.g. FileZilla) — FastComet's welcome email includes your FTP/cPanel login details
- Access to **phpMyAdmin** (in cPanel, under the "Databases" section)

---

## 4. Step-by-Step cPanel Deployment (FastComet)

These steps use FastComet's cPanel menu names. Any other cPanel host (including Hostinger's cPanel-based plans) works almost identically — the tool names and layout are standard cPanel, not FastComet-specific.

### Step 1 — Connect your domain
Your primary domain is normally already connected once FastComet provisions your hosting account (you chose it during signup). If you're deploying to an **additional** domain on the same account, go to cPanel → **Domains** → **Create A New Domain** and follow the wizard. Note the **document root** it creates — that's where you'll upload files in Step 4 (for your primary domain this is just `public_html`).

### Step 2 — Create a MySQL database
cPanel splits this into three small steps (the **MySQL® Database Wizard** icon in cPanel walks through the same three steps with a guided UI, if you prefer):
1. In cPanel, go to **MySQL® Databases**. Under "Create New Database," enter a name (e.g. `esl`) and click **Create Database**. cPanel automatically prefixes it with your cPanel username, e.g. `yourusername_esl` — that full prefixed name is what you'll use as `DB_NAME`.
2. Scroll to "MySQL Users" → "Add New User." Choose a username (also auto-prefixed, e.g. `yourusername_esladmin`) and a strong password. Click **Create User**.
3. Scroll to "Add User To Database," select the user and database you just created, click **Add**, then on the privileges screen check **ALL PRIVILEGES** and click **Make Changes**. Skipping this step is the most common reason the site can't connect to the database afterward.
4. The **database host** is `localhost` on FastComet shared hosting.

### Step 3 — Import the database schema
1. In cPanel, open **phpMyAdmin** (under the Databases section).
2. In the left sidebar, click the database you just created.
3. Click the **Import** tab.
4. Click **Choose File** and select `database.sql` from this project.
5. Click **Go** at the bottom. You should see a success message and a new set of tables (users, resources, memberships, etc.) plus some starter categories, default settings, and four sample resources.

### Step 4 — Upload the site files
1. In cPanel, open **File Manager**, and navigate to `public_html` (or the document root noted in Step 1, if this is an addon domain).
2. Upload every file and folder from this project into that directory. If File Manager offers a "zip and upload" option, zip the project first, upload the zip, then use File Manager's **Extract** feature — it's much faster than uploading hundreds of files one by one.
3. Double check that hidden files were included — especially every `.htaccess` file (`/`, `/config/`, `/includes/`, `/uploads/`, and its subfolders). These are what keep your database credentials and uploaded files from being directly browsable. FTP clients sometimes hide dotfiles by default — enable "show hidden files" if you're using FTP.

### Step 5 — Configure your database credentials
1. In File Manager, open `config/database.php` for editing.
2. Replace the placeholder values with what you noted in Step 2:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'yourusername_esl');
   define('DB_USER', 'yourusername_esladmin');
   define('DB_PASS', 'your-real-password');
   ```
3. Save.

### Step 6 — Configure your site URL and branding
1. Open `config/config.php` for editing.
2. Set `SITE_URL` to your real domain, no trailing slash:
   ```php
   define('SITE_URL', 'https://www.yourdomain.com');
   ```
3. While you're here, you can change `SITE_NAME`, `CONTACT_EMAIL`, `ADMIN_EMAIL`, and `SUBSCRIPTION_PRICE` — these are the central place all of those live, so changing them here updates the whole site.
4. Set `ENVIRONMENT` to `'production'` once you're done testing (see Section 9) — this hides PHP error details from visitors and shows a friendly error page instead.
5. Save.

### Step 7 — Select your PHP version
1. In cPanel, go to **MultiPHP Manager** (or **Select PHP Version**).
2. Select your domain and choose **PHP 8.1 or newer**.
3. While you're there, click through to the extension list and confirm `fileinfo`, `mbstring`, and `pdo_mysql` are checked — they're enabled by default on FastComet, but it's worth a glance.
4. If uploads larger than a few MB fail later, go to **MultiPHP INI Editor** and confirm `upload_max_filesize` and `post_max_size` are both at least `32M`.

### Step 8 — Verify upload folder permissions
The `uploads/` folder (and its subfolders `protected/`, `thumbnails/`, `previews/`, `payment-proofs/`) need to be writable by PHP so the admin panel can save uploaded files. On FastComet these are usually writable by default, but if you get an upload error later:
1. In File Manager, right-click each `uploads/` subfolder → **Permissions** (or **Change Permissions**).
2. Set to `755` (or `775` if `755` doesn't work — never use `777`).

### Step 9 — Enable SSL (HTTPS)
1. In cPanel, go to **SSL/TLS Status**, select your domain, and click **Run AutoSSL** if it isn't already showing as active. FastComet issues free AutoSSL (Let's Encrypt) certificates automatically for most accounts, often within a few hours of the domain resolving — check here first before running it manually.
2. Once it's active, open `.htaccess` at the project root and uncomment the three "Force HTTPS" lines at the bottom (remove the `#` from the start of each line). Doing this **before** SSL is active can cause a redirect loop, so wait until the certificate shows as active first.

### Step 10 — Create your first admin account
1. Visit `https://www.yourdomain.com/install/create-admin.php` in your browser.
2. Fill in your name, email, and a password (your choice — nothing is pre-set).
3. Submit. You'll see a confirmation and a link to the admin login.
4. **Immediately delete the `/install/` folder** from File Manager. The script refuses to run a second time on its own, but deleting the folder removes any doubt.

### Step 11 — Set up the optional cron job
See Section 7 below. This step is optional — the site works correctly without it.

### Step 12 — Test everything
Work through `TESTING.md` — it covers registration, login, membership, resource downloads, admin functions, and basic security checks. At minimum, verify: you can register a teacher account, log in, submit a manual payment, approve it as admin, and then download a members-only resource as that teacher.

---

## 5. Configuration Reference

| What | Where |
|---|---|
| Site name, tagline, description, contact/admin email, subscription price, currency, timezone, upload size limits, grade levels, resource types | `config/config.php` |
| Database credentials | `config/database.php` |
| Bank name/account, PromptPay number, QR code image, payment instructions | Admin panel → **Settings** (`/admin/settings.php`) — no code editing needed |
| Categories | Admin panel → **Categories** |
| Everything else (users, resources, payments) | Admin panel |

`config/config.php` is deliberately the single source of truth for branding/pricing so you never have to hunt through the codebase to rebrand the site — see the comments at the top of that file.

---

## 6. The `/install/` Folder

`install/create-admin.php` is a guarded, one-time setup form: it flatly refuses to create a second admin account once one already exists, checked on the server side (not just hidden in the UI), so it can never become a backdoor if you forget to delete it. Still — **delete the folder once you've used it**, as a matter of good hygiene.

---

## 7. Optional Cron Job

`cron/expire-memberships.php` flips memberships whose `expiry_date` has passed from `active` to `expired`, and sends a "your membership has expired" email at that moment. **This is optional** — `isMemberActive()` already re-checks the real expiry date on every single access attempt and self-heals a stale `active` row the moment anyone checks it, so download access is correctly blocked with or without this cron job running. What the cron job adds is: admin reports/stats staying accurate without needing a page visit to trigger the check, and the expiry email actually being sent.

To set it up on FastComet:
1. In cPanel, go to **Cron Jobs**.
2. Under "Common Settings," choose **Once Per Day** (or set the minute/hour fields manually — any time is fine, e.g. 2:00 AM).
3. Command:
   ```
   php /home/YOUR_CPANEL_USERNAME/public_html/cron/expire-memberships.php
   ```
   Adjust the path if you uploaded the site to an addon domain's document root instead of `public_html` directly. If running the command as shown fails, your account may need a version-specific PHP binary instead of a bare `php` — FastComet support can confirm the exact path (often something like `/usr/local/bin/php` or a path containing the PHP version number), and cPanel's Cron Jobs page will show your correct home directory path at the top of the form.

If your hosting plan only offers **URL-based** cron jobs instead of running a PHP file directly, set a random string as `CRON_SECRET` in `config/config.php` and point the cron job at:
```
https://www.yourdomain.com/cron/expire-memberships.php?token=YOUR_RANDOM_SECRET
```
Without a matching token, that URL returns `403 Forbidden` to anyone else who requests it.

---

## 8. Sample Data

`database.sql` seeds four sample resources (Numbers 1-10 Worksheet, Classroom Objects Lesson Plan, Animals PowerPoint, Present Simple Worksheet) so the site isn't empty on first load. **None of them have a real file attached yet** — visiting one shows a graceful "not currently available" message until you edit it in **Admin → Resources** and upload an actual file. Feel free to delete the sample rows once you've added your own content.

---

## 9. Development vs. Production Mode

`config/config.php` has an `ENVIRONMENT` setting:
- `'development'` — PHP errors are shown in full, useful while you're testing locally or right after deployment.
- `'production'` — errors are hidden from visitors (logged to `logs/php-error.log` instead) and a friendly error page is shown for anything unexpected.

**Set this to `'production'` before sharing the site publicly.**

---

## 10. Security Notes

- Passwords are hashed with PHP's `password_hash()` (bcrypt) — never stored or logged in plain text.
- All database queries use PDO prepared statements.
- Every state-changing form is CSRF-protected.
- Resource files live outside any publicly-browsable path in intent (`uploads/protected/`, denied at the `.htaccess` level) and are only ever served through `member/download.php`, which re-checks login + membership on every request.
- Login attempts are rate-limited and accounts lock temporarily after repeated failures; several public forms (register, contact, payment submission) are rate-limited too.
- File uploads are validated by both extension **and** actual file content (not the browser-supplied MIME type), with randomly-generated storage filenames — the original filename is kept for display only.

None of this is a substitute for keeping PHP/MySQL versions current on your hosting plan or using a strong admin password.

---

## 11. What's Not Built Yet

By design, this is a v1. The database is structured so these can be added later without redesigning anything: automatic recurring payments (Stripe/Omise/PromptPay API), coupon codes, annual plans, resource ratings/reviews/comments, an email newsletter, multiple subscription tiers, an affiliate program, download limits, certificates, a Thai-language interface, and analytics. The `payments` table already has a `gateway_reference` column and a `method` enum ready for a real payment gateway, and membership activation goes through a single function (`extend_membership()`) regardless of whether a payment was approved manually or (eventually) automatically — see `includes/payment-functions.php`.

---

## 12. Getting Help

If something doesn't work as expected, check `logs/php-error.log` first (only populated in production mode) — it will usually tell you exactly what failed and where.
