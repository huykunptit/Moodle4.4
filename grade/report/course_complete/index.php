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
 * Course completion progress report
 *
 * @package    report
 * @subpackage completion
 * @copyright  2009 Catalyst IT Ltd
 * @author     Aaron Barnes <aaronb@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\report_helper;

require_once '../../../config.php';

/**
 * Configuration
 */
define('COMPLETION_REPORT_PAGE',        25);
define('COMPLETION_REPORT_COL_TITLES',  true);

/*
 * Setup page, check permissions
 */

// Get course
$courseid = required_param('course', PARAM_INT);
$export = optional_param('export', 0, PARAM_INT);
$sort = optional_param('sort','',PARAM_ALPHA);
$edituser = optional_param('edituser', 0, PARAM_INT);


$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$context = context_course::instance($course->id);

$url = new moodle_url('/grade/report/course_complete/index.php', array('course'=>$course->id));
$PAGE->set_url($url);
$PAGE->set_pagelayout('report');



// Paging
$start   = optional_param('start', 0, PARAM_INT);
$sifirst = optional_param('sifirst', 'all', PARAM_NOTAGS);
$silast  = optional_param('silast', 'all', PARAM_NOTAGS);

// Whether to show extra user identity information.
$extrafields = \core_user\fields::get_identity_fields($context, true);
$leftcols = 1 + count($extrafields);

// Check permissions
require_login($course);

require_capability('gradereport/course_complete:view', $context);

// Get group mode
$group = groups_get_course_group($course, true); 
if ($group === 0 && $course->groupmode == SEPARATEGROUPS) {
    require_capability('moodle/site:accessallgroups',$context);
}

/**
 * Load data
 */

// Retrieve course_module data for all modules in the course
$modinfo = get_fast_modinfo($course);

// Get criteria for course
$completion = new completion_info($course);

if (!$completion->has_criteria()) {

    notice(
        get_string('nocriteriaset', 'gradereport_course_complete'), 
        $url 
    );

}

// Get criteria and put in correct order
$criteria = array();

foreach ($completion->get_criteria(COMPLETION_CRITERIA_TYPE_COURSE) as $criterion) {
    $criteria[] = $criterion;
}

foreach ($completion->get_criteria(COMPLETION_CRITERIA_TYPE_ACTIVITY) as $criterion) {
    $criteria[] = $criterion;
}

foreach ($completion->get_criteria() as $criterion) {
    if (!in_array($criterion->criteriatype, array(
            COMPLETION_CRITERIA_TYPE_COURSE, COMPLETION_CRITERIA_TYPE_ACTIVITY))) {
        $criteria[] = $criterion;
    }
}

// Can logged in user mark users as complete?
// (if the logged in user has a role defined in the role criteria)
$allow_marking = false;
$allow_marking_criteria = null;

if (!$export) {
    // Get role criteria
    $rcriteria = $completion->get_criteria(COMPLETION_CRITERIA_TYPE_ROLE);

    if (!empty($rcriteria)) {

        foreach ($rcriteria as $rcriterion) {
            $users = get_role_users($rcriterion->role, $context, true);

            // If logged in user has this role, allow marking complete
            if ($users && in_array($USER->id, array_keys($users))) {
                $allow_marking = true;
                $allow_marking_criteria = $rcriterion->id;
                break;
            }
        }
    }


    // Navigation and header
    $strcompletion = get_string('coursecompletion');

    $PAGE->set_title($strcompletion);
    $PAGE->set_heading($course->fullname);

    echo $OUTPUT->header();
    // Print the selected dropdown.
    $pluginname = get_string('pluginname', 'gradereport_course_complete');
    report_helper::print_report_selector($pluginname);

    // Handle groups (if enabled)
    groups_print_course_menu($course, $CFG->wwwroot.'/grade/report/course_complete/index.php?course='.$course->id);

}



/*
 * Setup page header
 */

    

if ($sifirst !== 'all') {
    set_user_preference('ifirst', $sifirst);
}
if ($silast !== 'all') {
    set_user_preference('ilast', $silast);
}

if (!empty($USER->preference['ifirst'])) {
    $sifirst = $USER->preference['ifirst'];
} else {
    $sifirst = 'all';
}

if (!empty($USER->preference['ilast'])) {
    $silast = $USER->preference['ilast'];
} else {
    $silast = 'all';
}

// Generate where clause
$where = array();
$where_params = array();

if ($sifirst !== 'all') {
    $where[] = $DB->sql_like('u.firstname', ':sifirst', false, false);
    $where_params['sifirst'] = $sifirst.'%';
}

if ($silast !== 'all') {
    $where[] = $DB->sql_like('u.lastname', ':silast', false, false);
    $where_params['silast'] = $silast.'%';
}

// Get user match count
$total = $completion->get_num_tracked_users(implode(' AND ', $where), $where_params, $group);

// Total user count
$grandtotal = $completion->get_num_tracked_users('', array(), $group);

// If no users in this course what-so-ever
if (!$grandtotal) {
    echo $OUTPUT->container(get_string('err_nousers', 'completion'), 'errorbox errorboxcontent');
    echo $OUTPUT->footer();
    exit;
}

// Get user data
$progress = array();

if ($total) {
    $progress = $completion->get_progress_all(
        implode(' AND ', $where),
        $where_params,
        $group,
        $firstnamesort ? 'u.firstname ASC' : 'u.lastname ASC',
        $export ? 0 : COMPLETION_REPORT_PAGE,
        $export ? 0 : $start,
        $context
    );
}

// Build link for paging
$link = $CFG->wwwroot.'/grade/report/course_complete/index.php?course='.$course->id;
if (strlen($sort)) {
    $link .= '&amp;sort='.$sort;
}
$link .= '&amp;start=';

$pagingbar = '';

// Initials bar.
$prefixfirst = 'sifirst';
$prefixlast = 'silast';
$pagingbar .= $OUTPUT->initials_bar($sifirst, 'firstinitial', get_string('firstname'), $prefixfirst, $url);
$pagingbar .= $OUTPUT->initials_bar($silast, 'lastinitial', get_string('lastname'), $prefixlast, $url);

// Do we need a paging bar?
if ($total > COMPLETION_REPORT_PAGE) {

    // Paging bar
    $pagingbar .= '<div class="paging">';
    $pagingbar .= get_string('page').': ';

    $sistrings = array();
    if ($sifirst != 'all') {
        $sistrings[] =  "sifirst={$sifirst}";
    }
    if ($silast != 'all') {
        $sistrings[] =  "silast={$silast}";
    }
    $sistring = !empty($sistrings) ? '&amp;'.implode('&amp;', $sistrings) : '';

    // Display previous link
    if ($start > 0) {
        $pstart = max($start - COMPLETION_REPORT_PAGE, 0);
        $pagingbar .= "(<a class=\"previous\" href=\"{$link}{$pstart}{$sistring}\">".get_string('previous').'</a>)&nbsp;';
    }

    // Create page links
    $curstart = 0;
    $curpage = 0;
    while ($curstart < $total) {
        $curpage++;

        if ($curstart == $start) {
            $pagingbar .= '&nbsp;'.$curpage.'&nbsp;';
        }
        else {
            $pagingbar .= "&nbsp;<a href=\"{$link}{$curstart}{$sistring}\">$curpage</a>&nbsp;";
        }

        $curstart += COMPLETION_REPORT_PAGE;
    }

    // Display next link
    $nstart = $start + COMPLETION_REPORT_PAGE;
    if ($nstart < $total) {
        $pagingbar .= "&nbsp;(<a class=\"next\" href=\"{$link}{$nstart}{$sistring}\">".get_string('next').'</a>)';
    }

    $pagingbar .= '</div>';
}

/*
 * Draw table header
 */

// Start of table

if (!$export) {
    $exporturl = new moodle_url('/grade/report/course_complete/index.php', array('course' => $course->id, 'export' => 1));
    print '<br class="clearer"/>'; // ugh

    $total_header = ($total == $grandtotal) ? $total : "{$total}/{$grandtotal}";
    echo $OUTPUT->heading(get_string('allparticipants').": {$total_header}", 3);

    echo '<div class="student-list-info d-flex justify-content-between align-items-center flex-column flex-sm-row flex-md-row flex-lg-row"><div>';
    echo '<h4>'. get_string('totallist', 'gradereport_final_score') . ': ' . $total_students . '</h4>';
    

    
    echo $pagingbar;
    echo '</div>';
    echo '<a href="'.$exporturl. '" class="btn btn-primary h-100">'.get_string('exportfile', 'gradereport_final_score').'</a>';
    echo '</div>';

    if (!$total) {
        echo $OUTPUT->notification(get_string('nothingtodisplay'), 'info', false);
        echo $OUTPUT->footer();
        exit;
    }

    print '<table id="completion-progress" class="table table-bordered generaltable flexible boxaligncenter
        completionreport" cellpadding="5" border="1">';

    // Print criteria group names
    print PHP_EOL.'<thead><tr style="vertical-align: top">';
    

    // Overall course completion status
    print '<th colspan="8" style="text-align: center;">' . get_string('course') . '</th>';


    print '</tr>';

    // Print aggregation methods
    print PHP_EOL.'<tr style="vertical-align: top">';
   
    print '</tr>';

    // Print criteria titles


    // Print user heading and icons
    print '<tr>';
    print '<th class="criteriaicon">';
    print 'STT';
    print '</th>';
    // User heading / sort option
    print '<th scope="col" class="completion-sortchoice" style="clear: both;">';
    
    $sistring = "&amp;silast={$silast}&amp;sifirst={$sifirst}";

    if ($firstnamesort) {
        print
            get_string('firstname')." / <a style='font-size:15px;' href=\"./index.php?course={$course->id}{$sistring}\">".
            get_string('lastname').'</a>';
    } else {
        print "<a style='font-size:15px;' href=\"./index.php?course={$course->id}&amp;sort=firstname{$sistring}\">".
            get_string('firstname').'</a> / '.
            get_string('lastname');
    }
    print '</th>';

    

    // Overall course completion status
    print '<th class="criteriaicon">';
    print 'Mã SV';
    print '</th>';

    print '<th class="criteriaicon">';
    print 'Sđt';
    print '</th>';

    print '<th class="criteriaicon">';
    print 'Email';
    print '</th>';

    print '<th class="criteriaicon">';
    print 'Môn';
    print '</th>';

    print '<th class="criteriaicon">';
    print 'Hoàn thành';
    print '</th>';


    print '<th class="criteriaicon">';
    print 'Tỉ lệ';
    print '</th>';
    print '</tr></thead>';

    echo '<tbody>';

    
    ///
    /// Display a row for each user
    ///
    $index = $start ?? 0;
    foreach ($progress as $user) {
        // Start user row
        $index++;
        
        print PHP_EOL . '<tr id="user-' . $user->id . '">';
        print '<td>' . $index . '</td>';
        // Determine URL for user details
        if (completion_can_view_data($user->id, $course)) {
            $userurl = new moodle_url('/blocks/completionstatus/details.php', array('course' => $course->id, 'user' => $user->id));
        } else {
            $userurl = new moodle_url('/user/view.php', array('id' => $user->id, 'course' => $course->id));
        }

        // Display user name with a link
        print '<th scope="row"><a href="' . $userurl->out() . '">' . fullname($user, has_capability('moodle/site:viewfullnames', $context)) . '</a></th>';

        // Initialize counters for completed criteria
        $total_criteria = count($criteria);
        $completed_criteria = 0;

        // Process each completion criterion
        foreach ($criteria as $criterion) {
            $criteria_completion = $completion->get_user_completion($user->id, $criterion);
            if ($criteria_completion->is_complete()) {
                $completed_criteria++;
            }
        }

        // Calculate completion percentage
        $completion_percentage = ($total_criteria > 0) ? ($completed_criteria / $total_criteria) * 100 : 0;
        print '<td>' . $user->username . '</td>';
        print '<td>' . ($user->phone1 ?? $user->phone2 ?? '_') . '</td>';
        print '<td>' . ($user->email ?? '_') . '</td>';
        print '<td>' . str_replace("Bài giảng môn học: ", "", $course->summary) . '</td>';
        // Display the number of completed criteria and completion percentage
        print '<td>' . $completed_criteria . '/' . $total_criteria . '</td>';
        print '<td>' . (int) $completion_percentage . '%</td>';

        // Handle overall course completion
        $params = array('userid' => $user->id, 'course' => $course->id);
        $ccompletion = new completion_completion($params);
        

        // End user row
        print '</tr>';
    }



    echo '</tbody>';


    print '</table>';

    

    

    echo $OUTPUT->footer($course);
} 
else{

    
    gradereport_course_complete\lib::createExcel($course, $progress, $criteria);
    $event = \report_completion\event\report_viewed::create(array('context' => $context));
    $event->trigger();
}



