<?php
/**
 * TeachLuma — Stripe Credentials
 *
 * >>> EDIT THIS FILE WITH YOUR REAL STRIPE KEYS <<<
 *
 * Stripe is the ONLY membership payment method — see includes/stripe-
 * functions.php. Checkout offers both card and PromptPay (scan-to-pay),
 * priced in THB using config.php's PRICE_MONTHLY / PRICE_ANNUAL, and
 * membership activates automatically via stripe/webhook.php as soon as
 * Stripe confirms the payment — no manual admin approval step.
 *
 * In your Stripe Dashboard (dashboard.stripe.com):
 *   - Secret key: Developers > API keys.
 *     Start with the "test mode" key (starts with sk_test_) to try the
 *     flow safely, then switch to the live key (sk_live_...) once you're
 *     ready to accept real payments.
 *   - Webhook signing secret: Developers > Webhooks > add an endpoint
 *     pointing to https://yourdomain.com/stripe/webhook.php, listening for
 *     the "checkout.session.completed" event. The signing secret (whsec_...)
 *     is shown after you create the endpoint.
 *
 * Do NOT commit real keys to a public repository.
 */

define('STRIPE_ENABLED', false); // set to true once the keys below are filled in

define('STRIPE_SECRET_KEY', '');
define('STRIPE_PUBLISHABLE_KEY', ''); // not currently used by this app (Checkout is a server-side redirect, not Stripe.js) — kept for a possible future client-side integration
define('STRIPE_WEBHOOK_SECRET', '');
