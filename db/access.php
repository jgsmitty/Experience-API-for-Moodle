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
 * TCAPI/LRS server related capabilities
 *
 * @package    local_tcapi
 * @category   access
 */

$capabilities = array(

    'local/tcapi:use' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
    ),
    'local/tcapi:fetchstatement' => array(
    	'captype' => 'read',
    	'contextlevel' => CONTEXT_SYSTEM,
    ),

);
