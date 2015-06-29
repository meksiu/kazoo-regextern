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

        if($dbconfig == false) {echo date('r').' NO_CONFIGDB!! '.$file.":".$line." ".$func." ".$msg."\n"; return(false);}
        if(    $dbconfig->default->extern_numbermanager_logging == 'debug'   && ($debug == 'd' || $debug == 'v'  || $debug == '')) echo date('r').' '.$file.":".$line." ".$func." ".$msg."\n";
        elseif($dbconfig->default->extern_numbermanager_logging == 'verbose' && ($debug == 'v' || $debug == '')) echo date('r').' '.$msg."\n";
        elseif($dbconfig->default->extern_numbermanager_logging == 'minimal' && $debug == '')  echo date('r').' '.$msg."\n";
}

function get_ip($type)
{
    if($type == '4') return(exec("ip addr list eth0 |grep 'inet '|cut -d' ' -f6|cut -d/ -f1"));
    if($type == '6') return(exec("ip addr list eth0 |grep 'inet6 '|cut -d' ' -f6|cut -d/ -f1"));
}

function db_config() {

    $config = get_entry('system_config','ecallmgr');
    if($config['err']) do_log("Get config Error:".$config['err']);
    else do_log("Get config Success:".$config['res']->_id,'v', __FILE__ ,__FUNCTION__, __LINE__);

return($config['res']);
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
<param name="expire-seconds" value="'.$dbconfig->default->extern_numbermanager_default_expire.'"/>
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
}

function check_gateways() {

    $ret = false;
    exec('fs_cli -x "sofia xmlstatus gateways"', $ret);
    $xml = simplexml_load_string(implode($ret));
    $json = json_encode($xml);
    $gateways = json_decode($json,TRUE);
    foreach($gateways['gateway'] AS $key => $gateway) {
        if($gateway['status'] == 'DOWN') {do_log("Gateway:".$gateway['name']." Proxy:".$gateway['proxy']." State:".$gateway['state']." Status:".$gateway['status'],'', __FILE__ ,__FUNCTION__, __LINE__);
            exec('sofia profile sipinterface_1 killgw '.$gateway['name'], $ret); sleep(1); exec('sofia profile sipinterface_1 rescan xmlreload', $ret);}
        else do_log("Gateway:".$gateway['name']." Proxy:".$gateway['proxy']." State:".$gateway['state']." Status:".$gateway['status'],'v', __FILE__ ,__FUNCTION__, __LINE__);
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

?>