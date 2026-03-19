<?php
session_start();
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config.php';

if (!isLogged()) {
    header("Location: login.php");
    exit;
}

$baseDir = IMAGES_DIR;
$thumbDir = THUMB_DIR;

$folders = array_filter(glob($baseDir . '/*'), 'is_dir');

function generateToken($folder) {
    return hash_hmac('sha256', $folder, SECRET_KEY);
}

?>
<!DOCTYPE html>
<html>
<head>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<meta charset="UTF-8">
<title>Album</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background:#111; color:#fff; }
.card { background:#000; border:1px solid #333; color:white;}
.thumb {
    height:180px;
    background-size:cover;
    background-position:center;
}
</style>
</head>
<body>

<div class="container py-4">
<div class="d-flex justify-content-between align-items-center mb-4">

    <h2 class="mb-0">I tuoi album</h2>

    <a href="logout.php" class="btn btn-outline-light btn-sm" title="Logout">
        <i class="bi bi-power"></i>
    </a>

</div>

<div class="row g-3">

<?php foreach ($folders as $folderPath):

    $folder = basename($folderPath);

    if (!canAccess($folder)) continue;

    // prova a trovare immagine start
    $meta = file_exists($folderPath . '/meta.json')
        ? json_decode(file_get_contents($folderPath . '/meta.json'), true)
        : [];

    $start = $meta['start_image'] ?? null;
    $thumb = null;

    if ($start && file_exists("$thumbDir/$folder/$start")) {
        $thumb = "thumbnails/$folder/$start";
    } else {
        $images = glob("$folderPath/*.{jpg,jpeg,JPG,JPEG}", GLOB_BRACE);
        if ($images) {
            $thumb = "images/$folder/" . basename($images[0]);
        }
    }

    $token = generateToken($folder);
?>

<div class="col-md-4">
    <a href="index.php?open=<?= urlencode($folder) ?>&token=<?= urlencode($token) ?>"
       class="text-decoration-none text-white">

        <div class="card">
            <div class="thumb"
                 style="background-image:url('<?= $thumb ?>')">
            </div>

            <div class="card-body">
                <strong><?= $meta['folder_comment'] ?></strong>
            </div>
        </div>

    </a>
</div>

<?php endforeach; ?>

</div>
</div>

</body>
</html>
