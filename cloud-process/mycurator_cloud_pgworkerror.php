<?php
/* mycurator_cloud_pgworkerror
 * This program bumps up the error try count to retry errors
 * Should be called by cron every few hours to retry errors and then if still bad they'll be gone
*/

//error reporting
error_reporting(E_ERROR);
//database, path globals
require_once('mycurator_cloud_init.php');

define('LOGFILE', FPATH.'page_log');
//Constants for the log
define ('MCT_AI_LOG_ERROR','ERROR');
define ('MCT_AI_LOG_ACTIVITY','ACTIVITY');
define ('MCT_AI_LOG_PROCESS','PROCESS');
//Set timezone
date_default_timezone_set("America/New_York");
//Open log
global $flog, $argv;
$flog = fopen(LOGFILE,'a');
$pid = getmypid();
//Connect to the DB
global $dblink;
$dblink = mysqli_connect(CS_SERVER,CS_USER, CS_PWD, CS_DB);
if (mysqli_connect_error()) {
    mct_cs_log("Page Error Worker $pid",MCT_AI_LOG_ERROR, 'Service Could Not Connect to DB',mysqli_connect_error());
    mct_cs_closeout();
    exit();
}
// Ready to go
$sql = "UPDATE wp_cs_requests SET rq_err_try = 1, rq_errcnt = 0 WHERE rq_err_try is null AND rq_errcnt > 0";
$req_row = mysqli_query($dblink, $sql);
if (!$req_row)  mct_cs_log("Page Error Worker $pid",MCT_AI_LOG_ERROR, 'Could not Update - Null try');
$sql = "UPDATE wp_cs_requests SET rq_err_try = rq_err_try + 1, rq_errcnt = 0 WHERE rq_errcnt > 0";
$req_row = mysqli_query($dblink, $sql);
if (!$req_row)  mct_cs_log("Page Error Worker $pid",MCT_AI_LOG_ERROR, 'Could not Update - non-null try');

mct_cs_log("Page Error Worker $pid",MCT_AI_LOG_PROCESS, 'Error Entries Rolled','');

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


?>