# 360 Viewer -- linkas.it/360

Viewer web per foto sferiche equirettangolari (Insta360) con:

-   Navigazione a fisarmonica (Bootstrap)
-   Viewer 360 fullscreen (Pannellum)
-   Deep link a cartella e immagine
-   Condivisione vista con yaw / pitch / zoom
-   Thumbnail generate automaticamente
-   Gestione descrizioni tramite `meta.json`
-   Pannello admin per editing metadata

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

## 📸 Come aggiungere foto

1.  Creare una nuova cartella dentro `/images`
2.  Inserire file JPG equirettangolari (export Insta360)
3.  Opzionale: creare `meta.json` oppure usare `/admin.php`

------------------------------------------------------------------------

## 📝 Formato meta.json

    {
      "folder_comment": "Descrizione della giornata",
      "images": {
        "img1.jpg": "Descrizione prima foto",
        "img2.jpg": "Descrizione seconda foto"
      }
    }

------------------------------------------------------------------------

## 🔗 Deep Link supportati

Cartella:

    /360?open=2026-02-10

Immagine:

    /360?open=2026-02-10&img=img1.jpg

Vista salvata:

    /360?open=2026-02-10&img=img1.jpg&yaw=120&pitch=-10&hfov=80

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

Proteggere `/admin.php` con: - Basic Auth - Whitelist IP - Login
semplice

------------------------------------------------------------------------

Progetto custom per linkas.it
