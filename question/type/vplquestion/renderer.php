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
 * Vplquestion renderer class.
 * @package    qtype_vplquestion
 * @copyright  Astor Bizard, 2019
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/locallib.php');

/**
 * Generates HTML output for vplquestion.
 * @copyright  Astor Bizard, 2019
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_vplquestion_renderer extends qtype_renderer {

    /**
     * {@inheritDoc}
     * @param question_attempt $qa the question attempt to display.
     * @param question_display_options $options controls what should and should not be displayed.
     * @see qtype_renderer::formulation_and_controls()
     */
    public function formulation_and_controls(question_attempt $qa, question_display_options $options) {

        $question = $qa->get_question();

        global $USER, $COURSE;
        $userid = $USER->id;
        $qid = $question->id;
        $vplid = $question->templatevpl;

        $inputname = $qa->get_qt_field_name('answer');
        $lastanswer = $qa->get_last_qt_var('answer');
        if ($lastanswer == null) {
            $lastanswer = $question->answertemplate;
        }

        $html = parent::formulation_and_controls($qa, $options) . $this->output->box_start();

        global $CFG;
        require_once($CFG->dirroot .'/mod/vpl/vpl.class.php');
        try {
            $vpl = new mod_vpl($vplid);
            list($course, $cm) = get_course_and_cm_from_cmid($vplid, 'vpl');
            if ($course->id != $COURSE->id) {
                $html .= $this->output->notification(get_string('vplnotincoursewarning', QVPL), 'warning');
            } else if (!$cm->visible) {
                $html .= $this->output->notification(get_string('vplnotavailablewarning', QVPL), 'warning');
            }
        } catch (moodle_exception $e) {
            // Something went wrong instantiating the VPL, the question is badly configured.
            $html .= $this->output->notification(get_string('vplnotfounderror', QVPL, $e->getMessage()), 'error');
            return $html . $this->output->box_end();
        }

        $vplversion = qtype_vplquestion_get_mod_vpl_version();

        $this->output->page->requires->strings_for_js(
                [
                    'closerecievednoretrieve',
                    'compilation',
                    'execution',
                    'evaluation',
                    'evaluationerror',
                    'execerror',
                    'editoroptions',
                ],
                QVPL);
        $this->output->page->requires->strings_for_js([ 'savechanges', 'cancel' ], 'moodle');
        $this->output->page->requires->js_call_amd(QVPL.'/studentanswer', 'setup',
                [ $qid, $vplid, $userid, $inputname, $vplversion ]);

        // Find the line where the {{ANSWER}} tag is located, to offset line numbers on Ace editor.
        // This offset is useful for compilation errors, so that error line will match editor line.
        $lineoffset = 1;
        foreach (explode("\n", $question->templatecontext) as $index => $line) {
            if (strpos($line, "{{ANSWER}}") !== false) {
                $lineoffset = $index + 1;
            }
        }

        $templatecontext = new stdClass();
        $templatecontext->qid = $qid;
        $templatecontext->readonly = $options->readonly;
        $templatecontext->inputname = $inputname;
        $templatecontext->lineoffset = $lineoffset;
        $templatecontext->templatelang = $question->templatelang;
        $templatecontext->lastanswer = $lastanswer;
        $templatecontext->run = $vpl->get_instance()->run;
        $templatecontext->precheck = $question->precheckpreference != 'none';
        $templatecontext->precheckaction = $question->precheckpreference == 'dbg' ? 'debug' : 'evaluate';
        $templatecontext->answertemplate = $question->answertemplate;
        $templatecontext->correction = has_capability('moodle/course:update', context_course::instance($COURSE->id));
        $templatecontext->teachercorrection = $question->teachercorrection;
        $templatecontext->installedthemes = [];
        foreach (qtype_vplquestion_get_ace_themes() as $id => $name) {
            $templatecontext->installedthemes[] = (object) [ 'id' => $id, 'name' => $name ];
        }
        $html .= $this->output->render_from_template('qtype_vplquestion/question', $templatecontext);

        $html .= $this->output->box_end();

        return $html;
    }

    /**
     * {@inheritDoc}
     * @param question_attempt $qa the question attempt to display.
     * @see qtype_renderer::specific_feedback()
     */
    public function specific_feedback(question_attempt $qa) {
        $feedback = '';
        if ($qa->get_state()->is_finished()) {
            $feedback = '<div class="correctness '.$qa->get_state_class(true).' badge text-white">'.
                            $qa->get_state()->default_string(true).
                        '</div>';
        }
        if ($qa->get_state()->is_graded()) {
            $evaldata = $qa->get_last_qt_var('_evaldata', null);
            if ($evaldata === null) {
                // In older versions (<= 2021070700), evaluation data was stored as response summary.
                // Keep this piece of code to handle old question attempts.
                $evaldata = $qa->get_response_summary();
            }
            $displayid = 'vpl_eval_details_q'.$qa->get_question()->id;
            $feedback .= '<div class="m-t-1">
                            <h5>'.get_string('evaluationdetails', QVPL).'</h5>
                            <pre id="'.$displayid.'" class="bg-white p-2 border"
                                data-result="'.htmlspecialchars($evaldata).'">
                            </pre>
                         </div>';
            $this->output->page->requires->js_call_amd(QVPL.'/studentanswer', 'displayResult', [ $displayid, null ]);
        }
        return $feedback;
    }

    /**
     * {@inheritDoc}
     * @param question_attempt $qa the question attempt to display.
     * @see qtype_renderer::correct_response()
     */
    public function correct_response(question_attempt $qa) {
        if (!$qa->get_question()->teachercorrection) {
            return '';
        }
        return '<h5>'.get_string('possiblesolution', QVPL).'</h5>'.
               '<pre class="line-height-3">'.htmlspecialchars($qa->get_question()->teachercorrection).'</pre>';
    }
}
