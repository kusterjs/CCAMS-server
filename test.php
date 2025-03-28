<?php

include_once('CCAMS.php');


$CCAMSstats = new CCAMSstats(true);
$seldate = new DateTime('2025-02-27');

$CCAMSstats->readStats($seldate);
echo $CCAMSstats->createStats();

?>