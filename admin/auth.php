<?php

session_set_cookie_params([
    'lifetime' => 60*60*24*30, // 30 giorni
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Strict'
]);

session_start();

if (empty($_SESSION['admin_user'])) {
    header("Location: login.php");
    exit;
}
