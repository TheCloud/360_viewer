<?php
session_start();
require_once __DIR__ . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (login($_POST['username'], $_POST['password'])) {
        header("Location: " . ($_GET['redirect'] ?? 'index.php'));
        exit;
    }

    $error = true;
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Login</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-dark text-white d-flex align-items-center justify-content-center vh-100">

<form method="post" class="bg-black p-4 rounded" style="min-width:300px;">
    <h4 class="mb-3">Accesso</h4>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">Credenziali non valide</div>
    <?php endif; ?>

<div class="mb-3 input-group">
    <span class="input-group-text"><i class="bi bi-person"></i></span>
    <input type="text"
           name="username"
           class="form-control"
           placeholder="Username"
           required>
</div>

<div class="mb-3 input-group">
    <span class="input-group-text"><i class="bi bi-lock"></i></span>
    <input type="password"
           name="password"
           class="form-control"
           placeholder="Password"
           required>
</div>
    <button class="btn btn-light w-100">Login</button>
</form>

</body>
</html>
