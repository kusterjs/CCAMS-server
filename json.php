<?php

include_once('CCAMS.php');
if (array_key_exists('debug',$_GET)) {
	$CCAMS = new CCAMS('/../cron/bin/',true);
	$CCAMSstats = new CCAMSstats(true);
} else {
	$CCAMS = new CCAMS('/../cron/bin/');
	$CCAMSstats = new CCAMSstats();
}

if (array_key_exists('r',$_GET)) {
	switch ($_GET['r']) {
		case 'get-ranges':
			echo $CCAMS->get_sqwk_ranges();
			break;
		case 'set-ranges':
			$CCAMS->set_sqwk_range($_POST['rangename'],$_POST['ranges']);
			//$CCAMS->set_squawk_range($_GET['rangename'],$_GET['ranges']);
			break;
		case 'squawks':
			echo $CCAMS->get_reserved_codes();
			break;
		case 'logfiles':
			echo $CCAMS->get_logs();
			break;
		case 'stats-daily':
			$CCAMSstats->readStats(strtotime($_POST['date']));
			echo $CCAMSstats->createStats();
			break;
		case 'stats-weekly':
			for ($i=-6; $i<=0; $i++) {
				$CCAMSstats->readStats(strtotime($_POST['date']." ".$i." days"));
			}
			echo $CCAMSstats->createStats();
			break;
		case 'stats-monthly':
			for ($i=-30; $i<=0; $i++) {
				$CCAMSstats->readStats(strtotime($_POST['date']." ".$i." days"));
			}
			echo $CCAMSstats->createStats();
			break;
		case 'stats-yearly':
			for ($i=-365; $i<=0; $i++) {
				$CCAMSstats->readStats(strtotime($_POST['date']." ".$i." days"));
			}
			echo $CCAMSstats->createStats();
			break;
		default:
			echo json_encode(array());
			break;
	}
}
?>