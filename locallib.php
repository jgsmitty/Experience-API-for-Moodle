<?php
define('TCAPI_LOG_ENDPOINT', 0);
define('TCAPI_LOG_CONTENT_ENDPOINT', 0);

define('TCAPI_SERVICE','local_tcapi');
define('TCAPI_MUST_EXIST',1);
define('TCAPI_MUST_NOT_EXIST',2);
define('TCAPI_OPTIONAL',8);
define('TCAPI_REQUIRED',9);
define('TCAPI_RETURN_OK','200');
define('TCAPI_RETURN_NOCONTENT','204');
define('TCAPI_RETURN_CONFLICT','409');
define('TCAPI_RETURN_PRECONDITIONFAILED','412');

if (isset($CFG->tcapi_endpoint))
	define('TCAPI_ENDPOINT',$CFG->tcapi_endpoint);
else
	define('TCAPI_ENDPOINT',$CFG->wwwroot.'/local/tcapi/endpoint.php/');

if (isset($CFG->tcapi_content_endpoint))
	define('TCAPI_CONTENT_ENDPOINT',$CFG->tcapi_content_endpoint);
else
	define('TCAPI_CONTENT_ENDPOINT',$CFG->wwwroot.'/local/tcapi/content_endpoint.php');
	
/**
 * Set SYSTEM role permission assignments for use of TCAPI.
 * This includes moodle/webservice:createtoken and local/tcapi:use.
 * Affected user role is Authenticated user / user
 */
function local_tcapi_set_role_permission_overrides() {
	global $CFG,$DB;
	$role = $DB->get_record('role', array('archetype'=>'user'), 'id', MUST_EXIST);
	if (isset($role->id)) {
		require_once $CFG->dirroot.'/lib/accesslib.php';
		role_change_permission($role->id, context_system::instance(), 'moodle/webservice:createtoken', CAP_ALLOW);
		role_change_permission($role->id, context_system::instance(), 'webservice/rest:use', CAP_ALLOW);
		role_change_permission($role->id, context_system::instance(), 'local/tcapi:use', CAP_ALLOW);
	}
}

/**
 * Gets a stored user token for use in making requests to external TCAPI webservice.
 * If a token does not exist, one is created.
 */
function local_tcapi_get_user_token() {
	global $USER,$DB;
    // if service doesn't exist, dml will throw exception
    $service_record = $DB->get_record('external_services', array('shortname'=>TCAPI_SERVICE, 'enabled'=>1), '*', MUST_EXIST);
	if ($token = local_tcapi_user_token_exists($service_record->id)) {
		return $token;
	} else if (has_capability('moodle/webservice:createtoken', context_system::instance())) {
	    // make sure the token doesn't exist (borrowed from /lib/externallib.php)
	    $numtries = 0;
	    do {
	        $numtries ++;
	        $generatedtoken = md5(uniqid(rand(),1));
	        if ($numtries > 5)
	            throw new moodle_exception('tokengenerationfailed');
	    } while ($DB->record_exists('external_tokens', array('token'=>$generatedtoken)) && $numtries <= 5);
		// create a new token
        $token = new stdClass;
        $token->token = $generatedtoken;
        $token->userid = $USER->id;
        $token->tokentype = EXTERNAL_TOKEN_PERMANENT;
        $token->contextid = context_system::instance()->id;
        $token->creatorid = $USER->id;
        $token->timecreated = time();
        $token->lastaccess = time();
        $token->externalserviceid = $service_record->id;
        $tokenid = $DB->insert_record('external_tokens', $token);
        add_to_log(SITEID, 'webservice', 'automatically create user token', '' , 'User ID: ' . $USER->id);
        $token->id = $tokenid;
    } else {
        throw new moodle_exception('cannotcreatetoken', 'webservice', '', TCAPI_SERVICE);
    }
    return $token;
}

/**
 * Simple check to see if a token for TCAPI web service already exists for a user
 * and returns it.
 * @param Integer $serviceid
 */
function local_tcapi_user_token_exists($serviceid=null) {
	global $USER,$DB;
	if ($serviceid == null) {
	    // if service doesn't exist, dml will throw exception
	    $service_record = $DB->get_record('external_services', array('shortname'=>TCAPI_SERVICE, 'enabled'=>1), '*', MUST_EXIST);
	    $serviceid = $service_record->id;
	}
	//Check if a token has already been created for this user and this service
    //Note: this could be an admin created or an user created token.
    //      It does not really matter we take the first one that is valid.
	$tokenssql = "SELECT t.id, t.sid, t.token, t.validuntil, t.iprestriction
              FROM {external_tokens} t
             WHERE t.userid = ? AND t.externalserviceid = ? AND t.tokentype = ?
          ORDER BY t.timecreated ASC";
    $tokens = $DB->get_records_sql($tokenssql, array($USER->id, $serviceid, EXTERNAL_TOKEN_PERMANENT));
    // if some valid tokens exist then use the most recent
    if (count($tokens) > 0) {
        $token = array_pop($tokens);
	    // log token access
	    $DB->set_field('external_tokens', 'lastaccess', time(), array('id'=>$token->id));
	    add_to_log(SITEID, 'webservice', 'user request webservice token', '' , 'User ID: ' . $USER->id);
	    return $token;
    }
    return false;
}

/**
 * TCAPI web service implementation classes and methods.
 *
 * @package    local_tcapi
 * @copyright  2012 Jamie Smith
 */
require_once("$CFG->dirroot/webservice/lib.php");


/**
 * TCAPI service server implementation.
 * Borrowed from Moodle webservice_rest_server class and modified to suit TCAPI requirements.
 *
 * @package    local_tcapi
 * @copyright  2012 Jamie Smith
 */
class webservice_tcapi_server extends webservice_base_server {

    /** @var string return header response code */
    protected $response_code;
    /** @var boolean encode response as json string */
    protected $response_encode;
    protected $request_method;
    
    /**
     * Contructor
     *
     * @param string $authmethod authentication method of the web service (WEBSERVICE_AUTHMETHOD_PERMANENT_TOKEN, ...)
     * @param string $response_code Header response code to return with response
     * @param boolean $response_encode Encode response as json string
     */
    public function __construct($authmethod) {
	    parent::__construct($authmethod);
	    $this->wsname = 'rest';
        $this->response_code = '200';
        $this->response_encode = true;
    }

    /**
     * This method parses the $_POST and $_GET superglobals then
     * any body params and looks for
     * the following information:
     *  1/ Authorization token
     *  2/ functionname via get_functionname method
     */
    protected function parse_request() {

        // Retrieve and clean the POST/GET parameters from the parameters specific to the server.
        parent::set_web_service_call_settings();

        $methodvariables = array();
        // Get GET and POST parameters.
        $methodvariables = array_merge($_GET, $_POST, $this->get_headers());
	    $this->requestmethod = (isset($methodvariables['method'])) ? $methodvariables['method'] : $_SERVER['REQUEST_METHOD'];
	    if ($this->requestmethod == 'OPTIONS')
	    	$this->send_options();
	    
	    // now how about PUT/POST bodies? These override any existing parameters.
        $body = @file_get_contents('php://input');
        if (TCAPI_LOG_ENDPOINT) {
        	global $DEBUGBODY;
        	if (isset($DEBUGBODY))
        		$body = $DEBUGBODY;
        }
        //echo $body;
        if (!isset($methodvariables['content']))
        	$methodvariables['content'] = $body;      
        if ($body_params = json_decode($body)) {
            foreach($body_params as $param_name => $param_value) {
                $methodvariables[$param_name] = stripslashes($param_value);
            }
        } else {
         	$body_params = array();
         	parse_str($body,$body_params);
            foreach($body_params as $param_name => $param_value) {
                $methodvariables[$param_name] = stripslashes($param_value);
            }
        }

        // Determine Authentication method to use (WEBSERVICE_AUTHMETHOD_PERMANENT_TOKEN is default)
        // Simple token (as used in Bookmarklet method) and Basic authentication is supported at this time.
        if (isset($methodvariables['Authorization'])) {
        	// TODO: Add support for OAuth authentication. That should really be a web service addition so we can call it here.
        	if (substr($methodvariables['Authorization'], 0, 5) == 'Basic') {
        		$user_auth = explode(":",base64_decode(substr($methodvariables['Authorization'], 6)));
        		if (is_array($user_auth) && count($user_auth) == 2) {
        			$this->username = $user_auth[0];
        			$this->password = $user_auth[1];
        			$this->authmethod = WEBSERVICE_AUTHMETHOD_USERNAME;
        			//echo 'Uses Basic Auth with Username: '.$this->username.' and Password: '.$this->password."\n";
        		}
        	} else {
		        $this->token = isset($methodvariables['Authorization']) ? $methodvariables['Authorization'] : null;        		
        		//echo 'Uses Token Auth with Token: '.$this->token."\n";
        	}
        }
        //print_r($methodvariables);
        unset($methodvariables['Authorization']);
        $this->parameters = $methodvariables;
     	$this->functionname = $this->get_functionname();
     	//echo $this->functionname;
    }
    
	/**
	 * Try to sort out headers for people who aren't running apache
	 */
	public static function get_headers() {
	  if (function_exists('apache_request_headers')) {
	    // we need this to get the actual Authorization: header
	    // because apache tends to tell us it doesn't exist
	    return apache_request_headers();
	  }
	  // otherwise we don't have apache and are just going to have to hope
	  // that $_SERVER actually contains what we need
	  $out = array();
	  foreach ($_SERVER as $key => $value) {
	    if (substr($key, 0, 5) == "HTTP_") {
	      // this is chaos, basically it is just there to capitalize the first
	      // letter of every word that is not an initial HTTP and strip HTTP
	      // code from przemek
	      $key = str_replace(
	        " ",
	        "-",
	        ucwords(strtolower(str_replace("_", " ", substr($key, 5))))
	      );
	      $out[$key] = $value;
	    }
	  }
	  return $out;
	}

	protected function send_options() {
    	header("HTTP/1.0 200 OK");
        header('Content-type: text/plain');
        header('Content-length: 0');
        header('Connection: Keep Alive');
        header('Keep Alive: timeout=2, max=100'); // TODO: What is most appropriate? Is this necessary?
        header('Access-Control-Allow-Origin: *'); // TODO: Decide how or whether or not to limit Xdomains.
        header('Access-Control-Allow-Methods: PUT, DELETE, POST, GET, OPTIONS'); // TODO: Should we base this on specific request and restrict based on endpoint target/request?
        header('Access-Control-Allow-Headers: authorization,content-type'); // TODO: Should we base this on specific request and restrict based on endpoint target/request?
        header('Access-Control-Max-Age: 1728000'); // TODO: What's appropriate here?
        exit();
	}
	
	/**
     * Send the result of function call to the WS client.
     * If an exception is caught, ensure a failure response code is sent.
     */
    protected function send_response() {

        //Check that the returned values are valid
        try {
            if ($this->function->returns_desc != null) {
                $validatedvalues = external_api::clean_returnvalue($this->function->returns_desc, $this->returns);
            } else {
                $validatedvalues = null;
            }
        } catch (Exception $ex) {
            $exception = $ex;
        }

        if (!empty($exception)) {
            $response =  $this->generate_error($exception);
            if ($this->response_code == '200')
            	$this->response_code = '400';
        } else {
            $response = ($this->function->returns_desc instanceof external_value) ? $validatedvalues : json_encode($validatedvalues);
        }
        $this->send_headers();
        echo $response;
    }

    /**
     * Send the error information to the WS client.
     * Note: the exception is never passed as null,
     *       it only matches the abstract function declaration.
     * @param exception $ex the exception that we are sending
     */
    protected function send_error($ex=null) {
        $this->send_headers(true);
        echo $this->generate_error($ex);
    }

    /**
     * Build the error information matching the JSON format
     * @param exception $ex the exception we are converting in the server rest format
     * @return string the error in JSON format
     */
    protected function generate_error($ex) {
        $errorobject = new stdClass;
        $errorobject->exception = get_class($ex);
        $errorobject->errorcode = $ex->errorcode;
        $errorobject->message = $ex->getMessage();
        if (debugging() and isset($ex->debuginfo)) {
            $errorobject->debuginfo = $ex->debuginfo;
        }
        $error = json_encode($errorobject);
        return $error;
    }

    /**
     * Internal implementation - sending of page headers.
     * @param boolean $iserror send error header with 400 response code if not already defined
     */
    protected function send_headers($iserror=false) {
    	if ($iserror && $this->response_code == '200')
            $this->response_code = '400';
    	header("HTTP/1.0 ".$this->response_code,true,$this->response_code);
        header('Access-Control-Allow-Origin: *'); // TODO: Decide how or whether or not to limit Xdomains.
    	header('Content-type: application/json');
        header('Cache-Control: private, must-revalidate, pre-check=0, post-check=0, max-age=0');
        header('Expires: '. gmdate('D, d M Y H:i:s', 0) .' GMT');
        header('Pragma: no-cache');
        header('Accept-Ranges: none');
    }
    
    /**
     * Internal implementation - get function name to execute
     * as part of webservice request.
     * @return string name of external function
     */
    protected function get_functionname() {
		/*
		 * Get the arguments passed to endpoint.
		 * Borrowed from weblib.php / get_file_arguments().
		 */
	    $relativepath = false;
	    
	    // then try extract file from the slasharguments
	    if (stripos($_SERVER['SERVER_SOFTWARE'], 'iis') !== false) {
	        // NOTE: ISS tends to convert all file paths to single byte DOS encoding,
	        //       we can not use other methods because they break unicode chars,
	        //       the only way is to use URL rewriting
	        if (isset($_SERVER['PATH_INFO']) and $_SERVER['PATH_INFO'] !== '') {
	            // check that PATH_INFO works == must not contain the script name
	            if (strpos($_SERVER['PATH_INFO'], $SCRIPT) === false) {
	                $relativepath = clean_param(urldecode($_SERVER['PATH_INFO']), PARAM_PATH);
	            }
	        }
	    } else {
	        // all other apache-like servers depend on PATH_INFO
	        if (isset($_SERVER['PATH_INFO'])) {
	            if (isset($_SERVER['SCRIPT_NAME']) and strpos($_SERVER['PATH_INFO'], $_SERVER['SCRIPT_NAME']) === 0) {
	                $relativepath = substr($_SERVER['PATH_INFO'], strlen($_SERVER['SCRIPT_NAME']));
	            } else {
	                $relativepath = $_SERVER['PATH_INFO'];
	            }
	            $relativepath = strtolower(clean_param($relativepath, PARAM_PATH));
	        }
	    }
		$functionname = '';
	    unset($this->parameters['method']);
	    
        if (substr($relativepath,0,11) == '/statements') {
        	switch ($this->requestmethod) {
        		case  'PUT':
        			$functionname = 'store_statement';
        			$this->response_encode = false;
        			break;
        		case  'POST':
        			$functionname = 'store_statement';
        			break;
        		default:
        			$functionname = 'fetch_statement';
        			break;
        	}
        } else if (substr($relativepath,0,17) == '/activities/state') {
        	$this->response_encode = false;
        	switch ($this->requestmethod) {
        		case  'PUT':
        			$functionname = 'store_activity_state';
        			break;
        		case  'DELETE':
        			$functionname = 'delete_activity_state';
        			break;
        		default:
        			$functionname = 'fetch_activity_state';
        			break;
        	}
        }
        if (!empty($functionname)) {
        	$this->get_functionparams($functionname);
        	return 'local_tcapi_'.$functionname;
        }
        return null;
    }
    
    /**
     * Internal implementation - sets parameters to extract from all parameters
     * passed as part of webservice request based on external function called.
     * @param string $functionname name of external function
     */
    protected function get_functionparams($functionname) {
    	$paramkeys = array('moodle_mod','moodle_mod_id');
    	switch($functionname) {
    		case 'fetch_statement':
    			$paramkeys = array_merge($paramkeys, array('statementId','registration')); 
    			break;
    		case 'store_statement':
    			$paramkeys = array_merge($paramkeys, array('content','statementId','registration')); 
    			break;
    		case 'store_activity_state':
    			$paramkeys = array_merge($paramkeys, array('content','activityId','actor','registration','stateId')); 
    			break;
    		case 'delete_activity_state':
    			$paramkeys = array_merge($paramkeys, array('activityId','actor','registration')); 
    			break;
    		case 'fetch_activity_state':
    			$paramkeys = array_merge($paramkeys, array('activityId','actor','registration','stateId','since')); 
    			break;
    		case 'delete_activity_state':
    			$paramkeys = array_merge($paramkeys, array('activityId','actor','registration')); 
    			break;
    			
    	}
    	$parameters = array();
    	foreach ($paramkeys as $key)
    		$parameters[$key] = (isset($this->parameters[$key])) ? $this->parameters[$key] : null;
    	$this->parameters = $parameters;
    }

}


/**
 * TCAPI test client class
 *
 * @package    webservice_tcapi
 * @copyright  2012 Jamie Smith
 * TODO: Refine to allow tesing of service. (Should construct sample statement.)
 */
class webservice_tcapi_test_client implements webservice_test_client_interface {
    /**
     * Execute test client WS request
     * @param string $serverurl server url (including token parameter)
     * @param string $function function name
     * @param array $params parameters of the called function
     * @return mixed
     */
    public function simpletest($serverurl, $function, $params) {
        return download_file_content($serverurl.'&wsfunction='.$function, null, $params);
    }
}

/**
 * Store posted statement and return statement id.
 * This function is called by the external service in externallib.php.
 * @param array $params array of parameter passed from external service 
 * @throws invalid_response_exception
 * @throws invalid_parameter_exception
 * @return mixed $return object to be used by external service or false if failure
 */
function local_tcapi_store_statement ($params) {
	global $DB;
	if (!is_null($params['content']) && ($statement = json_decode($params['content']))) {
		if (is_null($params['statementId']) && !isset($statement->id)) {
		    // make sure the statementId doesn't exist (borrowed from /lib/externallib.php)
		    $numtries = 0;
		    do {
		        $numtries ++;
		        $statementId = md5(uniqid(rand(),1));
		        if ($numtries > 5)
		            throw new invalid_response_exception('Could not create statement_id.');
		    } while ($DB->record_exists('tcapi_statement', array('statement_id'=>$statementId)) && $numtries <= 5);
		} else
			$statementId = (isset($statement->id)) ? $statement->id : $params['statementId'];
		$sData = new stdClass();
		$sData->statement_id = $statementId;
		$sData->statement = $params['content'];
		$sData->stored = time();
		$sData->verb = (isset($statement->verb)) ? $statement->verb : 'experienced';
		if (isset($statement->inProgress))
			$sData->inProgress = '1';
		if (!($actor = local_tcapi_get_actor($statement->actor))) {
			throw new invalid_response_exception('Agent object could not be found or created.');
			return false;
		} else
			$sData->actorid = $actor->id;
		if (isset($statement->context)) {
			if (isset($statement->context->registration))
				$sData->registration = $statement->context->registration;
			if (isset($statement->context->instructor)) {
				if ($actor = local_tcapi_get_actor($statement->context->instructor, true))
					$sData->instructorid = $actor->id;
			}
			if (isset($statement->context->team)) {
				if ($actor = local_tcapi_get_actor($statement->context->team, true))
					$sData->teamid = $actor->id;
			}
			if (isset($statement->context->contextActivities)) {
				$cas = array('grouping','parent','other');
				foreach ($cas as $ca) {
					$fieldId = 'context_'.$ca.'id';
					if (isset($statement->context->contextActivities->$ca)
						&& isset($statement->context->contextActivities->$ca->id)) {
							$activity = new stdClass();
							$activity->activity_id = $statement->context->contextActivities->$ca->id;
							if (isset($sData->context_groupingid))
								$activity->grouping_id = $sData->context_groupingid;
							if ($activity = local_tcapi_get_activity($activity))
								$sData->$fieldId = $activity->id;
					}
				}
			}
		}
		if (isset($statement->object)) {
			if (!isset($statement->object->objectType))
				$statement->object->objectType = 'activity';
			$objectType = strtolower($statement->object->objectType);
			$sData->object_type = $objectType;
			switch ($objectType) {
				case 'activity':
					if (isset($statement->object->id)) {
						$activity = new stdClass();
						$activity->activity_id = $statement->object->id;
						if (isset($statement->object->definition))
							$activity->definition = $statement->object->definition;
						if (isset($sData->context_groupingid))
							$activity->grouping_id = $sData->context_groupingid;
						if (!($activity = local_tcapi_get_activity($activity))) {
							throw new invalid_response_exception('Activity could not be found or created.');
							return false;
						}
						if (isset($activity->activityid))
							$statement->object->id = $activity->activityid; // be sure to capture this in case it's been captured from metadata
						$statement->activity = $activity;
					} else {
						throw new invalid_parameter_exception('Object->id required from statement.');
						return false;
					}
					$sData->objectid = $activity->id;
				break;
				case 'statement':
					if ($sData->verb == 'voided') {
						if (isset($statement->object->id) && ($r = $DB->get_record_select('tcapi_statement', 'statement_id = \'?\'', array($statement->object->id)))) {
							$r->voided = '1';
							if ($DB->update_record('tcapi_statement', $r) !== true) {
								throw new invalid_parameter_exception('Statement could not be voided.');
								return false;							
							}
						} else {
							throw new invalid_parameter_exception('statementId parameter required.');
							return false;							
						}
						$sData->objectid = $r->id;
					}
				break;
				case 'agent':
				case 'person':
				case 'group':
					if (isset($statement->object->id)) {
						if (!($actor = local_tcapi_get_actor($params['actor'], true))) {
							throw new invalid_response_exception('Agent object could not be found or created.');
							return false;
						}
					} else {
						throw new invalid_parameter_exception('Object->id required from statement.');
						return false;
					}
					$sData->objectid = $actor->id;
				break;
			}
		} elseif ($sData->verb == 'voided') {
			throw new invalid_parameter_exception('Statement object parameter required.');
			return false;
		}
		if (isset($statement->timestamp) && ($timestamp = strtotime($statement->timestamp)))
			$sData->timestamp = $timestamp;
		if (isset($statement->result)) {
			$rData = new stdClass();
			if (isset($statement->result->score))
				$rData->score = json_encode($statement->result->score);
			if (isset($statement->result->success))
				$rData->success = ($statement->result->success == 'true') ? '1' : '0';
			if (isset($statement->result->completion))
				$rData->completion = (strtolower($statement->result->completion) == 'completed') ? '1' : '0';
			if (isset($statement->result->duration)) {
				if ($tarr = local_tcapi_parse_duration($statement->result->duration))
					$rData->duration = implode(":",$tarr);
			}
			// Check special verbs for assumed completion and success results.
			// Return error if conflicting results are reported.
			$srv_verbs = array('completed','passed','mastered','failed');
			if (in_array($sData->verb, $srv_verbs)) {
				$completion = '1';
				$success = ($sData->verb == 'failed') ? '0' : '1';
				if ((isset($rData->completion) && $rData->completion != $completion)
					|| (isset($rData->success) && $rData->success != $success)) {
						throw new invalid_parameter_exception('Statement result conflict.');
						return false;
					}
				$rData->completion = $completion;
				$rData->success = $success;
			}
			if (isset($statement->result->response))
				$rData->response = $statement->result->response;
			if (($rid = $DB->insert_record('tcapi_result',$rData,true)) !== false)
				$sData->resultid = $rid;
		}
		if (!$DB->insert_record('tcapi_statement', $sData))
			throw new invalid_response_exception('Activity could not be found or created.');
		$return = new stdClass();
		$return->statement = $statement;
		$return->statementId = $sData->statement_id;
		$return->statementRow = $sData;
		if (isset($rData))
			$return->resultRow = $rData;
		return $return;
	}
		
	return false;
}

/**
 * Stores a posted activity state.
 * Called by the external service in externallib.php
 * @param array $params array of parameter passed from external service
 * @throws invalid_response_exception
 * @throws invalid_parameter_exception
 * @return mixed empty string or throws exception
 */
function local_tcapi_store_activity_state ($params) {
	global $DB;
	if (!is_null($params['activityId']) && !is_null($params['stateId']) && !is_null($params['content'])) {
		if ($actor = local_tcapi_get_actor($params['actor'])) {
			$data = new stdClass();
			$data->actorid = $actor->id;
			$data->state_id = $params['stateId'];
			if (isset($params['registration']))
				$data->registration = $params['registration'];
			$data->contents = $params['content'];
			$data->updated = time();
			$activity = new stdClass();
			$activity->activity_id = $params['activityId'];
			if (!($activity = local_tcapi_get_activity($activity)))
				throw new invalid_response_exception('Activity could not be found or created.');
			else
				$data->activityid = $activity->id;
			// Get state content for specific stateId
			if ($r = $DB->get_record_select('tcapi_state', 'actorid = ? AND activityid = ? AND state_id = ? ORDER BY `updated` DESC',array($actor->id, $activity->id, $params['stateId']),'id'))
			{
				$data->id = $r->id;
				if ($DB->update_record('tcapi_state', $data))
					return '';
				
			} elseif ($DB->insert_record('tcapi_state', $data))
					return '';
		}
	}
	throw new invalid_parameter_exception('Parameters invalid or state could not be stored.');
}

/**
 * Retrieves an activity state.
 * Called by the external service in externallib.php.
 * If the stateId is provided, will return the value stored under that stateId. Otherwise,
 * will return all stored stateIds as json encoded array.
 * @param array $params array of parameter passed from external service
 * @throws invalid_parameter_exception
 * @return mixed string containing state, string containing json encoded array of stored stateIds, or throws exception
 */
function local_tcapi_fetch_activity_state ($params) {
	global $DB;
	if (!is_null($params['activityId'])) {
		if ($actor = local_tcapi_get_actor($params['actor'])) {
			$return = '';
			$activity = new stdClass();
			$activity->activity_id = $params['activityId'];
			if (!($activity = local_tcapi_get_activity($activity,true)))
				return $return;
			if (isset($params['stateId'])) {
				// Get state content for specific stateId
				if ($r = $DB->get_record_select('tcapi_state', 'actorid = ? AND activityid = ? AND state_id = ? ORDER BY `updated` DESC',array($actor->id, $activity->id, $params['stateId']),'id,contents'))
					$return = $r->contents;
			} else {
				$states = array();
				$since = (isset($params['since']) && ($sinceTime = strtotime($params['since']))) ? 'AND `updated` >= '.$sinceTime : '';
				// Get all stateIds stored
				if ($rs = $DB->get_records_select('tcapi_state', 'actorid = ? AND activityid = ?'.$since,array($actor->id, $activity->id),'','id,state_id')) {
					foreach ($rs as $r)
						array_push($states,$r->stateid);
				}
				$return = json_encode($states);
			}
			return $return;
		}
	}
	throw new invalid_parameter_exception('Parameters invalid or state could not be retrieved.');
}

/**
 * Permanently deletes all states associated with a specific author and activity.
 * @param array $params array of parameter passed from external service
 * @return mixed an empty string if success or throws an exception
 */
function local_tcapi_delete_activity_state ($params) {
	global $DB;
	if (!is_null($params['activityId'])) {
		if ($actor = local_tcapi_get_actor($params['actor'])) {
			$activity = new stdClass();
			$activity->activity_id = $params['activityId'];
			if (!($activity = local_tcapi_get_activity($activity,true)))
				throw new invalid_response_exception('Activity could not be found.');
			// Delete all states stored
			$DB->delete_records_select('tcapi_state', 'actorid = ? AND activityid = ?',array($actor->id, $activity->id));
			return '';
		}
	}
	throw new invalid_parameter_exception('Parameters invalid or actor could not be found.');
}

function local_tcapi_get_actor ($actor, $objectType = false) {
	global $DB;
	if ((is_null($actor) || !is_object($actor)) && $objectType === false) {
		global $USER;
		$object = new stdClass();
		$object->name = array($USER->firstname.' '.$USER->lastname);
		$object->mbox = array($USER->email);
		$object->localid = $USER->id;
	}
	else
		$object = $actor;
	if (isset($object->mbox) && !empty($object->mbox)) {
		foreach ($object->mbox as $key=>$val)
			$object->mbox[$key] = (strpos($val,'mailto:') !== false) ? substr($val,strpos($val,'mailto:')+7) : $val;
	}
	$sqlwhere = 'object_type=\'person\'';
	$xtrasql = array();
	if (isset($object->localid))
		array_push($xtrasql, 'localid = '.$USER->id);
	if (isset($object->mbox_sha1sum) && !empty($object->mbox_sha1sum))
		array_push($xtrasql, '(mbox_sha1sum LIKE \'%'. implode("'%\' OR mbox_sha1sum LIKE \'%'",$object->mbox_sha1sum) .'%\))');
	if (isset($object->mbox) && !empty($object->mbox))
		array_push($xtrasql, '(mbox LIKE \'%'. implode("'%\' OR mbox LIKE \'%'",$object->mbox) .'%\')');
	if (!empty($xtrasql))
		$sqlwhere .= ' AND ('.implode(" OR ", $xtrasql).')';
	if (($actor = $DB->get_record_select('tcapi_agent', $sqlwhere)))
	{
		$actor = local_tcapi_push_actor_properties($actor, $object);
		if (isset($object->localid))
			$actor->localid = $object->localid;
		$DB->update_record('tcapi_agent', local_tcapi_db_conform($actor));
		return $actor;
	} else {
		$actor = new stdClass();
		$actor->object_type = 'person';
		if (isset($object->localid))
			$actor->localid = $object->localid;
		$actor = local_tcapi_push_actor_properties($actor, $object);
		if ($actor->id = $DB->insert_record('tcapi_agent', local_tcapi_db_conform($actor), true))
			return $actor;
	}
	return false;
}

function local_tcapi_get_activity ($object, $mustExist=false, $forceupdate=false) {
	global $DB,$CFG;
	$isMetaLink = (filter_var($object->activity_id,FILTER_VALIDATE_URL,FILTER_FLAG_PATH_REQUIRED)
		&& basename($object->activity_id) == 'tincan.xml');
	if (($isMetaLink && ($activity = $DB->get_record_select('tcapi_activity', 'metaurl = ?', array($object->activity_id))))
		|| (isset($object->grouping_id) && ($activity = $DB->get_record_select('tcapi_activity', 'activity_id = ? && grouping_id = ?', array($object->activity_id, $object->grouping_id))))
		|| (!isset($object->grouping_id) && !isset($object->metaurl) && ($activity = $DB->get_record_select('tcapi_activity', 'activity_id = ?', array($object->activity_id))))
		|| (isset($object->metaurl) && ($activity = $DB->get_record_select('tcapi_activity', 'activity_id = ? && metaurl = ?', array($object->activity_id, $object->metaurl))))
		)
	{
		if (empty($activity->known) || $forceupdate) {
			$activity = local_tcapi_push_activity_properties($activity, $object);
			$DB->update_record('tcapi_activity', local_tcapi_db_conform($activity));
		}
		return $activity;
	} else if ($isMetaLink)
	{
		$object->metaurl = $object->activity_id;
		// activity is defined in tincan.xml file
		$mparser = new local_tcapi_metaParser($object->metaurl);
		if (($activity = $mparser->parse()) && empty($mparser->errors))
			return $activity;
		elseif (!empty($mparser->errors))
			throw new invalid_response_exception(implode(" ",$mparser->errors));
	} else if ($mustExist === false) {
		$activity = new stdClass();
		$activity = local_tcapi_push_activity_properties($activity, $object);
		if ($activity->id = $DB->insert_record('tcapi_activity', local_tcapi_db_conform($activity), true))
			return $activity;
	}
	return false;
}

/**
 * 
 * Converts property names within object to Moodle DB conforming field names.
 * This is necessary prior to insertion into database.
 * @param object $object
 * @throws moodle_exception
 * @throws invalid_response_exception
 * @throws invalid_parameter_exception
 * @return object $newobject
 */
function local_tcapi_db_conform ($object) {
	$newobject = new stdClass();
	foreach ($object as $key => $val) {
		$key = strtolower(preg_replace('/([A-Z])/', '_$1', $key));
		$newobject->$key = $val;
	}
	return $newobject;
}

function local_tcapi_get_activity_id ($activity_id) {
	return $DB->get_record_select('tcapi_activity', 'activity_id = ?', array($activity_id));
}

function local_tcapi_push_actor_properties ($actor, $object) {
	$actor->givenName = isset($actor->given_name) ? $actor->given_name : null;
	$actor->familyName = isset($actor->family_name) ? $actor->family_name : null;
	$actor->firstName = isset($actor->first_name) ? $actor->first_name : null;
	$actor->lastName = isset($actor->last_name) ? $actor->last_name : null;
	return local_tcapi_push_object_properties ($actor, $object, array('name','mbox','mbox_sha1sum','openid','account','givenName','familyName','firstName','lastName'),
			 array('name','mbox','mbox_sha1sum','openid','account','givenName','familyName','firstName','lastName'));
}

function local_tcapi_push_activity_properties ($activity, $object) {
	$activity->interactionType = isset($activity->interaction_type) ? $activity->interaction_type : null;
	if (isset($object->definition)) {
		$object->name = (isset($object->definition->name)) ? $object->definition->name : null;
		$object->description = (isset($object->definition->description)) ? $object->definition->description : null;
	}
	return local_tcapi_push_object_properties ($activity, $object, array('activity_id','metaurl','known','name','description','type','interactionType','extensions','grouping_id'),
			false, array('name','description'));
}

function local_tcapi_push_object_properties ($currObject, $pushObject, $propertyKeys, $multipleVals=false, $isObject=false) {
	foreach ($propertyKeys as $key) {
		if (isset($pushObject->$key) && !empty($pushObject->$key)) {
			if ($multipleVals !== false && in_array($key, $multipleVals)) {
				if (isset($currObject->$key) && !is_array($currObject->$key))
					$currObject->$key = unserialize($currObject->$key);
				$currValues = (isset($currObject->$key)) ? $currObject->$key : array();
				$primaryValue = array_shift($pushObject->$key);
				if (($pKey = array_search($primaryValue,$currValues)) !== false)
					unset($currValues[$pKey]);
				$newValues = array_merge(array($primaryValue),$currValues);
				foreach ($pushObject->$key as $pushVal) {
					if (!in_array($pushVal, $newValues))
						array_push($newValues, $pushVal);
				}
				$currObject->$key = serialize($newValues);				
			} elseif ($isObject !== false && in_array($key, $isObject)) {
				if (isset($currObject->$key) && !is_object($currObject->$key))
					$currValues = unserialize($currObject->$key);
				else
					$currValues = new stdClass();
				$pushVals = (array)$pushObject->$key;
				foreach ($pushVals as $k=>$v)
					$currValues->$k = $v;
				$currObject->$key = json_encode($currValues);
			} else
				$currObject->$key = $pushObject->$key;
		}
	}
	return $currObject;
}

/**
 * 
 * This class allows parsing of TCAPI xml node object.
 * The returnObject holds the object ready for json and inclusion in a statement.
 * dbObject returns an object ready for insertion updating of a DB entry.
 * @author Jamie Smith 2012
 *
 */
class local_tcapi_activityParser {
	
	var $objectType;
	var $xml;
	var $metaurl;
	var $activity;
	var $extensions;
	var $jsonObject;
	var $dbObject;
	
	function __construct ($type) {
		$this->objectType = $type;
		$this->activity = new stdClass();
	}
	
	function parseObject($xml) {
		$this->xml = $xml;
		$this->activity = new stdClass();
		$this->activity->definition = new stdClass();
		$this->parseAttrByName('id');
		$this->parseAttrByName('type', false, 'definition');
		$this->parseAttrByName('name', true, 'definition');
		$this->parseAttrByName('description', true, 'definition');
		$this->parseAttrByName('interactionType', false, 'definition');
		if ($this->activity->definition->type == 'cmi.interaction' && isset($this->activity->definition->interactionType))
			$this->parseInteractionExtensions();
		$this->jsonObject = json_encode($this->activity);
		$this->createDbObject();
	}
	
	function parseAttrByName ($attr,$lang=false,$ca=null) {
		$a = null;
		if (isset($this->xml[$attr]))
			$a = strval($this->xml[$attr]);
		elseif (isset($this->xml->$attr)) {
			if ($lang)
				$a = $this->parseAsLangStr($this->xml->$attr);
			elseif ($this->xml->$attr->count() > 1) {
				$a = array();
				foreach ($this->xml->$attr as $node)
					array_push($a,strval($node));
			} else
				$a = strval($this->xml->$attr);
		}
		if (is_null($a))
			return;
		if (!is_null($ca))
			$this->activity->$ca->$attr = $a;
		else
			$this->activity->$attr = $a;
	}
	
	function parseAsLangStr ($attr) {
		$arr = array();
		foreach ($attr as $node)
			$arr[strval($node['lang'])] = strval($node);
		return (object)$arr;
	}
	
	function parseInteractionExtensions () {
		$this->extensions = new stdClass();
		$crp = (isset($this->xml->correctResponsePatterns->correctResponsePattern)) ? $this->xml->correctResponsePatterns->correctResponsePattern : null;
		if (!is_null($crp)) {
			$cra = array();
			foreach ($crp as $cr)
				array_push($cra,strval($cr));
			$this->activity->definition->correctResponsesPattern = array(implode("[,]",$cra));
			$this->extensions->correctResponsesPattern = array(implode("[,]",$cra));
		}
		$componentNames = array();
		switch ($this->activity->definition->interactionType) {
			case 'choice':
			case 'multiple-choice':
			case 'sequencing':
			case 'true-false':
				array_push($componentNames,'choices');
			break;
			case 'likert':
				array_push($componentNames,'scale');
			break;
			case 'matching':
				array_push($componentNames,'source');
				array_push($componentNames,'target');
			break;
		}
		foreach ($componentNames as $components) {
			if (isset($this->xml->$components->component)) {
				$compArray = array();
				foreach($this->xml->$components->component as $compNode) {
					$compObject = new stdClass();
					if (isset($compNode->id))
						$compObject->id = strval($compNode->id);
					else
						continue;
					if (isset($compNode->description))
						$compObject->description = $this->parseAsLangStr($compNode->description);
					array_push($compArray,$compObject);
				}
				$this->activity->definition->$components = $compArray;
				$this->extensions->$components = $compArray;
			}
		}
		return;
	}
	
	function createDbObject() {
		$this->dbObject = new stdClass();
		$this->dbObject->activity_id = $this->activity->id;
		if (isset($this->metaurl))
			$this->dbObject->metaurl = $this->metaurl;
		$this->dbObject->known = 1;
		$this->dbObject->name = (isset($this->activity->definition->name)) ? json_encode($this->activity->definition->name) : null;
		$this->dbObject->description = (isset($this->activity->definition->description)) ? json_encode($this->activity->definition->description) : null;
		$this->dbObject->type = (isset($this->activity->definition->type)) ? $this->activity->definition->type : null;
		$this->dbObject->interactionType = (isset($this->activity->definition->interactionType)) ? $this->activity->definition->interactionType : null;
		$this->dbObject->extensions = (isset($this->extensions)) ? json_encode($this->extensions) : null;
	}
	
}

class local_tcapi_metaParser {
	
	var $xmlUrl;
	var $xml;
	var $activities = array();
	var $mainActivity;
	var $storeActivities = true;
	var $errors = array();
	
	function __construct ($xmlUrl) {
		$this->xmlUrl = $xmlUrl;
	}
	
	function parse () {
		if ($this->validate_xml() == false)
			return false;
		if (isset($this->xml->activities) && isset($this->xml->activities->activity)) {
			for ($i=0;$i<$this->xml->activities->activity->count();$i++) {
				$aparser = new local_tcapi_activityParser('activity');
				$aparser->parseObject($this->xml->activities->activity[$i]);
				if (!isset($this->mainActivity) && isset($aparser->activity->id)
					&& isset($aparser->activity->definition->type) && $aparser->activity->definition->type == 'course') {
					$aparser->metaurl = $this->xmlUrl;
					$aparser->parseObject($this->xml->activities->activity[$i]);
					$this->mainActivity = local_tcapi_get_activity($aparser->dbObject, false, true);
				}
				else
					array_push($this->activities,$aparser->dbObject);
			}
			if (isset($this->mainActivity)) {
				foreach($this->activities as $dbObject) {
					$dbObject->grouping_id = $this->mainActivity->id;
					$activity = local_tcapi_get_activity($dbObject, false, $this->storeActivities);					
				}				
				$return = $this->mainActivity;
			}
			return $return;
		}
			
	}
	
	function validate_xml () {
		global $CFG;
		$dom = new DOMDocument;
		$xml = $this->getXml();
		if (empty($xml)) 
			array_push($this->errors,'XML file not found or unavailable.');
		elseif ($dom->loadXML($xml) === false)
			array_push($this->errors,'Could not load XML.');
		elseif (!$dom->schemaValidate($CFG->dirroot.'/local/tcapi/tincan.xsd')
			|| ($this->xml = simplexml_import_dom($dom)) === false)
			{
				array_push($this->errors,'XML file invalid for schema.');
				return false;
			}
		return true;
	}
	
	/*
	 * Attempt to determine if this is a local file accessed with pluginfile.php.
	 * If so, get file directly using native file class.
	 */
	function getXml () {
		global $CFG;
		$search = "$CFG->wwwroot/pluginfile.php/";
		if (substr($this->xmlUrl,0,strlen($search)) == $search) {
			$url = str_replace('pluginfile.php', 'webservice/pluginfile.php', clean_param($this->xmlUrl, PARAM_LOCALURL));
			// Determine connector for launch params.
			$connector = (stripos($url, '?') !== false) ? '&' : '?';
			if ($token = local_tcapi_get_user_token())
				return file_get_contents($url.$connector.'token='.$token->token);
		}
		else
			return file_get_contents($this->xmlUrl);
		array_push($this->errors,'User token required but not valid/found.');
		return '';
	}
}

/**
 * Parse an ISO 8601 duration string
 * @return array
 * @param string $str
 **/
function local_tcapi_parse_duration($str)
{
   $result = array();
   preg_match('/^(?:P)([^T]*)(?:T)?(.*)?$/', trim($str), $sections);
   if(!empty($sections[1]))
   {
      preg_match_all('/(\d+)([YMWD])/', $sections[1], $parts, PREG_SET_ORDER);
      $units = array('Y' => 'years', 'M' => 'months', 'W' => 'weeks', 'D' => 'days');
      foreach($parts as $part)
      {
      	$part[1] = '00'.$part[1]; 
      	$value = (strpos($part[1], '.')) ? substr($part[1], (strpos($part[1], '.')-2), strlen($part[1])) : substr($part[1], -2, 2);
         $result[$units[$part[2]]] = $value;
      }
   }
   if(!empty($sections[2]))
   {
      preg_match_all('/(\d*\.?\d+|\d+)([HMS])/', $sections[2], $parts, PREG_SET_ORDER);
      $units = array('H' => 'hours', 'M' => 'minutes', 'S' => 'seconds');
      foreach($parts as $part)
      {
      	$part[1] = '00'.$part[1]; 
      	$value = (strpos($part[1], '.')) ? substr($part[1], (strpos($part[1], '.')-2), strlen($part[1])) : substr($part[1], -2, 2);
      	 $result[$units[$part[2]]] = $value;
      }
   }
   return $result;
}
?>