#!/usr/bin/php
<?php
$stop = false;$reload = false;$first = false;$checkit = false;$host = false; $testsleep = 5;
require_once('phplib/Sag.php');
require_once('functions.php');
$hosts = 'localhost1 localhost2 localhost';

// check while
while($stop == false) {

    while($host == false) {
        foreach(explode(" ",$hosts) AS $testhost) { $host = check_couchdb($testhost); if($host) break;}
        if($host) continue;
        do_log("No connect to cluster-db sleep now for next tray in (s):".$testsleep);
        sleep($testsleep);
    }



    $sag = new Sag($host);
    $dbconfig = db_config();
print_r($dbconfig->default->fs_nodes);
    sleep(5);
$host=false;
}

?>