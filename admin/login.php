<?php

// Operazioni di setup al primo avvio
require_once("first_login.php");

session_set_cookie_params([
    'lifetime' => 60*60*24*30, // 30 giorni
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Strict'
]);

session_start();

$usersFile = __DIR__ . '/users.json';
$users = json_decode(file_get_contents($usersFile), true);

$error = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (isset($users[$username]) && password_verify($password, $users[$username])) {

        $_SESSION['admin_user'] = $username;

        header("Location: admin.php");
        exit;
    }

    $error = true;
}
?>

<!DOCTYPE html>
<html>
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Admin Login</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

body {
    background:#111;
}

.login-card {
    max-width:400px;
}

</style>

</head>

<body class="d-flex align-items-center justify-content-center vh-100">

<div class="card login-card shadow">

<div class="card-body">

<h4 class="mb-3 text-center">Admin Login</h4>

<?php if ($error): ?>

<div class="alert alert-danger">
Credenziali non valide
</div>

<?php endif; ?>

<form method="post">

<div class="mb-3">
<input
type="text"
name="username"
class="form-control"
placeholder="Username"
required>
</div>

<div class="mb-3">
<input
type="password"
name="password"
class="form-control"
placeholder="Password"
required>
</div>

<button class="btn btn-primary w-100">
Login
</button>

</form>

</div>

</div>

</body>
</html>
