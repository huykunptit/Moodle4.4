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
 * Vplquestion definition class.
 * @package    qtype_vplquestion
 * @copyright  Astor Bizard, 2019
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__.'/../../../config.php');
require_once(__DIR__.'/locallib.php');

require_login();

/**
 * Represents a vplquestion.
 * @copyright  Astor Bizard, 2019
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_vplquestion_question extends question_graded_automatically {

    /**
     * Question attempt step.
     * @var question_attempt_step $step
     */
    private $step = null;

    /**
     * {@inheritDoc}
     * @see question_definition::get_expected_data()
     */
    public function get_expected_data() {
        return [ 'answer' => PARAM_RAW ];
    }

    /**
     * {@inheritDoc}
     * @see question_definition::get_correct_response()
     */
    public function get_correct_response() {
        return [ 'answer' => $this->teachercorrection ];
    }

    /**
     * Wrapper to get the answer in a response object, handling unset variable.
     * @param array $response the response object, as defined in get_expected_data().
     * @return string the answer
     */
    private function get_answer(array $response) {
        return isset($response['answer']) ? $response['answer'] : '';
    }

    /**
     * {@inheritDoc}
     * @param array $response a response, as defined in get_expected_data().
     * @see question_manually_gradable::summarise_response()
     */
    public function summarise_response(array $response) {
        return str_replace("\r", "", $this->get_answer($response));
    }

    /**
     * {@inheritDoc}
     * @param array $response a response, as defined in get_expected_data().
     * @see question_manually_gradable::is_complete_response()
     */
    public function is_complete_response(array $response) {
        return $this->get_answer($response) != $this->answertemplate;
    }

    /**
     * {@inheritDoc}
     * @param array $response a response, as defined in get_expected_data().
     * @see question_automatically_gradable::get_validation_error()
     */
    public function get_validation_error(array $response) {
        if ($this->is_gradable_response($response)) {
            return '';
        }
        return get_string('pleaseanswer', QVPL);
    }

    /**
     * {@inheritDoc}
     * @param array $prevresponse the response previously recorded for this question, as defined in get_expected_data().
     * @param array $newresponse the new response, in the same format.
     * @see question_manually_gradable::is_same_response()
     */
    public function is_same_response(array $prevresponse, array $newresponse) {
        return question_utils::arrays_same_at_key_missing_is_blank($prevresponse, $newresponse, 'answer');
    }

    /**
     * {@inheritDoc}
     * @param question_attempt_step $step The first step of the question_attempt being started. Can be used to store state.
     * @param int $variant which variant of this question to start. Will be between 1 and get_num_variants(), inclusive.
     * @see question_definition::start_attempt()
     */
    public function start_attempt(question_attempt_step $step, $variant) {
        parent::start_attempt($step, $variant);
        // Store initial attempt step, to save evaluation details as a qt var in grade_response().
        $this->step = $step;
    }

    /**
     * {@inheritDoc}
     * @param question_attempt_step $step The first step of the question_attempt being loaded.
     * @see question_definition::apply_attempt_state()
     */
    public function apply_attempt_state(question_attempt_step $step) {
        parent::apply_attempt_state($step);
        // Store attempt step, to save evaluation details as a qt var in grade_response().
        $this->step = $step;
    }

    /**
     * {@inheritDoc}
     * @param array $response a response, as defined in get_expected_data().
     * @see question_automatically_gradable::grade_response()
     */
    public function grade_response(array $response) {
        global $DB;
        $deletesubmissions = get_config(QVPL, 'deletevplsubmissions') == '1';
        $result = qtype_vplquestion_evaluate($this->get_answer($response), $this, $deletesubmissions);
        $vplresult = $result->vplresult;
        $grade = qtype_vplquestion_extract_fraction($vplresult, $this->templatevpl);

        if ($grade !== null) {
            if ($this->gradingmethod == 0) {
                // All or nothing.
                $grade = floor($grade);
            }
            $gradingresult = [ $grade, question_state::graded_state_for_fraction($grade) ];
        } else {
            // No grade obtained. Something went wrong, display a message as explicit as possible.
            $vplresult->evaluationerror = qtype_vplquestion_make_evaluation_error_message($result, 'student');
            $gradingresult = [ 0, question_state::$gradedwrong ];
        }

        if ($this->step !== null) {
            // Store evaluation details as a qt var of initial attempt step,
            // to retrieve it from renderer (in order display the details from the renderer).
            $newvalue = json_encode($vplresult);
            if ($this->step instanceof question_attempt_step_read_only) {
                // The step is readonly, which means this is a standard attempt.
                // In that case, we store evaluation data directly in database.
                $table = 'question_attempt_step_data';
                $params = [ 'attemptstepid' => $this->step->get_id(), 'name' => '_evaldata' ];
                $currentrecord = $DB->get_record($table, $params);
                if ($currentrecord === false) {
                    $newrecord = array_merge($params, [ 'value' => $newvalue ]);
                    $DB->insert_record($table, $newrecord, false);
                } else {
                    $currentrecord->value = $newvalue;
                    $DB->update_record($table, $currentrecord);
                }
            } else {
                // The step is not readonly, which usually means this is a regrade.
                // In that case, cached qt data will be inserted in database, so we store evaluation data in a cached qt var.
                $this->step->set_qt_var('_evaldata', $newvalue);
            }
        }

        return $gradingresult;
    }
}
