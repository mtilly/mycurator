<?php
/* mycurator_cloud_pgworkmain
 * This file contains the code to read the wp_cs_pageq table and fetch pages from diffbot.
 * Pages fetched successfully are stored in wp_cs_cache - the pageq entry is deleted in all cases.
 * This is the main worker which calls sub workers to process requests, then justs waits for completion or kills them if they run too long
*/

//database, path globals
require_once('mycurator_cloud_init.php');
//File definitions
define('PIDFILE', FPATH.'myfile.pid');
define('LOGFILE', FPATH.'page_log');
//Constants for the log
define ('MCT_AI_LOG_ERROR','ERROR');
define ('MAX_ERROR',1);
define ('MAX_TRY',1);
define ('MCT_AI_LOG_ACTIVITY','ACTIVITY');
define ('MCT_AI_LOG_PROCESS','PROCESS');
define ('MAX_READ',50);
define ('MAX_WORKER',15);
define ('MAX_SECONDS',(60*5)-10); //seconds * minutes
//Set timezone
date_default_timezone_set("America/New_York");
set_time_limit(350);
$end_time = time()+MAX_SECONDS;
//Open log
global $flog, $pid;
$flog = fopen(LOGFILE,'a');
//Check if another copy is running
if (file_exists(PIDFILE)) {
    $pid = file_get_contents(PIDFILE);
    $kill = shell_exec('kill -0 '.$pid.' 2>&1');
    //mct_cs_log(MCT_AI_LOG_PROCESS,"Server Already Running, Kill Return",$kill);
    if (!$kill) {
        mct_cs_log("Page Main $pid",MCT_AI_LOG_PROCESS,"Server Already Running, Stopping",$pid);
        fclose($flog);
        exit();  //ping the process and exit if it is still running
    }
}
sleep(5); //Let Error roll happen first
//Set pidfile to say we are running
$pid = getmypid();
file_put_contents(PIDFILE, $pid);
function removePidFile() {
    unlink(PIDFILE);
}
register_shutdown_function('removePidFile');   
//Connect to the DB
global $dblink;
$dblink = mysqli_connect(CS_SERVER,CS_USER, CS_PWD, CS_DB);
if (mysqli_connect_error()) {
    mct_cs_log("Page Main $pid",MCT_AI_LOG_ERROR, 'Service Could Not Connect to DB',mysqli_connect_error());
    mct_cs_closeout();
    exit();
}
// Ready to go
//Get count to read from back
$sql = 'SELECT count(*) as cnt FROM `wp_cs_requests`';
$sql_result = mysqli_query($dblink, $sql);
$row = mysqli_fetch_assoc($sql_result);
if (empty($row) || !$row['cnt']) {
    mct_cs_log("Page Main $pid",MCT_AI_LOG_PROCESS,"No Requests to Process",'');
    mct_cs_closeout();
    exit();
}

//Create workers to process requests
$workid = array();
$tot = $row['cnt'];

for ($i=0;$i<MAX_WORKER; $i++){
    if ($tot > MAX_READ) {
        $lim = $tot - MAX_READ;
        $limit = "$lim,".MAX_READ;
        $tot = $tot - MAX_READ;
    } else {
        $limit = $tot;
        $tot = 0;
    }
    $wpid = mct_cs_startsub($limit);
    //mct_cs_log("Page Main $pid",MCT_AI_LOG_PROCESS,"Sub Started",$wpid);
    if (!empty($wpid)) $work_id[] = $wpid;
    if (!$tot) break;
}
//Any workers started?
$workers = count($work_id);
if (!$workers) {
    mct_cs_log("Page Main $pid",MCT_AI_LOG_PROCESS,"No Subs Started, Shutting Down",'');
    mct_cs_closeout();
    exit();
}
mct_cs_log("Page Main $pid",MCT_AI_LOG_PROCESS,"Server Starting $workers Rqsts:",$row['cnt']);
//Now wait till they all die or we run out of time
$dead_id = array();
while (time() < $end_time) {
    foreach ($work_id as $key => $worker) {
        if (!$worker) continue;
        $kill = shell_exec('kill -0 '.$worker.' 2>&1');
        if ($kill) {
            $dead_id[] = $worker;
            $work_id[$key] = 0;
        }
    }
    if (count($dead_id) == count($work_id)) break;
    sleep(1);
}
if (count($dead_id) != count($work_id)) {
    //kill any remaining processes
    foreach ($work_id as $worker) {
        if (!$worker) continue;
        $kill = shell_exec('kill -9 '.$worker.' 2>&1');
        mct_cs_log("Page Main $pid",MCT_AI_LOG_PROCESS,"Killing Sub",$worker);
    }
}
mct_cs_log("Page Main $pid",MCT_AI_LOG_PROCESS,"Server Shutting Down",count($work_id)-count($dead_id));
mct_cs_closeout();
exit();
//
//Functions below
//
function mct_cs_closeout() {
    //Close out log, database
    global $flog, $dblink;
    
    fclose($flog);
    mysqli_close($dblink);
}

function mct_cs_log($name,$type,$msg,$val){
    //log activity to page_log
    global $flog;
    
    $ts = date('m d y g:i:s');
    fwrite($flog,"$ts $name $type '$msg' '$val' \n");
}

function mct_cs_startsub($limit){
    //Start up a sub worker
    $shell = "bash start_sub.sh $limit ".MAX_ERROR." ".MAX_TRY;
    $pid = shell_exec("/usr/local/php73/bin/php-cli mycurator_cloud_pgworksub.php $limit ".MAX_ERROR." ".MAX_TRY." > /dev/null 2>/dev/null & echo $!");  //> /dev/null 2>/dev/null
    if (intval($pid)) return intval($pid);
    mct_cs_log("Page Main $pid",MCT_AI_LOG_ERROR, 'Could not Start Sub',$pid);
    return false;
}

?>
