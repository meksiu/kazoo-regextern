#!/usr/bin/php
<?php

sleep(2); // only for admin check
//firewall();

require_once('phplib/Sag.php');
require_once('functions.php');
require_once('config.php');

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
    // min seconds for core reload (because don't to quickly reload the core)
    $fs = count($dbconfig['ecallmgr']->default->fs_nodes);
    do_log("Nodes possible to register:".$fs,'v',__FILE__ ,__FUNCTION__, __LINE__);
    $myip4 = get_ip(4);
    $conti = false;
    foreach($dbconfig['ecallmgr']->default->fs_nodes AS $key => $nam) {
            $name = explode("@", $nam);
            if(gethostbyname($name[1]) == $myip4) {$conti=1; $match=$key;}
    }
    if($conti) {
        do_log("Our part of $fs is ".$dbconfig['ecallmgr']->default->fs_nodes[$match],'v',__FILE__ ,__FUNCTION__, __LINE__);

        $ret = get_all_dbs($host);
        foreach($ret['res'] AS $key => $value) {
            if(stristr($value, 'numbers/')) {
                $res = get_entry($value , '/_design/numbers/_view/regexternal_modified');
                if($res['err']) {do_log("Get regexternal_view Error:".$res['err']); continue;}
                else {
                    foreach($res['res']->rows AS $num) {
                        $num->value->db = $value;
                        $result[$num->id] = $num->value;
                    }
                }
            }
        }
        foreach($result AS $value) {
            try {if(@$value->regextern->pvt_changed == false) $value->regextern->pvt_changed = 0;} catch (Exception $e) {$value->regextern->pvt_changed = 0;print_r($value); sleep(1);}
            try {if(@$value->changed == false) $value->changed = 0;} catch (Exception $e) {$value->changed = 0;print_r($value); sleep(1);}
            try {if(@$value->modified == false) $value->modified = 0;} catch (Exception $e) {$value->modified = 0;print_r($value); sleep(1);}
            try {if(@$value->regextern->pvt_modified == false) $value->regextern->pvt_modified = 0;} catch (Exception $e) {$value->regextern->pvt_modified = 0;print_r($value); sleep(1);}
            do_log("Extension:".$value->regextern->extension." (".$value->regextern->pvt_modified." >= ".$value->regextern->pvt_changed." && ".$value->active." == true &&  ".$value->state." == 'in_service')", 'd',__FILE__, __FUNCTION__, __LINE__);
            if($value->regextern->pvt_modified >= $value->regextern->pvt_changed && ($value->active == true) &&  ($value->state == 'in_service'||$value->state == 'reseved')) {write_xml($value); $reload=true;}
            elseif($value->modified >= $value->changed && $value->active == false) {remove_xml($value); exec('fs_cli -x "sofia profile sipinterface_1 killgw '.substr($value->id,1).'"', $ret); gateway_removed($value->id); $reload=true;}
        }
        if($reload) {exec('fs_cli -x "sofia profile sipinterface_1 rescan reloadxml"', $gat); $reload=false; do_log("Reload Gateways:".var_dump($gat), 'v',__FILE__, __FUNCTION__, __LINE__);}
        if($checkit == false) {
            do_log("min check wait: $ncheck > ".$dbconfig['phone_numbers']->default->extern_numbermanager_min_check,'v','info',__FILE__,__FUNCTION__,__LINE__);
            if($ncheck > $dbconfig['phone_numbers']->default->extern_numbermanager_min_check) { check_gateways(); $ncheck = 0; };
            $checkit = true;
        }
    }
    $gat = false; $checkit = false; $ret = false; $res = false;
    // ========== FIRST INIT ===============================================
    if($first == false) {
      // first init all


    $first = true;
    }
    // ========== END FIRST INIT ===============================================

    $cmd = false;
    if(! is_numeric($dbconfig['phone_numbers']->default->extern_numbermanager_min_check)) $dbconfig['phone_numbers']->default->extern_numbermanager_min_check = '120';
    if(! is_numeric($dbconfig['phone_numbers']->default->extern_numbermanager_sleep_time)) $dbconfig['phone_numbers']->default->extern_numbermanager_sleep_time = '60';
    do_log("Sleep now:".$dbconfig['phone_numbers']->default->extern_numbermanager_sleep_time, 'v',__FILE__, __FUNCTION__, __LINE__);
    sleep($dbconfig['phone_numbers']->default->extern_numbermanager_sleep_time);
    $ncheck = $ncheck + $dbconfig['phone_numbers']->default->extern_numbermanager_sleep_time;
    $host=false;
}

?>