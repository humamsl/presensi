<?php
define("db_core","datacenter");
define("user_core","smpn234jkt");
define("pass_core","0pyPEWvm3jcYxJhWZzrsExE6N");
define("host_core","localhost");
define("port_core","3306");

$db_li_core=new mysqli(host_core,user_core,pass_core,db_core,port_core);
if (!$db_li_core) {
  echo "Koneksi 1 Gagal Periksa konfigurasi koneksi: " . $db_li_core->connect_error();
}
?>