#!/usr/bin/php
<?php

require_once('config.php');
require_once('phplib/Sag.php');
require_once('setup_functions.php');
require_once('functions.php');

$host = get_dbhost($hosts);
$sag = new Sag($host);

function input()
{
    $stdin = fopen('php://stdin', 'r');
    $resp = fgetc($stdin);

return($resp);
}
echo "HAVE YOU set config in hosts? !!!!!\n";
echo "system_config/crossbar.phone_numbers install file -> couchdb on $hosts (only 1)? Y/N ";$resp = input();if($resp == 'Y') restore('system_config', 'DB_INSTALL/system_config/', 'update');
echo "system_config/crossbar.phone_numbers restore from couchdb -> file ? Y/N ";$resp = input();if($resp == 'Y') backup('system_config', 'DB_INSTALL/system_config/', 'crossbar.phone_numbers');
sleep(2);

?>
