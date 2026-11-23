# Manual Testing Checklist

Run through this after deploying (or after any significant change). Check items off as you go — none of these need technical knowledge beyond clicking around the site.

## Registration

- [ ] Register with valid details → account created, redirected to login with a success message
- [ ] Register again with the same email → "already exists" error, no duplicate account created
- [ ] Register with an invalid email format (e.g. `notanemail`) → validation error, form not submitted
- [ ] Register with a weak password (e.g. `abc`) → validation error explaining the requirement
- [ ] Register with mismatched password/confirm password → validation error
- [ ] Submit the registration form 6+ times rapidly → rate-limit message after a few attempts

## Login

- [ ] Log in with correct email/password → redirected to dashboard
- [ ] Log in with wrong password → generic "incorrect email or password" (should NOT reveal whether the email exists)
- [ ] Fail login 5 times in a row → account temporarily locked, even with the correct password
- [ ] Log out → session cleared, visiting `/dashboard.php` afterward redirects to login
- [ ] Visit `/dashboard.php` while logged out → redirected to login, not shown a blank/broken page

## Password Reset

- [ ] Request a reset for a real email → generic "check your inbox" message (same message whether or not the email exists)
- [ ] Use the reset link → can set a new password
- [ ] Log in with the new password → works
- [ ] Try reusing the same reset link a second time → rejected as invalid/expired

## Membership

- [ ] New account → membership shows "Inactive" on the dashboard and subscription page
- [ ] Submit a payment → membership shows "Pending Approval"
- [ ] Admin approves the payment → membership flips to "Active" with an expiry date one month out
- [ ] Admin rejects a payment → membership reverts to "Inactive" (for a first-time payer) — but does NOT touch an already-active member's access if they were renewing early
- [ ] Manually set a membership's expiry date to the past (via Admin → Users → that user) → members-only resources become inaccessible again on the next check, without needing anyone to run the cron job

## Resources

- [ ] Browse `/resources.php` as a guest → free and members-only resources both listed, with correct badges
- [ ] Open a free resource as a guest → Download opens the "Create a free account" prompt; visiting `/member/download.php?id=…` directly redirects to `/register.php?redirect=/resource.php?slug=…`, and after signing up/logging in you land back on the resource
- [ ] Open a members-only resource as a guest → "Members Only" message with Subscribe/Login buttons, no download link
- [ ] Open a members-only resource as a logged-in but non-member (or expired) teacher → still blocked, same upsell message
- [ ] Open a members-only resource as an active member → real Download button, file downloads successfully
- [ ] Search resources by keyword → matching results only
- [ ] Filter by grade, type, category, and access (free/members) → each filters correctly, and can be combined
- [ ] Browse a page with more than 12 resources → pagination appears and works
- [ ] Favorite a resource (heart icon) while logged in → appears on `/member/favorites.php`; click again to un-favorite
- [ ] Download a resource → appears in `/member/downloads.php` with a working "Download Again" link

## Admin

- [ ] Log in at `/admin/login.php` with an admin account → reaches the admin dashboard
- [ ] Try logging into `/admin/login.php` with a valid **teacher** account's correct password → rejected with the same generic error as a wrong password
- [ ] While logged in as a teacher, visit any `/admin/*.php` page directly → 403 Forbidden, not silently allowed
- [ ] While logged out, visit any `/admin/*.php` page → redirected to `/admin/login.php` (not the public login page)
- [ ] Admin → Users: search and filter by role/membership status
- [ ] Admin → Users → a specific user: edit their profile, change their role, activate/deactivate their account, extend/reset/cancel their membership
- [ ] Try deactivating or demoting your **own** admin account → blocked with an explanation
- [ ] Admin → Categories: add, edit, and delete a category (deleting one should leave its resources intact, just uncategorized)
- [ ] Admin → Resources: add a new resource with a real file + thumbnail upload, edit it, then delete it (confirm dialog appears first)
- [ ] Admin → Payments: approve and reject payments, with a rejection reason shown to the teacher
- [ ] Admin → Settings: update the bank/PromptPay details and confirm they appear correctly on the teacher-facing Subscription page

## Security

- [ ] Submit any form (login, register, contact, resource forms) with the CSRF token altered/removed → rejected, not processed
- [ ] Try uploading a `.php` file renamed to `.pdf` as a resource file → rejected (content doesn't match the claimed type)
- [ ] Try uploading a `.php` file with its real extension anywhere a file upload exists → rejected outright
- [ ] Try requesting a protected resource file directly via its storage path (if you can find it) → blocked; the only working path is through `/member/download.php`
- [ ] Try SQL-injection-style input in any search/login field (e.g. `' OR '1'='1`) → treated as plain text, no error, no unexpected results
- [ ] Enter `<script>alert(1)</script>` into any text field that gets displayed back (name, resource title, contact message) → shown as literal text, not executed
- [ ] With `ENVIRONMENT` set to `'production'`, deliberately break something (e.g. temporarily rename a file a page depends on) → friendly error page shown, no raw PHP error or file path exposed to the visitor

## Free Download Limit & Upgrade Prompts

Turn the limit on in **Admin → Settings → Free downloads** (e.g. 2 per month) before the cap tests, and switch it back afterwards if it shouldn't stay on.

- [ ] Guest: `/member/download.php?id=…` and `/member/download-extra.php?id=…` for a free resource → redirected to `/register.php?redirect=/resource.php?slug=…`; the "Log in" link on that page keeps the same return URL
- [ ] Limit **off**: a Free-plan account downloads any number of free resources; resource page says "unlimited with your free account"; `users.free_downloads_used` stays 0
- [ ] Admin settings: limit 0 or 1001 → "Enter a whole number from 1 to 1000."; saving 2 with the switch on → saved, and an admin log entry is written
- [ ] Limit **on (2)**, new Free-plan account: resource page shows "Uses 1 of your 2 free downloads this month (2 left)."
- [ ] First download of resource A → file downloads, `free_downloads_used` = 1, a `downloads` row is added
- [ ] Download A again, and A's additional files → still free, count stays 1; the note says "You already downloaded this resource this month…"
- [ ] Download resource B → count 2. Resource C's page now shows "Monthly free downloads used" with Monthly/Annual/Compare buttons and no live download links (including additional files)
- [ ] Opening C's download URL directly → redirected back to C with the warning flash; the count stays 2
- [ ] Re-downloading A or B after the cap → still works (already downloaded this month)
- [ ] Downloading an additional file first (before the main file) counts once and adds a download-history row; the main file is then free
- [ ] Members-only resource with a Free Pro Preview credit → uses the credit, not the monthly free count
- [ ] Next month (or set `users.free_downloads_month` to last month and move that user's `downloads.downloaded_at` back 40 days in a test database) → downloads work again and the count restarts at 1
- [ ] Active Teacher Pro member → never limited, no comparison card, no post-download nudge, no ads
- [ ] Bundle buyer at the cap → resources in their bundle still download; other free resources are capped like any Free-plan account
- [ ] Dashboard and Subscription page show "x of y used" while the limit is on; pricing, register and homepage copy say "up to y a month" instead of "unlimited"
- [ ] Free-plan account clicks Download → the file downloads and a "Your download is starting" box appears with a link to `/pricing.php` (monthly and annual prices); the × closes it
- [ ] Free resource page (guest or Free plan) → "Free vs. Pro Complete Lesson Pack" card with a pricing link; when a members-only Complete Lesson Pack exists for the same subject and grade it is linked as "Fuller Pro pack for this grade"
- [ ] Switch the currency to USD → prices on the card, the nudge and the cap box all change to dollars
- [ ] Phone width (≈390px) → the comparison table fits without sideways scrolling

