<?php
/**
 * Welcome drip emails (Day 1, Day 3, Day 7 after sign-up) for Free-plan
 * teachers, plus the promotional-email opt-out they rely on.
 *
 * Sending happens only in cron/send-drip-emails.php. Every drip email has a
 * one-click unsubscribe link (/unsubscribe.php?token=...) and List-Unsubscribe
 * headers. Opting out stops promotional email only; transactional email
 * (password resets, payment and membership notices in includes/email.php)
 * never checks marketing_opt_out and keeps working.
 *
 * Needs migrations/add_email_drip.sql. Requires init.php (getDB()), and
 * loads what else it needs below.
 */
require_once __DIR__ . '/email.php';
require_once __DIR__ . '/membership.php';
require_once __DIR__ . '/currency-functions.php';
require_once __DIR__ . '/resource-functions.php';

const DRIP_STEPS = [1, 2, 3];

// -----------------------------------------------------------------------
// OPT-OUT AND UNSUBSCRIBE TOKENS
// -----------------------------------------------------------------------

/**
 * Returns the user's unsubscribe token, creating one if the account has
 * none yet (accounts created after the migration start without one). The
 * UPDATE only fills an empty token, so two simultaneous calls agree.
 */
function ensure_unsubscribe_token(int $userId): string
{
    $db = getDB();
    $db->prepare('UPDATE users SET email_unsubscribe_token = ? WHERE id = ? AND email_unsubscribe_token IS NULL')
        ->execute([bin2hex(random_bytes(32)), $userId]);

    $stmt = $db->prepare('SELECT email_unsubscribe_token FROM users WHERE id = ?');
    $stmt->execute([$userId]);

    return (string)$stmt->fetchColumn();
}

/** The account a well-formed unsubscribe token belongs to, or null. */
function find_user_by_unsubscribe_token(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }

    $stmt = getDB()->prepare(
        'SELECT id, first_name, email, marketing_opt_out, marketing_opt_out_at FROM users WHERE email_unsubscribe_token = ? LIMIT 1'
    );
    $stmt->execute([$token]);

    return $stmt->fetch() ?: null;
}

/** Opts an account out of (or back into) promotional email. Transactional email is unaffected. */
function set_marketing_opt_out(int $userId, bool $optOut): void
{
    getDB()->prepare(
        'UPDATE users SET marketing_opt_out = ?, marketing_opt_out_at = ' . ($optOut ? 'NOW()' : 'NULL') . ' WHERE id = ?'
    )->execute([$optOut ? 1 : 0, $userId]);
}

/** Whether the account opted out of promotional email; null if the drip migration hasn't been run yet. */
function get_marketing_opt_out(int $userId): ?bool
{
    try {
        $stmt = getDB()->prepare('SELECT marketing_opt_out FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $value = $stmt->fetchColumn();

        return $value === false ? null : (bool)$value;
    } catch (Throwable $e) {
        return null;
    }
}

function unsubscribe_url(string $token): string
{
    return base_url('unsubscribe.php?token=' . rawurlencode($token));
}

// -----------------------------------------------------------------------
// WHO IS DUE
// -----------------------------------------------------------------------

/**
 * Free-plan teachers due for drip $step, oldest sign-ups first.
 *
 * Due means: the previous step was sent (or, for step 1, nothing yet), the
 * step's delay since sign-up has passed, the previous drip email went out at
 * least DRIP_MIN_GAP_HOURS ago (so a missed cron run never sends two steps at
 * once), and the account is younger than DRIP_MAX_SIGNUP_AGE_DAYS. Skipped:
 * opted out, deactivated, admins, active or pending Teacher Pro, and (when
 * DRIP_SKIP_BUNDLE_BUYERS is on) bundle buyers.
 */
function get_drip_due_users(int $step, int $limit): array
{
    if (!in_array($step, DRIP_STEPS, true) || $limit <= 0) {
        return [];
    }

    $delayHours = (int)(DRIP_STEP_DELAY_HOURS[$step] ?? 0);
    $gapHours = (int)DRIP_MIN_GAP_HOURS;
    $maxAgeDays = (int)DRIP_MAX_SIGNUP_AGE_DAYS;
    $previous = $step - 1;

    $gapClause = $previous > 0
        ? "AND u.drip_step{$previous}_sent_at IS NOT NULL AND u.drip_step{$previous}_sent_at <= NOW() - INTERVAL {$gapHours} HOUR"
        : '';
    $bundleClause = DRIP_SKIP_BUNDLE_BUYERS
        ? 'AND NOT EXISTS (SELECT 1 FROM bundle_purchases bp WHERE bp.user_id = u.id)'
        : '';

    $stmt = getDB()->prepare(
        "SELECT u.id, u.first_name, u.email, u.country, u.created_at, u.pro_trial_credits
         FROM users u
         LEFT JOIN memberships m ON m.user_id = u.id
         WHERE u.drip_stage = ?
           AND u.marketing_opt_out = 0
           AND u.is_active = 1
           AND u.role = 'teacher'
           AND u.created_at <= NOW() - INTERVAL {$delayHours} HOUR
           AND u.created_at >= NOW() - INTERVAL {$maxAgeDays} DAY
           {$gapClause}
           AND NOT (
                COALESCE(m.status, '') = 'pending'
                OR (COALESCE(m.status, '') = 'active' AND COALESCE(m.expiry_date, '1000-01-01') >= CURDATE())
           )
           {$bundleClause}
         ORDER BY u.created_at ASC
         LIMIT " . (int)$limit
    );
    $stmt->execute([$previous]);

    return $stmt->fetchAll();
}

/**
 * Claims a step for one user before sending: moves drip_stage forward only
 * if it is still at the previous step and the account hasn't opted out in
 * the meantime. Returns false when another run (or an opt-out) got there
 * first, so a step can never be sent twice.
 */
function claim_drip_step(int $userId, int $step): bool
{
    $stmt = getDB()->prepare(
        "UPDATE users SET drip_stage = ?, drip_step{$step}_sent_at = NOW()
         WHERE id = ? AND drip_stage = ? AND marketing_opt_out = 0"
    );
    $stmt->execute([$step, $userId, $step - 1]);

    return $stmt->rowCount() > 0;
}

/** Undoes claim_drip_step() after a failed send, so the next run tries again. */
function release_drip_step(int $userId, int $step): void
{
    getDB()->prepare(
        "UPDATE users SET drip_stage = ?, drip_step{$step}_sent_at = NULL WHERE id = ? AND drip_stage = ?"
    )->execute([$step - 1, $userId, $step]);
}

/**
 * Sends drip $step to one due user: re-checks Pro status with
 * isMemberActive() (which also self-heals expired rows), claims the step,
 * sends, and releases the claim if mail() fails. Returns 'sent', 'skipped'
 * or 'failed'.
 */
function send_drip_step(array $user, int $step): string
{
    $userId = (int)$user['id'];

    if (isMemberActive($userId)) {
        return 'skipped';
    }

    $token = ensure_unsubscribe_token($userId);
    if ($token === '' || !claim_drip_step($userId, $step)) {
        return 'skipped';
    }

    try {
        $content = build_drip_email($step, $user, $token);
        $unsubscribe = unsubscribe_url($token);
        $sent = send_email($user['email'], $content['subject'], $content['html'], $content['text'], [
            'List-Unsubscribe'      => '<' . $unsubscribe . '>, <mailto:' . CONTACT_EMAIL . '?subject=unsubscribe>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    } catch (Throwable $e) {
        email_log_error('Drip step ' . $step . ' for user #' . $userId . ' could not be built: ' . $e->getMessage());
        $sent = false;
    }

    if (!$sent) {
        release_drip_step($userId, $step);
        return 'failed';
    }

    return 'sent';
}

/** Counts for the admin pages: how many accounts got each step, and how many opted out. */
function get_drip_stats(): array
{
    $row = getDB()->query(
        'SELECT SUM(drip_step1_sent_at IS NOT NULL) AS step1,
                SUM(drip_step2_sent_at IS NOT NULL) AS step2,
                SUM(drip_step3_sent_at IS NOT NULL) AS step3,
                SUM(marketing_opt_out = 1) AS opted_out
         FROM users'
    )->fetch() ?: [];

    return array_map('intval', $row + ['step1' => 0, 'step2' => 0, 'step3' => 0, 'opted_out' => 0]);
}

// -----------------------------------------------------------------------
// CONTENT
// -----------------------------------------------------------------------

/**
 * 'THB' for teachers whose profile country is Thailand, 'USD' for any other
 * country, null when no country is stored (the email then shows both).
 */
function drip_currency_for_country(?string $country): ?string
{
    $country = trim((string)$country);
    if ($country === '') {
        return null;
    }

    return preg_match('/^(thailand|thai|th|ประเทศไทย|ไทย)$/iu', $country) ? 'THB' : 'USD';
}

/** Teacher Pro prices for the Day 7 email, in one currency or both: ['monthly', 'annual', 'savings'] strings. */
function drip_price_parts(?string $currency): array
{
    $one = static function (string $cur): array {
        $monthly = plan_price_in('monthly', $cur);
        $annual = plan_price_in('annual', $cur);

        return [
            'monthly' => format_money($monthly, $cur),
            'annual'  => format_money($annual, $cur),
            'savings' => format_money(round($monthly * 12 - $annual, 2), $cur),
        ];
    };

    if ($currency !== null) {
        return $one($currency);
    }

    $usd = $one('USD');
    $thb = $one('THB');

    return [
        'monthly' => $usd['monthly'] . ' (' . $thb['monthly'] . ')',
        'annual'  => $usd['annual'] . ' (' . $thb['annual'] . ')',
        'savings' => $usd['savings'] . ' (' . $thb['savings'] . ')',
    ];
}

/**
 * Up to $limit popular FREE resources, Complete Lesson Packs first, then
 * other free resources to fill the list. Published and active only.
 */
function get_drip_free_highlights(int $limit = 3): array
{
    $stmt = getDB()->prepare(
        "SELECT r.title, r.slug, r.grade_level, r.resource_type, s.name AS subject_name
         FROM resources r
         INNER JOIN subjects s ON s.id = r.subject_id
         WHERE r.is_published = 1 AND r.status = 'active' AND r.is_free = 1
         ORDER BY (r.resource_type = 'Complete Lesson Pack') DESC, r.download_count DESC, r.created_at DESC
         LIMIT " . max(1, $limit)
    );
    $stmt->execute();

    return $stmt->fetchAll();
}

/** The most-downloaded members-only Complete Lesson Pack, to show what a Pro pack looks like. */
function get_drip_pro_pack_example(): ?array
{
    $stmt = getDB()->query(
        "SELECT r.title, r.slug, r.grade_level, s.name AS subject_name
         FROM resources r
         INNER JOIN subjects s ON s.id = r.subject_id
         WHERE r.is_published = 1 AND r.status = 'active' AND r.is_free = 0 AND r.resource_type = 'Complete Lesson Pack'
         ORDER BY r.download_count DESC, r.created_at DESC
         LIMIT 1"
    );

    return $stmt->fetch() ?: null;
}

/**
 * Subject, HTML and plain text for drip $step. Kept apart from sending so it
 * can be previewed or tested without sending anything. Only real data is
 * used: resources that exist, the account's actual Pro Preview balance, and
 * the configured prices.
 */
function build_drip_email(int $step, array $user, string $unsubscribeToken): array
{
    $name = trim((string)($user['first_name'] ?? '')) !== '' ? trim($user['first_name']) : 'there';
    $campaign = 'utm_source=email&utm_medium=email&utm_campaign=drip_step' . $step;
    $link = static fn(string $path): string => base_url($path . (str_contains($path, '?') ? '&' : '?') . $campaign);
    $s = drip_email_styles();

    $p = static fn(string $html): string =>
        '<p style="margin:0 0 14px;font-family:' . $s['text'] . ';font-size:16px;line-height:1.6;color:' . $s['ink'] . ';">' . $html . '</p>';
    $h2 = static fn(string $text): string =>
        '<div style="font-family:' . $s['heading'] . ';font-size:20px;font-weight:700;color:' . $s['navy'] . ';padding:10px 0 6px;">' . e($text) . '</div>';
    $button = static fn(string $url, string $label, bool $primary = true): string =>
        '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:6px 0 18px;"><tr>'
        . '<td style="border-radius:10px;background:' . ($primary ? $s['yellow'] : $s['navy']) . ';">'
        . '<a href="' . e($url) . '" style="display:inline-block;padding:13px 26px;font-family:' . $s['text'] . ';font-size:16px;font-weight:800;color:' . ($primary ? $s['navy'] : '#FFFFFF') . ';text-decoration:none;border-radius:10px;">' . e($label) . '</a>'
        . '</td></tr></table>';
    $resourceRows = static function (array $resources) use ($link, $s): array {
        $html = '';
        $text = '';
        foreach ($resources as $r) {
            $url = $link('resource.php?slug=' . rawurlencode($r['slug']));
            $meta = trim(($r['subject_name'] ?? '') . (!empty($r['grade_level']) ? ' · ' . $r['grade_level'] : '') . (!empty($r['resource_type']) ? ' · ' . $r['resource_type'] : ''), ' ·');
            $html .= '<tr><td style="padding:12px 0;border-bottom:1px solid #E8ECF8;">'
                . '<a href="' . e($url) . '" style="font-family:' . $s['text'] . ';font-size:16px;font-weight:700;color:' . $s['navy'] . ';text-decoration:none;">' . e($r['title']) . '</a>'
                . ($meta !== '' ? '<div style="font-family:' . $s['text'] . ';font-size:13px;color:' . $s['muted'] . ';padding-top:3px;">' . e($meta) . '</div>' : '')
                . '</td></tr>';
            $text .= '- ' . $r['title'] . ($meta !== '' ? ' (' . $meta . ')' : '') . "\n  " . $url . "\n";
        }

        return [$html !== '' ? '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:18px;">' . $html . '</table>' : '', $text];
    };

    if ($step === 1) {
        $highlights = get_drip_free_highlights(3);
        $allPacks = $highlights !== [] && count(array_filter($highlights, static fn(array $r): bool => $r['resource_type'] === 'Complete Lesson Pack')) === count($highlights);
        $listTitle = $allPacks ? 'Popular free Complete Lesson Packs' : 'Popular free lessons to try';
        [$listHtml, $listText] = $resourceRows($highlights);
        $gamesUrl = $link('games.php');
        $resourcesUrl = $link('resources.php?access=free');

        $subject = count($highlights) >= 3
            ? $name . ', here are 3 free lessons you can teach this week'
            : $name . ', your free lessons are ready to teach';
        $preheader = 'Ready-to-teach free lessons, plus classroom games that run right in your browser.';
        $body = $p('Hi ' . e($name) . ',')
            . $p('Thanks for joining ' . e(SITE_NAME) . '! If you are planning next week\'s lessons, here are some of the free resources other teachers download most. Each one is ready to print and teach.')
            . ($listHtml !== '' ? $h2($listTitle) . $listHtml : '')
            . $button($resourcesUrl, 'Browse free resources')
            . $h2('🎮 Classroom games, no download needed')
            . $p('Our browser games run straight from your classroom screen: open one, pick a topic and play as a class. They are perfect for warm-ups, revision and the last ten minutes before the bell.')
            . $button($gamesUrl, 'Play classroom games', false);
        $text = "Hi {$name},\n\n"
            . 'Thanks for joining ' . SITE_NAME . "! If you are planning next week's lessons, here are some of the free resources other teachers download most. Each one is ready to print and teach.\n\n"
            . ($listText !== '' ? strtoupper($listTitle) . "\n" . $listText . "\n" : '')
            . "Browse free resources: {$resourcesUrl}\n\n"
            . "CLASSROOM GAMES, NO DOWNLOAD NEEDED\n"
            . "Our browser games run straight from your classroom screen: open one, pick a topic and play as a class. Perfect for warm-ups, revision and the last ten minutes before the bell.\n"
            . "Play classroom games: {$gamesUrl}\n";
    } elseif ($step === 2) {
        $example = get_drip_pro_pack_example();
        $credits = max(0, (int)($user['pro_trial_credits'] ?? 0));
        $proUrl = $link('resources.php?access=members');
        $exampleUrl = $example ? $link('resource.php?slug=' . rawurlencode($example['slug'])) : null;
        $packItems = [
            'Editable PowerPoint slides with speaker notes',
            'Printable picture flashcards',
            'A worksheet with an answer key',
            'A quiz to check understanding',
            'A homework page',
            'An assessment rubric',
            'A teacher lesson plan with timings',
        ];
        $itemsHtml = '';
        foreach ($packItems as $item) {
            $itemsHtml .= '<tr><td style="padding:5px 0;font-family:' . $s['text'] . ';font-size:15px;color:' . $s['ink'] . ';">✅ ' . e($item) . '</td></tr>';
        }

        $subject = 'How to save 5 hours of lesson prep this week';
        $preheader = 'The difference between a single worksheet and a complete, ready-to-teach lesson.';
        $body = $p('Hi ' . e($name) . ',')
            . $p('Most of us know the feeling: the worksheet is ready, but the lesson isn\'t. You still need slides to introduce the topic, something visual for vocabulary, a way to check understanding and homework to send home. That is where the evenings go.')
            . $h2('A worksheet vs. a Complete Lesson Pack')
            . $p('A single worksheet covers one activity. A Teacher Pro <strong>Complete Lesson Pack</strong> covers the whole lesson, from the first slide to the homework:')
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#F4F6FB;border-radius:12px;margin-bottom:18px;"><tr><td style="padding:14px 20px;"><table role="presentation" cellpadding="0" cellspacing="0" border="0">' . $itemsHtml . '</table></td></tr></table>'
            . $p('Teach a few lessons a week from packs like these and it adds up to hours of prep saved. Open the pack, check the slides and teach.')
            . ($example ? $p('See one for yourself: <a href="' . e($exampleUrl) . '" style="color:' . $s['navy'] . ';font-weight:800;">' . e($example['title']) . '</a>' . (!empty($example['grade_level']) ? ' (' . e($example['grade_level']) . ')' : '') . '.') : '')
            . ($credits > 0
                ? '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#FFF1CC;border-radius:12px;margin-bottom:18px;"><tr><td style="padding:16px 20px;font-family:' . $s['text'] . ';font-size:15px;line-height:1.55;color:' . $s['ink'] . ';">'
                  . '🎁 You still have <strong>' . $credits . ' Free Pro Preview' . ($credits === 1 ? '' : 's') . '</strong>. Each one unlocks a members-only resource, so you can try a full pack in class before deciding.</td></tr></table>'
                : '')
            . $button($proUrl, 'Explore Pro lesson packs');
        $text = "Hi {$name},\n\n"
            . "Most of us know the feeling: the worksheet is ready, but the lesson isn't. You still need slides to introduce the topic, something visual for vocabulary, a way to check understanding and homework to send home. That is where the evenings go.\n\n"
            . "A WORKSHEET VS. A COMPLETE LESSON PACK\n"
            . "A single worksheet covers one activity. A Teacher Pro Complete Lesson Pack covers the whole lesson:\n"
            . implode("\n", array_map(static fn(string $i): string => '- ' . $i, $packItems)) . "\n\n"
            . "Teach a few lessons a week from packs like these and it adds up to hours of prep saved. Open the pack, check the slides and teach.\n\n"
            . ($example ? "See one for yourself: {$example['title']}\n{$exampleUrl}\n\n" : '')
            . ($credits > 0 ? "You still have {$credits} Free Pro Preview" . ($credits === 1 ? '' : 's') . ". Each one unlocks a members-only resource, so you can try a full pack in class before deciding.\n\n" : '')
            . "Explore Pro lesson packs: {$proUrl}\n";
    } else {
        $currency = drip_currency_for_country($user['country'] ?? null);
        $prices = drip_price_parts($currency);
        $annualUrl = $link('member/subscription.php?plan=annual');
        $monthlyUrl = $link('member/subscription.php?plan=monthly');
        $pricingUrl = $link('pricing.php');
        $priceBox = static fn(string $label, string $price, string $per, string $note, string $url, bool $best): string =>
            '<td class="tl-stack" width="50%" valign="top" style="padding:6px;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:' . ($best ? '#FFF1CC' : '#F4F6FB') . ';border-radius:12px;"><tr><td style="padding:18px;text-align:center;">'
            . '<div style="font-family:' . $s['text'] . ';font-size:12px;font-weight:800;letter-spacing:1px;color:' . $s['muted'] . ';text-transform:uppercase;">' . e($label) . '</div>'
            . '<div style="font-family:' . $s['heading'] . ';font-size:26px;font-weight:700;color:' . $s['navy'] . ';padding:6px 0 2px;">' . e($price) . '</div>'
            . '<div style="font-family:' . $s['text'] . ';font-size:14px;color:' . $s['ink'] . ';">' . e($per) . '</div>'
            . '<div style="font-family:' . $s['text'] . ';font-size:13px;color:' . $s['muted'] . ';padding:6px 0 10px;">' . e($note) . '</div>'
            . '<a href="' . e($url) . '" style="font-family:' . $s['text'] . ';font-size:15px;font-weight:800;color:' . $s['navy'] . ';">Choose ' . e(strtolower($label)) . ' &rarr;</a>'
            . '</td></tr></table></td>';

        $subject = 'Ready for the full library, ' . $name . '?';
        $preheader = 'Teacher Pro: every Complete Lesson Pack, from ' . $prices['monthly'] . ' a month.';
        $body = $p('Hi ' . e($name) . ',')
            . $p('You have had your ' . e(SITE_NAME) . ' account for about a week now. Thank you for being here! If our free resources have helped, Teacher Pro gives you the whole library: every members-only Complete Lesson Pack with editable slides, flashcards, quizzes, homework and rubrics, plus new packs as they are added.')
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:4px 0 14px;"><tr>'
            . $priceBox('Monthly', $prices['monthly'], 'per month', 'Try it for a month', $monthlyUrl, false)
            . $priceBox('Annual', $prices['annual'], 'per year', 'Save ' . $prices['savings'] . ' a year', $annualUrl, true)
            . '</tr></table>'
            . $p('No automatic renewal and no surprise charges: when your membership ends, your account simply returns to the free plan.')
            . $button($annualUrl, 'Upgrade to Teacher Pro')
            . $p('<a href="' . e($pricingUrl) . '" style="color:' . $s['navy'] . ';font-weight:700;">Compare plans</a>, including School and Department Passes for whole teams.');
        $text = "Hi {$name},\n\n"
            . 'You have had your ' . SITE_NAME . " account for about a week now. Thank you for being here! If our free resources have helped, Teacher Pro gives you the whole library: every members-only Complete Lesson Pack with editable slides, flashcards, quizzes, homework and rubrics, plus new packs as they are added.\n\n"
            . "MONTHLY: {$prices['monthly']} per month\n{$monthlyUrl}\n\n"
            . "ANNUAL (best value): {$prices['annual']} per year, save {$prices['savings']} a year\n{$annualUrl}\n\n"
            . "No automatic renewal and no surprise charges: when your membership ends, your account simply returns to the free plan.\n\n"
            . "Compare plans: {$pricingUrl}\n";
    }

    $text .= "\nHappy teaching,\nThe " . SITE_NAME . " team\n";

    return [
        'subject' => $subject,
        'html'    => drip_email_layout($subject, $preheader, $body, $unsubscribeToken),
        'text'    => $text . drip_email_text_footer($unsubscribeToken),
    ];
}

/** Brand colors and font stacks, matching build_welcome_drip_email() in includes/email.php. */
function drip_email_styles(): array
{
    return [
        'navy'    => '#172A6E',
        'yellow'  => '#FBB829',
        'ink'     => '#1F2433',
        'muted'   => '#5B6275',
        'heading' => "'Fredoka','Arial Rounded MT Bold','Trebuchet MS',Arial,sans-serif",
        'text'    => "'Nunito','Segoe UI',Arial,Helvetica,sans-serif",
    ];
}

/** The branded email shell (same header, sign-off and footer style as the welcome email), with the unsubscribe link. */
function drip_email_layout(string $subject, string $preheader, string $bodyHtml, string $unsubscribeToken): string
{
    $s = drip_email_styles();
    $unsubscribe = unsubscribe_url($unsubscribeToken);

    return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light">'
        . '<title>' . e($subject) . '</title>'
        . '<link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@600;700&family=Nunito:wght@400;700;800&display=swap" rel="stylesheet">'
        . '<style>@media only screen and (max-width:520px){.tl-stack{display:block!important;width:100%!important;}.tl-pad{padding-left:20px!important;padding-right:20px!important;}}</style>'
        . '</head><body style="margin:0;padding:0;background:#EEF1F8;">'
        . '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:#EEF1F8;">' . e($preheader) . '</div>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#EEF1F8;"><tr><td align="center" style="padding:24px 12px;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;background:#FFFFFF;border-radius:16px;overflow:hidden;">'
        . '<tr><td class="tl-pad" style="background:' . $s['navy'] . ';padding:24px 36px;">'
        . '<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>'
        . '<td valign="middle" style="padding-right:12px;"><img src="' . e(base_url('assets/images/apple-touch-icon.png')) . '" width="40" height="40" alt="" style="display:block;border-radius:10px;"></td>'
        . '<td valign="middle" style="font-family:' . $s['heading'] . ';font-size:24px;font-weight:700;color:#FFFFFF;">Teach<span style="color:' . $s['yellow'] . ';">Luma</span></td>'
        . '</tr></table></td></tr>'
        . '<tr><td class="tl-pad" style="padding:30px 36px 8px;">' . $bodyHtml . '</td></tr>'
        . '<tr><td class="tl-pad" style="padding:0 36px 30px;">'
        . '<p style="margin:0 0 6px;font-family:' . $s['text'] . ';font-size:16px;line-height:1.6;color:' . $s['ink'] . ';">Happy teaching,</p>'
        . '<p style="margin:0;font-family:' . $s['heading'] . ';font-size:17px;font-weight:700;color:' . $s['navy'] . ';">The ' . e(SITE_NAME) . ' team</p>'
        . '</td></tr>'
        . '<tr><td class="tl-pad" style="background:#F4F6FB;padding:20px 36px;font-family:' . $s['text'] . ';font-size:12px;line-height:1.6;color:' . $s['muted'] . ';">'
        . 'You are receiving this email because you created a free account at <a href="' . e(base_url('')) . '" style="color:' . $s['muted'] . ';">' . e(preg_replace('#^https?://#', '', SITE_URL)) . '</a>. '
        . '<a href="' . e($unsubscribe) . '" style="color:' . $s['muted'] . ';font-weight:700;">Unsubscribe from tips and offers</a> with one click. '
        . 'Account emails such as password resets and payment receipts will still reach you. '
        . 'Questions? Just reply, or write to <a href="mailto:' . e(CONTACT_EMAIL) . '" style="color:' . $s['muted'] . ';">' . e(CONTACT_EMAIL) . '</a>.'
        . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

function drip_email_text_footer(string $unsubscribeToken): string
{
    return "\n---\nYou are receiving this email because you created a free account at " . preg_replace('#^https?://#', '', SITE_URL) . ".\n"
        . 'Unsubscribe from tips and offers (one click): ' . unsubscribe_url($unsubscribeToken) . "\n"
        . "Account emails such as password resets and payment receipts will still reach you.\n";
}
