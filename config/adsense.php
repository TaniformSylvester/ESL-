<?php
/**
 * TeachLuma — Google AdSense Credentials
 *
 * >>> EDIT THIS FILE WITH YOUR REAL ADSENSE PUBLISHER ID <<<
 *
 * 1. Sign up at https://www.google.com/adsense and add teachluma.com as a
 *    site. Approval can take a few days.
 * 2. Once approved, go to Ads > Overview and copy your publisher ID —
 *    it looks like "pub-1234567890123456".
 * 3. Paste it below (keep the "ca-" prefix) and set ADSENSE_ENABLED to true.
 * 4. Also update ads.txt at the site root with the same publisher ID —
 *    see the instructions in that file.
 * 5. In the AdSense dashboard, go to Ads > Privacy & messaging and turn on
 *    a GDPR/CCPA consent message. This handles cookie-consent compliance
 *    automatically — no extra code needed on our side.
 *
 * Ads are automatically hidden for logged-in users with an active paid
 * membership (see includes/ads-functions.php) — only visitors and
 * free-tier members ever see ads.
 *
 * Do NOT commit a real publisher ID to a public repository.
 */

define('ADSENSE_ENABLED', false); // set to true once the value below is filled in

define('ADSENSE_PUBLISHER_ID', ''); // e.g. 'ca-pub-1234567890123456'
