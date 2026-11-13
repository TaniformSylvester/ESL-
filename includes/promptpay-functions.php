<?php
/**
 * "Scan to pay" with PromptPay, paid straight into the site owner's own
 * account (no payment gateway, no fees). A teacher scans the QR with any
 * Thai banking app, pays, then uploads the slip; the payment lands in
 * /admin/payments.php as 'pending' and is approved there like any other
 * manual payment (see includes/payment-functions.php).
 *
 * The QR is built from the PromptPay number in Admin > Settings using the
 * Thai QR / EMVCo payload format, with the plan's exact price embedded so
 * the banking app fills in the amount itself. If no valid PromptPay number
 * is set, an uploaded QR image (also in Admin > Settings) is shown
 * instead, and the teacher types the amount.
 */

require_once __DIR__ . '/settings-functions.php';

const PROMPTPAY_AID = 'A000000677010111';

// The installer's original default for "Payment Instructions", written for
// the old bank-transfer form ("...shown above, then submit your payment
// details below"). It doesn't fit the Scan to pay panel, so it's treated as
// blank; anything the owner actually wrote there is still shown.
const LEGACY_PAYMENT_INSTRUCTIONS = 'Please transfer the membership fee to the bank account or PromptPay number shown above, then submit your payment details below for approval.';

/**
 * Normalises a PromptPay ID to digits and works out its type:
 * 10-digit mobile number (01), 13-digit national ID / tax ID (02) or
 * 15-digit e-wallet ID (03). Returns null for anything else.
 *
 * @return array{tag: string, value: string}|null
 */
function promptpay_target(string $promptPayId): ?array
{
    $digits = preg_replace('/\D+/', '', $promptPayId) ?? '';

    if (strlen($digits) === 10 && $digits[0] === '0') {
        // Mobile: drop the leading 0, add the 66 country code, pad to 13.
        return ['tag' => '01', 'value' => str_pad('66' . substr($digits, 1), 13, '0', STR_PAD_LEFT)];
    }
    if (strlen($digits) === 13) {
        return ['tag' => '02', 'value' => $digits];
    }
    if (strlen($digits) === 15) {
        return ['tag' => '03', 'value' => $digits];
    }

    return null;
}

function promptpay_tlv(string $tag, string $value): string
{
    return $tag . str_pad((string)strlen($value), 2, '0', STR_PAD_LEFT) . $value;
}

/** CRC-16/CCITT-FALSE (poly 0x1021, init 0xFFFF), as the EMVCo QR spec requires. */
function promptpay_crc16(string $data): string
{
    $crc = 0xFFFF;
    $length = strlen($data);

    for ($i = 0; $i < $length; $i++) {
        $crc ^= ord($data[$i]) << 8;
        for ($bit = 0; $bit < 8; $bit++) {
            $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
            $crc &= 0xFFFF;
        }
    }

    return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
}

/**
 * The text to encode in a PromptPay QR. With an amount it's a one-time
 * ("dynamic", 12) code that pre-fills the amount; without, a reusable
 * ("static", 11) one. Returns null if the PromptPay ID isn't valid.
 */
function promptpay_payload(string $promptPayId, ?float $amount = null): ?string
{
    $target = promptpay_target($promptPayId);
    if ($target === null) {
        return null;
    }

    $hasAmount = $amount !== null && $amount > 0;

    $payload = promptpay_tlv('00', '01')
        . promptpay_tlv('01', $hasAmount ? '12' : '11')
        . promptpay_tlv('29', promptpay_tlv('00', PROMPTPAY_AID) . promptpay_tlv($target['tag'], $target['value']))
        . promptpay_tlv('58', 'TH')
        . promptpay_tlv('53', '764');

    if ($hasAmount) {
        $payload .= promptpay_tlv('54', number_format($amount, 2, '.', ''));
    }

    $payload .= '6304';

    return $payload . promptpay_crc16($payload);
}

/**
 * Everything the "Scan to pay" panel needs, or null when the owner hasn't
 * set up either a valid PromptPay number or a QR image (the option is then
 * hidden rather than shown half-configured).
 *
 * @return array{mode: string, promptpay_id: string, qr_image_url: string, account_name: string, instructions: string}|null
 */
function scan_to_pay_config(): ?array
{
    $promptPayId = get_setting('promptpay_number');
    $qrImage = get_setting('qr_code_image');

    if (promptpay_target($promptPayId) !== null) {
        $mode = 'generated';
    } elseif ($qrImage !== '') {
        $mode = 'image';
    } else {
        return null;
    }

    return [
        'mode'         => $mode,
        'promptpay_id' => $promptPayId,
        'qr_image_url' => $qrImage !== '' ? UPLOAD_BASE_URL . '/' . rawurlencode($qrImage) : '',
        'account_name' => get_setting('bank_account_name'),
        'instructions' => trim(get_setting('payment_instructions')) === LEGACY_PAYMENT_INSTRUCTIONS ? '' : get_setting('payment_instructions'),
    ];
}

/** Shows a PromptPay ID the way banking apps do (081-234-5678, 1-2345-67890-12-3), so a teacher can check it before paying. */
function format_promptpay_id(string $promptPayId): string
{
    $digits = preg_replace('/\D+/', '', $promptPayId) ?? '';

    if (strlen($digits) === 10) {
        return substr($digits, 0, 3) . '-' . substr($digits, 3, 3) . '-' . substr($digits, 6);
    }
    if (strlen($digits) === 13) {
        return $digits[0] . '-' . substr($digits, 1, 4) . '-' . substr($digits, 5, 5) . '-' . substr($digits, 10, 2) . '-' . $digits[12];
    }

    return $digits;
}
