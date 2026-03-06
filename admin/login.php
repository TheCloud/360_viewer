<?php
// Operazioni di setup al primo avvio
require_once("first_login.php");

$usersFile  = __DIR__.'/users.json';
$tokensFile = __DIR__.'/tokens.json';

$data   = json_decode(file_get_contents($usersFile), true);
$users = $data['users'] ?? [];

if (!file_exists($tokensFile)) {
    file_put_contents($tokensFile, json_encode([], JSON_PRETTY_PRINT));
}

$tokens = json_decode(file_get_contents($tokensFile), true);

$error = false;

if ($_SERVER['REQUEST_METHOD']==='POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (isset($users[$username]) && password_verify($password,$users[$username])) {

        $token = bin2hex(random_bytes(32));

        $tokens[$token] = [
            'user' => $username,
            'hash' => $users[$username],
            'created' => time()
        ];

        file_put_contents($tokensFile,json_encode($tokens,JSON_PRETTY_PRINT));

        setcookie(
            "admin_token",
            $token,
            time()+60*60*24*30,
            "/",
            "",
            isset($_SERVER['HTTPS']),
            true
        );

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
<title>Login Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-dark text-light d-flex align-items-center justify-content-center vh-100">

<div class="card bg-secondary p-4" style="max-width:400px">

<h4 class="mb-3">Login</h4>

<?php if ($error): ?>
<div class="alert alert-danger">
<?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<form method="post">

<input
class="form-control mb-3"
name="username"
placeholder="Username"
required>

<input
type="password"
class="form-control mb-3"
name="password"
placeholder="Password"
required>

<button class="btn btn-primary w-100">
Login
</button>

</form>

</div>

</body>
</html>
