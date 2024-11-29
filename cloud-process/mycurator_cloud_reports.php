<?php

/* 
 * This program runs simple log reports
 */
//Counts error types in error log
echo "<h2>Error Counts in Log</h2>";
$logfile = "php_errorlog.bak";
if (isset($_GET['logfile'])){
    $logfile = filter_var ( $_GET['logfile'], FILTER_SANITIZE_STRING);
}
echo '<p>'.$logfile.'</p>';
$cnts = array();
$ips = array();
$vers = array();
$mublogs = array();
$dbstr = array();
$old = array();
$totln = 0;
$rqcnt = $rqmax = $rqtot = 0;
$file = $logfile; 
$fp = fopen($file, "r");
while (($line = fgets($fp)) !== false) {
    $cnts['total'] += 1;
    if ($pos = strpos($line,'DB ')) { 
        $cnts['db'] += 1;
        if (strpos($line,'Cache',$pos) OR strpos($line,'Update',$pos) OR strpos($line,'Remove',$pos)) { 
            $cnts['dbi'] += 1;
        } else {
            $dbstr[] = $line;
        }
    } elseif ($pos = strpos($line,' DB')) {
        $cnts['db'] += 1;
        if (strpos($line,'Cache',$pos) OR strpos($line,'Update',$pos) OR strpos($line,'Remove',$pos)) {
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
    if ($pos=strpos($line,'Rqsts:')) {
        if ($pos > 0) {
            $rqcnt += 1;
            $ln = strlen($line) - ($pos+9) -3;
            
            $rqs = strval(substr($line,$pos+9,$ln));
            if ($rqs > $rqmax) $rqmax = $rqs;
            $rqtot += $rqs;
            //echo 'val '.$rqs.' ';
        }
    }
}
echo 'Total Lines: '.strval($cnts['total'])."<br /><br />";
if (strpos($file,'php_errorlog') !== false) {
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
    echo 'Page Loaded & Errors: '.strval($cnts['loaded']).' '.number_format(strval(($cnts['differr']+$cnts['curl']+$cnts['render'])/$cnts['loaded']),2)."<br /><br />";
    echo 'Rq Max & Avg: '.$rqmax.' '.number_format($rqtot/$rqcnt,0)."<br /><br />";    
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
//}
/*
?>
<form method="post" action="<?php echo esc_url($_SERVER['REQUEST_URI'] ); ?>" >
<input name="logfile" type="text" size="250" value="php_errorlog.bak" />
<div class="submit">
          <input name="submit" type="submit" value="submit" class="button-primary" />
</div>
</form>   
*/

?>