<?php
/*
 * The content end point expects a relative file path to be passed just as Moodle's file.php.
 * The purpose in using this method vs. a redirect is to eliminate a round-trip request.
 * This proves to be effective in delivering all media securely when requested by the Articulate Mobile Player.
 */
/**
 * AJAX_SCRIPT - exception will be converted into JSON
 */
define('AJAX_SCRIPT', true);

/**
 * NO_DEBUG_DISPLAY - disable moodle specific debug messages and any errors in output
 */
define('NO_DEBUG_DISPLAY', true);

/**
 * NO_MOODLE_COOKIES - no cookies with web service
 */
define('NO_MOODLE_COOKIES', true);

require_once '../../config.php';
require_once './locallib.php';

ob_start();

echo "\n\nNew submit:\n";

$parameters = array(); 
if (isset($_SERVER['QUERY_STRING']))
	parse_str($_SERVER['QUERY_STRING'], $parameters);
echo 'Parameters: '."\n";
print_r($parameters);

if ($_SERVER['REQUEST_METHOD'] == 'GET'
	&& isset($parameters['content_token'])) {
		$token = $parameters['content_token'];
		$action = 'return_file';
	} else {
		print_r($parameters);
		print_r($_SERVER);
		$action = 'deny';
	} 

echo 'Action = '.$action."\n";
	    
if ($action == 'return_file') {
	/*
	 * Use the same method as webservice/pluginfile.php to return file directly.
	 */
	require_once($CFG->libdir . '/filelib.php');
	require_once($CFG->dirroot . '/webservice/lib.php');
	
	//authenticate the user
	$webservicelib = new webservice();
	$authenticationinfo = $webservicelib->authenticate_user($token);
	
	//check the service allows file download
	$enabledfiledownload = (int) ($authenticationinfo['service']->downloadfiles);
	if (empty($enabledfiledownload)) {
		throw new webservice_access_exception('Web service file downloading must be enabled in external service settings');
	}
	
	//finally we can serve the file :)
	$relativepath = get_file_argument();
	echo 'Relative path = '.$relativepath."\n";
	tcapi_create_content_log();
	
	file_pluginfile($relativepath, 0);
}
else {
	tcapi_create_content_log();
	header('HTTP/1.0 401 Unauthorized',TRUE,401);			
}
exit;

function tcapi_create_content_log() {
	$contents = ob_get_contents();
	ob_end_clean();
	if (TCAPI_LOG_CONTENT_ENDPOINT) {
		$h = fopen("content_log.txt",'a+');
		fwrite($h, $contents);
		fclose($h);
	}
}