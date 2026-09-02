<?php
/**
 * TeachLuma — Stripe Credentials
 *
 * >>> EDIT THIS FILE WITH YOUR REAL STRIPE KEYS <<<
 *
 * In your Stripe Dashboard (dashboard.stripe.com):
 *   - Secret key / Publishable key: Developers > API keys
 *     Start with the "test mode" keys (they start with sk_test_ / pk_test_)
 *     to try the flow safely, then switch to live keys (sk_live_ / pk_live_)
 *     once you're ready to accept real payments.
 *   - Webhook signing secret: Developers > Webhooks > add an endpoint
 *     pointing to https://yourdomain.com/stripe/webhook.php, listening for
 *     the "checkout.session.completed" event. The signing secret (whsec_...)
 *     is shown after you create the endpoint.
 *
 * Do NOT commit real keys to a public repository.
 */

define('STRIPE_ENABLED', false); // set to true once the keys below are filled in

define('STRIPE_SECRET_KEY', '');
define('STRIPE_PUBLISHABLE_KEY', '');
define('STRIPE_WEBHOOK_SECRET', '');

// Card payments via Stripe offer two fixed prices: USD (for international
// cards) and THB (config.php PRICE_MONTHLY — added because some Thai-issued
// cards reject foreign-currency charges outright). Neither is a live
// conversion of the other. Bank transfer/PromptPay remain the other THB
// option for local payments, unaffected by this.
define('STRIPE_PRICE_USD', 6.99);
