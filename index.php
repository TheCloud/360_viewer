<?php


// Non esiste, prima installazione
//
//
$configFile = __DIR__ . '/config.php';
$defaultConfig = __DIR__ . '/config.default.php';

// Crea il file di configurazione e personalizza la hash per le cartelle
if (!file_exists($configFile)) {
    $config = file_get_contents($defaultConfig);
    /* genera chiave sicura */
    $secret = bin2hex(random_bytes(32));
    /* sostituisce placeholder */
    $config = str_replace('LA_TUA_CHIAVE', $secret, $config);
    file_put_contents($configFile, $config);
}

require_once $configFile;

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
<link rel="stylesheet" href="<?= APP_BASE_URL ?>/css/hotspots.css">
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
<link rel="stylesheet" href="<?= APP_BASE_URL ?>/css/hotspots.css">

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


$metaFile = $folderPath . '/meta.json';
$meta = loadMeta($folderPath);

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
<link rel="stylesheet" href="<?= APP_BASE_URL ?>/css/hotspots.css">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Foto 360</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.css"/>
<script src="https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.js"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

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

.leaflet-container {
    background: #111 !important;
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

.toast-container {
    z-index: 10002 !important;
}
.viewer-actions {
    position:absolute;
    top:20px;
    right:30px;
    display:flex;
    gap:10px;
    z-index:10001;
}

.viewer-actions .btn {
    width:40px;
    height:40px;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:0;
}

.viewer-actions i {
    font-size:18px;
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
        <div class="pano-badge"><i class="bi bi-globe2"></i></div>
    <?php else:?>
        <div class="pano-badge"><i class="bi bi-file-earmark-image"></i></div>
    <?php endif; ?>


</div>

<?php endforeach; ?>

</div>
</div>

<div id="viewerOverlay">
<div class="viewer-actions">

    <button id="shareViewBtn"
            class="btn btn-sm btn-outline-light"
            title="Condividi vista">
        <i class="bi bi-box-arrow-up"></i>
    </button>

    <button class="btn btn-sm btn-outline-light"
            onclick="closeViewer()"
            title="Chiudi">
        <i class="bi bi-x-lg"></i>
    </button>

</div>
<div id="panorama"></div>
<div id="viewerCaption" class="viewer-caption" style="display:none;"></div>
</div>

<script>

let viewer = null;
let flatMap = null;

let currentImageName = null;

let imageHotspotsData = <?= json_encode($meta['hotspots'] ?? []) ?>;
let panoramaFlags     = <?= json_encode($meta['panoramas'] ?? []) ?>;
let flatFlags = <?= json_encode($meta['flats'] ?? []) ?>;

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
    document.getElementById('panorama').innerHTML = '';
    if (viewer) {
        viewer.destroy();
        viewer = null;
    }

    const is360 = panoramaFlags[fileName] ?? false;
    const isFlat = flatFlags[fileName] ?? false;
if (isFlat || !is360) {
if (flatMap) {
    flatMap.remove();
    flatMap = null;
}
    const container = document.getElementById('panorama');
    container.innerHTML = "";

    const img = new Image();

    img.onload = function () {

        const width  = img.width;
        const height = img.height;

        flatMap = L.map('panorama', {
            crs: L.CRS.Simple,
            minZoom: -2,
            maxZoom: 2,
            zoomSnap: 0.2
        });

        const bounds = [[0,0],[height,width]];

        L.imageOverlay(imagePath, bounds).addTo(flatMap);

        flatMap.fitBounds(bounds, {
    padding: [0, 0]
});

flatMap.setMaxBounds(bounds);
flatMap.setZoom(flatMap.getZoom() + 0.01);

        const hs = imageHotspotsData?.[fileName] || [];

hs.forEach(h => {

    const x = parseFloat(h.x) * width;
    const y = parseFloat(h.y) * height;

    let cssClass = "hotspot info";
    if (h.type === "link") cssClass = "hotspot link";
    if (h.type === "url")  cssClass = "hotspot url";

    const icon = L.divIcon({
        className: '',
        html: '<div class="' + cssClass + '"></div>'
    });

    const marker = L.marker([y,x], {icon}).addTo(flatMap);

    if (h.type === "link" && h.target) {
        marker.on("click", () => loadScene(h.target));
    }

    if (h.type === "url" && h.target) {
        marker.on("click", () => window.open(h.target,"_blank"));
    }

    if (h.text) {
        marker.bindTooltip(h.text);
    }

});

    };

    img.src = imagePath;

    return;
}

    const previewPath = imagePath.replace('/images/', '/thumbnails/');

let pannellumConfig = {
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

        if (h.type === 'url' && h.target) {
            return {
                pitch, yaw,
                type: 'info',
                text: h.text || '',
                cssClass: 'hotspot url',
                clickHandlerFunc: function(){
                    window.open(h.target, "_blank");
                }
            };
        }

        if (h.type === 'link' && h.target) {
            return {
                pitch, yaw,
                type: 'info',
                text: h.text || '',
                cssClass: 'hotspot link',
                clickHandlerFunc: function() {
                    loadScene(h.target);
                }
            };
        }

        return {
            pitch, yaw,
            type: 'info',
            text: h.text || '',
            cssClass: 'hotspot info'
        };

    })
};

viewer = pannellum.viewer('panorama', pannellumConfig);

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

document.getElementById('shareViewBtn').addEventListener('click', function () {

    let pitch = 0;
    let yaw   = 0;
    let hfov  = 130;

    if (viewer) {
        pitch = viewer.getPitch();
        yaw   = viewer.getYaw();
        hfov  = viewer.getHfov();
    }

    if (flatMap) {
        const center = flatMap.getCenter();

        // normalizza come fai per salvare hotspot
        const bounds = flatMap.getBounds();
        const width  = bounds.getEast();
        const height = bounds.getSouth();

        yaw   = center.lng / width;
        pitch = center.lat / height;
        hfov  = flatMap.getZoom();
    }

    const url = new URL(window.location.href);

    url.searchParams.set('pitch', parseFloat(pitch).toFixed(4));
    url.searchParams.set('yaw', parseFloat(yaw).toFixed(4));
    url.searchParams.set('hfov', parseFloat(hfov).toFixed(2));

    const finalUrl = url.toString();

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(finalUrl)
            .then(() => showToast())
            .catch(() => fallbackCopy(finalUrl));
    } else {
        fallbackCopy(finalUrl);
    }

    function fallbackCopy(text) {
        const input = document.createElement("input");
        input.value = text;
        document.body.appendChild(input);
        input.select();
        document.execCommand("copy");
        document.body.removeChild(input);
        showToast();
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

function closeViewer() {

    document.getElementById('viewerOverlay').style.display = 'none';

    if (viewer) {
        viewer.destroy();
        viewer = null;
    }

    if (flatMap) {
    flatMap.remove();
    flatMap = null;
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

function showToast() {
    const toastEl = document.getElementById('copyToast');
    const toast = new bootstrap.Toast(toastEl);
    toast.show();
}

</script>
<div class="toast-container position-fixed bottom-0 end-0 p-3">
  <div id="copyToast" class="toast text-bg-dark border-0" role="alert">
    <div class="d-flex">
      <div class="toast-body">
        🔗 Link copiato
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto"
              data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
