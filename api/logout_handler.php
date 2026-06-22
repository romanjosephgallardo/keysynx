<?php
/**
 * KeySynx — Logout handler (form POST, not AJAX)
 */

session_start();
$_SESSION = [];
session_destroy();

$redirect = $_POST['redirect'] ?? 'index.php';
header('Location: ' . $redirect);
exit;
