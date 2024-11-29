<?php
/*
 * Plugin Name: Tgtinfo-admin
 * Plugin URI: -----
 * Description: Admin functions to complete the Target Info marketing site
 * Version: 2.0
 * Author: Mark Tilly
 * Author URL: http://www.target-info.com
 * License: GPLv2 or later
*/
//Target Info Marketing Site Admin Plugin
//
//Init action
add_action('init','tgtinfo_init');
add_action('template_redirect', 'tgtinfo_redir');
//Set up menus
add_action('admin_menu', 'tgtinfo_createmenu');
//meta box
add_action('add_meta_boxes','tgtinfo_paddmeta');

// Log login errors to Apache error log
add_action('wp_login_failed', 'log_wp_login_fail'); // hook failed login
//Insert jquery for training videos
add_action('wp_enqueue_scripts','tgtinfo_insertjs');
//Delete user will delete wp_cs_validate record too
add_action( 'delete_user', 'tgtinfo_delete_user' );
//mandrill payload filter to alter messages
//add_filter('mandrill_payload', 'tgtinfo_mandrill_payload');
//mandrill plain text option set
//add_filter( 'mandrill_nl2br', 'tgtinfo_forgotMyPasswordEmails',10,2 );

//Globals
$Acct_token = '';
$Acct_trial = false;
$Acct_days = 0;
$apikey = '';//MAILCHIMP 
//Load export users - modified plugin
include('tgtinfo-reports.php');
//include('tgtinfo-design.php');
//include('newTI.php');
include('MailChimp.php');
//init function
function tgtinfo_init(){
    //Sets up shortcodes
    add_shortcode('tgtinfo_myaccount_start','tgtinfo_myacct_start');
    add_shortcode('newTI_DocIndex', 'newTI_DocIndex');
    add_shortcode('newTI_TrainVideos', 'newTI_TrainVideos');
    add_shortcode('newTI_PurchaseWidget', 'newTI_PurchaseWidget');
    add_shortcode('newTI_BlogIndex', 'newTI_BlogIndex');
    
    //set daily classify_calls total update
    if (!wp_next_scheduled('mct_ai_cron_dailytot')){
        $strt = mktime(5); //1 am, daily period set by mycurator
        wp_schedule_event($strt,'daily','mct_ai_cron_dailytot');
    }
    //Register custom post type edd_payment for purchases
    //Set up args array
    $target_args = array (
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'query_var' => false,
        'rewrite' => false,
        'supports' => array( 
            'title', 'editor'
        ),
        'labels' => array(
            'name' => 'Purchases',
            'singular_name' => 'Purchase',
            'add_new' => 'Add New Purchase',
            'add_new_item' => 'Add New Purchase',
            'edit_item' => 'Edit Purchase',
            'new_item' => 'New Purchase',
            'view_item' => 'View Purchase',
            'search_items' => 'Search Purchases',
            'not_found' => 'No Purchases Found',
            'not_found_in_trash' => 'No Purchases Found In Trash'
        ),
    );
   
    register_post_type('edd_payment',$target_args);  //use edd_payment as we started with easy digital download
    //Register custom post type mct_testimonial for Testimonials
    //Set up args array
    $target_args = array (
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'query_var' => false,
        'rewrite' => false,
        'supports' => array( 
            'title', 'editor'
        ),
        'labels' => array(
            'name' => 'Testimonials',
            'singular_name' => 'Testimonial',
            'add_new' => 'Add New Testimonial',
            'add_new_item' => 'Add New Testimonial',
            'edit_item' => 'Edit Testimonial',
            'new_item' => 'New Testimonial',
            'view_item' => 'View Testimonial',
            'search_items' => 'Search Testimonials',
            'not_found' => 'No Testimonials Found',
            'not_found_in_trash' => 'No Testimonials Found In Trash'
        ),
    );
   
    register_post_type('mct_testimonial',$target_args);  //use edd_payment as we started with easy digital download
}

function tgtinfo_insertjs(){
    //get training page name
    if (is_front_page() || is_page('what-is-content-curation')) {
        wp_enqueue_script('tgtinfo_videos',plugins_url('video.js',__FILE__),array('jquery'));
    }
}
//Delete user in wp_cs_validate on this hook as well as edd_payment custom post type
function tgtinfo_delete_user ($user_id) {
    global $wpdb;
    
    $sql = "DELETE FROM `wp_cs_validate` WHERE `user_id` = $user_id";
    $rslt = $wpdb->query($sql);
    $my_posts = get_posts( array( 'author' => $user_id, 'post_type' => 'edd_payment' ) );
    if (empty($my_posts)) return;
    foreach ($my_posts as $p) wp_delete_post($p->ID);
    
}

function log_wp_login_fail($username) {  //log failed login for fail2ban 
        error_log("WP login failed for username: $username");
}

    
//Cron daily totals process
add_action ('mct_ai_cron_dailytot', 'mct_ai_run_dailytot');
function mct_ai_run_dailytot(){
    global $wpdb;
    
    $cache_life = 1;
    
    /*daily totals from validate
    $sql = "SELECT `last_date`, `run_total`, `cache_run`, `rqst_run` FROM `wp_cs_dailytot` WHERE last_date = (SELECT max(last_date) FROM `wp_cs_dailytot`)";
    $last_tot = $wpdb->get_row($sql);
     * 
     */
    
    //daily totals from validate
    $sql = "SELECT sum(classify_calls) as tot, sum(run_tot) as run FROM `wp_cs_validate`";
    $cur_tot = $wpdb->get_row($sql);
    $new_tot = $cur_tot->tot - $cur_tot->run;
    //Request counts and first/last date
    $sql = "SELECT count(*) from wp_cs_requests";
    $rcnt = $wpdb->get_var($sql);
    
    $sql = "SELECT rq_update from wp_cs_requests ORDER BY rq_id ASC LIMIT 1";
    $rold = $wpdb->get_var($sql);
    $sql = "SELECT rq_update from wp_cs_requests ORDER BY rq_id DESC LIMIT 1";
    $rnew = $wpdb->get_var($sql);
    
    
    /*cache totals
    $sql = "SELECT sum(pr_usage)as tot, sum(pr_rqst) as rtot, count(pr_id) as size FROM `wp_cs_cache`";
    $cache_tot = $wpdb->get_row($sql);
    //Get requests not used yet - to adjust cache new
    $sql = "SELECT sum(pr_rqst) as rtot FROM `wp_cs_cache` WHERE pr_usage = 0";
    $rqst_notused = $wpdb->get_var($sql);
    $rqst_new = $cache_tot->rtot - $last_tot->rqst_run;
    $rqst_pct = intval((($rqst_new - $rqst_notused)/$new_tot)*100);
    //Cache hits don't include 1st hit from a request entry.  Can't just do cache hits - requests as some requests aren't picked up yet
    $cache_new = $cache_tot->tot - $last_tot->cache_run - ($rqst_new - $rqst_notused);
    $cache_pct = intval(($cache_new/($cache_new+$new_tot))*100);
     * 
     */
    //Clean out old Cache entries
    $sql = "DELETE FROM  `wp_cs_cache` WHERE DATE(  `pr_date` ) < ADDDATE( CURDATE( ) , INTERVAL -$cache_life DAY)";
    $delcnt = $wpdb->query($sql);
    
    /* Get new cache, request total for insert after we deleted records
    $sql = "SELECT sum(pr_usage) as tot, sum(pr_rqst) as rtot FROM `wp_cs_cache`";
    $new_cache_tot = $wpdb->get_row($sql);
    $success = $wpdb->insert('wp_cs_dailytot',array('day_total' => $new_tot, 'run_total' => $cur_tot->tot, 
        'cache_day' => $cache_new, 'cache_run' => $new_cache_tot->tot,
        'rqst_day' => $rqst_new, 'rqst_run' => $new_cache_tot->rtot));
    if ($success) {
        wp_mail('support@target-info.com', "Daily Classify Calls $new_tot ",
                "Success - Cache Entries: $cache_tot->size Cached: $cache_new Cache %: $cache_pct Cache Deletes: $delcnt \n
                Requests: $rqst_new  Request %: $rqst_pct");
    } else {
        wp_mail('support@target-info.com', "Daily Total FAILED","Failed");
    }
     * 
     */
    $msg = "Classify Calls $new_tot \n Requests $rcnt \n Oldest $rold Newest $rnew";
          
    wp_mail('support@target-info.com', "Cache Deletes: $delcnt ", $msg);
    //If this is Sunday, do the weekly roll, switched to weekly starting 2013
    //Removed Monthly Tot table, using validate table
    if (date("D") == "Sun") {
        //Clean out topics over 90 days old - they will be re-inserted if needed
        $sql = "DELETE FROM  `wp_cs_topic` WHERE DATE(  `last_update` ) < ADDDATE( CURDATE( ) , INTERVAL -90 DAY)";
        $delcnt = $wpdb->query($sql);
        //$sql = "OPTIMIZE TABLE `wp_cs_topic`";
        //$optret = $wpdb->query($sql);
        //Calc this week counts
        $sql = "UPDATE `wp_cs_validate` SET `this_week`= `classify_calls` - `run_tot` WHERE 1";
        $wkcnt = $wpdb->query($sql);
        //Roll run_tot
        $sql = "UPDATE `wp_cs_validate` SET `run_tot`= `classify_calls` WHERE 1";
        $runcnt = $wpdb->query($sql);
        wp_mail('support@target-info.com', "Weekly Roll Completed: $delcnt Topics Deleted","This Week $wkcnt and Run Count $runcnt");
        //Optiomize DBs
        $sql = "OPTIMIZE TABLE `wp_cs_topic`";
        $optret = $wpdb->query($sql);
        $sql = "OPTIMIZE TABLE `wp_cs_cache`";
        $optret = $wpdb->query($sql);
        
    }  //end Sunday
}


function tgtinfo_top_widget(){
    //Displays admin info in header
?>
        <div id="toplinks">
            <span><?php wp_register('', ' &bull;'); ?></span>
            <span><?php wp_loginout(); ?></span> &bull;
            <span class="feed"><a href="<?php bloginfo('rss2_url'); ?>" title="Subscribe via RSS">Subscribe</a></span>
        </div>
<?php 
}

function tgtinfo_plan_button($attr){
    //Decide whether to put up the Subscribe Now button based on current user plan
    global $current_user;
    wp_get_current_user();
    
    $buttonpro = home_url('/pro-plan-subscription/');
    $buttonbus = home_url('/business-plan-subscription/');
    $plan = get_user_meta($current_user->ID, 'tgtinfo_plan',true);
    if ($attr['plan'] == 'Pro') {
      if (stripos($plan,'trial') === false && stripos($plan,'individual') === false) return;
      return '<div class="mct_ti_button"><a href="'.$buttonpro.'">Subscribe Now</a></div>';

    }
    if (stripos($plan,'business') !== false && stripos($plan,'trial') === false) return;
    return '<div class="mct_ti_button"><a href="'.$buttonbus.'">Subscribe Now</a></div>';
}

function tgtinfo_redir() {
    global $wpdb;
    
    //Is this My Account page?
    if (stripos($_SERVER['REQUEST_URI'], "/myaccount/") === false || !is_page())  return;
    // redirect to login if trying to access myaccount without being logged in
    if(!is_user_logged_in()) {
        //use token to change user for redirect to myaccount page
        $token = '';
        if (isset($_GET['token'])) $token = $_GET['token'];
        if (empty($token)) tgtinfo_doredirect();
        $_SERVER['REQUEST_URI'] = remove_query_arg('token',$_SERVER['REQUEST_URI']);
        $pos = preg_match('/[a-z0-9]{32}/',$token,$match);
        if (!$pos) tgtinfo_doredirect();
        $userid = $wpdb->get_var( "SELECT user_id FROM wp_cs_validate WHERE token = '$token'" );
        if (empty($userid)) tgtinfo_doredirect();
        wp_set_current_user($userid);
        wp_set_auth_cookie($userid);
        
    } 
}

function tgtinfo_doredirect() {
        //redirect to login page, and follow with redirect back here
        $output = "/wp-login.php";
        $output .= "?redirect_to=".$_SERVER['REQUEST_URI'];
        $wpurl = get_bloginfo('wpurl');
        $output = $wpurl.$output;
        
        if (function_exists('status_header')) status_header( 302 );
	header("HTTP/1.1 302 Temporary Redirect");
	header("Location:".$output);
	exit();
}

function tgtinfo_myacct_start(){
    //First section of the my account page, checks user, displays token and days left on trial if applicable
    //Also displays link to latest download version of the plugin
    global $current_user, $Acct_trial, $Acct_days, $wpdb;
    
    //Start the purchase history report, which returns the report from the output buffer
    wp_get_current_user();
    $rpt = tgtinfo_userpmt_rpt($current_user->ID,false);
    
    $plan = get_user_meta($current_user->ID,'tgtinfo_plan',true);
    $maxtopic = get_user_meta($current_user->ID,'tgtinfo_max_topic',true);
    $maxsites = get_user_meta($current_user->ID,'tgtinfo_max_sites',true);
    $maxnotebk = get_user_meta($current_user->ID,'tgtinfo_max_notebk',true);
    $maxsource = get_user_meta($current_user->ID,'tgtinfo_max_source',true);
    if ($maxsource == 0) $maxsource = 'Unlimited';
    $istrial = false;
    if (stripos($plan,'trial') !== false) $istrial = true;
    //Get another key if clicked
    if (isset($_POST['apikey'])){
        $newtoken = md5('tgtinfo-'.$current_user->user_email.strval(rand(100,10000)));
        $tokens = get_user_meta($current_user->ID, 'tgtinfo_apikey', true);
        $tokens[] = $newtoken;
        update_user_meta($current_user->ID, 'tgtinfo_apikey', $tokens);
        //Update wp_cs_validate
        if (stripos($plan,'pro') !== false) {
            $vplan = 'MyCurator Pro';
        } else {
            $vplan = 'MyCurator Bus';
        }
        $sql = "INSERT INTO `wp_cs_validate` (`token`, `user_id`, `product`) VALUES ('$newtoken', '$current_user->ID', '$vplan')";
        $newid = $wpdb->query($sql);
    }
    //The action edd_purchase_history_body_start should have saved the token and whether they were in a free trial and how long as globals
    //Lets output this data now
    //echo "<h3>Your MyCurator Purchase(s) and API Key(s) are listed below</h3>";
    if (stripos($plan,'ind') !== false || $istrial) { 
        //Welcome and Get Started Info
        echo "<h3>Your MyCurator Plan Information and API Key(s) are listed below</h3>";
        echo "<p><strong>To get started, install the MyCurator plugin <a href='https://wordpress.org/plugins/mycurator/' >(download here)</a>, then go to the MyCurator Dashboard menu item"
        . " on your site and paste in an API Key from below.  The MyCurator Dashboard has step by step"
                . " instructions. Make sure to view our Training Videos and Documentation on this site!</strong></p>";
    }
    if ($istrial){
        $enddate = $wpdb->get_var('Select end_date from wp_cs_validate where user_id = '.$current_user->ID.' limit 1');
        if ($enddate) {
            $endstamp = mktime(0,0,0,substr($enddate,5,2),substr($enddate,8,2),substr($enddate,0,4));
            $today = current_time('timestamp');
            $Acct_days = intval((($endstamp - $today)/86400));  //divide by # seconds in a day        
            if ($Acct_days > 0) {
                echo "<h4>You Have $Acct_days days left on your Free Trial - Purchase one of the subscription plans below before expiration to keep using MyCurator!</h4><br />";
            } else {
                echo "<h4>Your Free Trial has Expired - Purchase one of the subscription plans below to keep using MyCurator!</h4><br />";
            }
        }
    }
    //Display Plan
    if (stripos($plan, "Business") !== false) {
        if (empty($maxsites)) $maxsites = 2;
        echo "<h3>$plan with Unlimited Topics, Sources and Notebooks.  Up to $maxsites Sites.</h3>";
    } elseif (stripos($plan,'pro') !== false) {
        echo "<h3>$plan with up to $maxtopic Topics, $maxsource Sources and Unlimited Notebooks on Two Sites</h3>";
    } else {
        if ($maxnotebk == 0) {
            echo "<h3>$plan with up to $maxtopic Topics, $maxsource Sources and Unlimited Notebooks on a single Site</h3>";
        } else {
            echo "<h3>$plan with up to $maxtopic Topics, $maxsource Sources and $maxnotebk Notebooks on a single Site</h3>";
        }
    }
    //Display user tokens and button to create more
    $tokens = get_user_meta($current_user->ID, 'tgtinfo_apikey', true);
    if (!empty($tokens)){
        $tcnt = count($tokens);
        tgtinfo_custompurchase($tokens);
        echo "<p><strong>API Key(s) and Article Volume:</strong></p>";
        if (stripos($plan,'business') !== false && $tcnt < $maxsites && !$Acct_trial) {
            ?>
            <form method="post" action="<?php echo esc_url($_SERVER['REQUEST_URI'] ); ?>" >
                <div class="submit">
                <input name="apikey" type="submit" value="Get API Key" class="button-primary" />
                </div>
            <?php
        }
    }
    $vol = tgtinfo_vol_rpt($current_user->ID);
    echo $vol;
    echo "<p><strong>Purchase History:</strong></p>";
    echo $rpt;
}

function tgtinfo_vol_rpt($userid,$expires=false){
    //Creates the volume report in a buffer and returns it
    global $wpdb;
    
    $plan = get_user_meta($userid,'tgtinfo_plan',true);
        //Weekly Volumes

    $sql = "SELECT v.classify_calls, v.token, v.end_date, v.this_week, v.run_tot
            FROM  `wp_cs_validate` AS v
            WHERE v.user_id = '$userid' ";  //AND (m.last_update =  '$last_date' OR m.last_update is null)
    
    $rows = $wpdb->get_results($sql);
    $onerow = (count($rows) == 1) ? true : false;
    $mth_tot = 0;
    $wk_tot = 0;
    $calls_tot = 0;
    $run_tot = 0;
    
    $volume = 1500;
    if (stripos($plan,"pro") !== false) $volume = 10000;
    if (stripos($plan,"business") !== false) $volume = 20000;
    ob_start();  //buffer the report for later display
        ?>
<table>
    <tr>
        <th>API Key</th>
        <th>This Week</th>
        <th>Last Week</th>
        <?php if ($expires) echo "<th>End Date</th>"; ?>
    </tr>
    <?php 
    foreach ($rows as $row) { 
        $wk_tot += $row->this_week;
        $run_tot += $row->run_tot;
        $calls_tot += $row->classify_calls;
        ?>
    <tr>
        <td><?php echo $row->token; ?></td>
        <td><?php echo number_format($row->classify_calls - $row->run_tot);?></td>
        <td><?php echo number_format($row->this_week);?></td>
        <?php if ($expires) echo "<td>$row->end_date</td>"; ?>
    </tr>
 <?php } 
    if (!$onerow){ ?>
       <tr>
        <td>Totals =></td>
        <td><?php echo number_format($calls_tot - $run_tot);?></td>
        <td><?php echo number_format($wk_tot);?></td>
    </tr>
    <?php   
    }
    ?>
</table>
<?php
    return ob_get_clean();
}
//
////
//GFORM HOOKS/FILTERS
//
//Change Description to add text about Pro Plan to Business Plan update if required
add_filter("gform_pre_render_3","tgtinfo_pro_upgrade_text");
function tgtinfo_pro_upgrade_text($form){
    global $current_user;
    
    if (!$current_user) wp_get_current_user();
    $plan = get_user_meta($current_user->ID,'tgtinfo_plan',true);
    if (stripos($plan,'pro') === false) return $form;
    if (stripos($plan,'trial') !== false) return $form;
    //Pro going to Business Plan
    $form['description'] = "<p><strong>After you upgrade to the Business Plan, we will cancel your Pro Plan and Refund the last payment. </strong></p>".$form['description'];
    return $form;
}
//API key form valid, so plug in an API Key
add_filter("gform_pre_submission", "tgtinfo_addkey");
function tgtinfo_addkey($form){
    //check form id
    //plug api key into form field for key, use username as seed
    if ($form['id'] == 1 || $form['id'] == 2 || $form['id'] == 7) $_POST['input_9'] = md5('tgtinfo-'.$_POST['input_3']);
}

//User has registered for an API key, so enter a purchase (may be $0)
add_action("gform_user_registered", "tgtinfo_purchase", 10, 4);
function tgtinfo_purchase($user_id, $user_config, $entry, $user_pass){
    global $wpdb, $apikey;
    //User registered at this point,  post purchase and purchase meta
    //post purchase with post type edd_payment
    //post meta: apikey, plan, amount, 
    //cs_validate: set up with apikey, end_date if trial, max_calls if usage limit
    //
    //Mailchimp values
    $mcplan = '';
    $mctrial = 'No';
    //Log in user for redirect to myaccount page
    $user = get_userdata( $user_id );
    $user_login = $user->user_login;
    $val = wp_signon( array(
        'user_login' => $user_login,
        'user_password' =>  $user_pass,
        'remember' => false
    ) );

    //Post purchase
    $token = $entry['9'];

    $details = array(
      'post_author' => $user_id,
      'post_title'  =>  $entry['8.3'].' '.$entry['8.6'],
      'post_type' => 'edd_payment',
      'post_status' => 'publish'
   );
   $post_id = wp_insert_post($details);
   
   //Post meta values of amount, plan, affiliate
   update_post_meta($post_id,'tgtinfo_amount',0);
   /*if ($cookies = cm_get_cookie_params()) {
       $cookiekey = array_keys($cookies);
       $cookie = $cookiekey[0];
       if (isset($_COOKIE[$cookie])) update_post_meta($post_id,'tgtinfo_affiliate',intval($_COOKIE[$cookie]));
   }*/
   if ($entry['form_id'] == 1) {
       update_post_meta($post_id,'tgtinfo_plan','Individual Plan');
       update_user_meta($user_id,'tgtinfo_plan','Individual Plan'); 
       update_user_meta($user_id,'tgtinfo_max_topic',1);
       update_user_meta($user_id,'tgtinfo_max_notebk',2);
       update_user_meta($user_id,'tgtinfo_max_source',5);
       //User Meta gets API KEY 
       update_user_meta($user_id,'tgtinfo_apikey',array($token));  //single key for individual
   
       $sql = "INSERT INTO `wp_cs_validate` (`token`, `user_id`, `product`) VALUES ('$token', '$user_id', 'MyCurator Ind')";
       $newid = $wpdb->query($sql);
       $mcplan = 'Individual';
       $mctrial = 'No';
   }    
   if ($entry['form_id'] == 2) {
       update_post_meta($post_id,'tgtinfo_plan','Business Plan Trial');
       update_user_meta($user_id,'tgtinfo_plan','Business Plan Trial'); 
       update_user_meta($user_id,'tgtinfo_max_topic',0); //0 is infinite
       update_user_meta($user_id,'tgtinfo_max_notebk',0);
       update_user_meta($user_id,'tgtinfo_max_source',0);
       //User Meta gets API KEY - 2 for a trial
       $newtoken = md5('tgtinfo-'.$user->user_email.strval(rand(100,10000)));
       update_user_meta($user_id,'tgtinfo_apikey',array($token,$newtoken)); 
       $sql = "INSERT INTO `wp_cs_validate` (`token`, `user_id`, `product`, `end_date`) VALUES ('$token', '$user_id', 'MyCurator Bus', DATE_ADD(CURDATE(), INTERVAL +30 DAY) )";
       $newid = $wpdb->query($sql);
       $sql = "INSERT INTO `wp_cs_validate` (`token`, `user_id`, `product`, `end_date`) VALUES ('$newtoken', '$user_id', 'MyCurator Bus', DATE_ADD(CURDATE(), INTERVAL +30 DAY) )";
       $newid = $wpdb->query($sql);
       $mcplan = 'Business';
       $mctrial = 'Yes';
   }    
      if ($entry['form_id'] == 7) {
       update_post_meta($post_id,'tgtinfo_plan','Pro Plan Trial');
       update_user_meta($user_id,'tgtinfo_plan','Pro Plan Trial'); 
       update_user_meta($user_id,'tgtinfo_max_topic',6);
       update_user_meta($user_id,'tgtinfo_max_notebk',0);
       update_user_meta($user_id,'tgtinfo_max_source',0);
       //User Meta gets API KEY - 2 for a trial
       $newtoken = md5('tgtinfo-'.$user->user_email.strval(rand(100,10000)));
       update_user_meta($user_id,'tgtinfo_apikey',array($token,$newtoken)); 
       $sql = "INSERT INTO `wp_cs_validate` (`token`, `user_id`, `product`, `end_date`) VALUES ('$token', '$user_id', 'MyCurator Pro', DATE_ADD(CURDATE(), INTERVAL +30 DAY) )";
       $newid = $wpdb->query($sql);
       $sql = "INSERT INTO `wp_cs_validate` (`token`, `user_id`, `product`, `end_date`) VALUES ('$newtoken', '$user_id', 'MyCurator Pro', DATE_ADD(CURDATE(), INTERVAL +30 DAY) )";
       $newid = $wpdb->query($sql);
       $mcplan = 'Pro';
       $mctrial = 'Yes';
   }  
   //update mail list
   /*
   $MailChimp = new MailChimp($apikey);
   $result = $MailChimp->call('lists/subscribe', array(
            'id'                => '0bec11afb4',
            'email'             => array('email'=> $entry['7']),
            'merge_vars'        => array('FNAME'=> $entry['8.3'], 
                'LNAME'=> $entry['8.6'], 
                'USERREG' => date('Y-m-d'),
                'TRIAL'=> $mctrial, 
                'PLAN' => $mcplan),
            'double_optin'      => true,
            'update_existing'   => true,
            'replace_interests' => true,
    ));
   if (!empty($result['error'])) {
       wp_mail('support@target-info.com', "Failed Adding New Subscriber ".$entry['7'],$result['error']);
   }
    
    */
}

//pre-populate fields for purchase forms
add_filter("gform_field_value_first", "tgtinfo_dofirst");
function tgtinfo_dofirst($value){
    global $current_user;
    
    if (!$current_user) wp_get_current_user();

    return get_user_meta($current_user->ID,'first_name',true);
}
add_filter("gform_field_value_last", "tgtinfo_dolast");
function tgtinfo_dolast($value){
    global $current_user;
    
    if (!$current_user) wp_get_current_user();

    return get_user_meta($current_user->ID,'last_name',true);
}
add_filter("gform_field_value_email", "tgtinfo_doemail");
function tgtinfo_doemail($value){
    global $current_user;
    
    if (!$current_user) wp_get_current_user();
    return $current_user->user_email;
}
add_filter("gform_field_value_userid", "tgtinfo_douserid");
function tgtinfo_douserid($value){
    global $current_user;
    
    if (!$current_user) wp_get_current_user();
    return $current_user->ID;
}
//Process the order - business plan, other paid plans
add_action("gform_paypal_fulfillment", "tgtinfo_busplan", 10, 4);
function tgtinfo_busplan($entry, $config, $transaction_id, $amount) {
    global $wpdb, $apikey;
    
    $mcplan = ''; //mailchimp plan value
    if (empty($transaction_id)) $transaction_id = 'None'; //See if we are getting ID from hook
    //check for correct form
    if ($entry['form_id'] != 3  && $entry['form_id'] != 8 && $entry['form_id'] != 9) return;
    
    $user_id = $entry['5'];
    //Check if upgrade from Individual Plan
    $plan = get_user_meta($user_id,'tgtinfo_plan',true); //current plan
    $indupgrade = false;
    if (stripos($plan,'ind') !== false) $indupgrade = true;
    //Check if upgrade from Pro to Business
    $proupgrade = false;
    if (stripos($plan,'pro') !== false && stripos($plan,'trial') === false) $proupgrade = true;
    //Create a new purchase post
    $details = array(
      'post_author' => $user_id,
      'post_title'  =>  $entry['1.3'].' '.$entry['1.6'],
      'post_type' => 'edd_payment',
      'post_status' => 'publish'
   );
   $post_id = wp_insert_post($details); //add payment post
   if ($entry['form_id'] == 3) { //Business Plan
       update_post_meta($post_id,'tgtinfo_amount',30);
       update_post_meta($post_id,'tgtinfo_plan','Business Plan');
       update_user_meta($user_id,'tgtinfo_plan','Business Plan');
       update_user_meta($user_id,'tgtinfo_max_topic',0); //0 is infinite
       update_user_meta($user_id,'tgtinfo_max_notebk',0);
       update_user_meta($user_id,'tgtinfo_max_source',0);
       update_user_meta($user_id,'tgtinfo_max_sites',6); 
       $sql = "UPDATE `wp_cs_validate` SET `end_date` = NULL, `product` = 'MyCurator Bus' WHERE `user_id` = '$user_id'"; //set all entries for this user
       $newid = $wpdb->query($sql);  //update the validate table
       if ($indupgrade) {
           //Add a 2nd token
           $newtoken = md5('tgtinfo-'.$entry['3'].strval(rand(100,10000)));
           $tokens = get_user_meta($user_id, 'tgtinfo_apikey', true);
           $tokens[] = $newtoken;
           update_user_meta($user_id, 'tgtinfo_apikey', $tokens);
           $sql = "INSERT INTO `wp_cs_validate` (`token`, `user_id`, `product`) VALUES ('$newtoken', '$user_id', 'MyCurator Bus' )";
           $newid = $wpdb->query($sql);
       }
       if ($proupgrade){
           //Send email to cx/refund pro plan
           $headers = 'From: Sales <sales@target-info.com>' . "\r\n";
           $message = "New Upgrade from Pro to Business Plan.  Cx Paypal Subscription and Refund Last Payment.\r\n User ID: $user_id and email: ".$entry['3'];
           wp_mail('sales@target-info.com', 'Pro Upgrade to Business Plan', $message, $headers);
       }
       $mcplan = 'Business';
   } elseif ($entry['form_id'] == 9) { //Add notebooks
       //update purchase info
       update_post_meta($post_id,'tgtinfo_amount',25);
       update_post_meta($post_id,'tgtinfo_plan','Add Unlimited Notebooks');
       //Only update max notebooks, not any plan info
       update_user_meta($user_id,'tgtinfo_max_notebk',0);
   } else { //Pro plan
       update_post_meta($post_id,'tgtinfo_amount',15);
       update_post_meta($post_id,'tgtinfo_plan','Pro Plan');
       update_user_meta($user_id,'tgtinfo_plan','Pro Plan');
       update_user_meta($user_id,'tgtinfo_max_topic',6);
       update_user_meta($user_id,'tgtinfo_max_notebk',0);
       update_user_meta($user_id,'tgtinfo_max_source',0);
       $sql = "UPDATE `wp_cs_validate` SET `end_date` = NULL, `product` = 'MyCurator Pro' WHERE `user_id` = '$user_id'";  //set all entries for this user
       $newid = $wpdb->query($sql);  //update the validate table
       if ($indupgrade) {
           //Add a 2nd token
           $newtoken = md5('tgtinfo-'.$entry['3'].strval(rand(100,10000)));
           $tokens = get_user_meta($user_id, 'tgtinfo_apikey', true);
           $tokens[] = $newtoken;
           update_user_meta($user_id, 'tgtinfo_apikey', $tokens);
           $sql = "INSERT INTO `wp_cs_validate` (`token`, `user_id`, `product`) VALUES ('$newtoken', '$user_id', 'MyCurator Pro' )";
           $newid = $wpdb->query($sql);
       }
       $mcplan = 'Pro';
   }
       
   update_post_meta($post_id,'tgtinfo_paypaltrx',$transaction_id);
   /*if ($cookies = cm_get_cookie_params()) {
       $cookiekey = array_keys($cookies);
       $cookie = $cookiekey[0];
       if (isset($_COOKIE[$cookie])) update_post_meta($post_id,'tgtinfo_affiliate',intval($_COOKIE[$cookie]));
   }*/
   //Mailchimp list update
   /*
   if (!empty($mcplan)) {
       $userdata =  get_userdata($user_id);
       $MailChimp = new MailChimp($apikey);
       $result = $MailChimp->call('lists/update-member', array(
                'id'                => '0bec11afb4', //4
                'email'             => array('email'=> $userdata->user_email),  // use stored email vs $entry['3'] just filled in
                'merge_vars'        => array('TRIAL'=>'No', 
                    'PLAN' => $mcplan,
                    'PURCHASE' => date('Y-m-d'))
        ));
       if (!empty($result['error'])) {
           //try yo add
           $fname = get_user_meta($user_id,'first_name', true);
           $lname = get_user_meta($user_id,'last_name', true);
           $result = $MailChimp->call('lists/subscribe', array(
            'id'                => '0bec11afb4',
            'email'             => array('email'=> $userdata->user_email),
            'merge_vars'        => array('FNAME'=> $fname, 
                'LNAME'=> $lname, 
                'USERREG' => $userdata->user_registered,
                'TRIAL'=> 'No',
                'PURCHASE' => date('Y-m-d'),
                'PLAN' => $mcplan),
            'double_optin'      => true,
            'update_existing'   => true,
            'replace_interests' => true
            ));
           if (!empty($result['error'])) {
               wp_mail('support@target-info.com', "Failed Adding New Paid Subscriber ".$userdata->user_email,$result['error']);
           }
       }
   }
   */
}



function tgtinfo_userpmt_rpt($userid,$showtrx=true){
    //return a report of user pmts 
    //
    global $Acct_trial, $Acct_days;
    //get payments for this user
    $details = array(
          'author' => $userid,
          'post_type' => 'edd_payment',
          'post_status' => 'publish'
    );
    $uposts = get_posts($details);
    if (empty($uposts)) return '';
    ob_start();  //buffer the report for later display
    ?>
<table class="user-pmts">
   
    <tr>
        <th>Date</th>        
        <th>Amount</th>
        <th>Plan</th>
         <?php if ($showtrx) echo "<th>Paypal Trx</th>"; ?>
    </tr>
<?php
    foreach ($uposts as $post){
        $pmt = tgtinfo_pmtmeta($post->ID);
        if (count($uposts) == 1 && stripos($pmt['plan'], 'trial') !== false) {
            //Set globals on trial days left for this plan
            $Acct_trial = true;
            $strt_date = strtotime($post->post_date);
            $today = current_time('timestamp');
            $Acct_days = intval(30 - (($today - $strt_date)/86400));  //divide by # seconds in a day
        }
    ?>

    <tr>
        <td><?php echo $post->post_date;?></td>
        <?php if (stripos($pmt['plan'],'notebook') !== false) { ?>
            <td><?php echo '$'.$pmt['amt']." One Time Payment"; ?></td>
        <?php } else { ?>
            <td><?php echo '$'.$pmt['amt']." Monthly Subscription"; ?></td>
        <?php } ?>
        <td><?php echo $pmt['plan']; ?></td>
        <?php if ($pmt['trx'] && $showtrx) echo "<td>".$pmt['trx']."</td>"; ?>
    </tr>

<?php
    } ?>
    </table>
<?php
    return ob_get_clean();
    
}
//Builder action/filter hooks
//

function tgtinfo_credits($credits){
    //Display right side credits on site
    $credits = '<a href="http://www.target-info.com/terms-of-service/" >Terms of Service</a><br />';
    $credits .= '<a href="http://www.target-info.com/privacy-policy/" >Privacy Policy</a>';
    return $credits;
}
add_filter('builder_footer_credit','tgtinfo_credits');

// Add support for Featured Images
if (function_exists('add_theme_support')) {
    add_theme_support('post-thumbnails');
    add_image_size('index-categories', 150, 150, true);
    add_image_size('page-single', 150, 150, true);
}

function tgtinfo_InsertFeaturedImage($content) {
 
global $post;
 
$original_content = $content;
 
   if ( current_theme_supports( 'post-thumbnails' ) ) {
 
        if ((is_page()) || $post->post_type != 'post') {
            return $content;
        }
        if (is_single() && in_the_loop()) {
            $content = the_post_thumbnail('page-single');
            $content .= $original_content;
        } elseif (in_the_loop()) {
            $content = the_post_thumbnail('index-categories');
            $content .= $original_content;
        }
 
    }
    return $content;
}
//add_filter( 'the_title', 'tgtinfo_InsertFeaturedImage' );

//edd_purchase meta boxes
function tgtinfo_paddmeta(){
    add_meta_box('tgtinfo_metabox','Purchase Data','tgtinfo_purchasemeta','edd_payment','normal','high');
}
function tgtinfo_purchasemeta($post){
    //Display purchase meta data
    $pmt = tgtinfo_pmtmeta($post->ID);
    $userdata = get_userdata($post->post_author);
    
    echo $userdata->user_login."<br />";
    echo $userdata->user_email."<br />";
    echo $userdata->user_url."<br />";
    echo $pmt['token']."<br />";
    echo "$ ".strval($pmt['amt'])."<br />";
    echo $pmt['plan']."<br />";
}

function tgtinfo_pmtmeta($postid){
    //returns array of token, amt, plan from pmt meta
    $pmt = array();
    $pmt['amt'] = get_post_meta($postid, 'tgtinfo_amount',true);
    $pmt['plan'] = get_post_meta($postid, 'tgtinfo_plan',true);
    $pmt['trx'] = get_post_meta($postid, 'tgtinfo_paypaltrx',true);
    return $pmt;
}


//Disable admin email on new user
if ( ! function_exists( 'wp_new_user_notification' ) ) :
	function wp_new_user_notification( $user_id, $plaintext_pass = '' ) {
		
		/** Return early if no password is set */
		if ( empty( $plaintext_pass ) )
			return;
			
		$user 		= get_userdata( $user_id );
		$user_login = stripslashes( $user->user_login );
		$user_email = stripslashes( $user->user_email );

		// The blogname option is escaped with esc_html on the way into the database in sanitize_option
		// we want to reverse this for the plain text arena of emails.
		$blogname = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );

		$message  = sprintf( __( 'Username: %s' ), $user_login) . "\r\n";
		$message .= sprintf( __( 'Password: %s' ), $plaintext_pass) . "\r\n";
		$message .= wp_login_url() . "\r\n";

		wp_mail( $user_email, sprintf( __( '[%s] Your username and password' ), $blogname ), $message );

	}
endif;

function tgtinfo_mandrill_payload($message) {
    //modify the message before sending out with Mandrill
    //
    //New user notification, add <br>
    if(in_array('wp_wp_new_user_notification', $message['tags']['automatic']))
    {
        $message['html'] = str_replace(PHP_EOL,'<br>',$message['html']);
    }
    if(in_array('wp_GFCommon send_email', $message['tags']['automatic']))
    {
        if (substr($message['subject'],0,9) == 'MyCurator' ) {
            $message['tags'][] = 'wp_Sale';
        }
    }

    return $message;
}

function tgtinfo_forgotMyPasswordEmails($nl2br,$message) {
    $nl2br = false;
    if ( in_array( 'wp_retrieve_password', $message['tags']['automatic'] ) ) {
        $nl2br = true;
    }
    return $nl2br;
}

function tgtinfo_custompurchase($tokens){
    //Add any custom user account display here such as payment buttons or messages
    foreach($tokens as $token) {
        if ($token == 'def076ef9724916e25a29466bf11b107' ) { //Mike Stuart 1st online add new client payment link to business plan
            echo '<h3><a href="'.site_url().'/business-plan-subscription/">CLICK HERE</a>&nbsp;&nbsp;to add a New Business Plan Subscription for a Client</h3>';
        }
    }
}

function newTI_BlogIndex() {
    $site= site_url();
    ?>
        <h3><a href="<?php echo site_url();?>/category/articles/">Articles</a> on How Content Curation can Help Your Web Presence</h3>
            <div class="selling-point">Some selected articles:</div>
            <?php
            $cat = get_cat_ID('Articles');
            $args = array( 'numberposts' => 3, 'orderby' => 'rand', 'category' => $cat );
            $rand_posts = get_posts( $args );
            foreach( $rand_posts as $post ) { 
                $title = get_the_title($post->ID);
                if (strlen($title) > 45) $title = substr($title,0,42).'...';?>
                <div class="selling-point">
                    <a href="<?php echo get_permalink($post->ID); ?>"><?php echo $title; ?></a></div>
            <?php } ?>
            <p><a href="<?php echo site_url();?>/category/articles/">Browse All Articles...</a></p>
        <hr />
        <h3><a href="<?php echo site_url();?>/category/how-to/">How To Articles</a> and 
                <a href="<?php echo site_url();?>/category/mycurator/">MyCurator Releases</a></h3>
            <div class="selling-point">How To Topics:</div>
            <?php
            wp_tag_cloud('smallest=8&largest=22');
             ?>
            <p><a href="<?php echo site_url();?>/category/how-to/">Browse All How To Articles...</a></p>
        <hr />
        <h3>Best Content Curation Articles <a href="<?php echo site_url();?>/category/content-curation/">from the Web</a></h3>
            <div class="selling-point">Some selected articles:</div>
            <?php
            $cat = get_cat_ID('Content Curation');
            $args = array( 'numberposts' => 3, 'orderby' => 'rand', 'category' => $cat );
            $rand_posts = get_posts( $args );
            foreach( $rand_posts as $post ) { 
                $title = get_the_title($post->ID);
                if (strlen($title) > 45) $title = substr($title,0,42).'...';?>
                <div class="selling-point">
                    <a href="<?php echo get_permalink($post->ID); ?>"><?php echo $title; ?></a></div>
            <?php } ?>
            <p><a href="<?php echo site_url();?>/category/content-curation/">Browse All Articles...</a></p>
            <hr>
<?php
}


function newTI_PurchaseWidget() {
    global $current_user;
    
    wp_get_current_user();
    $plan = get_user_meta($current_user->ID,'tgtinfo_plan',true);
    $maxnb = get_user_meta($current_user->ID,'tgtinfo_max_notebk',true);
    
    ?>
            <hr /><h2>Upgrade Your Account To:</h2>
    <?php
    $trial = stripos($plan,'trial');
    if (stripos($plan,'ind') !== false || $trial) { 
            //if ($maxnb == 0) $trial = true; //Already purchased unlimited notebooks, so just show Pro/Bus plans
            newTI_purchaseind($trial);
    } else { 
        if (stripos($plan,'pro') !== false) newTI_purchasepro();
        if (stripos($plan,'bus') !== false) newTI_purchasebus();
    }
     
}

function newTI_purchaseind($trial) {
    $src = plugins_url('images/tick.png',__FILE__);
    $button = plugins_url('images/download-button.png',__FILE__);
    $site= site_url();
    
    if ($trial) {
        echo '<h3 title="Curate content for a variety of interests and news">Pro Plan - $15 per Month</h3>';
        echo "<p>Power 2 sites and 6 Topics with SEO building content curation!</p>";
    } else {
        echo '<h3 title="Expand your curation with more Topics, Sources and Notebooks">Pro Plan - $15 per Month</h3>';
        echo "<p>Add another site and more curation Topics, Sources and Notebooks!</p>";
    } ?>
            <ul>
    <li>Up to 6 Topics of Curated Content </li>
    <li>Use on 2 Sites </li>
    <li>Unlimited Notebooks </li>
    <li>Unlimited Sources </li>
            </ul>
    <div class="wp-block-buttons"> 
    <div class="wp-block-button"  id="pricing" ><a class="wp-block-button__link" href="<?php echo $site;?>/pro-plan-subscription/">BUY PRO PLAN</a></div>
    </div>
    <br /><hr>
    <h3  title="For online media, businesses and content aggregators">Business Plan - $30 per Month</h3>
    <p title="For online media, businesses and content aggregators">High Volume Curation over multiple sites and Unlimited Topics, Sources and Notebooks</p>
    <ul>
    <li>Unlimited Topics of Curated Content </li>
    <li>Includes 6 Sites</li>
    <li>Unlimited Notebooks </li>
    <li>Unlimited Sources </li>
    </ul>
    <div class="wp-block-buttons"> 
    <div class="wp-block-button"  id="pricing" ><a class="wp-block-button__link" href="<?php echo $site;?>/business-plan-subscription/">BUY BUSINESS PLAN</a></div>
    </div>  
    <br />
   

    <?php
}

function newTI_purchasepro() {
    $src = plugins_url('images/tick.png',__FILE__);
    $button = plugins_url('images/download-button.png',__FILE__);
    $site= site_url();
    ?>
    
    <h3  title="For online media, businesses and content aggregators">Business Plan - $30 per Month</h3>
    <p title="For online media, businesses and content aggregators">High Volume Curation over multiple sites</p>
    <ul>
    <li>Unlimited Topics of Curated Content </li>
    <li>Includes 6 Sites - Volume discounts for additional sites</li>
    <li>Priority support and consultation </li>
    <li>Unlimited Sources and Notebooks </li>
    <li>We will Cancel your Pro Plan and refund last payment </li>
    </ul>
    <div class="wp-block-buttons"> 
    <div class="wp-block-button"  id="pricing" ><a class="wp-block-button__link" href="<?php echo $site;?>/business-plan-subscription/">BUY BUSINESS PLAN</a></div>
    </div>
        <br /><hr>
   <h3>Enterprise Support and Consulting</h3>
    <p>Complete support for your Business Requirements</p>
    <ul>
    <li>Volume Discounts on Multiple Sites </li>
    <li>White Label Version of MyCurator </li>
    <li>Customization and Integration Services </li>
    <li>WordPress Multi-site Network Support </li>
    </ul>
    <div class="wp-block-buttons"> 
    <div class="wp-block-button"  id="pricing" ><a class="wp-block-button__link" href="<?php echo $site;?>/contact-us/">CONTACT US</a></div>
    </div>


    <?php
}

function newTI_purchasebus() {
    $src = plugins_url('images/tick.png',__FILE__);
    $button = plugins_url('images/download-button.png',__FILE__);
    $site= site_url();
    ?>
   
    <h3>Enterprise Support and Consulting</h3>
    <p>Complete support for your Business Requirements</p>
    <ul>
    <li>Volume Discounts on Multiple Sites </li>
    <li>White Label Version of MyCurator </li>
    <li>Customization and Integration Services </li>
    <li>WordPress Multi-site Network Support </li>
    </ul>
    <div class="wp-block-buttons"> 
    <div class="wp-block-button"  id="pricing" ><a class="wp-block-button__link" href="<?php echo $site;?>/contact-us/">CONTACT US</a></div>
    </div>
  
    

    <?php
}

function newTI_DocIndex() {
    $site= site_url();
    ?>
        <p>The documentation is intended as a reference for the fields and values that are used to 
            set up and operate the MyCurator content curation platform.  Use the Table of Contents
            below to navigate through the documentation pages.  This documentation will 
            be kept up to date with each release of MyCurator.  The training videos provide a broader 
            overview of how the various aspects of MyCurator work together to provide a content curation platform.
            </p>
        <ul>
	<li><a href="<?php echo $site;?>/documentation/getting-started/">Getting Started</a></li>
        <li><a href="<?php echo $site;?>/documentation-2/documentation-dashboard/">Dashboard</a></li>
        <li><a href="<?php echo $site;?>/documentation-2/documentation-sources/">Sources</a></li>
        <li><a href="<?php echo $site;?>/documentation-2/documentation-topics/">Topics</a></li>
        <li><a href="<?php echo $site;?>/documentation-2/documentation-manual-curation/">Manual Curation</a></li>
        <li><a href="<?php echo $site;?>/documentation-2/documentation-training/">Training</a></li>
        <li><a href="<?php echo $site;?>/documentation-2/documentation-get-it/">Get It</a></li>
	<li><a href="<?php echo $site;?>/documentation-2/documentation-source-it/">Source It</a></li>
        <li><a href="<?php echo $site;?>/documentation-2/google-alerts/">Google Alerts</a></li>
	<li><a href="<?php echo $site;?>/documentation-2/documentation-options/">Options</a></li>
        <li><a href="<?php echo $site;?>/documentation-2/documentation-notebooks/">Notebooks</a></li>
	<li><a href="<?php echo $site;?>/documentation-2/documentation-bulk-curation/">Bulk Curation</a></li>
	<li><a href="<?php echo $site;?>/documentation-2/documentation-custom-post-types/">Custom Post Types</a></li>
	<li><a href="<?php echo $site;?>/documentation-2/documentation-multi-curation/">[Multi] Article Curation</a></li>
	<li><a href="<?php echo $site;?>/documentation-2/documentation-auto-post/">Auto Post</a></li>
	<li><a href="<?php echo $site;?>/documentation-2/documentation-logs/">Logs</a></li>
	<li><a href="<?php echo $site;?>/documentation-2/documentation-twitter-api/">Twitter API</a></li>
	<li><a href="<?php echo $site;?>/documentation-2/documentation-formatting/">Training Posts Formatting</a></li>
	<li><a href="<?php echo $site;?>/documentation-2/documentation-international/">International</a></li>
</ul>
        <?php
}

function newTI_TrainVideos() {
    //Display training videos and swap in the code for the one they click
    
    ?>
            <h2>Getting Started Videos</h2>
            <p>View the Quick Start video to set up MyCurator using the Setup Wizard on your Dashboard and start seeing articles for curation within minutes. 
                View the Get It video to set up and use our bookmarklet to capture content while you browse on your computer, phone and tablet.
                Learn about the processing and status information available on the MyCurator Dashboard with the Dashboard video.</p>
            <div class="wp-block-buttons">
                <div class="wp-block-button" onclick="mctaishowvideo1('//www.youtube.com/embed/jSe4cFlSumU?autoplay=0&rel=0')"><a class="wp-block-button__link" >View Quick Start</a></div>
                <div class="wp-block-button" onclick="mctaishowvideo1('//www.youtube.com/embed/hsGJIL4tQMQ?autoplay=0&rel=0')"><a class="wp-block-button__link" >View Get It</a></div>
                <div class="wp-block-button" onclick="mctaishowvideo1('//www.youtube.com/embed/iKg0ndygdTQ?autoplay=0&rel=0')"><a class="wp-block-button__link" >View Dashboard</a></div>
            
                <div id="quick" style="display: none" height="325">
                <iframe id="quickframe" src="" width="600" height="300" frameborder="0" allowfullscreen="allowfullscreen"></iframe>
                <div class="wp-block-button"  onclick="document.getElementById('quick').style.display = 'none';
                    document.getElementById('quickframe').src = ''" ><a class="wp-block-button__link" >Close Video</a></div>
                </div>
            </div>
            <script>
            function mctaishowvideo1(src) {
                    document.getElementById("quickframe").src = src;
                    document.getElementById("quickframe").style = "";
                    document.getElementById("quick").style.display = "block";
            }
            </script>
            <hr />
            
            <h2>Sources Videos</h2>
            <p>Sources are RSS feeds that tell MyCurator where to find articles.  View the Sources video for an overview of Sources and 
            the Sources menu item.  Our Source It bookmarklet makes it easy to capture the RSS feed from any site that you visit.  
            Our Google Alerts video shows how to create a Google alert, a great source of articles for your curation.  The News Sources video 
            shows how to quicly add Google News, Bing News and Twitter sources.  View the Manually Add Sources video to learn how to add RSS feed 
            sources manually.</p>
            <div class="wp-block-buttons">
                <div class="wp-block-button" onclick=" mctaishowvideo2('//www.youtube.com/embed/Nyn8EVA4Oc4?autoplay=0&rel=0')"><a class="wp-block-button__link" >View Sources</a></div> 
                <div class="wp-block-button" onclick=" mctaishowvideo2('//www.youtube.com/embed/JO66meRuuKw?autoplay=0&rel=0')"><a class="wp-block-button__link" >View Source It</a></div> 
                <div class="wp-block-button" onclick=" mctaishowvideo2('//www.youtube.com/embed/G5S3lD1CQjA?autoplay=0&rel=0')"><a class="wp-block-button__link" >View Google Alerts</a></div> 
                <div class="wp-block-button" onclick="mctaishowvideo2('//www.youtube.com/embed/ryBQrV7mwXw?autoplay=0&rel=0')"><a class="wp-block-button__link" >View News/Twitter Sources</a></div> 
                <div class="wp-block-button" onclick="mctaishowvideo2('//www.youtube.com/embed/ToiBRPwB0aw?autoplay=0&rel=0')"><a class="wp-block-button__link" >View Manually Add Sources</a></div> 
                </p>
                <div id="source" style="display: none" height="325">
                <iframe id="sourceframe" src="" width="600" height="300" frameborder="0" allowfullscreen="allowfullscreen"></iframe>
                <div class="wp-block-button" onclick="document.getElementById('source').style.display = 'none';
                      document.getElementById('sourceframe').src = ''" ><a class="wp-block-button__link" >Close Video</a></div>
                </div>
            </div>
            <script>
            function mctaishowvideo2(src) {
                    document.getElementById("sourceframe").src = src;
                    document.getElementById("sourceframe").style = "";
                    document.getElementById("source").style.display = "block";
            }
            </script>
            <hr />
            
            <h2>Topics Videos</h2>
            <p>Topics tell MyCurator what keywords to look for in each article provided by your Sources.  View the Topics video for 
            an overview of Topics and using the Topics list.  View the Topics Add/Edit video for details on the fields and values of 
            the adding or editing a Topic.</p>
            <div class="wp-block-buttons">
                <div class="wp-block-button" onclick="mctaishowvideo3('//www.youtube.com/embed/F23bw7bgOtg?autoplay=0&rel=0')"><a class="wp-block-button__link" >View Topics</a></div> 
                <div class="wp-block-button" onclick="mctaishowvideo3('//www.youtube.com/embed/R_1hCboTRH4?autoplay=0&rel=0')"><a class="wp-block-button__link" >View Topics Add/Edit</a></div> 
                <div id="topic" style="display: none" height="325">
                <iframe id="topicframe" src="" width="600" height="300" frameborder="0" allowfullscreen="allowfullscreen"></iframe>
                <div class="wp-block-button" onclick="document.getElementById('topic').style.display = 'none';
                      document.getElementById('topicframe').src = ''" ><a class="wp-block-button__link" >Close Video</a></div>
                </div>
            </div>
            <script>
            function mctaishowvideo3(src) {
                    document.getElementById("topicframe").src = src;
                    document.getElementById("topicframe").style = "";
                    document.getElementById("topic").style.display = "block";
            }
            </script>
            <hr />
            
            <h2>Curation & Training Videos</h2>
            <p>View the Curation & Training video to learn all about the Training Posts page, curating articles and training MyCurator 
            to find the best articles for your site.  The Notebooks video highlights using Notebooks for more complex curations and 
            helping to write your own articles.  View the Logs video to understand how to tweak MyCurator processing and solve problems.</p>
            <div class="wp-block-buttons">
                <div class="wp-block-button" onclick="mctaishowvideo4('//www.youtube.com/embed/bJo1Gc1xieM?autoplay=0&rel=0')"><a class="wp-block-button__link" >View Curation & Training</a></div> 
                <div class="wp-block-button" onclick="mctaishowvideo4('//www.youtube.com/embed/Kmq7SwTRkLw?autoplay=0&rel=0')"><a class="wp-block-button__link" >View Notebooks</a></div> 
                <div class="wp-block-button" onclick="mctaishowvideo4('//www.youtube.com/embed/yhly-1l9iYQ?autoplay=0&rel=0')"><a class="wp-block-button__link" >View Logs</a></div> 
                <div id="curate" style="display: none" height="325">
                <iframe id="curateframe" src="" width="600" height="300" frameborder="0" allowfullscreen="allowfullscreen"></iframe>
                <div class="wp-block-button" onclick="document.getElementById('curate').style.display = 'none';
                      document.getElementById('curateframe').src = ''" ><a class="wp-block-button__link" >Close Video</a></div>
                </div>
            </div>
            <script>
            function mctaishowvideo4(src) {
                    document.getElementById("curateframe").src = src;
                    document.getElementById("curateframe").style = "";
                    document.getElementById("curate").style.display = "block";
            }
            </script>
            <hr />
            
    <?php
}
?>
