<?php
include_once('CCAMS.php');

if (array_key_exists('debug',$_GET)) $CCAMS = new CCAMS(true);
else $CCAMS = new CCAMS();
$CCAMS->authenticate();
echo $CCAMS->request_code();

if (array_key_exists('debug',$_GET)) file_put_contents(__DIR__.'/debug/log.txt',date("c").' '.__FILE__." EOL\n",FILE_APPEND);

?>