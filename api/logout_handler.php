<?php
/**
 * KeySynx — Logout handler (form POST, not AJAX)
 * Clears the session and redirects back to wherever the form was
 * submitted from — EXCEPT when that's your own profile page, since
 * "your own profile" doesn't mean anything anymore once logged out
 * (it would otherwise show a broken half-empty profile.php). In that
 * one case, send the person home instead.
 */

session_start();

$redirect = $_POST['redirect'] ?? 'index.html';

// Was this your OWN profile (profile.php with no ?user_id=, or
// ?user_id= matching the account that's about to be logged out)?
// Someone else's public profile (?user_id=<other id>) should still
// redirect back there fine, since that view needs no session.
$path = strtok($redirect, '?');
$isOwnProfilePage = false;
if (basename($path) === 'profile.php') {
    parse_str((string) parse_url($redirect, PHP_URL_QUERY), $query);
    $viewedUserId = isset($query['user_id']) ? (int) $query['user_id'] : null;
    $isOwnProfilePage = $viewedUserId === null || $viewedUserId === (int) ($_SESSION['user_id'] ?? 0);
}

$_SESSION = [];
session_destroy();

header('Location: ' . ($isOwnProfilePage ? 'index.html' : $redirect));
exit;
