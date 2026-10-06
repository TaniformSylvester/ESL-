<?php
/**
 * Renders one AdSense display ad unit. Expects $adSlot to be set to one of
 * the ADSENSE_SLOT_* constants before including this file. Silently
 * renders nothing for an active Pro member, a logged-out/free visitor
 * when AdSense isn't configured, or a missing slot — see should_show_ads()
 * in includes/ads-functions.php, the single source of truth this defers to.
 */
if (!should_show_ads() || empty($adSlot)) {
    return;
}
?>
<div class="container my-2">
    <div class="ad-slot text-center">
        <p class="text-secondary small text-uppercase mb-1" style="letter-spacing:.05em;">Advertisement</p>
        <ins class="adsbygoogle"
             style="display:block"
             data-ad-client="<?= e(ADSENSE_PUBLISHER_ID) ?>"
             data-ad-slot="<?= e($adSlot) ?>"
             data-ad-format="auto"
             data-full-width-responsive="true"></ins>
        <script>(adsbygoogle = window.adsbygoogle || []).push({});</script>
    </div>
</div>
