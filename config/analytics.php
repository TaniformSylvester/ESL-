<?php
/**
 * TeachLuma — Google Analytics 4 Configuration
 *
 * GA4 Measurement IDs are public by design (every page's client-side HTML
 * embeds them openly), unlike the Stripe/AdSense credentials elsewhere in
 * config/ — safe to commit directly, no secret involved.
 *
 * Scope: minimal funnel-diagnosis analytics only (see includes/header.php,
 * assets/js/analytics.js, assets/js/downloads.js). Configured
 * conservatively per the funnel-analytics decision: Google Signals and
 * Ads Personalization should be turned OFF in the GA4 property itself
 * (Admin > Data Settings > Data Collection) — a setting in the GA4
 * dashboard, not something this file controls.
 */

define('GA_ENABLED', true);
define('GA_MEASUREMENT_ID', 'G-32NKDNB7SS');
