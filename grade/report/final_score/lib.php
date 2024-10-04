<?php
// This file is part of Moodle - http://moodle.org/
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
 * This file contains functions used by the assign report
 *
 * @package    report
 * @subpackage assign
 * @copyright  2019 Howard Miller
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * This function extends the navigation with the report items
 *
 * @param navigation_node $navigation The navigation node to extend
 * @param stdClass $course The course to object for the report
 * @param stdClass $context The context of the course
 */
function report_final_score_extend_navigation_course($navigation, $course, $context) {
    global $CFG, $OUTPUT;

    // Must have rights to view this course and to see one of the 'assignment' types.
    if (has_capability('gradereport/final_score:view', $context) && has_capability('mod/assign:grade', $context)) {
        $url = new moodle_url('/grade/report/final_score/index.php', array('id' => $course->id));
        $navigation->add(get_string('pluginname', 'gradereport_final_score'), $url,
            navigation_node::TYPE_SETTING, null, null, new pix_icon('i/report', ''));
    }
}

function report_final_score_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $array = array(
        '*'                       => get_string('page-x', 'pagetype'),
        'report-*'                => get_string('page-report-x', 'pagetype'),
        'report-completion-*'     => get_string('page-report-completion-x',  'gradereport_final_score'),
        'report-completion-index' => get_string('page-report-completion-index',  'gradereport_final_score'),
        'report-completion-user'  => get_string('page-report-completion-user',  'gradereport_final_score')
    );
    return $array;
}
