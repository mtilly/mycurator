<?php
/* mycurator_cloud_process
 * This file contains the code to run the batch ai/filter process.  It will retrieve and post for all topics, including all sites
 * in a multisite environment with process_all call.
*/
/*
 * Changelog:
 * V1.1
 * Use globals for db for easier testing
 * Use correct diffbot token for business and individual plans
 */
//error reporting
error_reporting(E_ERROR);

//Log timing?
global $timing, $tm, $cache, $stopwords, $threeletter;
$timing = false;
$tm = array();
$tm['s'] = microtime(true);
$cache = true;
//This is the global that holds all the information as array values that will be returned
$mct_cs_cloud_response = array();
global $gzip_ok;

//diffbot token, use free plan
$token_ind = false;
$gzip_ok = false;  //pre-set to false for early versions of MyCurator
//This global holds the DB connection
global $dblink;
//Where did we come from
$referer = $_SERVER['HTTP_REFERER'];
//Constants for the log
define ('MCT_AI_LOG_ERROR','ERROR');
define ('MCT_AI_LOG_ACTIVITY','ACTIVITY');
define ('MCT_AI_LOG_PROCESS','PROCESS');
define ('MCT_AI_LOG_REQUEST','REQUEST');

//Get the classifier object & Support Fcns
require_once('mycurator_cloud_init.php'); //db globals
require_once('mycurator_cloud_classify.php');
require_once('mycurator_cloud_fcns.php');

//Use below as start with curl call
$request_body = file_get_contents('php://input');
//echo $request_body; //use this to test argument calls
//exit();
//Check encoding
if ($_SERVER['HTTP_CONTENT_ENCODING'] == 'gzip'){
    $request_body = gzuncompress($request_body);
}
$response = mct_cs_cloud_dispatch($request_body);
 mysqli_close($dblink);
 if ($gzip_ok && strlen($response) > 1000) {
     $response = gzcompress($response);
     header("Content-Type: application/json-gzip");  //Use content-type as it is passed through curl getinfo - but yes, not very 'standard'
     header("Content-Length: " . strlen($response));
 }  else {
     header("Content-Type: application/json");  
     header("Content-Length: " . strlen($response));
 }
 echo $response;
 exit();

function mct_cs_cloud_dispatch($json_post){
//First we verify the token as valid, then    
//This function dispatches the cloud processing based on the type of service requested
    global $mct_cs_cloud_response, $gzip_ok, $tm, $timing;
    $json_obj = json_decode($json_post);  //Decode the object
    
    //Verify the token
    $token = $json_obj->token;
    $userid = mct_cs_validate($token, $json_obj);
    if (!$userid) {
        return json_encode($mct_cs_cloud_response);
    }
    //Set encoding global
    if (!empty($json_obj->gzip) && $json_obj->gzip == true) $gzip_ok = true;
    
    if ($json_obj->type == 'Topic') {
        
        $ok = mct_cs_set_topic($json_obj);
        if ($ok) return json_encode(array('LOG' => 'OK'));
        else return json_encode($mct_cs_cloud_response);
    }
    if ($json_obj->type == 'Classify') {
       $topic = mct_cs_get_topic($json_obj); 
       if (empty($topic)) return json_encode($mct_cs_cloud_response);
       $post_arr = mct_cs_classify($topic, $json_obj); 
       if (empty($post_arr)) return json_encode($mct_cs_cloud_response);
       $tm['c'] = microtime(true);
       if ($timing) {
           $tstr = sprintf("Timing - Validate: %f Diffbot: %f Classify: %f",$tm['v']-$tm['s'],$tm['d']-$tm['v'],$tm['c']-$tm['d']);
           error_log($tstr);
       }
       return json_encode(array('postarr' => $post_arr));
    }
    if ($json_obj->type == 'GetPlan') {
        $arr = get_object_vars($json_obj->args);
        if (!empty($arr['blogcnt'])){
            //log this mu site usage
            error_log("MU Net: ".$token." Blogs: ".$arr['blogcnt']." ProcQ ".implode(',',$arr['procqueue']));   
            return json_encode("Logged");
        }
        $plan_arr = mct_cs_getplan($userid);
        if (empty($plan_arr)) return json_encode($mct_cs_cloud_response);
        return json_encode(array('planarr' => $plan_arr));
    }
}

function mct_cs_validate($token, $json_obj){
    //Validate whether token is valid, period hasn't ended
    // for MyCurator product - return the user id if valid
    global $dblink, $token_ind;
    
    //Valid version?
    if (empty($json_obj->ver)) {
        mct_cs_log('CloudService',MCT_AI_LOG_ERROR, 'MyCurator Version Too Old - Update MyCurator Plugin',$token);
        return false;
    }
    if ((int) substr($json_obj->ver,0,1) < 2) {  //Version 2 or greater
        if ($token != '467470860dc4bcaec917092cd0ab914a') {  //Custom for yayworld at 1.3.2
            mct_cs_log('CloudService',MCT_AI_LOG_ERROR, 'MyCurator Version Too Old - Update MyCurator Plugin',$token);
            return false;
        }
    }
    //Connect to the DB
    $dblink = mysqli_connect(CS_SERVER,CS_USER, CS_PWD, CS_DB);
    if (mysqli_connect_error()) {
        mct_cs_log('CloudService',MCT_AI_LOG_ERROR, 'Service Could Not Connect to DB',mysqli_connect_error());
        return false;
    }
    //mysql_select_db(CS_DB);
    
    //Query the validation table
    $sql = 'SELECT * FROM `wp_cs_validate` WHERE `token` = "'.$token.'"';
    $sql_result = mysqli_query($dblink, $sql);
    if (!$sql_result || !mysqli_num_rows($sql_result)){
        mct_cs_log('CloudService',MCT_AI_LOG_ERROR, "Invalid Token $token",'');
        return false;
    }
    
    //Check the product type and end date
    $valid_row = mysqli_fetch_assoc($sql_result);
    if (stripos($valid_row['product'],'MyCurator') === false) {
        mct_cs_log('CloudService',MCT_AI_LOG_ERROR, 'Invalid Service for this Token','');
        return false;
    }
    if (stripos($valid_row['product'],'Ind') !== false) {
        $token_ind = true;
    }
    if ($valid_row['end_date']) {
        $today = time();  //Yes, so check end date
        $end_date = strtotime($valid_row['end_date']);
        if ($today > $end_date) {
            mct_cs_log('CloudService',MCT_AI_LOG_ERROR, 'Service Period has Expired','');
            return false;
        }
    }
    /*update counts - moved to getpage: 1 for rqst or 1 for native call
    $update_cnt = $valid_row['classify_calls'] + 1;
    if ($json_obj->type == 'Classify') {
        $post_arr = get_object_vars($json_obj->args);
        if ($post_arr['page'] == "Not Here"){
            $sql = "UPDATE wp_cs_validate SET `classify_calls` = $update_cnt WHERE `token` = '$token'";
            $sql_result = mysql_query($sql);
        }
    }
    */
    //OK, return user id
    return $valid_row['user_id'];
    
}

function mct_cs_set_topic($jsonobj){
    //Set the topic into the DB, return true if ok, else false if a problem
    //and set a log value
    global $dblink, $referer;
    
    $topic = get_object_vars($jsonobj->args);
    $token = $jsonobj->token;
    $topicid = $topic['topic_id'];
    $ptype = "ASCII";
    $ver = 'Unk';
    if ($_SERVER['HTTP_USER_AGENT'] == '') $ver = 'Old';
    
    //Log call type and topic encoding    
    if (!empty($jsonobj->utf8) && $jsonobj->utf8 == true) $ptype = "UTF-8";
    if (!empty($jsonobj->ver)) $ver = $jsonobj->ver;
    error_log("Topic Encoded: ".$ptype." Version: ".$ver." Token: ".$token);   

    //See if we have this topic set for this token
    $sql = "SELECT `topic_id` FROM `wp_cs_topic` WHERE `token` = '$token' AND `topic_id` = $topicid";
    $sql_result = mysqli_query($dblink, $sql);
    if (!$sql_result){
        mct_cs_log('CloudService',MCT_AI_LOG_ERROR, 'DB Select Error on Find Topic','');
        return false;
    }
    if (!mysqli_num_rows($sql_result)){
        //Not found so do an insert
        $sql = "INSERT INTO `wp_cs_topic` (referer, token";  //topic_id,topic_name,topic_slug,topic_status,topic_type,topic_search_1,topic_search_2,";
        $valstr = "VALUES ('$referer', '$token'";
        foreach ($topic as $fld=>$vals){
            $sql .= ",$fld";
            $valstr .= ",'".mysqli_real_escape_string($dblink, $vals)."'";
        }
        $sql .= ") $valstr)";
        $sql_result = mysqli_query($dblink, $sql);
        if (!$sql_result){
            $err = mysqli_error($dblink);
            mct_cs_log('CloudService',MCT_AI_LOG_ERROR, "DB Error on Insert Topic: $err",'');
            return false;
        }
    } else {
        //Do an update
        $sql = "UPDATE `wp_cs_topic` SET `referer` = '$referer', `last_update` = now(), ";
        foreach ($topic as $fld=>$vals){
            if ($fld == 'topic_id') continue;  //part of where clause
            $sql .= "`$fld`='".mysqli_real_escape_string($dblink, $vals)."',";
        }
        $sql = substr($sql,0,strlen($sql)-1); //Get rid of last comma
        $sql .= "WHERE `token`='$token' AND `topic_id`=$topicid";
        $sql_result = mysqli_query($dblink, $sql);
        if (!$sql_result){
            $err = mysqli_error($dblink);
            mct_cs_log('CloudService',MCT_AI_LOG_ERROR, "DB Error on Update Topic: $err",'');
            return false;
        }
    }
    
    return true;
}


function mct_cs_get_topic($jsonobj){
    //Get the topic into from the DB, if error return false and set a log value
    //return the topic as an associative array if ok
    global $dblink;
    
    $token = $jsonobj->token;
    $topicid = $jsonobj->topic_id;
    
    //See if we have this topic set for this token
    $sql = "SELECT * FROM `wp_cs_topic` WHERE `token` = '$token' AND `topic_id` = $topicid";
    $sql_result = mysqli_query($dblink, $sql);
    if (!$sql_result){
        mct_cs_log('CloudService',MCT_AI_LOG_ERROR, 'DB Select Error on Get Topic','');
        return '';
    }
    if (mysqli_num_rows($sql_result) != 1){
        mct_cs_log('CloudService',MCT_AI_LOG_ERROR, 'Topic Not Found on DB '.$token,'');
        return '';
    }
    return mysqli_fetch_assoc($sql_result);
}

function mct_cs_getplan($id){
    //Get the plan information for the user
    //See if we have this topic set for this token
    global $dblink;
    $plan = array();
    
    $sql = "SELECT meta_key, meta_value FROM `wp_usermeta` WHERE `user_id` = '$id' ";
    $sql_result = mysqli_query($dblink, $sql);
    if (!$sql_result){
        mct_cs_log('CloudService',MCT_AI_LOG_ERROR, 'DB Select Error on Get Plan','');
        return '';
    }
    if (mysqli_num_rows($sql_result) == 0){
        mct_cs_log('CloudService',MCT_AI_LOG_ERROR, 'User Meta Not Found on DB','');
        return '';
    }
    while ($row = mysqli_fetch_assoc($sql_result)){
        if ($row['meta_key'] == 'tgtinfo_plan') $plan['name'] = $row['meta_value'];
        if ($row['meta_key'] == 'tgtinfo_apikey') $plan['token'] = $row['meta_value'];
        if ($row['meta_key'] == 'tgtinfo_max_topic') $plan['max'] = $row['meta_value'];
        if ($row['meta_key'] == 'tgtinfo_max_notebk') $plan['maxnb'] = $row['meta_value'];
        if ($row['meta_key'] == 'tgtinfo_max_source') $plan['maxsrc'] = $row['meta_value'];
    }
    if (empty($plan)) mct_cs_log('CloudService',MCT_AI_LOG_ERROR, 'User Plan Not Found on DB','');
    return $plan;
}

function mct_cs_log($topic, $type, $msg, $url){
    global $mct_cs_cloud_response;
    
    $ins_array = array(
                'logs_topic' => $topic,
                'logs_type' => $type,
                'logs_url' => $url,
                'logs_msg' => $msg
            );
    $mct_cs_cloud_response['LOG'] = $ins_array;
    if ($type == MCT_AI_LOG_ERROR && stripos($msg,"No relevance database") === false) error_log($msg);
}

function mct_cs_classify($topic, $jsonobj){ 
    global $dblink, $mct_cs_cloud_response, $tm;
    //$topic is an array with each field from the topics file
    //jsonobj holds the page and the current_link
    //build the post_arr array as we go and return it if no errors
    //If there are errors, return empty (and set LOG entry with mct_cs_log)
    
    $post_arr = get_object_vars($jsonobj->args);
    $page = $post_arr['page'];
    $ilink = $post_arr['current_link'];
    
    if ($topic['topic_type'] == 'Relevance'){
        //instantiate a classifier with topic db's (delete old one)
        $rel = new Relevance();
        $do_relevance = true;
        $dbret = $rel->get_db($topic);
        if ($dbret != ''){
            mct_cs_log($topic['topic_name'],MCT_AI_LOG_ERROR, $dbret, '');  //continue on as classify will return not sure
        } else {
            $rel->preparedb();  //get the sizing right
        }
    }
    $tm['v'] = microtime(true);
    if ($page == "Not Here"){
        //Need to get the page from diffbot
        $page = mct_ai_getpage($ilink, $topic, $jsonobj->rqst, $jsonobj->token);
        $post_arr['page'] = $page;
        $mct_cs_cloud_response['postarr'] = $post_arr;  //Load in response in case we don't post this entry
        if (empty($page)) {
            //error already logged
            return '';
        }
    } else {
        error_log("Local Cache Classify");
    }
    $tm['d'] = microtime(true);
    mct_ai_getshortwords($topic);  //Get three letter words into array from topic
    
    if (!empty($jsonobj->utf8) && $jsonobj->utf8 == true) {
        $words = mct_ai_utf_words($page, false); //get an array of words, don't remove dups as we need word order for phrase checking
    } else {
        $words = mct_ai_get_words($page, false); //get an array of words, don't remove dups as we need word order for phrase checking
    }
    if (empty($words)){
        mct_cs_log($topic['topic_name'],MCT_AI_LOG_ACTIVITY, 'No words in body of article',$post_arr['current_link']);
        return '';
    }
    if (empty($post_arr['getit'])){ //Don't filter if from the getit bookmarklet
        $valid = mct_ai_filter_feed($words, $topic, $post_arr); //run through filter
        if (!$valid) {
            return '';  //reason already logged
        }
    }
    if ($topic['topic_tag_search2']){
        mct_ai_set_tags($words, $topic, $post_arr); //set tags found, including phrases
    }
    //Now remove dups
     $words = mct_ai_no_dups($words);
     //Do relevance check if appropriate
    if ($do_relevance && empty($post_arr['getit'])) { //Don't classify if from Getit bookmarklet
        $classed = $rel->classify($words);
        if ($classed['good'] < 0 && $classed['bad'] < 0){
            mct_cs_log($topic['topic_name'],MCT_AI_LOG_ACTIVITY, 'Not trained, unable to classify document',$post_arr['current_link'] );
        }
        $post_arr = array_merge($post_arr, $classed);
    }

    if ($do_relevance){
        //get rid of classifier
        unset($rel);
    }
    return $post_arr;
}  
        
function mct_ai_filter_feed($words, $topic, $post_arr){
    global $wpdb, $mct_ai_current_link, $mct_ai_optarray;
    
    //filter on each topic option - length, keywords
    //Return true if it passes all 
    //remove page if false and return false
    
    if (count($words) < intval($topic['topic_min_length'])){
        mct_cs_log($topic['topic_name'],MCT_AI_LOG_ACTIVITY, 'Post too short: '.count($words),$post_arr['current_link']);
        return false;
    }
    //exclude keywords, none may be in words  - don't use root search, must match exactly
    if (!empty($topic['topic_exclude'])) {
        $key_str = $topic['topic_exclude'];
        $phrases = mct_ai_pop_phrases($key_str); //remove any phrases
        $key_str = trim($key_str);
        if (!empty($key_str)){
            $keywords = preg_split('{[\s,]+}',$key_str);
            foreach ($keywords as $keyw){
                if (strlen(trim($keyw)) == 0) continue;  //ignore blank keywords from preg problems
                $keyw = strtolower($keyw);
                if (mct_ai_inarray($keyw,$words)) {
                    mct_cs_log($topic['topic_name'],MCT_AI_LOG_ACTIVITY, 'Found excluded word: '.$keyw,$post_arr['current_link']);
                    return false;
                }
            }
        }
        if (!empty($phrases)){ //check phrases since keywords are ok
            $ret = mct_ai_filterphrase($phrases, $words, 'any');
            if ($ret != 'None'){
                mct_cs_log($topic['topic_name'],MCT_AI_LOG_ACTIVITY, 'Found excluded phrase: '.$ret,$post_arr['current_link']);
                return false;
            }
        }
    }
    //first keywords, must all be in words
    if (!empty($topic['topic_search_1'])) {
        $key_str = $topic['topic_search_1'];
        $phrases = mct_ai_pop_phrases($key_str); //remove any phrases
        $key_str = trim($key_str);
        if (!empty($key_str)){
            $keywords = preg_split('{[\s,]+}',$key_str);
            foreach ($keywords as $keyw){
                if (strlen(trim($keyw)) == 0) continue;  //ignore blank keywords from preg problems
                $keyw = strtolower($keyw);
                if (!mct_ai_inarray($keyw,$words)) {
                    mct_cs_log($topic['topic_name'],MCT_AI_LOG_ACTIVITY, 'No Search 1 word: '.$keyw,$post_arr['current_link']);
                    return false;
                }
            }
        }
        if (!empty($phrases)){ //check phrases since keywords are ok
            $ret = mct_ai_filterphrase($phrases, $words, 'all');
            if ($ret != 'Ok'){
                mct_cs_log($topic['topic_name'],MCT_AI_LOG_ACTIVITY, 'No Search 1 phrase: '.$ret,$post_arr['current_link']);
                return false;
            }
        }
    }
    //LAST CHECK - insert any new before this
    //second keywords must have at least one matching
    if (empty($topic['topic_search_2'])) return true;  //nothing to check so ok
    $key_str = str_replace("\n",' ',$topic['topic_search_2']);
    $phrases = mct_ai_pop_phrases($key_str); //remove any phrases
    $key_str = trim($key_str);
    if (!empty($key_str)){
        $keywords = preg_split('{[\s,]+}',$key_str);
        foreach ($keywords as $keyw){
            $keyw = strtolower($keyw);
            if (strlen(trim($keyw)) == 0) continue;  //ignore blank keywords from preg problems
            if (mct_ai_inarray($keyw,$words)) return true;
        }
    }
    if (!empty($phrases)){ //check phrases since keywords haven't matched
        $ret = mct_ai_filterphrase($phrases, $words, 'any');
        if ($ret != 'None'){
            return true;
        }
    }
    mct_cs_log($topic['topic_name'],MCT_AI_LOG_ACTIVITY, 'No Search 2 Words',$post_arr['current_link']);
    return false;  //last check wasn't valid  
}

function mct_ai_set_tags($words, $topic, &$post_arr){
    // This function checks each search 2 word/phrase against the document and stores those found in $post_arr[tags]
    if (empty($topic['topic_search_2'])) return true;  //nothing to check so ok
    $key_str = str_replace("\n",' ',$topic['topic_search_2']);
    $phrases = mct_ai_pop_phrases($key_str); //remove any phrases
    $key_str = trim($key_str);
    if (!empty($key_str)){
        $keywords = preg_split('{[\s,]+}',$key_str);
        foreach ($keywords as $keyw){
            if (strlen(trim($keyw)) == 0) continue;  //ignore blank keywords from preg problems
            $keyw = strtolower($keyw);
            if (mct_ai_inarray($keyw,$words)) $post_arr['tags'][] = $keyw;
        }
    }
    if (!empty($phrases)){
        $ret = mct_ai_filterphrase($phrases, $words, 'which');
        if (!empty($ret)) {
            foreach ($ret as $r) {
                $post_arr['tags'][] = $r;
            }
        }
    }
}
function mct_ai_inarray($key, $words){
    //does a simple root word search by finding a word that begins with the key
    foreach($words as $word){
        if (preg_match('{^'.$key.'(.*)$}ui',$word)){
            return true;
        }
    }
    return false;
}

function mct_ai_pop_phrases(&$keywords){
        //If any phrases are found surrounded by quotes, returns them as an array 
        //and removes them from the keywords argument
        $cnt = preg_match_all('{("|\')([^"\']*)("|\')}',$keywords,$matches);
        if ($cnt) {
            $phrase = array();
            foreach($matches[0] as $match){
                $keywords = preg_replace('{'.$match.'}','',$keywords);
                $match = preg_replace('{("|\')}','',$match);
                $match = trim(strtolower($match));
                $phrase[] = $match;
            }
            return $phrase;
        }
        return '';
    }
    
function mct_ai_filterphrase($phrase, $words, $type){
    //Checks if $phrase(array) in $words array
    //$type is all - all phrases must be in $words - returns phrase not found or 'Ok'
    // any - any one phrase must be in words - returns phrase found or 'None'
    // which - which phrases are in words (for tag use) - returns an array of phrases found 
    if (empty($phrase)) return;
    if ($type == 'all'){
        foreach ($phrase as $p){
            $pword = preg_split('{[\s,]+}',$p); //get phrase words
            $cnt = count($words);
            $pcnt = count($pword);
            $found_it = false;
            for ($i=0;$i<$cnt;$i++){ //go through each word and match each phrase word sequentially
                if (preg_match('{^'.$pword[0].'(.*)$}ui',$words[$i])) {  //use root word matching
                    $found_it = true;  //found first one, will set to false if we don't find all
                    for ($pi=1;$pi<$pcnt;$pi++){
                        if (!preg_match('{^'.$pword[$pi].'(.*)$}ui',$words[$i+$pi])) {
                            $found_it = false;
                            break;
                        }
                    }
                    if ($found_it) break; //still true, so we are done with this phrase, otherwise keep looking 
                } 
            }
            if (!$found_it) return $p;  //phrase that wasn't found
        }
        return 'Ok';
    }
    if ($type == 'any'){
        foreach ($phrase as $p){
            $pword = preg_split('{[\s,]+}',$p); //get phrase words
            $cnt = count($words);
            $pcnt = count($pword);
            $found_it = false;
            for ($i=0;$i<$cnt;$i++){ //go through each word and match each phrase word sequentially
                if (preg_match('{^'.$pword[0].'(.*)$}ui',$words[$i])) {
                    $found_it = true;  //found first one, will set to false if we don't find all
                    for ($pi=1;$pi<$pcnt;$pi++){
                        if (!preg_match('{^'.$pword[$pi].'(.*)$}ui',$words[$i+$pi])) {
                            $found_it = false;
                            break;
                        }
                    }
                    if ($found_it) return $p; //still true, so we found a phrase
                } 
            }
         }
        return 'None'; //should have returned true by now, so must not have found any
    }
    if ($type == 'which'){
        $found_phrases = array();
        foreach ($phrase as $p){
            $pword = preg_split('{[\s,]+}',$p); //get phrase words
            $cnt = count($words);
            $pcnt = count($pword);
            $found_it = false;
            for ($i=0;$i<$cnt;$i++){ //go through each word and match each phrase word sequentially
                if (preg_match('{^'.$pword[0].'(.*)$}ui',$words[$i])) {
                    $found_it = true;  //found first one, will set to false if we don't find all
                    for ($pi=1;$pi<$pcnt;$pi++){
                        if (!preg_match('{^'.$pword[$pi].'(.*)$}ui',$words[$i+$pi])) {
                            $found_it = false;
                            break;
                        }
                    }
                    if ($found_it) {
                        $found_phrases[] = $p; //still true, so we found a phrase
                        break;  //stop looking for this phrase
                    }
                } 
            }
         }
        return $found_phrases; //return any phrases
    }

 }


function mct_ai_getpage($url, $topic, $rqst, $token){
    //Get the page translated from diffbot and translate
    global $token_ind, $cache, $dblink;
    
    //Check for page in Cache
    if ($cache) {
        $dbstr = (strlen($url) > 1000) ? substr($url,0,1000) : $url; //truncate for index lookup
        $cache_url = mysqli_real_escape_string($dblink, $dbstr); 
        //Check Cache
        $sql = "SELECT `pr_id`, `pr_page_content`, `pr_usage`
            FROM wp_cs_cache 
            WHERE pr_url = '$cache_url'";
        $sql_result = mysqli_query($dblink, $sql);
        if ($sql_result && mysqli_num_rows($sql_result) >= 1){  //Will only use first one if we have dups
            //Found page, update usage count
            $cache_row = mysqli_fetch_assoc($sql_result);
            $usage = $cache_row['pr_usage']+1;
            $cid = $cache_row['pr_id'];
            $sql = "UPDATE wp_cs_cache SET pr_usage = $usage WHERE pr_id = $cid";
            $sql_result = mysqli_query($dblink, $sql);
            if (empty($cache_row['pr_page_content'])) {
                mct_cs_log($topic['topic_name'],MCT_AI_LOG_ERROR, 'Error Could not get Page Text ',$url);
            }
            if (!empty($rqst)){
                error_log("Request Got Page");
            } else {
                error_log("Cache Hit Direct Classify");
            }
            return $cache_row['pr_page_content'];
        }
    }
    if ($cache && !empty($rqst)) {
        //Insert into wp_cs_request with this url for later use if not there already
        $sql = "SELECT `rq_id`
            FROM wp_cs_requests 
            WHERE rq_url = '$cache_url'";
        $sql_result = mysqli_query($dblink, $sql);
        if (!$sql_result) {
            mct_cs_log($topic['topic_name'],MCT_AI_LOG_ERROR, 'DB Select Error Requesting Page',$url);
            return '';
        }
        if ($sql_result && mysqli_num_rows($sql_result) == 0){
            $db_key = intval($token_ind);
            $sql = "INSERT INTO wp_cs_requests (rq_url, rq_dbkey) VALUES ('$cache_url', $db_key)";
            $sql_result = mysqli_query($dblink, $sql);
            if (!$sql_result) {
                mct_cs_log($topic['topic_name'],MCT_AI_LOG_ERROR, 'DB Insert Error Requesting Page '.mysqli_error($dblink),$url);
                return '';
            }
            $sql = "UPDATE wp_cs_validate SET `classify_calls` = `classify_calls` + 1 WHERE `token` = '$token'";
            $sql_result = mysqli_query($dblink, $sql);
            error_log("Request Inserted");
        } else {
            error_log("Request Exists");
        }
        //Set Page Requested in log and return null
        mct_cs_log($topic['topic_name'],MCT_AI_LOG_REQUEST, 'Page Requested',$url);
        
        return '';
    }
    $sql = "UPDATE wp_cs_validate SET `classify_calls` = `classify_calls` + 1 WHERE `token` = '$token'";
    $sql_result = mysqli_query($dblink, $sql);
    $page = mct_ai_call_diffbot($url, $topic);
    error_log("Direct Classify");
    //Update cache with page if on
    if ($cache && !empty($page)) {
        //cache url hasn't changed 
        $sql_page = mysqli_real_escape_string($dblink, $page);
        $sql_page = preg_replace('/[\x{10000}-\x{10FFFF}]/u', "\xEF\xBF\xBD", $sql_page);  //remove 4 byte characters into unicode replacement character
        $sql = "INSERT INTO wp_cs_cache (pr_page_content, pr_usage, pr_url) VALUES ('$sql_page',0,'$cache_url')";
        $sql_result = mysqli_query($dblink, $sql);
        if (!$sql_result) {
            $err = mysqli_error($dblink);
            mct_cs_log($topic['topic_name'],MCT_AI_LOG_ERROR, 'DB Insert Error Into Cache Direct Classify'.$err,$url);
            //Keep going as its probably a duplicate
        }
    }
    return $page;
}


function mct_ai_getshortwords($topic){
    //Gets short words from search 1, search 2 and exclude keywords
    global $threeletter;
    
    $keywords = array();
    
    $key_str = $topic['topic_exclude']." ".$topic['topic_search_1']." ".$topic['topic_search_2'];
    $key_str = trim($key_str);
    if (empty($key_str)) return;
    
    $key_str = preg_replace('{("|\')}',' ',$key_str); //Drop quotes, don't care about phrasing
    $keywords = preg_split('{[\s,]+}',$key_str);
    
    if (empty($keywords)) return;
    foreach ($keywords as $keyw) {
        $keyw = trim($keyw);
        $len = mb_strlen($keyw,'UTF-8');
        if ($len == 3 || $len == 2) $threeletter[] = mct_ai_strtolower_utf8($keyw);
    }
    
}

function mct_ai_timeit($page){
    $strt = microtime(true);
    $words = mct_ai_get_words($page,false);
    $old = microtime(true) - $strt;
    $cnt = count($words);
    $strt = microtime(true);
    $words = mct_ai_utf_words($page, false);
    $new = microtime(true) - $strt;
    
    error_log("Old: ".$old." New: ".$new." Count: ".$cnt);
    
}


$stopwords = array('a', 'about', 'above', 'above', 'across', 'after', 'afterwards', 'again', 'against', 'all', 'almost', 'alone',
 'along', 'already', 'also','although','always','am','among', 'amongst', 'amoungst', 'amount',  'an', 'and', 'another', 'any',
'anyhow','anyone','anything','anyway', 'anywhere', 'are', 'around', 'as',  'at', 'back','be','became', 'because','become',
'becomes', 'becoming', 'been', 'before', 'beforehand', 'behind', 'being', 'below', 'beside', 'besides', 'between', 'beyond', 
'bill', 'both', 'bottom','but', 'by', 'call', 'can', 'cannot', 'cant', 'co', 'con', 'could', 'couldnt', 'cry', 'de', 'describe',
 'detail', 'do', 'done', 'down', 'due', 'during', 'each', 'eg', 'eight', 'either', 'eleven','else', 'elsewhere', 'empty', 'enough'
, 'etc', 'even', 'ever', 'every', 'everyone', 'everything', 'everywhere', 'except', 'few', 'fifteen', 'fify', 'fill', 'find', 
'fire', 'first', 'five', 'for', 'former', 'formerly', 'forty', 'found', 'four', 'from', 'front', 'full', 'further', 'get', 
'give', 'go', 'had', 'has', 'hasnt', 'have', 'he', 'hence', 'her', 'here', 'hereafter', 'hereby', 'herein', 'hereupon', 'hers',
 'herself', 'him', 'himself', 'his', 'how', 'however', 'hundred', 'ie', 'if', 'in', 'inc', 'indeed', 'interest', 'into', 'is', 
'it', 'its', 'itself', 'keep', 'last', 'latter', 'latterly', 'least', 'less', 'ltd', 'made', 'many', 'may', 'me', 'meanwhile', 
'might', 'mill', 'mine', 'more', 'moreover', 'most', 'mostly', 'move', 'much', 'must', 'my', 'myself', 'name', 'namely', 'neither', 
 'never', 'nevertheless', 'next', 'nine', 'no', 'nobody', 'none', 'noone', 'nor', 'not', 'nothing', 'now', 'nowhere', 'of', 'off', 
 'often', 'on', 'once', 'one', 'only', 'onto', 'or', 'other', 'others', 'otherwise', 'our', 'ours', 'ourselves', 'out', 'over', 'own',
 'part', 'per', 'perhaps', 'please', 'put', 'rather', 're', 'same', 'see', 'seem', 'seemed', 'seeming', 'seems', 'serious', 'several',
 'she', 'should', 'show', 'side', 'since', 'sincere', 'six', 'sixty', 'so', 'some', 'somehow', 'someone', 'something', 'sometime', 
 'sometimes', 'somewhere', 'still', 'such', 'system', 'take', 'ten', 'than', 'that', 'the', 'their', 'them', 'themselves', 'then', 
 'thence', 'there', 'thereafter', 'thereby', 'therefore', 'therein', 'thereupon', 'these', 'they', 'thickv', 'thin', 'third', 'this',
 'those', 'though', 'three', 'through', 'throughout', 'thru', 'thus', 'to', 'together', 'too', 'top', 'toward', 'towards', 'twelve', 
  'twenty', 'two', 'un', 'under', 'until', 'up', 'upon', 'us', 'very', 'via', 'was', 'we', 'well',
 'were', 'what', 'whatever', 'when', 'whence', 'whenever', 'where', 'whereafter', 'whereas', 'whereby', 'wherein', 'whereupon', 
'wherever', 'whether', 'which', 'while', 'whither', 'who', 'whoever', 'whole', 'whom', 'whose', 'why', 'will', 'with', 'within',
 'without', 'would', 'yet', 'you', 'your', 'yours', 'yourself', 'yourselves', 'the');

$threeletter = array('new');

?>
