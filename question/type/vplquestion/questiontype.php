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
 * Question type class for the vplquestion question type.
 * @package    qtype_vplquestion
 * @copyright  Astor Bizard, 2019
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__.'/locallib.php');

/**
 * The vplquestion type class.
 * @copyright  Astor Bizard, 2019
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_vplquestion extends question_type {

    /**
     * {@inheritDoc}
     * @see question_type::extra_question_fields()
     */
    public function extra_question_fields() {
        return [ "question_vplquestion",
            "templatevpl",
            "templatelang",
            "templatecontext",
            "answertemplate",
            "teachercorrection",
            "validateonsave",
            "execfiles",
            "precheckpreference",
            "precheckexecfiles",
            "gradingmethod",
        ];
    }

    /**
     * Imports question from the Moodle XML format.
     *
     * This function uses the default behavior and checks that template VPL is valid.
     *
     * @param mixed $data
     * @param mixed $question
     * @param qformat_xml $format
     * @param mixed|null $extra
     *
     * @see question_type::import_from_xml()
     */
    public function import_from_xml($data, $question, qformat_xml $format, $extra=null) {
        $importdata = parent::import_from_xml($data, $question, $format, $extra);
        global $COURSE, $OUTPUT;
        try {
            if (get_course_and_cm_from_cmid($importdata->templatevpl, 'vpl')[0]->id != $COURSE->id) {
                echo $OUTPUT->notification(get_string('cannotimportquestionvplunreachable', QVPL, $importdata->name), 'warning');
            }
        } catch (moodle_exception $e) {
            echo $OUTPUT->notification(get_string('cannotimportquestionvplnotfound', QVPL, $importdata->name), 'warning');
        }
        return $importdata;
    }
}
