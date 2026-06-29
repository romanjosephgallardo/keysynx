<?php
/**
 * KeySynx — Verification email sender (Brevo HTTP API)
 *
 * WHY HTTP API INSTEAD OF GMAIL SMTP:
 * Free shared hosts (InfinityFree included) commonly block or throttle
 * outbound SMTP ports (25/465/587) to prevent spam abuse — this is a
 * well-documented issue for exactly this kind of feature. An HTTPS API
 * call behaves like any other outgoing web request though, so it works
 * the same way on localhost AND once this is hosted online. Same file,
 * no changes needed between environments.
 *
 * SETUP (one-time, ~5 minutes):
 * 1. Sign up free at https://www.brevo.com (no credit card needed).
 * 2. Verify a sender address: Settings -> Senders, Domains & Dedicated IPs
 *    -> Senders -> Add a sender. You can use your own Gmail here — it's
 *    just the "From" address shown to recipients, and only needs a
 *    one-click confirmation link (no app password, no 2FA setup).
 * 3. Get your API key: Settings -> SMTP & API -> API Keys -> Generate a new API key.
 * 4. Paste the verified sender email + the API key into the constants below.
 */

// ====================== FILL THESE IN ======================
define('BREVO_API_KEY', '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');
define('MAIL_SENDER_EMAIL', 'romanjosephgallardo01@gmail.com'); // must match a VERIFIED sender in Brevo
define('MAIL_SENDER_NAME', 'KeySynx');
// =============================================================

/** Stores the most recent send error for debugging (see getLastMailError()). */
$GLOBALS['__keysynx_last_mail_error'] = '';

/**
 * TEMPORARY DEBUG HELPER — returns the exact error from the last send
 * attempt. Safe to stop surfacing this in the UI once email sending is
 * confirmed working (internal API errors shouldn't be user-facing at
 * the actual presentation/defense).
 */
function getLastMailError(): string {
    return $GLOBALS['__keysynx_last_mail_error'];
}

/**
 * Sends a 6-digit verification code to $toEmail via Brevo's transactional
 * email API. Returns true on success, false on failure (caller decides
 * what to tell the user — account still gets created either way, since
 * email delivery hiccups shouldn't block account creation; resend_code_handler.php
 * lets them retry).
 */
function sendVerificationCode(string $toEmail, string $username, string $code): bool {
    $payload = json_encode([
        'sender' => ['name' => MAIL_SENDER_NAME, 'email' => MAIL_SENDER_EMAIL],
        'to' => [['email' => $toEmail, 'name' => $username]],
        'subject' => 'Your KeySynx verification code',
        'htmlContent' =>
            "Hi <b>" . htmlspecialchars($username) . "</b>,<br><br>" .
            "Your KeySynx verification code is:<br>" .
            "<div style='font-size:28px; font-weight:700; letter-spacing:4px; margin:12px 0;'>" . htmlspecialchars($code) . "</div>" .
            "This code expires in 15 minutes.<br><br>" .
            "If you didn't request this, you can safely ignore this email.",
    ]);

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json',
        'api-key: ' . BREVO_API_KEY,
        'content-type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // fail fast rather than hang until PHP's script timeout

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        $GLOBALS['__keysynx_last_mail_error'] = 'Connection error: ' . $curlError;
        error_log('KeySynx mailer error: ' . $curlError);
        return false;
    }
    if ($httpCode < 200 || $httpCode >= 300) {
        $detail = "Brevo API error (HTTP {$httpCode}): {$response}";
        $GLOBALS['__keysynx_last_mail_error'] = $detail;
        error_log('KeySynx mailer error: ' . $detail);
        return false;
    }

    $GLOBALS['__keysynx_last_mail_error'] = '';
    return true;
}

/** Generates a random 6-digit numeric code as a zero-padded string. */
function generateVerificationCode(): string {
    return sprintf('%06d', random_int(0, 999999));
}
