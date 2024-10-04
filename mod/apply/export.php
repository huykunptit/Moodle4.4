<?php

/**
 * exports the entries
 *
 * @author  Fumi.Iseki
 * @license GNU Public License
 * @package mod_apply (modified from mod_feedback that by Andreas Grabs)
 */

require_once('../../config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/jbxl/jbxl_moodle_tools.php');
require_once($CFG->libdir.'/tablelib.php');


////////////////////////////////////////////////////////
//get the params
$id       = required_param('id', PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$do_show  = optional_param('do_show', 'export', PARAM_ALPHAEXT);
$show_all = optional_param('show_all', 0, PARAM_INT);
$action   = optional_param('action', '', PARAM_ALPHAEXT);

$current_tab = $do_show;
$this_action = 'export';


////////////////////////////////////////////////////////
//get the objects
if (! $cm = get_coursemodule_from_id('apply', $id)) {
    print_error('invalidcoursemodule');
}
if (! $course = $DB->get_record('course', array('id'=>$cm->course))) {
    print_error('coursemisconf');
}
if (! $apply = $DB->get_record('apply', array('id'=>$cm->instance))) {
    print_error('invalidcoursemodule');
}
if (!$courseid) $courseid = $course->id;

$req_own_data = false;
$name_pattern = $apply->name_pattern;
$context = context_module::instance($cm->id);


////////////////////////////////////////////////////////
// Check
require_login($course, true, $cm);
require_capability('mod/apply:viewreports', $context);


///////////////////////////////////////////////////////////////////////////
// URL
$base_url = new moodle_url('/mod/apply/'.$this_action.'.php');
$base_url->params(array('id'=>$id, 'courseid'=>$courseid, 'show_all'=>$show_all));
//
$this_url = new moodle_url($base_url);
$this_url->params(array('do_show'=>$do_show));
//
$back_url = new moodle_url('/mod/apply/view.php');
$back_url->params(array('id'=>$id, 'courseid'=>$courseid, 'do_show'=>'view_entries', 'show_all'=>$show_all));

$export_url = new moodle_url('/mod/apply/'.$this_action.'.php');


////////////////////////////////////////////////////////
/// Print the page header
$PAGE->navbar->add(get_string('apply:viewentries', 'apply'));
$PAGE->set_url($this_url);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_title(format_string($apply->name));

//
$cap_view_hidden_activities = has_capability('moodle/course:viewhiddenactivities', $context);
if ((empty($cm->visible) and !$cap_view_hidden_activities)) {
    notice(get_string('activityiscurrentlyhidden'));
}
if ((empty($cm->visible) and !$cap_view_hidden_activities)) {
    notice(get_string('activityiscurrentlyhidden'));
}


///////////////////////////////////////////////////////////////////////////
// export_enties

if ($action=='excel' or $action=='text') {
    //
    $submits = apply_get_submits_select($apply->id, 0, 'version>0 AND', null, 'user_id DESC', false, false);
    if ($submits) {
        $datas = new stdClass();
        $datas->attr = array();    // 属性 'string', 'number'. デフォルトは 'string'
        $datas->data = array();
        $apply_id = reset($submits)->apply_id;

        // Names (first line)
        $where  = 'apply_id=? AND hasvalue=? AND position>?';
        $params = array($apply_id, 1, 0);
        $apply_items = $DB->get_records_select('apply_item', $where, $params, 'position');

        $j = 0;
        $items = array();
        $fmts  = array();
        $vrsns = array();
        $datas->attr[0] = array();
        $datas->data[0] = array();
        foreach($apply_items as $val) {
            $datas->data[0][$j] = $val->name;
            $datas->attr[0][$j] = 'string';
            $items[$j] = $val->id;
            if ($val->typ=='numeric' or $val->typ=='multichoice') $fmts[$j] = 'number';
            else                                                  $fmts[$j] = 'string';
            $j++;
        }
        $datas->data[0][$j] = get_string('title_ack', 'apply');
        $datas->attr[0][$j] = 'string';
        $j++;
        $datas->data[0][$j] = get_string('title_exec', 'apply');
        $datas->attr[0][$j] = 'string';
        
        // Values
        $i = 1;
        foreach($submits as $submit) {
            $j = 0;
            foreach($items as $item) {
                $params = array('submit_id'=>$submit->id, 'item_id'=>$item, 'version'=>$submit->version);
                $value  = $DB->get_record('apply_value', $params);
                $datas->data[$i][$j] = $value->value;
                $datas->attr[$i][$j] = $fmts[$j];
                $j++;
            }
            $datas->data[$i][$j] = jbxl_get_user_name($submit->acked_user, $apply->name_pattern);
            $datas->attr[$i][$j] = 'string';
            $j++;
            $datas->data[$i][$j] = jbxl_get_user_name($submit->execd_user, $apply->name_pattern);
            $datas->attr[$i][$j] = 'string';
            $i++;
        }

        // Download
        if($action=='excel') {
            jbxl_download_data('xlsx', $datas);
            die();
        }
        else if($action=='text') {
            jbxl_download_data('txt', $datas);
            die();
        }
    }
}


///////////////////////////////////////////////////////////////////////////
echo $OUTPUT->header();

require('tabs.php');
require('html/download.html');

$back_button = $OUTPUT->single_button($back_url->out(), get_string('back_button', 'apply'));
echo '<br />';
echo '<div align="center">';
echo '<table border="0">';
echo '<tr>';
echo '<td>'.$back_button.'</td>';
echo '</tr>';
echo '</table>';
echo '</div>';

///////////////////////////////////////////////////////////////////////////
/// Finish the page
echo $OUTPUT->footer();

