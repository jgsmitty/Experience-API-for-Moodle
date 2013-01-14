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
        $methodvariables = array();
        // Get GET and POST parameters.
        $methodvariables = array_merge($_GET, $_POST);
        // now how about PUT/POST bodies? These override any existing parameters.
        $body = file_get_contents("php://input");
        echo $body."\n";
        if ($body_params = json_decode($body)) {
            foreach($body_params as $param_name => $param_value) {
                $methodvariables[$param_name] = $param_value;
            }
        } else {
         	$body_params = array();
         	parse_str($body,$body_params);
            foreach($body_params as $param_name => $param_value) {
                $methodvariables[$param_name] = $param_value;
            }
        }
        echo $_SERVER['REQUEST_METHOD']."\n";
        print_r($methodvariables);
$contents = ob_get_contents();
if (TCAPI_LOG_ENDPOINT) {
	$h = fopen("log.txt",'a+');
	fwrite($h, $contents);
	fclose($h);	
}

ob_end_clean();

/**
 * TCAPI REST web service entry point.
 * For ./statements and ./activity/state endpoint access, the authentication is done via tokens.
 * For direct access, ie.: record retrieval and operations, the authentication is done via 
 *
 * @package    webservice_rest
 * @copyright  2009 Jerome Mouneyrac
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


if (!webservice_protocol_is_enabled('rest')) {
    debugging('The server died because the web services or the REST protocol are not enable',
        DEBUG_DEVELOPER);
    die;
}

$server = new webservice_tcapi_server(WEBSERVICE_AUTHMETHOD_PERMANENT_TOKEN);
$server->run();

die;
?>