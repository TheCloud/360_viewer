<?php
require_once __DIR__ . '/config.php';

function generateToken($folder) {
    return hash_hmac('sha256', $folder, SECRET_KEY);
}

function isValidToken($folder, $token) {
    return hash_equals(generateToken($folder), $token ?? '');
}

$baseDir      = IMAGES_DIR;
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
            .box { max-width:600px; }
            .code {
                font-size:80px;
                font-weight:bold;
                color:#ff3b3b;
                margin-bottom:20px;
            }
            .msg { font-size:22px; margin-bottom:15px; }
            .sub { font-size:16px; color:#aaa; }
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
}

$folderPath = $baseDir . '/' . basename($openFolder);

if (!is_dir($folderPath)) {
    http_response_code(404);
    exit("Album non trovato");
}

/* =========================
   IMMAGINI + META
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
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
body { background:#111; color:#fff; }

.thumb-wrapper {
    position: relative;
    width: 300px;
}

.thumb {
    width:300px;
    height:150px;
    background-size:cover;
    background-position:center;
    border:2px solid #333;
    border-radius:6px;
    cursor:pointer;
}
.thumb:hover { border-color:#fff; }

.pano-badge {
    position:absolute;
    top:8px;
    right:8px;
    background:rgba(0,0,0,0.75);
    color:#fff;
    font-size:13px;
    padding:4px 8px;
    border-radius:6px;
}

#viewerOverlay {
    position:fixed;
    inset:0;
    background:#000;
    display:none;
    z-index:9999;
}

#panorama { position:absolute; inset:0; }

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

.custom-hotspot {
    width:18px;
    height:18px;
    background:#ff3b3b;
    border-radius:50%;
    border:2px solid #fff;
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
    $description   = $meta['images'][$filename] ?? '';
    $isPanorama    = $meta['panoramas'][$filename] ?? false;
?>

<div class="thumb-wrapper">

    <div class="thumb"
         style="background-image:url('<?= $relativeThumb ?>')"
         onclick="openViewer(
            '<?= $relativeImage ?>',
            '<?= htmlspecialchars($description, ENT_QUOTES) ?>',
            '<?= $filename ?>'
         )">
    </div>

    <?php if ($isPanorama): ?>
        <div class="pano-badge"><i class="bi bi-globe"></i></div>
    <?php endif; ?>

</div>

<?php endforeach; ?>

</div>
</div>

<div id="viewerOverlay">
<div class="closeBtn text-white" onclick="closeViewer()">✕</div>
<button id="shareViewBtn" class="btn btn-sm btn-outline-light">🔗 Condividi vista</button>
<div id="panorama"></div>
<div id="viewerCaption" class="viewer-caption" style="display:none;"></div>
</div>

<script>

let viewer = null;
let currentImageName = null;

let imageHotspotsData = <?= json_encode($meta['hotspots'] ?? []) ?>;
let panoramaFlags     = <?= json_encode($meta['panoramas'] ?? []) ?>;

let autoOpenFolder = <?= json_encode($openFolder) ?>;
let autoOpenImage  = <?= json_encode($openImage) ?>;
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

        return;
    }

    const previewPath = imagePath.replace('/images/', '/thumbnails/');

    viewer = pannellum.viewer('panorama', {
        type: 'equirectangular',
        panorama: imagePath,
        preview: previewPath,
        autoLoad: true,
        showControls: true,
        yaw: autoYaw ? parseFloat(autoYaw) : 0,
        pitch: autoPitch ? parseFloat(autoPitch) : 0,
        hfov: autoHfov ? parseFloat(autoHfov) : 130,
        hotSpots: (imageHotspotsData[fileName] || []).map(h => {

            const pitch = parseFloat(h.pitch);
            const yaw   = parseFloat(h.yaw);

            if (h.type === 'link' && h.target) {
                return {
                    pitch, yaw,
                    type: 'info',
                    text: h.text || '',
                    cssClass: 'link-hotspot',
                    clickHandlerFunc: function() {
                        loadScene(h.target);
                    }
                };
            }

            return {
                pitch, yaw,
                type: 'info',
                text: h.text || '',
                cssClass: 'custom-hotspot'
            };

        })
    });

    viewer.on('load', function () {

        const hs = imageHotspotsData?.[fileName] || [];

        if (!autoYaw && hs.length > 0) {
            viewer.lookAt(
                parseFloat(hs[0].pitch),
                parseFloat(hs[0].yaw),
                130,
                800
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

    openViewer(
        'images/' + autoOpenFolder + '/' + targetFile,
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

    document.getElementById('panorama').innerHTML = '';
}

document.addEventListener('DOMContentLoaded', function() {

    if (autoOpenImage) {
        openViewer(
            'images/' + autoOpenFolder + '/' + autoOpenImage,
            '',
            autoOpenImage
        );
    } else if (<?= json_encode($startImage) ?>) {
        openViewer(
            'images/' + autoOpenFolder + '/' + <?= json_encode($startImage) ?>,
            '',
            <?= json_encode($startImage) ?>
        );
    }

});
</script>

</body>
</html>
