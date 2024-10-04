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

require_once '../../config.php';

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

$url = new moodle_url('/report/academic_advisor/index.php', array('course'=>$course->id));
$PAGE->set_url($url);
$PAGE->set_pagelayout('report');

global $USER;


// Paging
$start   = optional_param('start', 0, PARAM_INT);
$sifirst = optional_param('sifirst', 'all', PARAM_NOTAGS);
$silast  = optional_param('silast', 'all', PARAM_NOTAGS);

// Whether to show extra user identity information.
$extrafields = \core_user\fields::get_identity_fields($context, true);
$leftcols = 1 + count($extrafields);

// Check permissions
require_login($course);

require_capability('report/academic_advisor:view', $context);

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
$in = new completion_info($course);
if (!$completion->has_criteria()) {

    notice(
        get_string('nocriteriaset', 'report_academic_advisor'), 
        $url 
    );

}

// $criteria = array();  // Initialize an empty array for criteria

//     // Fetch and combine course criteria
//     foreach ($completion->get_criteria(COMPLETION_CRITERIA_TYPE_COURSE) as $criterion) {
//         $criteria[] = $criterion;
//     }

//     // Fetch and combine activity criteria
//     foreach ($completion->get_criteria(COMPLETION_CRITERIA_TYPE_ACTIVITY) as $criterion) {
//         $criteria[] = $criterion;
//     }

//     // Fetch any remaining criteria that are not course or activity criteria
//     foreach ($completion->get_criteria() as $criterion) {
//         if (!in_array($criterion->criteriatype, array(
//                 COMPLETION_CRITERIA_TYPE_COURSE, COMPLETION_CRITERIA_TYPE_ACTIVITY))) {
//             $criteria[] = $criterion;
//         }
//     }


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
    $pluginname = get_string('pluginname', 'report_academic_advisor');
    report_helper::print_report_selector($pluginname);

    // Handle groups (if enabled)
    groups_print_course_menu($course, $CFG->wwwroot.'/report/academic_advisor/index.php?course='.$course->id);

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
$link = $CFG->wwwroot.'/report/academic_advisor/index.php?course='.$course->id;
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
    $exporturl = new moodle_url('/report/academic_advisor/download.php', array('course' => $course->id, 'export' => 1));
    print '<br class="clearer"/>'; // ugh

    $total_header = ($total == $grandtotal) ? $total : "{$total}/{$grandtotal}";
    echo $OUTPUT->heading(get_string('allparticipants').": {$total_header}", 3);

    echo '<div class="student-list-info d-flex justify-content-between align-items-center flex-column flex-sm-row flex-md-row flex-lg-row"><div>';
    

    
    echo $pagingbar;
    echo '</div>';
    echo '<a href="'.$exporturl. '" class="btn btn-primary h-100">'.get_string('exportfile', 'report_academic_advior').'</a>';
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
    print '<th colspan="10" class="text-center">' . get_string('course') . ': '.$course->fullname.'</th>';


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
    print 'Ngày tháng năm sinh';
    print '</th>';


    print '<th class="criteriaicon">';
    print 'Số điện thoại';
    print '</th>';

    print '<th class="criteriaicon">';
    print 'Email';
    print '</th>';

    print '<th class="criteriaicon">';
    print 'Môn';
    print '</th>';

    


    print '<th class="criteriaicon">';
    print 'Phần trăm hoàn thành môn học';
    print '</th>';

    print '<th class="criteriaicon ">';
    print 'Các mục chưa hoàn thành';
    print '</th>';


    print '<th class="criteriaicon">';
    print 'Lần cuối truy cập hệ thống';
    print '</th>';
    print '</tr></thead>';

    echo '<tbody>';

    
    ///
    /// Display a row for each user
    ///
    
    $index = $start ?? 0;
    foreach ($progress as $user) {
        if($user){
        $user_info = $DB->get_record('user',['id'=>$user->id]);
    

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


        
        
        // Save row data.
        $rows = array();


        $completions = $in->get_completions($user->id);


        $completed_criteria = 0;
        $incomplete_criteria = []; 
        $total_criteria = count($completions);
        // Loop through course criteria.
        foreach ($completions as $completion) {
            $criteria = $completion->get_criteria();

            $row = array();
           
            if ($completion->is_complete()) {
                $completed_criteria++;
            }
            else{
                $row['details'] = $criteria->get_details($completion);
            }

            $rows[] = $row; 
        }
        
        $birthday = profile_user_record($user->id);
        $completion_percentage = ($total_criteria > 0) ? ($completed_criteria / $total_criteria) * 100 : 0;
        $last_access = $DB->get_field('user_lastaccess', 'timeaccess', ['userid' => $user->id, 'courseid' => $course->id]);

        print '<td>' . $user->username . '</td>';
        if($birthday->dob){
            print '<td>' . date('d-m-Y', $birthday->dob) . '</td>';
        }
        else{
            print '<td class="text-center" > '.$USER->dob.'</td>';
        }
        
        $phone = !empty($user_info->phone1) ? $user_info->phone1 : (!empty($user_info->phone2) ? $user_info->phone2 : 'No phone available');
        print '<td class="text-center">' . $phone . '</td>';

        print '<td class="text-center">' . ($user_info->email ?? '_') . '</td>';
        print '<td class="text-left">' . str_replace("Bài giảng môn học: ", "", $user_info->department) . '</td>';
        print '<td class="text-center">' . round($completion_percentage,2)  . '%</td>';

        $criteria_output = [];

        foreach ($rows as $row) {
            if (!empty($row['details']['criteria'])) {
                if (is_array($row['details']['criteria'])) {
                    $criteria_output = array_merge($criteria_output, $row['details']['criteria']);
                } else {
                    $criteria_output[] = htmlspecialchars($row['details']['criteria']);
                }
            }
        }
        
        if (!empty($criteria_output)) {
            // Start the table cell with the max-width style
            echo '<td style="max-width:550px;">';
        
            // Start the unordered list with a unique ID for JavaScript
            echo '<ul id="criteria-list-' . $user->id . '">';
        
            // Display the first 3 items
            for ($i = 0; $i < count($criteria_output); $i++) {
                // Only show the first 3 items initially
                if ($i < 3) {
                    echo '<li>' . html_entity_decode($criteria_output[$i]) . '</li>';
                } else {
                    // Hide the remaining items by default
                    echo '<li class="extra-criteria" style="display:none;">' . html_entity_decode($criteria_output[$i]) . '</li>';
                }
            }
        
            echo '</ul>';
        
            // If there are more than 3 items, show the "Show more..." link
            if (count($criteria_output) > 3) {
                echo '<a href="#" id="show-more-' . $user->id . '" onclick="toggleCriteria(' . $user->id . '); return false;">Show more...</a>';
            }
        
            echo '</td>';
        } else {
            echo '<td>All Completed</td>';
        }
        

        print '<td class="text-center">' . date('d-m-Y H:i:s', $last_access) . '</td>';
    
        // End user row
        print '</tr>';
        }
    }

    echo '</tbody>';
    print '</table>';
    echo '
    <script>
    function toggleCriteria(userId) {
        // Get the hidden list items and the "Show more..." link for the specific user
        const extraItems = document.querySelectorAll("#criteria-list-" + userId + " .extra-criteria");
        const showMoreLink = document.getElementById("show-more-" + userId);

        // Toggle visibility of the hidden items
        extraItems.forEach(item => {
            if (item.style.display === "none") {
                item.style.display = "list-item";
            } else {
                item.style.display = "none";
            }
        });

        // Change the link text to "Show less..." or "Show more..."
        if (showMoreLink.innerText === "Show more...") {
            showMoreLink.innerText = "Show less...";
        } else {
            showMoreLink.innerText = "Show more...";
        }
    }
</script>

    
    
    ';
    echo $OUTPUT->footer($course);
} 
else{

    
    report_academic_advisor\lib::createExcel($course, $progress);
    $event = \report_completion\event\report_viewed::create(array('context' => $context));
    $event->trigger();
}



