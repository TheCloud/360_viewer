<?php
error_reporting(E_ERROR | E_WARNING | E_PARSE);


$isHttps =
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || $_SERVER['SERVER_PORT'] == 443;

define('APP_SCHEME', $isHttps ? 'https' : 'http');
define('APP_HOST', $_SERVER['HTTP_HOST']);


// Path fisico root dell'app
//define('APP_ROOT', dirname(__DIR__) . '/360');



// Se config.php è già dentro /360 puoi fare:
define('APP_ROOT', __DIR__);

// Cartelle fisiche
define('IMAGES_DIR', APP_ROOT . '/images');
define('THUMB_DIR', APP_ROOT . '/thumbnails');

// URL base auto-rilevata
define('APP_BASE_URL', "");
define('IMAGES_URL', APP_BASE_URL . '/images');
define('THUMB_URL', APP_BASE_URL . '/thumbnails');

// Token
define('SECRET_KEY', 'LA_TUA_CHIAVE');
