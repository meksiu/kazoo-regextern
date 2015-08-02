<?php

class Object {
    function ResetObject() {
        foreach ($this as $key => $property) {
            unset($this->$key);
        }
    }
}

function do_log($msg, $debug=false, $file=false,  $func=false, $line=false) {

    global $dbconfig;

        if($dbconfig['phone_numbers'] == false) {echo date('r').' NO_CONFIGDB!! '.$file.":".$line." ".$func." ".$msg."\n"; return(false);}
        if(    $dbconfig['phone_numbers']->default->extern_numbermanager_logging == 'debug'   && ($debug == 'd' || $debug == 'v'  || $debug == '')) echo date('r').' '.$file.":".$line." ".$func." ".$msg."\n";
        elseif($dbconfig['phone_numbers']->default->extern_numbermanager_logging == 'verbose' && ($debug == 'v' || $debug == '')) echo date('r').' '.$msg."\n";
        elseif($dbconfig['phone_numbers']->default->extern_numbermanager_logging == 'minimal' && $debug == '')  echo date('r').' '.$msg."\n";
}

function get_ip($type)
{
    if($type == '4') return(exec("ip addr list eth0 |grep 'inet '|cut -d' ' -f6|cut -d/ -f1"));
    if($type == '6') return(exec("ip addr list eth0 |grep 'inet6 '|cut -d' ' -f6|cut -d/ -f1"));
}

function db_config() {

    $cfg_p = get_entry('system_config','crossbar.phone_numbers'); $config['phone_numbers'] = $cfg_p['res'];
    $cfg_e = get_entry('system_config','ecallmgr');               $config['ecallmgr'] = $cfg_e['res'];
    if($cfg_p['err']) do_log("Get config Error:".$cfg_p['err']);
    else do_log("Get phone_numbers_config Success:".$config['phone_numbers']->_id."|Get ecallmgr_config Success:".$config['phone_numbers']->_id,'v', __FILE__ ,__FUNCTION__, __LINE__);

return($config);
}

function get_all_dbs($host) {

    global $sag;
    $ret['err'] = false;

    try {
            $ret['res'] = $sag->getAllDatabases()->body;
        }
        catch(Exception $e) {
              $ret['err'] = $e->getMessage()."Host:".$host;
        }
    return $ret;
}

function get_entry($db, $view) {

    global $sag;
    $ret['err'] = false;

    try {
            $sag->setDatabase($db);
            $ret['res'] = $sag->get($view)->body;
        }
        catch(Exception $e) {
              $ret['err'] = $e->getMessage()."DB:$db";
        }
    return $ret;
}

// res object
function put_changed($value) {

    global $sag;
    $ret['err'] = false;
    $ret = get_entry($value->db, urlencode($value->id));
    if($ret['err']) {do_log("Change Get:".$value->id ." Error:".$ret['err']); return(false);} else $res = $ret['res'];
    $res->regextern->pvt_changed = ((date("U", strtotime("0000-01-01"))-time())*-1); // this is gregoriantime (1.1.1 00:00)
    $res->views++;

    try {
            $sag->setDatabase($res->pvt_db_name);
            $ret['res'] = $sag->put(urlencode($res->_id), $res)->body->ok;
        }
        catch(Exception $e) {
              $ret['err'] = $e->getMessage()."DB:$db";
        }
    return $ret;
}

function write_xml($value) {

global $dbconfig;

    $xml = '<include>
<gateway name="'.substr($value->id,1).'">
<param name="proxy" value="'.$value->regextern->proxy.'"/>
<param name="username" value="'.$value->regextern->username.'"/>
<param name="extension" value="'.$value->regextern->extension.'"/>
<param name="password" value="'.$value->regextern->password.'"/>
<param name="register" value="true"/>
<param name="expire-seconds" value="'.$dbconfig['phone_numbers']->default->extern_numbermanager_default_expire.'"/>
<param name="context" value="context_2"/>
</gateway>
</include>
';
    file_put_contents("/etc/kazoo/freeswitch/gateways/".substr($value->id,1).".xml", $xml, LOCK_EX);
    $ret = put_changed($value);
    if($ret['err']) do_log("Adding:".$value->id ." Error:".$ret['err']);
    else do_log("Adding:".$value->id ." Success:".$ret['res'],'v', __FILE__ ,__FUNCTION__, __LINE__);
}

function remove_xml($value) {

    unlink("/etc/kazoo/freeswitch/gateways/".substr($value->id,1).".xml");
    $ret = put_changed($value);
    if($ret['err']) do_log("Remove:".substr($value->id,1)." Error:".$ret['err']);
    else do_log("Remove:".$value->id ." Success:".$ret['res'],'v', __FILE__ ,__FUNCTION__, __LINE__);
    @$gateways_status = unserialize(file_get_contents('/tmp/gateways_cache'));
    unset($gateways_status[$value->id]);
    file_put_contents('/tmp/gateways_cache',serialize($gateways_status));
}

function check_gateways() {

    $ret = false;$flag_save = false;
    exec('fs_cli -x "sofia xmlstatus gateways"', $ret);
    $xml = simplexml_load_string(implode($ret));
    $json = json_encode($xml);
    $gateways = json_decode($json,TRUE);
    @$gateways_status = unserialize(file_get_contents('/tmp/gateways_cache'));
    foreach($gateways['gateway'] AS $key => $gateway) {
        if($gateway['status'] != $gateways_status[$gateway['exten']]['reged_status']) {$gateways_status[$gateway['exten']]['reged_state'] = $gateway['state'];
            $gateways_status[$gateway['exten']]['reged_status'] = $gateway['status']; gateway_status_changed($gateway);$flag_save = true;}
        if($gateway['status'] == 'DOWN') {do_log("Exten:".$gateway['exten']." Proxy:".$gateway['proxy']." State:".$gateway['state']." Status:".$gateway['status'],'', __FILE__ ,__FUNCTION__, __LINE__);
            exec('sofia profile sipinterface_1 killgw '.$gateway['name'], $ret); sleep(1); exec('sofia profile sipinterface_1 rescan xmlreload', $ret);
        }
        else do_log("Exten:".$gateway['exten']." Proxy:".$gateway['proxy']." State:".$gateway['state']." Status:".$gateway['status'],'v', __FILE__ ,__FUNCTION__, __LINE__);
    }
    if($flag_save) file_put_contents('/tmp/gateways_cache',serialize($gateways_status));
}

function gateway_status_changed($gateway) {

    global $sag;
    $ret['err'] = false;
    $ret = get_entry('numbers/'.substr($gateway['exten'],0,5) , urlencode($gateway['exten']));
    if($ret['err']) {do_log("Gateway status change Exten:".$gateway['exten'] ." Error:".$ret['err']); return(false);} else $res = $ret['res'];
    $res->regextern->reged_status = $gateway['status'];
    $res->regextern->reged_state = $gateway['state'];
    $res->views++;
    try {
            $sag->setDatabase($res->pvt_db_name);
            $ret['res'] = $sag->put(urlencode($res->_id), $res)->body->ok;
        }
        catch(Exception $e) {
              $ret['err'] = $e->getMessage()."DB:$db";
        }
}


function check_couchdb($testhost) {

    $host = false;
    $pingt = exec("fping  -t 30 ".$testhost);
    if($pingt == $testhost.' is alive') {
        $ret = json_decode(check_http('http://'.$testhost.':5984', 2),TRUE);
        do_log("Check couchdb:".$testhost.":5984/",'d', __FILE__ ,__FUNCTION__, __LINE__);
        if($ret['couchdb'] == 'Welcome' && $ret['version'] == '1.1.1') return($testhost);
        else do_log("FAILED couchdb:".$testhost,'', __FILE__ ,__FUNCTION__, __LINE__);
    } else do_log("FAILED fping:".$pingt." -t 50 ip=".$testhost,'', __FILE__ ,__FUNCTION__, __LINE__);

return(false);

}

function check_http($url, $timeout) {

    $ch=curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);

    $result=curl_exec($ch);
    curl_close($ch);

return($result);

}

/* do db->file (db=system_config, dir=./DB_BACKUP/) */
function backup($db_a, $dir, $match=false)
{
    // get_all_docs
    $dbs = get_entry($db_a , '/_all_docs');
    $dbs = json_decode(json_encode($dbs['res']->rows), true);
    @mkdir($dir, 0777, true);

    foreach($dbs AS $k => $db){
        /* backup only if match */
        if($match == true && $match != $db['id']) continue;
        $data = get_entry($db_a , "/".urlencode($db['id']));
        unset($data['res']->_rev);
        @mkdir($dir.urlencode($db['id']), 0777, true);
        file_put_contents($dir.urlencode($db['id']).'/'.urlencode($db['id']).'.json',json_encode($data['res']));
    }
    return("backup db->file finished\n");
}

/* do file->db (db=system_config, dir=./DB_BACKUP/) type=update or restore[none]*/
function restore($db_a, $dir, $type=false)
{
    global $sag;

    if ($handle = opendir($dir)) {
        try {$sag->createDatabase($db_a);} catch(Exception $e) {echo $e->getMessage()."DB:".$db_a."\n";}
        $sag->setDatabase($db_a);
        while (false !== ($entry = readdir($handle))) {
            if(".." == $entry||"." == $entry) continue;
            $obj1 = get_entry($db_a , "/".$entry);
            $temp_rev = $obj1['res']->_rev;
            $obj2 = json_decode(file_get_contents($dir.$entry.'/'.$entry.'.json'));
            if(is_object($obj1)) $obj = update_together($obj1['res'], $obj2, 'object');
            else $obj = $obj2;
            $obj = object2array($obj); unset($obj['err']); unset($obj['_rev']);
            try {
                if(preg_match("/^_/",urldecode($entry))) echo $sag->put(urldecode($entry), $obj)->body->ok;
                else echo $sag->put($entry, $obj)->body->ok;
            }
            catch(Exception $e) {
                if($type == 'update') {
                    $obj['_rev'] = $temp_rev;
                    $obj['views'] = $obj['views']+1;
                }
                try {
                    if(preg_match("/^_/",urldecode($entry))) echo $sag->put(urldecode($entry), $obj)->body->ok;
                    else echo $sag->put($entry, $obj)->body->ok;
                }
                catch(Exception $e) {
                    echo $e->getMessage()."DB:".$db_a." file:".urlencode($entry)."\n";
                }
            }
        }
    }
    return("restore file->db finished\n");
}

function get_dbhost($hosts)
{
    global $testsleep;

    $host = false;
    while($host == false) {
        foreach(explode(" ",$hosts) AS $testhost) { $host = check_couchdb($testhost); if($host) break;}
        if($host) continue;
        show_debug("No connect to cluster-db sleep now for next tray in (s):".$testsleep);
        sleep($testsleep);
    }
return($host);

}

/* first object is base, object after is overlayed */
function update_together($object1, $object2, $typ)
{
    switch($typ) {
        case 'json':
            $res = json_encode(array_replace_recursive(json_decode( $object1, true ) , json_decode( $object2, true )));
        break;
        case 'object':
            $res = array_replace_recursive(object2array($object1) , object2array($object2) );
        break;
    }
    return($res);
}

/* first object is base, object after is overlayed add */
function merge_together($object1, $object2, $typ)
{
    switch($typ) {
        case 'json':
            $res = json_encode(array_merge_recursive(json_decode( $object1, true ) , json_decode( $object2, true )));
        break;
        case 'object':
            $res = array_merge_recursive($object1 , $object2);
        break;
    }
    return($res);
}

function array2object($array) {

    if (is_array($array)) {
        $obj = new StdClass();
        foreach ($array as $key => $val){
            $obj->$key = $val;
        }
    }
    else { $obj = $array; }
    return $obj;
}
 
function object2array($object) {
    if (is_object($object)) {
        foreach ($object as $key => $value) {
            $array[$key] = $value;
        }
    }
    else {
        $array = $object;
    }
    return $array;
}

?>