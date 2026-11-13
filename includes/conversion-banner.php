<?php
/**
 * Sticky upgrade banner for the free interactive pages (Teacher Tools,
 * Games). A page opts in with $showConversionBanner = true before
 * including footer.php, which renders this partial.
 *
 * Who sees what:
 *   - logged out: "Create Free Account" (returns to this page after sign-up)
 *   - logged-in free account: "Upgrade to Teacher Pro"
 *   - active Pro members and admins: nothing
 *
 * Non-intrusive by design, since these pages are often on a classroom
 * projector: it sits at the bottom, the page gets matching bottom padding
 * so it never covers a tool's controls, it hides in fullscreen and print,
 * and the close button hides it for 14 days on this device. Counts come
 * from the live library, rounded down ("1,200+").
 */
require_once __DIR__ . '/membership.php';

if (is_admin() || (is_logged_in() && isMemberActive())) {
    return;
}

require_once __DIR__ . '/resource-functions.php';

$bannerLoggedIn = is_logged_in();
if ($bannerLoggedIn) {
    $bannerText = 'Using ' . SITE_NAME . ' in your classroom? Unlock ' . marketing_count(get_published_members_only_count())
        . ' members-only lesson packs, PowerPoint slides and worksheets with Teacher Pro.';
    $bannerShort = 'Unlock ' . marketing_count(get_published_members_only_count()) . ' Pro lesson packs &amp; slides.';
    $bannerCta = 'Upgrade to Teacher Pro';
    $bannerUrl = base_url('member/subscription.php');
    $bannerEvent = 'upgrade_click';
} else {
    $bannerText = 'Using ' . SITE_NAME . ' in your classroom? Unlock ' . marketing_count(get_published_resource_count())
        . ' ready-to-teach resources, including PowerPoint slides and lesson packs.';
    $bannerShort = marketing_count(get_published_resource_count()) . ' ready-to-teach lessons &amp; slides.';
    $bannerCta = 'Create Free Account';
    $bannerReturn = '/' . ltrim((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
    $bannerQuery = (string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY);
    $bannerUrl = base_url('register.php?redirect=' . urlencode($bannerReturn . ($bannerQuery !== '' ? '?' . $bannerQuery : '')));
    $bannerEvent = 'signup_click';
}
$bannerPage = basename((string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '.php');
?>
<aside class="conversion-banner" id="conversionBanner" aria-label="<?= e(SITE_NAME) ?> membership" hidden>
    <div class="container d-flex flex-nowrap align-items-center gap-2 gap-md-3">
        <span class="conversion-banner-icon" aria-hidden="true"><i class="fa-solid fa-chalkboard-user"></i></span>
        <p class="conversion-banner-text mb-0 flex-grow-1">
            <span class="d-none d-md-inline"><?= e($bannerText) ?></span>
            <span class="d-md-none"><?= $bannerShort /* built above from a number and fixed text */ ?></span>
        </p>
        <a href="<?= e($bannerUrl) ?>" class="btn btn-warning btn-sm fw-bold text-nowrap"
           data-ga-event="<?= e($bannerEvent) ?>"
           data-ga-params="<?= e(json_encode(['location' => 'tool_banner', 'page' => $bannerPage])) ?>"><?= e($bannerCta) ?></a>
        <button type="button" class="btn-close btn-close-white conversion-banner-close" aria-label="Hide this message"></button>
    </div>
</aside>
<script>
    (function () {
        var banner = document.getElementById('conversionBanner');
        var key = 'tl_tool_banner_hidden_until';
        var hiddenUntil = 0;
        try { hiddenUntil = parseInt(localStorage.getItem(key), 10) || 0; } catch (e) {}
        if (!banner || hiddenUntil > Date.now()) { return; }

        var fit = function () { document.body.style.paddingBottom = banner.hidden ? '' : banner.offsetHeight + 'px'; };
        banner.hidden = false;
        document.body.classList.add('has-conversion-banner');
        fit();
        window.addEventListener('resize', fit);

        banner.querySelector('.conversion-banner-close').addEventListener('click', function () {
            banner.hidden = true;
            document.body.classList.remove('has-conversion-banner');
            fit();
            try { localStorage.setItem(key, String(Date.now() + 14 * 24 * 3600 * 1000)); } catch (e) {}
        });
    })();
</script>
