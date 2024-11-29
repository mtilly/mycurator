<?php
/* mycurator_cloud_pgworker
 * This file contains the code to read the wp_cs_pageq table and fetch pages from diffbot.
 * Pages fetched successfully are stored in wp_cs_cache - the pageq entry is deleted in all cases.
 * This is a sub-worker that is passed in the records to read from the wp_cs_request file by the pbworker main process
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
set_time_limit(350);
//Open log
global $flog, $argv;
$flog = fopen(LOGFILE,'a');
$pid = getmypid();
//
//Get limit variables
if (empty($argv[1]) || empty($argv[2]) || empty($argv[3])) {
    mct_cs_log("Page Sub Worker $pid",MCT_AI_LOG_PROCESS,"Missing Input Args",var_dump($argv));
    exit();
}
$limit = $argv[1];
define ('MAX_ERROR',$argv[2]);
define ('MAX_TRY',$argv[3]);
//Token indicator
global $token_ind;
$token_ind = false;
//Get the support functions
require_once('mycurator_cloud_fcns.php');
//mct_cs_log("Page Sub Worker $pid",MCT_AI_LOG_PROCESS,"Subworker Starting",'');

//Connect to the DB
global $dblink;
$dblink = mysqli_connect(CS_SERVER,CS_USER, CS_PWD, CS_DB);
if (mysqli_connect_error()) {
    mct_cs_log("Page Sub Worker $pid",MCT_AI_LOG_ERROR, 'Service Could Not Connect to DB',mysqli_connect_error());
    mct_cs_closeout();
    exit();
}
// Ready to go
//Query the pageq table
$sql = 'SELECT * FROM `wp_cs_requests` ORDER BY rq_id ASC LIMIT '.$limit;
$sql_result = mysqli_query($dblink, $sql);
if (!$sql_result){
    mct_cs_log("Page Sub Worker $pid",MCT_AI_LOG_ERROR, "DB Error Couldn't Select",'');
    mct_cs_closeout();
    exit();
}
//read and process
while ($row = mysqli_fetch_assoc($sql_result)){
    $page = '';
    if ($row['rq_err_try'] >= MAX_TRY ) {
        //Don't try to get page again, just drop through and move it to cache as an empty error page
    } else {
        if ($row['rq_errcnt'] >= MAX_ERROR) continue; //quit trying to process 
        //Now get page from diffbot
        $token_ind = $row['rq_dbkey']; //which diffbot key to use
        $page = mct_ai_call_diffbot($row['rq_url'], array('topic_name' => "Page Sub Worker $pid"));
        if (empty($page)) {
            $ecnt = $row['rq_errcnt'] + 1;
            $sql = "UPDATE wp_cs_requests SET rq_errcnt = $ecnt WHERE rq_id = ".$row['rq_id'];
            $upd_result = mysqli_query($dblink, $sql);
            if (!$upd_result){
                mct_cs_log("Page Sub Worker $pid",MCT_AI_LOG_ERROR, "DB Error Couldn't Update Error Count",'');
            }
            continue;  //forget errors for now - we'll try again later
        } 
    } 
    //Check Cache
    $sql_url = mysqli_real_escape_string($dblink, $row['rq_url']);
    $sql = "SELECT `pr_id`
        FROM wp_cs_cache 
        WHERE pr_url = '$sql_url'";
    $cache_result = mysqli_query($dblink, $sql);
    if (!$cache_result OR mysqli_num_rows($cache_result) == 0){
        //Insert to cache
        //$sql_url = mysqli_real_escape_string($dblink, $row['rq_url']);
        $sql_page = mysqli_real_escape_string($dblink, $page);
        $sql_page = preg_replace('/[\x{10000}-\x{10FFFF}]/u', "\xEF\xBF\xBD", $sql_page);  //remove 4 byte characters into unicode replacement character
        $sql = "INSERT INTO wp_cs_cache (pr_page_content, pr_usage, pr_url, pr_rqst) VALUES ('$sql_page',0,'$sql_url',1)";
        $ins_result = mysqli_query($dblink, $sql);
        if (!$ins_result){
            mct_cs_log("Page Sub Worker $pid",MCT_AI_LOG_ERROR, "DB Error Couldn't Insert to Cache",mysqli_error($dblink).' '.$sql_url);
            //Go ahead and delete request, probably a duplicate.  If not, we'll get a new request again.
            mct_cs_delrqst($row['rq_id']);
            continue;
        }
    }
    mct_cs_delrqst($row['rq_id']);
    
    mct_cs_log("Page Sub Worker $pid",MCT_AI_LOG_ACTIVITY, "Page Loaded in Cache",$row['rq_url']);
}
mysqli_free_result($sql_result);
//mct_cs_log("Page Sub Worker $pid",MCT_AI_LOG_PROCESS,"Sub Worker Shutting Down",'');
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

function mct_cs_delrqst($del) {
    global $dblink;
    
    $sql = "DELETE FROM wp_cs_requests WHERE rq_id = ".$del;
    $sql_result = mysqli_query($dblink, $sql);
    if (!$sql_result){
        mct_cs_log("Page Sub Worker $pid",MCT_AI_LOG_ERROR, "DB Error Couldn't Remove from Requests",$del);
    }
}

?>