<?php


require_once '../../../config.php';
$PAGE->set_url(new moodle_url('/grade/report/learning_results/index.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('learning_results', 'gradereport_learning_results')); 
$PAGE->set_heading(get_string('learning_results', 'gradereport_learning_results')); 
$PAGE->set_pagelayout('standard');

require_login();


$PAGE->theme->addblockposition  = BLOCK_ADDBLOCK_POSITION_CUSTOM;
?>


<script type="text/javascript">
    var Moodle = {
        url: '<?php echo $CFG->wwwroot; ?>',
        token: '<?php echo $token; ?>' 
    };
</script>


<?php 

$PAGE->requires->jquery(); 
$PAGE->requires->js(new moodle_url('../learning_results/amd/src/learning.js'));
$PAGE->requires->css(new moodle_url('../learning_results/amd/styles/learning.css'));


// Get the output renderer for the local plugin
$output = $PAGE->get_renderer('gradereport_learning_results');

echo $OUTPUT->header();
echo $output->render_filters();
echo $output->display_report_table();

echo $OUTPUT->footer();

