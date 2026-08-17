<?php
/*
 * Pintu masuk ADMS jalur eksplisit: http://<server>/adms/index.php
 *
 * Dipakai bila alamat server di mesin diisi lengkap sampai nama berkas.
 * Mesin boleh memanggilnya dengan atau tanpa jalur tambahan:
 *    /adms/index.php                        (aksi disimpulkan dari parameter)
 *    /adms/index.php/iclock/cdata?SN=..     (jalur tambahan / PATH_INFO)
 *    /adms/cdata?SN=..                      (lewat aturan nginx location /adms)
 *
 * Seluruh logikanya ada di includes/adms_handler.php (dipakai bersama dengan
 * pintu masuk /iclock/...).
 */
require __DIR__ . '/../includes/adms_handler.php';
