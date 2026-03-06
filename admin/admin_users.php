<?php

require_once __DIR__ . '/auth.php';

$usersFile = __DIR__ . '/users.json';
$data   = json_decode(file_get_contents($usersFile), true);
$users = $data['users'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['add_user'])) {

        $u = trim($_POST['username']);
        $p = password_hash($_POST['password'], PASSWORD_DEFAULT);

        if ($u && !isset($users[$u])) {
            $users[$u] = $p;
        }
    }

    if (isset($_POST['delete_user'])) {

        $del = $_POST['delete_user'];

        if ($del !== $_SESSION['admin_user']) {
            unset($users[$del]);
        }
    }
    $data['users']=$users;
    file_put_contents($usersFile, json_encode($data, JSON_PRETTY_PRINT));

    header("Location: admin_users.php");
    exit;
}

?>

<!DOCTYPE html>
<html>

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Gestione Utenti</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

body {
    background:#111;
    color:#fff;
}

.card {
    background:#1a1a1a;
}

</style>

</head>

<body class="container py-4">

<div class="d-flex justify-content-between mb-4">

<h3>Utenti Admin</h3>

<div>

<a href="admin.php" class="btn btn-outline-light btn-sm">
← Admin
</a>

<a href="logout.php" class="btn btn-outline-danger btn-sm">
Logout
</a>

</div>

</div>

<div class="row g-4">

<div class="col-lg-6">

<div class="card p-3">

<h5>Aggiungi utente</h5>

<form method="post">

<input type="hidden" name="add_user" value="1">

<div class="mb-3">
<input name="username" class="form-control" placeholder="Username" required>
</div>

<div class="mb-3">
<input name="password" type="password" class="form-control" placeholder="Password" required>
</div>

<button class="btn btn-success w-100">
Crea utente
</button>

</form>

</div>

</div>

<div class="col-lg-6">

<div class="card p-3">

<h5>Utenti esistenti</h5>

<table class="table table-dark table-striped">

<thead>
<tr>
<th>Username</th>
<th></th>
</tr>
</thead>

<tbody>

<?php foreach ($users as $u => $hash): ?>

<tr>

<td><?= htmlspecialchars($u) ?></td>

<td class="text-end">

<?php if ($u !== $_SESSION['admin_user']): ?>

<form method="post" style="display:inline">

<input type="hidden" name="delete_user" value="<?= htmlspecialchars($u) ?>">

<button class="btn btn-sm btn-danger">
Elimina
</button>

</form>

<?php else: ?>

<span class="text-secondary">corrente</span>

<?php endif; ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

</div>

</div>

</body>
</html>
