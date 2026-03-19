<?php
require_once __DIR__ . '/auth.php'; // auth admin

$usersFile = __DIR__ . '/../users.json';

if (!file_exists($usersFile)) {
    file_put_contents($usersFile, json_encode(['users' => []], JSON_PRETTY_PRINT));
}

$data = json_decode(file_get_contents($usersFile), true);
$users = $data['users'] ?? [];

/* === SCAN ALBUM === */
$imagesDir = __DIR__ . '/../images';
$allAlbums = [];

if (is_dir($imagesDir)) {
    foreach (scandir($imagesDir) as $f) {
        if ($f === '.' || $f === '..') continue;
        if (is_dir($imagesDir . '/' . $f)) {
            $allAlbums[] = $f;
        }
    }
    sort($allAlbums);
}

/* === POST === */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ADD
    if (isset($_POST['add_user'])) {

        $u = trim($_POST['username']);
        $p = password_hash($_POST['password'], PASSWORD_DEFAULT);

        if ($u && !isset($users[$u])) {
            $users[$u] = [
                'password' => $p,
                'albums' => []
            ];
        }
    }

    // DELETE
    if (isset($_POST['delete_user'])) {
        $del = $_POST['delete_user'];
        unset($users[$del]);
    }

    // UPDATE ALBUMS
    if (isset($_POST['update_albums'])) {

        $username = $_POST['username'];
        $albums = $_POST['albums'] ?? [];

        if (isset($users[$username])) {
            $users[$username]['albums'] = $albums;
        }
    }

    $data['users'] = $users;

    file_put_contents($usersFile, json_encode($data, JSON_PRETTY_PRINT));

    header("Location: admin_viewer_users.php");
    exit;
}

function getAlbumTitle($folder) {

    $metaFile = __DIR__ . '/../images/' . $folder . '/meta.json';
    if (!file_exists($metaFile)) {
        return $folder;
    }

    $meta = json_decode(file_get_contents($metaFile), true);

    if (!empty($meta['folder_comment'])) {
        return $meta['folder_comment'];
    }

    return $folder;
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Viewer Users</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<link href="/css/admin-dark.css" rel="stylesheet">
</head>

<body class="container py-4">

<div class="d-flex justify-content-between align-items-center mb-4">

<h3 class="mb-0">
    <i class="bi bi-people"></i> Utenti Viewer
</h3>
<div>
    <a href="admin.php" class="btn btn-outline-light btn-sm">
        <i class="bi bi-arrow-left"></i>
    </a>

    <a href="logout.php" class="btn btn-outline-danger btn-sm">
        <i class="bi bi-box-arrow-right"></i>
    </a>
</div>
</div>

<div class="row g-4">
<p>Un utente può vedere solo gli album assegnati oppure tutti.</br >
E' comunque possibile condividere un album singolo tramite URL privato.</p>
<!-- ADD USER -->
<div class="col-lg-4">

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
<i class="bi bi-plus-circle"></i> Crea
</button>

</form>

</div>

</div>

<!-- USERS LIST -->
<div class="col-lg-8">

<?php foreach ($users as $u => $user): ?>

<div class="card p-3 mb-3">

<div class="d-flex justify-content-between align-items-center mb-2">

<strong><?= htmlspecialchars($u) ?></strong>

<form method="post" style="margin:0">
<input type="hidden" name="delete_user" value="<?= htmlspecialchars($u) ?>">
<button class="btn btn-sm btn-outline-danger">
<i class="bi bi-trash"></i>
</button>
</form>

</div>

<!-- ALBUMS -->
<form method="post">

<input type="hidden" name="update_albums" value="1">
<input type="hidden" name="username" value="<?= htmlspecialchars($u) ?>">

<div class="mb-2">

<label class="form-label small text-secondary">Accesso album</label>

<div>

<label class="me-3">
<input type="checkbox" name="albums[]" value="*"
<?= in_array('*', $user['albums'] ?? []) ? 'checked' : '' ?>>
 Tutti
</label>

</div>

<div class="mt-2">

<?php foreach ($allAlbums as $album): ?>

<label class="album-box">

<input type="checkbox"
       name="albums[]"
       value="<?= $album ?>"
       <?= in_array($album, $user['albums'] ?? []) ? 'checked' : '' ?>>
        <?= htmlspecialchars(getAlbumTitle($album)) ?>
</label>

<?php endforeach; ?>

</div>

</div>

<button class="btn btn-sm btn-primary">
<i class="bi bi-save"></i> Salva
</button>

</form>

</div>

<?php endforeach; ?>

</div>

</div>

</body>
</html>
