<?php

function cleanMetaAgainstFilesystem(&$meta, $folderPath) {

    $files = array_merge(
        glob($folderPath . '/*.jpg'),
        glob($folderPath . '/*.jpeg')
    );

    $existing = array_map('basename', $files);

    foreach (['images','panoramas','flats','hotspots'] as $section) {

        if (!isset($meta[$section])) continue;

        foreach ($meta[$section] as $img => $v) {

            if (!in_array($img, $existing)) {
                unset($meta[$section][$img]);
            }

        }
    }
}


function loadMeta($folderPath) {

    $metaFile = $folderPath . '/meta.json';

    $meta = [
        'folder_comment' => '',
        'images' => [],
        'hotspots' => [],
        'panoramas' => [],
        'flats' => []
    ];

    if (file_exists($metaFile)) {
        $decoded = json_decode(file_get_contents($metaFile), true);
        if (is_array($decoded))
            $meta = array_merge($meta, $decoded);
    }

    cleanMetaAgainstFilesystem($meta, $folderPath);

    return $meta;
}

