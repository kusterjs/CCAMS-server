<?php

include_once('CCAMS.php');

if (array_key_exists('debug',$_GET)) $CCAMS = new CCAMS(true);
else $CCAMS = new CCAMS();
$CCAMS->collect_sqwk_range_data();


?>