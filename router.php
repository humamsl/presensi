<?php
/*
 * Router untuk PHP built-in server (php -S), dipakai saat pengembangan.
 * Meniru aturan .htaccess: /iclock/<apa saja> -> iclock/index.php.
 * Apache/Laragon tidak memakai file ini (pakai iclock/.htaccess).
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (preg_match('#^/iclock(/|$)#', $path)) {
    require __DIR__ . '/iclock/index.php';
    return true;
}

// File nyata disajikan apa adanya
return file_exists(__DIR__ . $path) && !is_dir(__DIR__ . $path) ? false : false;
