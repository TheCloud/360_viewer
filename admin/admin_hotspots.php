<?php

$baseDir = dirname(__DIR__) . '/images';
$thumbBaseDir = dirname(__DIR__) . '/thumbnails';

$folder = $_GET['folder'] ?? null;
$image  = $_GET['image'] ?? null;

if (!$folder || !$image) {
    die("Parametri mancanti");
}

$folderName = basename($folder);
$imageName  = basename($image);

$folderPath = $baseDir . '/' . $folderName;
$imagePath  = $folderPath . '/' . $imageName;

if (!is_dir($folderPath) || !file_exists($imagePath)) {
    die("Cartella o immagine non valida");
}

$metaFile = $folderPath . '/meta.json';

$meta = [
    'folder_comment' => '',
    'images' => [],
    'hotspots' => []
];

if (file_exists($metaFile)) {
    $raw = file_get_contents($metaFile);
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $meta = array_merge($meta, $decoded);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $image = $_POST['image'] ?? $image;

if (isset($_POST['delete_pitch'], $_POST['delete_yaw'])) {

    $pitchToDelete = (float)$_POST['delete_pitch'];
    $yawToDelete   = (float)$_POST['delete_yaw'];

    foreach ($meta['hotspots'][$image] as $key => $hs) {

        if (
            (float)$hs['pitch'] === $pitchToDelete &&
            (float)$hs['yaw'] === $yawToDelete
        ) {
            unset($meta['hotspots'][$image][$key]);
            break;
        }
    }

    // Riordina indici
    $meta['hotspots'][$image] = array_values($meta['hotspots'][$image]);

    file_put_contents(
        $metaFile,
        json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );

    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

    $pitch = floatval($_POST['pitch']);
    $yaw   = floatval($_POST['yaw']);
    $text  = trim($_POST['text']);

    if (!isset($meta['hotspots'][$imageName])) {
        $meta['hotspots'][$imageName] = [];
    }

    $meta['hotspots'][$imageName][] = [
        'pitch' => $pitch,
        'yaw'   => $yaw,
        'text'  => $text
    ];

    file_put_contents(
        $metaFile,
        json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );

    header("Location: admin_hotspots.php?folder=" . urlencode($folderName) . "&image=" . urlencode($imageName));
    exit;
}

$currentHotspots = $meta['hotspots'][$imageName] ?? [];

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Hotspot Editor</title>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.css"/>
<script src="https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.js"></script>

<style>
body { background:#111; color:#fff; font-family:Arial; padding:20px; }
#panorama { width:100%; height:70vh; margin-bottom:20px; }
input { padding:8px; width:100%; margin-bottom:10px; }
button { padding:10px 20px; background:#444; color:#fff; border:none; cursor:pointer; }
.hotspot-list { margin-top:20px; }
.hotspot-item { background:#1a1a1a; padding:10px; margin-bottom:10px; border-radius:6px; }
a { color:#0af; }

.preview-hotspot {
    width: 18px;
    height: 18px;
    border: 3px solid #00ffcc;
    border-radius: 50%;
    background: rgba(0, 255, 200, 0.4);
    transform: translate(-50%, -50%);
}
</style>
</head>
<body>

<a href="admin.php?folder=<?= urlencode($folderName) ?>">← Torna all'admin</a>

<h2><?= htmlspecialchars($folderName) ?> / <?= htmlspecialchars($imageName) ?></h2>

<div id="panorama"></div>

<form method="post">
    <input type="hidden" name="pitch" id="pitch">
    <input type="hidden" name="yaw" id="yaw">

    <label>Testo hotspot</label>
    <input type="text" name="text" required>

    <button type="submit">Salva Hotspot</button>
</form>

<div class="hotspot-list">
<h3>Hotspot esistenti</h3>

<?php foreach ($currentHotspots as $h): ?>
<div class="hotspot-item">
    Pitch: <?= $h['pitch'] ?> |
    Yaw: <?= $h['yaw'] ?> |
    Testo: <?= htmlspecialchars($h['text']) ?>
</div>
<form method="post" style="display:inline;">
    <input type="hidden" name="delete_pitch" value="<?= $h['pitch'] ?>">
    <input type="hidden" name="delete_yaw" value="<?= $h['yaw'] ?>">
    <input type="hidden" name="image" value="<?= htmlspecialchars($image) ?>">
    <button type="submit" style="margin-left:10px; background:#900; color:#fff; border:none; padding:4px 8px;">
        ❌ Elimina
    </button>
</form>

<?php endforeach; ?>

</div>

<script>

const hotspots = <?= json_encode($currentHotspots) ?>;

const viewer = pannellum.viewer('panorama', {
    type: 'equirectangular',
    panorama: '/360/images/<?= $folderName ?>/<?= $imageName ?>',
    autoLoad: true,
    showControls: true,
    hotSpots: hotspots.map(h => ({
        pitch: h.pitch,
        yaw: h.yaw,
        type: "info",
        text: h.text
    }))
});


let previewHotspotId = null;

viewer.on('mouseup', function(event) {

    const coords = viewer.mouseEventToCoords(event);
    if (!coords) return;

    const pitch = coords[0];
    const yaw   = coords[1];

    // aggiorna campi form
    document.getElementById('pitch').value = pitch.toFixed(4);
    document.getElementById('yaw').value   = yaw.toFixed(4);

    // rimuove eventuale hotspot preview precedente
    if (previewHotspotId) {
        viewer.removeHotSpot(previewHotspotId);
        previewHotspotId = null;
    }

    // crea nuovo hotspot preview
    previewHotspotId = "preview";

    viewer.addHotSpot({
        id: previewHotspotId,
        pitch: pitch,
        yaw: yaw,
        type: "info",
        cssClass: "preview-hotspot",
        text: ""
    });

});
</script>

</body>
</html>
