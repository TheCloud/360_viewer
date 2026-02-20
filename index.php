<?php

define('SECRET_KEY', 'LA_STESSA_IDENTICA_STRINGA_DI_INDEX');

function generateToken($folder) {
    return hash_hmac('sha256', $folder, SECRET_KEY);
}

function isValidToken($folder, $token) {
    return hash_equals(generateToken($folder), $token ?? '');
}

$baseDir      = __DIR__ . '/images';
$thumbBaseDir = __DIR__ . '/thumbnails';

$openFolder = $_GET['open'] ?? null;
$openImage  = $_GET['img'] ?? null;
$token      = $_GET['token'] ?? null;

$yawParam   = $_GET['yaw'] ?? null;
$pitchParam = $_GET['pitch'] ?? null;
$hfovParam  = $_GET['hfov'] ?? null;

/* =========================
   ACCESSO SOLO CON TOKEN
========================= */

if (!$openFolder || !isValidToken($openFolder, $token)) {
    http_response_code(403);
    die("Accesso non autorizzato");
}

$folderPath = $baseDir . '/' . basename($openFolder);

if (!is_dir($folderPath)) {
    die("Cartella non valida");
}

/* =========================
   THUMBNAIL
========================= */

function createThumbnail($sourcePath, $thumbPath, $maxWidth = 800) {

    if (!extension_loaded('gd')) return false;

    $info = getimagesize($sourcePath);
    if (!$info) return false;

    list($width, $height) = $info;

    $ratio     = $height / $width;
    $newWidth  = $maxWidth;
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

$images = glob($folderPath . '/*.jpg');

$meta = [
    'folder_comment' => '',
    'images'   => [],
    'hotspots' => []
];

$metaFile = $folderPath . '/meta.json';

if (file_exists($metaFile)) {
    $decoded = json_decode(file_get_contents($metaFile), true);
    if (is_array($decoded)) {
        $meta = array_merge($meta, $decoded);
    }
}

$thumbFolder = $thumbBaseDir . '/' . $openFolder;
if (!is_dir($thumbFolder)) {
    mkdir($thumbFolder, 0755, true);
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

.custom-hotspot {
    width:18px;
    height:18px;
    background:#ff3b3b;
    border-radius:50%;
    border:2px solid #fff;
    box-shadow:0 0 8px rgba(255,0,0,0.7);
}

.custom-hotspot::after {
    content:'';
    position:absolute;
    inset:-6px;
    border-radius:50%;
    border:2px solid rgba(255,0,0,0.6);
    animation:pulse 1.5s infinite;
}

@keyframes pulse {
    0% { transform:scale(0.6); opacity:1; }
    100% { transform:scale(1.6); opacity:0; }
}
.thumb {
    width:300px;
    height:150px;
    background-size:cover;
    background-position:center;
    border-radius:6px;
    border:2px solid #333;
    cursor:pointer;
}
.thumb:hover { border-color:#fff; }

#viewerOverlay {
    position:fixed;
    inset:0;
    background:#000;
    display:none;
    z-index:9999;
}

#panorama {
    position:absolute;
    inset:0;
}

.closeBtn {
    position:absolute;
    top:20px;
    right:30px;
    font-size:28px;
    cursor:pointer;
    z-index:10001;
}

#shareViewBtn {
    position:absolute;
    top:20px;
    left:30px;
    z-index:10001;
}

.viewer-caption {
    position:absolute;
    bottom:30px;
    left:50%;
    transform:translateX(-50%);
    background:rgba(0,0,0,0.6);
    padding:10px 20px;
    border-radius:6px;
}
</style>
</head>
<body>

<div class="container py-4">
<h1 class="mb-4">
<?= htmlspecialchars($meta['folder_comment'] ?: $openFolder) ?>
</h1>

<div class="d-flex flex-wrap gap-3">

<?php foreach ($images as $img):

    $filename = basename($img);

    $relativeImage = 'images/' . $openFolder . '/' . $filename;

    $thumbPath     = $thumbFolder . '/' . $filename;
    $relativeThumb = 'thumbnails/' . $openFolder . '/' . $filename;

    if (!file_exists($thumbPath) || filemtime($img) > filemtime($thumbPath)) {
        createThumbnail($img, $thumbPath);
    }

    $description = $meta['images'][$filename] ?? '';
?>

<div style="width:300px;">

    <div class="thumb"
         style="background-image:url('<?= $relativeThumb ?>')"
         onclick="openViewer(
             '<?= $relativeImage ?>',
             '<?= htmlspecialchars($description, ENT_QUOTES) ?>',
             '<?= $filename ?>'
         )">
    </div>

    <?php if (!empty($description)): ?>
        <div class="text-center mt-2 small text-light">
            <?= htmlspecialchars($description) ?>
        </div>
    <?php endif; ?>

</div>
<?php endforeach; ?>

</div>
</div>

<div id="viewerOverlay">
<div class="closeBtn text-white" onclick="closeViewer()">✕</div>
<button id="shareViewBtn" class="btn btn-sm btn-outline-light">
    🔗 Condividi vista
</button>
<div id="panorama"></div>
<div id="viewerCaption" class="viewer-caption" style="display:none;"></div>
</div>

<script>

let viewer = null;
let currentImageName = null;

let autoOpenFolder = <?= json_encode($openFolder) ?>;
let autoOpenImage  = <?= json_encode($openImage) ?>;
let autoToken      = <?= json_encode($token) ?>;

let autoYaw   = <?= json_encode($yawParam) ?>;
let autoPitch = <?= json_encode($pitchParam) ?>;
let autoHfov  = <?= json_encode($hfovParam) ?>;

let imageHotspotsData = <?= json_encode($meta['hotspots'] ?? []) ?>;

function openViewer(imagePath, description, fileName) {

    currentImageName = fileName;

    document.getElementById('viewerOverlay').style.display = 'block';

    if (viewer) {
        viewer.destroy();
        viewer = null;
    }

let config = {
    type: 'equirectangular',
    panorama: imagePath,
    autoLoad: true,
    showControls: true,
    hotSpots: (imageHotspotsData?.[fileName] || []).map(h => ({
        pitch: parseFloat(h.pitch),
        yaw: parseFloat(h.yaw),
        text: h.text || '',
        type: 'info',
        cssClass: 'custom-hotspot'
    }))
};

    if (autoYaw)   config.yaw   = parseFloat(autoYaw);
    if (autoPitch) config.pitch = parseFloat(autoPitch);
    if (autoHfov)  config.hfov  = parseFloat(autoHfov);

    viewer = pannellum.viewer('panorama', config);
viewer.on('load', function () {

    const hs = imageHotspotsData?.[fileName] || [];

    if (hs.length > 0) {

        const first = hs[0];

        viewer.lookAt(
            parseFloat(first.pitch),
            parseFloat(first.yaw),
            100,       // hfov iniziale (puoi regolarlo)
            800        // durata animazione in ms
        );
    }

});
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

    document.getElementById('viewerCaption').style.display = 'none';

    const url =
        window.location.origin +
        window.location.pathname +
        '?open=' + encodeURIComponent(autoOpenFolder) +
        '&token=' + encodeURIComponent(autoToken);

    history.replaceState(null, '', url);
}

document.getElementById('shareViewBtn').addEventListener('click', function() {

    if (!viewer || !currentImageName) return;

    const yaw   = viewer.getYaw().toFixed(2);
    const pitch = viewer.getPitch().toFixed(2);
    const hfov  = viewer.getHfov().toFixed(2);

    const shareUrl =
        window.location.origin +
        window.location.pathname +
        '?open=' + encodeURIComponent(autoOpenFolder) +
        '&token=' + encodeURIComponent(autoToken) +
        '&img=' + encodeURIComponent(currentImageName) +
        '&yaw=' + yaw +
        '&pitch=' + pitch +
        '&hfov=' + hfov;

    if (navigator.share) {
        navigator.share({ title:'Foto 360', url:shareUrl }).catch(()=>{});
    } else {
        navigator.clipboard.writeText(shareUrl);
    }
});

document.addEventListener('DOMContentLoaded', function() {

    if (autoOpenImage) {
        setTimeout(function() {
            openViewer(
                'images/' + autoOpenFolder + '/' + autoOpenImage,
                '',
                autoOpenImage
            );
        }, 500);
    }

});

</script>

</body>
</html>
