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
 * Web service local plugin tcapi external functions and service definitions.
 *
 * @package    local_tcapi
 * @copyright  2012 Jamie Smith
 */

// We defined the web service functions to install.
$functions = array(
        'local_tcapi_store_statement' => array(
                'classname'   => 'local_tcapi_external',
                'methodname'  => 'store_statement',
                'classpath'   => 'local/tcapi/externallib.php',
                'description' => 'Return statementId after storing state statement.',
                'type'        => 'write',
        ),
        'local_tcapi_fetch_statement' => array(
                'classname'   => 'local_tcapi_external',
                'methodname'  => 'fetch_statement',
                'classpath'   => 'local/tcapi/externallib.php',
                'description' => 'Return statement associated with specified statementId.',
                'type'        => 'read',
				'capabilities' => 'local/tcapi:fetchstatement',
        ),
        'local_tcapi_store_activity_state' => array(
                'classname'   => 'local_tcapi_external',
                'methodname'  => 'store_activity_state',
                'classpath'   => 'local/tcapi/externallib.php',
                'description' => 'Return success after storing state data.',
                'type'        => 'write',
        ),
        'local_tcapi_fetch_activity_state' => array(
                'classname'   => 'local_tcapi_external',
                'methodname'  => 'fetch_activity_state',
                'classpath'   => 'local/tcapi/externallib.php',
                'description' => 'Return stored state data.',
                'type'        => 'read',
        ),
        'local_tcapi_delete_activity_state' => array(
                'classname'   => 'local_tcapi_external',
                'methodname'  => 'delete_activity_state',
                'classpath'   => 'local/tcapi/externallib.php',
                'description' => 'Delete state data associated with specified actor and activity.',
                'type'        => 'write',
        ),
);

// We define the services to install as pre-build services. A pre-build service is not editable by administrator.
$services = array(
        'Tin Can API' => array(
                'functions' => array ('local_tcapi_store_statement','local_tcapi_fetch_statement','local_tcapi_store_activity_state','local_tcapi_fetch_activity_state','local_tcapi_delete_activity_state'),
				'requiredcapability' => 'local/tcapi:use',
				'shortname' => 'local_tcapi',
                'restrictedusers' => 0,
                'enabled' => 1,
				'downloadfiles' => 1,
        )
);
?>