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
   ACCESSO SOLO CON TOKEN
========================= */

if (!$openFolder || !isValidToken($openFolder, $token)) {

    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Accesso riservato</title>
        <style>
            body {
                margin:0;
                background:#000;
                color:#fff;
                display:flex;
                align-items:center;
                justify-content:center;
                height:100vh;
                font-family:Arial, sans-serif;
                text-align:center;
            }
            .box {
                max-width:600px;
            }
            .code {
                font-size:80px;
                font-weight:bold;
                color:#ff3b3b;
                margin-bottom:20px;
            }
            .msg {
                font-size:22px;
                margin-bottom:15px;
            }
            .sub {
                font-size:16px;
                color:#aaa;
            }
        </style>
    </head>
    <body>
        <div class="box">
            <div class="code">403</div>
            <div class="msg">Accesso riservato</div>
            <div class="sub">
                Questo contenuto è protetto.<br>
                Il link utilizzato non è valido o è scaduto.
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$folderPath = $baseDir . '/' . basename($openFolder);

if (!is_dir($folderPath)) {

    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Album non trovato</title>
        <style>
            body {
                margin:0;
                height:100vh;
                display:flex;
                justify-content:center;
                align-items:center;
                background:#0d0d0d;
                color:#fff;
                font-family:Arial, sans-serif;
                text-align:center;
            }
            .box {
                max-width:500px;
                padding:40px;
                background:#1a1a1a;
                border:1px solid #333;
                border-radius:12px;
                box-shadow:0 0 40px rgba(0,0,0,0.6);
            }
            h1 {
                font-size:28px;
                margin-bottom:15px;
                color:#ff6b6b;
            }
            p {
                color:#aaa;
                margin-bottom:25px;
            }
            a {
                display:inline-block;
                padding:10px 20px;
                background:#222;
                border:1px solid #444;
                color:#fff;
                text-decoration:none;
                border-radius:6px;
            }
            a:hover {
                background:#333;
            }
        </style>
    </head>
    <body>
        <div class="box">
            <h1>📂 Album non trovato</h1>
            <p>L'album richiesto non esiste o è stato rimosso.</p>
            <a href="/360/">Torna alla galleria</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

/* =========================
   THUMBNAIL
========================= */
function createThumbnail($sourcePath, $thumbPath, $size = 800) {

    if (!extension_loaded('gd')) return false;

    $info = getimagesize($sourcePath);
    if (!$info) return false;

    list($width, $height) = $info;

    $src = imagecreatefromjpeg($sourcePath);
    if (!$src) return false;

    // Lato del quadrato = min(width, height)
    $cropSize = min($width, $height);

    // Centro orizzontale
    $srcX = ($width - $cropSize) / 2;
    $srcY = ($height - $cropSize) / 2;

    $thumb = imagecreatetruecolor($size, $size);

    imagecopyresampled(
        $thumb,
        $src,
        0, 0,
        $srcX, $srcY,
        $size, $size,
        $cropSize, $cropSize
    );

    imagejpeg($thumb, $thumbPath, 80);

    imagedestroy($src);
    imagedestroy($thumb);

    return true;
}

    $images = glob($folderPath . '/*.{jpg,jpeg,JPG,JPEG}', GLOB_BRACE);

$meta = [
    'folder_comment' => '',
    'start_image'    => null,
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

$startImage = null;
if (!empty($meta['start_image']) &&
    file_exists($folderPath . '/' . $meta['start_image'])) {
    $startImage = $meta['start_image'];
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
.link-hotspot {
    width: 34px !important;
    height: 34px !important;
    display: block !important;
    position: absolute !important;
    pointer-events: auto;
    cursor: pointer;
}

.link-hotspot::before {
    content: "";
    position: absolute;
    inset: 0;
    border-radius: 50%;
    background: radial-gradient(circle,
        rgba(255,180,0,0.95) 20%,
        rgba(255,120,0,0.8) 50%,
        rgba(255,60,0,0.5) 75%,
        transparent 100%);
    box-shadow: 0 0 14px rgba(255,120,0,0.9);
}

.link-hotspot::after {
    content: "";
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -70%);
    width: 0;
    height: 0;
    border-left: 8px solid transparent;
    border-right: 8px solid transparent;
    border-bottom: 14px solid white;
    pointer-events: none;
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
window.imageHotspotsData = <?= json_encode(
    $meta['hotspots'] ?? [],
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
) ?>;

</script>
    <script>

let viewer = null;
let currentImageName = null;

let autoOpenFolder = <?= json_encode($openFolder) ?>;
let autoOpenImage  = <?= json_encode($openImage) ?>;
let autoToken      = <?= json_encode($token) ?>;


let autoYaw   = <?= json_encode($yawParam) ?>;
let autoPitch = <?= json_encode($pitchParam) ?>;
let autoHfov  = <?= json_encode($hfovParam) ?>;

let imageHotspotsData = window.imageHotspotsData || {};

function openViewer(imagePath, description, fileName) {
console.log("OPENING:", fileName);
console.log("HOTSPOTS RAW:", imageHotspotsData);
console.log("THIS IMAGE HS:", imageHotspotsData[fileName]);
    currentImageName = fileName;

    document.getElementById('viewerOverlay').style.display = 'block';
    let imageTitle = fileName;

    if (viewer) {
        viewer.destroy();
        viewer = null;
    }
let previewPath = imagePath.replace('/images/', '/thumbnails/');
let config = {
    type: 'equirectangular',
    panorama: imagePath,
    preview: previewPath,
    autoLoad: true,
    showControls: true,
    title: imageTitle,
strings: {
    loadButtonLabel: "Clicca e carica 360°",
    loadingLabel: "Caricamento in corso...",
    bylineLabel: ""
},
    hotSpots: (imageHotspotsData[fileName] || []).map(h => {

    const pitch = parseFloat(h.pitch);
    const yaw   = parseFloat(h.yaw);

    if (isNaN(pitch) || isNaN(yaw)) {
        console.warn("HOTSPOT INVALIDO:", h);
        return null;
    }

    if (h.type === 'link' && h.target) {
        return {
            pitch: pitch,
            yaw: yaw,
            type: 'info',
            text: h.text || '',
            cssClass: 'link-hotspot',
            clickHandlerFunc: function() {
                loadScene(h.target);
            }
        };
    }

    return {
        pitch: pitch,
        yaw: yaw,
        type: 'info',
        text: h.text || '',
        cssClass: 'custom-hotspot'
    };

}).filter(Boolean)
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

function loadScene(targetFile) {

    const newImagePath = 'images/' + autoOpenFolder + '/' + targetFile;

    openViewer(
        newImagePath,
        '',
        targetFile
    );

    const newUrl =
        window.location.origin +
        window.location.pathname +
        '?open=' + encodeURIComponent(autoOpenFolder) +
        '&token=' + encodeURIComponent(autoToken) +
        '&img=' + encodeURIComponent(targetFile);

    history.replaceState(null, '', newUrl);
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
        if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(shareUrl).catch(()=>{});
} else {
    // fallback classico
    const tmp = document.createElement('input');
    tmp.value = shareUrl;
    document.body.appendChild(tmp);
    tmp.select();
    document.execCommand('copy');
    document.body.removeChild(tmp);
}
    }
});

const defaultStartImage = <?= json_encode($startImage) ?>;

document.addEventListener('DOMContentLoaded', function() {

    if (autoOpenImage && !defaultStartImage) {

        setTimeout(function() {
            openViewer(
                'images/' + autoOpenFolder + '/' + autoOpenImage,
                '',
                autoOpenImage
            );
        }, 500);

    } else if (defaultStartImage) {

        setTimeout(function() {
            openViewer(
                'images/' + autoOpenFolder + '/' + defaultStartImage,
                '',
                defaultStartImage
            );
        }, 500);

    }

});
</script>

</body>
</html>
