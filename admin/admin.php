<?php

require_once __DIR__ . '/../config.php';

$currentFolder = $_GET['folder'] ?? null;

if ($currentFolder) {
    header("Location: admin_edit.php?folder=" . urlencode($currentFolder));
    exit;
}

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
    $newWidth  = $maxWidth;
    $newHeight = $maxWidth * $ratio;
    $src = imagecreatefromjpeg($sourcePath);
    if (!$src) return false;
    $thumb = imagecreatetruecolor($newWidth, $newHeight);
    imagecopyresampled(
        $thumb,
        $src,
        0, 0,
        0, 0,
        $newWidth, $newHeight,
        $width, $height
    );

    imagejpeg($thumb, $thumbPath, 80);
    imagedestroy($src);
    imagedestroy($thumb);

    return true;
}

$folders = array_filter(glob($baseDir . '/*'), 'is_dir');
// Ordina per data di modifica inversa
usort($folders, function($a, $b) {
    return filemtime($b) <=> filemtime($a);
});
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
body { background:#111; color:#fff; }
.card { background:#1a1a1a; border:1px solid #333; }
.card img {
    aspect-ratio: 16 / 9;
    object-fit: cover;
    border-bottom: 1px solid #333;
}
.pano-admin-preview {
    position: relative;
    width: 100%;
    aspect-ratio: 2 / 1;
    background-size: cover;
    background-position: center;
    border-bottom: 1px solid #333;
    transition: transform 0.4s ease, filter 0.4s ease;
    overflow: hidden;
}

.pano-admin-preview::after {
    content: "";
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at center,
        transparent 60%,
        rgba(0,0,0,0.4) 100%);
    pointer-events: none;
}

/* Hover elegante */
.pano-admin-preview:hover {
    transform: scale(1.03);
    filter: brightness(1.1);
}

/* Solo per panorami */
.pano-admin-preview.is-pano {
    background-size: 120% auto;  /* leggero crop centrale */
    background-position: center;
}

/* Overlay icona */
.pano-overlay {
    position: absolute;
    top: 12px;
    right: 12px;
    background: rgba(0,0,0,0.7);
    padding: 6px 8px;
    border-radius: 8px;
    backdrop-filter: blur(6px);
    font-size: 18px;
    color: #fff;
    box-shadow: 0 0 10px rgba(0,0,0,0.6);
}
</style>

</head>
<body class="container py-4">

<h1 class="mb-4">Gestione Cartelle 360&deg;</h1>
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
    $decoded = [];
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

    $previewImage = basename($images[0]); // fallback

    if ($hasMeta && !empty($decoded['start_image'])) {

        $start = $decoded['start_image'];

        if (file_exists($folder . '/' . $start)) {
            $previewImage = $start;
        }
    }

    $isPanorama = $decoded['panoramas'][$previewImage] ?? true;
    $preview = THUMB_URL . '/' . $name . '/' . $previewImage;
}

?>

<div class="col-12 col-sm-6 col-lg-4">
    <div class="card text-white h-100">

        <?php if ($preview): ?>
<div class="pano-admin-preview <?= $isPanorama ? 'is-pano' : '' ?>"
     style="background-image:url('<?= $preview ?>')">

    <?php if ($isPanorama): ?>
        <div class="pano-overlay">
            <i class="bi bi-globe2"></i>
        </div>
    <?php endif; ?>

</div>
        <?php endif; ?>

        <div class="card-body d-flex flex-column">

<h5 class="card-title">
    <?= $folderComment ? sanitize($folderComment) : sanitize($name) ?>
</h5>

<?php if ($folderComment): ?>
    <div class="text-secondary small">
        Cartella: <?= sanitize($name) ?>
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
                    Ultimo aggiornamento: <?= date("d-m-Y",filemtime($folder)) ?>
                </p>
            <?php endif; ?>
</p>
            <p class="card-text small"><?= $count ?> foto</p>

            <div class="mt-auto">
<div class="mt-auto pt-3">

    <?php
    $token = generateToken($name);

    $publicUrl = APP_SCHEME.'://' . $_SERVER['HTTP_HOST']
               . APP_BASE_URL."/index.php?open=" . urlencode($name)
               . "&token=" . $token;
    ?>

    <!-- RIGA 1 : PUBBLICO -->
    <div class="d-flex flex-column flex-sm-row gap-2 mb-2">

        <button class="btn btn-outline-primary btn-sm flex-fill"
                onclick="copyPublicLink(this, '<?= htmlspecialchars($publicUrl, ENT_QUOTES) ?>')">
            🔗 Copia link
        </button>

        <a href="<?= htmlspecialchars($publicUrl, ENT_QUOTES) ?>"
           target="_blank"
           class="btn btn-outline-secondary btn-sm flex-fill">
            👁 Apri viewer
        </a>

    </div>

    <!-- RIGA 2 : CRUD -->
    <div class="d-flex flex-column flex-sm-row gap-2">

        <a href="admin_upload.php?folder=<?= urlencode($name) ?>"
           class="btn btn-outline-success btn-sm flex-fill">
           ➕ Aggiungi
        </a>

        <a href="admin_edit.php?folder=<?= urlencode($name) ?>"
           class="btn btn-outline-light btn-sm flex-fill">
           ✏ Modifica
        </a>

        <form method="post"
              class="flex-fill"
              onsubmit="return confirm('Sei sicuro di voler eliminare definitivamente questo album?');">
            <input type="hidden" name="delete_folder" value="<?= htmlspecialchars($name) ?>">
            <button type="submit"
                    class="btn btn-outline-danger btn-sm w-100">
                🗑 Elimina
            </button>
        </form>

    </div>

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
