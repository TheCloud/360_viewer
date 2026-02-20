<?php

$baseDir = __DIR__ . '/images';
$thumbBaseDir = __DIR__ . '/thumbnails';

function sanitize($str) {
    return preg_replace('/[^a-zA-Z0-9-_]/', '', $str);
}

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    exit;
}

$folderName = sanitize($_POST['folder'] ?? '');
if (!$folderName) {
    http_response_code(400);
    exit;
}

$folderPath = $baseDir . '/' . $folderName;
$thumbFolder = $thumbBaseDir . '/' . $folderName;

if (!is_dir($folderPath)) mkdir($folderPath, 0755, true);
if (!is_dir($thumbFolder)) mkdir($thumbFolder, 0755, true);

if (empty($_FILES['images'])) {
    http_response_code(400);
    echo "Nessun file ricevuto (possibile superamento post_max_size)";
    exit;
}

$MAX_TOTAL_BYTES = getPhpUploadLimitBytes();

$totalBytes = 0;
foreach ($_FILES['images']['size'] as $size) {
    $totalBytes += (int)$size;
}

if ($totalBytes > $MAX_TOTAL_BYTES) {
    http_response_code(413);
    echo "Dimensione totale troppo grande";
    exit;
}

foreach ($_FILES['images']['tmp_name'] as $key => $tmpName) {

    if ($_FILES['images']['error'][$key] !== UPLOAD_ERR_OK) {
        continue;
    }

    $originalName = basename($_FILES['images']['name'][$key]);

    if (!preg_match('/\.(jpg|jpeg)$/i', $originalName)) {
        continue;
    }

    $targetPath = $folderPath . '/' . $originalName;

    if (!move_uploaded_file($tmpName, $targetPath)) {
        continue;
    }

    $thumbPath = $thumbFolder . '/' . $originalName;
    createThumbnail($targetPath, $thumbPath, 800);
}

// crea meta.json se non esiste
$metaFile = $folderPath . '/meta.json';
if (!file_exists($metaFile)) {
    $meta = [
        'folder_comment' => '',
        'images' => []
    ];
    file_put_contents(
        $metaFile,
        json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
}

echo "OK";
