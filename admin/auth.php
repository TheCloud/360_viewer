<?php

$usersFile  = __DIR__.'/users.json';
$tokensFile = __DIR__.'/tokens.json';

if (!file_exists($tokensFile)) {
    file_put_contents($tokensFile, json_encode([], JSON_PRETTY_PRINT));
}

$token = $_COOKIE['admin_token'] ?? null;

if (!$token) {
    header("Location: login.php");
    exit;
}

$tokens = json_decode(file_get_contents($tokensFile), true);
$data   = json_decode(file_get_contents($usersFile), true);

$users = $data['users'] ?? [];

if (!isset($tokens[$token])) {
    header("Location: login.php");
    exit;
}

$session = $tokens[$token];

$user = $session['user'];
$hash = $session['hash'];

if (!isset($users[$user]) || $users[$user] !== $hash) {

    unset($tokens[$token]);
    file_put_contents($tokensFile, json_encode($tokens, JSON_PRETTY_PRINT));

    setcookie("admin_token","",time()-3600,"/");

    header("Location: login.php?revoked=1");
    exit;
}

$_SESSION['admin_user'] = $user;
