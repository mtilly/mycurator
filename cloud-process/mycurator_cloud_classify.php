<?php
/* tgtai_classigy
 * This file contains the code to classify documents.  The classifier is an object.  It also contains the code to get words 
 * from a page as a function.
*/

function mct_ai_get_words($page, $dup=true){
    //$page is the page in diffbot_page format
    //this function returns an array of words stripped from the article, or '' if no words found
    //if dup is false, it won't remove duplicates
    
    global $stopwords, $threeletter;

    //Is this a formated page from DiffBot?
    if (strpos($page, 'savelink-article')=== false){
        return '';  
    }
    $title = '';
    $author = '';
    $article = '';
    //$page has the content, with html, using the format of diffbot_page function, separate sections
    $cnt = preg_match('{<title>([^<]*)</title>}i',$page,$matches);
    if ($cnt) $title = $matches[1];
    $cnt = preg_match('{<div>Author:([^<]*)</div>}',$page, $matches);
    if ($cnt) $author = $matches[1];
    $cnt = preg_match('{<span class="mct-ai-article-content">(.*)}si',$page,$matches);  //don't stop at end of line
    if ($cnt) $article = $matches[1];
    //Get rid of tags, non-alpha
    $title = wp_strip_all_tags($title, true);
    $title = preg_replace('{[^A-Za-z0-9\s\s+]}',' ',$title); //remove non-alpha

    $author = wp_strip_all_tags($author, true);
    $author = preg_replace('{[^A-Za-z0-9\s\s+]}',' ',$author); //remove non-alpha

    $article = wp_strip_all_tags($article, true);
    $article = preg_replace('{&[a-z]*;}',"'",$article);  //remove any encoding
    $article = preg_replace('{[^A-Za-z0-9\s\s+]}',' ',$article); //remove non-alpha
    //split sections  into words and merge
    $awords = preg_split('{\s+}',$article);
    if (empty($awords)){
        return '';  //no words in the body to work with
    }
    $auwords = preg_split('{\s+}',$author);
    $twords = preg_split('{\s+}',$title);
    $awords = array_merge($twords, $auwords, $awords );
    //remove stop words
    $words = array();
    foreach ($awords as $a){
        $a = trim($a);
        if (strlen($a) < 4){  //not long enough?
            if (strlen($a) < 2){ 
                continue;
            } else {
                if (!in_array(strtolower($a),$threeletter)){
                    $cnt = preg_match('{^[^a-z0-9]*$}',$a);  //Allow 2 and 3 word acronyms if all caps
                    if (!$cnt){
                        continue;
                    }
                }
            }
        }
        $a = strtolower($a); //now make lowercase
        // //$a = PorterStemmer::Stem($a);
        if (in_array($a,$stopwords)){  //a stopword?
            continue;
        }
        if ($dup){
            if (!in_array($a,$words)){  //no dups
                $words[] = $a;          //got a good word
            }
        } else {
            $words[] = $a;
        }
    }
    
    return $words;
}

function mct_ai_utf_words($page, $dup=true){
    //$page is the page in diffbot_page format
    //this function returns an array of words stripped from the article, or '' if no words found
    //if dup is false, it won't remove duplicates
    //Only strips punctuation and is UTF-8 aware
    
    global $stopwords, $threeletter;

    //Is this a formated page from DiffBot?
    if (strpos($page, 'savelink-article')=== false){
        return '';  
    }
    
    $title = '';
    $author = '';
    $article = '';
    //$page has the content, with html, using the format of diffbot_page function, separate sections
    $cnt = preg_match('{<title>([^<]*)</title>}i',$page,$matches);
    if ($cnt) $title = $matches[1];
    $cnt = preg_match('{<div>Author:([^<]*)</div>}',$page, $matches);
    if ($cnt) $author = $matches[1];
    $cnt = preg_match('{<span class="mct-ai-article-content">(.*)}si',$page,$matches);  //don't stop at end of line
    if ($cnt) $article = $matches[1];
    //Get rid of tags, punctuatio
    if ($title != ''){
        $title = wp_strip_all_tags($title, true);  //remove tags but leave spaces
        $title = html_entity_decode($title,ENT_NOQUOTES,"UTF-8"); //get rid of text entities
        $title = preg_replace('|[^\p{L}\p{N}]|u'," ",$title); //remove punctuation UTF-8
    }
    if ($author != ''){
        $author = wp_strip_all_tags($author, true);  //remove tags but leave spaces
        $author = html_entity_decode($author,ENT_NOQUOTES,"UTF-8");
        $author = preg_replace('|[^\p{L}\p{N}]|u'," ",$author); //remove punctuation, UTF-8 
    }
    if ($article != ''){
        $article = wp_strip_all_tags($article, true);  //remove tags but leave spaces
        $article = html_entity_decode($article,ENT_NOQUOTES,"UTF-8");
        $article = preg_replace('|[^\p{L}\p{N}]|u'," ",$article); //remove punctuation, UTF-8 aware
    }
    //split sections  into words and merge
    $allstr = $title." ".$author." ".$article;
    $awords = preg_split('{\s+}',$allstr);
    if (empty($awords)){
        return '';  //no words in the body to work with
    }
   
    //remove stop words
    $words = array();
    foreach ($awords as $a){
        $a = trim($a);
        $len = mb_strlen($a);
        if ($len < 4){  //not long enough?
            if ($len < 2){ 
                continue;
            } else {
                if (!mct_ai_utf_inarray($a,$threeletter)){  
                     continue;
                 }
            }
        }
        $a = mct_ai_strtolower_utf8($a); //now make lowercase
        // //$a = PorterStemmer::Stem($a);
        if (mct_ai_utf_inarray($a,$stopwords)){  //a stopword?
            continue;
        }
        if ($dup){
            if (!mct_ai_utf_inarray($a,$words)){  //no dups
                $words[] = $a;          //got a good word
            }
        } else {
            $words[] = $a;
        }
    }
    
    return $words;
}


function mct_ai_utf_inarray($key,$arrayval){
    //Find words in array using preg and u modifier for UTF-8 and i modifier for case
    if (!count($arrayval)) return false;
    foreach ($arrayval as $arr) {
        if (preg_match('{^'.$key.'$}ui',$arr)) return true;
    }
    return false;
}

function mct_ai_no_dups($words){
    //remove duplicates from an array of words
    
    if (empty($words)) return;
    $nodup = array();
    foreach($words as $w){
        if (!mct_ai_utf_inarray($w,$nodup)){  //no dups
            $nodup[] = $w;          //got a good word
        }
    }
    return $nodup;
}

function mct_ai_strtolower_utf8($string){ 
  $convert_to = array( 
    "a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k", "l", "m", "n", "o", "p", "q", "r", "s", "t", "u", 
    "v", "w", "x", "y", "z", "à", "á", "â", "ã", "ä", "å", "æ", "ç", "è", "é", "ê", "ë", "ì", "í", "î", "ï", 
    "ð", "ñ", "ò", "ó", "ô", "õ", "ö", "ø", "ù", "ú", "û", "ü", "ý", "а", "б", "в", "г", "д", "е", "ё", "ж", 
    "з", "и", "й", "к", "л", "м", "н", "о", "п", "р", "с", "т", "у", "ф", "х", "ц", "ч", "ш", "щ", "ъ", "ы", 
    "ь", "э", "ю", "я" 
  ); 
  $convert_from = array( 
    "A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R", "S", "T", "U", 
    "V", "W", "X", "Y", "Z", "À", "Á", "Â", "Ã", "Ä", "Å", "Æ", "Ç", "È", "É", "Ê", "Ë", "Ì", "Í", "Î", "Ï", 
    "Ð", "Ñ", "Ò", "Ó", "Ô", "Õ", "Ö", "Ø", "Ù", "Ú", "Û", "Ü", "Ý", "А", "Б", "В", "Г", "Д", "Е", "Ё", "Ж", 
    "З", "И", "Й", "К", "Л", "М", "Н", "О", "П", "Р", "С", "Т", "У", "Ф", "Х", "Ц", "Ч", "Ш", "Щ", "Ъ", "Ъ", 
    "Ь", "Э", "Ю", "Я" 
  ); 

  return str_replace($convert_from, $convert_to, $string); 
} 

define ('MCT_AI_GOODPROB',.95);
define ('MCT_AI_BADPROB',.95);
define ('MCT_AI_MAXDICT', 1000);
define ('MCT_AI_MINDICT', 50);

class Relevance {
    //Set up basic variables
    
    //$fc is an array of features, with each feature being an array of the 
    //two categories 'bad' and 'good' - so fc['word']['good'] will be a value (as will 'bad')
    private $fc = array();  
    private $cc = array();  //Count of documents in category, two keys 'good' and 'bad'
    private $laplace = 1;   //laplace smoothing of 0 value feature/category combination
    private $categories = array('good', 'bad');  //Valid categories 
    private $cur_shrink = 0;  //holds the current count of where we shrunk the db
    
    public function get_db($topic){
        //global $wpdb, $ai_topic_tbl;
        //This function gets the fc and cc databases out of the topic record
        //This must be called before classifying any documents
        //It may not be called if you are going to train from scratch
        //Topic name is used to find the topic record storing the database
        //MOD for Cloud process: don't use db call
        /*$sql = "SELECT topic_aidbfc, topic_aidbcat
            FROM $ai_topic_tbl
            WHERE topic_name = '$topic'";
        $dbs = $wpdb->get_row($sql, ARRAY_A);
        if (empty($dbs)){
            return "ERROR No Topic Found";
        } */
        $this->fc = @unserialize($topic['topic_aidbfc']);
        $this->cc = @unserialize($topic['topic_aidbcat']);
        if (empty($this->fc)){
            return "ERROR No relevance database";
        }
        return '';
    }
    /*  MOD - Not used in cloud process
    public function set_db($topic){
        global $wpdb, $ai_topic_tbl;
        //This function stores the fc and cc databases into the topic of record
        //This should not be called unless you have finished a training session
        //Topic name is used to find the topic record storing the database
        $datavals = array(
            'topic_aidbfc' => maybe_serialize($this->fc),
            'topic_aidbcat' => maybe_serialize($this->cc)
        );
        $where = array('topic_name' => $topic);
        $wpdb->update($ai_topic_tbl, $datavals, $where);
    } */  
    private function shrinkdb($min){
        //Shrink the database to reduce dimensionality
        //remove any features that have <= $min counts across all categories
        $remove_f = array();
        foreach ($this->fc as $key => $f){
            $cnt = 0;
            foreach ($this->categories as $cc) {
                $cnt += $f[$cc];
            }
            if ($cnt <= $min){
                $remove_f[] = $key;
            }
        }
        if (empty($remove_f)) return;
        foreach ($remove_f as $f) {
            unset($this->fc[$f]);
        }
        
    }
    private function isf($f){
        //Is this a feature in our database?
        if (array_key_exists($f,$this->fc)) return true;
        return false;
    }
    private function fcount($f, $cat){
        //The number of times the feature $f appears in the category $cat
        if (array_key_exists($f,$this->fc)){
            return $this->fc[$f][$cat];
        }
        return 0;
    }
    private function catcount($cat){
        //The number of documents in a category
        if (array_key_exists($cat, $this->cc)){
            return $this->cc[$cat];
        }
        return 0;
    }
    private function totalcount(){
        //The total number of documents
        return array_sum($this->cc);
    }
    private function dictsize(){
        //Total number of features in db
        return count($this->fc);
    }
    private function fcatsize($cat){
        //Total number of feature words found in a category
        $tot = 0;
        foreach ($this->fc as $f){
            $tot += $f[$cat];
        }
        return $tot;
    }
    /* MOD not used in cloud process
    public function train($item, $cat){
        //An item is an array of words, and each word f will be counted in fc[f][cat]
        //then the cc table for this cat will be incremented for 1 document
        if (!in_array($cat, $this->categories)) return;  //not a valid category
        foreach ($item as $f){
            if (empty($this->fc) || !array_key_exists($f,$this->fc)){
                //Set up all categories with a 0 count to start
                foreach ($this->categories as $cc) {
                    $this->fc[$f][$cc] = 0;
                }
            }
            $this->fc[$f][$cat] += 1;  //increment counts for every feature
        }
        //Update the document count for this category
        if (empty($this->cc) || !array_key_exists($cat, $this->cc)){
            $this->cc[$cat] = 0;
        }
        $this->cc[$cat] += 1;
    } */
    
    private function docprob($item, $cat){
        //Get the probability of each feature in this document $item for the category $cat
        //Return the probability value
        $dsize = $this->dictsize() * $this->laplace; //multiply total features times laplace
        $fsize = $this->fcatsize($cat);   //denominator for features in category
        $p = 1;
        foreach ($item as $f){
            if (!$this->isf($f)) continue;  //skip features that aren't in our database
            $p *= ($this->fcount($f,$cat) + $this->laplace)/($fsize+$dsize);
        }
        return $p;
    }
    
    private function prob($item){
        //Calculate probability for all categories for this document $item
        //return an array of probabilities for each category
        $normalterm = 0;  //addition of both category probabilities for denom
        $docprb = array();
        $catprb = array();
        $pr = array();
        //get the normal term and the doc probs for each category
        foreach ($this->categories as $cc){
            //calc Pr(doc|cat) for each category
            $docprb[$cc] = $this->docprob($item, $cc);
            // calc Pr(cat) assuming two categories
            $catprb[$cc] = ($this->catcount($cc)+$this->laplace)/($this->totalcount()+2*$this->laplace);
            $normalterm += $docprb[$cc]*$catprb[$cc];
        }
        //Now calc the final probabilities of this item in each category
        foreach ($this->categories as $cc){
            if ($normalterm == 0) {
                $pr[$cc] = 0;  //Probs can get too small
            } else {
                $pr[$cc] = ($docprb[$cc]*$catprb[$cc])/$normalterm;
            }
        }
        return $pr;
    }
    
    public function preparedb(){
        //Before classifying it shrinks the database to lower the chance of a calc getting
        // too small.  It shrinks the db until we get to under our target size
        $cur_size = 0;  // starting minimum word counts, go up from here
        $origdb = $this->fc;
        while ($this->dictsize() > MCT_AI_MAXDICT){
            $cur_size += 1;
            $this->shrinkdb($cur_size);  
        }
        $this->cur_shrink = $cur_size;
        return $cur_size;
    }
    public function classify($item){
        //Performs the probability calcs and then classifies the item
        //returns an array with 'class' => classification and
        //probablity values of each category by name
        
        $ret_vals = array();
        $old_db = $this->fc;  //save the old db in case we need to shrink it some

        if (empty($this->fc)) {
            $ret_vals['classed'] = 'not sure';
            $ret_vals['good'] = -1;  //Set -1 to signify no classification done
            $ret_vals['bad'] = -1;
            $ret_vals['dbsize'] = 0;
            return $ret_vals;  //No database
        }
        $pr = $this->prob($item);
        
        //if too long, try shrinking the db to the minimum
        $try = $this->cur_shrink;
        while ($pr['bad'] == 0 && $pr['good'] == 0  && $this->dictsize() > MCT_AI_MINDICT){
            $try += 1;
            $this->shrinkdb($try);
            $pr = $this->prob($item);
        }
        $ret_vals['dbsize'] = $this->dictsize();  //save this before resetting
        if ($try != $this->cur_shrink){
            $this->fc = $old_db;  //reset the database if we shrank it
        }
        
        if ($pr['good'] >=  MCT_AI_GOODPROB) {
            $ret_vals['classed'] = 'good';
            $ret_vals['good'] = $pr['good'];
            $ret_vals['bad'] = $pr['bad'];
            return $ret_vals;
        } 
        if ($pr['bad'] >=  MCT_AI_BADPROB){
            $ret_vals['classed'] = 'bad';
            $ret_vals['good'] = $pr['good'];
            $ret_vals['bad'] = $pr['bad'];
            return $ret_vals;
        } 
        if ($pr['bad'] == 0 && $pr['good'] == 0){
            $ret_vals['classed'] = 'too long';
            $ret_vals['good'] = $pr['good'];
            $ret_vals['bad'] = $pr['bad'];
        } else {
            $ret_vals['classed'] = 'not sure';
            $ret_vals['good'] = $pr['good'];
            $ret_vals['bad'] = $pr['bad'];
        }

        return $ret_vals;
    }
    
    public function report($topic){
        $report = array();
        if ($this->get_db($topic) == ''){
            foreach ($this->categories as $cat){
                $report[$cat] = $this->catcount($cat);
            }
            $report['dict'] = $this->dictsize();
            $report['shrinkdb'] = $this->preparedb();
            $diff = 0;
            foreach ($this->fc as $f){
                $diff += abs($f['good'] - $f['bad']);
            }
            $report['coef'] = $diff/$this->dictsize();
       }
       return $report;
    }
}  //end class 
/* MOD not used in cloud process
function mct_ai_trainpost($postid, $tname, $cat){
    global $wpdb, $ai_topic_tbl, $ai_sl_pages_tbl;
    // This function trains a topic relevance engine with a saved page from a post
    // postid is the post, tname is the topic name and cat is good/bad category
    
    // Get object and load the db's
    $rel = new Relevance();
    $rel->get_db($tname);
    
    //Get the page id from the post and get the page 
    $sl_ids = mct_ai_getsavedpageid($postid);
    if (count($sl_ids) != 1) return;  //Can only have one link if we train
    $page = mct_ai_getsavedpage($sl_ids[0]);
    $words = mct_ai_get_words($page);
    if (empty($words)) return;
    
    //Train it and save db
    $rel->train($words, $cat);
    $rel->set_db($tname);
    
    //Save trained topic in post meta
    $vals = get_post_meta($postid,'mct_ai_trained',true);
    if (!empty($vals)) {
        $vals[] = $cat.':'.$tname;
    } else {
        $vals = array($cat.':'.$tname);
    }
    update_post_meta($postid, 'mct_ai_trained', $vals);
    
    unset($rel); //Done with the object
} */
?>