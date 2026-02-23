<?php

require_once __DIR__ . '/../config.php';

$baseDir = IMAGES_DIR;
$thumbBaseDir = THUMB_DIR;

function generateToken($folder) {
    return hash_hmac('sha256', $folder, SECRET_KEY);
}

function sanitize($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES);
}


function deleteFolderRecursive($dir) {

    if (!is_dir($dir)) return;

    $files = scandir($dir);

    foreach ($files as $file) {
        if ($file == '.' || $file == '..') continue;

        $fullPath = $dir . '/' . $file;

        if (is_dir($fullPath)) {
            deleteFolderRecursive($fullPath);
        } else {
            unlink($fullPath);
        }
    }

    rmdir($dir);
}

/* =========================
   ELIMINAZIONE ALBUM
========================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_folder'])) {

    $folder = basename($_POST['delete_folder']);

    $imagesPath = $baseDir . '/' . $folder;
    $thumbPath  = $thumbBaseDir . '/' . $folder;

    if (is_dir($imagesPath)) {
        deleteFolderRecursive($imagesPath);
    }

    if (is_dir($thumbPath)) {
        deleteFolderRecursive($thumbPath);
    }

    header("Location: admin.php");
    exit;
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
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta charset="UTF-8">
<title>Admin 360</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background:#111; color:#fff; }
.card { background:#1a1a1a; border:1px solid #333; }
.card img {
    aspect-ratio: 2 / 1;
    object-fit: cover;
}
</style>
</head>
<body class="container py-4">

<h1 class="mb-4">Gestione Cartelle 360</h1>
<div class="mb-3">
    <a href="admin_upload.php" class="btn btn-success">
        ➕ Nuova cartella / Upload
    </a>
</div>
<div class="row g-4">

<?php foreach ($folders as $folder):

    $name = basename($folder);
    $images = glob($folder . '/*.{jpg,jpeg,JPG,JPEG}', GLOB_BRACE);
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
	$preview = IMAGES_URL .'/'. $name . '/' . basename($images[0]);
    }
?>

<div class="col-12 col-sm-6 col-lg-4">
    <div class="card text-white h-100">

        <?php if ($preview): ?>
            <img src="<?= $preview ?>" class="card-img-top img-fluid">
        <?php endif; ?>

        <div class="card-body d-flex flex-column">

<h5 class="card-title">
    <?= $folderComment ? sanitize($folderComment) : sanitize($name) ?>
</h5>

<?php if ($folderComment): ?>
    <div class="text-secondary small">
        <?= sanitize($name) ?>
    </div>
<?php endif; ?>

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

<a href="admin_upload.php?folder=<?= urlencode($name) ?>"
   class="btn btn-sm btn-outline-success ms-2">
   ➕ Aggiungi foto
</a>
<?php
$token = generateToken($name);
$token = generateToken($name);

$publicUrl = APP_SCHEME.'://' . $_SERVER['HTTP_HOST']
           . APP_BASE_URL."/index.php?open=" . urlencode($name)
           . "&token=" . $token;
?>

<div class="d-flex flex-wrap gap-2 mt-3">
<button class="btn btn-sm btn-outline-success"
        onclick="copyPublicLink(this, '<?= htmlspecialchars($publicUrl, ENT_QUOTES) ?>')">
    🔗 Link pubblico
</button>
                <a href="<?= htmlspecialchars($publicUrl, ENT_QUOTES) ?>"
                   target="_blank"
                   class="btn btn-sm btn-outline-secondary ms-2">
                    👁 Apri viewer
                </a>
<form method="post" onsubmit="return confirm('Sei sicuro di voler eliminare definitivamente questo album?');" style="display:inline;">
    <input type="hidden" name="delete_folder" value="<?= htmlspecialchars($name) ?>">
    <button type="submit" class="btn btn-sm btn-outline-danger mt-2">
        🗑 Elimina
    </button>
</form>
</div>
            </div>

        </div>
    </div>
</div>

<?php endforeach; ?>

</div>

<script>
function copyPublicLink(button, url) {

    if (navigator.clipboard && window.isSecureContext) {

        navigator.clipboard.writeText(url).then(() => {
            showCopied(button);
        }).catch(() => {
            fallbackCopy(button, url);
        });

    } else {
        fallbackCopy(button, url);
    }
}

function fallbackCopy(button, url) {

    const textarea = document.createElement("textarea");
    textarea.value = url;
    textarea.style.position = "fixed";
    textarea.style.opacity = "0";
    document.body.appendChild(textarea);

    textarea.select();
    textarea.setSelectionRange(0, 99999);

    try {
        document.execCommand("copy");
        showCopied(button);
    } catch (err) {
        alert("Copia non riuscita.\n\n" + url);
    }

    document.body.removeChild(textarea);
}

function showCopied(button) {
    const original = button.innerText;
    button.innerText = "✓ Copiato";
    setTimeout(() => {
        button.innerText = original;
    }, 1500);
}
</script>

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

$images = glob($folderPath . '/*.{jpg,jpeg,JPG,JPEG}', GLOB_BRACE);
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
.image-row img {
    width:100%;
    max-width:600px;
}
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
    $relativeThumb = THUMB_URL.'/' . $folderName . '/' . $filename;

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

        <div style="margin-top:8px;">
            <a href="admin_hotspots.php?folder=<?= urlencode($folderName) ?>&image=<?= urlencode($filename) ?>"
               style="color:#0af;">
                🟢 Modifica Hotspot
            </a>
        </div>
    </div>
</div>
<?php endforeach; ?>

<button type="submit">Salva</button>

</form>
<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        alert("Link copiato negli appunti");
    });
}
</script>
<script>
function copyPublicLink(button, url) {

    if (navigator.clipboard && window.isSecureContext) {

        navigator.clipboard.writeText(url).then(() => {
            showCopied(button);
        }).catch(() => {
            fallbackCopy(button, url);
        });

    } else {
        fallbackCopy(button, url);
    }
}

function fallbackCopy(button, url) {

    const textarea = document.createElement("textarea");
    textarea.value = url;
    textarea.style.position = "fixed";
    textarea.style.opacity = "0";
    document.body.appendChild(textarea);

    textarea.select();
    textarea.setSelectionRange(0, 99999);

    try {
        document.execCommand("copy");
        showCopied(button);
    } catch (err) {
        alert("Copia non riuscita.\n\n" + url);
    }

    document.body.removeChild(textarea);
}

function showCopied(button) {
    const original = button.innerText;
    button.innerText = "✓ Copiato";
    setTimeout(() => {
        button.innerText = original;
    }, 1500);
}
</script>
</body>
</html>
