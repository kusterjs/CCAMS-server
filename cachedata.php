<?php

include_once('CCAMS.php');
if (array_key_exists('debug',$_GET)) {
	$CCAMS = new CCAMS('/../cron/bin/',true);
	$CCAMSstats = new CCAMSstats(true);
} else {
	$CCAMS = new CCAMS('/../cron/bin/');
	$CCAMSstats = new CCAMSstats();
}

//$CCAMS->clean_user_cache();

$bin = $CCAMS->read_cache_file('/cache/users.bin');
echo "Count: ".count($bin).'<br>';
foreach ($bin as $ip => $cache) {
	echo date("c", $cache[1]).': '.$cache[0].', '.(pow(2,floor((time()-$cache[1])/60))-1).', '.(time()-$cache[1]).'<br>';
}
// echo var_dump($bin);



?>