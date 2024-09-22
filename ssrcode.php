<?php

include_once('CCAMS.php');

if (array_key_exists('debug',$_GET)) $CCAMS = new CCAMS(true);
else $CCAMS = new CCAMS();
$CCAMS->checks();
echo $CCAMS->request_code();

?>