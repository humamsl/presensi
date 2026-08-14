<?php

@session_start();
date_default_timezone_set("Asia/Jakarta");
set_time_limit(0);

//fungsi create
function create($table, $data) {
    include('konek.php');
    foreach ($data as $field => $value) {
        $values[] = "'" . addslashes($data[$field]) . "'";
        $fields[] = "`" . $field . "`";
    }
    $value_list = join(",", $values);
    $field_list = join(",", $fields);
    $query = "INSERT INTO " . $table . " (" . $field_list . ") VALUES (" . $value_list . ")";
    $hasil = $db_li->query($query);
    return $hasil;
}

//fungsi createOnUpdate
function createOnUpdate($table, $data) {
    include('konek.php');
    foreach ($data as $field => $value) {
        $values[] = "'" . addslashes($data[$field]) . "'";
        $fields[] = "`" . $field . "`";
        $dt[] = "`" . $field . "` = '" . addslashes($data[$field]) . "' ";
    }
    $value_list = join(",", $values);
    $field_list = join(",", $fields);
    $data_list = join(",", $dt);
    
    $query = "INSERT INTO " . $table . " (" . $field_list . ") VALUES (" . $value_list . ")
        ON DUPLICATE KEY UPDATE " . $data_list;
    $hasil = $db_li->query($query) or die(mysqli_error($db_li));
    return $hasil;
}

//fungsi edit
function update($table, $data, $where) {
    include('konek.php');
    foreach ($where as $f_where => $v_where) {
        $vws[] = "`" . $f_where . "` = '" . addslashes($where[$f_where]) . "'";
    }
    foreach ($data as $field => $value) {
        $dt[] = "`" . $field . "` = '" . addslashes($data[$field]) . "' ";
    }
    $data_list = join(",", $dt);
    $where_list = join(" AND ", $vws);
    $query = "UPDATE $table SET $data_list WHERE $where_list ";
    $hasil = $db_li->query($query);
    return $hasil;
}

//fungsi delete
function delete($table, $where) {
    include('konek.php');
    foreach ($where as $f_where => $v_where) {
        $vws[] = "`" . $f_where . "` = '" . addslashes(trim($where[$f_where])) . "'";
    }
    $where_list = join(" AND ", $vws);
    $query = "DELETE FROM $table WHERE $where_list ";
    $hasil = $db_li->query($query);
    return $hasil;
}

function sql($sql) {
    include('konek.php');
    $hasil = $db_li->query($sql);
    return $hasil;
}

function query($table, $where) {
    include 'konek.php';
    if ($where != '') {
        foreach ($where as $f_where => $v_where) {
            $vws[] = "`" . $f_where . "` = '" . $where[$f_where] . "'";
        }
        $where_list = join(" AND ", $vws);
        $query = "SELECT * FROM $table WHERE $where_list";
    } else {
        $query = "SELECT * FROM $table";
    }
    $result = $db_li->query($querys);
    return $result;
}

function tajar() {
        if (date('m') > 6) {
            $th = date('Y') - 1994;
        } else {
            $th = date('Y') - 1995;
        }
    return $th;
}

function url(){
  return sprintf(
    "%s://%s%s",
    isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http',
    $_SERVER['SERVER_NAME'],
    $_SERVER['REQUEST_URI']
  );
}

//fungsi perintah command adms
function cekdataadms($serial) {
    include 'konek.php';
    $ip = querydatamesin($serial,"ip");
    $querys = "SELECT * FROM tweb_command_adms WHERE ip_mesin = '$ip' AND status_command IS NULL order by tanggal_input asc limit 10";
    $result = $db_li->query($querys);
    $numrow = $result->num_rows;
	while($row = $result->fetch_assoc()){
		if($row['isi_command'] == "REBOOT"){
			$id = 1;
		}else{
			$id = $row['id'];
		}
		
		$command .= nl2br("C:$id:".$row['isi_command']);
	}
    return $command;
}

//fungsi perintah cek serial number
function cekserialnumber($serial) {
    include '../konek.php';
    $querys = "SELECT * FROM mesin_absensi WHERE serial_number = '$serial'";
    $result = $db_li->query($querys);
    $numrow = $result->num_rows;
	if($numrow > 0){
		$cekdata = "ada";
	}else{
		$cekdata = "tidak ada";
	}
    return $cekdata;
}

//fungsi perintah cek status pegawai atau siswa
function cekstatusabsensi($PIN, $ID_SEKOLAH=false) {
    include 'konek_pusat_data.php';
    $querys = "SELECT id FROM guru WHERE nip = '$PIN'";
    $result = $db_li_core->query($querys);
    $numrow = $result->num_rows;
	if($numrow > 0){
		$row = $result->fetch_assoc();
		$cekdata = "absensi_guru";
		$id = $row['id'];
	}else{
		 $querys = "SELECT nis,id FROM siswa WHERE nis = '$PIN'";
		$result = $db_li_core->query($querys);
		$numrow = $result->num_rows;
		if($numrow > 0){
			$row = $result->fetch_assoc();
			$cekdata = "absensi_siswa";
			$id = $row['id'];
		}else{
			$cekdata = "absensi_guru";
			$id = '';
		}
	}
    return $cekdata.'#'.$id;
}

/*function cekstatusabsensi($PIN,$ID_SEKOLAH) {
    include 'konek_pusat_data.php';
    $querys = "SELECT ID_PEGAWAI FROM t_pegawai WHERE NO_INDUK = '$PIN' AND ID_SEKOLAH = '$ID_SEKOLAH'";
    $result = $db_li_core->query($querys);
    $numrow = $result->num_rows;
	if($numrow > 0){
		$cekdata = "tweb_pegawai_hadir";
	}else{
		 $querys = "SELECT ID_SISWA FROM t_siswa WHERE NO_INDUK = '$PIN' AND ID_SEKOLAH = '$ID_SEKOLAH'";
		$result = $db_li_core->query($querys);
		$numrow = $result->num_rows;
		if($numrow > 0){
			$cekdata = "tweb_siswa_hadir";
		}else{
			$cekdata = "tweb_absensi";
		}
	}
    return $cekdata;
}*/

//fungsi query data mesin
function querydatamesin($serial,$var) {
    include 'konek.php';
    $querys = "SELECT * FROM mesin_absensi WHERE serial_number = '$serial'";
    $result = $db_li->query($querys);
    $numrow = $result->num_rows;
	$row = $result->fetch_assoc();
    return $row[$var];
}
?>