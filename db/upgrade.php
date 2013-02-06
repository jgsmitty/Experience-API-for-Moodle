<?php
/*
 * Created for addition of TCAPI support.
 * Jamie Smith - jamie.g.smith@gmail.com
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
 * Web service local plugin tcapi upgrade code.
 *
 * @package    local_tcapi
 * @copyright  2012 Jamie Smith
 */

defined('MOODLE_INTERNAL') || die;

function xmldb_local_tcapi_upgrade($oldversion) {
    global $CFG, $DB, $OUTPUT;

    // Do this every time just to make sure the correct permissions are in place.
	require_once("$CFG->dirroot/local/tcapi/locallib.php");
    local_tcapi_set_role_permission_overrides();
	
    $dbman = $DB->get_manager();


    // Moodle v2.2.0 release upgrade line
    // Put any upgrade step following this

    // Moodle v2.3.0 release upgrade line
    // Put any upgrade step following this

    if ($oldversion < 2013020502) {
    	// Changing nullability of field name on table tcapi_agent to null
    	$table = new xmldb_table('tcapi_agent');
    	$field = new xmldb_field('name', XMLDB_TYPE_TEXT, null, null, null, null, null, 'object_type');
    	
    	// Launch change of nullability for field name
    	$dbman->change_field_notnull($table, $field);
    	
    	// tcapi savepoint reached
    	upgrade_plugin_savepoint(true, 2013020502, 'local', 'tcapi');
    }
    
    if ($oldversion < 2013020503) {
    	// Update tcapi_agent fields to json vs. serialized
    	$fields = array('name','mbox','mbox_sha1sum','openid','account','given_name','family_name','first_name','last_name');
    	if ($records = $DB->get_records('tcapi_agent', array('object_type'=>'person'))) {
    		foreach ($records as $r) {
    			$update = false;
    			foreach ($fields as $key) {
    				if (!empty($r->$key) && ($val = unserialize($r->$key))) {
    					$r->$key = json_encode($val);
    					$update = true;
    				}
    			}
    			if ($update)
    				$DB->update_record('tcapi_agent', $r);
    		}
    	}
    	   	
    	// tcapi savepoint reached
    	upgrade_plugin_savepoint(true, 2013020503, 'local', 'tcapi');
    }
    
    return true;
}


?>