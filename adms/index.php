<?php
try 
{
	error_reporting(0);
	include "func.php";
	include "../konek.php";

	$url = url();
	$urlpecahtanya = explode('?',$url);
	$totalpecahurl = count(explode('/',$urlpecahtanya[0]));
	$pecahurl = explode('/',$urlpecahtanya[0]);
	$hasilpecahurl = $url;
	for ($x = 0; $x <= $totalpecahurl; $x++) {
	$pecahhasilurl = $pecahurl[$x];
	$get[$pecahhasilurl] = $pecahhasilurl;
	}

	$th = tajar();
	if($get['cdata'] == 'cdata' or $get['fdata'] == 'fdata'){
		$options = $_GET['options'];
		if($options == ''){
			$sn = $_GET['SN'];
			/*$cekserial = cekserialnumber($sn);
			if($cekserial == 'ada'){*/
			header("Content-Type: text/plain");
			header("Content-Lenght: 4");
			header("Connection: close");
			header("Pragma: no-cache");
			header("Cache-Control: no-store");
			$data = "OK";
			echo $data;
			$text = file_get_contents('php://input');
			$data_log_adms = array(
			"url"=>$hasilpecahurl,
			"isi_command"=>$text,
			"return"=>'',
			"cmd"=>'',
			"serial_number"=>$sn,
			"tanggal_akses"=>date('Y-m-d H:i:s')
			);
			$result_log_adms = create("tweb_log_command_adms", $data_log_adms);
			$data_mesin = array(
			"last_online"=>date('Y-m-d H:i:s')
			);
			$result_mesin = update("mesin_absensi", $data_mesin, array("serial_number"=>$sn));
			$pieces = explode("\n", $text);
			/*$ID_SEKOLAH = querydatamesin($sn,"ID_SEKOLAH");*/
			foreach($pieces as $element)
			{
				$pecahtext = explode("\t", $element);
				$pin = (str_replace("\n","", $pecahtext[0]));
				$datetime = (str_replace("\n","", $pecahtext[1]));
				$pecahdatetime = explode(" ", $datetime);
				$date = $pecahdatetime[0];
				$time = $pecahdatetime[1];
				$status = (str_replace("\n","", $pecahtext[2]));
				$suhu = (str_replace("\n","", $pecahtext[8]));
				if(is_numeric($pin) == TRUE){
					$pecahdata = explode("#",cekstatusabsensi($pin));
					$table = $pecahdata[0];
					if($table == 'absensi_guru'){
						$datainsert = array(
						"nip"=> $pin,
						"tanggal"=> $date,
						"jam"=> $time,
						"status"=> $status,
						"keterangan"=> $sn
						);
						$result_insert = create($table, $datainsert);
						

					}elseif($table == 'absensi_siswa'){
						$datainsert = array(
						"nis"=> $pin,
						"tanggal"=> $date,
						"jam"=> $time,
						"status"=> $status,
						"keterangan"=> $sn
						);
						$result_insert = create($table, $datainsert);


					}else{
						$datainsert = array(
						"nip"=> $pin,
						"tanggal"=> $date,
						"jam"=> $time,
						"status"=> $status,
						"keterangan"=> $sn
						);
						$result_insert = create($table, $datainsert);
					}
				
				}
			}
			/*}*/
		}else{
			$sn = $_GET['SN'];
			/*$cekserial = cekserialnumber($sn);
			if($cekserial == 'ada'){*/
			header("Content-Type: text/plain");
			header("Content-Lenght: 190");
			header("Connection: close");
			header("Pragma: no-cache");
			header("Cache-Control: no-store");
			$ErrorDelay = '20';
			$Delay = '20';
			$data = "GET OPTION FROM: $sn\nStamp=None\nOpStamp=None\nErrorDelay=$ErrorDelay\nDelay=$Delay\nTransTimes=00:00;14:05\nTransInterval=1\nTransFlag=1111111111\nRealtime=1\nTimeZone=7\nEncrypt=0";
			echo $data;
			$data_mesin = array(
			"last_online"=>date('Y-m-d H:i:s')
			);
			$result_mesin = update("mesin_absensi", $data_mesin, array("serial_number"=>$sn));
			/*}*/
			$data_log_adms = array(
			"url"=>$hasilpecahurl,
			"isi_command"=>'',
			"return"=>'',
			"cmd"=>'',
			"serial_number"=>$sn,
			"tanggal_akses"=>date('Y-m-d H:i:s')
			);
			$result_log_adms = create("tweb_log_command_adms", $data_log_adms);
			$datauserabsensi['isi_command'] = "CLEAR LOG";
			$datauserabsensi['id_user'] = 'auto';
			$datauserabsensi['ip_mesin'] = $sn;
			$datauserabsensi['tanggal_input'] = date('Y-m-d H:i:s');
			$resultlog = create("tweb_command_adms",$datauserabsensi);
		}
	}

	if($get['getrequest'] == 'getrequest'){
			$sn = $_GET['SN'];
			/*$cekserial = cekserialnumber($sn);
			if($cekserial == 'ada'){*/
			header("Content-Type: text/plain");
			header("Content-Lenght: 2");
			header("Connection: close");
			header("Pragma: no-cache");
			header("Cache-Control: no-store");
			$isi_command = cekdataadms($sn);
			if($isi_command == ''){
				$data .= "OK";
			}else{
				$data .= $isi_command;
			}
			echo $data;
			$data_mesin2 = array(
			"last_online"=>date('Y-m-d H:i:s')
			);
			$result_mesin = update("mesin_absensi", $data_mesin2, array("serial_number"=>$sn));
			$data_log_adms = array(
			"url"=>$hasilpecahurl,
			"isi_command"=>'',
			"return"=>'',
			"cmd"=>'',
			"serial_number"=>$sn,
			"tanggal_akses"=>date('Y-m-d H:i:s')
			);
			$result_log_adms = create("tweb_log_command_adms", $data_log_adms);
			/*}*/
	/*}*/
	}

	if($get['devicecmd'] == 'devicecmd'){
			$sn = $_GET['SN'];
			/*$cekserial = cekserialnumber($sn);
			if($cekserial == 'ada'){*/
			header("Content-Type: text/plain");
			header("Content-Lenght: 4");
			header("Connection: close");
			header("Pragma: no-cache");
			header("Cache-Control: no-store");
			$data .= "OK";
			echo $data;
			$data_mesin = array(
			"last_online"=>date('Y-m-d H:i:s')
			);
			$result_mesin = update("mesin_absensi", $data_mesin, array("serial_number"=>$sn));
			$text = file_get_contents('php://input');
			$pieces = explode("\n", $text);
			foreach($pieces as $element)
			{
				/*$element;*/
				$pecahtext = explode("&", $element);
				$pecahtextid = explode("=", $pecahtext[0]);
				$pecahtextstatus = explode("=", $pecahtext[1]);
				$pecahtextcmd = explode("=", $pecahtext[2]);
				$id = $pecahtextid[1];
				$status = $pecahtextstatus[1];
				$cmd = $pecahtextcmd[1];
				$data_up = array(
				"status_command"=>$status,
				"tanggal_return"=>date('Y-m-d H:i:s')
				);
				$result_up = update("tweb_command_adms", $data_up, array("id"=>$id));
				if($result_up){
					$data_log_adms = array(
					"url"=>$hasilpecahurl,
					"isi_command"=>$text,
					"return"=>$status,
					"cmd"=>$cmd,
					"serial_number"=>$sn,
					"tanggal_akses"=>date('Y-m-d H:i:s')
					);
					$result_log_adms = create("tweb_log_command_adms", $data_log_adms);
				}
			}
			/*}*/
	}

	// hapus log adms mulai 3 bulan ke belakang supaya tidak membebani storage server
	$db_li->query("DELETE FROM tweb_log_command_adms WHERE tanggal_akses < DATE_FORMAT(CURDATE() - INTERVAL 3 MONTH, '%Y-%m-01')");
	
/*$sn = $_GET['SN'];
			$data_log_adms = array(
			"url"=>$hasilpecahurl,
			"isi_command"=>'log',
			"return"=>'',
			"cmd"=>'',
			"serial_number"=>$sn,
			"tanggal_akses"=>date('Y-m-d H:i:s')
			);
			$result_log_adms = create("tweb_log_command_adms", $data_log_adms);*/
			
} 
catch (\Throwable $th) 
{

	// handle error adms
	$error = date('Y-m-d H:i:s').'  '."$th\r\n\n";
	
    return file_put_contents("error_log_adms", $error, FILE_APPEND);
}
?>