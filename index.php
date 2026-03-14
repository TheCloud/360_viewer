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
$startImage = null;
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
html {
    height:100%;
}

body {
    margin:0;
    min-height:100vh; /* fallback */
    min-height:100dvh; /* viewport dinamica mobile */
    display:flex;
    align-items:center;
    justify-content:center;
    background:#000;
    color:#fff;
    font-family:-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    text-align:center;
}

.box {
    max-width:600px;
    padding:40px;
}

.code {
    font-size:90px;
    font-weight:700;
    color:#ff3b3b;
    margin-bottom:20px;
}

.msg {
    font-size:22px;
    margin-bottom:15px;
}

.sub {
    font-size:15px;
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
exit;}

$folderPath = $baseDir . '/' . basename($openFolder);

if (!is_dir($folderPath)) {
http_response_code(403);
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Accesso riservato</title>
<style>
html {
    height:100%;
}

body {
    margin:0;
    min-height:100vh; /* fallback */
    min-height:100dvh; /* viewport dinamica mobile */
    display:flex;
    align-items:center;
    justify-content:center;
    background:#000;
    color:#fff;
    font-family:-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    text-align:center;
}

.box {
    max-width:600px;
    padding:40px;
}

.code {
    font-size:90px;
    font-weight:700;
    color:#ff3b3b;
    margin-bottom:20px;
}

.msg {
    font-size:22px;
    margin-bottom:15px;
}

.sub {
    font-size:15px;
    color:#aaa;
}
</style>
</head>
<body>
    <div class="box">
        <div class="code">404</div>
        <div class="msg">Album non trovato</div>
        <div class="sub">
            Questo contenuto non è stato trovato.<br>
            Il link utilizzato non è valido o è scaduto.
        </div>
    </div>
</body>
</html>
<?php
exit;}

/* =========================
   IMMAGINI + META
========================= */

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
$images = glob($folderPath . '/*.{jpg,jpeg,JPG,JPEG}', GLOB_BRACE);
if ($startImage) {

    usort($images, function($a, $b) use ($startImage) {

        $fa = basename($a);
        $fb = basename($b);

        if ($fa === $startImage) return -1;
        if ($fb === $startImage) return 1;

        return 0;
    });
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

.start-thumb {
    border: 3px solid #28a745 !important;
    box-shadow: 0 0 12px rgba(40,167,69,0.6);
}

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
    $isStart = ($filename === $startImage);
    $dataOra = null;

    $dataOra = null;

if (!empty($meta['images_meta'][$filename]['datetime'])) {

    $dt = DateTime::createFromFormat(
        'Y-m-d H:i:s',
        $meta['images_meta'][$filename]['datetime']
    );

    if ($dt) {
        $dataOra = $dt->format('d/m/Y H:i');
    }
}

if (!$dataOra) {
    $dataOra = date('d/m/Y H:i', filemtime($img));
}


?>

<div class="thumb-wrapper">

    <div class="thumb <?= $isStart ? 'start-thumb' : '' ?>"
         style="background-image:url('<?= $relativeThumb ?>')"
         onclick="openViewer(
            '<?= $relativeImage ?>',
            '<?= htmlspecialchars($description, ENT_QUOTES) ?>',
            '<?= $filename ?>'
         )">
    </div>
<div class="text-center mt-2 small text-light">

    <div style="font-weight:600;">
        <?= htmlspecialchars($description) ?>
    </div>

    <div style="color:#aaa;">
        <?= $dataOra ?>
    </div>

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
    const newUrl =
    window.location.origin +
    window.location.pathname +
    '?open=' + encodeURIComponent(autoOpenFolder) +
    '&token=' + encodeURIComponent(autoToken) +
    '&img=' + encodeURIComponent(fileName);

    history.replaceState(null, '', newUrl);

    document.getElementById('viewerOverlay').style.display = 'block';

    if (viewer) {
        viewer.destroy();
        viewer = null;
    }

    const is360 = panoramaFlags[fileName] ?? false;

    if (!is360) {

    const container = document.getElementById('panorama');

    container.innerHTML =
        '<div id="flatContainer" style="position:relative;width:100%;height:100%;">' +
        '<img id="flatImage" src="' + imagePath + '" ' +
        'style="width:100%;height:100%;object-fit:contain;">' +
        '</div>';

    const hs = imageHotspotsData?.[fileName] || [];

    const img = document.getElementById("flatImage");
    const wrap = document.getElementById("flatContainer");

    img.onload = function () {

        hs.forEach(h => {

            const pitch = parseFloat(h.pitch);
            const yaw   = parseFloat(h.yaw);

            const pos = panoToFlat(pitch, yaw);

            const dot = document.createElement("div");

            dot.className = (h.type === 'link')
                ? "link-hotspot"
                : "custom-hotspot";

            dot.style.position = "absolute";
            dot.style.left = pos.x + "%";
            dot.style.top  = pos.y + "%";

            if (h.type === "link" && h.target) {

                dot.onclick = function() {
                    loadScene(h.target);
                };

            } else if (h.text) {

                dot.title = h.text;

            }

            wrap.appendChild(dot);

        });

    };

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
        navigator.share({
            title: 'Foto 360',
            url: shareUrl
        }).catch(()=>{});
    } else {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(shareUrl).catch(()=>{});
        } else {
            const tmp = document.createElement('input');
            tmp.value = shareUrl;
            document.body.appendChild(tmp);
            tmp.select();
            document.execCommand('copy');
            document.body.removeChild(tmp);
        }
    }
});

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

function panoToFlat(pitch, yaw) {

    const x = (yaw + 180) / 360 * 100;
    const y = (90 - pitch) / 180 * 100;

    return { x, y };
}

function closeViewer() {

    document.getElementById('viewerOverlay').style.display = 'none';

    if (viewer) {
        viewer.destroy();
        viewer = null;
    }

    document.getElementById('panorama').innerHTML = '';

    const baseUrl =
        window.location.origin +
        window.location.pathname +
        '?open=' + encodeURIComponent(autoOpenFolder) +
        '&token=' + encodeURIComponent(autoToken);

    history.replaceState(null, '', baseUrl);
}


document.addEventListener('DOMContentLoaded', function() {

    if (autoOpenImage) {
        openViewer(
            'images/' + autoOpenFolder + '/' + autoOpenImage,
            '',
            autoOpenImage
        );
    }

});
</script>

</body>
</html>
