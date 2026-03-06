# 360 Viewer -- linkas.it/360

Viewer web per foto sferiche equirettangolari (Insta360) con:

-   Navigazione a fisarmonica (Bootstrap)
-   Viewer 360 fullscreen (Pannellum)
-   Deep link a cartella e immagine
-   Condivisione vista con yaw / pitch / zoom
-   Thumbnail generate automaticamente
-   Gestione descrizioni tramite `meta.json`
-   Pannello admin per editing metadata
-   Utenti admin

------------------------------------------------------------------------

## 📁 Struttura cartelle

    /360
     ├── index.php
     ├── admin.php
     ├── images/
     │    ├── 2026-02-10/
     │    │     ├── img1.jpg
     │    │     ├── img2.jpg
     │    │     └── meta.json
     │    └── 2026-02-15/
     │          ├── img1.jpg
     │          └── meta.json
     └── thumbnails/

------------------------------------------------------------------------

## ⚙️ Requisiti server

-   PHP 7.4+
-   Estensione GD attiva
-   Estensione EXIF consigliata

Verifica:

    php -m | grep gd
    php -m | grep exif

------------------------------------------------------------------------

## 📦 Thumbnail

-   Generate automaticamente alla prima visualizzazione
-   Salvate in `/thumbnails/NOMECARTELLA/`
-   Rigenerate se l'immagine originale viene modificata

------------------------------------------------------------------------

## 🔐 Sicurezza

La cartella /admin viene protetta con la gestione utenti, primo utente (da cancellare) admin/password

------------------------------------------------------------------------

Progetto custom per linkas.it
