<?php

require_once __DIR__ . '/../config.php';

$baseDir      = IMAGES_DIR;
$thumbBaseDir = THUMB_DIR;

function sanitize($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES);
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
        $thumb, $src,
        0,0,0,0,
        $newWidth,$newHeight,
        $width,$height
    );

    imagejpeg($thumb, $thumbPath, 80);

    imagedestroy($src);
    imagedestroy($thumb);

    return true;
}

$currentFolder = $_GET['folder'] ?? null;

if (!$currentFolder) {
    header("Location: admin.php");
    exit;
}

$folderName = basename($currentFolder);
$folderPath = $baseDir . '/' . $folderName;

if (!is_dir($folderPath)) {
    die("Cartella non valida");
}

$images   = glob($folderPath . '/*.{jpg,jpeg,JPG,JPEG}', GLOB_BRACE);
$metaFile = $folderPath . '/meta.json';

$meta = [
    'folder_comment' => '',
    'start_image'    => null,
    'images'         => [],
    'panoramas'      => [],
    'images_meta'    => []
];

if (file_exists($metaFile)) {
    $raw = file_get_contents($metaFile);
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $meta = array_merge($meta, $decoded);

if (!isset($meta['images'])) {
    $meta['images'] = [];
}
    }
}

/* =====================================================
   AUTO-DETECT PANORAMA + EXIF DATA (LAZY)
===================================================== */

if (!isset($meta['panoramas'])) {
    $meta['panoramas'] = [];
}

if (!isset($meta['images_meta'])) {
    $meta['images_meta'] = [];
}

$needsSave = false;

foreach ($images as $img) {

    $filename = basename($img);

    /* PANORAMA DETECTION */
    if (!isset($meta['panoramas'][$filename])) {

        $is360 = false;

        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data($img);
            if ($exif !== false) {
                if (!empty($exif['UsePanoramaViewer']) && $exif['UsePanoramaViewer'] == 1) {
                    $is360 = true;
                }
            }
        }

        if (!$is360) {
            $raw = @file_get_contents($img);
            if ($raw !== false) {
                if (
                    strpos($raw, 'GPano:ProjectionType') !== false &&
                    strpos($raw, 'equirectangular') !== false
                ) {
                    $is360 = true;
                }
                if (strpos($raw, 'FullPanoWidthPixels') !== false) {
                    $is360 = true;
                }
            }
        }

        if (!$is360) {
            $size = @getimagesize($img);
            if ($size) {
                $ratio = $size[0] / $size[1];
                if ($ratio > 1.95 && $ratio < 2.05) {
                    $is360 = true;
                }
            }
        }

        $meta['panoramas'][$filename] = $is360;
        $needsSave = true;
    }

    /* EXIF DATE */
    if (!isset($meta['images_meta'][$filename])) {

        $timestamp = null;
        $datetime  = null;

        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data($img);
            if ($exif !== false && !empty($exif['DateTimeOriginal'])) {

                $raw = $exif['DateTimeOriginal'];
                $formatted = str_replace(':', '-', substr($raw, 0, 10)) . substr($raw, 10);

                $timestamp = strtotime($formatted);
                $datetime  = date('Y-m-d H:i:s', $timestamp);
            }
        }

        if (!$timestamp) {
            $timestamp = filemtime($img);
            $datetime  = date('Y-m-d H:i:s', $timestamp);
        }

        $meta['images_meta'][$filename] = [
            'timestamp' => $timestamp,
            'datetime'  => $datetime
        ];

        $needsSave = true;
    }
}

if ($needsSave) {
    file_put_contents(
        $metaFile,
        json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
}

/* DELETE IMAGE */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_image'])) {

    $imageToDelete = basename($_POST['delete_image']);

    $imagePath = $folderPath . '/' . $imageToDelete;
    $thumbPath = THUMB_DIR . '/' . $folderName . '/' . $imageToDelete;

    if (file_exists($imagePath)) unlink($imagePath);
    if (file_exists($thumbPath)) unlink($thumbPath);

    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

/* SAVE META */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_image'])) {

    $meta['folder_comment'] = $_POST['folder_comment'] ?? '';
    $meta['start_image']    = $_POST['start_image'] ?? null;

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

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.css"/>
<script src="https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.js"></script>

<style>
body { background:#111; color:#fff; }

.card {
    background:#1a1a1a;
    border:1px solid #333;
}

.preview-360 {
    width:100%;
    aspect-ratio:2 / 1;
    background:#000;
    border-bottom:1px solid #333;
}

.form-control, textarea {
    background:#111;
    border:1px solid #333;
    color:#fff;
}

.form-control:focus {
    background:#111;
    color:#fff;
    border-color:#666;
    box-shadow:none;
}

.start-radio:checked {
    accent-color:#28a745;
}

.meta-box {
    background:#181818;
    border:1px solid #333;
    border-radius:8px;
    padding:20px;
}
</style>
</head>

<body class="container py-4">

<div class="mb-4">
    <a href="admin.php" class="btn btn-outline-light btn-sm">
        ← Torna alla lista
    </a>
</div>

<h1 class="mb-4"><?= sanitize($folderName) ?></h1>

<form method="post">

<div class="meta-box mb-5">
    <h5 class="mb-3">Titolo cartella</h5>
    <textarea name="folder_comment"
              class="form-control"
              rows="3"><?= sanitize($meta['folder_comment']) ?></textarea>

    <button type="submit"
            class="btn btn-success mt-3">
        💾 Salva modifiche
    </button>
</div>

<div class="row g-4">

<?php foreach ($images as $img):

    $filename = basename($img);
    $thumbPath = $thumbFolder . '/' . $filename;
    $relativeThumb = THUMB_URL.'/' . $folderName . '/' . $filename;

    if (!file_exists($thumbPath) || filemtime($img) > filemtime($thumbPath)) {
        createThumbnail($img, $thumbPath, 800);
    }

    $desc = $meta['images'][$filename] ?? '';
    $isPanorama = $meta['panoramas'][$filename] ?? true;
?>

<div class="col-12 col-lg-6">

    <div class="card h-100">

        <?php if ($isPanorama): ?>
            <div class="preview-360"
                 id="preview_<?= sanitize($filename) ?>"></div>
        <?php else: ?>
            <img src="<?= $relativeThumb ?>"
                 class="preview-360"
                 style="object-fit:cover;">
        <?php endif; ?>

        <div class="card-body">

            <h6 class="text-secondary small mb-2">
                <?= sanitize($filename) ?>
            </h6>

            <div class="mb-3">
                <input type="text"
                       name="images[<?= sanitize($filename) ?>]"
                       value="<?= sanitize($desc) ?>"
                       class="form-control"
                       placeholder="Descrizione immagine">
            </div>

            <div class="d-flex flex-wrap gap-3 align-items-center">

                <div class="form-check">
                    <input class="form-check-input start-radio"
                           type="radio"
                           name="start_image"
                           value="<?= sanitize($filename) ?>"
                           <?= ($meta['start_image'] ?? '') === $filename ? 'checked' : '' ?>>
                    <label class="form-check-label small text-light">
                        ⭐ Immagine iniziale
                    </label>
                </div>

                <a class="btn btn-outline-primary btn-sm"
                   href="admin_hotspots.php?folder=<?= urlencode($folderName) ?>&image=<?= urlencode($filename) ?>">
                   🟢 Hotspot
                </a>

                <button type="submit"
                        name="delete_image"
                        value="<?= htmlspecialchars($filename) ?>"
                        class="btn btn-outline-danger btn-sm"
                        onclick="return confirm('Eliminare questa foto?');">
                    ❌ Elimina
                </button>

            </div>

        </div>

    </div>

</div>

<?php endforeach; ?>

</div>

<div class="mt-5">
    <button type="submit" class="btn btn-success">
        💾 Salva tutto
    </button>
</div>

</form>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Admin 360 - <?= sanitize($folderName) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.css"/>
<script src="https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.js"></script>
<style>
body { font-family: Arial; background:#111; color:#fff; padding:20px; }
input, textarea { width:100%; padding:8px; background:#222; border:1px solid #444; color:#fff; }
button { padding:10px 20px; background:#444; color:#fff; border:none; cursor:pointer; margin-top:20px; }

.start-selector {
    margin-top: 10px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.start-selector input[type="radio"] {
    accent-color: #28a745;
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.start-selector label {
    cursor: pointer;
    font-size: 14px;
    color: #ccc;
}

.start-selector input[type="radio"]:checked + label {
    color: #28a745;
    font-weight: 600;
}

.image-info {
	width:100%;
    max-width: 1000px;
}
a { color:#0af; text-decoration:none; }
a:hover { text-decoration:underline; }

.image-info {
    flex: 1;
}

.image-info input {
    width: 100%;
    padding: 8px;
    margin-top: 6px;
    background: #111;
    border: 1px solid #333;
    color: #fff;
    border-radius: 4px;
}

.button-row {
    display: flex;
    gap: 12px;
    align-items: center;
}

.inline-form {
    margin: 0;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 36px;
    padding: 0 16px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: 0.2s ease;
}

.image-row {
    display: grid;
    grid-template-columns: 1fr;
    gap: 15px;
    margin-bottom: 40px;
    padding: 25px;
    background: #1a1a1a;
    border-radius: 10px;
    border: 1px solid #333;
}

.image-row img {
    width: 100%;
    max-width: 1000px;
    aspect-ratio: 2 / 1;
    object-fit: cover;
}

.preview-360 {
    width: 100%;
    max-width: 500px;
    aspect-ratio: 2 / 1;
    border: 1px solid #444;
    background: #000;
    border-radius: 8px;
}

</style>
</head>
<body>

<a href="admin.php">← Torna alla lista</a>

<h1><?= sanitize($folderName) ?></h1>

<form method="post">
<h3>Titolo cartella</h3>
<textarea name="folder_comment" rows="3"><?= sanitize($meta['folder_comment']) ?></textarea>
<button type="submit" class="btn btn-success">💾 Salva</button>

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
<?php $isPanorama = $meta['panoramas'][$filename] ?? true; ?>

<?php if ($isPanorama): ?>

    <div class="preview-360"
         id="preview_<?= sanitize($filename) ?>">
    </div>

<?php else: ?>

    <img src="<?= $relativeThumb ?>"
         class="preview-360"
         style="object-fit:cover;">

<?php endif; ?>
    <div class="image-info">
        <span><?= sanitize($filename) ?></span>
        <input type="text"
               name="images[<?= sanitize($filename) ?>]"
               value="<?= sanitize($desc) ?>"
               placeholder="Descrizione immagine">
	<div class="button-row">
<div class="start-selector">
    <input type="radio"
           id="start_<?= sanitize($filename) ?>"
           name="start_image"
           value="<?= sanitize($filename) ?>"
           <?= ($meta['start_image'] ?? '') === $filename ? 'checked' : '' ?>>

    <label for="start_<?= sanitize($filename) ?>">
        ⭐ Immagine iniziale
    </label>
</div>
    <a class="btn btn-primary"
       href="admin_hotspots.php?folder=<?= urlencode($folderName) ?>&image=<?= urlencode($filename) ?>">
       🟢 Gestione Hotspot
    </a>
    <button type="submit"
        name="delete_image"
        value="<?= htmlspecialchars($filename) ?>"
        class="btn btn-danger"
        onclick="return confirm('Eliminare questa foto?');">
    ❌ Elimina
</button>

</div>
    </div>
</div>
<?php endforeach; ?>
<script>
document.addEventListener("DOMContentLoaded", function() {

    <?php foreach ($images as $img):
        $filename = basename($img);
        $relativeImage = IMAGES_URL.'/' . $folderName . '/' . $filename;
	$relativeThumb = THUMB_URL.'/' . $folderName . '/' . $filename;
    ?>

    pannellum.viewer("preview_<?= sanitize($filename) ?>", {
        type: "equirectangular",
	preview: "<?= $relativeThumb ?>",      // thumbnail statica
        panorama: "<?= $relativeImage ?>",
        autoLoad: false,
        showControls: false,
        compass: false,
        keyboardZoom: false,
        mouseZoom: false,
        pitch: 0,
        yaw: 0,
        hfov: 130,
        hotSpots: [],
	strings: {
        	loadButtonLabel: "Clicca",
        	loadingLabel: "Caricamento in corso..."
    	},

    });

    <?php endforeach; ?>

});
</script>
<button type="submit" class="btn btn-success">💾 Salva</button>
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

<script>
document.addEventListener("DOMContentLoaded", function() {

<?php foreach ($images as $img):
    $filename = basename($img);
    $relativeImage = IMAGES_URL.'/' . $folderName . '/' . $filename;
    $relativeThumb = THUMB_URL.'/' . $folderName . '/' . $filename;
    $isPanorama = $meta['panoramas'][$filename] ?? true;
?>

<?php if ($isPanorama): ?>
    pannellum.viewer("preview_<?= sanitize($filename) ?>", {
        type: "equirectangular",
        preview: "<?= $relativeThumb ?>",
        panorama: "<?= $relativeImage ?>",
        autoLoad: false,
        showControls: false,
        compass: false,
        keyboardZoom: false,
        mouseZoom: false,
        pitch: 0,
        yaw: 0,
        hfov: 130,
        hotSpots: [],
        strings: {
            loadButtonLabel: "Clicca",
            loadingLabel: "Caricamento..."
        }
    });
<?php endif; ?>

<?php endforeach; ?>

});
</script>
</body>
</html>
