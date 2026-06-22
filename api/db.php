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
function reputationTier(int $points): string {
    if ($points >= 100) return 'Moderator';
    if ($points >= 50)  return 'Verified Contributor';
    if ($points >= 20)  return 'Trusted Analyzer';
    return 'New Contributor';
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

function jsonResponse($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function jsonError(string $message, int $status = 400): void {
    jsonResponse(['error' => $message], $status);
}
