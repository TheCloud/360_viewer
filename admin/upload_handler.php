<?php
ini_set('memory_limit', '512M');
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config.php';

$baseDir = IMAGES_DIR;
$thumbBaseDir = THUMB_DIR;

$isFlat=false;
$is360=false;

file_put_contents(
    __DIR__ . '/debug.log',
    print_r($_FILES, true),
    FILE_APPEND
);

function sanitize($str) {
    return preg_replace('/[^a-zA-Z0-9-_]/', '', $str);
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
    if (!$src) {
        file_put_contents(__DIR__.'/debug.log', "GD FAIL\n", FILE_APPEND);
        return false;
    }

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

/* =========================
   RILEVA PANORAMICA 360
========================= */

function isPanorama360($filePath) {

    // EXIF base
    if (function_exists('exif_read_data')) {
        $exif = @exif_read_data($filePath);
        if ($exif !== false) {
            if (!empty($exif['UsePanoramaViewer']) && $exif['UsePanoramaViewer'] == 1) {
                return true;
            }
        }
    }

    // XMP GPano
    $data = file_get_contents($filePath);
    if ($data !== false) {

        if (strpos($data, 'GPano:ProjectionType') !== false &&
            strpos($data, 'equirectangular') !== false) {
            return true;
        }

        if (strpos($data, 'UsePanoramaViewer') !== false) {
            return true;
        }

        if (strpos($data, 'FullPanoWidthPixels') !== false) {
            return true;
        }
    }

    return false;
}

/* ========================
 * Crea una immagine falsa sferica delle flat
*/
function convertFlatToPseudoPanorama($imagePath) {

$src = imagecreatefromjpeg($imagePath);
if (!$src) return false;

$srcW = imagesx($src);
$srcH = imagesy($src);

$scale = min(2000 / $srcW, 1000 / $srcH);

$newW = (int)($srcW * $scale);
$newH = (int)($srcH * $scale);

$resized = imagecreatetruecolor($newW, $newH);

imagecopyresampled(
    $resized, $src,
    0,0,0,0,
    $newW, $newH,
    $srcW, $srcH
);

$canvas = imagecreatetruecolor(4096, 2048);

$black = imagecolorallocate($canvas, 0,0,0);
imagefill($canvas, 0,0, $black);

$dstX = (4096 - $newW) / 2;
$dstY = (2048 - $newH) / 2;

imagecopy($canvas, $resized, $dstX, $dstY, 0,0, $newW, $newH);

imagejpeg($canvas, $imagePath, 92);

imagedestroy($src);
imagedestroy($resized);
imagedestroy($canvas);

    /* aggiunge metadata GPano */
    $cmdExif = "exiftool -overwrite_original "
        . "-ProjectionType=equirectangular "
        . "-UsePanoramaViewer=True "
        . "-FullPanoWidthPixels=4096 "
        . "-FullPanoHeightPixels=2048 "
        . "-CroppedAreaImageWidthPixels=2000 "
        . "-CroppedAreaImageHeightPixels=1000 "
        . "-CroppedAreaLeftPixels=1048 "
        . "-CroppedAreaTopPixels=524 "
        . escapeshellarg($imagePath);

    exec($cmdExif);

    return true;
}

/* =========================
   VALIDAZIONE REQUEST
========================= */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    exit;
}

$folderName = sanitize($_POST['folder'] ?? '');
if (!$folderName) {
    http_response_code(400);
    exit;
}

$folderPath  = $baseDir . '/' . $folderName;
$thumbFolder = $thumbBaseDir . '/' . $folderName;

if (!is_dir($folderPath))  mkdir($folderPath, 0755, true);
if (!is_dir($thumbFolder)) mkdir($thumbFolder, 0755, true);

if (empty($_FILES['image'])) {
    http_response_code(400);
    echo "Nessun file ricevuto";
    exit;
}

if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {

    file_put_contents(
        __DIR__ . '/debug.log',
        "UPLOAD ERROR: " . $_FILES['image']['error'] . "\n",
        FILE_APPEND
    );

    echo "Errore upload file: " . $_FILES['image']['error'];
    exit;
}

/* =========================
   VALIDAZIONE MIME
========================= */

$originalName = basename($_FILES['image']['name']);

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $_FILES['image']['tmp_name']);
finfo_close($finfo);

if ($mime !== 'image/jpeg') {
    http_response_code(400);
    echo "File non valido";
    exit;
}

/* =========================
   SALVATAGGIO FILE
========================= */

$targetPath = $folderPath . '/' . $originalName;

if (file_exists($targetPath)) {
    $originalName = time() . '_' . $originalName;
    $targetPath   = $folderPath . '/' . $originalName;
}

if (!move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
    http_response_code(500);
    echo "Errore salvataggio";
    exit;
}

/* =========================
   CREA THUMBNAIL
========================= */

$thumbPath = $thumbFolder . '/' . $originalName;
createThumbnail($targetPath, $thumbPath, 800);

/* =========================
   RILEVA 360
========================= */

$is360 = isPanorama360($targetPath);

if (!$is360) {
    if (convertFlatToPseudoPanorama($targetPath)) {
        $isFlat = true;
    }
}

/* =========================
   AGGIORNA META.JSON
========================= */

$metaFile = $folderPath . '/meta.json';
$meta = loadMeta($folderPath);

// salva flag panorama
if (!isset($meta['panoramas'])) {
    $meta['panoramas'] = [];
}

if ($is360){
    $meta['panoramas'][$originalName] = $is360;
}
if (!isset($meta['flats'])) {
    $meta['flats'] = [];
}

if ($isFlat) {
    $meta['flats'][$originalName] = $isFlat;
}

file_put_contents(
    $metaFile,
    json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

/* =========================
   RISPOSTA
========================= */

echo "OK";
