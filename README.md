# 360 Viewer -- linkas.it/360

Viewer web per foto sferiche equirettangolari (Insta360) e flat con:

-   Navigazione di album
-   Viewer 360 fullscreen
-   Deep link a cartella e immagine
-   Condivisione vista con yaw / pitch / zoom
-   Thumbnail generate automaticamente
-   Gestione descrizioni
-   Pannello admin per editing metadata
-   Utenti viewer (con selezione album)
-   Utenti admin

------------------------------------------------------------------------

## ⚙️ Requisiti server

-   PHP 7.4+
-   Estensione GD attiva
-   Estensione EXIF consigliata

Verifica:

    php -m | grep gd
    php -m | grep exif

------------------------------------------------------------------------

## 🔐 Sicurezza

La cartella /admin viene protetta con la gestione utenti, primo utente (da cancellare) admin/password
