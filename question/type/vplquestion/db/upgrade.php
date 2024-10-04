<?php
// This file is part of Moodle - https://moodle.org/
//
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
 * Plug-in version and dependencies description.
 * @package    qtype_vplquestion
 * @copyright  Astor Bizard, 2019
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Performs database actions to upgrade from older versions, if required.
 * @param int $oldversion
 * @return boolean
 */
function xmldb_qtype_vplquestion_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2019091700) {
        $table = new xmldb_table('question_vplquestion');

        // Define field precheckpreference to be added to question_vplquestion.
        $field = new xmldb_field('precheckpreference', XMLDB_TYPE_CHAR, '4', null, null, null, null, 'execfiles');

        // Conditionally launch add field precheckpreference.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field precheckexecfiles to be added to question_vplquestion.
        $field = new xmldb_field('precheckexecfiles', XMLDB_TYPE_TEXT, null, null, null, null, null, 'precheckpreference');

        // Conditionally launch add field precheckexecfiles.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field allowcheck to be dropped from question_vplquestion.
        $field = new xmldb_field('allowcheck');

        // Conditionally launch drop field allowcheck.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Vplquestion savepoint reached.
        upgrade_plugin_savepoint(true, 2019091700, 'qtype', 'vplquestion');
    }

    return true;
}
