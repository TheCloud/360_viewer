<?php
require_once __DIR__ . '/config.php';

function generateToken($folder) {
    return hash_hmac('sha256', $folder, SECRET_KEY);
}

function isValidToken($folder, $token) {
    return hash_equals(generateToken($folder), $token ?? '');
}

$baseDir = IMAGES_DIR;
$thumbBaseDir = THUMB_DIR;

$openFolder = $_GET['open'] ?? null;
$openImage  = $_GET['img'] ?? null;
$token      = $_GET['token'] ?? null;

$yawParam   = $_GET['yaw'] ?? null;
$pitchParam = $_GET['pitch'] ?? null;
$hfovParam  = $_GET['hfov'] ?? null;

/* =========================
   TOKEN CHECK
========================= */

if (!$openFolder || !isValidToken($openFolder, $token)) {
    http_response_code(403);
    exit("403 Accesso riservato");
}

$folderPath = $baseDir . '/' . basename($openFolder);

if (!is_dir($folderPath)) {
    http_response_code(404);
    exit("Album non trovato");
}

/* =========================
   IMMAGINI
========================= */

$images = glob($folderPath . '/*.{jpg,jpeg,JPG,JPEG}', GLOB_BRACE);

$meta = [
    'folder_comment' => '',
    'start_image'    => null,
    'images'         => [],
    'hotspots'       => [],
    'panoramas'      => []
];

$metaFile = $folderPath . '/meta.json';

if (file_exists($metaFile)) {
    $decoded = json_decode(file_get_contents($metaFile), true);
    if (is_array($decoded)) {
        $meta = array_merge($meta, $decoded);
    }
}

$startImage = null;
if (!empty($meta['start_image']) &&
    file_exists($folderPath . '/' . $meta['start_image'])) {
    $startImage = $meta['start_image'];
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Foto 360</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.css"/>
<script src="https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.js"></script>

<style>
body { background:#111; color:#fff; }
.thumb { width:300px; height:150px; background-size:cover; background-position:center; cursor:pointer; border:2px solid #333; }
.thumb:hover { border-color:#fff; }

.thumb-wrapper {
    position: relative;
    width: 300px;
}

.pano-badge {
    position: absolute;
    top: 8px;
    right: 8px;
    background: rgba(0,0,0,0.75);
    color: #fff;
    font-size: 13px;
    font-weight: 600;
    padding: 4px 8px;
    border-radius: 6px;
    backdrop-filter: blur(4px);
}
.pano-badge {
    position: absolute;
    top: 8px;
    right: 8px;
    background: rgba(0,0,0,0.75);
    color: #fff;
    font-size: 13px;
    font-weight: 600;
    padding: 4px 8px;
    border-radius: 6px;
    backdrop-filter: blur(4px);
}

#viewerOverlay { position:fixed; inset:0; background:#000; display:none; z-index:9999; }
#panorama { position:absolute; inset:0; }
.closeBtn { position:absolute; top:20px; right:30px; font-size:28px; cursor:pointer; z-index:10001; }
.viewer-caption { position:absolute; bottom:30px; left:50%; transform:translateX(-50%); background:rgba(0,0,0,0.6); padding:10px 20px; border-radius:6px; }
</style>
</head>
<body>

<div class="container py-4">
<h1><?= htmlspecialchars($meta['folder_comment'] ?: $openFolder) ?></h1>

<div class="d-flex flex-wrap gap-3">

<?php foreach ($images as $img):

    $filename = basename($img);
    $relativeImage = 'images/' . $openFolder . '/' . $filename;
    $relativeThumb = 'thumbnails/' . $openFolder . '/' . $filename;

    $description = $meta['images'][$filename] ?? '';
?>

<?php $isPanorama = $meta['panoramas'][$filename] ?? false; ?>

<div style="width:300px; position:relative;">

    <div class="thumb"
         style="background-image:url('<?= $relativeThumb ?>')"
         onclick="openViewer(
            '<?= $relativeImage ?>',
            '<?= htmlspecialchars($description, ENT_QUOTES) ?>',
            '<?= $filename ?>'
         )">
    </div>

    <?php if ($isPanorama): ?>
        <div class="pano-badge">360°</div>
    <?php endif; ?>

</div>

<?php endforeach; ?>

</div>
</div>

<div id="viewerOverlay">
<div class="closeBtn text-white" onclick="closeViewer()">✕</div>
<div id="panorama"></div>
<div id="viewerCaption" class="viewer-caption" style="display:none;"></div>
</div>

<script>

let viewer = null;
let currentImageName = null;

let imageHotspotsData = <?= json_encode($meta['hotspots'] ?? []) ?>;
let panoramaFlags     = <?= json_encode($meta['panoramas'] ?? []) ?>;

let autoOpenFolder = <?= json_encode($openFolder) ?>;
let autoToken      = <?= json_encode($token) ?>;

let autoYaw   = <?= json_encode($yawParam) ?>;
let autoPitch = <?= json_encode($pitchParam) ?>;
let autoHfov  = <?= json_encode($hfovParam) ?>;

function openViewer(imagePath, description, fileName) {

    currentImageName = fileName;
    document.getElementById('viewerOverlay').style.display = 'block';

    if (viewer) {
        viewer.destroy();
        viewer = null;
    }

    const is360 = panoramaFlags[fileName] ?? false;

    if (!is360) {

        document.getElementById('panorama').innerHTML =
            '<img src="' + imagePath + '" style="width:100%; height:100%; object-fit:contain;">';

    } else {

        const previewPath = imagePath.replace('/images/', '/thumbnails/');

        let config = {
            type: 'equirectangular',
            panorama: imagePath,
            preview: previewPath,
            autoLoad: true,
            showControls: true,
            hotSpots: (imageHotspotsData[fileName] || []).map(h => ({
                pitch: parseFloat(h.pitch),
                yaw: parseFloat(h.yaw),
                type: 'info',
                text: h.text || ''
            }))
        };

        if (autoYaw)   config.yaw   = parseFloat(autoYaw);
        if (autoPitch) config.pitch = parseFloat(autoPitch);
        if (autoHfov)  config.hfov  = parseFloat(autoHfov);

        viewer = pannellum.viewer('panorama', config);
    }

    const caption = document.getElementById('viewerCaption');
    if (description) {
        caption.innerText = description;
        caption.style.display = 'block';
    } else {
        caption.style.display = 'none';
    }
}

function closeViewer() {

    document.getElementById('viewerOverlay').style.display = 'none';

    if (viewer) {
        viewer.destroy();
        viewer = null;
    }

    document.getElementById('panorama').innerHTML = '';
}

const defaultStartImage = <?= json_encode($startImage) ?>;

document.addEventListener('DOMContentLoaded', function() {

    if (defaultStartImage) {
        setTimeout(function() {
            openViewer(
                'images/' + autoOpenFolder + '/' + defaultStartImage,
                '',
                defaultStartImage
            );
        }, 400);
    }

});
</script>

</body>
</html>
