<?php

$htaccess = __DIR__ . '/.htaccess';

if (!file_exists($htaccess)) {
file_put_contents(
    $htaccess,
'<FilesMatch "\.(json)$">
    Require all denied
</FilesMatch>

<FilesMatch "\.(json)$">
    Order allow,deny
    Deny from all
</FilesMatch>'
);
}

$usersFile = __DIR__ . '/users.json';

if (!file_exists($usersFile)) {
        $default = [
            "users" => [
                "admin" => password_hash("password", PASSWORD_DEFAULT)
            ]
        ];
        file_put_contents(
            $usersFile,
            json_encode($default, JSON_PRETTY_PRINT)
        );

    // inizializza tokens
    $tokensFile = __DIR__ . '/tokens.json';
    if (!file_exists($tokensFile)) {
        file_put_contents($tokensFile, json_encode([], JSON_PRETTY_PRINT));
    }

echo <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Admin inizializzato</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-dark text-light d-flex align-items-center justify-content-center vh-100">

<div class="card border-secondary p-4" style="max-width:500px">

<h4 class="mb-3">Prima inizializzazione</h4>

<p>È stato creato automaticamente il file utenti con l'utente:</p>

<ul>
<li><strong>username:</strong> admin</li>
<li><strong>password:</strong> password</li>
</ul>

<p class="mb-3">
Accedi, crea un altro utente ed elimina admin.
</p>

<a href="admin_users.php" class="btn btn-primary">
Gestione utenti
</a>

</div>

</body>
</html>
HTML;

exit;
}
?>
