<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config.php';

$baseDir = IMAGES_DIR;

$folder = $_GET['folder'] ?? null;
$image  = $_GET['image'] ?? null;

if (!$folder || !$image) die("Parametri mancanti");

$folderName = basename($folder);
$imageName  = basename($image);

$folderPath = $baseDir . '/' . $folderName;
$imagePath  = $folderPath . '/' . $imageName;

if (!is_dir($folderPath) || !file_exists($imagePath))
    die("Cartella o immagine non valida");

$metaFile = $folderPath . '/meta.json';

$meta = loadMeta($folderPath);
$isFlat = $meta['flats'][$imageName] ?? false;

/* ================= DELETE / SAVE ================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($meta['hotspots'][$imageName])) {
        $meta['hotspots'][$imageName] = [];
    }

    /* ================= DELETE ================= */

    if ($isFlat && isset($_POST['delete_x'], $_POST['delete_y'])) {

        $xToDelete = (float)$_POST['delete_x'];
        $yToDelete = (float)$_POST['delete_y'];

        foreach ($meta['hotspots'][$imageName] as $key => $hs) {
            if (isset($hs['x'], $hs['y']) &&
                (float)$hs['x'] === $xToDelete &&
                (float)$hs['y'] === $yToDelete) {
                unset($meta['hotspots'][$imageName][$key]);
                break;
            }
        }

        $meta['hotspots'][$imageName] =
            array_values($meta['hotspots'][$imageName]);

        file_put_contents(
            $metaFile,
            json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    if (!$isFlat && isset($_POST['delete_pitch'], $_POST['delete_yaw'])) {

        $pitchToDelete = (float)$_POST['delete_pitch'];
        $yawToDelete   = (float)$_POST['delete_yaw'];

        foreach ($meta['hotspots'][$imageName] as $key => $hs) {
            if (isset($hs['pitch'], $hs['yaw']) &&
                (float)$hs['pitch'] === $pitchToDelete &&
                (float)$hs['yaw'] === $yawToDelete) {
                unset($meta['hotspots'][$imageName][$key]);
                break;
            }
        }

        $meta['hotspots'][$imageName] =
            array_values($meta['hotspots'][$imageName]);

        file_put_contents(
            $metaFile,
            json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    /* ================= SAVE ================= */

    $pitch  = floatval($_POST['pitch']);
    $yaw    = floatval($_POST['yaw']);
    $text   = trim($_POST['text']);
    $type   = $_POST['type'] ?? 'info';
    $target = $_POST['target'] ?? '';

    if ($isFlat) {
        $entry = [
            'x'    => $pitch,
            'y'    => $yaw,
            'text' => $text
        ];
    } else {
        $entry = [
            'pitch' => $pitch,
            'yaw'   => $yaw,
            'text'  => $text
        ];
    }

    if ($type === 'link' && !empty($target)) {
        $entry['type']   = 'link';
        $entry['target'] = basename($target);
    }

    if ($type === 'url' && !empty($_POST['url'])) {
        $entry['type']   = 'url';
        $entry['target'] = trim($_POST['url']);
    }

    $meta['hotspots'][$imageName][] = $entry;

    file_put_contents(
        $metaFile,
        json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );

    header("Location: admin_hotspots.php?folder=" .
        urlencode($folderName) .
        "&image=" . urlencode($imageName));
    exit;
}

$currentHotspots = $meta['hotspots'][$imageName] ?? [];

/* immagini disponibili */
$allImages = array_merge(
    glob($folderPath . '/*.jpg'),
    glob($folderPath . '/*.jpeg')
);
?>

<!DOCTYPE html>
<html>
<head>
<link rel="stylesheet" href="<?= APP_BASE_URL ?>/css/hotspots.css">
<meta charset="UTF-8">
<title>Hotspot Editor</title>

<link rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.css"/>
<script src="https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.js"></script>

<link rel="stylesheet"
href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
body { background:#111; color:#fff; font-family:Arial; margin:0; }
.page { max-width:1200px; margin:auto; padding:30px; }

.top-bar {
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:20px;
}

.back-btn { color:#0af; text-decoration:none; }

.card {
    background:#1a1a1a;
    border:1px solid #333;
    border-radius:10px;
    padding:20px;
    margin-bottom:25px;
    position:relative;
    z-index:0;
}
#panorama {
    width:100%;
    height:65vh;
    border-radius:8px;
    position:relative;
    z-index:1;
}

.form-row {
    display:flex;
    gap:20px;
    flex-wrap:wrap;
    align-items:end;
}

.pnlm-hotspot-base {
    pointer-events: auto !important;
}

.form-col { min-width:220px; }
.flex-grow { flex:1; }

input, select {
    width:100%;
    padding:10px;
    background:#222;
    border:1px solid #444;
    color:#fff;
    border-radius:6px;
}

button {
    padding:8px 14px;
    border:none;
    border-radius:6px;
    cursor:pointer;
}

.primary-btn { background:#28a745; margin-top:15px; }
.danger-btn { background:#900; color:#fff; }

.hotspot-row {
    display:flex;
    justify-content:space-between;
    align-items:center;
    background:#222;
    padding:12px;
    border-radius:8px;
    margin-bottom:10px;
}
</style>
</head>

<body>

<div class="page">

<div class="top-bar">
    <a class="back-btn"
       href="admin.php?folder=<?= urlencode($folderName) ?>">
        ← Torna
    </a>
    <h2><?= htmlspecialchars($folderName) ?> / <?= htmlspecialchars($imageName) ?></h2>
</div>

<div class="card">
    <div id="panorama"></div>
</div>

<div class="card">
    <h3>Nuovo Hotspot</h3>

    <form method="post">
        <input type="hidden" name="pitch" id="pitch">
        <input type="hidden" name="yaw" id="yaw">

        <div class="form-row">
            <div class="form-col">
                <label>Tipo</label>
                <select name="type" id="typeSelect">
                    <option value="info">Solo testo</option>
                    <option value="link">Link ad altra immagine</option>
                    <option value="url">Link esterno</option>
                </select>
            </div>

            <div class="form-col" id="linkCol">
                <label>Destinazione</label>
                <select name="target" id="targetSelect">
                    <option value="">-- Seleziona --</option>
                    <?php foreach ($allImages as $imgFile):
                        $imgName = basename($imgFile);
                        if ($imgName === $imageName) continue;
                        $label = !empty($meta['images'][$imgName])
                            ? $meta['images'][$imgName]
                            : $imgName;
                    ?>
                    <option value="<?= $imgName ?>"
                            data-label="<?= htmlspecialchars($label) ?>">
                        <?= htmlspecialchars($label) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-col flex-grow">
                <label>Testo</label>
                <input type="text" name="text"
                       id="textInput" required>
            </div>

            <div class="form-col" id="urlCol" style="display:none;">
                <label>URL</label>
                <input type="text" name="url" id="urlInput"
                placeholder="https://...">
            </div>
        </div>

        <button type="submit"
                class="primary-btn">
            Salva Hotspot
        </button>
    </form>
</div>

<div class="card">
<h3>Hotspot esistenti</h3>

<?php foreach ($currentHotspots as $h): ?>
<div class="hotspot-row">
<div>
    <strong><?= htmlspecialchars($h['text']) ?></strong>

    <?php if ($isFlat): ?>
        (<?= $h['x'] ?> / <?= $h['y'] ?>)
    <?php else: ?>
        (<?= $h['pitch'] ?> / <?= $h['yaw'] ?>)
    <?php endif; ?>

    <?php if (!empty($h['target'])): ?>
        → <?= htmlspecialchars($h['target']) ?>
    <?php endif; ?>
</div>
    <form method="post">
        <?php if ($isFlat): ?>
            <input type="hidden" name="delete_x" value="<?= $h['x'] ?>">
            <input type="hidden" name="delete_y" value="<?= $h['y'] ?>">
        <?php else: ?>
            <input type="hidden" name="delete_pitch" value="<?= $h['pitch'] ?>">
            <input type="hidden" name="delete_yaw" value="<?= $h['yaw'] ?>">
        <?php endif; ?>
        <button class="danger-btn">Elimina</button>
    </form>
</div>
<?php endforeach; ?>

</div>

</div>

<script>

const hotspots = <?= json_encode($currentHotspots) ?>;
const is360 = <?= json_encode($meta['panoramas'][$imageName] ?? false) ?>;
const isFlat = <?= json_encode($meta['flats'][$imageName] ?? false) ?>;

let viewer = null;
let flatMap = null;
let previewId = null;

if (is360) {

    let config = {
        type: 'equirectangular',
        panorama: '<?= IMAGES_URL ?>/<?= $folderName ?>/<?= $imageName ?>',
        autoLoad: true,
        showControls: true,

        hotSpots: hotspots.map(h => {

            if (h.type === "link" && h.target) {
                return {
                    pitch: h.pitch,
                    yaw: h.yaw,
                    type: "info",
                    cssClass: "hotspot link",
                    text: h.text,
                    clickHandlerFunc: function() {
                        window.location.href =
                        "admin_hotspots.php?folder=<?= urlencode($folderName) ?>&image=" +
                        encodeURIComponent(h.target);
                    }
                };
            }

            if (h.type === "url" && h.target) {
                return {
                    pitch: h.pitch,
                    yaw: h.yaw,
                    type: "info",
                    cssClass: "hotspot url",
                    text: h.text,
                    clickHandlerFunc: function() {
                        window.open(h.target, "_blank");
                    }
                };
            }

            return {
                pitch: h.pitch,
                yaw: h.yaw,
                type: "info",
                cssClass: "hotspot info",
                text: h.text
            };

        })
    };

    viewer = pannellum.viewer('panorama', config);

    viewer.on('mouseup', function(event) {

        const coords = viewer.mouseEventToCoords(event);
        if (!coords) return;

        const pitch = coords[0];
        const yaw   = coords[1];

        document.getElementById('pitch').value = pitch.toFixed(4);
        document.getElementById('yaw').value   = yaw.toFixed(4);

        if (previewId) {
            viewer.removeHotSpot(previewId);
        }

        previewId = "preview";

        viewer.addHotSpot({
            id: previewId,
            pitch: pitch,
            yaw: yaw,
            cssClass: "hotspot preview"
        });

    });

} else {

    const imageUrl = '<?= IMAGES_URL ?>/<?= $folderName ?>/<?= $imageName ?>';

    const img = new Image();

    img.onload = function() {

        const width  = img.width;
        const height = img.height;

        flatMap = L.map('panorama', {
            crs: L.CRS.Simple,
            minZoom: -2,
            maxZoom: 2,
            zoomSnap: 0.1
        });

        const bounds = [[0,0],[height,width]];

        L.imageOverlay(imageUrl, bounds).addTo(flatMap);
        flatMap.fitBounds(bounds);
        flatMap.setMaxBounds(bounds);

        hotspots.forEach(h => {

            const x = parseFloat(h.x) * width;
            const y = parseFloat(h.y) * height;

            let cssClass = 'hotspot info';
            if (h.type === 'link') cssClass = 'hotspot link';
            if (h.type === 'url')  cssClass = 'hotspot url';

            const icon = L.divIcon({
                className: '',
                html: '<div class="' + cssClass + '"></div>'
            });

            const marker = L.marker([y, x], {icon}).addTo(flatMap);

            if (h.type === "link" && h.target) {
                marker.on("click", () => {
                    window.location.href =
                    "admin_hotspots.php?folder=<?= urlencode($folderName) ?>&image=" +
                    encodeURIComponent(h.target);
                });
            }

            if (h.type === "url" && h.target) {
                marker.on("click", () => window.open(h.target, "_blank"));
            }

            if (h.text) {
                marker.bindTooltip(h.text);
            }

        });

        flatMap.on("click", function(e) {

            const lat = e.latlng.lat;
            const lng = e.latlng.lng;

            const x = lng / width;
            const y = lat / height;

            if (x < 0 || x > 1 || y < 0 || y > 1) return;

            document.getElementById('pitch').value = x.toFixed(6);
            document.getElementById('yaw').value   = y.toFixed(6);

            if (previewId) {
                flatMap.removeLayer(previewId);
            }

            const previewIcon = L.divIcon({
                className: '',
                html: '<div class="hotspot preview"></div>'
            });

            previewId = L.marker([lat, lng], {icon: previewIcon}).addTo(flatMap);

        });
    };

    img.src = imageUrl;
}

/* UI logic */

const typeSelect=document.getElementById('typeSelect');
const linkCol=document.getElementById('linkCol');
const targetSelect=document.getElementById('targetSelect');
const textInput=document.getElementById('textInput');
const urlCol = document.getElementById('urlCol');

let autoFilled=false;

targetSelect.addEventListener('change',function(){
    if(typeSelect.value!=='link')return;
    const sel=this.options[this.selectedIndex];
    if(autoFilled||!textInput.value.trim()){
        textInput.value=sel.dataset.label||sel.text;
        autoFilled=true;
    }
});

textInput.addEventListener('input',()=>autoFilled=false);

function toggle(){

    linkCol.style.display =
        (typeSelect.value==='link') ? 'block':'none';

    urlCol.style.display =
        (typeSelect.value==='url') ? 'block':'none';

}

typeSelect.addEventListener('change',toggle);
toggle();

</script>

</body>
</html>
