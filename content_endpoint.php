<?php

/**
 * NO_DEBUG_DISPLAY - disable moodle specific debug messages and any errors in output
 */
define('NO_DEBUG_DISPLAY', true);

/**
 * NO_MOODLE_COOKIES - no cookies with web service
 */
define('NO_MOODLE_COOKIES', true);

include_once '../../config.php';
include_once './locallib.php';

ob_start();

echo "\n\nNew submit:\n";

$parameters = array(); 
if (isset($_SERVER['QUERY_STRING']))
	parse_str($_SERVER['QUERY_STRING'], $parameters);
if (isset($_SERVER['PATH_INFO'])) {
	echo $_SERVER['PATH_INFO']."\n";
	$url = $CFG->wwwroot.$_SERVER['PATH_INFO'];
}
//echo 'Parameters: '."\n";
//print_r($parameters);

if ($_SERVER['REQUEST_METHOD'] == 'GET'
	&& isset($_SERVER['HTTP_REFERER']) && $_SERVER['HTTP_REFERER'] == 'app:/Articulate.swf'
	&& isset($parameters['content_token'])) {
		$url .= '?token='.$parameters['content_token'];
		$action = 'redirect';
	} else {
		$action = 'deny';
	} 

//echo "\n";
if (isset($url))
	echo 'URL = '.$url."\n";
echo 'Action = '.$action."\n";
	    
$contents = ob_get_contents();
ob_end_clean();
if (TCAPI_LOG_CONTENT_ENDPOINT) {
	$h = fopen("content_log.txt",'a+');
	fwrite($h, $contents);
	fclose($h);
}

if ($action == 'redirect')
	header('Location: '.$url,TRUE,301);
else
	header('HTTP/1.0 401 Unauthorized',TRUE,401);			
	
exit;       
?>