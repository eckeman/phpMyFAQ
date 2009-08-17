<?php
/**
 * This is the main functions file.
 *
 * @package   phpMyFAQ
 * @author    Thorsten Rinne <thorsten@phpmyfaq.de>
 * @author    Matthias Sommerfeld <phlymail@phlylabs.de>
 * @author    Bastian Poettner <bastian@poettner.net>
 * @author    Meikel Katzengreis <meikel@katzengreis.com>
 * @author    Robin Wood <robin@digininja.org>
 * @author    Matteo Scaramuccia <matteo@phpmyfaq.de>
 * @author    Adrianna Musiol <musiol@imageaccess.de>
 * @since     2001-02-18
 * @version   SVN: $Id$
 * @copyright 2001-2009 phpMyFAQ Team
 *
 * Portions created by Matthias Sommerfeld are Copyright (c) 2001-2004 blue
 * birdy, Berlin (http://bluebirdy.de). All Rights Reserved.
 *
 * The contents of this file are subject to the Mozilla Public License
 * Version 1.1 (the "License"); you may not use this file except in
 * compliance with the License. You may obtain a copy of the License at
 * http://www.mozilla.org/MPL/
 *
 * Software distributed under the License is distributed on an "AS IS"
 * basis, WITHOUT WARRANTY OF ANY KIND, either express or implied. See the
 * License for the specific language governing rights and limitations
 * under the License.
 */

//
// DEBUGGING FUNCTIONS
//

/**
 * Function to get a pretty formatted output of a variable
 *
 * NOTE: Just for debugging!
 *
 * @param   object
 * @return  void
 * @access  public
 * @since   2004-11-27
 * @author  Thorsten Rinne <thorsten@phpmyfaq.de>
 */
function dump($var)
{
    print '<pre>';
    var_dump($var);
    print '</pre>';
}

/**
 * debug_backtrace() wrapper function
 *
 * @param   $string
 * @return  string
 * @access  public
 * @since   2006-06-24
 * @author  Thorsten Rinne <thorsten@phpmyfaq.de>
 */
function pmf_debug($string)
{
    // sometimes Zend Optimizer causes segfaults with debug_backtrace()
    if (extension_loaded('Zend Optimizer')) {
        $ret = "<pre>" . $string . "</pre><br />\n";
    } else {
        $debug = debug_backtrace();
        $ret   = '';
        if (isset($debug[2]['class'])) {
        	$ret  = $debug[2]['file'] . ":<br />";
            $ret .= $debug[2]['class'].$debug[1]['type'];
            $ret .= $debug[2]['function'] . '() in line ' . $debug[2]['line'];
            $ret .= ": <pre>" . $string . "</pre><br />\n";
        }
    }
    return $ret;
}

/**
 * phpMyFAQ custom error handler function, also to prevent the disclosure of
 * potential sensitive data.
 *
 * @access public
 * @param  int    $level    The level of the error raised.
 * @param  string $message  The error message.
 * @param  string $filename The filename that the error was raised in.
 * @param  int    $line     The line number the error was raised at.
 * @param  mixed  $context  It optionally contains an array of every variable
 *                          that existed in the scope the error was triggered in.
 * @since  2009-02-01
 * @author Matteo Scaramuccia <matteo@phpmyfaq.de>
 */
function pmf_error_handler($level, $message, $filename, $line, $context)
{
    // Sanity check
    // Note: when DEBUG mode is true we want to track any error!
    if (
        // 1. the @ operator sets the PHP's error_reporting() value to 0
           (!DEBUG && (0 == error_reporting()))
        // 2. Honor the value of PHP's error_reporting() function
        || (!DEBUG && (0 == ($level & error_reporting())))
        ) {
        // Do nothing
        return true;
    }

    // Cleanup potential sensitive data
    $filename = (DEBUG ? $filename : basename($filename));

    // Give an alias name to any PHP error level number
    // PHP 5.3.0+
    if (!defined('E_DEPRECATED')) {
        define('E_DEPRECATED', 8192);
    }
    // PHP 5.3.0+
    if (!defined('E_USER_DEPRECATED')) {
        define('E_USER_DEPRECATED', 16384);        
    }    
    $errorTypes = array(
        E_ERROR             => 'error',
        E_WARNING           => 'warning',
        E_PARSE             => 'parse error',
        E_NOTICE            => 'notice',
        E_CORE_ERROR        => 'code error',
        E_CORE_WARNING      => 'core warning',
        E_COMPILE_ERROR     => 'compile error',
        E_COMPILE_WARNING   => 'compile warning',
        E_USER_ERROR        => 'user error',
        E_USER_WARNING      => 'user warning',
        E_USER_NOTICE       => 'user notice',
        E_STRICT            => 'strict warning',
        E_RECOVERABLE_ERROR => 'recoverable error',
        E_DEPRECATED        => 'deprecated warning',
        E_USER_DEPRECATED   => 'user deprecated warning',
    );
    $errorType = 'unknown error';
    if (isset($errorTypes[$level])) {
        $errorType = $errorTypes[$level];
    }

    // Custom error message
    $errorMessage = <<<EOD
<br />
<b>phpMyFAQ $errorType</b> [$level]: $message in <b>$filename</b> on line <b>$line</b><br />
EOD;

    switch ($level) {
        // Blocking errors
        case E_ERROR:
        case E_PARSE:
        case E_CORE_ERROR:
        case E_COMPILE_ERROR:
        case E_USER_ERROR:
            // Clear any output that has already been generated
            // TBD: it generally seems not useful unless when errors appear on
            //      coded HTTP streaming e.g. when creating PDF to be sent to users
            if (ob_get_length()) {
                //ob_clean();
            }
            // Output the error message
            echo $errorMessage;
            // Prevent processing any more PHP scripts
            exit();
            break;
        // Not blocking errors
        default:
            // Output the error message
            echo $errorMessage;
            break;
    }
    
    return true;
}

//
// GENERAL FUNCTIONS
//

/**
 * Returns all sorting possibilities for FAQ records
 *
 * @param   string  $current
 * @return  string
 * @access  public
 * @since   2007-03-10
 * @author  Thorsten Rinne <thorsten@phpmyfaq.de>
 */
function sortingOptions($current)
{
    global $PMF_LANG;

    $options = array('id', 'thema', 'visits', 'datum', 'author');
    $output = '';

    foreach ($options as $value) {
        printf('<option value="%s"%s>%s</option>',
            $value,
            ($value == $current) ? ' selected="selected"' : '',
            $PMF_LANG['ad_conf_order_'.$value]);
    }

    return $output;
}

/**
 * Converts the phpMyFAQ date format to a format similar to ISO 8601 standard
 *
 * @param   string  $date
 * @return  date
 * @access  public
 * @since   2001-04-30
 * @author  Thorsten Rinne <thorsten@phpmyfaq.de>
 */
function makeDate($date)
{
    $current    = strtotime(substr($date, 0, 4) . '-' .
                  substr($date, 4, 2) . '-' .
                  substr($date, 6, 2) . ' ' .
                  substr($date, 8, 2) . ':' .
                  substr($date, 10, 2));
    $timestamp  = $current;

    return date('Y-m-d H:i', $timestamp);
}

/**
 * Converts the phpMyFAQ/Unix date format to the format given by the PHP date
 * format
 *
 * @param   object  date
 * @param   string  the PHP format of date
 * @param   boolean true if the passed date is in phpMyFAQ format, false if in
 *                  Unix timestamp format
 * @return  string  date in the requested format
 * @since   2006-06-26
 * @author  Matteo Scaramuccia <matteo@phpmyfaq.de>
 */
function makeDateByFormat($date, $format, $phpmyfaq = true)
{
    if ($phpmyfaq) {
        $current = strtotime(substr($date, 0, 4) . '-' .
                   substr($date, 4, 2) . '-' .
                   substr($date, 6, 2) . ' ' .
                   substr($date, 8, 2) . ':' .
                   substr($date, 10, 2));
    } else {
        $current = $date;
    }
    $timestamp = $current;

    return date($format, $timestamp);
}

/**
 * Converts the Unix date format to the format needed to view a comment entry
 *
 * @param   object  date
 * @return  string  formatted date for the comment
 * @since   2006-08-21
 * @author  Matteo Scaramuccia <matteo@phpmyfaq.de>
 */
function makeCommentDate($date)
{
    return date('Y-m-d H:i', $date);
}

/**
 * Converts the phpMyFAQ/Unix date format to the RFC 822 format
 *
 * @param   string  date
 * @param   boolean true if the passed date is in phpMyFAQ format, false if in
 *          Unix timestamp format
 * @return  string  RFC 822 date
 * @since   2005-10-03
 * @author  Matteo Scaramuccia <matteo@phpmyfaq.de>
 */
function makeRFC822Date($date, $phpmyfaq = true)
{
    $rfc822TZ = date('O');
    if ('+0000' == $rfc822TZ) {
        $rfc822TZ = 'GMT';
    }

    return makeDateByFormat($date, 'D, d M Y H:i:s', $phpmyfaq).' '.$rfc822TZ;
}

/**
 * Converts the phpMyFAQ/Unix date format to the ISO 8601 format
 *
 * See the spec here: http://www.w3.org/TR/NOTE-datetime
 *
 * @param    string  date
 * @param    boolean true if the passed date is in phpMyFAQ format, false if in Unix timestamp format
 * @return   string  ISO 8601 date
 * @since    2005-10-03
 * @author   Matteo Scaramuccia <matteo@phpmyfaq.de>
 */
function makeISO8601Date($date, $phpmyfaq = true)
{
    $iso8601TZD = date('P');
    if ('+00:00' == $iso8601TZD) {
        $iso8601TZD = 'Z';
    }

    return makeDateByFormat($date, 'Y-m-d\TH:i:s', $phpmyfaq).$iso8601TZD;
}

/**
 * If the email spam protection has been activated from the general PMF configuration
 * this function converts an email address e.g. from "user@example.org" to "user_AT_example_DOT_org"
 * Otherwise it will return the plain email address.
 *
 * @param  string $email E-mail address
 * @return string
 */
function safeEmail($email)
{
    $faqconfig = PMF_Configuration::getInstance();
    if ($faqconfig->get('spam.enableSafeEmail')) {
        return str_replace(array('@', '.'), array('_AT_', '_DOT_'), $email);
    } else {
        return $email;
    }
}

/**
 * check4AddrMatch()
 *
 * Checks for an address match (IP or Network)
 *
 * @param   string  IP Address
 * @param   string  Network Address (e.g.: a.b.c.d/255.255.255.0 or a.b.c.d/24) or IP Address
 * @return  boolean
 * @since   2006-01-23
 * @author  Matteo Scaramuccia <matteo@phpmyfaq.de>
 * @author  Kenneth Shaw <ken@expitrans.com>
 */
function check4AddrMatch($ip, $network)
{
    // See also ip2long PHP online manual: Kenneth Shaw
    // coded a network matching function called net_match.
    // We use here his way of doing bit-by-bit network comparison
    $matched = false;

    // Start applying the discovering of the network mask
    $ip_arr = explode('/', $network);

    $network_long = ip2long($ip_arr[0]);
    $ip_long      = ip2long($ip);

    if (!isset($ip_arr[1])) {
        // $network seems to be a simple ip address, instead of a network address
        $matched = ($network_long == $ip_long);
    } else {
        // $network seems to be a real network address
        $x = ip2long($ip_arr[1]);
        // Evaluate the netmask: <Network Mask> or <CIDR>
        $mask = ( long2ip($x) == $ip_arr[1] ? $x : 0xffffffff << (32 - $ip_arr[1]));
        $matched = ( ($ip_long & $mask) == ($network_long & $mask) );
    }

    return $matched;
}

/**
 * Performs a check if an IPv4 is banned
 * 
 * NOTE: This function does not support IPv6
 *
 * @param   string  IP
 * @return  boolean
 * @since   2003-06-06
 * @access  public
 * @author  Thorsten Rinne <thorsten@phpmyfaq.de>
 */
function IPCheck($ip)
{
    $faqconfig = PMF_Configuration::getInstance();
    $bannedIPs = explode(' ', $faqconfig->get('main.bannedIPs'));
    
    foreach ($bannedIPs as $oneIPorNetwork) {
        if (check4AddrMatch($ip, $oneIPorNetwork)) {
            return false;
        }
    }
    return true;
}

/**
 * This function returns the banned words dictionary as an array.
 *
 * @return  array
 * @access  public
 * @author  Matteo Scaramuccia <matteo@phpmyfaq.de>
 */
function getBannedWords()
{
    $bannedTrimmedWords = array();
    $bannedWordsFile = dirname(__FILE__).'/blockedwords.txt';
    $bannedWords     = array();

    // Read the dictionary
    if (file_exists($bannedWordsFile) && is_readable($bannedWordsFile)) {
        $bannedWords = @file($bannedWordsFile);
    }
    // Trim it
    foreach ($bannedWords as $word) {
        $bannedTrimmedWords[] = trim($word);
    }

    return $bannedTrimmedWords;
}

/**
 * This function checks the content against a dab word list
 * if the banned word spam protection has been activated from the general PMF configuration.
 *
 * @param   string  $content
 * @return  bool
 * @access  public
 * @author  Katherine A. Bouton
 * @author  Thorsten Rinne <thorsten@phpmyfaq.de>
 * @author  Matteo Scaramuccia <matteo@phpmyfaq.de>
 * @author  Peter Beauvain <pbeauvain@web.de>
 */
function checkBannedWord($content)
{
    $faqconfig = PMF_Configuration::getInstance();

    // Sanity checks
    $content = trim($content);
    if (('' == $content) && (!$faqconfig->get('spam.checkBannedWords'))) {
        return true;
    }

    $bannedWords = getBannedWords();
    // We just search a match of, at least, one banned word into $content
    $content = PMF_String::strtolower($content);
    if (is_array($bannedWords)) {
        foreach ($bannedWords as $bannedWord) {
            if (PMF_String::strpos($content, PMF_String::strtolower($bannedWord)) !== false) {
                return false;
            }
        }
    }

    return true;
}

/**
 * Get out the HTML code for the fieldset that insert the captcha code in a (public) form
 *
 * @param   string  Text of the HTML Legend element
 * @param   string  HTML code for the Captcha image
 * @param   string  Length of the Captcha code
 * @return  string
 * @since   2006-04-25
 * @author  Matteo Scaramuccia <matteo@phpmyfaq.de>
 */
function printCaptchaFieldset($legend, $img, $length, $error = '')
{
    $faqconfig = PMF_Configuration::getInstance();
    $html      = '';

    if ($faqconfig->get('spam.enableCatpchaCode')) {
        $html = sprintf('<fieldset><legend>%s</legend>', $legend);
        $html .= '<div style="text-align:left;">';
        if ($error != '') {
            $html .= '<div class="error">' . $error . '</div>';
        }
        $html .= $img;
        $html .= '&nbsp; &nbsp;<input class="inputfield" type="text" name="captcha" id="captcha" value="" size="7" style="vertical-align: top; height: 35px; text-valign: middle; font-size: 20pt;" />';
        $html .= '</div></fieldset>';
    }

    return $html;
}

/**
 * This function returns the passed content with HTML hilighted banned words.
 *
 * @param   string  $content
 * @return  string
 * @access  public
 * @author  Matteo Scaramuccia <matteo@phpmyfaq.de>
 */
function getHighlightedBannedWords($content)
{
    $bannedHTMLHiliWords = array();
    $bannedWords = getBannedWords();

    // Build the RegExp array
    foreach ($bannedWords as $word) {
        $bannedHTMLHiliWords[] = "/(".quotemeta($word).")/ism";
    }
    // Use the CSS "highlight" class to highlight the banned words
    if (count($bannedHTMLHiliWords)>0) {
        return PMF_String::preg_replace($bannedHTMLHiliWords, "<span class=\"highlight\">\\1</span>", $content);
    }
    else {
        return $content;
    }
}

/**
 * Adds PHP syntax highlighting to your pre tags
 *
 * @param   string  $content
 * @return  string
 * @access  public
 * @author  Thorsten Rinne <thorsten@phpmyfaq.de>
 * @since   2004-12-25
 */
function hilight($content)
{
    $string = $content[2];

    $string = str_replace("&lt;?php", " ", $string);
    $string = str_replace("?&gt;", " ", $string);

    if (!ereg('^<\\?', $string) || !ereg('^&lt;\\?', $string)) {
        $string = "<?php\n".$string."\n?>";
    }

    $string = implode("\n", explode("<br />", $string));
    $string = highlight_string($string, true);
    $string = eregi_replace('^.*<pre>',  '', $string);
    $string = eregi_replace('</pre>.*$', '', $string);
    $string = str_replace("\n", "", $string);
    $string = str_replace("&nbsp;", " ", $string);

    // Making the PHP generated stuff XHTML compatible
    $string = PMF_String::preg_replace('/<FONT COLOR="/i', '<span style="color:', $string);
    $string = PMF_String::preg_replace('/<\/FONT>/i', '</span>', $string);

    return $string;
}

/**
 * An OS independent function like usleep
 *
 * @param   integer
 * @return  void
 * @since   2004-05-30
 * @author  Thorsten Rinne <thorsten@phpmyfaq.de>
 */
function wait($usecs)
{
    $temp = gettimeofday();
    $start = (int)$temp["usec"];
    while(1) {
        $temp = gettimeofday();
        $stop = (int)$temp["usec"];
        if ($stop - $start >= $usecs) {
            break;
        }
    }
}

/**
 * Returns the number of anonymous users and registered ones.
 * These are the numbers of unique users who have perfomed
 * some activities within the last five minutes
 *
 * @param  integer $activityTimeWindow Optionally set the time window size in sec. 
 *                                     Default: 300sec, 5 minutes
 * @return array
 */
function getUsersOnline($activityTimeWindow = 300)
{
    $users     = array(0 ,0);
    $faqconfig = PMF_Configuration::getInstance();
    $db        = PMF_Db::getInstance();

    if ($faqconfig->get('main.enableUserTracking')) {
        $timeNow = ($_SERVER['REQUEST_TIME'] - $activityTimeWindow);
        // Count all sids within the time window
        // TODO: add a new field in faqsessions in order to find out only sids of anonymous users
        $result = $db->query("
                    SELECT
                        count(sid) AS anonymous_users
                    FROM
                        ".SQLPREFIX."faqsessions
                    WHERE
                            user_id = -1
                        AND time > ".$timeNow);
        if (isset($result)) {
            $row      = $db->fetch_object($result);
            $users[0] = $row->anonymous_users;
        }
        // Count all faquser records within the time window
        $result = $db->query("
                    SELECT
                        count(session_id) AS registered_users
                    FROM
                        ".SQLPREFIX."faquser
                    WHERE
                        session_timestamp > ".$timeNow);
        if (isset($result)) {
            $row      = $db->fetch_object($result);
            $users[1] = $row->registered_users;
        }
    }

    return $users;
}


/******************************************************************************
 * Funktionen fuer Artikelseiten
 ******************************************************************************/

/**
 * Macht an den String nen / dran, falls keiner da ist
 * @@ Bastian, 2002-01-06
 */
function EndSlash($string)
{
    if (PMF_String::substr($string, PMF_String::strlen($string)-1, 1) != "/" ) {
        $string .= "/";
    }
    return $string;
}

/**
 * Decode MIME header elements in e-mails | @@ Matthias Sommerfeld
 * (c) 2001-2004 blue birdy, Berlin (http://bluebirdy.de)
 * used with permission
 * Last Update: @@ Thorsten, 2004-07-17
 */
if (!function_exists('quoted_printable_encode')) {
	function quoted_printable_encode($return = '')
	{
	    // Ersetzen der lt. RFC 1521 noetigen Zeichen
	    $return = PMF_String::preg_replace('/([^\t\x20\x2E\041-\074\076-\176])/ie', "sprintf('=%2X',ord('\\1'))", $return);
	    $return = PMF_String::preg_replace('!=\ ([A-F0-9])!', '=0\\1', $return);
	    // Einfuegen von QP-Breaks (=\r\n)
	    if (PMF_String::strlen($return) > 75) {
		$length = PMF_String::strlen($return); $offset = 0;
		do {
		    $step = 76;
		    $add_mode = (($offset+$step) < $length) ? 1 : 0;
		    $auszug = PMF_String::substr($return, $offset, $step);
		    if (PMF_String::preg_match('!\=$!', $auszug))   $step = 75;
		    if (PMF_String::preg_match('!\=.$!', $auszug))  $step = 74;
		    if (PMF_String::preg_match('!\=..$!', $auszug)) $step = 73;
		    $auszug = PMF_String::substr($return, $offset, $step);
		    $offset += $step;
		    $schachtel .= $auszug;
		    if (1 == $add_mode) $schachtel.= '='."\r\n";
		    } while ($offset < $length);
		$return = $schachtel;
		}
	    $return = PMF_String::preg_replace('!\.$!', '. ', $return);
	    return PMF_String::preg_replace('!(\r\n|\r|\n)$!', '', $return)."\r\n";
	}
}


/**
 * Get search data weither as array or resource
 *
 * @param string $searchterm
 * @param boolean $asResource
 * @param string $cat
 * @param boolean $allLanguages
 * 
 * @return array|resource
 */
function getSearchData($searchterm, $asResource = false, $cat = '%', $allLanguages = true)
{
    global $db, $LANGCODE;

    $result = null;  
    $num         = 0;
    $faqconfig   = PMF_Configuration::getInstance();

    $cond = array(SQLPREFIX."faqdata.active" => "'yes'");

    if ($cat != '%') {
        $cond = array_merge(array(SQLPREFIX."faqcategoryrelations.category_id" => $cat), $cond);
    }

    if ((!$allLanguages) && (!is_numeric($searchterm))) {
        $cond = array_merge(array(SQLPREFIX."faqdata.lang" => "'".$LANGCODE."'"), $cond);
    }

    if (is_numeric($searchterm)) {
        // search for the solution_id
        $result = $db->search(SQLPREFIX.'faqdata',
                        array(SQLPREFIX.'faqdata.id AS id',
                              SQLPREFIX.'faqdata.lang AS lang',
                              SQLPREFIX.'faqdata.solution_id AS solution_id',
                              SQLPREFIX.'faqcategoryrelations.category_id AS category_id',
                              SQLPREFIX.'faqdata.thema AS thema',
                              SQLPREFIX.'faqdata.content AS content'),
                        SQLPREFIX.'faqcategoryrelations',
                        array(SQLPREFIX.'faqdata.id = '.SQLPREFIX.'faqcategoryrelations.record_id',
                              SQLPREFIX.'faqdata.lang = '.SQLPREFIX.'faqcategoryrelations.record_lang'),
                        array(SQLPREFIX.'faqdata.solution_id'),
                        $searchterm,
                        $cond);
    } else {
        $result = $db->search(SQLPREFIX."faqdata",
                        array(SQLPREFIX."faqdata.id AS id",
                              SQLPREFIX."faqdata.lang AS lang",
                              SQLPREFIX."faqcategoryrelations.category_id AS category_id",
                              SQLPREFIX."faqdata.thema AS thema",
                              SQLPREFIX."faqdata.content AS content"),
                        SQLPREFIX."faqcategoryrelations",
                        array(SQLPREFIX."faqdata.id = ".SQLPREFIX."faqcategoryrelations.record_id",
                              SQLPREFIX."faqdata.lang = ".SQLPREFIX."faqcategoryrelations.record_lang"),
                        array(SQLPREFIX."faqdata.thema",
                              SQLPREFIX."faqdata.content",
                              SQLPREFIX."faqdata.keywords"),
                        $searchterm,
                        $cond);
    }

    if ($result) {
        $num = $db->num_rows($result);
    }

    // Show the record with the solution ID directly
    // Sanity checks: if a valid Solution ID has been provided the result set
    //                will measure 1: this is true ONLY if the faq is not
    //                classified among more than 1 category
    if (is_numeric($searchterm) && ($searchterm >= PMF_SOLUTION_ID_START_VALUE) && ($num > 0)) {
        // Hack: before a redirection we must force the PHP session update for preventing data loss
        session_write_close();
        if ($faqconfig->get('main.enableRewriteRules')) {
            header('Location: '.PMF_Link::getSystemUri('/index.php').'/solution_id_'.$searchterm.'.html');
        } else {
            header('Location: '.PMF_Link::getSystemUri('/index.php').'/index.php?solution_id='.$searchterm);
        }
        exit();
    }

    if (0 == $num) {
        $keys = PMF_String::preg_split("/\s+/", $searchterm);
        $numKeys = count($keys);
        $where = '';
        for ($i = 0; $i < $numKeys; $i++) {
            if (PMF_String::strlen($where) != 0 ) {
                $where = $where." OR ";
            }
            $where = $where.'('.SQLPREFIX."faqdata.thema LIKE '%".$keys[$i]."%' OR ".SQLPREFIX."faqdata.content LIKE '%".$keys[$i]."%' OR ".SQLPREFIX."faqdata.keywords LIKE '%".$keys[$i]."%')";
            if (is_numeric($cat)) {
                $where .= ' AND '.SQLPREFIX.'faqcategoryrelations.category_id = '.$cat;
            }
            if (!$allLanguages) {
                $where .= ' AND '.SQLPREFIX."faqdata.lang = '".$LANGCODE."'";
            }
        }

        $where = " WHERE (".$where.") AND ".SQLPREFIX."faqdata.active = 'yes'";
        $query = 'SELECT '.SQLPREFIX.'faqdata.id AS id, '.SQLPREFIX.'faqdata.lang AS lang, '.SQLPREFIX.'faqcategoryrelations.category_id AS category_id, '.SQLPREFIX.'faqdata.thema AS thema, '.SQLPREFIX.'faqdata.content AS content FROM '.SQLPREFIX.'faqdata LEFT JOIN '.SQLPREFIX.'faqcategoryrelations ON '.SQLPREFIX.'faqdata.id = '.SQLPREFIX.'faqcategoryrelations.record_id AND '.SQLPREFIX.'faqdata.lang = '.SQLPREFIX.'faqcategoryrelations.record_lang '.$where;
        $result = $db->query($query);
    }

    return $asResource ? $result : $db->fetchAll($result);
}

/**
 * The main search function for the full text search
 *
 * TODO: add filter for (X)HTML tag names and attributes!
 *
 * @param   string  Text/Number (solution id)
 * @param   string  '%' to avoid any category filtering
 * @param   boolean true to search over all languages
 * @param   boolean true to disable the results paging
 * @param   boolean true to use it for Instant Response
 * @return  string
 * @access  public
 * @author  Thorsten Rinne <thorsten@phpmyfaq.de>
 * @author  Matteo Scaramuccia <matteo@phpmyfaq.de>
 * @author  Adrianna Musiol <musiol@imageaccess.de>
 * @since   2002-09-16
 */
function searchEngine($searchterm, $cat = '%', $allLanguages = true, $hasMore = false, $instantRespnse = false)
{
    global $sids, $category, $PMF_LANG, $LANGCODE, $faq, $current_user, $current_groups;

    $_searchterm = PMF_htmlentities(stripslashes($searchterm), ENT_QUOTES, $PMF_LANG['metaCharset']);
    $seite       = 1;
    $output      = '';
    $num         = 0;
    $searchItems = array();
    $langs       = (true == $allLanguages) ? '&amp;langs=all' : '';
    $seite       = PMF_Filter::filterInput(INPUT_GET, 'seite', FILTER_VALIDATE_INT, 1);
    $db          = PMF_Db::getInstance();
    $faqconfig   = PMF_Configuration::getInstance();

    $result = getSearchData($searchterm, true, $cat, $allLanguages);
    $num    = $db->num_rows($result);

    if (0 == $num) {
        $output = $PMF_LANG['err_noArticles'];
    }

    $pages = ceil($num / $faqconfig->get('main.numberOfRecordsPerPage'));
    $last  = $seite * $faqconfig->get('main.numberOfRecordsPerPage');
    $first = $last - $faqconfig->get('main.numberOfRecordsPerPage');
    if ($last > $num) {
        $last = $num;
    }

    if ($num > 0) {
        if ($num == "1") {
            $output .= '<p>'.$num.$PMF_LANG["msgSearchAmount"]."</p>\n";
        } else {
            $output .= '<p>'.$num.$PMF_LANG["msgSearchAmounts"];
            if ($hasMore && ($pages > 1)) {
                $output .= sprintf(
                    $PMF_LANG['msgInstantResponseMaxRecords'],
                    $faqconfig->get('main.numberOfRecordsPerPage'));
            }
            $output .= "</p>\n";
        }
        if (!$hasMore && ($pages > 1)) {
            $output .= "<p><strong>".$PMF_LANG["msgPage"].$seite." ".$PMF_LANG["msgVoteFrom"]." ".$pages." ".$PMF_LANG["msgPages"]."</strong></p>";
        }
        $output .= "<ul class=\"phpmyfaq_ul\">\n";

        $counter = 0;
        $displayedCounter = 0;
        while (($row = $db->fetch_object($result)) && $displayedCounter < $faqconfig->get('main.numberOfRecordsPerPage')) {
            $counter ++;
            if ($counter <= $first) {
                continue;
            }
            $displayedCounter++;

            $b_permission = false;
			//Groups Permission Check
            if ($faqconfig->get('main.permLevel') == 'medium') {
                $perm_group = $faq->getPermission('group', $row->id);
				foreach ($current_groups as $index => $value){
					if (in_array($value, $perm_group)) {
						$b_permission = true;
					}
				}
			}
			if ($faqconfig->get('main.permLevel') == 'basic' || $b_permission) {
				$perm_user = $faq->getPermission('user', $row->id);
				foreach ($perm_user as $index => $value) {
					if ($value == -1) {
						$b_permission = true;
						break;
					} elseif (((int)$value == $current_user)) {
						$b_permission = true;
						break;
					} else {
						$b_permission = false;
					}
				}
			}

			if ($b_permission) {
                $rubriktext = $category->getPath($row->category_id);
                $thema = PMF_htmlentities(chopString($row->thema, 15),ENT_QUOTES, $PMF_LANG['metaCharset']);
                $content = chopString(strip_tags($row->content), 25);
                $searchterm = str_replace(array('^', '.', '?', '*', '+', '{', '}', '(', ')', '[', ']', '"'), '', $searchterm);
                $searchterm = preg_quote($searchterm, '/');
                $searchItems = explode(' ', $searchterm);

                if (PMF_String::strlen($searchItems[0]) > 1) {
                    foreach ($searchItems as $item) {
                        if (PMF_String::strlen($item) > 2) {
                            $thema = PMF_String::preg_replace_callback('/'
                                .'('.$item.'="[^"]*")|'
                                .'((href|src|title|alt|class|style|id|name|dir|onclick|ondblclick|onmousedown|onmouseup|onmouseover|onmousemove|onmouseout|onkeypress|onkeydown|onkeyup)="[^"]*'.$item.'[^"]*")|'
                                .'('.$item.')'
                                .'/mis',
                                "highlight_no_links",
                                $thema );
                            $content = PMF_String::preg_replace_callback('/'
                                .'('.$item.'="[^"]*")|'
                                .'((href|src|title|alt|class|style|id|name|dir|onclick|ondblclick|onmousedown|onmouseup|onmouseover|onmousemove|onmouseout|onkeypress|onkeydown|onkeyup)="[^"]*'.$item.'[^"]*")|'
                                .'('.$item.')'
                                .'/mis',
                                    "highlight_no_links",
                                $content);
                        }
                    }
                }

                // Print the link to the faq record
                $url = sprintf(
                    '?%saction=artikel&amp;cat=%d&amp;id=%d&amp;artlang=%s&amp;highlight=%s',
                    $sids,
                    $row->category_id,
                    $row->id,
                    $row->lang,
                    urlencode($_searchterm));

                if ($instantRespnse) {
                    $currentUrl = PMF_Link::getSystemRelativeUri('ajaxresponse.php').'index.php';
                } else {
                    $currentUrl = PMF_Link::getSystemRelativeUri();
                }
                $oLink = new PMF_Link($currentUrl.$url);
                $oLink->itemTitle = $row->thema;
                $oLink->text = $thema;
                $oLink->tooltip = $row->thema;
                $output .=
                    '<li><strong>'.$rubriktext.'</strong>: '.$oLink->toHtmlAnchor().'<br />'
                    .'<div class="searchpreview"><strong>'.$PMF_LANG['msgSearchContent'].'</strong> '.$content.'...</div>'
                    .'<br /></li>'."\n";
			}
        }
        $output .= "</ul>\n";
    } else {
        $output = $PMF_LANG["err_noArticles"];
    }

    if (!$hasMore && ($num > $faqconfig->get('main.numberOfRecordsPerPage'))) {
        $output .= "<p align=\"center\"><strong>";
        $vor = $seite - 1;
        $next = $seite + 1;
        if ($vor != 0) {
            if ($faqconfig->get('main.enableRewriteRules')) {
                $output .= sprintf("[ <a href=\"search.html?search=%s&amp;seite=%d%s&amp;searchcategory=%d\">%s</a> ]",
                                urlencode($_searchterm),
                                $vor,
                                $langs,
                                $cat,
                                $PMF_LANG['msgPrevious']);
            } else {
                $output .= sprintf("[ <a href=\"index.php?%saction=search&amp;search=%s&amp;seite=%d%s&amp;searchcategory=%d\">%s</a> ]",
                                $sids,
                                urlencode($_searchterm),
                                $vor,
                                $langs,
                                $cat,
                                $PMF_LANG['msgPrevious']);
            }
        }
        $output .= " ";
        if ($next <= $pages) {
            $url = $sids.'&amp;action=search&amp;search='.urlencode($_searchterm).'&amp;seite='.$next.$langs."&amp;searchcategory=".$cat;
            $oLink = new PMF_Link(PMF_Link::getSystemRelativeUri().'?'.$url);
            $oLink->itemTitle = '';
            $oLink->text = $PMF_LANG["msgNext"];
            $oLink->tooltip = $PMF_LANG["msgNext"];
            $output .= '[ '.$oLink->toHtmlAnchor().' ]';
        }
        $output .= "</strong></p>";
    }

    return $output;
}

/**
 * Callback function for filtering HTML from URLs and images
 *
 * @param   array
 * @access  public
 * @return  string
 * @author  Thorsten Rinne <thorsten@phpmyfaq.de>
 * @author  Matthias Sommerfeld <phlymail@phlylabs.de>
 * @author  Johannes Schlueter <johannes@php.net>
 * @since   2003-07-14
 */
function highlight_no_links(Array $matches)
{
    $itemAsAttrName  = $matches[1];
    $itemInAttrValue = $matches[2]; // $matches[3] is the attribute name
    $prefix          = isset($matches[3]) ? $matches[3] : '';
    $item            = isset($matches[4]) ? $matches[4] : '';
    $postfix         = isset($matches[5]) ? $matches[5] : '';
    
    if (!empty($item)) {
        return '<span class="highlight">'.$prefix.$item.$postfix.'</span>';
    }

    // Fallback: the original matched string
    return $matches[0];
}

/**
 * This functions chops a string | @@ Thorsten, 2003-12-16
 * Last Update: @@ Thorsten, 2003-12-16
 */
function chopString($string, $words)
{
    $str = "";
    $pieces = explode(" ", $string);
    $num = count($pieces);
    if ($words > $num) {
        $words = $num;
    }
    for ($i = 0; $i < $words; $i++) {
        $str .= $pieces[$i]." ";
    }
    return $str;
}

//
// Various functions
//

/**
 * This is a wrapper for htmlspecialchars() with a check on valid charsets.
 *
 * @param  string $string      String
 * @param  string $quote_style Quote style
 * @param  string $charset     Charset
 * @return string
 */
function PMF_htmlentities($string, $quote_style = ENT_QUOTES, $charset = 'UTF-8')
{
    return htmlspecialchars($string, $quote_style, $charset);
}

/**
 * Build url for attachment download
 *
 * @param int $recordId
 * @param int $filename
 * @param bool $forHtml if the url will be used in html
 * @return string
 */
function buildAttachmentUrl($recordId, $filename, $forHtml = true)
{
    $amp = $forHtml ? '&amp;' : '&';
    
    return sprintf('index.php?action=attachment%sid=%s%sfile=%s', $amp, $recordId, $amp, $filename);
}

/**
 * Check if an attachment dir is valid
 *
 * @param int $id
 * @return boolean
 */
function isAttachmentDirOk($id)
{
    $recordAttachmentsDir = PMF_ATTACHMENTS_DIR . DIRECTORY_SEPARATOR . $id; 
    
    return false !== PMF_ATTACHMENTS_DIR && file_exists(PMF_ATTACHMENTS_DIR) && is_dir(PMF_ATTACHMENTS_DIR) &&
           file_exists($recordAttachmentsDir) && is_dir($recordAttachmentsDir);
}

/******************************************************************************
 * Funktionen fuer die Benutzerauthentifizierung und Rechtevergabe
 ******************************************************************************/

/**
 * Adds a menu entry according to user permissions.
 * ',' stands for 'or', '*' stands for 'and'
 *
 * @param  string  $restrictions Restrictions
 * @param  string  $action       Action parameter
 * @param  string  $caption      Caption
 * @param  string  $active       Active
 * @access public
 * @author Thorsten Rinne <thorsten@phpmyfaq.de>
 * 
 * @return string
 */
function addMenuEntry($restrictions = '', $action = '', $caption = '', $active = '')
{
    global $PMF_LANG;

    $class = '';
    if ($active == $action) {
        $class = ' class="current"';
    }

    if ($action != '') {
        $action = "action=".$action;
    }

    if (isset($PMF_LANG[$caption])) {
        $_caption = $PMF_LANG[$caption];
    } else {
        $_caption = 'No string for '.$caption;
    }

    $output = sprintf('        <li><a%s href="?%s">%s</a></li>%s',
        $class,
        $action,
        $_caption,
        "\n");
           
    return evalPermStr($restrictions) ? $output : '';
}

/**
 * Parse and check a permission string
 * 
 * Permissions are glued with each other as follows
 * - '+' stands for 'or'
 * - '*' stands for 'and'
 * 
 * No braces will be parsed, only simple expressions
 * @example right1*right2+right3+right4*right5
 * 
 * @author Anatoliy Belsky <anatoliy.belsky@mayflower.de>
 * @param string $restrictions
 * 
 * @return boolean
 */
function evalPermStr($restrictions)
{
    global $permission;
    
    if(false !== strpos($restrictions, '+')) {
    	$retval = false;
        foreach (explode('+', $restrictions) as $_restriction) {
			$retval = $retval || evalPermStr($_restriction);
			if($retval) {
				break;
			}
        }        
    } else if(false !== strpos($restrictions, '*')) {
    	$retval = true;
        foreach (explode('*', $restrictions) as $_restriction) {
            if(!isset($permission[$_restriction]) || !$permission[$_restriction]) {
                $retval = false;
                break;   
            }
        }  
    } else {
    	$retval = strlen($restrictions) > 0 && isset($permission[$restrictions]) && $permission[$restrictions];
    }
    
    return $retval;
}


/**
 * Administrator logging
 *
 * @param   string
 * @return  void
 * @access  public
 * @since   2001-02-18
 * @author  Bastian Poettner <bastian@poettner.net>
 * @author  Thorsten Rinne <thorsten@phpmyfaq.de>
 */
function adminlog($text)
{
    global $auth, $user;

    $faqconfig = PMF_Configuration::getInstance();
    $db        = PMF_Db::getInstance();
    
    if ($faqconfig->get('main.enableAdminLog') && $auth && isset($user)) {
        $query = sprintf(
                'INSERT INTO
                    %sfaqadminlog
                    (id, time, usr, text, ip)
                VALUES (%d, %d, %d, %s, %s)',
                    SQLPREFIX,
                    $db->nextID(SQLPREFIX.'faqadminlog', 'id'),
                    $_SERVER['REQUEST_TIME'],
                    $user->userdata->get('user_id'),
                    "'".nl2br($text)."'",
                    "'".$_SERVER['REMOTE_ADDR']."'"
                    );

        $db->query($query);
    }
}

/**
 * Checkt, ob eine SQL-Tabelle leer ist | @@ Thorsten 2002-01-10
 * Last Update: @@ Thorsten, 2003-03-24
 */
function emptyTable($table)
{
    global $db;
    if ($db->num_rows($db->query("SELECT * FROM ".$table)) < 1) {
        return true;
    } else {
        return false;
    }
}

/******************************************************************************
 * Funktionen fuer den Adminbereich
 ******************************************************************************/

/**
 * Funktion zum generieren vom "Umblaettern" | @@ Bastian, 2002-01-03
 * Last Update: @@ Thorsten, 2004-05-07
 */
function PageSpan($code, $start, $end, $akt)
{
    global $PMF_LANG;
    if ($akt > $start) {
        $out = str_replace("<NUM>", $akt-1, $code).$PMF_LANG["msgPreviusPage"]."</a> | ";
    } else {
        $out = "";
    }
    for ($h = $start; $h<=$end; $h++) {
        if ($h > $start) {
            $out .= ", ";
        }
        if ($h != $akt) {
            $out .= str_replace("<NUM>", $h, $code).$h."</a>";
        } else {
            $out .= $h;
        }
    }
    if ($akt < $end) {
        $out .= " | ".str_replace("<NUM>", $akt+1, $code).$PMF_LANG["msgNextPage"]."</a>";
    }
    $out = $PMF_LANG["msgPageDoublePoint"].$out;
    return $out;
}

/**
 * Bastelt aus den Dateinamen des Tracking einen Timestamp | @@ Bastian, 2002-01-05
 * Last Update: @@ Thorsten, 2002-09-19
 * Last Update: @@ Matteo, 2006-06-13
 */
function FileToDate($file, $endOfDay = false)
{
    if (PMF_String::strlen($file) >= 16) {
        $tag = PMF_String::substr($file, 8, 2);
        $mon = PMF_String::substr($file, 10, 2);
        $yea = PMF_String::substr($file, 12, 4);
        if (!$endOfDay) {
            $tim = mktime(0, 0, 0, $mon, $tag, $yea);
        } else {
            $tim = mktime(23, 59, 59, $mon, $tag, $yea);
        }
        return $tim;
    } else {
        return -1;
    }
}

/**
 * Bastelt nen Timestamp ausm Datum | @@ Bastian, 2001-04-09
 * Last Update: @@ Thorsten - 2002-09-27
 */
function mkts($datum,$zeit)
{
    if (strlen($datum) > 0) {
        $tag = substr($datum,0,strpos($datum,"."));
        $datum = substr($datum,(strpos($datum,".")+1),strlen($datum));
        $monat = substr($datum,0,strpos($datum,"."));
        $datum = substr($datum,(strpos($datum,".")+1),strlen($datum));
        $jahr = $datum;
    } else {
        $tag = date("d");
        $monat = date("m");
        $jahr = date("Y");
    } if (strlen($zeit) > 0) {
        $stunde = substr($zeit,0,strpos($zeit,":"));
        $zeit = substr($zeit,(strpos($zeit,":")+1),strlen($zeit));
        $minute = substr($zeit,0,strpos($zeit,":"));
        $zeit = substr($zeit,(strpos($zeit,":")+1),strlen($zeit));
        $sekunde = $zeit;
    } else {
        $stunde = date("H");
        $minute = date("i");
        $sekunde = date("s");
    }
    return mktime($stunde, $minute, $sekunde, $monat, $tag, $jahr);
}

//
// Functions for backup and SQL security
//

/**
 * This function builds the the queries for the backup
 *
 * @param    string      query
 * @param    string      table name
 * @return   array
 * @access   public
 * @author   Meikel Katzengreis <meikel@katzengreis.com>
 * @author   Thorsten Rinne <thorsten@phpmyfaq.de>
 * @since    2003-03-24
 */
function build_insert($query, $table)
{
    global $db;
    if (!$result = $db->query($query)) {
        return;
    }
    $ret = array();

    $ret[] = "\n-- Table: ".$table;

    while ($row = $db->fetch_assoc($result)) {
        $p1 = array();
        $p2 = array();
        foreach ($row as $key => $val) {
            $p1[] = $key;
            if ('rights' != $key && is_numeric($val)) {
                $p2[] = $val;
            } else {
                if (is_null($val)) {
                    $p2[] = 'NULL';
                } else {
                    $p2[] = sprintf("'%s'", $db->escape_string($val));
                }
            }
        }
        $ret[] = "INSERT INTO ".$table." (".implode(",", $p1).") VALUES (".implode(",", $p2).");";
    }

    return $ret;
}

/**
 * Align the prefix of the table name used in the PMF backup file,
 * from the (old) value of the system upon which the backup was performed
 * to the (new) prefix of the system upon which the backup will be restored.
 * This alignment will be perfomed ONLY upon those given SQL queries starting
 * with the given pattern.
 *
 * @param   $query              string
 * @param   $start_pattern      string
 * @param   $oldvalue           string
 * @param   $newvalue           string
 * @return  string
 * @access  public
 * @author  Matteo Scaramuccia <matteo@phpmyfaq.de>
 */
function alignTablePrefixByPattern($query, $start_pattern, $oldvalue, $newvalue)
{
    $ret = $query;

    preg_match_all("/^".$start_pattern."\s+(\w+)(\s+|$)/i", $query, $matches);
    if (isset($matches[1][0])) {
        $oldtablefullname = $matches[1][0];
        $newtablefullname = $newvalue.substr($oldtablefullname, strlen($oldvalue));
        $ret = str_replace($oldtablefullname, $newtablefullname, $query);
    }

    return $ret;
}

/**
 * Align the prefix of the table name used in the PMF backup file,
 * from the (old) value of the system upon which the backup was performed
 * to the (new) prefix of the system upon which the backup will be restored
 * This alignment will be performed upon all of the SQL query "patterns"
 * provided within the PMF backup file.
 *
 * @param   $query          string
 * @param   $oldvalue       string
 * @param   $newvalue       string
 * @return  string
 * @access  public
 * @author  Matteo Scaramuccia <matteo@phpmyfaq.de>
 */
function alignTablePrefix($query, $oldvalue, $newvalue)
{
    // Align DELETE FROM <prefix.tablename>
    $query = alignTablePrefixByPattern($query, "DELETE FROM", $oldvalue, $newvalue);
    // Align INSERT INTO <prefix.tablename>
    $query = alignTablePrefixByPattern($query, "INSERT INTO", $oldvalue, $newvalue);

    return $query;
}
