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
 * Web service local plugin tcapi install code.
 *
 * @package    local_tcapi
 * @copyright  2012 Jamie Smith
 */

defined('MOODLE_INTERNAL') || die;

function xmldb_local_tcapi_install($oldversion) {
    global $CFG, $DB, $OUTPUT;

    // Do this every time just to make sure the correct permissions are in place.
	require_once("$CFG->dirroot/local/tcapi/locallib.php");
    local_tcapi_set_role_permission_overrides();
	
    // Moodle v2.2.0 release upgrade line
    // Put any upgrade step following this

    // Moodle v2.3.0 release upgrade line
    // Put any upgrade step following this


    return true;
}


?>