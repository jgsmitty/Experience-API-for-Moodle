<?php
/*
 * Created for addition of TCAPI support.
 * Jamie Smith - jamie.g.smith@gmail.com
 * Copied from common access.php as in other plugins.
 */

// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Tin Can API protocal as Local Plugin
 *
 * @package    local_tcapi
 * @copyright  2012 Jamie Smith
 */
require_once($CFG->libdir . "/externallib.php");

class local_tcapi_external extends external_api {

    public static function fetch_statement_parameters () {
        return new external_function_parameters(
                array('moodle_mod' => new external_value(PARAM_TEXT, 'Moodle module name, if any', VALUE_DEFAULT, NULL), 
                	'moodle_mod_id' => new external_value(PARAM_TEXT, 'Moodle module id, if any', VALUE_DEFAULT, NULL),
                	'registration' => new external_value(PARAM_TEXT, 'Registration ID associated with this state', VALUE_DEFAULT, NULL),
                	'statementId' => new external_value(PARAM_TEXT, 'Statement ID associated with this state', VALUE_DEFAULT, NULL),
                )
        );
    }

    public static function fetch_statement ($moodle_mod,$moodle_mod_id,$registration,$statementId) {
        global $CFG;
        
        $params = array('registration'=>$registration,'statementId'=>$statementId);
                
        $statementObject = local_tcapi_fetch_statement($params);
        if (!is_null($moodle_mod) && file_exists($CFG->dirroot.'/mod/'.$moodle_mod.'/tcapilib.php')) {
        	include_once $CFG->dirroot.'/mod/'.$moodle_mod.'/tcapilib.php';
        	$mod_function = $moodle_mod.'_tcapi_fetch_statement';
        	$params['moodle_mod_id'] = $moodle_mod_id;
        	if (function_exists($mod_function))
        		return call_user_func($mod_function, $params, $statementObject);
        }	
        	        
        return $statementObject->statement;
    }

    public static function fetch_statement_returns () {
        return new external_value(PARAM_TEXT, 'Statement requested if exists');
    }

	public static function store_statement_parameters () {
        return new external_function_parameters(
                array('moodle_mod' => new external_value(PARAM_TEXT, 'Moodle module name, if any', VALUE_DEFAULT, NULL), 
                	'moodle_mod_id' => new external_value(PARAM_TEXT, 'Moodle module id, if any', VALUE_DEFAULT, NULL),
                	'registration' => new external_value(PARAM_TEXT, 'Registration ID associated with this state', VALUE_DEFAULT, NULL),
                	'statementId' => new external_value(PARAM_TEXT, 'Statement ID associated with this state', VALUE_DEFAULT, NULL),
                	'content' => new external_value(PARAM_TEXT, 'Statement to store', VALUE_DEFAULT, ''),
                )
        );
    }

    public static function store_statement ($moodle_mod,$moodle_mod_id,$registration,$statementId,$content) {
        global $CFG;
        
        $params = array('registration'=>$registration,'statementId'=>$statementId,'content' => $content);
                
        $statementObject = local_tcapi_store_statement($params);
        if (!is_null($moodle_mod) && file_exists($CFG->dirroot.'/mod/'.$moodle_mod.'/tcapilib.php')) {
        	include_once $CFG->dirroot.'/mod/'.$moodle_mod.'/tcapilib.php';
        	$mod_function = $moodle_mod.'_tcapi_store_statement';
        	$params['moodle_mod_id'] = $moodle_mod_id;
        	if (function_exists($mod_function))
        		return call_user_func($mod_function, $params, $statementObject);
        }	
        	        
        return $statementObject->statementId;
    }

    public static function store_statement_returns () {
        return new external_value(PARAM_TEXT, 'Statement ID of stored statement');
    }

	public static function store_activity_state_parameters () {
        return new external_function_parameters(
                array('moodle_mod' => new external_value(PARAM_TEXT, 'Moodle module name, if any', VALUE_DEFAULT, NULL), 
                	'moodle_mod_id' => new external_value(PARAM_TEXT, 'Moodle module id, if any', VALUE_DEFAULT, NULL),
                	'content' => new external_value(PARAM_TEXT, 'State document to store', VALUE_DEFAULT, ''),
                	'activityId' => new external_value(PARAM_TEXT, 'Activity ID associated with this state'),
                	'actor' => new external_value(PARAM_RAW, 'Actor associated with this state'),
                	'registration' => new external_value(PARAM_TEXT, 'Registration ID associated with this state', VALUE_DEFAULT, NULL),
                	'stateId' => new external_value(PARAM_TEXT, 'id for the state, within the given context'),
                )
        );
    }

    public static function store_activity_state($moodle_mod,$moodle_mod_id,$content,$activityId,$actor,$registration,$stateId) {
        global $CFG;
        
        $params = array('content' => $content,'activityId'=>$activityId,'actor'=>$actor,'registration'=>$registration,'stateId'=>$stateId);
        $params['actor'] = json_decode($actor);
                
        $response = local_tcapi_store_activity_state($params);
        
        if (!is_null($moodle_mod) && file_exists($CFG->dirroot.'/mod/'.$moodle_mod.'/tcapilib.php')) {
        	include_once $CFG->dirroot.'/mod/'.$moodle_mod.'/tcapilib.php';
        	$mod_function = $moodle_mod.'_tcapi_store_activity_state';
        	$params['moodle_mod_id'] = $moodle_mod_id;
        	if (function_exists($mod_function))
        		return call_user_func($mod_function, $params, $response);
        }	
        
        return $response;
    }

    public static function store_activity_state_returns() {
        return new external_value(PARAM_TEXT, 'Success or Failure');
    }

    public static function fetch_activity_state_parameters() {
        return new external_function_parameters(
                array('moodle_mod' => new external_value(PARAM_TEXT, 'Moodle module name, if any', VALUE_DEFAULT, NULL), 
                	'moodle_mod_id' => new external_value(PARAM_TEXT, 'Moodle module id, if any', VALUE_DEFAULT, NULL),
                	'activityId' => new external_value(PARAM_TEXT, 'Activity ID associated with state(s)'),
                	'actor' => new external_value(PARAM_RAW, 'Actor associated with state(s)'),
                	'registration' => new external_value(PARAM_TEXT, 'Registration ID associated with state(s)', VALUE_DEFAULT, NULL),
                	'stateId' => new external_value(PARAM_TEXT, 'id for the state, within the given context', VALUE_DEFAULT, NULL),
                	'since' => new external_value(PARAM_TEXT, 'time benchmark, if any', VALUE_DEFAULT, NULL),
                )
        );
    }

    public static function fetch_activity_state($moodle_mod,$moodle_mod_id,$activityId,$actor,$registration,$stateId,$since) {
        global $CFG;
        
        $params = array('activityId'=>$activityId,'actor'=>$actor,'registration'=>$registration,'stateId'=>$stateId,'since'=>$since);
        $params['actor'] = json_decode($actor);
                
        $response = local_tcapi_fetch_activity_state($params);
        
        if (!is_null($moodle_mod) && file_exists($CFG->dirroot.'/mod/'.$moodle_mod.'/tcapilib.php')) {
        	include_once $CFG->dirroot.'/mod/'.$moodle_mod.'/tcapilib.php';
        	$mod_function = $moodle_mod.'_tcapi_fetch_activity_state';
        		$params['moodle_mod_id'] = $moodle_mod_id;
        	if (function_exists($mod_function))
        		return call_user_func($mod_function, $params, $response);
        }		
        
        return $response;
    }

    public static function fetch_activity_state_returns() {
        return new external_value(PARAM_TEXT, 'Activity state value');
    }

    public static function delete_activity_state_parameters() {
        return new external_function_parameters(
                array('moodle_mod' => new external_value(PARAM_TEXT, 'Moodle module name, if any', VALUE_DEFAULT, NULL), 
                	'moodle_mod_id' => new external_value(PARAM_TEXT, 'Moodle module id, if any', VALUE_DEFAULT, NULL),
                	'activityId' => new external_value(PARAM_TEXT, 'Activity ID associated with state(s)'),
                	'actor' => new external_value(PARAM_RAW, 'Actor associated with state(s)'),
                	'registration' => new external_value(PARAM_TEXT, 'Registration ID associated with state(s)', VALUE_DEFAULT, NULL),
                )
        );
    }

    public static function delete_activity_state($moodle_mod,$moodle_mod_id,$activityId,$actor,$registration) {
        global $CFG;
        
        $params = array('activityId'=>$activityId,'actor'=>$actor,'registration'=>$registration);
        $params['actor'] = json_decode($actor);
                
        if (!is_null($moodle_mod) && file_exists($CFG->dirroot.'/mod/'.$moodle_mod.'/tcapilib.php')) {
        	include_once $CFG->dirroot.'/mod/'.$moodle_mod.'/tcapilib.php';
        	$mod_function = $moodle_mod.'_tcapi_fetch_activity_state';
        	if (function_exists($mod_function)) {
        		$params['moodle_mod_id'] = $moodle_mod_id;
        		$params = $mod_function($params);
        	}
        }		
        unset($params['moodle_mod_id']);
        if (isset($params['response']))
        	return $params['response'];
        else
    		return local_tcapi_delete_activity_state($params);
    }

    public static function delete_activity_state_returns() {
        return new external_value(PARAM_TEXT, 'Empty string');
    }
}