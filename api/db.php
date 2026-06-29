<?php
/**
 * KeySynx — Database connection
 * Defaults match a fresh XAMPP install (MySQL on localhost,
 * user "root", no password). Change these if your setup differs.
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'keysynx');

function getDb(): mysqli {
    static $conn = null;
    if ($conn === null) {
        // PHP 8.1+ makes mysqli throw exceptions by default on query errors.
        // This codebase is written expecting the older "check the return
        // value" style (if (!$stmt->execute())) everywhere — without this,
        // ordinary, expected errors (like a duplicate username/email on
        // registration) become uncaught fatal errors instead of a clean
        // JSON error response.
        mysqli_report(MYSQLI_REPORT_OFF);
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]);
            exit;
        }
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}

/**
 * Derives a reputation tier label from points.
 * Thresholds are ours (not specified in the proposal) — tune freely.
 */
function reputationTier(float $points): string {
    if ($points >= 100) return 'Moderator';
    if ($points >= 50)  return 'Verified Contributor';
    if ($points >= 20)  return 'Trusted Analyzer';
    return 'New Contributor';
}

/**
 * Color pairing for each reputation tier, so the same tier always looks
 * the same wherever it's shown (comment feed, profile badge, profile
 * legend). Moderator gets the "gold" treatment since it's the highest
 * tier and also the one that unlocks actual moderation access.
 */
function reputationTierColor(string $tier): array {
    return match ($tier) {
        'Moderator'             => ['color' => 'var(--pending)',       'bg' => 'rgba(242,184,75,0.15)'],
        'Verified Contributor'  => ['color' => 'var(--verified)',      'bg' => 'rgba(94,230,168,0.15)'],
        'Trusted Analyzer'      => ['color' => 'var(--accent-violet)', 'bg' => 'rgba(185,163,255,0.15)'],
        default                 => ['color' => 'var(--text-dim)',      'bg' => 'rgba(255,255,255,0.06)'],
    };
}

/**
 * Shared avatar-color helper — used by partials/topbar.php, profile.php,
 * and song.php. Defined once here (guarded) since several included files
 * need it and PHP fatally errors on redeclaring a function.
 */
if (!function_exists('avatarHue')) {
    function avatarHue(string $username): int {
        return array_sum(array_map('ord', str_split($username))) % 360;
    }
}

/**
 * Song moderation access (approve/reject/delete entries): granted to
 * true admins, OR to any user who's reached 100+ reputation points
 * (the "Moderator" tier) — either path counts, per the proposal's
 * Contributor Reputation System. Managing OTHER users' roles is a
 * stricter permission — see isTrueAdmin() below, used for that.
 */
function hasModeratorAccess(?array $user): bool {
    if (!$user) return false;
    if (($user['role'] ?? '') === 'admin') return true;
    return (float) ($user['reputation_points'] ?? 0) >= 100;
}

/**
 * Role management (promoting/demoting other users) stays restricted to
 * users with the actual 'admin' role in the database — reaching 100+
 * reputation grants moderation access, not the ability to grant access
 * to others.
 */
function isTrueAdmin(?array $user): bool {
    return $user && ($user['role'] ?? '') === 'admin';
}

function jsonResponse($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function jsonError(string $message, int $status = 400): void {
    jsonResponse(['error' => $message], $status);
}
