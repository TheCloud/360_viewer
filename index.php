<?php

$baseDir = __DIR__ . '/images';
$thumbBaseDir = __DIR__ . '/thumbnails';

if (!is_dir($thumbBaseDir)) {
    mkdir($thumbBaseDir, 0755, true);
}

// Cartella e file
$openFolder = $_GET['open'] ?? null;
$openImage = $_GET['img'] ?? null;

// Stato immagine
$yawParam   = $_GET['yaw']   ?? null;
$pitchParam = $_GET['pitch'] ?? null;
$hfovParam  = $_GET['hfov']  ?? null;

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
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>360 Viewer</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.css"/>
<script src="https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.js"></script>

<style>
body { background:#111; color:#fff; }
.thumb {
    width:300px;
    height:150px;
    background-size:cover;
    background-position:center;
    border-radius:6px;
    border:2px solid #333;
    cursor:pointer;
    position:relative;
}
.thumb:hover { border-color:#fff; }
.thumb-caption {
    position:absolute;
    bottom:0;
    left:0;
    right:0;
    background:rgba(0,0,0,0.6);
    font-size:12px;
    padding:4px;
    text-align:center;
}
#viewerOverlay {
    position:fixed;
    inset:0;
    background:#000;
    display:none;
    z-index:9999;
}
#panorama { width:100%; height:100%; }
.closeBtn {
    position:absolute;
    top:20px;
    right:30px;
    font-size:28px;
    cursor:pointer;
    z-index:10000;
}
.viewer-caption {
    position:absolute;
    bottom:30px;
    left:50%;
    transform:translateX(-50%);
    background:rgba(0,0,0,0.6);
    padding:10px 20px;
    border-radius:6px;
    max-width:80%;
    text-align:center;
}
</style>
</head>
<body>

<div class="container py-4">
<h1 class="mb-4">Foto Julien 360°</h1>

<div class="accordion" id="accordion360">

<?php foreach ($folders as $folder): 

    $folderName = basename($folder);
    $images = glob($folder . '/*.jpg');

    // ORDINE PER EXIF
    usort($images, function($a, $b) {
        if (function_exists('exif_read_data')) {
            $exifA = @exif_read_data($a);
            $exifB = @exif_read_data($b);
            $dateA = $exifA['DateTimeOriginal'] ?? null;
            $dateB = $exifB['DateTimeOriginal'] ?? null;
            if ($dateA && $dateB) {
                return strtotime($dateA) <=> strtotime($dateB);
            }
        }
        return filemtime($a) <=> filemtime($b);
    });

$meta = [
    'folder_comment' => '',
    'images' => []
];

$metaFile = $folder . '/meta.json';
if (file_exists($metaFile)) {
    $json = file_get_contents($metaFile);
    $decoded = json_decode($json, true);
    if (is_array($decoded)) {
        $meta = array_merge($meta, $decoded);
	$folderComment = $meta['folder_comment'] ?? '';

    }
}
    $thumbFolder = $thumbBaseDir . '/' . $folderName;
    if (!is_dir($thumbFolder)) mkdir($thumbFolder, 0755, true);
    $isOpen = ($openFolder === $folderName);
?>

<div class="accordion-item bg-dark text-white border-secondary">
<h2 class="accordion-header">
<div class="d-flex justify-content-between align-items-center w-100">

    <button class="accordion-button bg-dark text-white <?= $isOpen ? '' : 'collapsed' ?>"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#collapse<?= $folderName ?>">
        <?= htmlspecialchars($folderName) ?>: <?= htmlspecialchars($folderComment) ?>
    </button>

    <button class="btn btn-sm btn-outline-light ms-2"
            onclick="shareFolder(event, '<?= $folderName ?>')">
        🔗 Share
    </button>

</div></h2>

<div id="collapse<?= $folderName ?>"
     class="accordion-collapse collapse <?= $isOpen ? 'show' : '' ?>"
     data-bs-parent="#accordion360">

<div class="accordion-body bg-dark text-white">

<?php if ($folderComment): ?>
<p class="text-secondary"><?= htmlspecialchars($folderComment) ?></p>
<?php endif; ?>

<div class="d-flex flex-wrap gap-3">

<?php foreach ($images as $img):

    $filename = basename($img);
    $relativeImage = 'images/' . $folderName . '/' . $filename;

    $thumbPath = $thumbFolder . '/' . $filename;
    $relativeThumb = 'thumbnails/' . $folderName . '/' . $filename;

    if (!file_exists($thumbPath) || filemtime($img) > filemtime($thumbPath)) {
        createThumbnail($img, $thumbPath, 800);
    }

    $description = $meta['images'][$filename] ?? '';
?>

<div class="thumb"
     style="background-image:url('<?= $relativeThumb ?>')"
     onclick="openViewer('<?= $relativeImage ?>','<?= htmlspecialchars($description, ENT_QUOTES) ?>')">

<?php if ($description): ?>
<div class="thumb-caption"><?= htmlspecialchars($description) ?></div>
<?php endif; ?>

</div>

<?php endforeach; ?>

</div>
</div>
</div>
</div>

<?php endforeach; ?>

</div>
</div>

<div id="viewerOverlay">
<div class="closeBtn text-white" onclick="closeViewer()">✕</div>
<button id="shareViewBtn"
        class="btn btn-sm btn-outline-light"
        style="position:absolute; top:20px; left:30px; z-index:10000;">
    🔗 Condividi vista
</button>
<div id="panorama"></div>
<div id="viewerCaption" class="viewer-caption" style="display:none;"></div>
</div>

<script>
let viewer;

function openViewer(imagePath, description) {

    document.getElementById('viewerOverlay').style.display = 'block';
    if (viewer) viewer.destroy();

let config = {
    type: 'equirectangular',
    panorama: imagePath,
    autoLoad: true,
    showControls: true
};

if (autoYaw !== null && autoYaw !== "" && !isNaN(parseFloat(autoYaw))) {
    config.yaw = parseFloat(autoYaw);
}

if (autoPitch !== null && autoPitch !== "" && !isNaN(parseFloat(autoPitch))) {
    config.pitch = parseFloat(autoPitch);
}

if (autoHfov !== null && autoHfov !== "" && !isNaN(parseFloat(autoHfov))) {
    config.hfov = parseFloat(autoHfov);
}

viewer = pannellum.viewer('panorama', config);

viewer.on('mouseup', updateViewState);
viewer.on('touchend', updateViewState);
viewer.on('zoomchange', updateViewState);
    const parts = imagePath.split('/');
    const folderName = parts[1];
    const fileName = parts[2];

    const newUrl = window.location.origin +
                   window.location.pathname +
                   '?open=' + encodeURIComponent(folderName) +
                   '&img=' + encodeURIComponent(fileName);

    history.replaceState(null, '', newUrl);

    const caption = document.getElementById('viewerCaption');
    if (description && description.trim() !== '') {
        caption.innerText = description;
        caption.style.display = 'block';
    } else {
        caption.style.display = 'none';
    }
}

function updateViewState() {

    const yaw   = viewer.getYaw().toFixed(2);
    const pitch = viewer.getPitch().toFixed(2);
    const hfov  = viewer.getHfov().toFixed(2);

    const parts = viewer.getConfig().panorama.split('/');
    const folderName = parts[1];
    const fileName   = parts[2];

    const newUrl = window.location.origin +
                   window.location.pathname +
                   '?open=' + encodeURIComponent(folderName) +
                   '&img=' + encodeURIComponent(fileName) +
                   '&yaw=' + yaw +
                   '&pitch=' + pitch +
                   '&hfov=' + hfov;

    history.replaceState(null, '', newUrl);
}

function closeViewer() {
    document.getElementById('viewerOverlay').style.display = 'none';
    if (viewer) viewer.destroy();
    viewer = null;
    document.getElementById('viewerCaption').style.display = 'none';
	const url = window.location.origin +
            window.location.pathname +
            '?open=' + encodeURIComponent(autoOpenFolder || '');
history.replaceState(null, '', url);
}
function shareFolder(event, folderName) {

    event.stopPropagation();

    const url = window.location.origin + window.location.pathname + '?open=' + encodeURIComponent(folderName);

    if (navigator.share) {
        navigator.share({
            title: 'Foto 360',
            url: url
        }).catch(() => {});
    } else {
        navigator.clipboard.writeText(url).then(() => {
            alert('Link copiato negli appunti:\n' + url);
        });
    }
}

document.addEventListener('DOMContentLoaded', function() {

    const accordion = document.getElementById('accordion360');

    accordion.addEventListener('shown.bs.collapse', function(event) {

        const collapseId = event.target.id; // es: collapse2026-02-10
        const folderName = collapseId.replace('collapse', '');

        const newUrl = window.location.origin +
                       window.location.pathname +
                       '?open=' + encodeURIComponent(folderName);

        history.replaceState(null, '', newUrl);
    });

    accordion.addEventListener('hidden.bs.collapse', function(event) {

        // Se nessuna cartella è aperta → pulisci URL
        const openPanels = accordion.querySelectorAll('.accordion-collapse.show');

        if (openPanels.length === 0) {
            const cleanUrl = window.location.origin + window.location.pathname;
            history.replaceState(null, '', cleanUrl);
        }
    });

});

document.addEventListener('DOMContentLoaded', function() {

    if (autoOpenFolder && autoOpenImage) {

        const imagePath = 'images/' + autoOpenFolder + '/' + autoOpenImage;

        // aspetta che bootstrap abbia aperto l'accordion
        setTimeout(function() {
            openViewer(imagePath, '');
        }, 500);
    }

});
document.getElementById('shareViewBtn').addEventListener('click', function() {

    const currentUrl = window.location.href;

    if (navigator.share) {
        navigator.share({
            title: 'Foto 360',
            url: currentUrl
        }).catch(() => {});
    } else {
        navigator.clipboard.writeText(currentUrl).then(() => {

            const btn = document.getElementById('shareViewBtn');
            const originalText = btn.innerText;

            btn.innerText = "✓ Copiato";
            setTimeout(() => {
                btn.innerText = originalText;
            }, 1500);

        });
    }

});
</script>
<script>
const autoOpenFolder = <?= json_encode($openFolder) ?>;
const autoOpenImage = <?= json_encode($openImage) ?>;
const autoYaw   = <?= json_encode($yawParam) ?>;
const autoPitch = <?= json_encode($pitchParam) ?>;
const autoHfov  = <?= json_encode($hfovParam) ?>;
</script>
</body>
</html>
