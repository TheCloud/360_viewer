<?php

session_start();

$tokensFile = __DIR__ . '/tokens.json';

$token = $_COOKIE['admin_token'] ?? null;

if ($token && file_exists($tokensFile)) {

    $tokens = json_decode(file_get_contents($tokensFile), true);

    if (isset($tokens[$token])) {
        unset($tokens[$token]);

        file_put_contents(
            $tokensFile,
            json_encode($tokens, JSON_PRETTY_PRINT)
        );
    }
}

// cancella cookie
setcookie('admin_token', '', time() - 3600, '/');
setcookie('admin_token', '', time() - 3600, '/', '', true, true);

// distruggi sessione
session_destroy();

header("Location: login.php");
exit;
