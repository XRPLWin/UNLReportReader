<?php

//todo delete this

require './vendor/autoload.php';

$manager = new \XRPLWin\UNLReportParser\Manager('https://xahau-test.net');

//$manager->fetchMulti(6873344,true,5);
//$manager->fetchMulti(6869248,true,1);
$r = $manager->fetchSingle(6869248);
dd($r);
