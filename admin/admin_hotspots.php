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

$meta = [
    'folder_comment' => '',
    'images' => [],
    'hotspots' => []
];

if (file_exists($metaFile)) {
    $raw = file_get_contents($metaFile);
    $decoded = json_decode($raw, true);
    if (is_array($decoded))
        $meta = array_merge($meta, $decoded);
}

/* ================= DELETE ================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['delete_pitch'], $_POST['delete_yaw'])) {

        $pitchToDelete = (float)$_POST['delete_pitch'];
        $yawToDelete   = (float)$_POST['delete_yaw'];

        if (!empty($meta['hotspots'][$imageName])) {
            foreach ($meta['hotspots'][$imageName] as $key => $hs) {
                if ((float)$hs['pitch'] === $pitchToDelete &&
                    (float)$hs['yaw'] === $yawToDelete) {
                    unset($meta['hotspots'][$imageName][$key]);
                    break;
                }
            }
            $meta['hotspots'][$imageName] =
                array_values($meta['hotspots'][$imageName]);
        }

        file_put_contents($metaFile,
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

    if (!isset($meta['hotspots'][$imageName]))
        $meta['hotspots'][$imageName] = [];

    $entry = [
        'pitch' => $pitch,
        'yaw'   => $yaw,
        'text'  => $text
    ];

    if ($type === 'link' && !empty($target)) {
        $entry['type']   = 'link';
        $entry['target'] = basename($target);
    }

 if ($type === 'url' && !empty($_POST['url'])) {
    $entry['type']   = 'url';
    $entry['target'] = trim($_POST['url']);
}

    $meta['hotspots'][$imageName][] = $entry;

    file_put_contents($metaFile,
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
<meta charset="UTF-8">
<title>Hotspot Editor</title>

<link rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.css"/>
<script src="https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.js"></script>

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

.info-hotspot {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: radial-gradient(circle,
        rgba(255,0,0,0.95) 30%,
        rgba(255,0,0,0.4) 70%,
        transparent 100%);
    box-shadow: 0 0 12px rgba(255,0,0,0.9);
    transform: translate(-50%, -50%);
    position: absolute;
}

.link-hotspot {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: radial-gradient(circle,
        rgba(255,180,0,0.95) 30%,
        rgba(255,120,0,0.6) 70%,
        transparent 100%);
    box-shadow: 0 0 14px rgba(255,120,0,0.9);
    transform: translate(-50%, -50%);
    position: absolute;
}

.pnlm-hotspot-base {
    pointer-events: auto !important;
}

.preview-hotspot {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: rgba(0, 255, 200, 0.9);
    box-shadow: 0 0 12px rgba(0,255,200,1);
    position: absolute;
    transform: translate(-50%, -50%);
    z-index: 9999 !important;
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
    (<?= $h['pitch'] ?> / <?= $h['yaw'] ?>)
    <?php if (!empty($h['target'])): ?>
        → <?= htmlspecialchars($h['target']) ?>
    <?php endif; ?>
</div>
    <form method="post">
        <input type="hidden"
               name="delete_pitch"
               value="<?= $h['pitch'] ?>">
        <input type="hidden"
               name="delete_yaw"
               value="<?= $h['yaw'] ?>">
        <button class="danger-btn">Elimina</button>
    </form>
</div>
<?php endforeach; ?>

</div>

</div>

<script>

const hotspots = <?= json_encode($currentHotspots) ?>;
const is360 = <?= json_encode($meta['panoramas'][$imageName] ?? false) ?>;

let viewer = null;
let previewId = null;

if (is360) {

    viewer = pannellum.viewer('panorama', {
        type:'equirectangular',
        panorama:'<?= IMAGES_URL ?>/<?= $folderName ?>/<?= $imageName ?>',
        autoLoad:true,
        showControls:true,
        hotSpots: hotspots.map(h => {

            if (h.type === "link" && h.target) {
                return {
                    pitch: h.pitch,
                    yaw: h.yaw,
                    type: "info",
                    cssClass: "link-hotspot",
                    text: h.text,
                    clickHandlerFunc: function() {
                        window.location.href =
                        "admin_hotspots.php?folder=<?= urlencode($folderName) ?>&image=" +
                        encodeURIComponent(h.target);
                    }
                };
            }

            return {
                pitch: h.pitch,
                yaw: h.yaw,
                type: "info",
                cssClass: "info-hotspot",
                text: h.text
            };

        })
    });

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
            cssClass: "preview-hotspot",
            createTooltipFunc: function(div) {
                div.classList.add("preview-hotspot");
            }
        });

    });

} else {

    const container = document.getElementById("panorama");

    container.innerHTML =
        '<div id="flatEditor" style="position:relative;width:100%;height:100%;">' +
        '<img id="flatImage" src="<?= IMAGES_URL ?>/<?= $folderName ?>/<?= $imageName ?>" ' +
        'style="width:100%;height:100%;object-fit:contain;">' +
        '</div>';

    const img = document.getElementById("flatImage");
    const wrap = document.getElementById("flatEditor");

    img.onload = function(){

        hotspots.forEach(h => {

        const naturalRatio = img.naturalWidth / img.naturalHeight;
const boxRatio     = wrap.clientWidth / wrap.clientHeight;

let drawWidth, drawHeight, offsetX = 0, offsetY = 0;

if (naturalRatio > boxRatio) {
    drawWidth  = wrap.clientWidth;
    drawHeight = wrap.clientWidth / naturalRatio;
    offsetY = (wrap.clientHeight - drawHeight) / 2;
} else {
    drawHeight = wrap.clientHeight;
    drawWidth  = wrap.clientHeight * naturalRatio;
    offsetX = (wrap.clientWidth - drawWidth) / 2;
}

hotspots.forEach(h => {

    const pos = panoToFlat(h.pitch, h.yaw);

    const dot = document.createElement("div");

    dot.className = (h.type === "link" || h.type === "url")
        ? "link-hotspot"
        : "info-hotspot";

    dot.style.position = "absolute";
    dot.style.left = (offsetX + (pos.x / 100) * drawWidth) + "px";
    dot.style.top  = (offsetY + (pos.y / 100) * drawHeight) + "px";

    wrap.appendChild(dot);

});

        });

    };

    img.addEventListener("click", function(e){

    const rect = img.getBoundingClientRect();

    const naturalRatio = img.naturalWidth / img.naturalHeight;
    const boxRatio     = rect.width / rect.height;

    let drawWidth, drawHeight, offsetX = 0, offsetY = 0;

    if (naturalRatio > boxRatio) {

        drawWidth  = rect.width;
        drawHeight = rect.width / naturalRatio;
        offsetY = (rect.height - drawHeight) / 2;

    } else {

        drawHeight = rect.height;
        drawWidth  = rect.height * naturalRatio;
        offsetX = (rect.width - drawWidth) / 2;

    }

    const x = (e.clientX - rect.left - offsetX) / drawWidth;
    const y = (e.clientY - rect.top  - offsetY) / drawHeight;

    if (x < 0 || x > 1 || y < 0 || y > 1) return;

    const yaw   = x * 360 - 180;
    const pitch = 90 - y * 180;

    document.getElementById('pitch').value = pitch.toFixed(4);
    document.getElementById('yaw').value   = yaw.toFixed(4);

    /* rimuove preview precedente */
    if (previewId) previewId.remove();

    const preview = document.createElement("div");
preview.className = "preview-hotspot";
preview.style.position = "absolute";

preview.style.left = (offsetX + x * drawWidth) + "px";
preview.style.top  = (offsetY + y * drawHeight) + "px";

wrap.appendChild(preview);
previewId = preview;

    wrap.appendChild(preview);

    previewId = preview;

    });

}

function panoToFlat(pitch, yaw){

    const x = (yaw + 180) / 360 * 100;
    const y = (90 - pitch) / 180 * 100;

    return {x,y};
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
