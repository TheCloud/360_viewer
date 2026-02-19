<?php

$baseDir = __DIR__ . '/images';
$thumbBaseDir = __DIR__ . '/thumbnails';

if (!is_dir($thumbBaseDir)) {
    mkdir($thumbBaseDir, 0755, true);
}

function sanitize($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES);
}

function createThumbnail($sourcePath, $thumbPath, $maxWidth = 400) {

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

/* SALVATAGGIO */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $folder = basename($_POST['folder']);
    $folderPath = $baseDir . '/' . $folder;

    if (is_dir($folderPath)) {

        $meta = [
            "folder_comment" => $_POST['folder_comment'] ?? '',
            "images" => []
        ];

        if (!empty($_POST['images'])) {
            foreach ($_POST['images'] as $file => $desc) {
                $meta["images"][basename($file)] = $desc;
            }
        }

        file_put_contents(
            $folderPath . '/meta.json',
            json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }
}

$folders = array_filter(glob($baseDir . '/*'), 'is_dir');
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Meta 360</title>
<style>
body { font-family: Arial; background:#111; color:#fff; padding:20px; }
h2 { margin-top:40px; }

textarea, input[type=text] {
    width:100%;
    padding:6px;
    margin:5px 0 15px 0;
    background:#222;
    border:1px solid #444;
    color:#fff;
}

button {
    padding:8px 20px;
    background:#444;
    color:#fff;
    border:none;
    cursor:pointer;
}
button:hover { background:#666; }

.image-row {
    display:flex;
    gap:15px;
    align-items:flex-start;
    background:#1a1a1a;
    padding:10px;
    margin-bottom:10px;
}

.image-row img {
    width:200px;
    border-radius:6px;
}

.image-info {
    flex:1;
}
</style>
</head>
<body>

<h1>Gestione meta.json</h1>

<?php foreach ($folders as $folder): ?>
<?php
    $folderName = basename($folder);
    $images = glob($folder . '/*.jpg');

    $metaFile = $folder . '/meta.json';

    if (!file_exists($metaFile)) {
        $initialMeta = [
            "folder_comment" => "",
            "images" => []
        ];
        foreach ($images as $img) {
            $initialMeta["images"][basename($img)] = "";
        }
        file_put_contents(
            $metaFile,
            json_encode($initialMeta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    $meta = json_decode(file_get_contents($metaFile), true);

    $thumbFolder = $thumbBaseDir . '/' . $folderName;
    if (!is_dir($thumbFolder)) {
        mkdir($thumbFolder, 0755, true);
    }
?>

<h2><?= sanitize($folderName) ?></h2>

<form method="post">
<input type="hidden" name="folder" value="<?= sanitize($folderName) ?>">

<label>Commento cartella:</label>
<textarea name="folder_comment" rows="2"><?= sanitize($meta['folder_comment']) ?></textarea>

<?php foreach ($images as $img):

    $filename = basename($img);
    $desc = $meta['images'][$filename] ?? '';

    $thumbPath = $thumbFolder . '/' . $filename;
    $relativeThumbPath = 'thumbnails/' . $folderName . '/' . $filename;

    if (!file_exists($thumbPath) || filemtime($img) > filemtime($thumbPath)) {
        createThumbnail($img, $thumbPath, 400);
    }
?>

<div class="image-row">

    <img src="<?= $relativeThumbPath ?>">

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

<?php endforeach; ?>

</body>
</html>
