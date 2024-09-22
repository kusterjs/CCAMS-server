<?php

include_once('CCAMS.php');
if (array_key_exists('debug',$_GET)) {
	$CCAMS = new CCAMS(true);
	$CCAMSstats = new CCAMSstats(true);
} else {
	$CCAMS = new CCAMS();
	$CCAMSstats = new CCAMSstats();
}

//$CCAMS->clean_user_cache();

$bin = $CCAMS->read_bin_file('users.bin');
echo "Count: ".count($bin).'<br>';
foreach ($bin as $ip => $cache) {
	echo date("c", $cache[1]).': '.$cache[0].', '.(pow(2,floor((time()-$cache[1])/60))-1).', '.(time()-$cache[1]).'<br>';
}
// echo var_dump($bin);



?>