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
- [ ] Open a free resource as a guest → Download button works, no login required
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
