<?php
/**
 * Ad-display eligibility. should_show_ads() is the single source of truth
 * for whether AdSense should load on a given request — every other file
 * must call it rather than re-deriving the rule.
 */
require_once __DIR__ . '/membership.php';

/**
 * Ads are shown to logged-out visitors and free-tier members, never to
 * logged-in users with an active paid membership, and never at all while
 * AdSense isn't configured.
 */
function should_show_ads(): bool
{
    static $result = null;

    if ($result === null) {
        $result = ADSENSE_ENABLED
            && ADSENSE_PUBLISHER_ID !== ''
            && !(isset($_SESSION['user_id']) && isMemberActive());
    }

    return $result;
}
