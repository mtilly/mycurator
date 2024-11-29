<?php
//Admin reports for target info marketing site
//

//Display purchase admin menu
function tgtinfo_createmenu() {
    
    add_menu_page('Admin', 'Admin','publish_posts',__FILE__,'tgtinfo_adminpage');
    add_submenu_page(__FILE__,'Details', 'Details','publish_posts',__FILE__.'_details','tgtinfo_adminpage');
    add_submenu_page(__FILE__,'Active Users', 'Active Users','publish_posts',__FILE__.'_callsmth','tgtinfo_callsmth');
    add_submenu_page(__FILE__,'User Activity', 'User Activity','publish_posts',__FILE__.'_useract','tgtinfo_user_activity');
    add_submenu_page(__FILE__,'Error Counts', 'Error Counts','publish_posts',__FILE__.'_errorcnt','tgtinfo_errorrpt');
    add_submenu_page(__FILE__,'Access Counts', 'Access Counts','publish_posts',__FILE__.'_accesscnt','tgtinfo_accessrpt');
    add_submenu_page(__FILE__,'Add Payment', 'Add Payment','publish_posts',__FILE__.'_addpmt','tgtinfo_addpmt');
    add_submenu_page(__FILE__,'Send Emails', 'Send Emails','publish_posts',__FILE__.'_sendemail','sendEmailsFromFile');
    add_submenu_page(__FILE__,'Paid Plans', 'Paid Plans','publish_posts',__FILE__.'_paidplan','tgtinfo_paid_plans');
    
}

//Admin pages
function tgtinfo_adminpage(){
    //Display admin page to review accounts, create purchases from paypal, update cs_validate
    global $wpdb;
   ini_set('memory_limit','512M'); 
   $wpuser = '';
   $foundit = false;
   $token = '';
   $userid = 0;
   //Set up user email dropdown
   $allusers = get_users(array('orderby' => 'user_email'));
   
   if (isset($_REQUEST['cancel-plan'])){
       if (empty($_REQUEST['end_date'])){
           $msg = "No End Date for Cancel";
       } else {
           $enddate = $_REQUEST['end_date'];
           $userid = $_REQUEST['userid'];
           $plan = get_user_meta($userid,'tgtinfo_plan',true);
           $newplan = $plan.' Trial';
           //Add Payment Record
           $details = array(
              'post_author' => $userid,
              'post_title'  =>  get_user_meta($userid,'first_name',true).' '.get_user_meta($userid,'last_name',true),
              'post_type' => 'edd_payment',
              'post_status' => 'publish'
           );
           $post_id = wp_insert_post($details);
           $paymsg = "Cx ".$plan." on ".date(get_option('date_format'));
           update_post_meta($post_id,'tgtinfo_amount',0);
           update_post_meta($post_id,'tgtinfo_plan',$newplan);
           update_post_meta($post_id,'tgtinfo_paypaltrx',$paymsg);
           //Update user meta
           update_user_meta($userid,'tgtinfo_plan',$newplan);  //plan into user meta too
           //update end date
           $ret = $wpdb->update('wp_cs_validate', array('end_date' => $enddate),array('user_id' => $userid));
           $msg = "Subscription Cancelled";
       }
   }
   if (isset($_REQUEST['update-end-date'])){
       $enddate = $_REQUEST['end_date'];
       $updtoken = $_REQUEST['upd_token'];
       $userid = $_REQUEST['userid'];
       if (empty($enddate)) {
           $ret = $wpdb->query("UPDATE `wp_cs_validate` SET `end_date` = NULL WHERE `user_id` = '$userid'");
       } else {
          $ret = $wpdb->update('wp_cs_validate', array('end_date' => $enddate),array('user_id' => $userid));
       }
       $msg = "Date Updated: Ret: ".strval($ret);
   }
   if (isset($_REQUEST['delete-token'])){
       $userid = $_REQUEST['userid'];
       $deltoken = $_REQUEST['del-token'];
       $ret = $wpdb->query("DELETE FROM `wp_cs_validate` WHERE `token` = '$deltoken'");
       $tokens = get_user_meta($userid, 'tgtinfo_apikey', true);
       foreach ($tokens as $ind => $tok) {
           if ($tok == $deltoken) unset($tokens[$ind]);
       }
       update_user_meta($userid, 'tgtinfo_apikey', $tokens);
       $msg = "Token Deleted: Ret: ".strval($ret);
   }
   if (isset($_REQUEST['update-max'])){
       $userid = $_REQUEST['userid'];
       if ($_REQUEST['max-topic'] != null) update_user_meta($userid,'tgtinfo_max_topic',$_REQUEST['max-topic']);
       if ($_REQUEST['max-sites'] != null) update_user_meta($userid,'tgtinfo_max_sites',$_REQUEST['max-sites']);
       if ($_REQUEST['max-notebk'] != null) update_user_meta($userid,'tgtinfo_max_notebk',$_REQUEST['max-notebk']);
       if ($_REQUEST['max-source'] != null) update_user_meta($userid,'tgtinfo_max_source',$_REQUEST['max-source']);
   }
   if (isset($_REQUEST['search']) ){
       if (isset($_REQUEST['token']) && strlen($_REQUEST['token']) == 32) {
           $tuser = $wpdb->get_var('Select user_id from wp_cs_validate where token = "'.$_REQUEST['token'].'"');
           $theuser = get_user_by('id', $tuser);
       } elseif (isset($_REQUEST['paypal']) && strlen($_REQUEST['paypal']) > 2) {
           //Get postmeta payment record
           $details = array(
                  'numberposts' => 1,
                  'post_type' => 'edd_payment',
                  'meta_key' => 'tgtinfo_paypaltrx',
                  'meta_value' => sanitize_text_field($_REQUEST['paypal'])
            );
            $posts = get_posts($details);
            if (!empty($posts)) {
                $tuser = $posts[0]->post_author;
                $theuser = get_user_by('id', $tuser);
            }
            else {
               $tuser = $wpdb->get_var('Select created_by from wp_gf_entry where transaction_id = "'.$_REQUEST['paypal'].'"'); 
               $theuser = get_user_by('id', $tuser);
            }
       } elseif (isset($_REQUEST['userid']) && strlen($_REQUEST['userid']) >= 1) {
           $theuser = get_user_by('id', $_REQUEST['userid']);
       } else {
           $theuser = get_user_by('email', $_REQUEST['email']);
       }
       $wp_user = $theuser->data;
       $sql = "SELECT * FROM `wp_cs_validate` WHERE `user_id` = $wp_user->ID";
       $valid = $wpdb->get_row($sql);
       $foundit = true;
       $userid = $wp_user->ID;
   }
   
   //Get another key if clicked
    if (isset($_REQUEST['add-token'])){
        $userid = $_REQUEST['userid'];
        $user_data = get_userdata($userid);
        $newtoken = md5('tgtinfo-'.$user_data->user_email.strval(rand(100,10000)));
        $tokens = get_user_meta($user_data->ID, 'tgtinfo_apikey', true);
        $tokens[] = $newtoken;
        update_user_meta($user_data->ID, 'tgtinfo_apikey', $tokens);
        //Update wp_cs_validate
        $plan = get_user_meta($user_data->ID,'tgtinfo_plan',true);
        if (stripos($plan,'pro') !== false) {
            $vplan = 'MyCurator Pro';
        } else {
            $vplan = 'MyCurator Bus';
        }
        $sql = "INSERT INTO `wp_cs_validate` (`token`, `user_id`, `product`) VALUES ('$newtoken', '$user_data->ID', '$vplan')";
        $newid = $wpdb->query($sql);
        $msg = "New Token: ".$newtoken." Return from query".$newid;
    }
    ?>
    <div class='wrap' >
        <div class="postbox-container">
           
            <h2>Target Info Payment Admin</h2> 
            <?php if (!empty($msg)){ ?>
               <div id="message" class="updated" ><p><strong><?php echo $msg ; ?></strong></p></div>
            <?php } ?>
            <form method="post" action="<?php echo esc_url($_SERVER['REQUEST_URI'] ); ?>" >
            <table class="form-table" >
                <tr>
                    <th scope="row">Email to Find</th>
                    <th scope="row">Token to Find</th>
                    <th scope="row">Paypal Trx to Find</th>
                    <th scope="row">user ID to Find</th>
                </tr>
                <tr>
                    <td><select name="email" >
                    <?php foreach ($allusers as $users){ ?>
                        <option value="<?php echo $users->user_email; ?>" <?php selected($users->ID,$userid); ?> ><?php echo $users->user_email; ?></option>
                    <?php } //end foreach ?>
                        </select></td>       
                     <td><input name="token" type="input" size="35"  /></td>  
                     <td><input name="paypal" type="input" size="30"  /></td>
                     <td><input name="userid" type="input" size="20"  /></td>
                </tr>
            </table>
            <div class="submit">
              <input name="search" type="submit" value="search" class="button-primary" />
            </div>
            </form>   
<?php
    if ($foundit) { 
        $plan = get_user_meta($wp_user->ID,'tgtinfo_plan',true);?>
         <div class="postbox-container">
             <h3>User Data</h3>
            <form method="post" action="<?php echo esc_url($_SERVER['REQUEST_URI'] ); ?>" >
            <table class="form-table" >
                <tr>
                    <th scope="row">ID</th>
                    <th scope="row">Login Name</th>
                    <th scope="row">Email</th>
                    <th scope="row">Plan</th>
                    <th scope="row">Max Topics</th>
                    <th scope="row">Max Sites</th>
                    <th scope="row">Max Notebooks</th>
                    <th scope="row">Max Sources</th>
                    <th scope="row">End Date</th>
                </tr>
                <tr>
                    <td><?php echo $wp_user->ID; ?></td>    
                    <td><?php echo $wp_user->user_login; ?></td>   
                    <td><?php echo $wp_user->user_email; ?></td>  
                    <td><?php echo $plan; ?></td> 
                    <td><input name="max-topic" type="input" size="12" value="<?php echo get_user_meta($wp_user->ID,'tgtinfo_max_topic',true); ?>" /></td> 
                    <td><input name="max-sites" type="input" size="12" value="<?php echo get_user_meta($wp_user->ID,'tgtinfo_max_sites',true); ?>" /></td> 
                    <td><input name="max-notebk" type="input" size="12" value="<?php echo get_user_meta($wp_user->ID,'tgtinfo_max_notebk',true); ?>" /></td> 
                    <td><input name="max-source" type="input" size="12" value="<?php echo get_user_meta($wp_user->ID,'tgtinfo_max_source',true); ?>" /></td> 
                    <td><input name="end_date" type="input" size="12" value="<?php echo $valid->end_date; ?>" /></td> 
                </tr>
            </table> 
            
            <div class="submit">
              <input name="upd_token" type="hidden" value="<?php echo $valid->token; ?>" />
              <input type="hidden" name="userid" value="<?php echo $wp_user->ID; ?>" >
              <input name="update-end-date" type="submit" value="update-end-date" class="button-secondary" />
              <input name="add-token" type="submit" value="add-token" class="button-secondary" />
              <input name="update-max" type="submit" value="update-max" class="button-secondary" />
              <?php if (count(get_user_meta($wp_user->ID, 'tgtinfo_apikey', true)) > 1) { ?>
                  <input name="delete-token" type="submit" value="delete-token" class="button-secondary" />
                  <input name="del-token" type="input" size="50" >
              <?php } 
              if (stripos($plan,'Individual') === false && stripos($plan,'Trial') === false) { //Cancel button ?>
                  <input name="cancel-plan" type="submit" value="cancel-plan" class="button-secondary" />&nbsp;&nbsp;Make sure to enter end date
              <?php } ?>
            </div>
            </form>                   
<?php  
           echo tgtinfo_vol_rpt($wp_user->ID,true)."<br />"; 
           echo tgtinfo_userpmt_rpt($wp_user->ID)."<br />";
           echo tgtinfo_gfpmts_rpt($wp_user->ID);
   ?>         
         </div>           
    <?php 
    
    } 
        
}

function tgtinfo_gfpmts_rpt($userid){
    //return a report of user pmts 
    //
     global $wpdb;
    //get payments for this user
    $sql = "SELECT payment_date, source_url, payment_status, payment_amount, transaction_id"
            . " FROM wp_gf_entry WHERE created_by = ".$userid;
    $uposts = $wpdb->get_results($sql);
    if (empty($uposts)) return '';
    ob_start();  //buffer the report for later display
    ?>
<table class="user-pmts">
   
    <tr>
        <th>Date</th> 
        <th>Status</th>
        <th>Amount</th>
        <th>Plan</th>
        <th>Paypal Trx</th>
    </tr>
<?php
    foreach ($uposts as $post){
       
    ?>

    <tr>
        <td><?php echo $post->payment_date;?></td>
        <td><?php echo $post->payment_status;?></td>
        <td><?php echo $post->payment_amount;?></td>
        <td><?php echo substr($post->source_url,27);?></td>
        <td><?php echo $post->transaction_id;?></td>
    </tr>

<?php
    } ?>
    </table>
<?php
    return ob_get_clean();
    
}
//Display all users in the admin section, based on wp_cs_validate
function tgtinfo_allusers(){
    global $wpdb, $ai_logs_tbl, $ai_topic_tbl, $blog_id;
    
    $maxrow = 25;
    $alter = true;
    // Check whether to show our domains only
    $showti = isset($_GET['showti']) ? true : false;   
    //Check whether to sort 
    $tokensort = isset($_GET['token']) ? true : false;
    $prodsort = isset($_GET['product']) ? true : false;
    $trialsort = isset($_GET['trial']) ? true : false;
    //Set current page from get
    $currentPage = 1;
    if (isset($_GET['paged'])){
        $currentPage = $_GET['paged'];
    }
    //Get total rows available
    $sql = "SELECT COUNT(*) as myCount FROM `wp_cs_validate`";
    $counts = $wpdb->get_row($sql,ARRAY_A);
    $myCount = $counts['myCount'];
    
        ?>
    <div class='wrap'>
   
    <h2>MyCurator Subscribers</h2> 
    <p>put showti in get string to show just TI - put &token or &product or &trial in get string to sort respectively</p>
    
    <?php
       print("<div class=\"tablenav\">"); 
       $qargs = array(
           'paged' => '%#%');
       $page_links = paginate_links( array(
		'base' => add_query_arg($qargs ) ,
		'format' => '',
		'total' => ceil($myCount/$maxrow),
		'current' => $currentPage
	));
	//Pagination display
	if ( $page_links )
		echo "<div class='tablenav-pages'>$page_links</div>";

        //Get Values from Db
        $bottom = ($currentPage - 1) * $maxrow;
	$top = $currentPage * $maxrow;
        $sql = "SELECT v.token, v.classify_calls, v.product, v.end_date, u.user_email, u.user_login, u.ID, u.user_url
            FROM  `wp_cs_validate` v,  `wp_users` u
            WHERE v.user_id = u.ID";
        if ($tokensort) {
            $sql .= " ORDER BY v.token ASC LIMIT " . $bottom . "," . $maxrow;
        } elseif ($prodsort) {
            $sql .= " ORDER BY v.product, u.ID LIMIT " . $bottom . "," . $maxrow;
        } elseif ($trialsort) {
            $sql .= " ORDER BY v.end_date DESC LIMIT " . $bottom . "," . $maxrow;
        } else {
            $sql .= " ORDER BY u.user_email ASC LIMIT " . $bottom . "," . $maxrow;
        }
        $edit_vals = array();
        $edit_vals = $wpdb->get_results($sql, ARRAY_A);
        $a = 1;
        ?>
        </div>
        <table class="widefat" >
            <thead>
                <tr>
                <th>ID</th>
                <th>Email</th>
                <th>Login</th>
                <th>Website</th>
                <th>Token</th>
                <th>Product</th>
                <th>Calls</th>
                <th>End Date</th>
                <th>Trained?</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($edit_vals as $row){
                //Get training status
                $sql = "SELECT last_update, topic_aidbcat FROM wp_cs_topic WHERE token = '".$row['token']."'";
                $topics = $wpdb->get_results($sql);
                $tcnt = 0;
                $trained = 'No';
                if ($topics) {
                    foreach ($topics as  $topic){
                        if ($topic->topic_aidbcat != null)
                            $trained = "Yes";
                    }
                    $tcnt = count($topics);
                }
                //display this row?
               if ($showti && !tgtinfo_istgtinfo($row['user_url'])) continue;
               if (!$showti && tgtinfo_istgtinfo($row['user_url'])) continue;

                echo('<tr');
                if ($alter) {
		 	$alter = false;
		 	print(" class='alternate' ");
		} else {
			$alter = true;
		}
                $emailurl = admin_url()."admin.php?page=tgtinfo-admin/tgtinfo-reports.php_details&search=search&email=".$row['user_email'];
                echo ('>');
                echo('<td><a href="'.admin_url().'user-edit.php?user_id='.$row['ID'].'" >'.$row['ID'].'</a></td>');
                echo('<td><a href="'.$emailurl.'" target="_blank" >'.$row['user_email'].'</a></td>');
                echo('<td>'.$row['user_login'].'</td>');
                echo('<td><a href="'.$row['user_url'].'" target="_blank" >'.$row['user_url'].'</a></td>');
                echo('<td>'.$row['token'].'</td>');
                echo('<td>'.$row['product'].'</td>');
                echo('<td>'.$row['classify_calls'].'</td>');
                echo('<td>'.$row['end_date'].'</td>');
                echo('<td>'.$trained." $tcnt".'</td>');
                echo('</tr>');
            } ?>
           </tbody>
        </table>

<?php
}

function tgtinfo_callsmth() {
    //Print report of calls so far this week by user (used to be month)
    global $wpdb;
    

    //Put up display headers
    $maxrow = 50;
    $alter = true;
    // Check whether to show our domains only
    $showti = isset($_REQUEST['showti']) ? true : false;
    $referer = isset($_REQUEST['referer']) ? true : false;
    //Check whether to sort by token
    $tokensort = isset($_REQUEST['token']) ? true : false;
    $prodsort = isset($_REQUEST['product']) ? true : false;
    $endsort = isset($_REQUEST['enddate']) ? true : false;
    $regsort = isset($_REQUEST['registered']) ? true : false;
    //Set current page from get
    $currentPage = 1;
    if (isset($_GET['paged'])){
        $currentPage = $_GET['paged'];
    }
    //Get total rows available
    $sql = "SELECT COUNT(*) AS myCount, SUM(v.this_week) AS lastwk, SUM(v.classify_calls - v.run_tot) as thiswk
    FROM  `wp_cs_validate` AS v WHERE v.this_week > 0 ";
    $sums = $wpdb->get_row($sql,ARRAY_A);
    $myCount = $sums['myCount'];
        ?>
    <div class='wrap'>
   
    <h2>MyCurator Active Users as of Last Week: <?php echo $myCount." This Week: ".$sums['thiswk']." Last Week: ".$sums['lastwk']; ?></h2>
    <form method="get" action="admin.php" >
        <input type="hidden" name ="page" value="tgtinfo-admin/tgtinfo-reports.php_callsmth" />
    Sort by Token? <input name="token" type="checkbox" value="1" />&nbsp;&nbsp;
    Sort by Product? <input name="product" type="checkbox" value="1" />&nbsp;&nbsp;
    Sort by Registered? <input name="registered" type="checkbox" value="1" />&nbsp;&nbsp;
    Show TI? <input name="showti" type="checkbox" value="1" />&nbsp;&nbsp;
    <input name="Submit" type="submit" value="Sort" class="button-secondary" />
    </form>
    <?php
       print("<div class=\"tablenav\">"); 
       $qargs = array(
           'paged' => '%#%');
       $page_links = paginate_links( array(
		'base' => add_query_arg($qargs ) ,
		'format' => '',
		'total' => ceil($myCount/$maxrow),
		'current' => $currentPage
	));
	//Pagination display
	if ( $page_links )
		echo "<div class='tablenav-pages'>$page_links</div>";
        $a = 1;
        ?>
        </div>
        <table class="widefat" >
            <thead>
                <tr>
                <th>ID</th>
                <th>Email</th>
                <th>Website</th>
                <th>Token</th>
                <th>Product</th>
                <th>This Week</th>
                <th>Last Week</th>
                <th>Registered</th>
                </tr>
            </thead>
            <tbody>
            <?php        
    //Now get rows to display - only active users
    $bottom = ($currentPage - 1) * $maxrow;
    $top = $currentPage * $maxrow;
    $sql = "SELECT SUM( v.classify_calls ) AS classify_calls, SUM( v.this_week ) AS mth_total, SUM( v.run_tot ) AS run_total, 
        u.user_url, u.user_email, u.user_registered, u.ID, v.token, v.product
FROM  `wp_cs_validate` AS v
JOIN wp_users AS u ON v.user_id = u.ID
WHERE  v.this_week > 0
GROUP BY u.id ";
    if ($tokensort) {
        $sql .= " ORDER BY v.token LIMIT " . $bottom . "," . $maxrow;
    } elseif ($prodsort) {
        $sql .= " ORDER BY v.product LIMIT " . $bottom . "," . $maxrow;
    } elseif ($endsort) {
        $sql .= " ORDER BY v.end_date DESC LIMIT " . $bottom . "," . $maxrow;
    } elseif ($regsort) {
        $sql .= " ORDER BY u.user_registered LIMIT " . $bottom . "," . $maxrow;
    } else {
        $sql .= " ORDER BY mth_total DESC LIMIT " . $bottom . "," . $maxrow;
    }
    $calls = $wpdb->get_results($sql);
    foreach ($calls as $call){
        $thiscnt = $call->classify_calls - $call->run_total;

        //Display a row
        echo('<tr');
        if ($alter) {
                $alter = false;
                print(" class='alternate' ");
        } else {
                $alter = true;
        }
        if ($showti && !tgtinfo_istgtinfo($call->user_url)) continue;
        if (!$showti && tgtinfo_istgtinfo($call->user_url)) continue;
        $emailurl = admin_url()."admin.php?page=tgtinfo-admin/tgtinfo-reports.php_details&search=search&email=".$call->user_email;
        echo ('>');
        echo "<td>$call->ID</td>";
        echo "<td><a href='$emailurl'  target='_blank' >$call->user_email</a></td>";
        echo "<td><a href='$call->user_url' target='_blank' >$call->user_url</a></td>";
        echo "<td>$call->token</td>";
        echo "<td>$call->product</td>";
        //echo "<td>$call->end_date</td>";
        echo "<td>$thiscnt</td>";
        echo "<td>$call->mth_total</td>";
        echo "<td>$call->user_registered</td>";
        echo('</tr>');
    } ?>
           </tbody>
        </table>
    <?php

}

function tgtinfo_user_activity(){
    global $wpdb;
    
    $vals = array('total' => 0, 'notactive' => 0, 'dropped' => 0, 'active' => 0, 
        'highvol' => 0, 'bus' => 0, 'trial' => 0);
    $trials = 0;
    $trials_arr = array();
    //Get Totals
    $cols = $wpdb->get_col("SELECT COUNT(*) FROM (SELECT u.ID
FROM  `wp_cs_validate` AS v
INNER JOIN wp_users AS u ON v.user_id = u.ID
GROUP BY u.id
)temp");
    $vals['total'] = $cols[0]; 

    //Get all that were once active
    $sql = "SELECT SUM( v.classify_calls ) AS classify_calls, SUM( v.this_week ) AS mth_total, SUM( v.run_tot ) AS run_total, u.user_url, u.user_email, u.user_registered, u.id
FROM  `wp_cs_validate` AS v
INNER JOIN wp_users AS u ON v.user_id = u.ID
WHERE v.classify_calls > 0
GROUP BY u.id";
    $calls = $wpdb->get_results($sql);
    ?>
    <div class='wrap'>
    
    <h2>MyCurator User Activity</h2>    
    <?php
    foreach ($calls as $call){
        //if (tgtinfo_istgtinfo($call->user_url)) continue;
        $plan = get_user_meta($call->id,'tgtinfo_plan',true);
        $vals['wasactive'] += 1;
        if ($plan == "Business Plan") { 
            $expire = $wpdb->get_var("Select end_date from wp_cs_validate Where user_id = '$call->id'");
            if (!empty($expire)) {
                $today = time();  //Yes, so check end date
                $end_date = strtotime($expire);
                if ($today > $end_date) continue;
            }
            if (!tgtinfo_istgtinfo($call->user_url)) $vals['bus'] += 1;
        }
        if ($plan == "Pro Plan") { 
            $expire = $wpdb->get_var("Select end_date from wp_cs_validate Where user_id = '$call->id'");
            if (!empty($expire)) {
                $today = time();  //Yes, so check end date
                $end_date = strtotime($expire);
                if ($today > $end_date) continue;
            }
             if (!tgtinfo_istgtinfo($call->user_url)) $vals['pro'] += 1;
        }
        if ($call->mth_total > 0 || $call->classify_calls > $call->run_total) {  //still active if active last week or this week
            $vals['active'] += 1;
            if ($plan == "Business Plan Trial") { 
                
                $expire = $wpdb->get_var("Select end_date from wp_cs_validate Where user_id = '$call->id'");
                $today = time();  //Yes, so check end date
                $end_date = strtotime($expire);
                if ($today > $end_date) continue;
                $vals['bus_t'] += 1;
                $lastn = get_user_meta($call->id, 'last_name',true);
                $firstn = get_user_meta($call->id, 'first_name',true);
                $trials_arr[$trials] = array($call->user_email,$firstn,$lastn,$call->user_registered,$expire,"Business Plan Trial");
                $trials += 1;
            }
            if ($plan == "Pro Plan Trial") { 
                
                $expire = $wpdb->get_var("Select end_date from wp_cs_validate Where user_id = '$call->id'");
                $today = time();  //Yes, so check end date
                $end_date = strtotime($expire);
                if ($today > $end_date) continue;
                $vals['pro_t'] += 1;
                $lastn = get_user_meta($call->id, 'last_name',true);
                $firstn = get_user_meta($call->id, 'first_name',true);
                $trials_arr[$trials] = array($call->user_email,$firstn,$lastn,$call->user_registered,$expire,"Pro Plan Trial");
                $trials += 1;
            }
        } else {
            $vals['dropped'] += 1;
        }
    }
    $vals['notactive'] = $vals['total']-$vals['wasactive'];
    ?>
    <p>Total Users to-date: <?php echo $vals['total']; ?></p>
    <p>Users Never Active : <?php echo $vals['notactive']; ?></p>
    <p>Active at one time : <?php echo $vals['wasactive']; ?></p>
    <p>Users dropped out  : <?php echo $vals['dropped']; ?></p>
    <p>Current Active User: <?php echo $vals['active']; ?></p>
    <p>Business Plan User : <?php echo $vals['bus']; ?></p>
    <p>Active Business Trial User  : <?php echo $vals['bus_t']; ?></p>
    <p>Pro Plan User : <?php echo $vals['pro']; ?></p>
    <p>Active Pro Trial User  : <?php echo $vals['pro_t']; ?></p>
    <h3>Trial Customers</h3>
<?php 
    foreach ($trials_arr as $tr){
        echo "<p> $tr[0] $tr[1] $tr[2] $tr[3] $tr[4] $tr[5] $tr[6] </p>";
    }
}

function tgtinfo_istgtinfo($url){
    $ti_dom = array("localhost/plugindev","memepost", "target-info","localhost/mumycdev");
    
    foreach ($ti_dom as $ti){
        if (stripos($url, $ti) !== false) return true;
    }
    return false;
}

function tgtinfo_errorrpt(){
    //Counts error types in error log
    echo "<p><a href='http://tgtinfo.net/mycurator_cloud_reports.php/?logfile=page_log.bak' >Page Log .BAK</a></p>";
    echo "<p><a href='http://tgtinfo.net/mycurator_cloud_reports.php/?logfile=php_errorlog.bak' >Error Log .BAK</a></p>";
    echo "<p><a href='http://tgtinfo.net/mycurator_cloud_reports.php/?logfile=page_log' >Page Log</a></p>";
    echo "<p><a href='http://tgtinfo.net/mycurator_cloud_reports.php/?logfile=php_errorlog' >Error Log</a></p>";
    exit();

    echo "<h2>Error Counts in Log</h2>";
    $logfile = "/home/tgaitest/public_html/tgtinfo.net/php_errorlog";
    if (isset($_POST['logfile'])){
        $cnts = array();
        $ips = array();
        $vers = array();
        $mublogs = array();
        $dbstr = array();
        $old = array();
        $totln = 0;
        $file = sanitize_text_field($_POST['logfile']);
        $fp = fopen($file, "r");
        while (($line = fgets($fp)) !== false) {
            $cnts['total'] += 1;
            if ($pos = strpos($line,'DB ')) { 
                $cnts['db'] += 1;
                if (strpos($line,'Cache',$pos)) { 
                    $cnts['dbi'] += 1;
                } else {
                    $dbstr[] = $line;
                }
            } elseif ($pos = strpos($line,' DB')) {
                $cnts['db'] += 1;
                if (strpos($line,'Cache',$pos)) { 
                    $cnts['dbi'] += 1;
                } else {
                    $dbstr[] = $line;
                }
            }
            if (strpos($line,'Invalid Token')) $cnts['token'] += 1;
            if (strpos($line,'Expired')) $cnts['end'] += 1;
            if (strpos($line,'Invalid Service')) $cnts['product'] += 1;
            if (strpos($line,'Curl')) {
                $cnts['curl'] += 1;
                if (strpos($line,'Curl error: 0'))$cnts['timeout'] += 1;
            }
            if (strpos($line,'Diffbot Error')){ 
                $cnts['differr'] += 1;
                if (strpos($line,'Request timed out')) $cnts['dbtime'] += 1;
                if (strpos($line,'download page')) $cnts['dbdown'] += 1;
            }
            if (strpos($line,'Killing Sub'))$cnts['kill'] += 1;
            if (strpos($line,'Render')) $cnts['render'] += 1;
            if (strpos($line,'Page Text')) $cnts['ptext'] += 1;
            if (strpos($line,'Page Loaded')) $cnts['loaded'] += 1;
            if (strpos($line,'relevance')) $cnts['ai'] += 1;
            if (strpos($line,'Encoded:')) $cnts['encode'] += 1;
            if (strpos($line,'Encoded: UTF-8')) $cnts['utf8'] += 1;
            if (strpos($line,'Version:')) {
                $ip = preg_replace('{^.*\[client\s([^\]]*)\].*$}','$1',$line);
                $token = trim(preg_replace('{^.*Token:\s([^,\s]*)[,\s].*$}','$1',$line));
                $ver = preg_replace('{^.*Version:\s([0-9.UnkOld]*).*$}','$1',$line);
                
                $ips[$token] = $ver;
            }
            if (strpos($line,'MU Net:')) {
                $token = trim(preg_replace('{^.*MU Net:\s([^\s]*)[\s].*$}','$1',$line));
                $blogcnt = trim(preg_replace('{^.*Blogs:\s([^\s]*)[\s].*$}','$1',$line));
                $mublogs[$token] = $blogcnt; //Will always get the last count for this token
            }
            if (strpos($line,'Local Cache')) $cnts['lcl'] += 1;
            if (strpos($line,'Cache Hit')) $cnts['dchit'] += 1;
            if (strpos($line,'Got Page')) $cnts['rgot'] += 1;
            if (strpos($line,'Request Inserted')) $cnts['rinsert'] += 1;
            if (strpos($line,'Request Exists')) $cnts['rexist'] += 1;
            if (strpos($line,'Direct Classify')) $cnts['dclass'] += 1;
        }
        echo 'Total Lines: '.strval($cnts['total'])."<br /><br />";
        if (strpos($file,'php_errorlog')) {
            echo '==================<br /><br />';
            $totln = $cnts['lcl']+$cnts['dchit']+$cnts['rgot']+$cnts['rinsert']+$cnts['dclass']+$cnts['rexist'];
            echo 'Total Calls: '.strval($totln)."<br /><br />";
            echo 'Requests: '.strval($cnts['rgot']+$cnts['rinsert']+$cnts['rexist']).' '.number_format(strval($cnts['rgot']+$cnts['rinsert']+$cnts['rexist'])/$totln,2)."<br /><br />";
            $totln = $totln - $cnts['rinsert'];
            echo 'Request In (dbl cnt): '.strval($cnts['rinsert'])."<br /><br />";
            echo 'New Total Calls: '.strval($totln)."<br /><br />";
            echo 'Local Cache Classify: '.strval($cnts['lcl']).' '.number_format(strval($cnts['lcl']/$totln),2)."<br /><br />";
            echo 'Direct Calls: '.strval($cnts['dchit']+$cnts['dclass']).' '.number_format(strval(($cnts['dchit']+$cnts['dclass'])/$totln),2)."<br /><br />";
            echo 'Direct Cache Hits: '.strval($cnts['dchit']).' '.number_format(strval($cnts['dchit'])/strval(($cnts['dchit']+$cnts['dclass'])),2)."<br /><br />";
            echo 'Direct Error: '.number_format(strval(($cnts['differr']+$cnts['render'])/($cnts['dclass'])),2)."<br /><br />";
            echo 'Requests Extra Out & Exist: '.strval($cnts['rgot']-$cnts['rinsert']).' '.strval($cnts['rexist'])."<br /><br />";
            echo 'Request Cache & Error: '.number_format(strval((($cnts['rgot']-$cnts['rinsert'])+$cnts['rexist'])/$totln),2).' '.number_format(strval($cnts['ptext']/$cnts['rinsert']),2)."<br /><br />";
            echo 'Diffbot Classify: '.strval($cnts['rinsert']+$cnts['dclass']).' '.number_format(strval(($cnts['rinsert']+$cnts['dclass'])/$totln),2)."<br /><br />";                 
            echo '==================<br /><br />';
        } else {
            echo 'Page Loaded & Errors: '.strval($cnts['loaded']).' '.number_format(strval(($cnts['differr']+$cnts['render'])/$cnts['loaded']),2)."<br /><br />";
        }
        echo 'DB Errors: '.strval($cnts['db'])."<br /><br />";
        echo 'DB Insert Error Into Cache: '.strval($cnts['dbi'])."<br /><br />";
        if (count($dbstr)) {
            foreach ($dbstr as $dstr) {
                echo $dstr."<br />";
            }
            echo "<br />";
        }
        echo 'Invalid Token: '.strval($cnts['token'])."<br /><br />";
        echo 'Token expired: '.strval($cnts['end'])."<br /><br />";
        echo 'Invalid Product: '.strval($cnts['product'])."<br /><br />";
        echo 'Page Kill: '.strval($cnts['kill'])."<br /><br />";
        echo 'Curl errors: '.strval($cnts['curl'])."<br /><br />";  //Curl errors are included in Page Render Errors
        echo 'Curl Timeouts: '.strval($cnts['timeout'])."<br /><br />";
        echo 'DBot Error: '.strval($cnts['differr'])."<br /><br />";
        echo 'DBot Timeouts: '.strval($cnts['dbtime'])."<br /><br />";
        echo 'DBot Downloads: '.strval($cnts['dbdown'])."<br /><br />";
        echo 'Page Render: '.strval($cnts['render'])."<br /><br />";
        echo 'Page Text: '.strval($cnts['ptext'])."<br /><br />";
        echo 'Encode-Topic: '.strval($cnts['encode'])."<br /><br />";
        echo 'UTF-8 Option: '.strval($cnts['utf8'])."<br /><br />";
        echo 'Versions<br />';
        //Get versions
        foreach ($ips as $token => $ver) {
            $vers[$ver] += 1;
        }
        foreach ($vers as $ver => $cnt) {
            echo $ver.' => '.strval($cnt).'<br />';
        }
        echo "Total: ".count($ips)."<br /><br />";
        echo "MU Blog Counts";
        echo '<br />';
        foreach ($mublogs as $key => $mu){
            echo $key." => ".$mu."<br /><br />";
        }
        echo '<br />';
        echo '<br />';
    }
    ?>
    <form method="post" action="<?php echo esc_url($_SERVER['REQUEST_URI'] ); ?>" >
    <input name="logfile" type="text" size="250" value="/home/tgaitest/public_html/tgtinfo.net/php_errorlog" />
    <div class="submit">
              <input name="submit" type="submit" value="submit" class="button-primary" />
    </div>
    </form>   
        
     <?php
}

function tgtinfo_addpmt(){
   //Add a manual payment (from invoice or other reason)
   global $wpdb;
   //Set up user email dropdown
   ini_set('memory_limit','512M'); 
   $allusers = get_users(array('orderby' => 'user_email'));
   $foundit = false;
   
   if (isset($_REQUEST['search']) && isset($_REQUEST['email']) && !empty($_REQUEST['email'])){
       $theuser = get_user_by('email', $_REQUEST['email']);
       $wp_user = $theuser->data;
       if (!empty($wp_user)) {
           $foundit = true;
           //Get cs_validate data
           $sql = "SELECT * FROM `wp_cs_validate` WHERE `user_id` = $wp_user->ID";
           $valid = $wpdb->get_row($sql);
           if (empty($valid)) {
               echo "NO Validate Row for this user";
               exit();
           }
       }    
   }
   
   if (isset($_REQUEST['apikey'])){
        $theuser = get_userdata($_REQUEST['userid']);
        $wp_user = $theuser->data; 
        $newtoken = md5('tgtinfo-'.$wp_user->user_email.strval(rand(100,10000)));
        $tokens = get_user_meta($wp_user->ID, 'tgtinfo_apikey', true);
        $plan = get_user_meta($wp_user->ID,'tgtinfo_plan',true);
        $tokens[] = $newtoken;
        update_user_meta($wp_user->ID, 'tgtinfo_apikey', $tokens);
        //Update wp_cs_validate
        if (stripos($plan,'pro') !== false) {
            $vplan = 'MyCurator Pro';
        } elseif (stripos($plan,'bus') !== false) {
            $vplan = 'MyCurator Bus';
        } else {
            $vplan = 'MyCurator Ind';
        }
        $sql = "INSERT INTO `wp_cs_validate` (`token`, `user_id`, `product`) VALUES ('$newtoken', '$wp_user->ID', '$vplan')";
        $newid = $wpdb->query($sql);
    } 
    if (isset($_REQUEST['payment'])){
      $theuser = get_userdata($_REQUEST['userid']);
      $wp_user = $theuser->data; 
       $details = array(
          'post_author' => $wp_user->ID,
          'post_title'  =>  get_user_meta($wp_user->ID,'first_name',true).' '.get_user_meta($wp_user->ID,'last_name',true),
          'post_type' => 'edd_payment',
          'post_status' => 'publish'
       );
       $post_id = wp_insert_post($details);
        
       update_post_meta($post_id,'tgtinfo_amount',intval($_REQUEST['amount']));
       update_post_meta($post_id,'tgtinfo_plan',trim($_REQUEST['fullname']));
       update_user_meta($wp_user->ID,'tgtinfo_plan',trim($_REQUEST['fullname']));  //plan into user meta too
       update_user_meta($wp_user->ID,'tgtinfo_max_topic',trim($_REQUEST['max_topic']));
       update_user_meta($wp_user->ID,'tgtinfo_max_notebk',trim($_REQUEST['max_notebk']));
       update_post_meta($post_id,'tgtinfo_paypaltrx',trim($_REQUEST['paypal']));
       if (!empty($_REQUEST['affiliate'])){
           update_post_meta($post_id,'tgtinfo_affiliate',trim($_REQUEST['affiliate']));
       }
       //Update cs_validate 
       $user_id = $wp_user->ID;
       $vname = trim($_REQUEST['validname']);
       $sql = "UPDATE `wp_cs_validate` SET `end_date` = NULL, `product` = '$vname' WHERE `user_id` = '$user_id'";
       $newid = $wpdb->query($sql);  //update the validate table
   }

       ?>
    <div class='wrap' >
        <div class="postbox-container">
           
            <h2>Target Info Add Manual Payment to an existing user - ONLY ONE TOKEN ON FILE -  New product names will be used</h2> 
            <?php if (!empty($msg)){ ?>
               <div id="message" class="updated" ><p><strong><?php echo $msg ; ?></strong></p></div>
            <?php } 
            if (!$foundit) {?>
            <form method="post" action="<?php echo esc_url($_SERVER['REQUEST_URI'] ); ?>" >
            <table class="form-table" >
                <tr>
                    <th scope="row">Email to Find</th>
                </tr>
                <tr>
                    <td><select name="email" >
                    <?php foreach ($allusers as $users){ ?>
                        <option value="<?php echo $users->user_email; ?>" ><?php echo $users->user_email; ?></option>
                    <?php } //end foreach ?>
                        </select></td>       
                </tr>
            </table>
            <div class="submit">
              <input name="search" type="submit" value="search" class="button-primary" />
            </div>
            </form>   
<?php 
            }
    if ($foundit) { ?>
      <div class="postbox-container">
       Click the New API Key button  for an added key for this user, or fill in new payment data.  If New API Key button is clicked, payment will be ignored!
          <table class="user-pmts" >
                <tr>
                    <th>Login Name</th>
                    <th>Email</th>
                </tr>
                <tr>
                    <td><?php echo $wp_user->user_login; ?></td>   
                    <td><?php echo $wp_user->user_email; ?></td>  
                </tr>
          </table><br /> <br />
           <?php  echo tgtinfo_userpmt_rpt($wp_user->ID);  ?>  <br /> <br />
          <form method="post" action="<?php echo esc_url($_SERVER['REQUEST_URI'] ); ?>" >  
              <input name="userid" type="hidden" value="<?php echo $wp_user->ID; ?>" />
              <input name="apikey" type="submit" value="apikey" class="button-secondary" />
           <table>
              <tr>
                <th>Amount</th>
                <td><input name="amount" type="text" size="10" value="" /></td>
              </tr> 
              <tr>
                <th>Product Full Name</th>
                <td><input name="fullname" type="text" size="50" value="" /></td>
              </tr> 
              <tr>
                <th>Max Topics</th>
                <td><input name="max_topic" type="text" size="10" value="" /></td>
              </tr> 
              <tr>
                <th>Max Notebooks</th>
                <td><input name="max_notebk" type="text" size="10" value="" /></td>
              </tr> 
              <tr>
                <th>Product Validate Name</th>
                <td><input name="validname" type="text" size="50" value="" /></td>
              </tr> 
              <tr>
                <th>Paypal Trx</th>
                <td><input name="paypal" type="text" size="50" value="" /></td>
              </tr> 
              <tr>
                <th>Affiliate Code</th>
                <td><input name="affiliate" type="text" size="50" value="" /></td>
              </tr> 
           </table>
              <h3>Full Names - Validate Name</h3>
              <p>Individual Plan - MyCurator Ind</p>
              <p>Pro Plan Trial - MyCurator Pro</p>
              <p>Business Plan Trial - MyCurator Bus</p>
              <p>Pro Plan - MyCurator Pro</p>
              <p>Business Plan - MyCurator Bus</p>
           <input name="payment" type="submit" value="payment" class="button-primary" />
          </form>
      </div>
<?php
    }
}

function tgtinfo_paid_plans(){
    //Report all paid plans that are active
    global $wpdb;
    //Get Pro users
   $pro_users = get_users(array('meta_key' => 'tgtinfo_plan', 'meta_value' => "Pro Plan", "orderby" => "registered", 'order' => 'ASC'));
   $bus_users = get_users(array('meta_key' => 'tgtinfo_plan', 'meta_value' => "Business Plan", "orderby" => "registered", 'order' => 'ASC'));
   $all_users = array_merge($pro_users, $bus_users);

   ?>
   <table class="widefat" >
    <thead>
        <tr>
        <th>Email</th>
        <th>Plan</th>
        <th>Amount</th>
        <th>User ID</th>
        <th>Active?</th>
        <th>From Plan</th>
        </tr>
    </thead>
    <tbody>
<?php
   $total = 0;
   $ptot = 0;
   $pcnt = 0;
   $btot = 0;
   $bcnt = 0;
   $fromind = 0;
   $fromtrial = 0;
   $age_pro = array();
   $age_bus = array();
   foreach ($all_users as $u) {
       //TI User?
       if (tgtinfo_istgtinfo($u->user_url)) continue;
       
       $plan = get_user_meta($u->ID,'tgtinfo_plan',true);
       //Get Pmt trx's
       $args = array(
            'post_type' => 'edd_payment',
            'author' => $u->ID,
            'orderby'     => 'post_date',
	    'order'       => 'DESC',
            'post_status' => 'publish'
        );
        $posts = get_posts($args);
        $last = $posts[0];
        $prev = $posts[1];
        //Expired?
       $expire = $wpdb->get_var("Select end_date from wp_cs_validate Where user_id = '$u->ID'");
       if (!empty($expire)) {
           $end_date = strtotime($expire);
           //Get days of paid use
           $paypal = get_post_meta($last->ID, 'tgtinfo_paypaltrx',true);
           if (strpos($paypal,'Cx') !== false) {
               $start_date = strtotime($prev->post_date);
               $days = floor(($end_date - $start_date)/(60*60*24));
           } else {
               $start_date = strtotime($last->post_date);
               $days = floor(($end_date - $start_date)/(60*60*24));
           }
           if (stripos($plan,'Pro')!== false){
               $age_pro['cnt'] += 1;
               $age_pro['days'] += $days;
           } else {
               $age_bus['cnt'] += 1;
               $age_bus['days'] += $days;
           }
           //Now check if past expiration and continue if so
           $today = time();  
           if ($today > $end_date) continue;
       }
       $amt = get_post_meta($last->ID, 'tgtinfo_amount',true);
       if ($amt > 50) $amt = $amt/12;  //Full year pmt
       $total += $amt;
       if (stripos($plan,'Pro')!== false){
           $pcnt += 1;
           $ptot += $amt;
       } else {
           $bcnt += 1;
           $btot += $amt;
       }
       $paypal = get_post_meta($last->ID, 'tgtinfo_paypaltrx',true);
       $prevplan = get_post_meta($prev->ID, 'tgtinfo_plan',true);
       if (stripos($prevplan,'Trial') !== false) $fromtrial += 1;
       if (stripos($prevplan,'Ind') !== false) $fromind += 1;
       $emailurl = admin_url()."admin.php?page=tgtinfo-admin/tgtinfo-reports.php_details&search=search&email=".$u->user_email;
       //Check if active in gf pmts
       $gf_active = 'No';
       $sql = "SELECT payment_date, source_url, payment_status, payment_amount, transaction_id"
            . " FROM wp_gf_entry WHERE created_by = ".$u->ID;
        $uposts = $wpdb->get_results($sql);
        if (!empty($uposts)) {
            foreach ($uposts as $post){
                if ($post->payment_status == 'Active') $gf_active = 'Yes';
            }
        }
        if ($gf_active == 'No' && !empty($paypal)) $gf_active = 'Manual';
     ?>  
        <tr>
            <td><?php echo '<a href="'.$emailurl.'">'.$u->user_email.'</a>'; ?></td>
            <td><?php echo $plan; ?></td>
            <td><?php echo $amt; ?></td>
            <td><?php echo $u->ID; ?></td>
            <td><?php echo $gf_active; ?></td>
            <td><?php echo $prevplan; ?></td>
        </tr>  
        
   <?php     
   }
   ?>
   <tr>
        <td>Total Pro <?php echo $pcnt.'/'.$ptot.' Bus '.$bcnt.'/'.$btot; ?></td>
        <td></td>
        <td><?php echo $total; ?></td>
        <td></td>
        <td>From Trial <?php echo $fromtrial.' Individual '.$fromind; ?></td>
    </tr>
    <?php
    //Now get cx plans for aging
    $pro_users = get_users(array('meta_key' => 'tgtinfo_plan', 'meta_value' => "Pro Plan Trial"));
    $bus_users = get_users(array('meta_key' => 'tgtinfo_plan', 'meta_value' => "Business Plan Trial"));
    $all_users = array_merge($pro_users, $bus_users);
    foreach ($all_users as $u) {
       //TI User?
       if (tgtinfo_istgtinfo($u->user_url)) continue;
       
       $plan = get_user_meta($u->ID,'tgtinfo_plan',true);
       //Get Pmt trx's
       $args = array(
            'post_type' => 'edd_payment',
            'author' => $u->ID,
            'orderby'     => 'post_date',
	    'order'       => 'DESC',
            'post_status' => 'publish'
        );
        $posts = get_posts($args);
        if (count($posts) < 2) continue;  //can't be a cancelled post, not enough entries
        $last = $posts[0];
        $prev = $posts[1];
        $expire = $wpdb->get_var("Select end_date from wp_cs_validate Where user_id = '$u->ID'");
       if (!empty($expire)) {
           $end_date = strtotime($expire);
           //Get days of paid use
           $paypal = get_post_meta($last->ID, 'tgtinfo_paypaltrx',true);
           if (strpos($paypal,'Cx') === false) continue;  // not a cx transaction
           $start_date = strtotime($prev->post_date);
           $days = floor(($end_date - $start_date)/(60*60*24));
           if (stripos($plan,'Pro')!== false){
               $age_pro['cnt'] += 1;
               $age_pro['days'] += $days;
           } else {
               $age_bus['cnt'] += 1;
               $age_bus['days'] += $days;
           }
       }
    }
    
    ?>
    <tr>
        <td>Age Pro <?php if ($age_pro['cnt'] > 0) { echo intval(($age_pro['days']/$age_pro['cnt'])/30); ?> Count <?php echo $age_pro['cnt']; }?></td>
        <td></td>
        <td>Age Bus <?php if ($age_bus['cnt'] > 0) { echo intval(($age_bus['days']/$age_bus['cnt'])/30); ?> Count <?php echo $age_bus['cnt']; } ?></td>
        <td></td>
        <td>Age Tot <?php echo intval((($age_bus['days']+$age_pro['days'])/($age_bus['cnt']+$age_pro['cnt']))/30); ?></td>
    </tr>
    </tbody>
   </table>
    <?php
}

function tgtinfo_accessrpt(){
    //Counts error types in error log
    set_time_limit(300);
    echo "<h2>Access Counts in Log</h2>";
    $logfile = "/var/log/tgtinfo.net/access_log";
    if (isset($_POST['logfile'])){
        $cnts = array();
        $ti = array();
        $time = array();
        $med = array();
        $ti_med = array();
        $file = sanitize_text_field($_POST['logfile']);
        $fp = fopen($file, "r");
        while (($line = fgets($fp)) !== false) {
            $cnts['total'] += 1;
            if (strpos($line,'Classify')) {
                $cnts['classify'] += 1;
                $pos = preg_match('{[\d]+\.[\d]+\.[\d]+\s([\d]+)\s([\d]+)\s([\d]+).*$}',$line,$match);
                if ($pos) {
                    if ($match[2] >= 1000) {
                        $time['cnt'] += 1;
                        $time['sec'] += absint($match[3]/1000);
                        $med[] = $match[3];
                        if (stripos($line,'target-info') !== false) {
                            $ti['cnt'] += 1;
                            $ti['sec'] += absint($match[3]/1000);
                            $ti_med[] = $match[3];
                        }
                    }
                    
                }
            }
            if (strpos($line,'Topic')) $cnts['topic'] += 1;
            if (strpos($line,'GetIt')) $cnts['getit'] += 1;
            if (strpos($line,'GetPlan')) $cnts['getplan'] += 1;
            if (strpos($line,'"-"')) $cnts['unknown'] += 1;
        }
        //calc medians
        rsort($med);
        $mid = round(count($med)/2);
        $med_val = $med[$mid-1];
        rsort($ti_med);
        $mid = round(count($ti_med)/2);
        $ti_med_val = $ti_med[$mid-1];
        echo 'Total Lines: '.strval($cnts['total'])."<br /><br />";
        echo 'Classify: '.strval($cnts['classify'])."<br /><br />";
        echo 'Topic: '.strval($cnts['topic'])." (high, counts invalids)<br /><br />";
        echo 'Get It: '.strval($cnts['getit'])."<br /><br />";
        echo 'Get Plan: '.strval($cnts['getplan'])."<br /><br />";
        echo 'Unknown: '.strval($cnts['unknown'])."<br /><br />";
        echo '<br />';
        echo 'Classify > 1k: '.strval($time['cnt']).' Time: '.strval(($time['sec']/$time['cnt'])/1000).' Median: '.strval($med_val/1000000);
        echo '<br /><br />';
        echo 'TI       > 1k: '.strval($ti['cnt']).' Time: '.strval(($ti['sec']/$ti['cnt'])/1000).' Median: '.strval($ti_med_val/1000000);
        echo '<br /><br />';
    }
    ?>
    <form method="post" action="<?php echo esc_url($_SERVER['REQUEST_URI'] ); ?>" >
    <input name="logfile" type="text" size="250" value="/var/log/tgtinfo.net/access_log" />
    <div class="submit">
              <input name="submit" type="submit" value="submit" class="button-primary" />
    </div>
    </form>   
        
     <?php
}

function tgtinfo_clear_users() {
    //Clear users who've never tried the system
    require_once( ABSPATH.'wp-admin/includes/user.php' );
    global $wpdb;
    $msg = '';
    set_time_limit(600);  //bump up execution time
    if (isset($_POST['submit'])){
        $sql = "SELECT user_id, sum(classify_calls + run_tot) as tot FROM `wp_cs_validate`  group by user_id having tot = 0";
        $users = $wpdb->get_results($sql);
        if (!empty($users)) {
            foreach ($users as $u) {
                if (get_userdata($u->user_id)) {
                    //Delete user which will also delete the validate and edd_payment records through hooks
                    wp_delete_user($u->user_id);
                } else {
                    //user already deleted, Delete validate records
                    $sql = "DELETE FROM `wp_cs_validate` WHERE `user_id` = $u->user_id";
                    $rslt = $wpdb->query($sql);
                }
            } 
            $msg = count($users)." Users Deleted!";
        } else $msg = "No Users to Clear!";
    }
    ?>
               <h3>Clear Users</h3>
               <p><?php echo $msg ?></p>
    <form method="post" action="<?php echo esc_url($_SERVER['REQUEST_URI'] ); ?>" >
    
    <div class="submit">
        Clear all Users who've never pinged our cloud server?
              <input name="submit" type="submit" value="submit" class="button-primary" />
    </div>
    </form>   
        
     <?php
}

function sendEmailsFromFile() {
    $filePath = '/home/customer/www/target-info.com/public_html/emailsout.txt';
    $subject = 'MyCurator to close next year';
    $message = 
'Dear Valued Customer,


I hope this message finds you well. I’m writing to inform you that we will be closing down the MyCurator plugin and our cloud server that supports the plugin. Despite our efforts, we’ve seen a shift in how content is being created. With the advent of advanced language models (LLMs), it has become much easier for our customers to generate targeted articles and content on their own, reducing the need for our solution to the point where we cannot generate enough revenue to cover our hosting and 3rd party services.


As a result, we have decided to discontinue our services effective March 31st, 2025. We will be canceling all paid subscription fees at the end of November but you will be able to use MyCurator until March 31st and we will provide bug fixes and customer service until 3/31/2025.  We will not be adding new features or functionality.


It has been over 12 years since we first published MyCurator and we have enjoyed the journey with our customers.  We appreciate your support and loyalty over this time.


If you have any questions or need assistance, please don’t hesitate to reach out to mtilly@target-info.com. Thank you again for being part of our journey.


Best regards,

Mark Tilly
';

    // Check if the file exists
    if (!file_exists($filePath)) {
        echo "File not found - ".$filePath;
        return;
    }

    // Open the file for reading
    $file = fopen($filePath, "r");
    $cnt = 0;

    // Check if the file opened successfully
    if ($file) {
        // Loop through each line in the file
        while (($email = fgets($file)) !== false) {
            // Trim whitespace and newline characters
            $email = trim($email);

            // Check if the email address is not empty and is valid
            if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                // Send the email using WordPress's wp_mail function
                if (wp_mail($email, $subject, $message)) {
                    echo "Email sent to: $email<br>";
                    sleep(1);
                } else {
                    echo "Failed to send email to: $email<br>";
                }
            } else {
                echo "Invalid email address: $email<br>";
            }
            $cnt += 1;
        }

        // Close the file
        fclose($file);
        echo "Count sent ".strval($cnt);
    } else {
        echo "Unable to open the file.";
    }
}


?>