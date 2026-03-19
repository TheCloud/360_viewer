<?php

function loadUsers() {
    $file = __DIR__ . '/users.json';
    if (!file_exists($file)) return [];
    $data = json_decode(file_get_contents($file), true);
    return $data['users'] ?? [];
}

function usersExist() {
    return count(loadUsers()) > 0;
}

function login($username, $password) {

    $users = loadUsers();

    if (!isset($users[$username])) return false;

    if (!password_verify($password, $users[$username]['password'])) {
        return false;
    }

    $_SESSION['user'] = $username;
    return true;
}

function logout() {
    unset($_SESSION['user']);
}

function isLogged() {
    return !empty($_SESSION['user']);
}

function getCurrentUser() {
    if (!isLogged()) return null;

    $users = loadUsers();
    $username = $_SESSION['user'];

    return $users[$username] ?? null;
}

function canAccess($folder) {

    $user = getCurrentUser();
    if (!$user) return false;

    $albums = $user['albums'] ?? [];

    // accesso globale
    if (in_array('*', $albums)) return true;

    // accesso specifico
    return in_array($folder, $albums);
}
