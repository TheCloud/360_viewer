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

if (empty($_FILES['image'])) {
    http_response_code(400);
    echo "Nessun file ricevuto";
    exit;
}

if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo "Errore upload file";
    exit;
}

$originalName = basename($_FILES['image']['name']);

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $_FILES['image']['tmp_name']);
finfo_close($finfo);

if ($mime !== 'image/jpeg') {
    http_response_code(400);
    echo "File non valido";
    exit;
}

$targetPath = $folderPath . '/' . $originalName;

if (file_exists($targetPath)) {
    $originalName = time() . '_' . $originalName;
    $targetPath = $folderPath . '/' . $originalName;
}

if (!move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
    http_response_code(500);
    echo "Errore salvataggio";
    exit;
}

$thumbPath = $thumbFolder . '/' . $originalName;
createThumbnail($targetPath, $thumbPath, 800);

echo "OK";
