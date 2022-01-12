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
	if (array_key_exists('date', $_POST)) $seldate = new DateTime($_POST['date']);
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
			$CCAMSstats->readStats($seldate);
			echo $CCAMSstats->createStats();
			break;
		case 'stats-weekly':
			$date = clone $seldate;
			$date->sub(new DateInterval('P7D'));
			do {
				$date->add(new DateInterval('P1D'));
				$CCAMSstats->readStats($date);
				//echo $date->format('Y-m-d');
				//echo $seldate->diff($date)->format('%d');
			} while ($seldate->diff($date)->days != 0);
			//echo var_dump($seldate->diff($date)->days);
			echo $CCAMSstats->createStats();
			break;
		case 'stats-monthly':
			$date = clone $seldate;
			$date->sub(new DateInterval('P'.$date->format('j').'D'));
			do {
				$CCAMSstats->readStats($date);
				$date->add(new DateInterval('P1D'));
			} while ($date->format('n') == $seldate->format('n'));
			echo $CCAMSstats->createStats();
			break;
		case 'stats-yearly':

			break;
		default:
			echo json_encode(array());
			break;
	}
}
?>