
<?php

$con = mysqli_connect("192.168.1.226", "", "");


@mysqli_select_db($con, "dealer_order") or die("Unable to select database");

$max_id = mysqli_fetch_object(mysqli_query($con, "select max(messageID) as id from batch_pull_sms"))->id;

$msisdn				= "";
$userID				= "";
$passwd				= "";
$messageID 			= $max_id;

$url = "https://vas.banglalink.net/ems_feedback_data_pull/pull_sms.php?msisdn=" . $msisdn . "&userID=" . $userID . "&passwd=" . $passwd . "&messageID=" . $messageID;


$json = file_get_contents($url);
$obj = json_decode($json, true);

foreach ($obj as $key => $val) {


	$splitID = explode(":", $key);
	$newID = $splitID[1];
	$sender			= $obj[$key]['sender'];




	$rd1 	= explode(" ", $obj[$key]['received_date']);
	$rd2 	= explode("-", $rd1[0]);

	$received_date	= $rd2[2] . "-" . $rd2[1] . "-" . $rd2[0] . " " . $rd1[1];
	$message		= $obj[$key]['message'];





	$sql = "insert into dealer_order.batch_pull_sms 
	(messageID, 
	sender, 
	received_date, 
	message

	)
	values
	(" . $newID . ", 
	'" . $sender . "', 
	'" . $received_date . "',
	'" . $message . "'
)";
	//print($sql);
	//print "<br/>";
	mysqli_query($con, $sql);
}





$sqlprocess = mysqli_query($con, "select * from  batch_pull_sms where process_status=0");


while ($row = mysqli_fetch_object($sqlprocess)) {






	if (strlen($row->sender) == 13) {

		$trimmsg = str_replace(' ', '', $row->message);
		$trimmsg = preg_replace('/[^A-Za-z0-9\-]/', '', $trimmsg);
		$batch_id = (int)$trimmsg;
		$validbc = 0;
		$zone = "";


		if (strlen($batch_id) == 11) {

			$validbc = mysqli_fetch_object(mysqli_query($con, "select count(batch_id) as tot from batch_details where batch_id='" . $batch_id . "'"))->tot;
		} else if (strlen($batch_id) > 8) {

			$batch_id = substr($batch_id, 0, 9);
			$validbc = mysqli_fetch_object(mysqli_query($con, "select count(batch_id) as tot from batch_details where batch_id='" . $batch_id . "'"))->tot;

			$zone = str_replace($batch_id, '', $trimmsg);
			$zone = str_replace('"', '', $zone);
			$zone = str_replace("'", "", $zone);
		}





		$dupbc = mysqli_fetch_object(mysqli_query($con, "select count(batch_id) as tot from batch_registered where batch_id='" . $batch_id . "'"))->tot;



		$message = urlencode("স্বাগতম! আপনার পাম্পটি ওয়ারেন্টি ভুক্ত করা হলো। পেডরোলো");

		if ($validbc == 0) {

			$message = urlencode("দুঃখিত, ওয়ারেন্টি নম্বরটি সঠিক নয়, আবার চেষ্টা করুন।");
		} else if ($dupbc > 0) {

			$message = urlencode("এই ওয়ারেন্টি নম্বরটি আগেই রেজিস্টার করা হয়েছে।");
		} else {


			mysqli_query($con, "insert into batch_registered(phone_no,batch_id,mat_no,created_date,zone)
			(select '" . $row->sender . "',batch_id,mat_no,'" . $row->received_date . "','" . $zone . "' from batch_details where batch_id='" . $batch_id . "')");


			mysqli_query($con, "update batch_details set registered=1 where batch_id='" . $batch_id . "'");
		}

		$sendurl = "https://vas.banglalink.net/sendSMS/sendSMS?msisdn=" . $row->sender . "&message=" . $message . "&userID=" . $userID . "&passwd=" . $passwd . "&sender=Pedrollo";
		$parse_send = file($sendurl);
	}

	mysqli_query($con, "update batch_pull_sms set process_status=1 where messageID=" . $row->messageID);
}

?>

