<?php

include_once('CCAMS.php');

if (array_key_exists('debug',$_GET)) $CCAMS = new CCAMS('/../cron/bin/',true);
else $CCAMS = new CCAMS('/../cron/bin/');
$CCAMS->check_reserved_codes();
$CCAMS->clean_squawk_cache();

?>