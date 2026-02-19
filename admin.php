<?php

$baseDir = __DIR__ . '/images';
$thumbBaseDir = __DIR__ . '/thumbnails';

function sanitize($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES);
}

function createThumbnail($sourcePath, $thumbPath, $maxWidth = 800) {

    if (!extension_loaded('gd')) return false;

    $info = getimagesize($sourcePath);
    if (!$info) return false;

    list($width, $height) = $info;

    $ratio = $height / $width;
    $newWidth = $maxWidth;
    $newHeight = $maxWidth * $ratio;

    $src = imagecreatefromjpeg($sourcePath);
    if (!$src) return false;

    $thumb = imagecreatetruecolor($newWidth, $newHeight);
    imagecopyresampled($thumb, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    imagejpeg($thumb, $thumbPath, 80);

    imagedestroy($src);
    imagedestroy($thumb);

    return true;
}

$folders = array_filter(glob($baseDir . '/*'), 'is_dir');
$currentFolder = $_GET['folder'] ?? null;

/* ===================================================== */
/* LISTA CARTELLE */
/* ===================================================== */
if (!$currentFolder) {
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Admin 360</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background:#111; color:#fff; }
.card { background:#1a1a1a; border:1px solid #333; }
.card img { object-fit:cover; height:180px; }
</style>
</head>
<body class="container py-4">

<h1 class="mb-4">Gestione Cartelle 360</h1>

<div class="row g-4">

<?php foreach ($folders as $folder):

    $name = basename($folder);
    $images = glob($folder . '/*.jpg');
    $count = count($images);

    $metaFile = $folder . '/meta.json';
    $hasMeta = file_exists($metaFile);

    $folderComment = '';
    $hasDescriptions = false;

    if ($hasMeta) {
        $raw = file_get_contents($metaFile);
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        $decoded = json_decode($raw, true);

        if (is_array($decoded)) {
            $folderComment = $decoded['folder_comment'] ?? '';
            if (!empty($folderComment)) {
                $hasDescriptions = true;
            }
        }
    }

    $preview = null;
    if ($count > 0) {
        $preview = 'images/' . $name . '/' . basename($images[0]);
    }
?>

<div class="col-md-4">
    <div class="card text-white h-100">

        <?php if ($preview): ?>
            <img src="<?= $preview ?>" class="card-img-top">
        <?php endif; ?>

        <div class="card-body d-flex flex-column">

            <h5 class="card-title"><?= sanitize($name) ?></h5>

            <?php if (!$hasMeta): ?>
                <span class="badge bg-danger mb-2">
                    Nessun meta.json
                </span>

            <?php elseif (!$hasDescriptions): ?>
                <span class="badge bg-warning text-dark mb-2">
                    Meta vuoto
                </span>

            <?php else: ?>
                <p class="card-text text-secondary small">
                    <?= sanitize($folderComment) ?>
                </p>
            <?php endif; ?>

            <p class="card-text small"><?= $count ?> foto</p>

            <div class="mt-auto">

                <a href="?folder=<?= urlencode($name) ?>"
                   class="btn btn-sm <?= $hasMeta ? 'btn-outline-light' : 'btn-warning text-dark' ?>">
                    <?= $hasMeta ? '✏ Modifica' : '➕ Crea descrizioni' ?>
                </a>

                <a href="index.php?open=<?= urlencode($name) ?>"
                   target="_blank"
                   class="btn btn-sm btn-outline-secondary ms-2">
                    👁 Apri viewer
                </a>

            </div>

        </div>
    </div>
</div>

<?php endforeach; ?>

</div>
</body>
</html>
<?php
exit;
}

/* ===================================================== */
/* GESTIONE SINGOLA CARTELLA */
/* ===================================================== */

$folderName = basename($currentFolder);
$folderPath = $baseDir . '/' . $folderName;

if (!is_dir($folderPath)) {
    die("Cartella non valida");
}

$images = glob($folderPath . '/*.jpg');
$metaFile = $folderPath . '/meta.json';

$meta = [
    'folder_comment' => '',
    'images' => []
];

if (file_exists($metaFile)) {
    $raw = file_get_contents($metaFile);
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $meta = array_merge($meta, $decoded);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $meta['folder_comment'] = $_POST['folder_comment'] ?? '';

    foreach ($images as $img) {
        $filename = basename($img);
        $meta['images'][$filename] = $_POST['images'][$filename] ?? '';
    }

    file_put_contents(
        $metaFile,
        json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );

    header("Location: ?folder=" . urlencode($folderName));
    exit;
}

$thumbFolder = $thumbBaseDir . '/' . $folderName;
if (!is_dir($thumbFolder)) mkdir($thumbFolder, 0755, true);

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Admin 360 - <?= sanitize($folderName) ?></title>
<style>
body { font-family: Arial; background:#111; color:#fff; padding:20px; }
input, textarea { width:100%; padding:8px; background:#222; border:1px solid #444; color:#fff; }
button { padding:10px 20px; background:#444; color:#fff; border:none; cursor:pointer; margin-top:20px; }
.image-row { display:flex; flex-direction:column; gap:10px; margin-bottom:30px; background:#1a1a1a; padding:15px; border-radius:8px; }
.image-row img { width:600px; max-width:100%; border-radius:6px; }
.image-info { width:100%; }
a { color:#0af; text-decoration:none; }
a:hover { text-decoration:underline; }
</style>
</head>
<body>

<a href="admin.php">← Torna alla lista</a>

<h1><?= sanitize($folderName) ?></h1>

<form method="post">

<h3>Commento cartella</h3>
<textarea name="folder_comment" rows="3"><?= sanitize($meta['folder_comment']) ?></textarea>

<h3>Immagini</h3>

<?php foreach ($images as $img):

    $filename = basename($img);
    $thumbPath = $thumbFolder . '/' . $filename;
    $relativeThumb = 'thumbnails/' . $folderName . '/' . $filename;

    if (!file_exists($thumbPath) || filemtime($img) > filemtime($thumbPath)) {
        createThumbnail($img, $thumbPath, 800);
    }

    $desc = $meta['images'][$filename] ?? '';
?>

<div class="image-row">
    <img src="<?= $relativeThumb ?>">
    <div class="image-info">
        <strong><?= sanitize($filename) ?></strong>
        <input type="text"
               name="images[<?= sanitize($filename) ?>]"
               value="<?= sanitize($desc) ?>"
               placeholder="Descrizione immagine">
    </div>
</div>

<?php endforeach; ?>

<button type="submit">Salva</button>

</form>
</body>
</html>
