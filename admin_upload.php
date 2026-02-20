<?php

function toBytes($val) {
    $val = trim($val);
    $unit = strtolower(substr($val, -1));
    $num = (int)$val;

    switch($unit) {
        case 'g': return $num * 1024 * 1024 * 1024;
        case 'm': return $num * 1024 * 1024;
        case 'k': return $num * 1024;
        default:  return (int)$val;
    }
}

$uploadMax = ini_get('upload_max_filesize');
$postMax   = ini_get('post_max_size');
$memoryMax = ini_get('memory_limit');

$effectiveBytes = min(toBytes($uploadMax), toBytes($postMax));
$effectiveMB = round($effectiveBytes / 1024 / 1024, 1);

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Upload 360</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background:#111; color:#fff; }
.drop-zone {
    border:2px dashed #666;
    padding:50px;
    text-align:center;
    border-radius:10px;
    cursor:pointer;
}
.drop-zone.dragover {
    background:#222;
    border-color:#0af;
}
.progress { height:25px; }
</style>
<?php
function getPhpUploadLimitBytes() {

    $upload = ini_get('upload_max_filesize');
    $post   = ini_get('post_max_size');

    $toBytes = function($val) {
        $val = trim($val);
        $unit = strtolower(substr($val, -1));
        $num = (int)$val;

        switch($unit) {
            case 'g': return $num * 1024 * 1024 * 1024;
            case 'm': return $num * 1024 * 1024;
            case 'k': return $num * 1024;
            default:  return (int)$val;
        }
    };

    return min($toBytes($upload), $toBytes($post));
}

$maxUploadBytes = getPhpUploadLimitBytes();
?>
<script>
const PHP_MAX_UPLOAD = <?= $maxUploadBytes ?>;
</script>
</head>
<body class="container py-4">
<div class="mb-3">
    <a href="admin.php" class="btn btn-outline-light btn-sm">
        ← Torna all’admin
    </a>
</div>
<h1>Upload Foto 360</h1>
<div class="alert alert-info">
    <!-- <strong>Limiti server:</strong><br>
    upload_max_filesize: <?= $uploadMax ?><br>
    post_max_size: <?= $postMax ?><br>
    memory_limit: <?= $memoryMax ?><br>
    <hr>-->
    <strong>Dimensione massima effettiva per upload:</strong> <?= $effectiveMB ?> MB
</div>
<div class="mb-3">
<label>Nome cartella (es: 2026-02-20)</label>
<input type="text" id="folderName" class="form-control" placeholder="<?=date("Y-m-d");?>">
</div>

<div id="dropZone" class="drop-zone">
Trascina qui le immagini JPG oppure clicca
<input type="file" id="fileInput" multiple accept=".jpg,.jpeg" hidden>
</div>

<div class="progress mt-4 d-none" id="progressWrapper">
<div class="progress-bar" id="progressBar" role="progressbar" style="width:0%">0%</div>
</div>

<div id="result" class="mt-3"></div>

<script>
const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');
const progressBar = document.getElementById('progressBar');
const progressWrapper = document.getElementById('progressWrapper');
const result = document.getElementById('result');

dropZone.addEventListener('click', () => fileInput.click());

dropZone.addEventListener('dragover', e => {
    e.preventDefault();
    dropZone.classList.add('dragover');
});

dropZone.addEventListener('dragleave', () => {
    dropZone.classList.remove('dragover');
});

dropZone.addEventListener('drop', e => {
    e.preventDefault();
    dropZone.classList.remove('dragover');
    uploadFiles(e.dataTransfer.files);
});

fileInput.addEventListener('change', () => {
    uploadFiles(fileInput.files);
});

function uploadFiles(files) {

    const folderName = document.getElementById('folderName').value.trim();
    if (!folderName) {
        alert("Inserisci nome cartella");
        return;
    }

    // CONTROLLO DIMENSIONE TOTALE
    const MAX_TOTAL_MB = 100;
    let totalBytes = 0;

    for (let i = 0; i < files.length; i++) {
        totalBytes += files[i].size;
    }

if (totalBytes > PHP_MAX_UPLOAD) {

    const maxMB = (PHP_MAX_UPLOAD / (1024*1024)).toFixed(1);

    alert("Limite massimo consentito: " + maxMB + " MB");
    return;
}
    const formData = new FormData();
    formData.append('folder', folderName);

    for (let i = 0; i < files.length; i++) {
        formData.append('images[]', files[i]);
    }

    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'upload_handler.php', true);

    xhr.upload.onprogress = function(e) {
        if (e.lengthComputable) {
            const percent = Math.round((e.loaded / e.total) * 100);
            progressWrapper.classList.remove('d-none');
            progressBar.style.width = percent + '%';
            progressBar.innerText = percent + '%';
        }
    };

    xhr.onload = function() {
        if (xhr.status === 200) {
            result.innerHTML = '<div class="alert alert-success">Upload completato</div>';
            progressBar.style.width = '0%';
            progressBar.innerText = '0%';
        } else {
            result.innerHTML = '<div class="alert alert-danger">Errore upload (' + xhr.status + ')</div>';
        }
    };

    xhr.send(formData);
}
</script>

</body>
</html>
