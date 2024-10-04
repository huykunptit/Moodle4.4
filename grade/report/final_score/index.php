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
 * Assignment report
 *
 * @copyright  2019 Howard Miller (howardsmiller@gmail.com)
 * @package    report
 * @subpackage finalscore
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\report_helper;

define('REPORT_PAGESIZE', 20);

require_once '../../../config.php';

// Parameters.
$id = required_param('id', PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$export = optional_param('export', 0, PARAM_INT);
$sifirst = optional_param('sifirst', 'all', PARAM_TEXT);
$silast = optional_param('silast', 'all', PARAM_TEXT);

$paramUrl = array('id' => $id);

if ($sifirst != 'all') {
    $paramUrl['sifirst'] = $sifirst;
}

if ($silast != 'all') {
    $paramUrl['silast'] = $silast;
}

$url = new moodle_url('/grade/report/final_score/index.php', $paramUrl);

$fullurl = new moodle_url('/grade/report/final_score/index.php', $paramUrl);

// Page setup.
$PAGE->set_url($fullurl);
$PAGE->set_pagelayout('report');

$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);

require_login($course);
$context = context_course::instance($course->id);
require_capability('gradereport/final_score:view', $context);

$output = $PAGE->get_renderer('gradereport_final_score');

$group = groups_get_course_group($course, true); 
if ($group === 0 && $course->groupmode == SEPARATEGROUPS) {
    require_capability('moodle/site:accessallgroups',$context);
}


$category = $DB->get_record('course_categories', array('id' => $course->category), 'name, parent', MUST_EXIST);
$category_chain = $category->name;
$student_role = $DB->get_record('role', array('shortname' => 'student'), 'id', MUST_EXIST);


$sql_count = '
    SELECT COUNT(*)
    FROM {user} u
    JOIN {role_assignments} ra ON ra.userid = u.id
    WHERE ra.roleid = :roleid 
    AND ra.contextid = :contextid ';

$offset = $page * REPORT_PAGESIZE;

$limit = '';

if (!$export) {
    $limit = 'LIMIT ' . $offset . ', ' . REPORT_PAGESIZE;
}


$sql = '
    SELECT u.*
    FROM {user} u
    JOIN {role_assignments} ra ON ra.userid = u.id
    WHERE ra.roleid = :roleid 
    AND ra.contextid = :contextid ';

$params = array(
    'roleid' => $student_role->id,
    'contextid' => $context->id,
);

$params_count = array(
    'roleid' => $student_role->id,
    'contextid' => $context->id,
);

if ($sifirst !== 'all') {
    // count total
    $sql_count .= ' AND u.firstname LIKE :firstname';
    $params_count['firstname'] = '%' . $sifirst . '%';

    // search user
    $sql .= ' AND u.lastname LIKE :firstname';
    $params['firstname'] = '%' . $sifirst . '%';
}

if ($silast !== 'all') {
    // count total
    $sql_count .= ' AND u.lastname LIKE :lastname';
    $params_count['lastname'] = '%' . $silast . '%';

    // search user
    $sql .= ' AND u.firstname LIKE :lastname';
    $params['lastname'] = '%' . $silast . '%';
}

// Add ordering and limit
$sql .= ' ORDER BY CONCAT(u.lastname, " ", u.firstname) ASC ' . $limit;


$total_students = $DB->count_records_sql($sql_count, $params_count);
$students = $DB->get_records_sql($sql, $params);




$student_ids = array_map(fn($student) => $student->id, $students);
$grades = gradereport_final_score\lib::get_grades_for_students($course->id, $student_ids);

// Convert grades data into a lookup array
$grades_lookup = [];
foreach ($grades as $grade) {
    $grades_lookup[$grade->student_id] = $grade;
}

// Prepare the data for rendering
$grades_data = [
    'heading' => "BẢNG ĐIỂM THÀNH PHẦN",
    'faculty' => $category_chain,
    'department' => str_replace("Bài giảng môn học: ", "", $course->summary),
    'term_info' => "Thi lần 1 học kỳ 1 năm học 2024-2025",
    'teacher_name' => "Phạm Thị Khánh",
    'students' => []
];
$index = 1 + ($page * REPORT_PAGESIZE);

foreach ($students as $student) {
    $grade = $grades_lookup[$student->id] ?? (object)[
        'midterm_grade' => 0,
        'practical_grade' => 0,
        'assignment_grade' => 0
    ];

    $grades_data['students'][] = [
        'index' => $index++,
        'student_id' => strtoupper($student->username),
        'last_name' => $student->lastname,
        'first_name' => $student->firstname,
        'class' => str_replace("Bài giảng môn học: ", "", $course->summary),
        'attendance_grade' => (int) \core_completion\progress::get_course_progress_percentage($course, $student->id) / 10,
        'practice_grade' => $grade->practical_grade ?? 0,
        'assignment_grade' => round($grade->midterm_grade, 2) ?? 0,
        'final_project_grade' => round($grade->assignment_grade, 2) ?? 0,
        'notes' => ''
    ];
}


// Set page title and heading
$PAGE->set_title($course->shortname . ': ' . get_string('pluginname', 'gradereport_final_score'));
$PAGE->set_heading($course->fullname);

// Output report
if (!$export) {

    // Initials bar.
    $pagingbar = '';
    
    $prefixfirst = 'sifirst';
    $prefixlast = 'silast';
    $pagingbar .= $OUTPUT->initials_bar($sifirst, 'firstinitial', get_string('firstname'), $prefixfirst, $url);
    $pagingbar .= $OUTPUT->initials_bar($silast, 'lastinitial', get_string('lastname'), $prefixlast, $url);


    echo $output->header();
    $pluginname = get_string('pluginname', 'gradereport_final_score');
    report_helper::print_report_selector($pluginname);
    
    echo '<div class="student-list-info d-flex justify-content-between align-items-center flex-column flex-sm-row flex-md-row flex-lg-row"><div>';
    echo '<h4>'. get_string('totallist', 'gradereport_final_score') . ': ' . $total_students . '</h4>';
    

    
    echo $pagingbar;
    echo '</div>';
    echo '<a href="'.$url. '&export=1" class="btn btn-primary h-100">'.get_string('exportfile', 'gradereport_final_score').'</a>';
    echo '</div>';


    echo $output->render_from_template('gradereport_final_score/table', $grades_data);
    echo $OUTPUT->paging_bar($total_students, $page, REPORT_PAGESIZE, $fullurl);

    echo $OUTPUT->footer();
} else {
    $teacher = $DB->get_records_sql(
        'SELECT u.firstname, u.lastname, ra.roleid as roleid
         FROM {user} u
         JOIN {role_assignments} ra ON ra.userid = u.id
         JOIN {role} r ON r.id = ra.roleid
         WHERE ra.contextid = :contextid
         AND r.id = :roleid',
        array(
            'contextid' => $context->id,
            'roleid' => 3
        )
    );
    
    
    gradereport_final_score\lib::createExcel($course, $students, $category, $teacher);
    $event = \report_completion\event\report_viewed::create(array('context' => $context));
    $event->trigger();
}